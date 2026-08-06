<?php

namespace App\Support\Chat;

/**
 * Normalize / merge / present MCP server config for user chat settings.
 */
final class McpServerConfig
{
    public const MAX_SERVERS = 10;

    public const MAX_TOKEN_LENGTH = 2048;

    public const ID_REGEX = '^[a-z][a-z0-9]*(-[a-z0-9]+)*$';

    /**
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<array<string, mixed>>  $existing
     * @return list<array{id: string, name: string, url: string, token: string|null}>
     */
    public static function mergeForStorage(array $incoming, array $existing): array
    {
        $existingById = [];

        foreach ($existing as $server) {
            if (! is_array($server) || ! is_string($server['id'] ?? null) || $server['id'] === '') {
                continue;
            }

            $existingById[$server['id']] = $server;
        }

        $merged = [];
        $seenIds = [];

        foreach ($incoming as $server) {
            if (! is_array($server)) {
                continue;
            }

            $id = isset($server['id']) && is_string($server['id']) ? trim($server['id']) : '';

            if ($id === '' || isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $name = isset($server['name']) && is_string($server['name']) ? trim($server['name']) : $id;
            $url = isset($server['url']) && is_string($server['url']) ? trim($server['url']) : '';

            $incomingToken = $server['token'] ?? null;
            $token = null;

            if (is_string($incomingToken) && trim($incomingToken) !== '') {
                $token = trim($incomingToken);
            } elseif (isset($existingById[$id]['token']) && is_string($existingById[$id]['token']) && $existingById[$id]['token'] !== '') {
                $token = $existingById[$id]['token'];
            }

            $merged[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'url' => $url,
                'token' => $token,
            ];
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>|null  $servers
     * @return list<array{id: string, name: string, url: string, hasToken: bool}>
     */
    public static function presentForClient(?array $servers): array
    {
        $presented = [];

        foreach ($servers ?? [] as $server) {
            if (! is_array($server) || ! is_string($server['id'] ?? null) || $server['id'] === '') {
                continue;
            }

            $token = $server['token'] ?? null;
            $hasToken = is_string($token) && trim($token) !== '';

            $presented[] = [
                'id' => $server['id'],
                'name' => is_string($server['name'] ?? null) && $server['name'] !== ''
                    ? $server['name']
                    : $server['id'],
                'url' => is_string($server['url'] ?? null) ? $server['url'] : '',
                'hasToken' => $hasToken,
            ];
        }

        return $presented;
    }

    /**
     * Soft-filter enabled ids to those that exist on the user's server list.
     *
     * @param  list<mixed>|null  $ids
     * @param  list<array<string, mixed>>|null  $servers
     * @return list<string>
     */
    public static function filterEnabledIds(?array $ids, ?array $servers): array
    {
        $known = [];

        foreach ($servers ?? [] as $server) {
            if (is_array($server) && is_string($server['id'] ?? null) && $server['id'] !== '') {
                $known[$server['id']] = true;
            }
        }

        $filtered = [];
        $seen = [];

        foreach ($ids ?? [] as $id) {
            if (! is_string($id) || $id === '' || isset($seen[$id]) || ! isset($known[$id])) {
                continue;
            }

            $seen[$id] = true;
            $filtered[] = $id;
        }

        return $filtered;
    }
}
