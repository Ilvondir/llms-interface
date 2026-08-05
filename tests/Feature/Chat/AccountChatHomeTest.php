<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountChatHomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_receives_account_chat_props(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create([
            'title' => 'Stored chat',
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat/Index')
            ->has('chatSettings')
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.title', 'Stored chat')
            ->has('activeConversation', fn (Assert $active) => $active
                ->where('id', $conversation->id)
                ->where('title', 'Stored chat')
                ->has('messages')
                ->etc()
            )
        );
    }

    #[Test]
    public function guest_home_does_not_expose_account_conversation_props(): void
    {
        $owner = User::factory()->create();
        Conversation::factory()->for($owner)->create(['title' => 'Secret']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat/Index')
            ->missing('chatSettings')
            ->missing('conversations')
            ->missing('activeConversation')
        );
    }
}
