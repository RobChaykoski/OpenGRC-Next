<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Evidence\ConnectorRegistry;
use App\Services\Evidence\Connectors\OpenMetadataConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OpenMetadataConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'openmetadata.classification_names' => ['PII'],
            'openmetadata.tag_map' => ['PII.Sensitive' => 'personal_information'],
            'openmetadata.page_size' => 2,
            'openmetadata.max_pages' => 10,
        ]);
    }

    #[Test]
    public function it_aggregates_catalog_governance_per_service(): void
    {
        Http::fakeSequence('https://openmetadata.test/api/v1/tables*')
            ->push([
                'data' => [
                    $this->table('analytics.customers', [['name' => 'Data Team']], ['PII.Sensitive', 'Tier.Tier1']),
                    $this->table('analytics.orders', [], ['Tier.Tier1']),
                ],
                'paging' => ['total' => 3, 'after' => 'cursor-2'],
            ])
            ->push([
                'data' => [
                    $this->table('analytics.events', [['name' => 'Data Team']], []),
                ],
                'paging' => ['total' => 3],
            ]);

        $records = iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));

        $this->assertCount(1, $records);

        $evidence = $records[0];

        $this->assertSame('openmetadata:catalog:analytics', $evidence->externalId);
        $this->assertSame(OpenMetadataConnector::EVIDENCE_TYPE, $evidence->evidenceType);
        $this->assertSame(ControlStatus::Failed, $evidence->status);
        $this->assertSame(3, $evidence->payload['tableCount']);
        $this->assertSame(6, $evidence->payload['columnCount']);
        $this->assertSame(2, $evidence->payload['ownedTableCount']);
        $this->assertSame(1, $evidence->payload['classifiedTableCount']);
        $this->assertSame(1, $evidence->payload['unownedTableCount']);
        $this->assertSame(2, $evidence->payload['unclassifiedTableCount']);
        $this->assertSame(66.7, $evidence->payload['ownerCoveragePercent']);
        $this->assertSame(33.3, $evidence->payload['classificationCoveragePercent']);
        $this->assertSame(['PII.Sensitive' => 1], $evidence->payload['classificationTags']);
        $this->assertSame(['personal_information' => 1], $evidence->payload['mappedClassifications']);
        $this->assertSame('analytics', $evidence->payload['service']['name']);
        $this->assertTrue($evidence->payload['paginationComplete']);
    }

    #[Test]
    public function a_fully_governed_catalog_is_effective(): void
    {
        Http::fake([
            'https://openmetadata.test/api/v1/tables*' => Http::response([
                'data' => [
                    $this->table('warehouse.customers', [['name' => 'Data Team']], ['PII.Sensitive']),
                    $this->table('warehouse.orders', [['name' => 'Data Team']], ['PII.NonSensitive']),
                ],
                'paging' => ['total' => 2],
            ]),
        ]);

        $records = iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));

        $this->assertCount(1, $records);
        $this->assertSame(ControlStatus::Effective, $records[0]->status);
        $this->assertSame(100.0, $records[0]->payload['ownerCoveragePercent']);
        $this->assertSame(100.0, $records[0]->payload['classificationCoveragePercent']);
    }

    #[Test]
    public function every_request_is_a_read_with_the_bot_token(): void
    {
        Http::fake([
            'https://openmetadata.test/api/v1/tables*' => Http::response([
                'data' => [],
                'paging' => ['total' => 0],
            ]),
        ]);

        iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer bot-token')
            && $request->hasHeader('Accept', 'application/json'));

        Http::assertNotSent(fn (Request $request): bool => ! in_array($request->method(), ['GET', 'HEAD'], true));
    }

    #[Test]
    public function the_api_root_accepts_an_optional_v1_suffix(): void
    {
        Http::fake([
            'https://openmetadata.test/api/v1/tables*' => Http::response([
                'data' => [],
                'paging' => ['total' => 0],
            ]),
        ]);

        iterator_to_array(app(OpenMetadataConnector::class)->collect(
            $this->source(['configuration' => [
                'base_url' => 'https://openmetadata.test/api/v1',
                'token' => 'bot-token',
            ]])
        ));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openmetadata.test/api/v1/tables?fields=columns%2Ctags%2Cowners%2Cservice%2Cdomains&limit=2');
    }

    #[Test]
    public function an_empty_catalog_is_unknown_not_clean(): void
    {
        Http::fake([
            'https://openmetadata.test/api/v1/tables*' => Http::response([
                'data' => [],
                'paging' => ['total' => 0],
            ]),
        ]);

        $records = iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));

        $this->assertCount(1, $records);
        $this->assertSame('openmetadata:catalog', $records[0]->externalId);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame('no_tables', $records[0]->payload['reason']);
    }

    #[Test]
    public function a_truncated_catalog_fails_loudly(): void
    {
        config(['openmetadata.max_pages' => 1]);

        Http::fakeSequence('https://openmetadata.test/api/v1/tables*')
            ->push([
                'data' => [$this->table('analytics.customers')],
                'paging' => ['total' => 2, 'after' => 'cursor-2'],
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENMETADATA_MAX_PAGES');

        iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));
    }

    #[Test]
    public function a_count_mismatch_fails_loudly(): void
    {
        Http::fake([
            'https://openmetadata.test/api/v1/tables*' => Http::response([
                'data' => [$this->table('analytics.customers')],
                'paging' => ['total' => 2],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reports 2 rows');

        iterator_to_array(app(OpenMetadataConnector::class)->collect($this->source()));
    }

    #[Test]
    public function a_source_without_a_token_is_rejected(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bot token');

        iterator_to_array(app(OpenMetadataConnector::class)->collect(
            $this->source(['configuration' => [
                'base_url' => 'https://openmetadata.test/api',
                'token' => '',
            ]])
        ));
    }

    #[Test]
    public function the_connector_is_registered_for_the_openmetadata_source_type(): void
    {
        $connector = app(ConnectorRegistry::class)->for($this->source());

        $this->assertInstanceOf(OpenMetadataConnector::class, $connector);
    }

    /**
     * @param  array<int, array<string, mixed>>  $owners
     * @param  array<int, string>  $tags
     * @return array<string, mixed>
     */
    private function table(string $fqn, array $owners = [], array $tags = []): array
    {
        return [
            'name' => substr($fqn, (int) strrpos($fqn, '.') + 1),
            'fullyQualifiedName' => $fqn,
            'service' => [
                'name' => explode('.', $fqn)[0],
                'type' => 'databaseService',
            ],
            'owners' => $owners,
            'tags' => [],
            'columns' => [
                ['name' => 'id', 'tags' => []],
                ['name' => 'value', 'tags' => array_map(
                    fn (string $tag): array => ['tagFQN' => $tag],
                    $tags
                )],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create(array_merge([
            'name' => 'OpenMetadata',
            'type' => EvidenceSourceType::OpenMetadata,
            'enabled' => true,
            'configuration' => [
                'base_url' => 'https://openmetadata.test/api',
                'token' => 'bot-token',
            ],
        ], $overrides));
    }
}
