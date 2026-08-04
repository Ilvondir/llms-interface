<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatModelsProxyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_list_models_through_proxy(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'http://lm.test/v1/models' => Http::response([
                'data' => [
                    ['id' => 'model-a'],
                    ['id' => 'model-b'],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('chat.models'), [
            'api_base_url' => 'http://lm.test',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', 'model-a');
        $response->assertJsonPath('data.1.id', 'model-b');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://lm.test/v1/models');
    }
}
