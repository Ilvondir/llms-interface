<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\User;
use App\Support\Chat\ChatContentLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountConversationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_view_or_mutate_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create(['title' => 'Private']);

        $this->actingAs($intruder)
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->patch(route('conversations.update', $conversation), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('conversations.destroy', $conversation))
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'title' => 'Private',
        ]);
    }

    #[Test]
    public function user_cannot_store_or_update_prompts_on_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();
        $prompt = Prompt::factory()->for($conversation)->create([
            'role' => 'user',
            'content' => 'Owner message',
        ]);

        $this->actingAs($intruder)
            ->postJson(route('conversations.prompts.store', $conversation), [
                'role' => 'user',
                'content' => 'Intruder',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->patchJson(route('conversations.prompts.update', [$conversation, $prompt]), [
                'content' => 'Hijacked',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('prompts', 1);
        $this->assertDatabaseHas('prompts', [
            'id' => $prompt->id,
            'content' => 'Owner message',
        ]);
    }

    #[Test]
    public function active_conversation_id_must_belong_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Conversation::factory()->for($other)->create();

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'active_conversation_id' => $foreign->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active_conversation_id']);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'active_conversation_id' => 9_999_999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['active_conversation_id']);
    }

    #[Test]
    public function prompt_content_rejects_oversized_payloads(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('conversations.prompts.store', $conversation), [
                'role' => 'user',
                'content' => str_repeat('a', ChatContentLimits::MAX_CONTENT_CHARS + 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    #[Test]
    public function conversation_field_json_patch_omits_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => 'T']);
        Prompt::factory()->for($conversation)->create(['role' => 'user', 'content' => 'Hi']);

        $response = $this->actingAs($user)
            ->patchJson(route('conversations.update', $conversation), [
                'title' => 'Renamed',
            ])
            ->assertOk();

        $response->assertJsonPath('activeConversation.title', 'Renamed');
        $this->assertArrayNotHasKey('messages', $response->json('activeConversation'));
    }
}
