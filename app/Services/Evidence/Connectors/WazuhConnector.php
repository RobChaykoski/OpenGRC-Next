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
use RuntimeException;
use Throwable;

/**
 * Wazuh SCA pull connector (ADR 0009, ADR 0017).
 *
 * Wazuh's Security Configuration Assessment checks an agent against one or more
 * policies (CIS benchmark, PCI DSS, NIST, ...) and reports pass/fail/invalid
 * counts and a score. The connector emits one measurement per agent because the
 * validator reduces the latest record per asset: one record per policy would
 * silently discard every policy but the newest.
 *
 * The manager API mints a short-lived JWT with a POST to one fixed path; every
 * data request is GET. A disconnected agent or an incomplete scan is Unknown,
 * never Effective.
 */
class WazuhConnector implements EvidenceConnector
{
    public const EVIDENCE_TYPE = 'sca-compliance';

    public const AUTHENTICATION_PATH = '/security/user/authenticate';

    /** Page size Wazuh applies by default to list endpoints. */
    protected const PAGE_SIZE = 500;

    /** Bound a run so a misconfigured or enormous API cannot exhaust a worker. */
    protected const MAX_PAGES = 20;

    protected ?string $baseUrl = null;

    protected ?string $username = null;

    protected ?string $password = null;

    protected int $timeout = 15;

    protected ?string $token = null;

    protected ?string $caBundle = null;

    protected ?string $agentGroup = null;

