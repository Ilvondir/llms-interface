<?php

namespace App\Support\Chat;

use App\Models\User;

class ApiKeyResolver
{
    /**
     * Prefer the key sent with the request; fall back to the one saved on the account.
     */
    public function resolve(?User $user, ?string $requestKey): ?string
    {
        $requestKey = trim((string) $requestKey);

        if ($requestKey !== '') {
            return $requestKey;
        }

        if ($user === null) {
            return null;
        }

        $stored = trim((string) $user->chatSettings?->api_key);

        return $stored !== '' ? $stored : null;
    }
}
