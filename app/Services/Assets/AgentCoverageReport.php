<?php

namespace App\Services\Assets;

use App\Enums\EvidenceSourceType;
use App\Models\Asset;
use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Asset coverage and missed-asset report.
 *
 * The report deliberately reads only what OpenGRC already holds: the asset
 * register, Wazuh and PatchMon evidence, and the discovery mirror. NetBox,
 * Ralph and Fleet are reported as not integrated until their connectors exist;
 * they are not silently treated as coverage.
 */
class AgentCoverageReport
{
    public const WAZUH_EVIDENCE = 'sca-compliance';

    public const PATCHMON_EVIDENCE = 'patch-compliance';

    private const WAZUH_STALE_DAYS = 2;

    private const PATCHMON_STALE_DAYS = 2;

    private const DISCOVERY_STALE_DAYS = 7;

    /**
     * @return array{
     *     summary: array<string, int|float|null>,
     *     sources: array<string, bool>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function build(): array
    {
        $assets = Asset::query()->with('assetCriticality')->get();
        $assetLookup = $this->assetLookup($assets->all());
        $rows = [];

        foreach ($assets as $asset) {
            $rows['asset:'.$asset->getKey()] = $this->assetRow($asset);
        }

        $this->applyWazuh($rows, $assetLookup);
        $this->applyPatchMon($rows, $assetLookup);
        $this->applyDiscovery($rows, $assetLookup);
        $this->applyFindings($rows);

        $rows = $this->finalize($rows);

        return [
            'summary' => $this->summary($assets->count(), $rows),
            'sources' => $this->sourceStatus(),
            'rows' => array_values($rows),
        ];
    }

    /**
     * @param  array<int, Asset>  $assets
     * @return array<string, int>
     */
    private function assetLookup(array $assets): array
    {
        $lookup = [];

        foreach ($assets as $asset) {
            foreach ($this->assetKeys($asset) as $key) {
                $lookup[$key] ??= $asset->getKey();
            }
        }

        return $lookup;
    }

