<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Evidence\ConnectorRegistry;
use App\Services\Evidence\Connectors\GophishConnector;
use App\Support\Evidence\NormalisedEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GophishConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    #[Test]
    public function it_records_aggregate_results_for_a_completed_campaign(): void
    {
        $this->fakeGophish();

        $records = $this->collect();

        $completed = $this->record($records, 'gophish:campaign:7');

        $this->assertSame(GophishConnector::EVIDENCE_TYPE, $completed->evidenceType);
        $this->assertSame(ControlStatus::Unknown, $completed->status);
        $this->assertSame('AT-2', $completed->controlIdentifier);
        $this->assertSame('2026-10-09T16:00:00Z', $completed->collectedAt->toIso8601ZuluString());
        $this->assertSame('Completed', $completed->payload['campaign']['status']);
        $this->assertSame('Payroll update', $completed->payload['campaign']['template']);
        $this->assertSame('Finance', $completed->payload['campaign']['groups'][0]);

        $this->assertSame(120, $completed->payload['stats']['total']);
        $this->assertSame(118, $completed->payload['stats']['sent']);
        $this->assertSame(60, $completed->payload['stats']['opened']);
        $this->assertSame(14, $completed->payload['stats']['clicked']);
        $this->assertSame(3, $completed->payload['stats']['submittedData']);
        $this->assertSame(21, $completed->payload['stats']['emailReported']);
        $this->assertSame(2, $completed->payload['stats']['error']);

        $this->assertSame(50.8, $completed->payload['rates']['openedPct']);
        $this->assertSame(11.9, $completed->payload['rates']['clickedPct']);
        $this->assertSame(2.5, $completed->payload['rates']['submittedPct']);
        $this->assertSame(17.8, $completed->payload['rates']['reportedPct']);

        $this->assertTrue($completed->payload['aggregateOnly']);
        $this->assertSame('2026-10-01T13:00:00Z', $completed->payload['period']['launchDate']);
    }

    #[Test]
    public function it_never_stores_target_identity_or_event_detail(): void
    {
        $this->fakeGophish();

        $payload = json_encode(
            $this->record($this->collect(), 'gophish:campaign:7')->payload,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString('dr.example@example.test', $payload);
        $this->assertStringNotContainsString('Example', $payload);
        $this->assertStringNotContainsString('203.0.113.9', $payload);
        $this->assertStringNotContainsString('Mozilla/5.0', $payload);
        $this->assertStringNotContainsString('<html>', $payload);
        $this->assertStringNotContainsString('capture_passwords', $payload);
        $this->assertStringNotContainsString('results', $payload);
        $this->assertStringNotContainsString('timeline', $payload);
    }

    #[Test]
    public function it_reads_summaries_only_with_a_raw_authorization_header(): void
    {
        $this->fakeGophish();

        $this->collect();

        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/results'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/campaigns/7/summary'));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'secret-key'));
    }

    #[Test]
    public function a_running_campaign_is_measured_at_collection_time(): void
    {
        $this->fakeGophish();
        $this->travelTo(now()->setDateTime(2026, 11, 21, 9, 0, 0));

        $running = $this->record($this->collect(), 'gophish:campaign:8');

        $this->assertSame('2026-11-21T09:00:00Z', $running->collectedAt->toIso8601ZuluString());
        $this->assertSame('In progress', $running->payload['campaign']['status']);
        $this->assertSame(1, $running->payload['stats']['clicked']);
    }

    #[Test]
    public function a_completed_campaign_is_measured_at_its_completion_date(): void
    {
        $this->fakeGophish();
        $this->travelTo(now()->setDateTime(2026, 11, 21, 9, 0, 0));

        $completed = $this->record($this->collect(), 'gophish:campaign:7');

        $this->assertSame('2026-10-09T16:00:00Z', $completed->collectedAt->toIso8601ZuluString());
    }

    #[Test]
    public function it_skips_campaigns_outside_the_lookback_window(): void
    {
        $this->fakeGophish();

        $ids = array_map(
            fn ($record): string => $record->externalId,
            $this->collect()
        );

        $this->assertContains('gophish:campaign:7', $ids);
        $this->assertContains('gophish:campaign:8', $ids);
        $this->assertNotContains('gophish:campaign:3', $ids);
    }

    #[Test]
    public function it_caps_the_number_of_campaigns_newest_first(): void
    {
        $this->fakeGophish();

        $records = $this->collect(['max_campaigns' => 1]);

        $this->assertCount(1, $records);
        $this->assertSame('gophish:campaign:8', $records[0]->externalId);
    }

    #[Test]
    public function a_failed_summary_is_unknown_and_does_not_stop_the_run(): void
    {
        $this->fakeGophish([
            'https://gophish.test/api/campaigns/7/summary' => Http::response(['message' => 'error'], 500),
        ]);

        $records = $this->collect();

        $failed = $this->record($records, 'gophish:campaign:7');

        $this->assertSame(ControlStatus::Unknown, $failed->status);
        $this->assertSame('summary_unavailable', $failed->payload['reason']);
        $this->assertArrayHasKey('error', $failed->payload);

        $this->assertSame(ControlStatus::Unknown, $this->record($records, 'gophish:campaign:8')->status);
    }

    #[Test]
    public function a_summary_without_stats_is_unknown_not_zero_clicks(): void
    {
        $this->fakeGophish([
            'https://gophish.test/api/campaigns/7/summary' => Http::response([
                'id' => 7,
                'name' => 'Q4 finance awareness',
            ]),
        ]);

        $record = $this->record($this->collect(), 'gophish:campaign:7');

        $this->assertSame(ControlStatus::Unknown, $record->status);
        $this->assertSame('summary_incomplete', $record->payload['reason']);
        $this->assertArrayNotHasKey('stats', $record->payload);
    }

    #[Test]
    public function no_campaigns_is_an_informational_unknown_record(): void
    {
        $this->fakeGophish([
            'https://gophish.test/api/campaigns/' => Http::response([]),
        ]);

        $records = $this->collect();

        $this->assertCount(1, $records);
        $this->assertSame('gophish:campaigns', $records[0]->externalId);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame('no_campaigns', $records[0]->payload['reason']);
    }

    #[Test]
    public function zero_sent_targets_produce_no_rates(): void
    {
        $this->fakeGophish([
            'https://gophish.test/api/campaigns/7/summary' => Http::response([
                'id' => 7,
                'name' => 'Q4 finance awareness',
                'status' => 'Completed',
                'stats' => [
                    'total' => 0,
                    'sent' => 0,
                    'opened' => 0,
                    'clicked' => 0,
                    'submitted_data' => 0,
                    'email_reported' => 0,
                    'error' => 0,
                ],
            ]),
        ]);

        $record = $this->record($this->collect(), 'gophish:campaign:7');

        $this->assertNull($record->payload['rates']['openedPct']);
        $this->assertNull($record->payload['rates']['clickedPct']);
    }

    #[Test]
    public function it_accepts_a_base_url_that_includes_the_api_suffix(): void
    {
        $this->fakeGophish();

        $this->collect(['base_url' => 'https://gophish.test/api']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gophish.test/api/campaigns/');
    }

    #[Test]
    public function a_source_without_a_base_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(app(GophishConnector::class)->collect($this->source(['base_url' => null])));
    }

    #[Test]
    public function a_source_without_an_api_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(app(GophishConnector::class)->collect($this->source(['token' => ''])));
    }

    #[Test]
    public function an_api_error_envelope_fails_the_run_instead_of_looking_empty(): void
    {
        $this->fakeGophish([
            'https://gophish.test/api/campaigns/' => Http::response([
                'message' => 'Invalid API Key',
                'success' => false,
                'data' => null,
            ]),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid API Key');

        $this->collect();
    }

    #[Test]
    public function the_connector_is_registered(): void
    {
        $this->assertTrue(
            app(ConnectorRegistry::class)->has(EvidenceSourceType::Gophish)
        );
    }

    /**
     * @return array<int, NormalisedEvidence>
     */
    protected function collect(array $overrides = []): array
    {
        return iterator_to_array(
            app(GophishConnector::class)->collect($this->source($overrides))
        );
    }

    /**
     * @param  array<int, NormalisedEvidence>  $records
     */
    protected function record(array $records, string $externalId): NormalisedEvidence
    {
        foreach ($records as $record) {
            if ($record->externalId === $externalId) {
                return $record;
            }
        }

        $this->fail("No evidence record was produced for {$externalId}.");
    }

    protected function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create([
            'name' => 'Gophish test',
            'type' => EvidenceSourceType::Gophish,
            'enabled' => true,
            'configuration' => array_merge([
                'base_url' => 'https://gophish.test',
                'token' => 'secret-key',
                'control_identifier' => 'AT-2',
                'lookback_days' => 180,
                'max_campaigns' => 25,
            ], $overrides),
        ]);
    }

    /**
     * The list response deliberately carries target rows, event timelines and
     * template HTML, because that is what Gophish returns. The connector must
     * keep none of it.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeGophish(array $overrides = []): void
    {
        Http::fake(array_merge([
            'https://gophish.test/api/campaigns/' => Http::response([
                $this->campaign([
                    'id' => 7,
                    'name' => 'Q4 finance awareness',
                    'created_date' => '2026-10-01T12:00:00Z',
                    'launch_date' => '2026-10-01T13:00:00Z',
                    'completed_date' => '2026-10-09T16:00:00Z',
                    'status' => 'Completed',
                ]),
                $this->campaign([
                    'id' => 8,
                    'name' => 'Q4 engineering awareness',
                    'created_date' => '2026-11-19T12:00:00Z',
                    'launch_date' => '2026-11-19T13:00:00Z',
                    'completed_date' => '0001-01-01T00:00:00Z',
                    'status' => 'In progress',
                ]),
                $this->campaign([
                    'id' => 3,
                    'name' => 'Old campaign',
                    'created_date' => '2025-01-02T12:00:00Z',
                    'launch_date' => '2025-01-02T13:00:00Z',
                    'completed_date' => '2025-01-05T13:00:00Z',
                    'status' => 'Completed',
                ]),
            ]),
            'https://gophish.test/api/campaigns/7/summary' => Http::response([
                'id' => 7,
                'name' => 'Q4 finance awareness',
                'status' => 'Completed',
                'stats' => [
                    'total' => 120,
                    'sent' => 118,
                    'opened' => 60,
                    'clicked' => 14,
                    'submitted_data' => 3,
                    'email_reported' => 21,
                    'error' => 2,
                ],
            ]),
            'https://gophish.test/api/campaigns/8/summary' => Http::response([
                'id' => 8,
                'name' => 'Q4 engineering awareness',
                'status' => 'In progress',
                'stats' => [
                    'total' => 40,
                    'sent' => 40,
                    'opened' => 20,
                    'clicked' => 1,
                    'submitted_data' => 0,
                    'email_reported' => 4,
                    'error' => 0,
                ],
            ]),
            'https://gophish.test/api/campaigns/3/summary' => Http::response([
                'id' => 3,
                'name' => 'Old campaign',
                'status' => 'Completed',
                'stats' => [
                    'total' => 10,
                    'sent' => 10,
                    'opened' => 10,
                    'clicked' => 10,
                    'submitted_data' => 10,
                    'email_reported' => 0,
                    'error' => 0,
                ],
            ]),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function campaign(array $overrides): array
    {
        return array_merge([
            'id' => 7,
            'name' => 'Campaign',
            'created_date' => '2026-10-01T12:00:00Z',
            'launch_date' => '2026-10-01T13:00:00Z',
            'send_by_date' => '0001-01-01T00:00:00Z',
            'completed_date' => '0001-01-01T00:00:00Z',
            'status' => 'In progress',
            'template' => [
                'id' => 1,
                'name' => 'Payroll update',
                'subject' => 'Your payslip',
                'html' => '<html><body>Click {{.URL}}</body></html>',
            ],
            'page' => [
                'id' => 1,
                'name' => 'Credential capture',
                'html' => '<html>Landing page</html>',
                'capture_credentials' => true,
                'capture_passwords' => true,
            ],
            'groups' => [['id' => 1, 'name' => 'Finance']],
            'results' => [[
                'id' => 'hoqKYFn',
                'email' => 'dr.example@example.test',
                'first_name' => 'Example',
                'last_name' => 'User',
                'status' => 'Clicked Link',
                'ip' => '203.0.113.9',
                'latitude' => 0,
                'longitude' => 0,
                'reported' => false,
            ]],
            'timeline' => [[
                'email' => 'dr.example@example.test',
                'time' => '2026-10-01T13:05:00Z',
                'message' => 'Clicked Link',
                'details' => '{"browser":{"user-agent":"Mozilla/5.0"}}',
            ]],
            'url' => 'https://gophish.test',
        ], $overrides);
    }
}
