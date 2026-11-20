<?php

namespace Tests\Feature;

use App\Services\Secrets\OpenBaoClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OpenBaoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openbao' => [
                'base_url' => 'https://bao.test',
                'token' => 'reader-token',
                'kv_mount' => 'secret',
                'timeout' => 5,
            ],
        ]);
    }

    #[Test]
    public function it_parses_an_openbao_reference(): void
    {
        $client = app(OpenBaoClient::class);

        $this->assertSame(
            ['mount' => 'secret', 'path' => 'opengrc/wazuh-api'],
            $client->parseReference('openbao://secret/opengrc/wazuh-api')
        );
        $this->assertSame(
            ['mount' => 'secret', 'path' => 'opengrc/wazuh-api'],
            $client->parseReference('openbao://opengrc/wazuh-api')
        );
        $this->assertSame(
            ['mount' => 'secret', 'path' => 'opengrc/wazuh-api'],
            $client->parseReference('openbao://secret/data/opengrc/wazuh-api')
        );
        $this->assertNull($client->parseReference('vault://secret/opengrc'));
    }

    #[Test]
    public function it_reads_kv_metadata_with_the_vault_token_header(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://bao.test/v1/secret/metadata/opengrc/wazuh-api' => Http::response([
                'data' => ['current_version' => 2],
            ]),
        ]);

        $metadata = app(OpenBaoClient::class)->kvMetadata('secret', 'opengrc/wazuh-api');

        $this->assertSame(2, $metadata['data']['current_version']);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://bao.test/v1/secret/metadata/opengrc/wazuh-api'
            && $request->hasHeader('X-Vault-Token', 'reader-token'));
    }

    #[Test]
    public function it_extracts_the_current_version_creation_time(): void
    {
        $metadata = [
            'data' => [
                'current_version' => 3,
                'versions' => [
                    '3' => ['created_time' => '2026-11-20T08:00:00Z'],
                ],
            ],
        ];

        $this->assertSame(
            '2026-11-20T08:00:00Z',
            app(OpenBaoClient::class)->rotationDate($metadata)?->toIso8601ZuluString()
        );
    }

    #[Test]
    public function an_unconfigured_client_is_rejected(): void
    {
        config(['services.openbao' => []]);

        $this->expectException(RuntimeException::class);

        app(OpenBaoClient::class)->kvMetadata('secret', 'opengrc/wazuh-api');
    }
}
