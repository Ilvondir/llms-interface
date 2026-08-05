<?php

namespace Tests\Unit\Support\Chat;

use App\Support\Chat\RequestPayloadSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequestPayloadSanitizerTest extends TestCase
{
    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    #[Test]
    public function it_strips_image_url_parts_from_messages(): void
    {
        $sanitized = RequestPayloadSanitizer::sanitize([
            'model' => 'demo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hi'],
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                    ],
                ],
            ],
            'stream' => true,
        ]);

        $this->assertSame([
            ['type' => 'text', 'text' => 'Hi'],
            ['type' => 'text', 'text' => '[image omitted]'],
        ], $sanitized['messages'][0]['content']);
        $this->assertTrue($sanitized['stream']);
    }

    #[Test]
    public function it_collapses_single_omitted_image_to_plain_text(): void
    {
        $sanitized = RequestPayloadSanitizer::sanitize([
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                    ],
                ],
            ],
        ]);

        $this->assertSame('[image omitted]', $sanitized['messages'][0]['content']);
    }
}
