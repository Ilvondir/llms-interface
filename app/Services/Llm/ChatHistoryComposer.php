<?php

namespace App\Services\Llm;

class ChatHistoryComposer
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    public function compose(?string $systemPrompt, array $messages): array
    {
        $composed = [];

        $trimmedSystemPrompt = trim((string) $systemPrompt);

        if ($trimmedSystemPrompt !== '') {
            $composed[] = [
                'role' => 'system',
                'content' => $trimmedSystemPrompt,
            ];
        }

        foreach ($messages as $message) {
            $role = $message['role'] ?? null;
            $content = trim((string) ($message['content'] ?? ''));

            if (! in_array($role, ['system', 'user', 'assistant'], true) || $content === '') {
                continue;
            }

            if ($role === 'system' && $trimmedSystemPrompt !== '') {
                continue;
            }

            $composed[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $composed;
    }
}
