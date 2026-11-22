<?php

namespace Tests\Feature\Api;

use App\Models\Control;
use App\Models\EvidenceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_a_hash_verified_evidence_snapshot(): void
    {
        $token = $this->token();
        $evidence = $this->evidence();

        $this->withToken($token)
            ->postJson('/api/v1/evidence', [
                'id' => 'evidence-1',
                'sha256' => $this->digest($evidence),
                'scanId' => 'scan-1',
                'findingCount' => 1,
                'evidence' => $evidence,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, EvidenceRecord::query()->count());
        $this->assertDatabaseHas('evidence_records', [
            'client_id' => 'acme',
            'external_id' => 'evidence-1',
            'evidence_type' => 'data-discovery',
        ]);
        $this->assertDatabaseHas('evidence_sources', ['name' => 'Discovery: acme']);
    }

    #[Test]
    public function it_accepts_detection_coverage_evidence_and_links_a_control(): void
    {
        $control = Control::factory()->create(['code' => 'SI-4']);
        $evidence = $this->detectionEvidence();

        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'detection-1',
                'sha256' => $this->digest($evidence),
                'evidenceType' => 'detection-coverage',
                'capturedAt' => '2026-11-20T10:00:00Z',
                'controlIdentifier' => 'SI-4',
                'evidence' => $evidence,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('evidence_records', [
            'client_id' => 'acme',
            'external_id' => 'detection-1',
            'evidence_type' => 'detection-coverage',
            'control_id' => $control->getKey(),
            'asset_id' => null,
            'status' => 'unknown',
        ]);
        $this->assertDatabaseHas('evidence_sources', ['name' => 'Detection: acme']);
    }

    #[Test]
    public function it_accepts_an_adversary_emulation_summary_from_the_range(): void
    {
        $control = Control::factory()->create(['code' => 'CA-8']);
        $evidence = $this->emulationEvidence();

        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'emulation-1',
                'sha256' => $this->digest($evidence),
                'evidenceType' => 'adversary-emulation',
                'capturedAt' => '2026-11-20T10:00:00Z',
                'controlIdentifier' => 'CA-8',
                'evidence' => $evidence,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('evidence_records', [
            'client_id' => 'acme',
            'external_id' => 'emulation-1',
            'evidence_type' => 'adversary-emulation',
            'control_id' => $control->getKey(),
            'status' => 'unknown',
        ]);
        $this->assertDatabaseHas('evidence_sources', ['name' => 'Range: acme']);

        $record = EvidenceRecord::query()->where('external_id', 'emulation-1')->firstOrFail();

        $this->assertSame('atomic-red-team', $record->payload['library']);
        $this->assertSame(14, $record->payload['testsExecuted']);
        $this->assertSame(['T1059.001-1', 'T1053.005-2'], $record->payload['testIds']);
    }

    #[Test]
    public function it_accepts_a_wstg_security_testing_summary(): void
    {
        $control = Control::factory()->create(['code' => 'SA-11']);
        $evidence = $this->securityTestingEvidence();

        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'security-testing-1',
                'sha256' => $this->digest($evidence),
                'evidenceType' => 'security-testing',
                'capturedAt' => '2026-11-20T10:00:00Z',
                'controlIdentifier' => 'SA-11',
                'evidence' => $evidence,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('evidence_records', [
            'client_id' => 'acme',
            'external_id' => 'security-testing-1',
            'evidence_type' => 'security-testing',
            'control_id' => $control->getKey(),
            'status' => 'unknown',
        ]);
        $this->assertDatabaseHas('evidence_sources', ['name' => 'AppSec: acme']);

        $record = EvidenceRecord::query()->where('external_id', 'security-testing-1')->firstOrFail();

        $this->assertSame('owasp-wstg', $record->payload['methodology']);
        $this->assertSame('v42', $record->payload['methodologyVersion']);
        $this->assertSame(['WSTG-ATHN-01'], $record->payload['failedTestIds']);
    }

    #[Test]
    public function it_accepts_an_awareness_training_summary(): void
    {
        $evidence = [
            'schema' => 'opengrc.awareness-training/v1',
            'campaign' => [
                'id' => 7,
                'name' => 'Q4 finance awareness',
                'status' => 'Completed',
            ],
            'stats' => [
                'total' => 3,
                'sent' => 3,
                'opened' => 2,
                'clicked' => 0,
                'submittedData' => 0,
                'emailReported' => 1,
            ],
        ];

        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'awareness-1',
                'sha256' => $this->digest($evidence),
                'evidenceType' => 'awareness-training',
                'capturedAt' => '2026-10-09T16:00:00Z',
                'evidence' => $evidence,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('evidence_records', [
            'client_id' => 'acme',
            'external_id' => 'awareness-1',
            'evidence_type' => 'awareness-training',
            'status' => 'unknown',
        ]);
    }

    #[Test]
    public function it_rejects_an_unknown_evidence_type(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'detection-2',
                'sha256' => str_repeat('a', 64),
                'evidenceType' => 'malware-sample',
                'evidence' => ['schema' => 'unknown'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evidenceType');

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function it_rejects_a_mismatched_hash(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/evidence', [
                'id' => 'evidence-2',
                'sha256' => str_repeat('a', 64),
                'evidence' => ['schema' => 'opendiscovery.evidence/v1'],
            ])
            ->assertStatus(422);

        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_replay_returns_the_original_record(): void
    {
        $token = $this->token();
        $evidence = $this->evidence();
        $body = [
            'id' => 'evidence-3',
            'sha256' => $this->digest($evidence),
            'evidence' => $evidence,
        ];

        $first = $this->withToken($token)->postJson('/api/v1/evidence', $body)->assertCreated();
        $second = $this->withToken($token)->postJson('/api/v1/evidence', $body)->assertOk();

        $this->assertSame(
            $first->json('evidenceId'),
            $second->json('evidenceId')
        );
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_duplicate_external_id_does_not_create_a_second_row(): void
    {
        $token = $this->token();
        $first = $this->evidence();
        $second = ['schema' => 'opendiscovery.evidence/v1', 'scan' => ['id' => 'scan-other']];

        $this->withToken($token)->postJson('/api/v1/evidence', [
            'id' => 'evidence-4',
            'sha256' => $this->digest($first),
            'evidence' => $first,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/evidence', [
            'id' => 'evidence-4',
            'sha256' => $this->digest($second),
            'evidence' => $second,
        ])->assertOk();

        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_missing_token_returns_401_json(): void
    {
        $this->postJson('/api/v1/evidence', [])
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function a_token_without_the_scope_returns_403(): void
    {
        $this->withToken($this->token(abilities: []))
            ->postJson('/api/v1/evidence', [])
            ->assertStatus(403)
            ->assertJsonPath('message', 'The token is missing the evidence.write scope.');
    }

    #[Test]
    public function a_token_without_a_client_returns_403(): void
    {
        $this->withToken($this->token(clientId: null))
            ->postJson('/api/v1/evidence', [])
            ->assertStatus(403)
            ->assertJsonPath('message', 'The token is not bound to a client.');
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function token(array $abilities = ['evidence.write'], ?string $clientId = 'acme'): string
    {
        $new = User::factory()->create()->createToken('discovery', $abilities);

        if ($clientId !== null) {
            $new->accessToken->forceFill(['client_id' => $clientId])->save();
        }

        return $new->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function evidence(): array
    {
        return [
            'schema' => 'opendiscovery.evidence/v1',
            'scan' => ['id' => 'scan-1', 'findingsCount' => 1],
            'findings' => [['detector' => 'ca_sin', 'preview' => '04****86']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detectionEvidence(): array
    {
        return [
            'schema' => 'opengrc.detection-coverage/v1',
            'platform' => 'opendetectionengine',
            'sigmaRules' => 42,
            'yaraRules' => 18,
            'failingRules' => 1,
            'attackTechniquesCovered' => 37,
            'lastValidatedAgeDays' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emulationEvidence(): array
    {
        return [
            'schema' => 'opengrc.adversary-emulation/v1',
            'range' => 'ocr-lab-1',
            'operationId' => 'op-123',
            'adversary' => 'APT29',
            'planner' => 'atomic',
            'library' => 'atomic-red-team',
            'libraryVersion' => '2026.11',
            'techniquesAttempted' => 12,
            'techniquesSucceeded' => 5,
            'techniquesDetected' => 9,
            'techniquesPrevented' => 7,
            'undetectedTechniques' => 3,
            'unpreventedTechniques' => 5,
            'testsExecuted' => 14,
            'testsSucceeded' => 9,
            'testsFailed' => 5,
            'testIds' => ['T1059.001-1', 'T1053.005-2'],
            'detectionCoveragePercent' => 75.0,
            'preventionCoveragePercent' => 58.3,
            'assetIds' => ['asset-42'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function securityTestingEvidence(): array
    {
        return [
            'schema' => 'opengrc.security-testing/v1',
            'methodology' => 'owasp-wstg',
            'methodologyVersion' => 'v42',
            'applicationId' => 'app-42',
            'environment' => 'staging',
            'testsPlanned' => 90,
            'testsExecuted' => 88,
            'testsPassed' => 80,
            'testsFailed' => 8,
            'testsNotApplicable' => 2,
            'coveragePercent' => 97.8,
            'failedTestIds' => ['WSTG-ATHN-01'],
            'categories' => [
                'ATHN' => ['planned' => 10, 'executed' => 10, 'failed' => 1],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function digest(array $payload): string
    {
        $this->sort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sort(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sort($value);
            }
        }
    }
}
