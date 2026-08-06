<?php

namespace App\Services\Mcp;

/**
 * Maps MCP tool definitions to OpenAI-compatible tools[] entries with collision-safe names.
 */
final class OpenAiToolMapper
{
    public const SEPARATOR = '__';

    public function prefix(string $serverId, string $toolName): string
    {
        return $serverId.self::SEPARATOR.$toolName;
    }

    /**
     * @return array{server_id: string, tool_name: string}|null
     */
    public function parse(string $prefixedName): ?array
    {
        $separatorPos = strpos($prefixedName, self::SEPARATOR);

        if ($separatorPos === false || $separatorPos === 0) {
            return null;
        }

        $serverId = substr($prefixedName, 0, $separatorPos);
        $toolName = substr($prefixedName, $separatorPos + strlen(self::SEPARATOR));

        if ($serverId === '' || $toolName === '') {
            return null;
        }

        return [
            'server_id' => $serverId,
            'tool_name' => $toolName,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function toOpenAiTool(
        string $serverId,
        string $toolName,
        ?string $description,
        array $inputSchema,
    ): array {
        $parameters = $inputSchema !== []
            ? $inputSchema
            : ['type' => 'object', 'properties' => new \stdClass];

        // OpenAI expects object; empty properties {} may serialize as [] in JSON — use stdClass when empty properties.
        if (($parameters['type'] ?? null) === 'object'
            && array_key_exists('properties', $parameters)
            && $parameters['properties'] === []) {
            $parameters['properties'] = new \stdClass;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->prefix($serverId, $toolName),
                'description' => $description ?? '',
                'parameters' => $parameters,
            ],
        ];
    }
}
