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
 * Gophish awareness-simulation connector (ADR 0050).
 *
 * Gophish owns the campaign: targets, templates, sending, tracking and the raw
 * event timeline. OpenGRC reads the campaign **summary** — aggregate counts and
 * the campaign's dates — and never requests or stores a target row. No email
 * address, name, IP address, user agent, submitted credential or timeline entry
 * reaches an evidence payload.
 *
 * The connector does not score the programme. A click rate is a measurement, not
 * a verdict: whether it passes is a validation rule written against the
 * organisation's own target (ADR 0040, ADR 0050).
 *
 * A completed campaign is measured at its completion date, so re-collecting an
 * old campaign is a duplicate rather than a new record. A campaign still running
 * is measured at collection time so its counts keep moving.
 */
class GophishConnector implements EvidenceConnector
{
    public const EVIDENCE_TYPE = 'awareness-training';

    public const CAMPAIGNS_PATH = '/api/campaigns/';

    public const DEFAULT_MAX_CAMPAIGNS = 25;

    public const DEFAULT_LOOKBACK_DAYS = 180;

    /** A campaign limit that keeps one misconfigured run away from a worker. */
    protected const MAX_CAMPAIGN_CAP = 500;

    protected ?PendingRequest $client = null;

    protected int $maxCampaigns = self::DEFAULT_MAX_CAMPAIGNS;

    protected int $lookbackDays = self::DEFAULT_LOOKBACK_DAYS;

