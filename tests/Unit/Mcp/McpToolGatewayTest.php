<?php

namespace Tests\Unit\Mcp;

use App\Services\Mcp\McpToolGateway;
use App\Services\Mcp\OpenAiToolMapper;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Schema\ToolResult;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class McpToolGatewayTest extends TestCase
{
    #[Test]
    public function list_tools_maps_prefixed_openai_tools_and_collects_per_server_errors(): void
    {
        $okClient = $this->createMock(Client::class);
        $okClient->method('tools')->willReturn(Collection::make([
            'search' => new Tool(
                client: null,
                name: 'search',
                title: 'Search',
                description: 'Find things',
                inputSchema: [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string']],
                ],
                outputSchema: null,
                annotations: [],
                meta: null,
            ),
        ]));
        $okClient->method('connected')->willReturn(false);

        $failClient = $this->createMock(Client::class);
        $failClient->method('tools')->willThrowException(new RuntimeException('boom'));
        $failClient->method('connected')->willReturn(false);

        $gateway = new McpToolGateway(
            new OpenAiToolMapper,
            function (string $serverId) use ($okClient, $failClient): Client {
                return $serverId === 'exa' ? $okClient : $failClient;
            },
        );

        $result = $gateway->listTools([
            ['id' => 'exa', 'url' => 'https://mcp.exa.ai/mcp', 'token' => 'k'],
            ['id' => 'broken', 'url' => 'https://example.test/mcp'],
        ]);

        $this->assertCount(1, $result['tools']);
        $this->assertSame('exa__search', $result['tools'][0]['function']['name']);
        $this->assertSame('Find things', $result['tools'][0]['function']['description']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('broken', $result['errors'][0]['server_id']);
        $this->assertSame('boom', $result['errors'][0]['message']);
    }

    #[Test]
    public function call_tool_strips_prefix_and_returns_text_result(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('callTool')
            ->with('web_search_exa', ['query' => 'php'])
            ->willReturn(new ToolResult(
                content: [['type' => 'text', 'text' => 'results']],
                isError: false,
            ));
        $client->method('connected')->willReturn(false);

        $gateway = new McpToolGateway(
            new OpenAiToolMapper,
            fn (): Client => $client,
        );

        $text = $gateway->callTool('exa__web_search_exa', ['query' => 'php'], [
            ['id' => 'exa', 'url' => 'https://mcp.exa.ai/mcp', 'token' => 'k'],
        ]);

        $this->assertSame('results', $text);
    }

    #[Test]
    public function call_tool_returns_error_string_when_server_missing(): void
    {
        $gateway = new McpToolGateway(new OpenAiToolMapper);

        $text = $gateway->callTool('missing__search', [], []);

        $this->assertStringContainsString('not configured', $text);
    }
}
