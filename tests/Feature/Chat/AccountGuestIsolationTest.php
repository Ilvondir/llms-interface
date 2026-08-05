<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountGuestIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_stream_does_not_create_conversation_or_prompt_rows(): void
    {
        Http::preventStrayRequests();

        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hi\"}}]}\n\n"."data: [DONE]\n\n";

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ])->assertOk()->assertStreamed();

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('prompts', 0);
        $this->assertDatabaseCount('user_chat_settings', 0);
    }

    #[Test]
    public function authenticated_home_lists_only_own_conversations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = Conversation::factory()->for($user)->create(['title' => 'Mine']);
        Conversation::factory()->for($other)->create(['title' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Chat/Index')
                ->has('conversations', 1)
                ->where('conversations.0.id', $own->id)
                ->where('conversations.0.title', 'Mine')
            );
    }

    #[Test]
    public function authenticated_json_prompt_store_persists_params_and_reasoning(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('conversations.prompts.store', $conversation), [
                'role' => 'assistant',
                'content' => 'Answer',
                'reasoning' => 'why',
                'params' => [
                    'temperature' => 0.4,
                    'max_tokens' => 64,
                    'top_p' => 0.8,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('activeConversation.id', $conversation->id);

        $this->assertDatabaseHas('prompts', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Answer',
            'reasoning' => 'why',
        ]);

        $prompt = Prompt::query()->where('conversation_id', $conversation->id)->first();
        $this->assertSame(0.4, $prompt->params['temperature']);
    }
}
