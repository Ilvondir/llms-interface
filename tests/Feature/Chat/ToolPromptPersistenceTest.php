<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolPromptPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_persist_assistant_tool_calls_and_tool_results(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('conversations.prompts.store', $conversation), [
            'role' => 'user',
            'content' => 'Search something',
        ])->assertOk();

        $toolCalls = [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => [
                'name' => 'exa__web_search_exa',
                'arguments' => '{"query":"Wojciech Galka"}',
            ],
        ]];

        $assistant = $this->actingAs($user)->postJson(route('conversations.prompts.store', $conversation), [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => $toolCalls,
            'model' => 'demo',
        ]);
        $assistant->assertOk();

        $tool = $this->actingAs($user)->postJson(route('conversations.prompts.store', $conversation), [
            'role' => 'tool',
            'tool_call_id' => 'call_1',
            'content' => '{"results":["hit"]}',
        ]);
        $tool->assertOk();

        $assistantPrompt = Prompt::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->whereNotNull('tool_calls')
            ->first();

        $this->assertNotNull($assistantPrompt);
        $this->assertSame('exa__web_search_exa', $assistantPrompt->tool_calls[0]['function']['name']);

        $toolPrompt = Prompt::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'tool')
            ->first();

        $this->assertNotNull($toolPrompt);
        $this->assertSame('call_1', $toolPrompt->tool_call_id);
        $this->assertSame('{"results":["hit"]}', $toolPrompt->content);

        $messages = $tool->json('activeConversation.messages');
        $this->assertTrue(collect($messages)->contains(
            fn (array $message): bool => ($message['role'] ?? null) === 'tool'
                && ($message['toolCallId'] ?? null) === 'call_1',
        ));
        $this->assertTrue(collect($messages)->contains(
            fn (array $message): bool => ($message['role'] ?? null) === 'assistant'
                && isset($message['toolCalls']),
        ));
    }
}
