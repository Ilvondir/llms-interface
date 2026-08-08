<?php

namespace Tests\Unit\Support\Chat;

use App\Support\Chat\ChatContentLimits;
use App\Support\Chat\MessageContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageContentTest extends TestCase
{
    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    #[Test]
    public function it_round_trips_parts_through_storage_encoding(): void
    {
        $parts = [
            ['type' => 'text', 'text' => 'What is this?'],
            ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
        ];

        $stored = MessageContent::encodeForStorage($parts);
        $decoded = MessageContent::decodeFromStorage($stored);

        $this->assertIsString($stored);
        $this->assertSame($parts, $decoded);
        $this->assertSame('What is this?', MessageContent::plainText($decoded));
    }

    #[Test]
    public function it_preserves_legacy_string_content(): void
    {
        $this->assertSame('Hello', MessageContent::encodeForStorage('Hello'));
        $this->assertSame('Hello', MessageContent::decodeFromStorage('Hello'));
    }

    #[Test]
    public function it_rejects_oversized_string_content(): void
    {
        $error = MessageContent::validationError(str_repeat('a', ChatContentLimits::MAX_CONTENT_CHARS + 1));

        $this->assertNotNull($error);
        $this->assertStringContainsString((string) ChatContentLimits::MAX_CONTENT_CHARS, $error);
    }

    #[Test]
    public function it_rejects_more_than_one_image_part(): void
    {
        $error = MessageContent::validationError([
            ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
            ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('at most', $error);
    }

    #[Test]
    public function it_treats_mcp_style_typed_json_strings_as_opaque_text(): void
    {
        $mcpPayload = json_encode([
            [
                'type' => 'resource_link',
                'uri' => 'https://github.com/laravel/framework',
                'name' => 'laravel/framework',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->assertIsString($mcpPayload);
        $this->assertNull(MessageContent::validationError($mcpPayload));
        $this->assertSame($mcpPayload, MessageContent::encodeForStorage($mcpPayload));
        $this->assertSame($mcpPayload, MessageContent::decodeFromStorage($mcpPayload));
    }

    #[Test]
    public function it_does_not_treat_mixed_mcp_types_as_multimodal_parts(): void
    {
        $parts = [
            ['type' => 'text', 'text' => 'README'],
            ['type' => 'resource', 'resource' => ['uri' => 'file://README.md', 'text' => '# Hi']],
        ];

        $this->assertFalse(MessageContent::looksLikeParts($parts));

        $asString = (string) json_encode($parts, JSON_UNESCAPED_SLASHES);
        $this->assertNull(MessageContent::validationError($asString));
    }

    #[Test]
    public function it_still_rejects_unsupported_types_when_sent_as_content_array(): void
    {
        $error = MessageContent::validationError([
            ['type' => 'resource_link', 'uri' => 'https://example.test'],
        ]);

        $this->assertSame('Content part at index 0 has an unsupported type.', $error);
    }
}
