<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatHistoryComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatHistoryComposerTest extends TestCase
{
    #[Test]
    public function it_composes_system_prompt_and_strips_reasoning(): void
    {
        $composer = new ChatHistoryComposer;

        $messages = $composer->compose('Be helpful.', [
            [
                'role' => 'user',
                'content' => 'Hello',
                'reasoning' => 'should not leak',
            ],
            [
                'role' => 'assistant',
                'content' => 'Hi there',
                'reasoning' => 'internal thoughts',
                'stats' => ['ttftMs' => 12],
            ],
            [
                'role' => 'assistant',
                'content' => '   ',
            ],
        ]);

        $this->assertSame([
            [
                'role' => 'system',
                'content' => 'Be helpful.',
            ],
            [
                'role' => 'user',
                'content' => 'Hello',
            ],
            [
                'role' => 'assistant',
                'content' => 'Hi there',
            ],
        ], $messages);
    }

    #[Test]
    public function it_skips_duplicate_system_messages_when_system_prompt_provided(): void
    {
        $composer = new ChatHistoryComposer;

        $messages = $composer->compose('Canonical', [
            [
                'role' => 'system',
                'content' => 'From history',
            ],
            [
                'role' => 'user',
                'content' => 'Hi',
            ],
        ]);

        $this->assertSame([
            [
                'role' => 'system',
                'content' => 'Canonical',
            ],
            [
                'role' => 'user',
                'content' => 'Hi',
            ],
        ], $messages);
    }
}
