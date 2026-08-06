<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatHistoryComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatHistoryComposerToolRolesTest extends TestCase
{
    #[Test]
    public function it_composes_assistant_tool_calls_and_tool_results(): void
    {
        $composer = new ChatHistoryComposer;

        $composed = $composer->compose(null, [
            ['role' => 'user', 'content' => 'Hi'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'exa__search', 'arguments' => '{}'],
                ]],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'call_1',
                'content' => 'result',
            ],
        ]);

        $this->assertSame('user', $composed[0]['role']);
        $this->assertSame('assistant', $composed[1]['role']);
        $this->assertArrayHasKey('tool_calls', $composed[1]);
        $this->assertSame('tool', $composed[2]['role']);
        $this->assertSame('call_1', $composed[2]['tool_call_id']);
        $this->assertSame('result', $composed[2]['content']);
    }
}
