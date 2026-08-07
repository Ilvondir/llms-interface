<?php

namespace App\Services\Mcp;

use Illuminate\Support\Arr;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Schema\Implementation;

/**
 * Like Laravel MCP's Initialize, but accepts every protocol version the package
 * knows about (server-side). Upstream clientSupported() only allows the two
 * newest dates, which breaks hosts like Smithery that still negotiate older ones.
 *
 * @implements Method<InitializeResult>
 */
class CompatibleInitialize implements Method
{
    public function __construct(protected Implementation $clientInfo) {}

    public function method(): string
    {
        return 'initialize';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => (object) [],
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }

    public function handle(Protocol $protocol): InitializeResult
    {
        return self::resultFrom($protocol->dispatch($this));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resultFrom(array $payload): InitializeResult
    {
        $protocolVersion = Arr::get($payload, 'protocolVersion');
        $capabilities = Arr::get($payload, 'capabilities');
        /** @var array{name: string, version: string, title?: string, description?: string, icons?: array<int, array{src: string, mimeType?: string, sizes?: array<string>, theme?: string}>, websiteUrl?: string} $serverInfo */
        $serverInfo = Arr::get($payload, 'serverInfo');
        $serverName = Arr::get($serverInfo, 'name');
        $serverVersion = Arr::get($serverInfo, 'version');
        $instructions = Arr::get($payload, 'instructions');

        if (! is_string($protocolVersion) || ! in_array($protocolVersion, ProtocolVersion::supported(), true)) {
            $got = is_string($protocolVersion) ? $protocolVersion : get_debug_type($protocolVersion);

            throw new ClientException(
                'The server negotiated an unsupported protocol version ['.$got.']. Supported: '.implode(', ', ProtocolVersion::supported()).'.'
            );
        }

        if (! is_array($capabilities)
            || ! is_array($serverInfo)
            || ! is_string($serverName)
            || ! is_string($serverVersion)) {
            throw new ClientException('Invalid initialize response from server.');
        }

        return new InitializeResult(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: Implementation::from($serverInfo),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
