<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestChatSchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function account_persistence_introduces_conversation_tables(): void
    {
        $this->assertTrue(
            Schema::hasTable('conversations'),
            'S-02 must create a conversations table.',
        );

        $this->assertTrue(
            Schema::hasTable('prompts'),
            'S-02 must create a prompts table.',
        );

        $this->assertTrue(
            Schema::hasTable('user_chat_settings'),
            'S-02 must create a user_chat_settings table.',
        );
    }

    #[Test]
    public function conversation_policy_allows_only_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();

        $policy = new ConversationPolicy;

        $this->assertTrue($policy->view($owner, $conversation));
        $this->assertTrue($policy->update($owner, $conversation));
        $this->assertTrue($policy->delete($owner, $conversation));
        $this->assertTrue($policy->create($owner));

        $this->assertFalse($policy->view($other, $conversation));
        $this->assertFalse($policy->update($other, $conversation));
        $this->assertFalse($policy->delete($other, $conversation));
    }
}
