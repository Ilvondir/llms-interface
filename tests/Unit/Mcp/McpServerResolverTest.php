<?php

namespace Tests\Unit\Mcp;

use App\Models\User;
use App\Models\UserChatSettings;
use App\Services\Mcp\McpServerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpServerResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolve_for_user_reads_encrypted_servers_and_filters_enabled(): void
    {
        $user = User::factory()->create();
        UserChatSettings::factory()->for($user)->create([
            'mcp_servers' => [[
                'id' => 'exa',
                'name' => 'Exa',
                'url' => 'https://mcp.exa.ai/mcp',
                'token' => 'db-secret',
            ]],
        ]);

        $resolved = (new McpServerResolver)->resolve($user, [
            'enabled_mcp_server_ids' => ['exa'],
            'mcp_credentials' => [
                ['id' => 'exa', 'token' => 'ignored'],
            ],
        ]);

        $this->assertCount(1, $resolved);
        $this->assertSame('exa', $resolved[0]['id']);
        $this->assertSame('db-secret', $resolved[0]['token']);
    }
}
