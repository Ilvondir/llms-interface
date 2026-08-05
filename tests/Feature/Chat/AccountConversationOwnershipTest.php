<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
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
}
