<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatStreamProxyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_stream_chat_completions_without_reasoning_in_upstream_payload(): void
    {
        Http::preventStrayRequests();

        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hi\"}}]}\n\n"."data: [DONE]\n\n";

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'system_prompt' => 'Be brief.',
            'temperature' => 0.2,
            'top_p' => 0.9,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello',
                    'reasoning' => 'client should not send this upstream',
                ],
                [
                    'role' => 'assistant',
                    'content' => 'Hey',
                    'reasoning' => 'secret',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertStreamed();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        $response->assertStreamedContent($sse);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'http://lm.test/v1/chat/completions') {
                return false;
            }

            $data = $request->data();

            $this->assertTrue($data['stream'] ?? false);
            $this->assertSame('demo-model', $data['model']);
            $this->assertSame(0.2, $data['temperature']);
            $this->assertSame(0.9, $data['top_p']);
            $this->assertArrayNotHasKey('max_tokens', $data);

            foreach ($data['messages'] as $message) {
                $this->assertArrayHasKey('role', $message);
                $this->assertArrayHasKey('content', $message);
                $this->assertArrayNotHasKey('reasoning', $message);
                $this->assertSame(['role', 'content'], array_keys($message));
            }

            $this->assertSame([
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hey'],
            ], $data['messages']);

            return true;
        });
    }

    #[Test]
    public function stream_rejects_invalid_payload(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'not-a-url',
            'model' => 'demo-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello',
                ],
            ],
        ]);

        $response->assertInvalid(['api_base_url']);
        Http::assertSentCount(0);
    }
}
