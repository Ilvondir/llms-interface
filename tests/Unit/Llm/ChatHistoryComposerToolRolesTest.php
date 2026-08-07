<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatHistoryComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatHistoryComposerToolRolesTest extends TestCase
{
    #[Test]
    public function it_omits_prior_assistant_tool_calls_and_tool_results_from_upstream_history(): void
    {
        $composer = new ChatHistoryComposer;

        $composed = $composer->compose(null, [
            ['role' => 'user', 'content' => 'Search the web'],
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
                'content' => 'huge tool payload '.str_repeat('x', 200),
            ],
            ['role' => 'assistant', 'content' => 'Here is what I found.'],
            ['role' => 'user', 'content' => 'Thanks — summarize again'],
        ]);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Search the web'],
            ['role' => 'assistant', 'content' => 'Here is what I found.'],
            ['role' => 'user', 'content' => 'Thanks — summarize again'],
        ], $composed);
    }
}