    /**
     * @return array<string, mixed>
     */
    private function assetRow(Asset $asset): array
    {
        return [
            'key' => 'asset:'.$asset->getKey(),
            'asset_id' => $asset->getKey(),
            'asset_name' => $asset->name ?: $asset->hostname ?: $asset->asset_tag ?: 'Asset #'.$asset->getKey(),
            'documented' => true,
            'owner' => $asset->assigned_to_user_id !== null,
            'criticality' => $asset->assetCriticality?->name,
            'wazuh' => null,
            'patchmon' => null,
            'discovery' => null,
            'open_findings' => 0,
            'critical_high' => 0,
            'last_seen' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function observedRow(?string $name): array
    {
        return [
            'key' => null,
            'asset_id' => null,
            'asset_name' => $name ?: 'Unmapped host',
            'documented' => false,
            'owner' => false,
            'criticality' => null,
            'wazuh' => null,
            'patchmon' => null,
            'discovery' => null,
            'open_findings' => 0,
            'critical_high' => 0,
            'last_seen' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rows
     * @param  array<string, int>  $assetLookup
     */
    private function applyWazuh(array &$rows, array $assetLookup): void
    {
        foreach ($this->latestEvidence(self::WAZUH_EVIDENCE) as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $agent = is_array($payload['agent'] ?? null) ? $payload['agent'] : [];
            $agentId = $this->normalise($agent['id'] ?? null);
            $name = is_string($agent['name'] ?? null) ? $agent['name'] : null;
            $keys = $this->recordKeys([
                $name,
                $agentId,
                $agentId ? 'wazuh:'.$agentId : null,
                $agentId ? 'wazuh:sca:'.$agentId : null,
                $agent['ip'] ?? null,
            ]);

            $rowKey = $this->resolveRowKey($record->asset_id, $keys, $assetLookup, $rows);
            $rowKey ??= $this->ensureObserved($rows, $keys[0] ?? $record->external_id, $name);

            $active = (bool) ($payload['agentActive'] ?? (($agent['status'] ?? null) === 'active'));
            $collected = $record->collected_at;

            if ($this->isNewer($rows[$rowKey]['wazuh'], $collected)) {
                $rows[$rowKey]['wazuh'] = [
                    'status' => is_string($agent['status'] ?? null) ? $agent['status'] : null,
                    'active' => $active,
                    'collected_at' => $collected,
                    'stale' => $this->isStale($collected, self::WAZUH_STALE_DAYS) || ! $active,
                    'fail' => is_numeric($payload['fail'] ?? null) ? (int) $payload['fail'] : null,
                    'invalid' => is_numeric($payload['invalid'] ?? null) ? (int) $payload['invalid'] : null,
                    'policies' => is_numeric($payload['policyCount'] ?? null) ? (int) $payload['policyCount'] : null,
                ];
            }

            $this->touch($rows[$rowKey], $collected);
        }
    }

    /**
     * @param  array<string, mixed>  $rows
     * @param  array<string, int>  $assetLookup
     */
    private function applyPatchMon(array &$rows, array $assetLookup): void
    {
        foreach ($this->latestEvidence(self::PATCHMON_EVIDENCE) as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $host = is_array($payload['host'] ?? null) ? $payload['host'] : [];
            $hostname = is_string($host['hostname'] ?? null) ? $host['hostname'] : null;
            $keys = $this->recordKeys([
                $hostname,
                $host['ip'] ?? null,
                $hostname ? 'patchmon:'.$hostname : null,
            ]);

            $rowKey = $this->resolveRowKey($record->asset_id, $keys, $assetLookup, $rows);
            $rowKey ??= $this->ensureObserved($rows, $keys[0] ?? $record->external_id, $hostname);

            $reporting = (bool) ($payload['agentReporting'] ?? true);
            $reportAge = is_numeric($payload['reportAgeDays'] ?? null) ? (int) $payload['reportAgeDays'] : null;
            $collected = $record->collected_at;
            $stale = $this->isStale($collected, self::PATCHMON_STALE_DAYS)
                || ! $reporting
                || ($reportAge !== null && $reportAge > self::PATCHMON_STALE_DAYS);

            if ($this->isNewer($rows[$rowKey]['patchmon'], $collected)) {
                $rows[$rowKey]['patchmon'] = [
                    'reporting' => $reporting,
                    'collected_at' => $collected,
                    'stale' => $stale,
                    'report_status' => is_string($payload['reportStatus'] ?? null) ? $payload['reportStatus'] : null,
                    'security_updates' => is_numeric($payload['securityUpdates'] ?? null) ? (int) $payload['securityUpdates'] : null,
                    'outdated_packages' => is_numeric($payload['outdatedPackages'] ?? null) ? (int) $payload['outdatedPackages'] : null,
                    'needs_reboot' => (bool) ($payload['needsReboot'] ?? false),
                ];
            }

            $this->touch($rows[$rowKey], $collected);
        }
    }

    /**
     * @param  array<string, mixed>  $rows
     * @param  array<string, int>  $assetLookup
     */
    private function applyDiscovery(array &$rows, array $assetLookup): void
    {
        foreach ($this->latestScans() as $scan) {
            $name = is_string($scan->asset_name) ? $scan->asset_name : null;
            $keys = $this->recordKeys([$name]);
            $rowKey = $this->resolveRowKey(null, $keys, $assetLookup, $rows);
            $rowKey ??= $this->ensureObserved($rows, $keys[0] ?? 'scan:'.$scan->getKey(), $name);

            if ($this->isNewer($rows[$rowKey]['discovery'], $scan->synced_at)) {
                $rows[$rowKey]['discovery'] = [
                    'status' => $scan->status,
                    'synced_at' => $scan->synced_at,
                    'stale' => $this->isStale($scan->synced_at, self::DISCOVERY_STALE_DAYS),
                    'findings_count' => (int) $scan->findings_count,
                ];
            }

            $this->touch($rows[$rowKey], $scan->synced_at);
        }
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    private function applyFindings(array &$rows): void
    {
        $findings = DiscoveryFinding::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->selectRaw(
                'asset_name, count(*) as open_count, '
                .'sum(case when severity in (?, ?) then 1 else 0 end) as high_count',
                ['critical', 'high']
            )
            ->groupBy('asset_name')
            ->get();

        $byName = [];

        foreach ($findings as $finding) {
            $key = $this->normalise($finding->asset_name);

            if ($key !== null) {
                $byName[$key] = [
                    'open' => (int) $finding->getAttribute('open_count'),
                    'high' => (int) $finding->getAttribute('high_count'),
                ];
            }
        }

        foreach ($rows as &$row) {
            $key = $this->normalise($row['asset_name']);

            if ($key !== null && isset($byName[$key])) {
                $row['open_findings'] = $byName[$key]['open'];
                $row['critical_high'] = $byName[$key]['high'];
            }
        }

        unset($row);
    }

    /**
     * @param  array<string, mixed>  $rows
     * @return array<string, mixed>
     */
    private function finalize(array $rows): array
    {
        foreach ($rows as &$row) {
            $gaps = [];

            if (! $row['documented']) {
                $gaps[] = 'undocumented';
            }

            if ($row['documented'] && ! $row['owner']) {
                $gaps[] = 'unowned';
            }

            if ($row['documented'] && $row['wazuh'] === null) {
                $gaps[] = 'missing_wazuh';
            }

            if ($row['documented'] && $row['patchmon'] === null) {
                $gaps[] = 'missing_patchmon';
            }

            if ($row['discovery'] === null) {
                $gaps[] = 'unscanned';
            }

            if (($row['wazuh']['stale'] ?? false) === true) {
                $gaps[] = 'stale_wazuh';
            }

            if (($row['patchmon']['stale'] ?? false) === true) {
                $gaps[] = 'stale_patchmon';
            }

            if (($row['discovery']['stale'] ?? false) === true) {
                $gaps[] = 'stale_discovery';
            }

            $row['gaps'] = array_values(array_unique($gaps));
            $row['recommendation'] = $this->recommendation($row['gaps']);
            $row['priority'] = $this->priority($row);
        }

        unset($row);

        usort($rows, function (array $left, array $right): int {
            $rank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

            return [$rank[$right['priority']] ?? 0, $left['asset_name']]
                <=> [$rank[$left['priority']] ?? 0, $right['asset_name']];
        });

        return $rows;
    }

    /**
     * @param  array<int, string>  $gaps
     */
    private function recommendation(array $gaps): string
    {
        return match (true) {
            in_array('undocumented', $gaps, true) => 'Create an OpenGRC asset, assign an owner, then deploy the missing agents.',
            in_array('unowned', $gaps, true) => 'Assign an owner and criticality, then confirm agent coverage.',
            in_array('missing_wazuh', $gaps, true) => 'Deploy the Wazuh agent and verify it appears in the agent inventory.',
            in_array('missing_patchmon', $gaps, true) => 'Deploy the PatchMon agent and verify a package report arrives.',
            in_array('stale_wazuh', $gaps, true) => 'Check Wazuh agent connectivity; the last measurement is stale.',
            in_array('stale_patchmon', $gaps, true) => 'Check PatchMon reporting; the last package report is stale.',
            in_array('unscanned', $gaps, true) => 'Run a discovery scan or deploy the collector on this host.',
            in_array('stale_discovery', $gaps, true) => 'Refresh the discovery mirror; the last scan is stale.',
            default => 'Covered; keep the evidence fresh.',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function priority(array $row): string
    {
        $gaps = $row['gaps'];

        if (in_array('undocumented', $gaps, true)) {
            return 'critical';
        }

        if ($row['critical_high'] > 0 && ! $row['owner']) {
            return 'critical';
        }

        if (in_array('missing_wazuh', $gaps, true) || in_array('missing_patchmon', $gaps, true)) {
            return 'high';
        }

        if ($row['critical_high'] > 0
            || in_array('stale_wazuh', $gaps, true)
            || in_array('stale_patchmon', $gaps, true)) {
            return 'high';
        }

        if (in_array('unowned', $gaps, true)
            || in_array('unscanned', $gaps, true)
            || in_array('stale_discovery', $gaps, true)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $rows
     */
    private function ensureObserved(array &$rows, string $key, ?string $name): string
    {
        $rowKey = 'observed:'.$key;

        if (! isset($rows[$rowKey])) {
            $rows[$rowKey] = $this->observedRow($name);
            $rows[$rowKey]['key'] = $rowKey;
        } elseif (($rows[$rowKey]['asset_name'] ?? 'Unmapped host') === 'Unmapped host' && $name !== null) {
            $rows[$rowKey]['asset_name'] = $name;
        }

        return $rowKey;
    }

    /**
     * @param  array<string, mixed>  $rows
     * @param  array<string, int>  $assetLookup
     * @param  array<int, string>  $keys
     */
    private function resolveRowKey(mixed $assetId, array $keys, array $assetLookup, array $rows): ?string
    {
        if (is_numeric($assetId) && isset($rows['asset:'.(int) $assetId])) {
            return 'asset:'.(int) $assetId;
        }

        foreach ($keys as $key) {
            if (isset($assetLookup[$key]) && isset($rows['asset:'.$assetLookup[$key]])) {
                return 'asset:'.$assetLookup[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function recordKeys(array $values): array
    {
        $keys = [];

        foreach ($values as $value) {
            $key = $this->normalise($value);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function assetKeys(Asset $asset): array
    {
        return $this->recordKeys([
            $asset->name,
            $asset->hostname,
            $asset->asset_tag,
            $asset->endpoint_agent_id,
            $asset->ip_address,
        ]);
    }

    private function normalise(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function isStale(?CarbonInterface $at, int $days): bool
    {
        return $at === null || $at->lt(now()->subDays($days));
    }

    /**
     * @param  array<string, mixed>|null  $current
     */
    private function isNewer(?array $current, ?CarbonInterface $at): bool
    {
        if ($current === null) {
            return true;
        }

        if ($at === null) {
            return false;
        }

        $existing = $current['collected_at'] ?? $current['synced_at'] ?? null;

        return ! $existing instanceof CarbonInterface || $at->gt($existing);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function touch(array &$row, ?CarbonInterface $at): void
    {
        if ($at === null) {
            return;
        }

        if ($row['last_seen'] === null || $at->gt($row['last_seen'])) {
            $row['last_seen'] = $at;
        }
    }

    /**
     * @return Collection<int, EvidenceRecord>
     */
    private function latestEvidence(string $type): Collection
    {
        $ids = EvidenceRecord::query()
            ->selectRaw('max(id)')
            ->where('evidence_type', $type)
            ->groupBy('external_id');

        return EvidenceRecord::query()
            ->whereIn('id', $ids)
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, DiscoveryScan>
     */
    private function latestScans(): Collection
    {
        $ids = DiscoveryScan::query()
            ->selectRaw('max(id)')
            ->groupBy('asset_name');

        return DiscoveryScan::query()
            ->whereIn('id', $ids)
            ->orderByDesc('synced_at')
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int|float|null>
     */
    private function summary(int $totalAssets, array $rows): array
    {
        $documented = count(array_filter($rows, fn (array $row): bool => $row['documented']));
        $undocumented = count($rows) - $documented;
        $unowned = count(array_filter($rows, fn (array $row): bool => $row['documented'] && ! $row['owner']));
        $wazuh = count(array_filter($rows, fn (array $row): bool => $row['documented'] && $row['wazuh'] !== null && ! $row['wazuh']['stale']));
        $patchmon = count(array_filter($rows, fn (array $row): bool => $row['documented'] && $row['patchmon'] !== null && ! $row['patchmon']['stale']));
        $discovery = count(array_filter($rows, fn (array $row): bool => $row['documented'] && $row['discovery'] !== null && ! $row['discovery']['stale']));
        $stale = count(array_filter($rows, fn (array $row): bool => in_array('stale_wazuh', $row['gaps'], true)
            || in_array('stale_patchmon', $row['gaps'], true)
            || in_array('stale_discovery', $row['gaps'], true)));
        $critical = count(array_filter($rows, fn (array $row): bool => in_array($row['priority'], ['critical', 'high'], true)));

        return [
            'assets_total' => $totalAssets,
            'documented' => $documented,
            'undocumented' => $undocumented,
            'unowned' => $unowned,
            'wazuh_covered' => $wazuh,
            'patchmon_covered' => $patchmon,
            'discovery_covered' => $discovery,
            'stale' => $stale,
            'critical_gaps' => $critical,
            'wazuh_percent' => $totalAssets > 0 ? round($wazuh / $totalAssets * 100, 1) : null,
            'patchmon_percent' => $totalAssets > 0 ? round($patchmon / $totalAssets * 100, 1) : null,
            'discovery_percent' => $totalAssets > 0 ? round($discovery / $totalAssets * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function sourceStatus(): array
    {
        return [
            'wazuh' => EvidenceRecord::query()->where('evidence_type', self::WAZUH_EVIDENCE)->exists()
                || EvidenceSource::query()->where('type', EvidenceSourceType::Wazuh)->exists(),
            'patchmon' => EvidenceRecord::query()->where('evidence_type', self::PATCHMON_EVIDENCE)->exists()
                || EvidenceSource::query()->where('type', EvidenceSourceType::Patchmon)->exists(),
            'discovery' => DiscoveryScan::query()->exists() || filled(config('services.discovery.base_url')),
            'netbox' => false,
            'ralph' => false,
            'fleet' => false,
        ];
    }
}
