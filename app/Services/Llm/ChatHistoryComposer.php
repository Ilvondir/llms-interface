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

            // Prior tool rounds stay in the UI/DB but are omitted from upstream history
            // so small-context models are not flooded with tool payloads. The current
            // turn's tool loop still appends calls/results inside ChatToolOrchestrator.
            if ($role === 'tool') {
                continue;
            }

            if ($role === 'assistant' && isset($message['tool_calls']) && is_array($message['tool_calls']) && $message['tool_calls'] !== []) {
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
