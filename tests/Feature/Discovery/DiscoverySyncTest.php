<?php

namespace Tests\Feature\Discovery;

use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use App\Services\Discovery\DiscoveryClient;
use App\Services\Discovery\DiscoverySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DiscoverySyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_mirrors_scans_and_findings_and_links_them(): void
    {
        $this->fakeConsole([$this->scan()], [$this->finding(), $this->finding(['id' => 'finding-2'])]);
        $this->configureConsole();

        $counts = $this->sync()->run();

        $this->assertSame(1, $counts['scans_seen']);
        $this->assertSame(1, $counts['scans_created']);
        $this->assertSame(0, $counts['scans_updated']);
        $this->assertSame(2, $counts['findings_seen']);
        $this->assertSame(2, $counts['findings_created']);
        $this->assertSame(0, $counts['findings_unlinked']);
        $this->assertSame(0, $counts['failed']);

        $scan = DiscoveryScan::query()->where('remote_id', 'scan-1')->firstOrFail();

        $this->assertSame('acme', $scan->tenant);
        $this->assertSame('asset-1', $scan->asset_id);
        $this->assertSame('web-01', $scan->asset_name);
        $this->assertSame('profile-1', $scan->profile_id);
        $this->assertSame('Baseline', $scan->profile_name);
        $this->assertSame('completed', $scan->status);
        $this->assertSame(150, $scan->files_seen);
        $this->assertSame(140, $scan->files_scanned);
        $this->assertSame(2, $scan->findings_count);
        $this->assertSame('2026-11-20 08:00:00', $scan->started_at->utc()->toDateTimeString());
        $this->assertSame('2026-11-20 08:05:00', $scan->finished_at->utc()->toDateTimeString());
        $this->assertNotNull($scan->synced_at);
        $this->assertCount(2, $scan->findings);

        $finding = DiscoveryFinding::query()->where('remote_id', 'finding-1')->firstOrFail();

        $this->assertSame($scan->id, $finding->discovery_scan_id);
        $this->assertSame('acme', $finding->tenant);
        $this->assertSame('scan-1', $finding->remote_scan_id);
        $this->assertSame('pii.email', $finding->detector_key);
        $this->assertSame('personal-data', $finding->category);
        $this->assertSame('high', $finding->severity);
        $this->assertSame('confidential', $finding->classification);
        $this->assertSame('/srv/app/.env', $finding->path);
        $this->assertSame('user@example.test', $finding->match_preview);
        $this->assertSame(str_repeat('a', 64), $finding->match_sha256);
        $this->assertSame(0.93, $finding->confidence);
        $this->assertSame('open', $finding->status);
        $this->assertNotNull($finding->synced_at);
        $this->assertSame($scan->id, $finding->scan?->id);
    }

    #[Test]
    public function a_finding_without_a_mirrored_scan_stays_unlinked(): void
    {
        $this->fakeConsole([], [$this->finding(['scan_id' => 'scan-missing'])]);
        $this->configureConsole();

        $counts = $this->sync()->run();

        $this->assertSame(1, $counts['findings_created']);
        $this->assertSame(1, $counts['findings_unlinked']);

        $finding = DiscoveryFinding::query()->where('remote_id', 'finding-1')->firstOrFail();

        $this->assertNull($finding->discovery_scan_id);
        $this->assertSame('scan-missing', $finding->remote_scan_id);
    }

    #[Test]
    public function a_second_run_updates_rows_instead_of_duplicating_them(): void
    {
        $this->fakeConsole([$this->scan()], [$this->finding()]);
        $this->configureConsole();
        $sync = $this->sync();

        $sync->run();
        $counts = $sync->run();

        $this->assertSame(1, $counts['scans_seen']);
        $this->assertSame(0, $counts['scans_created']);
        $this->assertSame(1, $counts['scans_updated']);
        $this->assertSame(1, $counts['findings_seen']);
        $this->assertSame(0, $counts['findings_created']);
        $this->assertSame(1, $counts['findings_updated']);
        $this->assertSame(1, DiscoveryScan::query()->count());
        $this->assertSame(1, DiscoveryFinding::query()->count());
    }

    #[Test]
    public function it_refreshes_changed_remote_values(): void
    {
        $scan = DiscoveryScan::query()->create([
            'tenant' => 'acme',
            'remote_id' => 'scan-1',
            'status' => 'running',
            'files_scanned' => 5,
            'synced_at' => now()->subDay(),
        ]);

        DiscoveryFinding::query()->create([
            'tenant' => 'acme',
            'remote_id' => 'finding-1',
            'remote_scan_id' => 'scan-1',
            'severity' => 'low',
            'synced_at' => now()->subDay(),
        ]);

        $this->fakeConsole(
            [$this->scan(['status' => 'completed', 'files_scanned' => 140])],
            [$this->finding(['severity' => 'critical'])],
        );
        $this->configureConsole();

        $counts = $this->sync()->run();

        $this->assertSame(1, $counts['scans_updated']);
        $this->assertSame(0, $counts['scans_created']);
        $this->assertSame(1, $counts['findings_updated']);

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame(140, $scan->files_scanned);

        $finding = DiscoveryFinding::query()->where('remote_id', 'finding-1')->firstOrFail();
        $this->assertSame('critical', $finding->severity);
        $this->assertSame($scan->id, $finding->discovery_scan_id);
    }

    #[Test]
    public function the_same_remote_id_in_two_tenants_is_two_rows(): void
    {
        $this->fakeConsole([$this->scan()], [$this->finding()]);

        $this->sync('acme')->run();
        $this->sync('globex')->run();

        $this->assertSame(2, DiscoveryScan::query()->count());
        $this->assertSame(2, DiscoveryFinding::query()->count());
        $this->assertSame(
            ['acme', 'globex'],
            DiscoveryScan::query()->orderBy('tenant')->pluck('tenant')->all()
        );

        $globex = DiscoveryScan::query()->where('tenant', 'globex')->firstOrFail();
        $this->assertSame(1, $globex->findings()->count());
        $this->assertSame($globex->id, $globex->findings()->firstOrFail()->discovery_scan_id);
    }

    #[Test]
    public function it_refuses_to_run_when_the_console_is_not_configured(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured');

        (new DiscoverySync(new DiscoveryClient([])))->run();
    }

    #[Test]
    public function the_command_fails_when_the_console_is_not_configured(): void
    {
        config(['services.discovery' => []]);
        Http::fake();

        $this->artisan('opengrc:discovery:sync')
            ->expectsOutputToContain('OpenDiscoveryEngine is not configured')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    #[Test]
    public function the_command_prints_a_summary_table(): void
    {
        $this->fakeConsole([$this->scan()], [$this->finding()]);
        $this->configureConsole();

        $this->artisan('opengrc:discovery:sync')
            ->expectsOutputToContain('Scans')
            ->expectsOutputToContain('Findings')
            ->assertExitCode(0);

        $this->assertSame(1, DiscoveryScan::query()->count());
        $this->assertSame(1, DiscoveryFinding::query()->count());
    }

    #[Test]
    public function the_command_fails_when_the_console_errors(): void
    {
        $this->fakeConsole([], [], 500);
        $this->configureConsole();

        $this->artisan('opengrc:discovery:sync')
            ->expectsOutputToContain('Discovery sync failed')
            ->assertExitCode(1);
    }

    private function configureConsole(): void
    {
        config([
            'services.discovery' => [
                'base_url' => 'https://discovery.test',
                'token' => 'console-token',
                'tenant' => 'acme',
                'timeout' => 5,
            ],
        ]);
    }

    private function sync(string $tenant = 'acme'): DiscoverySync
    {
        return new DiscoverySync(new DiscoveryClient([
            'base_url' => 'https://discovery.test',
            'token' => 'console-token',
            'tenant' => $tenant,
            'timeout' => 5,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function fakeConsole(array $scans, array $findings, int $status = 200): void
    {
        Http::fake([
            'https://discovery.test/api/v1/console/scans' => Http::response(['data' => $scans], $status),
            'https://discovery.test/api/v1/console/findings' => Http::response(['data' => $findings], $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function scan(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function finding(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}
