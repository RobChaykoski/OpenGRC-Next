<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Mirror the OpenDiscoveryEngine console into local read-only tables.
 *
 * The sync is one-way and idempotent: every remote scan and finding is upserted
 * by `remote_id`, and nothing is ever deleted. An object that disappears in the
 * engine stays in the mirror until an operator removes it deliberately. One bad
 * item is counted and skipped so it cannot discard the rest of a batch.
 */
class DiscoverySync
{
    public function __construct(protected DiscoveryClient $client) {}

    /**
     * @return array{
     *     scans_seen: int,
     *     scans_created: int,
     *     scans_updated: int,
     *     scans_skipped: int,
     *     findings_seen: int,
     *     findings_created: int,
     *     findings_updated: int,
     *     findings_unlinked: int,
     *     findings_skipped: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    public function run(): array
    {
        if (! $this->client->configured()) {
            throw new RuntimeException(
                'The OpenDiscoveryEngine console is not configured. '
                .'Set DISCOVERY_CONSOLE_URL, DISCOVERY_CONSOLE_TOKEN and DISCOVERY_CONSOLE_TENANT.'
            );
        }

        $tenant = $this->client->tenant();

        if ($tenant === null) {
            throw new RuntimeException(
                'The OpenDiscoveryEngine console has no tenant configured. '
                .'Set DISCOVERY_CONSOLE_TENANT.'
            );
        }

        $counts = $this->emptyCounts();

        $scans = $this->client->scans();
        $counts['scans_seen'] = count($scans);

        foreach ($scans as $item) {
            $this->syncScan($tenant, $item, $counts);
        }

        $scanIds = DiscoveryScan::query()
            ->where('tenant', $tenant)
            ->pluck('id', 'remote_id');

        $findings = $this->client->findings();
        $counts['findings_seen'] = count($findings);

        foreach ($findings as $item) {
            $this->syncFinding($tenant, $item, $scanIds, $counts);
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $counts
     */
    protected function syncScan(string $tenant, array $item, array &$counts): void
    {
        $remoteId = $this->string($item['id'] ?? null);

        if ($remoteId === null) {
            $counts['scans_skipped']++;

            return;
        }

        try {
            $scan = DiscoveryScan::query()->firstOrNew([
                'tenant' => $tenant,
                'remote_id' => $remoteId,
            ]);

            $created = ! $scan->exists;

            $scan->fill([
                'asset_id' => $this->string($item['asset_id'] ?? null),
                'asset_name' => $this->string($item['asset_name'] ?? null),
                'profile_id' => $this->string($item['profile_id'] ?? null),
                'profile_name' => $this->string($item['profile_name'] ?? null),
                'status' => $this->string($item['status'] ?? null),
                'files_seen' => $this->int($item['files_seen'] ?? null),
                'files_scanned' => $this->int($item['files_scanned'] ?? null),
                'findings_count' => $this->int($item['findings_count'] ?? null),
                'error' => $this->string($item['error'] ?? null),
                'started_at' => $this->date($item['started_at'] ?? null),
                'finished_at' => $this->date($item['finished_at'] ?? null),
                'remote_created_at' => $this->date($item['created_at'] ?? null),
                'synced_at' => now(),
            ])->save();

            $created ? $counts['scans_created']++ : $counts['scans_updated']++;
        } catch (Throwable $exception) {
            $this->recordFailure($counts, "scan {$remoteId}", $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, int>  $scanIds
     * @param  array<string, mixed>  $counts
     */
    protected function syncFinding(string $tenant, array $item, Collection $scanIds, array &$counts): void
    {
        $remoteId = $this->string($item['id'] ?? null);

        if ($remoteId === null) {
            $counts['findings_skipped']++;

            return;
        }

        $remoteScanId = $this->string($item['scan_id'] ?? null);
        $localScanId = $remoteScanId === null ? null : $scanIds->get($remoteScanId);

        if ($remoteScanId !== null && $localScanId === null) {
            $counts['findings_unlinked']++;
        }

        try {
            $finding = DiscoveryFinding::query()->firstOrNew([
                'tenant' => $tenant,
                'remote_id' => $remoteId,
            ]);

            $created = ! $finding->exists;

            $finding->fill([
                'discovery_scan_id' => $localScanId,
                'remote_scan_id' => $remoteScanId,
                'asset_id' => $this->string($item['asset_id'] ?? null),
                'asset_name' => $this->string($item['asset_name'] ?? null),
                'detector_key' => $this->string($item['detector_key'] ?? null),
                'category' => $this->string($item['category'] ?? null),
                'severity' => $this->string($item['severity'] ?? null),
                'classification' => $this->string($item['classification'] ?? null),
                'path' => $this->string($item['path'] ?? null),
                'match_preview' => $this->string($item['match_preview'] ?? null),
                'match_sha256' => $this->string($item['match_sha256'] ?? null),
                'confidence' => $this->float($item['confidence'] ?? null),
                'status' => $this->string($item['status'] ?? null),
                'remote_created_at' => $this->date($item['created_at'] ?? null),
                'synced_at' => now(),
            ])->save();

            $created ? $counts['findings_created']++ : $counts['findings_updated']++;
        } catch (Throwable $exception) {
            $this->recordFailure($counts, "finding {$remoteId}", $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $counts
     */
    protected function recordFailure(array &$counts, string $subject, Throwable $exception): void
    {
        $counts['failed']++;

        if (count($counts['errors']) < 5) {
            $counts['errors'][] = "{$subject}: {$exception->getMessage()}";
        }
    }

    /**
     * @return array{
     *     scans_seen: int,
     *     scans_created: int,
     *     scans_updated: int,
     *     scans_skipped: int,
     *     findings_seen: int,
     *     findings_created: int,
     *     findings_updated: int,
     *     findings_unlinked: int,
     *     findings_skipped: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    protected function emptyCounts(): array
    {
        return [
            'scans_seen' => 0,
            'scans_created' => 0,
            'scans_updated' => 0,
            'scans_skipped' => 0,
            'findings_seen' => 0,
            'findings_created' => 0,
            'findings_updated' => 0,
            'findings_unlinked' => 0,
            'findings_skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    protected function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    protected function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
