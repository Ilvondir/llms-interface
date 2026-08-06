<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stream_rejects_missing_model(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello',
                ],
            ],
        ]);

        $response->assertInvalid(['model']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_rejects_empty_messages(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [],
        ]);

        $response->assertInvalid(['messages']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function models_rejects_invalid_api_base_url(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.models'), [
            'api_base_url' => 'not-a-url',
        ]);

        $response->assertInvalid(['api_base_url']);
        Http::assertSentCount(0);
    }

    #[Test]
    public function stream_allows_empty_assistant_content_when_tool_calls_present(): void
    {
        Http::preventStrayRequests();

        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n";

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Search something'],
                [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'exa__search',
                            'arguments' => '{"q":"x"}',
                        ],
                    ]],
                ],
                [
                    'role' => 'tool',
                    'tool_call_id' => 'call_1',
                    'content' => 'result',
                ],
                ['role' => 'assistant', 'content' => 'First answer'],
                ['role' => 'user', 'content' => 'Follow up about ZETO'],
            ],
        ]);

        $response->assertOk();
        $response->assertStreamed();
        $this->assertStringContainsString('ok', $response->streamedContent());
    }

    #[Test]
    public function stream_still_rejects_empty_user_content(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $response = $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                ['role' => 'user', 'content' => ''],
            ],
        ]);

        $response->assertInvalid(['messages.0.content']);
        Http::assertSentCount(0);
    }
}