    protected ?string $controlIdentifier = null;

    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::Gophish;
    }

    public function collect(EvidenceSource $source): iterable
    {
        $capturedAt = CarbonImmutable::now()->utc();
        $this->connect($source);

        $campaigns = $this->campaigns();

        if ($campaigns === []) {
            yield new NormalisedEvidence(
                externalId: 'gophish:campaigns',
                evidenceType: self::EVIDENCE_TYPE,
                status: ControlStatus::Unknown,
                collectedAt: $capturedAt,
                payload: [
                    'source' => 'gophish',
                    'reason' => 'no_campaigns',
                    'campaignCount' => 0,
                    'lookbackDays' => $this->lookbackDays,
                    'maxCampaigns' => $this->maxCampaigns,
                    'aggregateOnly' => true,
                    'capturedAt' => $capturedAt->toIso8601ZuluString(),
                ],
                controlIdentifier: $this->controlIdentifier,
            );

            return;
        }

        foreach ($campaigns as $campaign) {
            $campaignId = $this->int($campaign['id'] ?? null);
            $campaignRef = $campaignId > 0 ? (string) $campaignId : null;
            $name = $this->string($campaign['name'] ?? null);

            if ($campaignRef === null) {
                continue;
            }

            try {
                $summary = $this->get('/api/campaigns/'.rawurlencode($campaignRef).'/summary');
            } catch (Throwable $exception) {
                yield $this->unknown($campaignRef, $name, 'summary_unavailable', $exception->getMessage());

                continue;
            }

            $stats = is_array($summary['stats'] ?? null) ? $summary['stats'] : null;

            if ($stats === null) {
                yield $this->unknown($campaignRef, $name, 'summary_incomplete', 'The campaign summary has no stats object.');

                continue;
            }

            yield $this->normalise($campaign, $summary, $stats, $capturedAt);
        }
    }

    protected function connect(EvidenceSource $source): void
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];

        $this->maxCampaigns = min(
            self::MAX_CAMPAIGN_CAP,
            max(1, $this->int($configuration['max_campaigns'] ?? null) ?: self::DEFAULT_MAX_CAMPAIGNS),
        );

        $this->lookbackDays = max(1, $this->int($configuration['lookback_days'] ?? null) ?: self::DEFAULT_LOOKBACK_DAYS);

        $identifier = $configuration['control_identifier'] ?? null;
        $this->controlIdentifier = is_string($identifier) && trim($identifier) !== '' ? trim($identifier) : null;

        $baseUrl = $configuration['base_url'] ?? null;
        $apiKey = $configuration['token'] ?? null;
        $caBundle = $configuration['ca_bundle'] ?? null;

        if (! is_string($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The Gophish source needs a valid API base URL.');
        }

        if (! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The Gophish base URL must use http or https.');
        }

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new InvalidArgumentException('The Gophish source needs an API key.');
        }

        $this->client = Http::grcReadOnly(
            $this->apiRoot($baseUrl),
            null,
            [],
            is_string($caBundle) && $caBundle !== '' ? $caBundle : null,
        )
            // Gophish expects the raw key in the Authorization header, not a
            // bearer token. The read-only macro still blocks every write.
            ->withHeaders(['Authorization' => trim($apiKey)])
            ->acceptJson()
            ->timeout((int) ($configuration['timeout'] ?? 15))
            ->connectTimeout(5);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function campaigns(): array
    {
        $payload = $this->get(self::CAMPAIGNS_PATH);

        // Gophish answers an API error with `{message, success}` at HTTP 200.
        // Treat it as a failed run, not as an empty campaign list.
        if (isset($payload['success']) || isset($payload['message'])) {
            throw new InvalidArgumentException(
                $this->string($payload['message'] ?? null) ?? 'Gophish returned an error envelope.'
            );
        }

        $cutoff = CarbonImmutable::now()->utc()->subDays($this->lookbackDays);

        $campaigns = [];

        foreach ($payload as $campaign) {
            if (! is_array($campaign) || $this->int($campaign['id'] ?? null) <= 0) {
                continue;
            }

            $launchedAt = $this->date($campaign['launch_date'] ?? null)
                ?? $this->date($campaign['created_date'] ?? null);

            if ($launchedAt !== null && $launchedAt->lessThan($cutoff)) {
                continue;
            }

            $campaigns[] = $campaign + ['_reference_date' => $launchedAt];
        }

        usort(
            $campaigns,
            static fn (array $a, array $b): int => ($b['_reference_date']?->getTimestamp() ?? 0)
                <=> ($a['_reference_date']?->getTimestamp() ?? 0),
        );

        return array_slice($campaigns, 0, $this->maxCampaigns);
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $stats
     */
    protected function normalise(
        array $campaign,
        array $summary,
        array $stats,
        CarbonImmutable $capturedAt,
    ): NormalisedEvidence {
        $campaignId = $this->int($campaign['id'] ?? null);
        $name = $this->string($campaign['name'] ?? null) ?? $this->string($summary['name'] ?? null);

        $launchedAt = $this->date($campaign['launch_date'] ?? null);
        $completedAt = $this->date($campaign['completed_date'] ?? null);
        $sendByDate = $this->date($campaign['send_by_date'] ?? null);

        // A finished campaign is measured once, at its completion date, so a
        // later run deduplicates instead of appending the same result again.
        $collectedAt = $completedAt ?? $capturedAt;

        $total = $this->int($stats['total'] ?? null);
        $sent = $this->int($stats['sent'] ?? null);
        $opened = $this->int($stats['opened'] ?? null);
        $clicked = $this->int($stats['clicked'] ?? null);
        $submitted = $this->int($stats['submitted_data'] ?? null);
        $reported = $this->int($stats['email_reported'] ?? null);
        $errors = $this->int($stats['error'] ?? null);

        $denominator = $sent > 0 ? $sent : $total;

        return new NormalisedEvidence(
            externalId: 'gophish:campaign:'.$campaignId,
            evidenceType: self::EVIDENCE_TYPE,
            // Aggregate counts are a measurement. Only a validation rule turns
            // them into effective or failed against the programme's own target.
            status: ControlStatus::Unknown,
            collectedAt: $collectedAt,
            payload: [
                'source' => 'gophish',
                'campaign' => [
                    'id' => $campaignId,
                    'name' => $name,
                    'status' => $this->string($summary['status'] ?? null)
                        ?? $this->string($campaign['status'] ?? null),
                    'template' => $this->namedObject($campaign['template'] ?? null),
                    'landingPage' => $this->namedObject($campaign['page'] ?? null),
                    'groups' => $this->groupNames($campaign['groups'] ?? null),
                ],
                'stats' => [
                    'total' => $total,
                    'sent' => $sent,
                    'opened' => $opened,
                    'clicked' => $clicked,
                    'submittedData' => $submitted,
                    'emailReported' => $reported,
                    'error' => $errors,
                ],
                'rates' => [
                    'openedPct' => $this->percentage($opened, $denominator),
                    'clickedPct' => $this->percentage($clicked, $denominator),
                    'submittedPct' => $this->percentage($submitted, $denominator),
                    'reportedPct' => $this->percentage($reported, $denominator),
                ],
                'period' => [
                    'launchDate' => $launchedAt?->toIso8601ZuluString(),
                    'sendByDate' => $sendByDate?->toIso8601ZuluString(),
                    'completedDate' => $completedAt?->toIso8601ZuluString(),
                ],
                'campaignAgeDays' => $launchedAt === null
                    ? null
                    : max(0, (int) floor(abs($launchedAt->diffInDays(CarbonImmutable::now())))),
                'aggregateOnly' => true,
                'capturedAt' => $collectedAt->toIso8601ZuluString(),
            ],
            controlIdentifier: $this->controlIdentifier,
        );
    }

    protected function unknown(
        string $campaignRef,
        ?string $name,
        string $reason,
        ?string $error = null,
    ): NormalisedEvidence {
        $capturedAt = CarbonImmutable::now()->utc();

        return new NormalisedEvidence(
            externalId: 'gophish:campaign:'.$campaignRef,
            evidenceType: self::EVIDENCE_TYPE,
            status: ControlStatus::Unknown,
            collectedAt: $capturedAt,
            payload: [
                'source' => 'gophish',
                'campaign' => [
                    'id' => (int) $campaignRef,
                    'name' => $name,
                ],
                'reason' => $reason,
                'error' => $error,
                'aggregateOnly' => true,
                'capturedAt' => $capturedAt->toIso8601ZuluString(),
            ],
            controlIdentifier: $this->controlIdentifier,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        if (! $this->client instanceof PendingRequest) {
            throw new InvalidArgumentException('The Gophish connector has not been configured.');
        }

        $payload = $this->client->get($path)->throw()->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("Gophish returned a non-object payload for {$path}.");
        }

        return $payload;
    }

    /**
     * Accept either the web root or the API root and return the web root, so a
     * source configured as https://host or https://host/api both work.
     */
    protected function apiRoot(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (str_ends_with(strtolower($baseUrl), '/api')) {
            $baseUrl = substr($baseUrl, 0, -4);
        }

        return rtrim($baseUrl, '/');
    }

    protected function namedObject(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        return is_array($value) ? $this->string($value['name'] ?? null) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function groupNames(mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $names = [];

        foreach ($groups as $group) {
            $name = $this->namedObject($group);

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Gophish uses the zero time for a date that is not set.
     */
    protected function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, '0001-01-01')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    protected function percentage(int $part, int $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }

        return round($part / $whole * 100, 1);
    }

    protected function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
