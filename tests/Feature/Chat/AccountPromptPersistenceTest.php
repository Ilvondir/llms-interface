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
}
