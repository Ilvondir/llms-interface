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
}
