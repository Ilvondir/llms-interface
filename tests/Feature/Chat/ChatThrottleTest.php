<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatThrottleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stream_returns_too_many_requests_when_throttle_exceeded(): void
    {
        config(['llms.throttle_per_minute' => 1]);

        Http::preventStrayRequests();

        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hi\"}}]}\n\n"."data: [DONE]\n\n";

        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response($sse, 200, [
                'Content-Type' => 'text/event-stream',
            ]),
        ]);

        $payload = [
            'api_base_url' => 'http://lm.test/v1',
            'model' => 'demo-model',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello',
                ],
            ],
        ];

        $this->postJson(route('chat.stream'), $payload)->assertOk();
        $this->postJson(route('chat.stream'), $payload)->assertStatus(429);
    }
}
