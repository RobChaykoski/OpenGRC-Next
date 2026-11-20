<?php

namespace Tests\Feature\Discovery;

use App\Services\Discovery\DiscoveryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DiscoveryClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function client(array $overrides = []): DiscoveryClient
    {
        return new DiscoveryClient(array_merge([
            'base_url' => 'https://discovery.test',
            'token' => 'console-token',
            'tenant' => 'acme',
            'timeout' => 5,
        ], $overrides));
    }

    #[Test]
    public function it_is_configured_only_with_a_url_a_token_and_a_tenant(): void
    {
        $this->assertTrue($this->client()->configured());
        $this->assertFalse($this->client(['base_url' => null])->configured());
        $this->assertFalse($this->client(['token' => ''])->configured());
        $this->assertFalse($this->client(['base_url' => 'https://discovery.test', 'token' => null])->configured());
        $this->assertFalse($this->client(['tenant' => null])->configured());
        $this->assertFalse($this->client(['tenant' => ' '])->configured());
    }

    #[Test]
    public function it_reads_scans_with_a_bearer_token_and_json_accept_header(): void
    {
        Http::fake([
            'https://discovery.test/api/v1/console/scans' => Http::response([
                [
                    'id' => 'scan-1',
                    'asset_id' => 'asset-1',
                    'asset_name' => 'web-01',
                    'profile_id' => 'profile-1',
                    'profile_name' => 'Baseline',
                    'status' => 'completed',
                    'files_seen' => 150,
                    'files_scanned' => 140,
                    'findings_count' => 2,
                    'error' => null,
                    'started_at' => '2026-11-20T08:00:00Z',
                    'finished_at' => '2026-11-20T08:05:00Z',
                    'created_at' => '2026-11-20T08:00:00Z',
                ],
            ], 200),
        ]);

        $scans = $this->client()->scans();

        $this->assertCount(1, $scans);
        $this->assertSame('scan-1', $scans[0]['id']);
        $this->assertSame(140, $scans[0]['files_scanned']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discovery.test/api/v1/console/scans'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer console-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    #[Test]
    public function it_reads_findings(): void
    {
        Http::fake([
            'https://discovery.test/api/v1/console/findings' => Http::response([
                [
                    'id' => 'finding-1',
                    'scan_id' => 'scan-1',
                    'asset_id' => 'asset-1',
                    'asset_name' => 'web-01',
                    'detector_key' => 'pii.email',
                    'category' => 'personal-data',
                    'severity' => 'high',
                    'classification' => 'confidential',
                    'path' => '/srv/app/.env',
                    'match_preview' => 'user@example.test',
                    'match_sha256' => str_repeat('a', 64),
                    'confidence' => 0.93,
                    'status' => 'open',
                    'created_at' => '2026-11-20T08:05:00Z',
                ],
            ], 200),
        ]);

        $findings = $this->client()->findings();

        $this->assertCount(1, $findings);
        $this->assertSame('pii.email', $findings[0]['detector_key']);
        $this->assertSame(0.93, $findings[0]['confidence']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://discovery.test/api/v1/console/findings'
            && $request->method() === 'GET');
    }

    #[Test]
    public function it_unwraps_a_data_envelope(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['id' => 'scan-1'],
                    ['id' => 'scan-2'],
                ],
                'meta' => ['total' => 2],
            ], 200),
        ]);

        $this->assertSame(['scan-1', 'scan-2'], array_column($this->client()->scans(), 'id'));
    }

    #[Test]
    public function it_rejects_a_non_2xx_response(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'unauthorised'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->client()->scans();
    }

    #[Test]
    public function it_rejects_a_non_object_payload(): void
    {
        Http::fake([
            '*' => Http::response('null', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-object payload');

        $this->client()->scans();
    }

    #[Test]
    public function it_rejects_a_payload_without_an_item_list(): void
    {
        Http::fake([
            '*' => Http::response(['data' => 'not-a-list'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no [scans] list');

        $this->client()->scans();
    }

    #[Test]
    public function it_probes_health_without_parsing_the_body(): void
    {
        Http::fake([
            '*' => Http::response('', 204),
        ]);

        $health = $this->client()->health();

        $this->assertSame('https://discovery.test', $health['base_url']);
        $this->assertSame('acme', $health['tenant']);
        $this->assertSame(204, $health['status']);
    }

    #[Test]
    public function an_unconfigured_client_makes_no_request(): void
    {
        Http::fake();

        try {
            (new DiscoveryClient([]))->scans();

            $this->fail('An unconfigured client must not read the console.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not configured', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_non_http_url_is_refused_before_the_request_leaves(): void
    {
        Http::fake();

        try {
            $this->client(['base_url' => 'ftp://discovery.test'])->scans();

            $this->fail('A non-http console URL must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('http or https', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
