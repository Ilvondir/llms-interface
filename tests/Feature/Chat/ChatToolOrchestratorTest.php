<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Models\UserChatSettings;
use App\Services\Mcp\McpToolGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatToolOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function sse(array $events): string
    {
        $chunks = [];

        foreach ($events as $event) {
            $chunks[] = 'data: '.json_encode($event)."\n\n";
        }

        $chunks[] = "data: [DONE]\n\n";

        return implode('', $chunks);
    }

    #[Test]
    public function guest_tool_loop_emits_status_calls_mcp_and_streams_final_content(): void
    {
        Http::preventStrayRequests();

        $toolCallSse = $this->sse([
            [
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'index' => 0,
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'exa__search',
                                'arguments' => '{"q":"php"}',
                            ],
                        ]],
                    ],
                ]],
            ],
            [
                'choices' => [[
                    'delta' => [],
                    'finish_reason' => 'tool_calls',
                ]],
            ],
        ]);

        $finalSse = $this->sse([
            [
                'choices' => [[
                    'delta' => ['content' => 'Final answer'],
                ]],
            ],
            [
                'choices' => [[
                    'delta' => [],
                    'finish_reason' => 'stop',
                ]],
            ],
        ]);

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::sequence()
                ->push($toolCallSse, 200, ['Content-Type' => 'text/event-stream'])
                ->push($finalSse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $gateway = $this->createMock(McpToolGateway::class);
        $gateway->expects($this->once())
            ->method('listTools')
            ->willReturn([
                'tools' => [[
                    'type' => 'function',
                    'function' => [
                        'name' => 'exa__search',
                        'description' => 'Search',
                        'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                    ],
                ]],
                'errors' => [],
            ]);
        $gateway->expects($this->once())
            ->method('callTool')
            ->with('exa__search', ['q' => 'php'], $this->callback(function (array $servers): bool {
                return ($servers[0]['id'] ?? null) === 'exa'
                    && ($servers[0]['token'] ?? null) === 'guest-token';
            }))
            ->willReturn('search results');

        $this->app->instance(McpToolGateway::class, $gateway);

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Search php'],
            ],
            'enabled_mcp_server_ids' => ['exa'],
            'mcp_servers' => [
                ['id' => 'exa', 'name' => 'Exa', 'url' => 'https://mcp.exa.ai/mcp'],
            ],
            'mcp_credentials' => [
                ['id' => 'exa', 'token' => 'guest-token'],
            ],
        ]);

        $response->assertOk();
        $response->assertStreamed();
        $body = $response->streamedContent();

        $this->assertStringContainsString('"event":"tool_status"', $body);
        $this->assertStringContainsString('"status":"calling"', $body);
        $this->assertStringContainsString('"status":"done"', $body);
        $this->assertStringContainsString('Final answer', $body);
        $this->assertStringContainsString('data: [DONE]', $body);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/chat/completions')) {
                return false;
            }

            $data = $request->data();

            return isset($data['tools']) && is_array($data['tools']) && $data['tools'] !== [];
        });
    }

    #[Test]
    public function discovery_failure_emits_mcp_warning_and_streams_without_tools(): void
    {
        Http::preventStrayRequests();

        $sse = $this->sse([
            ['choices' => [['delta' => ['content' => 'Plain']]]],
            ['choices' => [['delta' => [], 'finish_reason' => 'stop']]],
        ]);

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $gateway = $this->createMock(McpToolGateway::class);
        $gateway->method('listTools')->willReturn([
            'tools' => [],
            'errors' => [['server_id' => 'exa', 'message' => 'unreachable']],
        ]);
        $gateway->expects($this->never())->method('callTool');
        $this->app->instance(McpToolGateway::class, $gateway);

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
            'enabled_mcp_server_ids' => ['exa'],
            'mcp_servers' => [
                ['id' => 'exa', 'name' => 'Exa', 'url' => 'https://mcp.exa.ai/mcp'],
            ],
            'mcp_credentials' => [
                ['id' => 'exa', 'token' => 't'],
            ],
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('"event":"mcp_warning"', $body);
        $this->assertStringContainsString('Plain', $body);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return ! array_key_exists('tools', $data);
        });
    }

    #[Test]
    public function authenticated_user_uses_db_token_and_ignores_body_credentials(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        UserChatSettings::factory()->for($user)->create([
            'mcp_servers' => [[
                'id' => 'exa',
                'name' => 'Exa',
                'url' => 'https://mcp.exa.ai/mcp',
                'token' => 'db-secret',
            ]],
        ]);

        $sse = $this->sse([
            ['choices' => [['delta' => ['content' => 'Ok'], 'finish_reason' => 'stop']]],
        ]);

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $this->mock(McpToolGateway::class, function ($mock) {
            $mock->shouldReceive('listTools')
                ->once()
                ->withArgs(function (array $servers): bool {
                    return ($servers[0]['token'] ?? null) === 'db-secret';
                })
                ->andReturn([
                    'tools' => [[
                        'type' => 'function',
                        'function' => [
                            'name' => 'exa__search',
                            'description' => 'Search',
                            'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                        ],
                    ]],
                    'errors' => [],
                ]);
            $mock->shouldReceive('callTool')->never();
        });

        $response = $this->actingAs($user)
            ->postJson(route('chat.stream'), [
                'api_base_url' => 'http://lm.test/v1',
                'model' => 'demo-model',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hi'],
                ],
                'enabled_mcp_server_ids' => ['exa'],
                'mcp_credentials' => [
                    ['id' => 'exa', 'token' => 'body-should-be-ignored'],
                ],
            ]);

        $response->assertOk();
        $response->assertStreamed();
        $this->assertStringContainsString('Ok', $response->streamedContent());
    }

    #[Test]
    public function max_tool_rounds_emits_cut_off(): void
    {
        config(['llms.mcp_max_tool_rounds' => 1]);
        Http::preventStrayRequests();

        $toolCallSse = $this->sse([
            [
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'index' => 0,
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'exa__search',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ],
        ]);

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($toolCallSse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $gateway = $this->createMock(McpToolGateway::class);
        $gateway->method('listTools')->willReturn([
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'exa__search',
                    'description' => 'Search',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
            ]],
            'errors' => [],
        ]);
        $gateway->method('callTool')->willReturn('ok');
        $this->app->instance(McpToolGateway::class, $gateway);

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Go'],
            ],
            'enabled_mcp_server_ids' => ['exa'],
            'mcp_servers' => [
                ['id' => 'exa', 'name' => 'Exa', 'url' => 'https://mcp.exa.ai/mcp'],
            ],
            'mcp_credentials' => [
                ['id' => 'exa', 'token' => 't'],
            ],
        ]);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Stopped after 1 tool rounds', $body);
    }
}
