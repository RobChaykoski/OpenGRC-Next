<?php

namespace App\Models;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One policy or document in a readiness assessment.
 *
 * The three status dimensions move independently. A policy can be approved
 * (documentation) while its implementation is only planned and its
 * effectiveness has never been tested.
 */
class PolicyReadinessItem extends Model
{
    protected $fillable = [
        'assessment_id',
        'policy_id',
        'code',
        'category',
        'title',
        'priority',
        'framework_applicability',
        'nist_csf',
        'itsg33',
        'cmmc',
        'objective',
        'expected_evidence',
        'applicability',
        'documentation_status',
        'implementation_status',
        'effectiveness_status',
        'owner',
        'target_date',
        'last_reviewed',
        'evidence_location',
        'notes',
    ];

    protected $casts = [
        'documentation_status' => PolicyDocumentationStatus::class,
        'implementation_status' => PolicyImplementationStatus::class,
        'effectiveness_status' => PolicyEffectivenessStatus::class,
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PolicyReadinessAssessment::class, 'assessment_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    /**
     * @param  Builder<PolicyReadinessItem>  $query
     * @return Builder<PolicyReadinessItem>
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('documentation_status', '!=', PolicyDocumentationStatus::Approved->value)
                ->orWhere('implementation_status', '!=', PolicyImplementationStatus::Implemented->value)
                ->orWhere('effectiveness_status', '!=', PolicyEffectivenessStatus::OperatingEffective->value);
        });
    }

    public function isApplicable(): bool
    {
        $value = strtolower((string) $this->applicability);

        return ! str_contains($value, 'not applicable');
    }
}
