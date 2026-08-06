<?php

namespace Tests\Unit\Chat;

use Laravel\Mcp\Client;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpLaravelClientAutoloadTest extends TestCase
{
    #[Test]
    public function laravel_mcp_client_class_is_autoloadable(): void
    {
        $this->assertTrue(class_exists(Client::class));
        $this->assertTrue(method_exists(Client::class, 'web'));
    }
}
