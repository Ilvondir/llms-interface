<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\WebClient;

/**
 * HTTP MCP client that tolerates older protocol versions during initialize.
 */
class CompatibleWebClient extends WebClient
{
    public function __construct(
        HttpTransport $httpTransport,
        ?Implementation $clientInfo = null,
    ) {
        parent::__construct($httpTransport, $clientInfo);

        $this->protocol = new CompatibleProtocol($this->httpTransport, $this->clientInfo);
    }

    public static function connectTo(string $url): self
    {
        return new self(new HttpTransport($url));
    }
}
