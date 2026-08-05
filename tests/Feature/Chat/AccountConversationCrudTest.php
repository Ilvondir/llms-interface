<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountConversationCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_create_rename_and_delete_conversations(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->post(route('conversations.store'));
        $create->assertRedirect();

        $conversation = Conversation::query()->whereBelongsTo($user)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('New chat', $conversation->title);

        $rename = $this->actingAs($user)->patch(route('conversations.update', $conversation), [
            'title' => 'Renamed',
        ]);
        $rename->assertOk();
        $this->assertSame('Renamed', $conversation->fresh()->title);

        $delete = $this->actingAs($user)->delete(route('conversations.destroy', $conversation));
        $delete->assertRedirect(route('home'));
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    #[Test]
    public function guest_cannot_create_conversations(): void
    {
        $response = $this->post(route('conversations.store'));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('conversations', 0);
    }
}
