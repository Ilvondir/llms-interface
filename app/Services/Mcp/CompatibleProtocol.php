<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Client\Protocol;
use Throwable;

/**
 * MCP client protocol that initializes with broader version acceptance.
 */
class CompatibleProtocol extends Protocol
{
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->connect();
        $this->connecting = true;

        try {
            $this->initializeResult = (new CompatibleInitialize($this->clientInfo))->handle($this);

            $this->transport->setProtocolVersion($this->initializeResult->protocolVersion);

            $this->notify('notifications/initialized');
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }
}
