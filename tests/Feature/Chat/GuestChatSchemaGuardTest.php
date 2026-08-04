<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestChatSchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_chat_change_does_not_introduce_conversation_tables(): void
    {
        $this->assertFalse(
            Schema::hasTable('conversations'),
            'S-01 must not create a conversations table.',
        );

        $this->assertFalse(
            Schema::hasTable('prompts'),
            'S-01 must not create a prompts table.',
        );
    }
}
