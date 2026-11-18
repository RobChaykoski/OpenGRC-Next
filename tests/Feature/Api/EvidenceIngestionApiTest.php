<?php

namespace Tests\Feature\Api;

use App\Models\EvidenceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_a_hash_verified_evidence_snapshot(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $evidence = [
            'schema' => 'opendiscovery.evidence/v1',
            'scan' => ['id' => 'scan-1', 'findingsCount' => 1],
            'findings' => [['detector' => 'ca_sin', 'preview' => '04****86']],
        ];

        $response = $this->postJson('/api/v1/evidence', [
            'id' => 'evidence-1',
            'sha256' => $this->digest($evidence),
            'evidence' => $evidence,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'accepted');
        $this->assertSame(1, EvidenceRecord::query()->count());
    }

    #[Test]
    public function it_rejects_a_mismatched_hash(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/evidence', [
            'id' => 'evidence-2',
            'sha256' => str_repeat('a', 64),
            'evidence' => ['schema' => 'opendiscovery.evidence/v1'],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, EvidenceRecord::query()->count());
    }

    #[Test]
    public function a_replay_is_not_duplicated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $evidence = ['schema' => 'opendiscovery.evidence/v1', 'scan' => ['id' => 'scan-3']];
        $body = [
            'id' => 'evidence-3',
            'sha256' => $this->digest($evidence),
            'evidence' => $evidence,
        ];

        $this->postJson('/api/v1/evidence', $body)->assertCreated();
        $this->postJson('/api/v1/evidence', $body)->assertCreated();

        $this->assertSame(1, EvidenceRecord::query()->count());
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
