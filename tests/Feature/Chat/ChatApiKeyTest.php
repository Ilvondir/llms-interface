<?php

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Models\UserChatSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeModels(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://lm.test/v1/models' => Http::response(['data' => [['id' => 'model-a']]], 200),
        ]);
    }

    #[Test]
    public function guest_request_key_is_sent_as_bearer_token(): void
    {
        $this->fakeModels();

        $this->postJson(route('chat.models'), [
            'api_base_url' => 'http://lm.test',
            'api_key' => 'sk-guest',
        ])->assertOk();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-guest'));
    }

    #[Test]
    public function no_authorization_header_without_a_key(): void
    {
        $this->fakeModels();

        $this->postJson(route('chat.models'), ['api_base_url' => 'http://lm.test'])->assertOk();

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
    }

    #[Test]
    public function stream_sends_the_key_as_bearer_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://lm.test/v1/chat/completions' => Http::response("data: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $this->postJson(route('chat.stream'), [
            'api_base_url' => 'http://lm.test',
            'api_key' => 'sk-stream',
            'model' => 'model-a',
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ])->assertOk()->streamedContent();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-stream'));
    }

    #[Test]
    public function saved_key_is_encrypted_hidden_from_the_client_and_reported_as_flag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), ['api_key' => 'sk-secret'])
            ->assertOk();

        $response->assertJsonPath('chatSettings.hasApiKey', true);
        $this->assertStringNotContainsString('sk-secret', $response->getContent());

        $raw = DB::table('user_chat_settings')->where('user_id', $user->id)->value('api_key');
        $this->assertNotSame('sk-secret', $raw);
        $this->assertSame('sk-secret', UserChatSettings::where('user_id', $user->id)->first()->api_key);
    }

    #[Test]
    public function blank_key_keeps_the_saved_one_and_clear_removes_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('chat-settings.update'), ['api_key' => 'sk-secret']);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), ['api_key' => '', 'api_base_url' => 'http://lm.test'])
            ->assertJsonPath('chatSettings.hasApiKey', true);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), ['clear_api_key' => true])
            ->assertJsonPath('chatSettings.hasApiKey', false);

        $this->assertNull(UserChatSettings::where('user_id', $user->id)->first()->api_key);
    }

    #[Test]
    public function saved_key_is_used_when_the_request_has_none(): void
    {
        $this->fakeModels();
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('chat-settings.update'), ['api_key' => 'sk-saved']);

        $this->actingAs($user)
            ->postJson(route('chat.models'), ['api_base_url' => 'http://lm.test'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-saved'));
    }

    #[Test]
    public function request_key_overrides_the_saved_one(): void
    {
        $this->fakeModels();
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson(route('chat-settings.update'), ['api_key' => 'sk-saved']);

        $this->actingAs($user)
            ->postJson(route('chat.models'), ['api_base_url' => 'http://lm.test', 'api_key' => 'sk-override'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-override'));
    }

    #[Test]
    public function guests_cannot_save_a_key(): void
    {
        $this->patchJson(route('chat-settings.update'), ['api_key' => 'sk-secret'])->assertUnauthorized();
    }
}
