<?php

namespace App\Services\Llm;

use App\Services\Mcp\McpToolGateway;
use App\Services\Mcp\OpenAiToolMapper;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatToolOrchestrator
{
    public function __construct(
        private ChatCompletionProxy $proxy,
        private McpToolGateway $gateway,
        private OpenAiToolMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{id: string, name?: string, url: string, token?: string|null}>  $servers
     */
    public function stream(string $apiBaseUrl, array $payload, array $servers): StreamedResponse
    {
        $timeout = $this->proxy->chatTimeoutSeconds();

        return response()->stream(function () use ($apiBaseUrl, $payload, $servers, $timeout): void {
            set_time_limit($timeout);
            ignore_user_abort(true);

            try {
                $this->runToolLoop($apiBaseUrl, $payload, $servers);
            } catch (Throwable $exception) {
                report($exception);

                $this->emitEvent([
                    'event' => 'mcp_warning',
                    'message' => 'Chat stream failed: '.$exception->getMessage(),
                ]);

                $this->emitEvent([
                    'choices' => [[
                        'delta' => [
                            'content' => "\n\n[Stream error: ".$exception->getMessage().']',
                        ],
                    ]],
                ]);
                $this->emitDone();
            }
        }, 200, $this->proxy->sseHeaders());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{id: string, name?: string, url: string, token?: string|null}>  $servers
     */
    private function runToolLoop(string $apiBaseUrl, array $payload, array $servers): void
    {
        $listed = $this->gateway->listTools($servers);
        $tools = $listed['tools'];

        if ($tools !== []) {
            $this->emitEvent([
                'event' => 'mcp_tools',
                'tools' => $tools,
            ]);
        }

        if ($tools === []) {
            $detail = $listed['errors'] !== []
                ? implode('; ', array_map(
                    fn (array $error): string => ($error['server_id'] ?? '?').': '.($error['message'] ?? 'unknown error'),
                    $listed['errors'],
                ))
                : 'No tools available from configured MCP servers.';

            $this->emitEvent([
                'event' => 'mcp_warning',
                'message' => 'MCP tools unavailable; continuing without tools. '.$detail,
            ]);

            $this->forwardCompletionEvents($apiBaseUrl, $payload);
            $this->emitDone();

            return;
        }

        $messages = $payload['messages'];
        $maxRounds = max(1, (int) config('llms.mcp_max_tool_rounds', 50));

        for ($round = 1; $round <= $maxRounds; $round++) {
            $roundPayload = [
                ...$payload,
                'messages' => $messages,
                'tools' => $tools,
            ];

            $accumulatedToolCalls = [];
            $finishReason = null;
            $earlyToolNotices = [];

            foreach ($this->proxy->eachSseJsonEvent($apiBaseUrl, $roundPayload) as $event) {
                $choice = $event['choices'][0] ?? null;
                $delta = is_array($choice) ? ($choice['delta'] ?? null) : null;

                if (is_array($delta)) {
                    $this->mergeToolCallDeltas($accumulatedToolCalls, $delta['tool_calls'] ?? null);
                    $this->emitEarlyToolCallingNotices($accumulatedToolCalls, $servers, $earlyToolNotices, $round);

                    if ($this->deltaHasVisibleText($delta)) {
                        $this->emitEvent($event);
                    }
                } elseif (isset($event['usage'])) {
                    $this->emitEvent($event);
                }

                if (is_array($choice) && isset($choice['finish_reason']) && is_string($choice['finish_reason'])) {
                    $finishReason = $choice['finish_reason'];
                }
            }

            $toolCalls = $this->finalizeToolCalls($accumulatedToolCalls);

            if ($finishReason !== 'tool_calls' || $toolCalls === []) {
                $this->emitDone();

                return;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => array_values($toolCalls),
            ];

            $this->emitEvent([
                'event' => 'history_message',
                'message' => [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => array_values($toolCalls),
                ],
            ]);

            foreach ($toolCalls as $index => $toolCall) {
                $name = $toolCall['function']['name'] ?? '';
                $rawArgs = $toolCall['function']['arguments'] ?? '{}';
                $arguments = is_string($rawArgs) ? json_decode($rawArgs, true) : [];

                if (! is_array($arguments)) {
                    $arguments = [];
                }

                $parsed = is_string($name) ? $this->mapper->parse($name) : null;
                $serverId = is_string($parsed['server_id'] ?? null) ? $parsed['server_id'] : '';
                $serverName = $this->serverDisplayName($serverId, $servers);
                $frameId = $this->thinkingFrameId($round, $index);
                $apiToolCallId = is_string($toolCall['id'] ?? null) && $toolCall['id'] !== ''
                    ? $toolCall['id']
                    : $frameId;

                $this->emitEvent([
                    'event' => 'tool_status',
                    'tool_call_id' => $frameId,
                    'server_id' => $serverId,
                    'server_name' => $serverName,
                    'tool' => $name,
                    'status' => 'calling',
                    'arguments' => $arguments,
                    'detail' => '',
                ]);

                $resultText = $this->gateway->callTool(
                    is_string($name) ? $name : '',
                    $arguments,
                    $servers,
                );

                $isError = str_starts_with($resultText, 'Error');
                $persistedResult = $this->truncateToolResult($resultText);

                $this->emitEvent([
                    'event' => 'tool_status',
                    'tool_call_id' => $frameId,
                    'server_id' => $serverId,
                    'server_name' => $serverName,
                    'tool' => $name,
                    'status' => $isError ? 'error' : 'done',
                    'arguments' => $arguments,
                    'result' => $persistedResult,
                    'detail' => $isError ? $this->truncateToolResult($resultText, 500) : '',
                ]);

                $toolMessage = [
                    'role' => 'tool',
                    'tool_call_id' => $apiToolCallId,
                    'content' => $persistedResult,
                ];

                $messages[] = $toolMessage;

                $this->emitEvent([
                    'event' => 'history_message',
                    'message' => $toolMessage,
                ]);
            }

            if ($round === $maxRounds) {
                $this->emitEvent([
                    'event' => 'tool_status',
                    'server_id' => '',
                    'tool' => '',
                    'status' => 'error',
                    'detail' => 'Stopped after '.$maxRounds.' tool rounds.',
                ]);

                $this->emitEvent([
                    'choices' => [[
                        'delta' => [
                            'content' => "\n\n[Stopped after {$maxRounds} tool rounds.]",
                        ],
                    ]],
                ]);
                $this->emitDone();

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function forwardCompletionEvents(string $apiBaseUrl, array $payload): void
    {
        foreach ($this->proxy->eachSseJsonEvent($apiBaseUrl, $payload) as $event) {
            $this->emitEvent($event);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function emitEvent(array $event): void
    {
        echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function emitDone(): void
    {
        echo "data: [DONE]\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * @param  array<string, mixed>  $delta
     */
    private function deltaHasVisibleText(array $delta): bool
    {
        foreach (['content', 'reasoning_content', 'reasoning'] as $key) {
            if (isset($delta[$key]) && is_string($delta[$key]) && $delta[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $accumulated
     */
    private function mergeToolCallDeltas(array &$accumulated, mixed $deltas): void
    {
        if (! is_array($deltas)) {
            return;
        }

        foreach ($deltas as $delta) {
            if (! is_array($delta)) {
                continue;
            }

            $index = $delta['index'] ?? null;

            if (! is_int($index) && ! (is_string($index) && ctype_digit($index))) {
                $index = count($accumulated);
            }

            $index = (int) $index;

            if (! isset($accumulated[$index])) {
                $accumulated[$index] = [
                    'id' => '',
                    'type' => 'function',
                    'function' => [
                        'name' => '',
                        'arguments' => '',
                    ],
                ];
            }

            if (isset($delta['id']) && is_string($delta['id'])) {
                $accumulated[$index]['id'] = $delta['id'];
            }

            if (isset($delta['type']) && is_string($delta['type'])) {
                $accumulated[$index]['type'] = $delta['type'];
            }

            $fn = $delta['function'] ?? null;

            if (! is_array($fn)) {
                continue;
            }

            if (isset($fn['name']) && is_string($fn['name'])) {
                $accumulated[$index]['function']['name'] .= $fn['name'];
            }

            if (isset($fn['arguments']) && is_string($fn['arguments'])) {
                $accumulated[$index]['function']['arguments'] .= $fn['arguments'];
            }
        }
    }

    /**
     * Stable Thinking-panel id for one tool invocation (unique across tool rounds).
     */
    private function thinkingFrameId(int $round, int $index): string
    {
        return 'r'.$round.'_i'.$index;
    }

    /**
     * Emit a Thinking-panel frame as soon as the model names a tool (before args finish / MCP runs).
     *
     * @param  array<int, array<string, mixed>>  $accumulated
     * @param  list<array{id: string, name: string, url: string, token: string|null}>  $servers
     * @param  array<int, true>  $notified
     */
    private function emitEarlyToolCallingNotices(array &$accumulated, array $servers, array &$notified, int $round): void
    {
        foreach ($accumulated as $index => $call) {
            if (isset($notified[$index])) {
                continue;
            }

            $name = $call['function']['name'] ?? '';

            if (! is_string($name) || $name === '') {
                continue;
            }

            $notified[$index] = true;

            $rawArgs = $call['function']['arguments'] ?? '';
            $arguments = is_string($rawArgs) ? json_decode($rawArgs, true) : [];

            if (! is_array($arguments)) {
                $arguments = [];
            }

            $parsed = $this->mapper->parse($name);
            $serverId = is_string($parsed['server_id'] ?? null) ? $parsed['server_id'] : '';
            $frameId = $this->thinkingFrameId($round, $index);

            $this->emitEvent([
                'event' => 'tool_status',
                'tool_call_id' => $frameId,
                'server_id' => $serverId,
                'server_name' => $this->serverDisplayName($serverId, $servers),
                'tool' => $name,
                'status' => 'calling',
                'arguments' => $arguments,
                'detail' => '',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $accumulated
     * @return array<int, array<string, mixed>>
     */
    private function finalizeToolCalls(array $accumulated): array
    {
        ksort($accumulated);

        $calls = [];

        foreach ($accumulated as $index => $call) {
            $name = $call['function']['name'] ?? '';

            if (! is_string($name) || $name === '') {
                continue;
            }

            if (($call['id'] ?? '') === '') {
                $call['id'] = 'call_'.$index;
            }

            $calls[$index] = $call;
        }

        return $calls;
    }

    /**
     * @param  list<array{id: string, name: string, url: string, token: string|null}>  $servers
     */
    private function serverDisplayName(string $serverId, array $servers): string
    {
        if ($serverId === '') {
            return 'MCP';
        }

        foreach ($servers as $server) {
            if (($server['id'] ?? null) === $serverId) {
                $name = $server['name'] ?? null;

                return is_string($name) && $name !== '' ? $name : $serverId;
            }
        }

        return $serverId;
    }

    private function truncateToolResult(string $text, ?int $max = null): string
    {
        $limit = $max ?? max(256, (int) config('llms.mcp_tool_result_max_chars', 8000));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit)."\n…[truncated]";
    }
}
