<?php

namespace App\Services\Mcp;

use Closure;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\WebClient;
use Throwable;

/**
 * Discovers and invokes tools on user-configured HTTP MCP servers.
 */
class McpToolGateway
{
    /**
     * @param  (Closure(string $serverId, string $url, ?string $token): Client)|null  $clientFactory
     */
    public function __construct(
        private OpenAiToolMapper $mapper,
        private ?Closure $clientFactory = null,
    ) {}

    /**
     * @param  list<array{id: string, url: string, token?: string|null, name?: string}>  $servers
     * @return array{
     *     tools: list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>,
     *     errors: list<array{server_id: string, message: string}>
     * }
     */
    public function listTools(array $servers): array
    {
        $tools = [];
        $errors = [];

        foreach ($servers as $server) {
            $serverId = $server['id'] ?? null;
            $url = $server['url'] ?? null;

            if (! is_string($serverId) || $serverId === '' || ! is_string($url) || $url === '') {
                continue;
            }

            $token = isset($server['token']) && is_string($server['token']) && $server['token'] !== ''
                ? $server['token']
                : null;

            $client = null;

            try {
                $client = $this->makeClient($serverId, $url, $token);
                $discovered = $client->tools();

                foreach ($discovered as $tool) {
                    if (! $tool instanceof Tool) {
                        continue;
                    }

                    $tools[] = $this->mapper->toOpenAiTool(
                        $serverId,
                        $tool->name,
                        $tool->description,
                        $tool->inputSchema,
                    );
                }
            } catch (Throwable $exception) {
                $errors[] = [
                    'server_id' => $serverId,
                    'message' => $exception->getMessage(),
                ];
            } finally {
                if ($client instanceof Client && $client->connected()) {
                    $client->disconnect();
                }
            }
        }

        return [
            'tools' => $tools,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<array{id: string, url: string, token?: string|null}>  $servers
     */
    public function callTool(string $prefixedName, array $arguments, array $servers): string
    {
        $parsed = $this->mapper->parse($prefixedName);

        if ($parsed === null) {
            return 'Error: invalid tool name "'.$prefixedName.'".';
        }

        $server = null;

        foreach ($servers as $candidate) {
            if (($candidate['id'] ?? null) === $parsed['server_id']) {
                $server = $candidate;
                break;
            }
        }

        if ($server === null || ! is_string($server['url'] ?? null) || $server['url'] === '') {
            return 'Error: MCP server "'.$parsed['server_id'].'" is not configured.';
        }

        $token = isset($server['token']) && is_string($server['token']) && $server['token'] !== ''
            ? $server['token']
            : null;

        $client = null;

        try {
            $client = $this->makeClient($parsed['server_id'], $server['url'], $token);
            $result = $client->callTool($parsed['tool_name'], $arguments);
            $text = $result->text();

            if ($result->isError) {
                return $text !== '' ? $text : 'Tool reported an error with no message.';
            }

            if ($text !== '') {
                return $text;
            }

            if (is_array($result->structuredContent) && $result->structuredContent !== []) {
                $encoded = json_encode($result->structuredContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return is_string($encoded) ? $encoded : 'Tool returned structured content that could not be encoded.';
            }

            return '';
        } catch (Throwable $exception) {
            return 'Error calling tool "'.$prefixedName.'": '.$exception->getMessage();
        } finally {
            if ($client instanceof Client && $client->connected()) {
                $client->disconnect();
            }
        }
    }

    private function makeClient(string $serverId, string $url, ?string $token): Client
    {
        if ($this->clientFactory instanceof Closure) {
            return ($this->clientFactory)($serverId, $url, $token);
        }

        /** @var WebClient $client */
        $client = Client::web($url)->withTimeout((float) config('llms.mcp_client_timeout', 30));

        if ($token !== null) {
            $client->withToken($token);
        }

        return $client;
    }
}
