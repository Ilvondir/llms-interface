<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatHistoryComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatHistoryComposerTest extends TestCase
{
    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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

    #[Test]
    public function it_composes_image_url_parts_without_reasoning(): void
    {
        $composer = new ChatHistoryComposer;

        $messages = $composer->compose(null, [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is this?'],
                    ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                ],
                'reasoning' => 'must not leak',
            ],
        ]);

        $this->assertSame([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is this?'],
                    ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                ],
            ],
        ], $messages);
    }

    #[Test]
    public function it_keeps_image_only_messages_and_drops_empty_parts(): void
    {
        $composer = new ChatHistoryComposer;

        $messages = $composer->compose(null, [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => '   '],
                ],
            ],
        ]);

        $this->assertSame([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                ],
            ],
        ], $messages);
    }
}
