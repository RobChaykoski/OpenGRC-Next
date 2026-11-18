<?php

namespace App\Services\Evidence\Connectors;

use App\Contracts\EvidenceConnector;
use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * PatchMon pull connector (ADR 0009).
 *
 * Reads hosts, package statistics, system state, package reports, and the agent
 * queue over scoped Basic Authentication. Every request is GET; the read-only
 * HTTP macro refuses anything else.
 *
 * The record status is informational. The authoritative control state comes
 * from validation rules, which may map the same patch evidence to SI-2, A.8.8,
 * CC7.1, or any other framework in the catalog.
 */
class PatchMonConnector implements EvidenceConnector
{
    public const EVIDENCE_TYPE = 'patch-compliance';

    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Patchmon;
    }

    public function collect(EvidenceSource $source): iterable
    {
        $client = $this->client($source);
        $hostsPayload = $this->get($client, '/hosts');
        $hosts = $hostsPayload['hosts'] ?? [];

        if (! is_array($hosts)) {
            throw new InvalidArgumentException('PatchMon returned an invalid hosts payload.');
        }

        foreach ($hosts as $host) {
            if (! is_array($host)) {
                continue;
            }

            $hostId = $this->string($host['id'] ?? null);

            if ($hostId === null) {
                continue;
            }

            $hostname = $this->string($host['hostname'] ?? null) ?? $hostId;

            try {
                $stats = $this->get($client, "/hosts/{$hostId}/stats");
                $system = $this->get($client, "/hosts/{$hostId}/system");
                $reports = $this->get($client, "/hosts/{$hostId}/package_reports");
                $queue = $this->get($client, "/hosts/{$hostId}/agent_queue");
            } catch (Throwable $exception) {
                yield $this->unknown($source, $host, $hostname, $exception->getMessage());

                continue;
            }

            yield $this->normalise($source, $host, $hostname, $stats, $system, $reports, $queue);
        }
    }

    protected function client(EvidenceSource $source): PendingRequest
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];
        $baseUrl = $configuration['base_url'] ?? null;
        $username = $configuration['username'] ?? null;
        $password = $configuration['password'] ?? null;

        if (! is_string($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The PatchMon source needs a valid API base URL.');
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The PatchMon base URL must use http or https.');
        }

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new InvalidArgumentException('The PatchMon source needs a username and password.');
        }

        return Http::grcReadOnly(rtrim($baseUrl, '/'))
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->timeout((int) ($configuration['timeout'] ?? 15))
            ->connectTimeout(5);
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(PendingRequest $client, string $path): array
    {
        $payload = $client->get($path)->throw()->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("PatchMon returned a non-object payload for {$path}.");
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $host
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $system
     * @param  array<string, mixed>  $reports
     * @param  array<string, mixed>  $queue
     */
    protected function normalise(
        EvidenceSource $source,
        array $host,
        string $hostname,
        array $stats,
        array $system,
        array $reports,
        array $queue,
    ): NormalisedEvidence {
        $latest = $this->latestReport($reports);
        $reportStatus = $this->string($latest['status'] ?? null);
        $collectedAt = $this->date($latest['date'] ?? null) ?? CarbonImmutable::now()->utc();

        $securityUpdates = $this->int($stats['security_updates'] ?? null);
        $outdatedPackages = $this->int($stats['outdated_packages'] ?? null);
        $needsReboot = (bool) ($system['needs_reboot'] ?? false);
        $queueStatus = is_array($queue['queue_status'] ?? null) ? $queue['queue_status'] : [];
        $queueFailed = $this->int($queueStatus['failed'] ?? null);
        $agentReporting = $reportStatus === 'success' && $queueFailed === 0;

        $status = match (true) {
            $reportStatus !== 'success' => ControlStatus::Unknown,
            $securityUpdates > 0, $needsReboot => ControlStatus::Failed,
            default => ControlStatus::Effective,
        };

        return new NormalisedEvidence(
            externalId: 'patchmon:'.$hostname,
            evidenceType: self::EVIDENCE_TYPE,
            status: $status,
            collectedAt: $collectedAt,
            payload: [
                'source' => 'patchmon',
                'host' => [
                    'id' => $this->string($host['id'] ?? null),
                    'hostname' => $hostname,
                    'friendlyName' => $this->string($host['friendly_name'] ?? null),
                    'ip' => $this->string($host['ip'] ?? null),
                    'groups' => $this->groups($host['host_groups'] ?? null),
                ],
                'securityUpdates' => $securityUpdates,
                'outdatedPackages' => $outdatedPackages,
                'totalInstalledPackages' => $this->int($stats['total_installed_packages'] ?? null),
                'needsReboot' => $needsReboot,
                'kernelVersion' => $this->string($system['kernel_version'] ?? null),
                'reportStatus' => $reportStatus,
                'reportAgeDays' => $this->ageDays($collectedAt),
                'agentReporting' => $agentReporting,
                'queue' => [
                    'active' => $this->int($queueStatus['active'] ?? null),
                    'waiting' => $this->int($queueStatus['waiting'] ?? null),
                    'failed' => $queueFailed,
                    'delayed' => $this->int($queueStatus['delayed'] ?? null),
                ],
                'capturedAt' => $collectedAt->toIso8601ZuluString(),
            ],
            assetIdentifier: $hostname,
            controlIdentifier: $this->controlIdentifier($source),
        );
    }

    /**
     * @param  array<string, mixed>  $host
     */
    protected function unknown(
        EvidenceSource $source,
        array $host,
        string $hostname,
        string $error,
    ): NormalisedEvidence {
        return new NormalisedEvidence(
            externalId: 'patchmon:'.$hostname,
            evidenceType: self::EVIDENCE_TYPE,
            status: ControlStatus::Unknown,
            collectedAt: CarbonImmutable::now()->utc(),
            payload: [
                'source' => 'patchmon',
                'host' => [
                    'id' => $this->string($host['id'] ?? null),
                    'hostname' => $hostname,
                    'friendlyName' => $this->string($host['friendly_name'] ?? null),
                    'ip' => $this->string($host['ip'] ?? null),
                    'groups' => $this->groups($host['host_groups'] ?? null),
                ],
                'error' => $error,
                'capturedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
            ],
            assetIdentifier: $hostname,
            controlIdentifier: $this->controlIdentifier($source),
        );
    }

    /**
     * @param  array<string, mixed>  $reports
     * @return array<string, mixed>
     */
    protected function latestReport(array $reports): array
    {
        $list = $reports['reports'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        $latest = null;
        $latestDate = null;

        foreach ($list as $report) {
            if (! is_array($report)) {
                continue;
            }

            $date = $this->date($report['date'] ?? null);

            if ($date === null) {
                continue;
            }

            if ($latestDate === null || $date->greaterThan($latestDate)) {
                $latest = $report;
                $latestDate = $date;
            }
        }

        return is_array($latest) ? $latest : [];
    }

    protected function controlIdentifier(EvidenceSource $source): ?string
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];
        $identifier = $configuration['control_identifier'] ?? null;

        return is_string($identifier) && trim($identifier) !== '' ? trim($identifier) : null;
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

    protected function ageDays(CarbonImmutable $collectedAt): int
    {
        return max(0, (int) floor(abs($collectedAt->diffInDays(CarbonImmutable::now()))));
    }

    /**
     * @return array<int, string>
     */
    protected function groups(mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $names = [];

        foreach ($groups as $group) {
            $name = is_array($group) ? $this->string($group['name'] ?? null) : null;

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    protected function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
