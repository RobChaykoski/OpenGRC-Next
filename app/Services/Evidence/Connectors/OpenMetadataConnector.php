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

/**
 * OpenMetadata catalog governance connector (ADR 0020).
 *
 * The catalog owns data assets, owners, tags and lineage. OpenGRC reads a
 * bounded, metadata-only slice and turns it into coverage evidence per catalog
 * service. It never requests sample data, profiling values or table rows, and
 * it never writes to the catalog.
 *
 * A table counts as classified when the table or any column carries a tag in
 * one of the configured classification namespaces. Unknown tags are counted by
 * FQN but are never guessed into an OpenGRC classification code.
 */
class OpenMetadataConnector implements EvidenceConnector
{
    public const EVIDENCE_TYPE = 'catalog-governance';

    public const TABLES_PATH = '/v1/tables';

    protected ?PendingRequest $client = null;

    protected int $pageSize = 100;

    protected int $maxPages = 50;

    /** @var array<int, string> */
    protected array $classificationNames = [];

    /** @var array<string, string> */
    protected array $tagMap = [];

    public function type(): EvidenceSourceType
    {
        return EvidenceSourceType::OpenMetadata;
    }

    public function collect(EvidenceSource $source): iterable
    {
        $this->connect($source);

        $capturedAt = CarbonImmutable::now()->utc();
        $groups = [];

        foreach ($this->paginate(self::TABLES_PATH, [
            'fields' => 'columns,tags,owners,service,domains',
        ]) as $table) {
            $service = $this->serviceKey($table);

            $groups[$service] ??= [
                'name' => $service,
                'type' => $this->serviceType($table),
                'tables' => 0,
                'columns' => 0,
                'owned' => 0,
                'classified' => 0,
                'tags' => [],
                'mapped' => [],
            ];

            $groups[$service]['tables']++;
            $groups[$service]['columns'] += $this->columnCount($table);

            if ($this->hasOwner($table)) {
                $groups[$service]['owned']++;
            }

            $tags = $this->classificationTags($table);

            if ($tags !== []) {
                $groups[$service]['classified']++;
            }

            foreach ($tags as $tag) {
                $groups[$service]['tags'][$tag] = ($groups[$service]['tags'][$tag] ?? 0) + 1;

                $mapped = $this->tagMap[$tag] ?? null;

                if ($mapped !== null) {
                    $groups[$service]['mapped'][$mapped] = ($groups[$service]['mapped'][$mapped] ?? 0) + 1;
                }
            }
        }

        if ($groups === []) {
            yield new NormalisedEvidence(
                externalId: 'openmetadata:catalog',
                evidenceType: self::EVIDENCE_TYPE,
                status: ControlStatus::Unknown,
                collectedAt: $capturedAt,
                payload: [
                    'source' => 'openmetadata',
                    'reason' => 'no_tables',
                    'tableCount' => 0,
                    'classificationNamespaces' => $this->classificationNames,
                    'capturedAt' => $capturedAt->toIso8601ZuluString(),
                ],
                controlIdentifier: $this->controlIdentifier($source),
            );

            return;
        }

        foreach ($groups as $group) {
            $tables = $group['tables'];
            $owned = $group['owned'];
            $classified = $group['classified'];

            $status = match (true) {
                $tables === 0 => ControlStatus::Unknown,
                $owned < $tables || $classified < $tables => ControlStatus::Failed,
                default => ControlStatus::Effective,
            };

            yield new NormalisedEvidence(
                externalId: 'openmetadata:catalog:'.$group['name'],
                evidenceType: self::EVIDENCE_TYPE,
                status: $status,
                collectedAt: $capturedAt,
                payload: [
                    'source' => 'openmetadata',
                    'service' => [
                        'name' => $group['name'],
                        'type' => $group['type'],
                    ],
                    'tableCount' => $tables,
                    'columnCount' => $group['columns'],
                    'ownedTableCount' => $owned,
                    'classifiedTableCount' => $classified,
                    'unownedTableCount' => $tables - $owned,
                    'unclassifiedTableCount' => $tables - $classified,
                    'ownerCoveragePercent' => $this->percent($owned, $tables),
                    'classificationCoveragePercent' => $this->percent($classified, $tables),
                    'classificationNamespaces' => $this->classificationNames,
                    'classificationTags' => $this->topTags($group['tags']),
                    'mappedClassifications' => $group['mapped'],
                    'paginationComplete' => true,
                    'capturedAt' => $capturedAt->toIso8601ZuluString(),
                ],
                controlIdentifier: $this->controlIdentifier($source),
            );
        }
    }

