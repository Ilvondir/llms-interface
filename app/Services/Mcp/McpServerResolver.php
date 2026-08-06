<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Support\Chat\McpServerConfig;

/**
 * Resolves which MCP servers (with tokens) apply to a chat stream request.
 */
class McpServerResolver
{
    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{id: string, name: string, url: string, token: string|null}>
     */
    public function resolve(?User $user, array $validated): array
    {
        $enabledIds = $validated['enabled_mcp_server_ids'] ?? [];

        if (! is_array($enabledIds) || $enabledIds === []) {
            return [];
        }

        if ($user !== null) {
            return $this->resolveForUser($user, $enabledIds);
        }

        return $this->resolveForGuest($validated, $enabledIds);
    }

    /**
     * @param  list<mixed>  $enabledIds
     * @return list<array{id: string, name: string, url: string, token: string|null}>
     */
    private function resolveForUser(User $user, array $enabledIds): array
    {
        $settings = $user->chatSettings()->first();
        $stored = is_array($settings?->mcp_servers) ? $settings->mcp_servers : [];
        $enabled = McpServerConfig::filterEnabledIds($enabledIds, $stored);
        $enabledLookup = array_flip($enabled);

        $resolved = [];

        foreach ($stored as $server) {
            if (! is_array($server) || ! is_string($server['id'] ?? null)) {
                continue;
            }

            if (! isset($enabledLookup[$server['id']])) {
                continue;
            }

            $resolved[] = [
                'id' => $server['id'],
                'name' => is_string($server['name'] ?? null) ? $server['name'] : $server['id'],
                'url' => is_string($server['url'] ?? null) ? $server['url'] : '',
                'token' => isset($server['token']) && is_string($server['token']) && $server['token'] !== ''
                    ? $server['token']
                    : null,
            ];
        }

        return array_values(array_filter(
            $resolved,
            fn (array $server): bool => $server['url'] !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<mixed>  $enabledIds
     * @return list<array{id: string, name: string, url: string, token: string|null}>
     */
    private function resolveForGuest(array $validated, array $enabledIds): array
    {
        $servers = $validated['mcp_servers'] ?? [];

        if (! is_array($servers)) {
            return [];
        }

        $credentials = [];

        foreach ($validated['mcp_credentials'] ?? [] as $cred) {
            if (! is_array($cred) || ! is_string($cred['id'] ?? null)) {
                continue;
            }

            $token = $cred['token'] ?? null;
            $credentials[$cred['id']] = is_string($token) && $token !== '' ? $token : null;
        }

        $normalized = [];

        foreach ($servers as $server) {
            if (! is_array($server) || ! is_string($server['id'] ?? null) || $server['id'] === '') {
                continue;
            }

            $normalized[] = [
                'id' => $server['id'],
                'name' => is_string($server['name'] ?? null) ? $server['name'] : $server['id'],
                'url' => is_string($server['url'] ?? null) ? $server['url'] : '',
                'token' => null,
            ];
        }

        $enabled = McpServerConfig::filterEnabledIds($enabledIds, $normalized);
        $enabledLookup = array_flip($enabled);

        $resolved = [];

        foreach ($normalized as $server) {
            if (! isset($enabledLookup[$server['id']]) || $server['url'] === '') {
                continue;
            }

            $resolved[] = [
                ...$server,
                'token' => $credentials[$server['id']] ?? null,
            ];
        }

        return $resolved;
    }
}