    protected int $maxAgents = 10000;

    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Wazuh;
    }

    public function collect(EvidenceSource $source): iterable
    {
        $this->connect($source);

        $agents = $this->agents();

        if ($agents === []) {
            yield new NormalisedEvidence(
                externalId: 'wazuh:inventory',
                evidenceType: self::EVIDENCE_TYPE,
                status: ControlStatus::Unknown,
                collectedAt: CarbonImmutable::now()->utc(),
                payload: [
                    'source' => 'wazuh',
                    'reason' => 'no_agents',
                    'agentCount' => 0,
                    'capturedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
                ],
                controlIdentifier: $this->controlIdentifier($source),
            );

            return;
        }

        foreach ($agents as $agent) {
            $agentId = $this->string($agent['id'] ?? null);

            if ($agentId === null) {
                continue;
            }

            try {
                $payload = $this->get('/sca/'.rawurlencode($agentId));
            } catch (Throwable $exception) {
                yield $this->unknown($source, $agent, 'sca_unavailable', $exception->getMessage());

                continue;
            }

            $policies = $payload['data']['affected_items'] ?? null;

            if (! is_array($policies) || $policies === []) {
                yield $this->unknown($source, $agent, 'no_sca_policy', null);

                continue;
            }

            yield $this->normalise($source, $agent, $policies);
        }
    }

    protected function connect(EvidenceSource $source): void
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];
        $this->baseUrl = $this->baseUrl($configuration);
        $this->username = $this->string($configuration['username'] ?? null);
        $this->password = $this->string($configuration['password'] ?? null);
        $this->timeout = $this->timeout($configuration);
        $this->caBundle = $this->caBundle($configuration);
        $this->agentGroup = $this->string($configuration['agent_group'] ?? null);
        $this->maxAgents = $this->maxAgents($configuration);

        if ($this->username === null || $this->password === null) {
            throw new InvalidArgumentException('The Wazuh source needs an API username and password.');
        }

        $this->reauthenticate();
    }

    /**
     * Mint the short-lived token. This is the only permitted POST (ADR 0017).
     */
    protected function reauthenticate(): void
    {
        if ($this->baseUrl === null || $this->username === null || $this->password === null) {
            throw new InvalidArgumentException('The Wazuh client is not configured.');
        }

        $response = Http::grcAuthenticate($this->baseUrl, self::AUTHENTICATION_PATH, $this->caBundle)
            ->withBasicAuth($this->username, $this->password)
            ->timeout($this->timeout)
            ->connectTimeout(5)
            ->post(self::AUTHENTICATION_PATH);

        $response->throw();

        $payload = $response->json();
        $token = is_array($payload) ? ($payload['data']['token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw new InvalidArgumentException('Wazuh did not return an authentication token.');
        }

        $this->token = $token;
    }

    protected function read(): PendingRequest
    {
        return Http::grcReadOnly((string) $this->baseUrl, $this->token, [], $this->caBundle)
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout(5);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function baseUrl(array $configuration): string
    {
        $baseUrl = $configuration['base_url'] ?? null;

        if (! is_string($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The Wazuh source needs a valid API base URL.');
        }

        if (! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The Wazuh base URL must use http or https.');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function agents(): array
    {
        $agents = [];
        $total = null;
        $exhausted = true;
        $capped = false;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $params = [
                'limit' => self::PAGE_SIZE,
                'offset' => $page * self::PAGE_SIZE,
            ];

            if ($this->agentGroup !== null) {
                $params['group'] = $this->agentGroup;
            }

            $payload = $this->get('/agents', $params);

            $items = $payload['data']['affected_items'] ?? null;

            if (! is_array($items)) {
                throw new InvalidArgumentException('Wazuh returned an invalid agents payload.');
            }

            foreach ($items as $agent) {
                if (is_array($agent)) {
                    $agents[] = $agent;
                }
            }

            if (is_numeric($payload['data']['total_affected_items'] ?? null)) {
                $total = (int) $payload['data']['total_affected_items'];
            }

            if (count($agents) >= $this->maxAgents) {
                $agents = array_slice($agents, 0, $this->maxAgents);
                $capped = true;
                $exhausted = true;

                break;
            }

            if ($items === [] || ($total !== null && count($agents) >= $total)) {
                $exhausted = true;

                break;
            }

            $exhausted = false;
        }

        if (! $capped && $total !== null && count($agents) < $total) {
            throw new RuntimeException(sprintf(
                'Wazuh reports %d agents but the connector read %d. Raise the collection cap before enabling this source.',
                $total,
                count($agents),
            ));
        }

        if (! $exhausted) {
            throw new RuntimeException(
                'Wazuh returned more agents than the connector may page through. Raise the collection cap before enabling this source.'
            );
        }

        return $agents;
    }

    /**
     * @param  array<string, mixed>  $agent
     * @param  array<int, mixed>  $policies
     */
    protected function normalise(EvidenceSource $source, array $agent, array $policies): NormalisedEvidence
    {
        $agentId = $this->string($agent['id'] ?? null) ?? 'unknown';
        $agentName = $this->string($agent['name'] ?? null) ?? $agentId;
        $agentActive = $this->string($agent['status'] ?? null) === 'active';

        $pass = 0;
        $fail = 0;
        $invalid = 0;
        $total = 0;
        $score = null;
        $hasFailure = false;
        $hasIncomplete = false;
        $details = [];
        $latestScanEnd = null;
        $latestScanStart = null;

        foreach ($policies as $policy) {
            if (! is_array($policy)) {
                continue;
            }

            $policyPass = $this->intOrNull($policy['pass'] ?? null);
            $policyFail = $this->intOrNull($policy['fail'] ?? null);
            $policyInvalid = $this->intOrNull($policy['invalid'] ?? null);
            $policyTotal = $this->intOrNull($policy['total_checks'] ?? null);
            $policyScore = $this->intOrNull($policy['score'] ?? null);
            $scanStart = $this->date($policy['start_scan'] ?? null);
            $scanEnd = $this->date($policy['end_scan'] ?? null);

            if ($policyFail !== null && $policyFail > 0) {
                $hasFailure = true;
            }

            if ($policyPass === null || $policyFail === null || $policyInvalid === null || $policyTotal === null) {
                $hasIncomplete = true;
            } else {
                $pass += $policyPass;
                $fail += $policyFail;
                $invalid += $policyInvalid;
                $total += $policyTotal;

                if ($policyTotal <= 0 || $policyInvalid > 0 || $policyPass !== $policyTotal) {
                    $hasIncomplete = true;
                }
            }

            if ($policyScore !== null) {
                $score = $score === null ? $policyScore : min($score, $policyScore);
            }

            if ($scanEnd !== null && ($latestScanEnd === null || $scanEnd->greaterThan($latestScanEnd))) {
                $latestScanEnd = $scanEnd;
            }

            if ($scanStart !== null && ($latestScanStart === null || $scanStart->greaterThan($latestScanStart))) {
                $latestScanStart = $scanStart;
            }

            $details[] = [
                'id' => $this->string($policy['policy_id'] ?? null),
                'name' => $this->string($policy['name'] ?? null),
                'description' => $this->string($policy['description'] ?? null),
                'score' => $policyScore,
                'pass' => $policyPass,
                'fail' => $policyFail,
                'invalid' => $policyInvalid,
                'totalChecks' => $policyTotal,
                'scanStartedAt' => $scanStart?->toIso8601ZuluString(),
                'scanEndedAt' => $scanEnd?->toIso8601ZuluString(),
            ];
        }

        $collectedAt = $latestScanEnd
            ?? $latestScanStart
            ?? $this->date($agent['lastKeepAlive'] ?? null)
            ?? CarbonImmutable::now()->utc();

        $status = match (true) {
            ! $agentActive => ControlStatus::Unknown,
            $details === [] => ControlStatus::Unknown,
            $hasFailure => ControlStatus::Failed,
            $hasIncomplete => ControlStatus::Unknown,
            $total > 0 && $pass === $total && $invalid === 0 => ControlStatus::Effective,
            default => ControlStatus::Unknown,
        };

        return new NormalisedEvidence(
            externalId: 'wazuh:sca:'.$agentId,
            evidenceType: self::EVIDENCE_TYPE,
            status: $status,
            collectedAt: $collectedAt,
            payload: [
                'source' => 'wazuh',
                'agent' => $this->agentPayload($agent, $agentName),
                'score' => $score,
                'pass' => $pass,
                'fail' => $fail,
                'invalid' => $invalid,
                'totalChecks' => $total,
                'policyCount' => count($details),
                'policies' => $details,
                'agentActive' => $agentActive,
                'scanStartedAt' => $latestScanStart?->toIso8601ZuluString(),
                'scanEndedAt' => $latestScanEnd?->toIso8601ZuluString(),
                'scanAgeDays' => $latestScanEnd === null ? null : $this->ageDays($latestScanEnd),
                'capturedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
            ],
            assetIdentifier: $agentName,
            controlIdentifier: $this->controlIdentifier($source),
        );
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    protected function unknown(
        EvidenceSource $source,
        array $agent,
        string $reason,
        ?string $error,
    ): NormalisedEvidence {
        $agentId = $this->string($agent['id'] ?? null) ?? 'unknown';
        $agentName = $this->string($agent['name'] ?? null) ?? $agentId;

        // A missing policy is a configuration gap; keep one record rather than
        // writing a new one on every run. An outage is timestamped by the last
        // contact so repeated failures remain visible as history.
        $collectedAt = $reason === 'no_sca_policy'
            ? ($this->date($agent['dateAdd'] ?? null) ?? CarbonImmutable::now()->utc())
            : ($this->date($agent['lastKeepAlive'] ?? null) ?? CarbonImmutable::now()->utc());

        return new NormalisedEvidence(
            externalId: 'wazuh:sca:'.$agentId,
            evidenceType: self::EVIDENCE_TYPE,
            status: ControlStatus::Unknown,
            collectedAt: $collectedAt,
            payload: [
                'source' => 'wazuh',
                'agent' => $this->agentPayload($agent, $agentName),
                'reason' => $reason,
                'error' => $error,
                'capturedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
            ],
            assetIdentifier: $agentName,
            controlIdentifier: $this->controlIdentifier($source),
        );
    }

    /**
     * @param  array<string, mixed>  $agent
     * @return array<string, mixed>
     */
    protected function agentPayload(array $agent, string $agentName): array
    {
        return [
            'id' => $this->string($agent['id'] ?? null),
            'name' => $agentName,
            'status' => $this->string($agent['status'] ?? null),
            'version' => $this->string($agent['version'] ?? null),
            'ip' => $this->string($agent['ip'] ?? null),
            'os' => $this->os($agent['os'] ?? null),
            'groups' => $this->groups($agent['group'] ?? null),
            'lastKeepAlive' => $this->string($agent['lastKeepAlive'] ?? null),
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    protected function os(mixed $os): ?array
    {
        if (! is_array($os)) {
            return null;
        }

        return [
            'name' => $this->string($os['name'] ?? null),
            'version' => $this->string($os['version'] ?? null),
            'platform' => $this->string($os['platform'] ?? null),
            'arch' => $this->string($os['arch'] ?? null),
        ];
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
            $name = is_array($group) ? $this->string($group['name'] ?? null) : $this->string($group);

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        $response = $this->read()->get($path, $query);

        // A JWT lasts 900 seconds. A large fleet can outlive it; mint a new one
        // and retry rather than turning the rest of the run into Unknown.
        if ($response->status() === 401) {
            $this->reauthenticate();
            $response = $this->read()->get($path, $query);
        }

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("Wazuh returned a non-object payload for {$path}.");
        }

        if (isset($payload['error']) && (int) $payload['error'] !== 0) {
            $message = is_string($payload['message'] ?? null) ? $payload['message'] : 'unknown error';

            throw new RuntimeException("Wazuh API error for {$path}: {$message}");
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function caBundle(array $configuration): ?string
    {
        $path = $configuration['ca_bundle'] ?? null;

        if ($path === null || $path === '') {
            return null;
        }

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(
                'The Wazuh CA bundle must be a readable PEM file on the OpenGRC host.'
            );
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function timeout(array $configuration): int
    {
        $timeout = $configuration['timeout'] ?? 15;

        return is_numeric($timeout) ? max(1, min(120, (int) $timeout)) : 15;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function maxAgents(array $configuration): int
    {
        $max = $configuration['max_agents'] ?? 10000;

        return is_numeric($max) ? max(1, min(50000, (int) $max)) : 10000;
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

    protected function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
