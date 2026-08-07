<?php

namespace Tests\Unit\Mcp;

use App\Support\Chat\McpServerConfig;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpServerConfigNameTest extends TestCase
{
    #[Test]
    public function merge_preserves_spaces_inside_display_names(): void
    {
        $merged = McpServerConfig::mergeForStorage(
            [
                [
                    'id' => 'smithery',
                    'name' => '  Smithery Exa Search  ',
                    'url' => 'https://server.smithery.ai/exa/mcp',
                ],
            ],
            [],
        );

        $this->assertSame('Smithery Exa Search', $merged[0]['name']);
    }

    #[Test]
    public function present_for_client_keeps_spaced_names(): void
    {
        $presented = McpServerConfig::presentForClient([
            [
                'id' => 'smithery',
                'name' => 'My MCP Server',
                'url' => 'https://example.test/mcp',
                'token' => null,
            ],
        ]);

        $this->assertSame('My MCP Server', $presented[0]['name']);
    }
}
