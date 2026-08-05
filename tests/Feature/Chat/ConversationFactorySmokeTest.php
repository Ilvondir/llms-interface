<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserChatSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationFactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function conversation_and_prompt_factories_persist_for_a_user(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::factory()->for($user)->create([
            'title' => 'Smoke chat',
            'model' => 'local-model',
        ]);

        $prompt = Prompt::factory()->for($conversation)->create([
            'role' => 'user',
            'content' => 'Hello',
            'position' => 1,
        ]);

        $assistant = Prompt::factory()->for($conversation)->assistant()->create([
            'content' => 'Hi there',
            'position' => 2,
            'reasoning' => 'thinking',
            'stats' => ['ttftMs' => 12],
        ]);

        $settings = UserChatSettings::factory()->for($user)->create([
            'api_base_url' => 'http://localhost:1234',
            'active_conversation_id' => $conversation->id,
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Smoke chat',
        ]);

        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'position' => 1,
        ]);

        $this->assertDatabaseHas('prompts', [
            'id' => $assistant->id,
            'role' => 'assistant',
            'position' => 2,
        ]);

        $this->assertDatabaseHas('user_chat_settings', [
            'id' => $settings->id,
            'user_id' => $user->id,
            'active_conversation_id' => $conversation->id,
        ]);

        $this->assertTrue($user->conversations()->whereKey($conversation)->exists());
        $this->assertNotNull($user->chatSettings);
        $this->assertCount(2, $conversation->fresh()->prompts);
    }

    #[Test]
    public function deleting_a_user_cascades_conversations_prompts_and_settings(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        Prompt::factory()->for($conversation)->create(['position' => 1]);
        UserChatSettings::factory()->for($user)->create([
            'active_conversation_id' => $conversation->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('prompts', 0);
        $this->assertDatabaseCount('user_chat_settings', 0);
    }
}
