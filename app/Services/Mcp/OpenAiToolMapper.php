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

        $parameters = $this->normalizeSchema($parameters);

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->prefix($serverId, $toolName),
                'description' => $description ?? '',
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * PHP decodes JSON `{}` to `[]`, which re-encodes as an invalid schema. Restore empty objects
     * in schema positions that must be objects, leaving enum/const/default/required untouched.
     *
     * @param  array<mixed>  $schema
     * @return array<mixed>|\stdClass
     */
    private function normalizeSchema(array $schema): array|\stdClass
    {
        if ($schema === []) {
            return new \stdClass;
        }

        if (array_is_list($schema)) {
            return array_map(
                fn (mixed $item): mixed => is_array($item) ? $this->normalizeSchema($item) : $item,
                $schema,
            );
        }

        $mapKeys = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];
        $schemaKeys = ['additionalProperties', 'items', 'additionalItems', 'contains', 'propertyNames',
            'unevaluatedProperties', 'unevaluatedItems', 'not', 'if', 'then', 'else'];
        $schemaListKeys = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

        foreach ($schema as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (in_array($key, $mapKeys, true)) {
                $schema[$key] = $value === []
                    ? new \stdClass
                    : array_map(
                        fn (mixed $child): mixed => is_array($child) ? $this->normalizeSchema($child) : $child,
                        $value,
                    );
            } elseif (in_array($key, $schemaKeys, true)) {
                $schema[$key] = $this->normalizeSchema($value);
            } elseif (in_array($key, $schemaListKeys, true)) {
                $schema[$key] = array_map(
                    fn (mixed $child): mixed => is_array($child) ? $this->normalizeSchema($child) : $child,
                    $value,
                );
            }
        }

        return $schema;
    }
}
