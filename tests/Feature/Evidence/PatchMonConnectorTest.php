<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Evidence\Connectors\PatchMonConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatchMonConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    #[Test]
    public function it_normalises_a_host_into_patch_evidence(): void
    {
        $this->fakePatchMon();

        $records = iterator_to_array(
            app(PatchMonConnector::class)->collect($this->source())
        );

        $this->assertCount(1, $records);

        $evidence = $records[0];

        $this->assertSame('patchmon:web-01', $evidence->externalId);
        $this->assertSame(PatchMonConnector::EVIDENCE_TYPE, $evidence->evidenceType);
        $this->assertSame(ControlStatus::Failed, $evidence->status);
        $this->assertSame('web-01', $evidence->assetIdentifier);
        $this->assertSame('SI-2', $evidence->controlIdentifier);
        $this->assertSame('2026-11-17T09:00:00Z', $evidence->collectedAt->toIso8601ZuluString());

        $this->assertSame(12, $evidence->payload['securityUpdates']);
        $this->assertSame(274, $evidence->payload['outdatedPackages']);
        $this->assertSame(1251, $evidence->payload['totalInstalledPackages']);
        $this->assertTrue($evidence->payload['needsReboot']);
        $this->assertTrue($evidence->payload['agentReporting']);
        $this->assertSame('success', $evidence->payload['reportStatus']);
        $this->assertSame(['Prod'], $evidence->payload['host']['groups']);
    }

    #[Test]
    public function every_request_is_a_read_request_with_basic_auth(): void
    {
        $this->fakePatchMon();

        iterator_to_array(app(PatchMonConnector::class)->collect($this->source()));

        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization'));
        Http::assertSentCount(5);
    }

    #[Test]
    public function a_missing_or_failed_report_is_unknown_not_effective(): void
    {
        $this->fakePatchMon([
            'https://patchmon.test/api/hosts/h1/package_reports' => Http::response([
                'reports' => [],
            ]),
        ]);

        $records = iterator_to_array(
            app(PatchMonConnector::class)->collect($this->source())
        );

        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertFalse($records[0]->payload['agentReporting']);
        $this->assertNull($records[0]->payload['reportStatus']);
    }

    #[Test]
    public function one_unreachable_host_does_not_fail_the_whole_collection(): void
    {
        $this->fakePatchMon([
            'https://patchmon.test/api/hosts/h1/stats' => Http::response([], 500),
        ]);

        $records = iterator_to_array(
            app(PatchMonConnector::class)->collect($this->source())
        );

        $this->assertCount(1, $records);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertArrayHasKey('error', $records[0]->payload);
    }

    #[Test]
    public function a_source_without_a_base_url_is_rejected(): void
    {
        $source = $this->source(['base_url' => null]);

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(app(PatchMonConnector::class)->collect($source));
    }

    protected function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create([
            'name' => 'PatchMon test',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
            'configuration' => array_merge([
                'base_url' => 'https://patchmon.test/api',
                'username' => 'opengrc',
                'password' => 'secret',
                'control_identifier' => 'SI-2',
            ], $overrides),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fakePatchMon(array $overrides = []): void
    {
        Http::fake(array_merge([
            'https://patchmon.test/api/hosts' => Http::response([
                'hosts' => [[
                    'id' => 'h1',
                    'hostname' => 'web-01',
                    'friendly_name' => 'Web 01',
                    'ip' => '10.0.0.10',
                    'host_groups' => [['id' => 'g1', 'name' => 'Prod']],
                ]],
                'total' => 1,
            ]),
            'https://patchmon.test/api/hosts/h1/stats' => Http::response([
                'security_updates' => 12,
                'outdated_packages' => 274,
                'total_installed_packages' => 1251,
                'total_repos' => 887,
            ]),
            'https://patchmon.test/api/hosts/h1/system' => Http::response([
                'needs_reboot' => true,
                'kernel_version' => '7.3.3',
            ]),
            'https://patchmon.test/api/hosts/h1/package_reports' => Http::response([
                'reports' => [[
                    'date' => '2026-11-17T09:00:00Z',
                    'status' => 'success',
                    'security_updates' => 12,
                    'outdated_packages' => 274,
                ]],
            ]),
            'https://patchmon.test/api/hosts/h1/agent_queue' => Http::response([
                'queue_status' => [
                    'active' => 0,
                    'waiting' => 0,
                    'failed' => 0,
                    'delayed' => 0,
                ],
            ]),
        ], $overrides));
    }
}
