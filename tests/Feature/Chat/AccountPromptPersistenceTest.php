<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountPromptPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_store_and_update_prompts_with_params_and_reasoning(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $storeUser = $this->actingAs($user)->post(route('conversations.prompts.store', $conversation), [
            'role' => 'user',
            'content' => 'Hello',
            'sent_at' => 1_700_000_000_000,
        ]);
        $storeUser->assertOk();

        $userPrompt = Prompt::query()->where('conversation_id', $conversation->id)->where('role', 'user')->first();
        $this->assertNotNull($userPrompt);
        $this->assertSame(1, $userPrompt->position);

        $storeAssistant = $this->actingAs($user)->post(route('conversations.prompts.store', $conversation), [
            'role' => 'assistant',
            'content' => 'Hi',
            'reasoning' => 'step',
            'params' => [
                'temperature' => 0.2,
                'max_tokens' => 128,
                'top_p' => 0.9,
            ],
            'stats' => ['ttftMs' => 42],
            'model' => 'local-model',
            'request_payload' => ['model' => 'local-model'],
            'received_at' => 1_700_000_000_500,
        ]);
        $storeAssistant->assertOk();

        $assistant = Prompt::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->first();
        $this->assertNotNull($assistant);
        $this->assertSame(2, $assistant->position);
        $this->assertSame('step', $assistant->reasoning);
        $this->assertSame(0.2, $assistant->params['temperature']);

        $update = $this->actingAs($user)->patch(
            route('conversations.prompts.update', [$conversation, $assistant]),
            [
                'content' => 'Hi partial',
                'error' => 'upstream failed',
                'reasoning' => 'partial think',
            ],
        );
        $update->assertOk();

        $assistant->refresh();
        $this->assertSame('Hi partial', $assistant->content);
        $this->assertSame('upstream failed', $assistant->error);
        $this->assertSame('partial think', $assistant->reasoning);
    }

    #[Test]
    public function authenticated_user_can_store_assistant_prompt_with_empty_content_and_error(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)->post(route('conversations.prompts.store', $conversation), [
            'role' => 'user',
            'content' => 'Hello',
        ])->assertOk();

        $storeFailedAssistant = $this->actingAs($user)->postJson(route('conversations.prompts.store', $conversation), [
            'role' => 'assistant',
            'content' => '',
            'error' => 'Connection refused',
            'model' => 'local-model',
        ]);

        $storeFailedAssistant->assertOk();

        $assistant = Prompt::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->first();
        $this->assertNotNull($assistant);
        $this->assertSame('', $assistant->content);
        $this->assertSame('Connection refused', $assistant->error);
    }

    #[Test]
    public function guest_cannot_store_prompts(): void
    {
        $owner = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();

        $this->post(route('conversations.prompts.store', $conversation), [
            'role' => 'user',
            'content' => 'Nope',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('prompts', 0);
    }

    #[Test]
    public function authenticated_user_can_store_image_parts_and_presenter_returns_array(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $tinyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $parts = [
            ['type' => 'text', 'text' => 'Describe'],
            ['type' => 'image_url', 'image_url' => ['url' => $tinyPng]],
        ];

        $response = $this->actingAs($user)->postJson(route('conversations.prompts.store', $conversation), [
            'role' => 'user',
            'content' => $parts,
        ]);

        $response->assertOk();

        $prompt = Prompt::query()->where('conversation_id', $conversation->id)->where('role', 'user')->first();
        $this->assertNotNull($prompt);
        $this->assertJsonStringEqualsJsonString(json_encode($parts), $prompt->content);

        $presented = $response->json('activeConversation.messages.0.content');
        $this->assertIsArray($presented);
        $this->assertSame('Describe', $presented[0]['text']);
        $this->assertSame($tinyPng, $presented[1]['image_url']['url']);
    }

    #[Test]
    public function request_payload_with_raw_image_bytes_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $hugeImage = 'data:image/png;base64,'.str_repeat('A', 120_000);

        $this->actingAs($user)
            ->postJson(route('conversations.prompts.store', $conversation), [
                'role' => 'assistant',
                'content' => 'Hi',
                'request_payload' => [
                    'model' => 'demo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'image_url', 'image_url' => ['url' => $hugeImage]],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_payload']);
    }

    #[Test]
    public function sanitized_request_payload_without_image_bytes_is_accepted(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('conversations.prompts.store', $conversation), [
                'role' => 'assistant',
                'content' => 'Hi',
                'request_payload' => [
                    'model' => 'demo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => '[image omitted]',
                        ],
                    ],
                    'stream' => true,
                ],
            ])
            ->assertOk();

        $prompt = Prompt::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->first();
        $this->assertNotNull($prompt);
        $this->assertSame('[image omitted]', $prompt->request_payload['messages'][0]['content']);
    }
}
