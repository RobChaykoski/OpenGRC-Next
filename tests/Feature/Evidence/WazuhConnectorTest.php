<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Evidence\ConnectorRegistry;
use App\Services\Evidence\Connectors\WazuhConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WazuhConnectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    #[Test]
    public function it_aggregates_every_sca_policy_into_one_measurement_per_agent(): void
    {
        $this->fakeWazuh($this->policies());

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertCount(1, $records);

        $evidence = $records[0];

        $this->assertSame('wazuh:sca:001', $evidence->externalId);
        $this->assertSame(WazuhConnector::EVIDENCE_TYPE, $evidence->evidenceType);
        $this->assertSame(ControlStatus::Failed, $evidence->status);
        $this->assertSame('web-01', $evidence->assetIdentifier);
        $this->assertSame('CM-6', $evidence->controlIdentifier);
        $this->assertSame('2026-11-18T09:05:00Z', $evidence->collectedAt->toIso8601ZuluString());

        $this->assertSame(2, $evidence->payload['policyCount']);
        $this->assertCount(2, $evidence->payload['policies']);
        $this->assertSame('cis_ubuntu24', $evidence->payload['policies'][0]['id']);
        $this->assertSame(230, $evidence->payload['pass']);
        $this->assertSame(2, $evidence->payload['fail']);
        $this->assertSame(232, $evidence->payload['totalChecks']);
        $this->assertSame(98, $evidence->payload['score']);
        $this->assertTrue($evidence->payload['agentActive']);
        $this->assertSame(['default'], $evidence->payload['agent']['groups']);
    }

    #[Test]
    public function every_data_request_is_a_get_with_the_bearer_token(): void
    {
        $this->fakeWazuh($this->policies());

        iterator_to_array(app(WazuhConnector::class)->collect($this->source()));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/security/user/authenticate'));
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET'
            && ! str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/security/user/authenticate'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer jwt-token'));
        Http::assertSentCount(3);
    }

    #[Test]
    public function a_disconnected_agent_is_unknown_even_when_every_policy_passed(): void
    {
        $this->fakeWazuh([
            $this->passingPolicy(),
        ], ['status' => 'disconnected']);

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertFalse($records[0]->payload['agentActive']);
        $this->assertSame('disconnected', $records[0]->payload['agent']['status']);
    }

    #[Test]
    public function an_incomplete_policy_is_unknown_not_effective(): void
    {
        $this->fakeWazuh([[
            'policy_id' => 'cis_ubuntu24',
            'name' => 'CIS Ubuntu Linux 24.04 Benchmark',
            'pass' => 98,
            'fail' => 0,
            'invalid' => 2,
            'total_checks' => 100,
            'score' => 98,
            'end_scan' => '2026-11-18T09:05:00Z',
        ]]);

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame(2, $records[0]->payload['invalid']);
    }

    #[Test]
    public function an_agent_without_sca_policies_yields_one_unknown_record(): void
    {
        $this->fakeWazuh([]);

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertCount(1, $records);
        $this->assertSame('wazuh:sca:001', $records[0]->externalId);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame('no_sca_policy', $records[0]->payload['reason']);
        $this->assertSame('2026-01-01T00:00:00Z', $records[0]->collectedAt->toIso8601ZuluString());
    }

    #[Test]
    public function one_failing_agent_does_not_discard_the_rest_of_the_fleet(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/security/user/authenticate')) {
                return Http::response(['data' => ['token' => 'jwt-token'], 'error' => 0]);
            }

            if ($path === '/agents') {
                return Http::response([
                    'data' => [
                        'affected_items' => [
                            $this->agent('001', 'web-01'),
                            $this->agent('002', 'db-01'),
                        ],
                        'total_affected_items' => 2,
                    ],
                    'error' => 0,
                ]);
            }

            if ($path === '/sca/001') {
                return Http::response([], 500);
            }

            if ($path === '/sca/002') {
                return Http::response([
                    'data' => ['affected_items' => [$this->passingPolicy()]],
                    'error' => 0,
                ]);
            }

            return Http::response([], 404);
        });

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertCount(2, $records);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame('sca_unavailable', $records[0]->payload['reason']);
        $this->assertSame(ControlStatus::Effective, $records[1]->status);
    }

    #[Test]
    public function it_mints_a_new_token_when_the_jwt_expires_mid_run(): void
    {
        $authCalls = 0;
        $scaCalls = 0;

        Http::fake(function (Request $request) use (&$authCalls, &$scaCalls) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/security/user/authenticate')) {
                $authCalls++;

                return Http::response(['data' => ['token' => 'jwt-token-'.$authCalls], 'error' => 0]);
            }

            if ($path === '/agents') {
                return Http::response([
                    'data' => [
                        'affected_items' => [$this->agent('001', 'web-01')],
                        'total_affected_items' => 1,
                    ],
                    'error' => 0,
                ]);
            }

            if ($path === '/sca/001') {
                $scaCalls++;

                if ($scaCalls === 1) {
                    return Http::response(['message' => 'token expired'], 401);
                }

                return Http::response([
                    'data' => ['affected_items' => [$this->passingPolicy()]],
                    'error' => 0,
                ]);
            }

            return Http::response([], 404);
        });

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertSame(2, $authCalls);
        $this->assertSame(ControlStatus::Effective, $records[0]->status);
    }

    #[Test]
    public function it_pages_through_every_agent(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_ends_with($path, '/security/user/authenticate')) {
                return Http::response(['data' => ['token' => 'jwt-token'], 'error' => 0]);
            }

            if ($path === '/agents') {
                $offset = (int) ($query['offset'] ?? 0);
                $items = $offset === 0
                    ? [$this->agent('001', 'web-01')]
                    : [$this->agent('002', 'db-01')];

                return Http::response([
                    'data' => [
                        'affected_items' => $items,
                        'total_affected_items' => 2,
                    ],
                    'error' => 0,
                ]);
            }

            if (str_starts_with($path, '/sca/')) {
                return Http::response([
                    'data' => ['affected_items' => [$this->passingPolicy()]],
                    'error' => 0,
                ]);
            }

            return Http::response([], 404);
        });

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertCount(2, $records);
        $this->assertSame('wazuh:sca:001', $records[0]->externalId);
        $this->assertSame('wazuh:sca:002', $records[1]->externalId);
    }

    #[Test]
    public function it_can_limit_a_test_run_to_one_agent_group(): void
    {
        $this->fakeWazuh([$this->passingPolicy()]);

        iterator_to_array(
            app(WazuhConnector::class)->collect($this->source(['agent_group' => 'prod']))
        );

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/agents')
            && str_contains($request->url(), 'group=prod'));
    }

    #[Test]
    public function it_caps_the_agent_count_for_a_limited_test_run(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/security/user/authenticate')) {
                return Http::response(['data' => ['token' => 'jwt-token'], 'error' => 0]);
            }

            if ($path === '/agents') {
                return Http::response([
                    'data' => [
                        'affected_items' => [
                            $this->agent('001', 'web-01'),
                            $this->agent('002', 'db-01'),
                        ],
                        'total_affected_items' => 2,
                    ],
                    'error' => 0,
                ]);
            }

            if (str_starts_with($path, '/sca/')) {
                return Http::response([
                    'data' => ['affected_items' => [$this->passingPolicy()]],
                    'error' => 0,
                ]);
            }

            return Http::response([], 404);
        });

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source(['max_agents' => 1]))
        );

        $this->assertCount(1, $records);
        $this->assertSame('wazuh:sca:001', $records[0]->externalId);
    }

    #[Test]
    public function an_empty_agent_list_yields_an_unknown_inventory_record(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/security/user/authenticate')) {
                return Http::response(['data' => ['token' => 'jwt-token'], 'error' => 0]);
            }

            return Http::response([
                'data' => ['affected_items' => [], 'total_affected_items' => 0],
                'error' => 0,
            ]);
        });

        $records = iterator_to_array(
            app(WazuhConnector::class)->collect($this->source())
        );

        $this->assertCount(1, $records);
        $this->assertSame('wazuh:inventory', $records[0]->externalId);
        $this->assertSame(ControlStatus::Unknown, $records[0]->status);
        $this->assertSame('no_agents', $records[0]->payload['reason']);
    }

    #[Test]
    public function a_source_without_a_base_url_is_rejected(): void
    {
        $source = $this->source(['base_url' => null]);

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(app(WazuhConnector::class)->collect($source));
    }

    #[Test]
    public function an_unreadable_ca_bundle_is_rejected(): void
    {
        $source = $this->source(['ca_bundle' => storage_path('missing-wazuh-ca.pem')]);

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(app(WazuhConnector::class)->collect($source));
    }

    #[Test]
    public function a_readable_ca_bundle_is_accepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wazuh-ca-');
        file_put_contents($path, "-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----\n");

        try {
            $this->fakeWazuh([$this->passingPolicy()]);

            $records = iterator_to_array(
                app(WazuhConnector::class)->collect($this->source(['ca_bundle' => $path]))
            );

            $this->assertSame(ControlStatus::Effective, $records[0]->status);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_connector_is_registered_for_the_wazuh_source_type(): void
    {
        $this->assertTrue(
            app(ConnectorRegistry::class)->has(EvidenceSourceType::Wazuh)
        );
    }

    protected function source(array $overrides = []): EvidenceSource
    {
        return EvidenceSource::create([
            'name' => 'Wazuh test',
            'type' => EvidenceSourceType::Wazuh,
            'enabled' => true,
            'configuration' => array_merge([
                'base_url' => 'https://wazuh.test:55000',
                'username' => 'opengrc',
                'password' => 'secret',
                'control_identifier' => 'CM-6',
            ], $overrides),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function policies(): array
    {
        return [
            [
                'policy_id' => 'cis_ubuntu24',
                'name' => 'CIS Ubuntu Linux 24.04 Benchmark',
                'description' => 'CIS benchmark for Ubuntu 24.04',
                'pass' => 180,
                'fail' => 2,
                'invalid' => 0,
                'total_checks' => 182,
                'score' => 98,
                'start_scan' => '2026-11-17T09:00:00Z',
                'end_scan' => '2026-11-17T09:05:00Z',
            ],
            [
                'policy_id' => 'pci_dss',
                'name' => 'PCI DSS',
                'pass' => 50,
                'fail' => 0,
                'invalid' => 0,
                'total_checks' => 50,
                'score' => 100,
                'start_scan' => '2026-11-18T09:00:00Z',
                'end_scan' => '2026-11-18T09:05:00Z',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function passingPolicy(): array
    {
        return [
            'policy_id' => 'cis_ubuntu24',
            'name' => 'CIS Ubuntu Linux 24.04 Benchmark',
            'pass' => 100,
            'fail' => 0,
            'invalid' => 0,
            'total_checks' => 100,
            'score' => 100,
            'start_scan' => '2026-11-17T09:00:00Z',
            'end_scan' => '2026-11-17T09:05:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function agent(string $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'status' => 'active',
            'version' => 'Wazuh v4.14.0',
            'ip' => '10.0.0.10',
            'os' => [
                'name' => 'Ubuntu',
                'version' => '24.04',
                'platform' => 'ubuntu',
                'arch' => 'x86_64',
            ],
            'group' => ['default'],
            'lastKeepAlive' => '2026-11-18T09:10:00Z',
            'dateAdd' => '2026-01-01T00:00:00Z',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $policies
     * @param  array<string, mixed>  $agentOverrides
     */
    protected function fakeWazuh(array $policies, array $agentOverrides = []): void
    {
        Http::fake(function (Request $request) use ($policies, $agentOverrides) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/security/user/authenticate')) {
                return Http::response(['data' => ['token' => 'jwt-token'], 'error' => 0]);
            }

            if ($path === '/agents') {
                return Http::response([
                    'data' => [
                        'affected_items' => [array_merge($this->agent('001', 'web-01'), $agentOverrides)],
                        'total_affected_items' => 1,
                    ],
                    'error' => 0,
                ]);
            }

            if ($path === '/sca/001') {
                return Http::response([
                    'data' => [
                        'affected_items' => $policies,
                        'total_affected_items' => count($policies),
                    ],
                    'error' => 0,
                ]);
            }

            return Http::response([], 404);
        });
    }
}
