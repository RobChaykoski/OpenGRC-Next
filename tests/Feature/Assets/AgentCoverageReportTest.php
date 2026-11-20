<?php

namespace Tests\Feature\Assets;

use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\DiscoveryScan;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\User;
use App\Services\Assets\AgentCoverageReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentCoverageReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_finds_undocumented_unmanaged_unowned_and_unscanned_assets(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Admin]);
        $web = Asset::factory()->create([
            'name' => 'web-01',
            'hostname' => 'web-01',
            'asset_tag' => 'AT-WEB-01',
            'assigned_to_user_id' => $owner->getKey(),
        ]);
        $db = Asset::factory()->create([
            'name' => 'db-01',
            'hostname' => 'db-01',
            'asset_tag' => 'AT-DB-01',
            'assigned_to_user_id' => $owner->getKey(),
            'ip_address' => '10.0.0.2',
        ]);

        $wazuh = $this->source('Wazuh', EvidenceSourceType::Wazuh);
        $patchmon = $this->source('PatchMon', EvidenceSourceType::Patchmon);

        $this->evidence($wazuh, 'wazuh:sca:001', AgentCoverageReport::WAZUH_EVIDENCE, [
            'source' => 'wazuh',
            'agent' => ['id' => '001', 'name' => 'db-01', 'status' => 'active'],
            'agentActive' => true,
            'fail' => 0,
            'invalid' => 0,
            'policyCount' => 1,
        ]);

        $this->evidence($patchmon, 'patchmon:db-01', AgentCoverageReport::PATCHMON_EVIDENCE, [
            'source' => 'patchmon',
            'host' => ['hostname' => 'db-01', 'ip' => '10.0.0.2'],
            'agentReporting' => true,
            'reportStatus' => 'success',
            'reportAgeDays' => 0,
            'securityUpdates' => 0,
            'outdatedPackages' => 0,
        ]);

        DiscoveryScan::create([
            'remote_id' => 'scan-db-01',
            'asset_id' => 'ode-db-01',
            'asset_name' => 'db-01',
            'profile_id' => 'profile-1',
            'profile_name' => 'Baseline',
            'status' => 'completed',
            'synced_at' => now(),
        ]);

        $this->evidence($wazuh, 'wazuh:sca:099', AgentCoverageReport::WAZUH_EVIDENCE, [
            'source' => 'wazuh',
            'agent' => ['id' => '099', 'name' => 'ghost-01', 'status' => 'active'],
            'agentActive' => true,
            'fail' => 0,
            'invalid' => 0,
        ]);

        $report = app(AgentCoverageReport::class)->build();
        $rows = collect($report['rows'])->keyBy('asset_name');

        $this->assertTrue($rows['web-01']['documented']);
        $this->assertContains('missing_wazuh', $rows['web-01']['gaps']);
        $this->assertContains('missing_patchmon', $rows['web-01']['gaps']);
        $this->assertContains('unscanned', $rows['web-01']['gaps']);
        $this->assertNotContains('unowned', $rows['web-01']['gaps']);

        $this->assertSame([], $rows['db-01']['gaps']);
        $this->assertSame('low', $rows['db-01']['priority']);

        $this->assertFalse($rows['ghost-01']['documented']);
        $this->assertContains('undocumented', $rows['ghost-01']['gaps']);
        $this->assertSame('critical', $rows['ghost-01']['priority']);
        $this->assertStringContainsString('Create an OpenGRC asset', $rows['ghost-01']['recommendation']);

        $this->assertSame(2, $report['summary']['assets_total']);
        $this->assertSame(1, $report['summary']['undocumented']);
        $this->assertTrue($report['sources']['wazuh']);
        $this->assertTrue($report['sources']['patchmon']);
        $this->assertTrue($report['sources']['discovery']);
        $this->assertFalse($report['sources']['netbox']);
    }

    #[Test]
    public function a_stale_agent_does_not_count_as_coverage(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Admin]);
        Asset::factory()->create([
            'name' => 'old-01',
            'hostname' => 'old-01',
            'asset_tag' => 'AT-OLD-01',
            'assigned_to_user_id' => $owner->getKey(),
        ]);

        $wazuh = $this->source('Wazuh', EvidenceSourceType::Wazuh);

        $this->evidence($wazuh, 'wazuh:sca:005', AgentCoverageReport::WAZUH_EVIDENCE, [
            'source' => 'wazuh',
            'agent' => ['id' => '005', 'name' => 'old-01', 'status' => 'active'],
            'agentActive' => true,
            'fail' => 0,
            'invalid' => 0,
        ], now()->subDays(10));

        $report = app(AgentCoverageReport::class)->build();
        $row = collect($report['rows'])->firstWhere('asset_name', 'old-01');

        $this->assertContains('stale_wazuh', $row['gaps']);
        $this->assertSame(0, $report['summary']['wazuh_covered']);
    }

    protected function source(string $name, EvidenceSourceType $type): EvidenceSource
    {
        return EvidenceSource::create([
            'name' => $name,
            'type' => $type,
            'enabled' => true,
            'configuration' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function evidence(
        EvidenceSource $source,
        string $externalId,
        string $type,
        array $payload,
        mixed $collectedAt = null,
    ): EvidenceRecord {
        return EvidenceRecord::create([
            'source_id' => $source->getKey(),
            'external_id' => $externalId,
            'evidence_type' => $type,
            'status' => ControlStatus::Effective,
            'collected_at' => $collectedAt ?? now(),
            'payload' => $payload,
            'payload_sha256' => hash('sha256', json_encode($payload) ?: ''),
            'idempotency_key' => Str::uuid()->toString(),
            'ingested_at' => now(),
            'created_at' => now(),
        ]);
    }
}
