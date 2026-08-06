<?php

namespace App\Services\Llm;

use App\Support\Chat\MessageContent;

class ChatHistoryComposer
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
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

            if ($role === 'tool') {
                $toolCallId = $message['tool_call_id'] ?? '';
                $content = $message['content'] ?? '';

                if (! is_string($toolCallId) || $toolCallId === '') {
                    continue;
                }

                $composed[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => is_string($content) ? $content : '',
                ];

                continue;
            }

            if ($role === 'assistant' && isset($message['tool_calls']) && is_array($message['tool_calls']) && $message['tool_calls'] !== []) {
                $content = $message['content'] ?? '';
                $composed[] = [
                    'role' => 'assistant',
                    'content' => is_string($content) ? $content : '',
                    'tool_calls' => $message['tool_calls'],
                ];

                continue;
            }

            $content = MessageContent::normalize($message['content'] ?? null);

            if (! in_array($role, ['system', 'user', 'assistant'], true) || $content === null) {
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