    protected function connect(EvidenceSource $source): void
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];
        $baseUrl = $configuration['base_url'] ?? null;
        $token = $configuration['token'] ?? null;

        if (! is_string($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('The OpenMetadata source needs a valid API base URL.');
        }

        if (! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The OpenMetadata base URL must use http or https.');
        }

        if (! is_string($token) || trim($token) === '') {
            throw new InvalidArgumentException('The OpenMetadata source needs a read-only bot token.');
        }

        $this->pageSize = max(1, min(500, (int) config('openmetadata.page_size', 100)));
        $this->maxPages = max(1, (int) config('openmetadata.max_pages', 50));

        $names = config('openmetadata.classification_names', ['PII']);
        $this->classificationNames = is_array($names)
            ? array_values(array_filter(array_map(
                fn (mixed $name): string => strtolower(trim((string) $name)),
                $names
            )))
            : ['pii'];

        $map = config('openmetadata.tag_map', []);
        $this->tagMap = [];

        if (is_array($map)) {
            foreach ($map as $tag => $code) {
                if (is_string($tag) && is_string($code) && $tag !== '' && $code !== '') {
                    $this->tagMap[$tag] = $code;
                }
            }
        }

        $this->client = Http::grcReadOnly($this->apiRoot($baseUrl), trim($token), [], $this->caBundle($configuration))
            ->acceptJson()
            ->timeout(max(1, min(120, (int) ($configuration['timeout'] ?? 15))))
            ->connectTimeout(5);
    }

    /**
     * Follow the cursor to the end. A truncated catalog is a failed collection,
     * never a smaller estate.
     *
     * @param  array<string, string|int>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    protected function paginate(string $path, array $query = []): \Generator
    {
        $after = null;
        $pages = 0;
        $seen = 0;
        $total = null;

        while (true) {
            if ($pages >= $this->maxPages) {
                throw new RuntimeException(
                    'OpenMetadata returned more rows than the connector may page through. '
                    .'Raise OPENMETADATA_MAX_PAGES before enabling this source.'
                );
            }

            $requestQuery = $query + ['limit' => $this->pageSize];

            if ($after !== null) {
                $requestQuery['after'] = $after;
            }

            $payload = $this->get($path, $requestQuery);
            $items = $payload['data'] ?? null;

            if (! is_array($items)) {
                throw new InvalidArgumentException("OpenMetadata returned no data list for {$path}.");
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $seen++;
                    yield $item;
                }
            }

            $pages++;
            $paging = is_array($payload['paging'] ?? null) ? $payload['paging'] : [];
            $total = is_numeric($paging['total'] ?? null) ? (int) $paging['total'] : null;
            $after = is_string($paging['after'] ?? null) && $paging['after'] !== ''
                ? $paging['after']
                : null;

            if ($after === null) {
                break;
            }
        }

        if ($total !== null && $seen < $total) {
            throw new RuntimeException(sprintf(
                'OpenMetadata reports %d rows for %s but the connector read %d.',
                $total,
                $path,
                $seen,
            ));
        }
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        $response = $this->client()->get($path, $query);
        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("OpenMetadata returned a non-object payload for {$path}.");
        }

        return $payload;
    }

    protected function client(): PendingRequest
    {
        if ($this->client === null) {
            throw new RuntimeException('The OpenMetadata client is not configured.');
        }

        return $this->client;
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function serviceKey(array $table): string
    {
        $qualifiedName = $this->string($table['fullyQualifiedName'] ?? null);

        if ($qualifiedName !== null) {
            $parts = explode('.', $qualifiedName);

            if ($parts[0] !== '') {
                return $parts[0];
            }
        }

        $service = is_array($table['service'] ?? null) ? $table['service'] : [];
        $name = $this->string($service['name'] ?? null)
            ?? $this->string($service['fullyQualifiedName'] ?? null);

        return $name === null ? 'unknown' : explode('.', $name)[0];
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function serviceType(array $table): ?string
    {
        $service = is_array($table['service'] ?? null) ? $table['service'] : [];

        return $this->string($service['type'] ?? null)
            ?? $this->string($service['serviceType'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function hasOwner(array $table): bool
    {
        $owners = $table['owners'] ?? null;

        return is_array($owners) && $owners !== [];
    }

    /**
     * @param  array<string, mixed>  $table
     */
    protected function columnCount(array $table): int
    {
        $columns = $table['columns'] ?? null;

        return is_array($columns) ? count($columns) : 0;
    }

    /**
     * Classification tag FQNs on the table and its columns.
     *
     * @param  array<string, mixed>  $table
     * @return array<int, string>
     */
    protected function classificationTags(array $table): array
    {
        $tags = [];

        foreach ($this->tagFqns($table['tags'] ?? null) as $tag) {
            if ($this->isClassificationTag($tag)) {
                $tags[$tag] = true;
            }
        }

        $columns = $table['columns'] ?? null;

        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }

                foreach ($this->tagFqns($column['tags'] ?? null) as $tag) {
                    if ($this->isClassificationTag($tag)) {
                        $tags[$tag] = true;
                    }
                }
            }
        }

        return array_keys($tags);
    }

    /**
     * @return array<int, string>
     */
    protected function tagFqns(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $values = [];

        foreach ($tags as $tag) {
            $fqn = null;

            if (is_string($tag)) {
                $fqn = $tag;
            } elseif (is_array($tag)) {
                $fqn = $this->string($tag['tagFQN'] ?? null)
                    ?? $this->string($tag['fullyQualifiedName'] ?? null);
            }

            if ($fqn !== null) {
                $values[$fqn] = true;
            }
        }

        return array_keys($values);
    }

    protected function isClassificationTag(string $tag): bool
    {
        $root = strtolower(explode('.', $tag, 2)[0]);

        return in_array($root, $this->classificationNames, true);
    }

    /**
     * @param  array<string, int>  $tags
     * @return array<string, int>
     */
    protected function topTags(array $tags): array
    {
        arsort($tags);

        return array_slice($tags, 0, 20, true);
    }

    protected function percent(int $value, int $total): float
    {
        return $total > 0 ? round($value / $total * 100, 1) : 0.0;
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
                'The OpenMetadata CA bundle must be a readable PEM file on the OpenGRC host.'
            );
        }

        return $path;
    }

    /**
     * Accept both https://catalog.example/api and https://catalog.example/api/v1.
     */
    protected function apiRoot(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        return preg_replace('#/v1$#', '', $baseUrl) ?? $baseUrl;
    }

    protected function controlIdentifier(EvidenceSource $source): ?string
    {
        $configuration = is_array($source->configuration) ? $source->configuration : [];
        $identifier = $configuration['control_identifier'] ?? null;

        return is_string($identifier) && trim($identifier) !== '' ? trim($identifier) : null;
    }

    protected function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
