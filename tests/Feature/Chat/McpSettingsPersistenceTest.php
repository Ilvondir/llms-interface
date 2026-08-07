<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserChatSettings;
use App\Support\Chat\McpServerConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpSettingsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_patch_stores_encrypted_mcp_servers_without_leaking_token_in_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'exa',
                        'name' => 'Exa',
                        'url' => 'https://mcp.exa.ai/mcp',
                        'token' => 'secret-exa-key',
                    ],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('chatSettings.mcpServers.0.id', 'exa');
        $response->assertJsonPath('chatSettings.mcpServers.0.url', 'https://mcp.exa.ai/mcp');
        $response->assertJsonPath('chatSettings.mcpServers.0.hasToken', true);
        $this->assertArrayNotHasKey('token', $response->json('chatSettings.mcpServers.0'));

        $settings = UserChatSettings::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('secret-exa-key', $settings->mcp_servers[0]['token']);

        $raw = DB::table('user_chat_settings')->where('user_id', $user->id)->value('mcp_servers');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('secret-exa-key', $raw);
    }

    #[Test]
    public function empty_token_on_patch_preserves_existing_token(): void
    {
        $user = User::factory()->create();
        UserChatSettings::factory()->for($user)->create([
            'mcp_servers' => [
                [
                    'id' => 'exa',
                    'name' => 'Exa',
                    'url' => 'https://mcp.exa.ai/mcp',
                    'token' => 'keep-me',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'exa',
                        'name' => 'Exa Search',
                        'url' => 'https://mcp.exa.ai/mcp',
                        'token' => '',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('chatSettings.mcpServers.0.name', 'Exa Search')
            ->assertJsonPath('chatSettings.mcpServers.0.hasToken', true);

        $settings = UserChatSettings::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('keep-me', $settings->mcp_servers[0]['token']);
    }

    #[Test]
    public function omitted_token_key_preserves_existing_token(): void
    {
        $user = User::factory()->create();
        UserChatSettings::factory()->for($user)->create([
            'mcp_servers' => [
                [
                    'id' => 'exa',
                    'name' => 'Exa',
                    'url' => 'https://mcp.exa.ai/mcp',
                    'token' => 'keep-me',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'exa',
                        'name' => 'Exa',
                        'url' => 'https://mcp.exa.ai/mcp',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('chatSettings.mcpServers.0.hasToken', true);

        $settings = UserChatSettings::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('keep-me', $settings->mcp_servers[0]['token']);
    }

    #[Test]
    public function conversation_enabled_mcp_server_ids_are_filtered_to_known_servers(): void
    {
        $user = User::factory()->create();
        UserChatSettings::factory()->for($user)->create([
            'mcp_servers' => [
                [
                    'id' => 'exa',
                    'name' => 'Exa',
                    'url' => 'https://mcp.exa.ai/mcp',
                    'token' => 'secret',
                ],
            ],
        ]);
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson(route('conversations.update', $conversation), [
                'enabled_mcp_server_ids' => ['exa', 'unknown', 'exa'],
            ])
            ->assertOk()
            ->assertJsonPath('activeConversation.enabledMcpServerIds', ['exa']);

        $conversation->refresh();
        $this->assertSame(['exa'], $conversation->enabled_mcp_server_ids);
    }

    #[Test]
    public function mcp_server_validation_rejects_duplicate_ids_and_invalid_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'Bad_Id',
                        'name' => 'Bad',
                        'url' => 'https://mcp.exa.ai/mcp',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mcp_servers.0.id']);

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'exa',
                        'name' => 'A',
                        'url' => 'https://mcp.exa.ai/mcp',
                    ],
                    [
                        'id' => 'exa',
                        'name' => 'B',
                        'url' => 'https://mcp.exa.ai/mcp',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mcp_servers.1.id']);
    }

    #[Test]
    public function settings_patch_accepts_mcp_server_names_with_spaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('chat-settings.update'), [
                'mcp_servers' => [
                    [
                        'id' => 'exa',
                        'name' => 'Exa Search Tools',
                        'url' => 'https://mcp.exa.ai/mcp',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('chatSettings.mcpServers.0.name', 'Exa Search Tools');

        $settings = UserChatSettings::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Exa Search Tools', $settings->mcp_servers[0]['name']);
    }

    #[Test]
    public function merge_helper_replaces_token_when_non_empty(): void
    {
        $merged = McpServerConfig::mergeForStorage(
            [
                [
                    'id' => 'exa',
                    'name' => 'Exa',
                    'url' => 'https://mcp.exa.ai/mcp',
                    'token' => 'new-token',
                ],
            ],
            [
                [
                    'id' => 'exa',
                    'name' => 'Exa',
                    'url' => 'https://mcp.exa.ai/mcp',
                    'token' => 'old-token',
                ],
            ],
        );

        $this->assertSame('new-token', $merged[0]['token']);
    }
}
