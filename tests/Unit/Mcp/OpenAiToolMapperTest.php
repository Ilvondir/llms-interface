<?php

namespace Tests\Unit\Mcp;

use App\Services\Mcp\OpenAiToolMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenAiToolMapperTest extends TestCase
{
    #[Test]
    public function prefix_and_parse_round_trip(): void
    {
        $mapper = new OpenAiToolMapper;

        $prefixed = $mapper->prefix('exa', 'web_search_exa');

        $this->assertSame('exa__web_search_exa', $prefixed);
        $this->assertSame([
            'server_id' => 'exa',
            'tool_name' => 'web_search_exa',
        ], $mapper->parse($prefixed));
    }

    #[Test]
    public function parse_keeps_tool_name_segments_after_first_separator(): void
    {
        $mapper = new OpenAiToolMapper;

        $this->assertSame([
            'server_id' => 'exa',
            'tool_name' => 'web__search',
        ], $mapper->parse('exa__web__search'));
    }

    #[Test]
    public function colliding_tool_names_on_different_servers_get_distinct_openai_names(): void
    {
        $mapper = new OpenAiToolMapper;

        $a = $mapper->toOpenAiTool('exa', 'search', 'Exa search', [
            'type' => 'object',
            'properties' => ['q' => ['type' => 'string']],
        ]);
        $b = $mapper->toOpenAiTool('other', 'search', 'Other search', [
            'type' => 'object',
            'properties' => ['q' => ['type' => 'string']],
        ]);

        $this->assertSame('exa__search', $a['function']['name']);
        $this->assertSame('other__search', $b['function']['name']);
        $this->assertNotSame($a['function']['name'], $b['function']['name']);
    }

    #[Test]
    public function parse_returns_null_for_invalid_names(): void
    {
        $mapper = new OpenAiToolMapper;

        $this->assertNull($mapper->parse('noseparator'));
        $this->assertNull($mapper->parse('__tool'));
        $this->assertNull($mapper->parse('server__'));
    }
}
