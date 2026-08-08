<?php

namespace App\Support\Chat;

/**
 * Normalize and inspect OpenAI-compatible message content (string | content parts).
 */
final class MessageContent
{
    /**
     * @return string|list<array<string, mixed>>|null Null when empty / unusable.
     */
    public static function normalize(mixed $content): string|array|null
    {
        if (is_string($content)) {
            $trimmed = trim($content);

            return $trimmed === '' ? null : $trimmed;
        }

        if (! is_array($content) || $content === []) {
            return null;
        }

        $parts = [];
        $imageCount = 0;

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            $type = $part['type'] ?? null;

            if ($type === 'text') {
                $text = isset($part['text']) && is_string($part['text'])
                    ? trim($part['text'])
                    : '';

                $parts[] = [
                    'type' => 'text',
                    'text' => $text,
                ];

                continue;
            }

            if ($type === 'image_url') {
                $url = data_get($part, 'image_url.url');

                if (! is_string($url) || $url === '' || ! preg_match(ChatContentLimits::IMAGE_DATA_URL_PATTERN, $url)) {
                    continue;
                }

                $imageCount++;

                if ($imageCount > ChatContentLimits::MAX_IMAGES_PER_MESSAGE) {
                    continue;
                }

                $parts[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $url,
                    ],
                ];
            }
        }

        if (self::isEmpty($parts)) {
            return null;
        }

        return $parts;
    }

    public static function isEmpty(mixed $content): bool
    {
        if (is_string($content)) {
            return trim($content) === '';
        }

        if (! is_array($content) || $content === []) {
            return true;
        }

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            $type = $part['type'] ?? null;

            if ($type === 'text' && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                return false;
            }

            if ($type === 'image_url') {
                $url = data_get($part, 'image_url.url');

                if (is_string($url) && $url !== '') {
                    return false;
                }
            }
        }

        return true;
    }

    public static function containsImage(mixed $content): bool
    {
        if (! is_array($content)) {
            return false;
        }

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (($part['type'] ?? null) !== 'image_url') {
                continue;
            }

            $url = data_get($part, 'image_url.url');

            if (is_string($url) && $url !== '') {
                return true;
            }
        }

        return false;
    }

    public static function plainText(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                $text = trim($part['text']);

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    public static function characterLength(mixed $content): int
    {
        if (is_string($content)) {
            return mb_strlen($content);
        }

        if (! is_array($content)) {
            return 0;
        }

        $length = 0;

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            if (($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                $length += mb_strlen($part['text']);
            }

            if (($part['type'] ?? null) === 'image_url') {
                $url = data_get($part, 'image_url.url');

                if (is_string($url)) {
                    $length += mb_strlen($url);
                }
            }
        }

        return $length;
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     */
    public static function looksLikeParts(array $parts): bool
    {
        if ($parts === []) {
            return false;
        }

        // Only treat as OpenAI chat multimodal content when every part uses a
        // type we support (text | image_url). MCP tool payloads often look like
        // [{ "type": "resource", ... }] / resource_link / etc. and must stay
        // opaque strings — otherwise validation rejects them as "unsupported type".
        foreach ($parts as $part) {
            if (! is_array($part) || ! array_key_exists('type', $part)) {
                return false;
            }

            $type = $part['type'];

            if ($type !== 'text' && $type !== 'image_url') {
                return false;
            }
        }

        return true;
    }

    /**
     * Persistable string for prompts.content (legacy text or JSON-encoded parts).
     */
    public static function encodeForStorage(mixed $content): string
    {
        if (is_string($content)) {
            $decoded = json_decode($content, true);

            if (is_array($decoded) && self::looksLikeParts($decoded)) {
                $normalized = self::normalize($decoded);

                return $normalized === null
                    ? $content
                    : (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return $content;
        }

        $normalized = self::normalize($content);

        if ($normalized === null) {
            return '';
        }

        if (is_string($normalized)) {
            return $normalized;
        }

        return (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return string|list<array<string, mixed>>
     */
    public static function decodeFromStorage(?string $stored): string|array
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        $decoded = json_decode($stored, true);

        if (is_array($decoded) && self::looksLikeParts($decoded)) {
            $normalized = self::normalize($decoded);

            return $normalized ?? $stored;
        }

        return $stored;
    }

    /**
     * Validate raw request content shape. Returns an error message or null when valid.
     */
    public static function validationError(mixed $content): ?string
    {
        if (is_string($content)) {
            if (mb_strlen($content) > ChatContentLimits::MAX_CONTENT_CHARS) {
                return 'The content may not be greater than '.ChatContentLimits::MAX_CONTENT_CHARS.' characters.';
            }

            $decoded = json_decode($content, true);

            if (is_array($decoded) && self::looksLikeParts($decoded)) {
                return self::validationError($decoded);
            }

            return null;
        }

        if (! is_array($content)) {
            return 'The content must be a string or an array of content parts.';
        }

        if ($content === []) {
            return 'The content parts array must not be empty.';
        }

        $imageCount = 0;

        foreach ($content as $index => $part) {
            if (! is_array($part)) {
                return "Content part at index {$index} must be an object.";
            }

            $type = $part['type'] ?? null;

            if ($type === 'text') {
                if (! array_key_exists('text', $part) || ! is_string($part['text'])) {
                    return "Content part at index {$index} of type text must include a string text field.";
                }

                continue;
            }

            if ($type === 'image_url') {
                $imageCount++;

                if ($imageCount > ChatContentLimits::MAX_IMAGES_PER_MESSAGE) {
                    return 'Each message may include at most '.ChatContentLimits::MAX_IMAGES_PER_MESSAGE.' image.';
                }

                $url = data_get($part, 'image_url.url');

                if (! is_string($url) || $url === '') {
                    return "Content part at index {$index} of type image_url must include image_url.url.";
                }

                if (! preg_match(ChatContentLimits::IMAGE_DATA_URL_PATTERN, $url)) {
                    return "Content part at index {$index} image_url.url must be a data:image/(jpeg|png|gif|webp);base64 URL.";
                }

                continue;
            }

            return "Content part at index {$index} has an unsupported type.";
        }

        if (self::isEmpty($content)) {
            return 'The content must include non-empty text or an image.';
        }

        if (self::characterLength($content) > ChatContentLimits::MAX_CONTENT_CHARS) {
            return 'The content may not be greater than '.ChatContentLimits::MAX_CONTENT_CHARS.' characters.';
        }

        return null;
    }
}
