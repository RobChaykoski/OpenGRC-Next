<?php

namespace Tests\Feature\Evidence;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Enums\UserRole;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\User;
use App\Policies\EvidenceRecordPolicy;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceRecordPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_role_can_read_evidence(): void
    {
        $record = $this->record();

        foreach ([UserRole::Viewer, UserRole::Editor, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertTrue($user->can('viewAny', EvidenceRecord::class));
            $this->assertTrue($user->can('view', $record));
        }
    }

    #[Test]
    public function no_role_is_allowed_to_write_evidence_by_policy(): void
    {
        $record = $this->record();
        $policy = new EvidenceRecordPolicy;

        foreach ([UserRole::Viewer, UserRole::Editor, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->update($user, $record));
            $this->assertFalse($policy->delete($user, $record));
            $this->assertFalse($policy->deleteAny($user));
        }
    }

    protected function record(): EvidenceRecord
    {
        $source = EvidenceSource::create([
            'name' => 'PatchMon',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
        ]);

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Queued,
        ]);

        return app(EvidenceIngestor::class)->ingest($source, $job, new NormalisedEvidence(
            externalId: 'host-1',
            evidenceType: 'patch-compliance',
            status: ControlStatus::Failed,
            collectedAt: CarbonImmutable::parse('2026-11-17T09:00:00Z'),
            payload: ['securityUpdates' => 2],
        ));
    }
}
