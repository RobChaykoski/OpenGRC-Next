<?php

namespace Tests\Feature\Audit;

use App\Enums\DlpSuppressionState;
use App\Models\AccessGrant;
use App\Models\AccessReview;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\Asset;
use App\Models\AssistantProposal;
use App\Models\ControlAttestation;
use App\Models\ControlCrosswalk;
use App\Models\ControlValidationRule;
use App\Models\DlpFinding;
use App\Models\DlpPolicy;
use App\Models\DlpSuppression;
use App\Models\EvidenceSource;
use App\Models\EvidenceStream;
use App\Models\Exercise;
use App\Models\Finding;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use App\Models\PolicyException;
use App\Models\ProtectedResource;
use App\Models\RemediationTask;
use App\Models\VendorSubcontractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, class-string> */
    private const AUDITED = [
        AccessGrant::class,
        AccessReview::class,
        ApprovalRequest::class,
        ApprovalRequestStep::class,
        Asset::class,
        AssistantProposal::class,
        ControlAttestation::class,
        ControlCrosswalk::class,
        ControlValidationRule::class,
        DlpFinding::class,
        DlpPolicy::class,
        DlpSuppression::class,
        EvidenceSource::class,
        EvidenceStream::class,
        Exercise::class,
        Finding::class,
        Policy::class,
        PolicyAcknowledgement::class,
        PolicyException::class,
        ProtectedResource::class,
        RemediationTask::class,
        VendorSubcontractor::class,
    ];

    #[Test]
    public function governance_records_are_activity_logged(): void
    {
        foreach (self::AUDITED as $model) {
            $this->assertContains(
                LogsActivity::class,
                class_uses_recursive($model),
                "{$model} is not activity-logged, so the audit ledger cannot see it."
            );
        }
    }

    #[Test]
    public function a_suppression_logs_its_state_but_not_its_justification(): void
    {
        $suppression = DlpSuppression::factory()->create([
            'justification' => 'Sensitive justification text that must not be copied.',
        ]);

        $suppression->update([
            'state' => DlpSuppressionState::Revoked,
            'revoked_reason' => 'Sensitive revocation reason.',
        ]);

        $activity = Activity::query()
            ->where('subject_type', DlpSuppression::class)
            ->latest('id')
            ->firstOrFail();

        $logged = json_encode($activity->attribute_changes?->toArray() ?? [], JSON_THROW_ON_ERROR);
        $changed = $activity->attribute_changes['attributes'] ?? [];

        $this->assertArrayHasKey('state', $changed);
        $this->assertStringNotContainsString('Sensitive justification text', $logged);
        $this->assertStringNotContainsString('Sensitive revocation reason', $logged);
    }

    #[Test]
    public function an_evidence_source_change_never_logs_its_configuration(): void
    {
        $source = EvidenceSource::create([
            'name' => 'Audit coverage source',
            'type' => 'other',
            'enabled' => true,
            'configuration' => ['token' => 'super-secret-token-value'],
        ]);

        $source->update(['enabled' => false]);

        $activity = Activity::query()
            ->where('subject_type', EvidenceSource::class)
            ->latest('id')
            ->firstOrFail();

        $logged = json_encode($activity->attribute_changes?->toArray() ?? [], JSON_THROW_ON_ERROR);
        $changed = $activity->attribute_changes['attributes'] ?? [];

        $this->assertStringNotContainsString('super-secret-token-value', $logged);
        $this->assertArrayHasKey('enabled', $changed);
    }
}
