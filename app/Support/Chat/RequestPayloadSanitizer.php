<?php

namespace App\Support\Chat;

/**
 * Strip image bytes from stored assistant request_payload mirrors.
 */
final class RequestPayloadSanitizer
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (! isset($payload['messages']) || ! is_array($payload['messages'])) {
            return $payload;
        }

        $payload['messages'] = array_map(function (mixed $message): mixed {
            if (! is_array($message)) {
                return $message;
            }

            $content = $message['content'] ?? null;

            if (! is_array($content)) {
                return $message;
            }

            $nextContent = [];

            foreach ($content as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if (($part['type'] ?? null) === 'image_url') {
                    $nextContent[] = [
                        'type' => 'text',
                        'text' => '[image omitted]',
                    ];

                    continue;
                }

                $nextContent[] = $part;
            }

            if (count($nextContent) === 1
                && ($nextContent[0]['type'] ?? null) === 'text'
                && is_string($nextContent[0]['text'] ?? null)) {
                $message['content'] = $nextContent[0]['text'];
            } else {
                $message['content'] = $nextContent;
            }

            return $message;
        }, $payload['messages']);

        return $payload;
    }
}
