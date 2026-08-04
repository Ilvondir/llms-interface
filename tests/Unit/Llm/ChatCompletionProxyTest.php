<?php

namespace Tests\Unit\Llm;

use App\Services\Llm\ChatCompletionProxy;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatCompletionProxyTest extends TestCase
{
    #[Test]
    public function it_normalizes_api_root_with_and_without_v1_suffix(): void
    {
        $proxy = new ChatCompletionProxy;

        $this->assertSame(
            'http://localhost:1234/v1',
            $proxy->normalizeApiRoot('http://localhost:1234'),
        );

        $this->assertSame(
            'http://localhost:1234/v1',
            $proxy->normalizeApiRoot('http://localhost:1234/v1/'),
        );
    }

    #[Test]
    public function chat_timeout_seconds_uses_config_and_rejects_zero(): void
    {
        config(['llms.timeout' => 600]);

        $this->assertSame(600, (new ChatCompletionProxy)->chatTimeoutSeconds());

        config(['llms.timeout' => 0]);

        $this->assertSame(1, (new ChatCompletionProxy)->chatTimeoutSeconds());
    }

    #[Test]
    public function stream_chat_completions_uses_configured_http_timeout(): void
    {
        config(['llms.timeout' => 600, 'llms.connect_timeout' => 12]);

        Http::preventStrayRequests();
        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response(
                "data: {\"choices\":[{\"delta\":{\"content\":\"hi\"}}]}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $response = (new ChatCompletionProxy)->streamChatCompletions('http://lm.test', [
            'model' => 'test',
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]);

        ob_start();
        $response->sendContent();
        ob_end_clean();

        Http::assertSentCount(1);
    }
}
