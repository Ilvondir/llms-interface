<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Support\Chat\ChatContentLimits;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatStreamMultimodalValidationTest extends TestCase
{
    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    #[Test]
    public function authenticated_user_can_stream_image_url_content_parts_to_upstream(): void
    {
        Http::preventStrayRequests();

        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"A cat\"}}]}\n\n"."data: [DONE]\n\n";

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $parts = [
            ['type' => 'text', 'text' => 'Describe'],
            ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
        ];

        $user = new User([
            'id' => 1,
            'name' => 'Vision User',
            'email' => 'vision@example.com',
        ]);

        $response = $this->actingAs($user)->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'vision-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $parts,
                    'reasoning' => 'should not leak',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertStreamed();

        Http::assertSent(function (Request $request) use ($parts) {
            if ($request->url() !== 'http://lm.test/v1/chat/completions') {
                return false;
            }

            $data = $request->data();

            $this->assertSame([
                [
                    'role' => 'user',
                    'content' => $parts,
                ],
            ], $data['messages']);

            foreach ($data['messages'] as $message) {
                $this->assertArrayNotHasKey('reasoning', $message);
                $this->assertSame(['role', 'content'], array_keys($message));
            }

            return true;
        });
    }

    #[Test]
    public function guest_cannot_stream_image_url_content_parts(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'vision-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                    ],
                ],
            ],
        ]);

        $response->assertInvalid(['messages.0.content']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_rejects_too_many_messages(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $messages = [];

        for ($i = 0; $i < ChatContentLimits::MAX_MESSAGES_PER_REQUEST + 1; $i++) {
            $messages[] = [
                'role' => 'user',
                'content' => "msg-{$i}",
            ];
        }

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => $messages,
        ]);

        $response->assertInvalid(['messages']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_rejects_unsupported_content_part_type(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'file', 'file' => ['url' => 'x']],
                    ],
                ],
            ],
        ]);

        $response->assertInvalid(['messages.0.content']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_rejects_more_than_one_image_per_message(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                    ],
                ],
            ],
        ]);

        $response->assertInvalid(['messages.0.content']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_rejects_non_data_image_url(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/cat.png']],
                    ],
                ],
            ],
        ]);

        $response->assertInvalid(['messages.0.content']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_returns_upstream_error_body_when_model_rejects_images(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response(
                ['error' => ['message' => 'This model does not support images']],
                400,
            ),
        ]);

        $user = new User([
            'id' => 1,
            'name' => 'Vision User',
            'email' => 'vision@example.com',
        ]);

        $response = $this->actingAs($user)->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'text-only',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => self::TINY_PNG]],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'does not support images',
            (string) $response->json('message'),
        );
    }
}
