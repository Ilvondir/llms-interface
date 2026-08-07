<?php

namespace Tests\Unit\Mcp;

use App\Services\Mcp\CompatibleInitialize;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompatibleInitializeResultTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function supportedVersions(): array
    {
        $cases = [];

        foreach (ProtocolVersion::supported() as $version) {
            $cases[$version] = [$version];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('supportedVersions')]
    public function accepts_every_protocol_version_known_to_laravel_mcp(string $version): void
    {
        $result = CompatibleInitialize::resultFrom([
            'protocolVersion' => $version,
            'capabilities' => [],
            'serverInfo' => [
                'name' => 'smithery',
                'version' => '1.0.0',
            ],
        ]);

        $this->assertSame($version, $result->protocolVersion);
        $this->assertSame('smithery', $result->serverInfo->name);
    }

    #[Test]
    public function accepts_older_versions_that_stock_laravel_client_rejects(): void
    {
        $older = array_values(array_diff(
            ProtocolVersion::supported(),
            ProtocolVersion::clientSupported(),
        ));

        $this->assertNotEmpty(
            $older,
            'Expected laravel/mcp to keep older versions for servers while restricting the client.',
        );

        foreach ($older as $version) {
            $result = CompatibleInitialize::resultFrom([
                'protocolVersion' => $version,
                'capabilities' => (array) [],
                'serverInfo' => [
                    'name' => 'exa',
                    'version' => '0.1.0',
                ],
            ]);

            $this->assertSame($version, $result->protocolVersion);
        }
    }

    #[Test]
    public function rejects_unknown_protocol_versions_with_detail(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('unsupported protocol version [1999-01-01]');

        CompatibleInitialize::resultFrom([
            'protocolVersion' => '1999-01-01',
            'capabilities' => [],
            'serverInfo' => [
                'name' => 'x',
                'version' => '1',
            ],
        ]);
    }
}
