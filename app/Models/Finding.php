<?php

namespace App\Models;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A gap that needs an owner and a date.
 *
 * Findings were audit-item effectiveness values before this model existed. An
 * automated finding is raised by a failed validation and resolved by a passing
 * one; a manual finding is only closed by a person.
 */
class Finding extends Model
{
    protected $fillable = [
        'title',
        'description',
        'severity',
        'status',
        'source',
        'control_id',
        'risk_id',
        'asset_id',
        'evidence_source_id',
        'rule_id',
        'assessment_report_id',
        'owner_id',
        'dedupe_key',
        'occurrences',
        'identified_at',
        'last_seen_at',
        'due_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'severity' => EvidenceSeverity::class,
        'status' => FindingStatus::class,
        'source' => FindingSource::class,
        'occurrences' => 'integer',
        'identified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Control, $this>
     */
    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'control_id');
    }

    /**
     * The risk this finding contributes to, if one has been raised.
     *
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'risk_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function evidenceSource(): BelongsTo
    {
        return $this->belongsTo(EvidenceSource::class, 'evidence_source_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ControlValidationRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<AssessmentReport, $this>
     */
    public function assessmentReport(): BelongsTo
    {
        return $this->belongsTo(AssessmentReport::class, 'assessment_report_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [FindingStatus::Open, FindingStatus::InProgress]);
    }

    public function isOpen(): bool
    {
        $status = $this->status;

        return $status instanceof FindingStatus
            ? $status->isOpen()
            : in_array($status, [FindingStatus::Open->value, FindingStatus::InProgress->value], true);
    }

    public function resolve(?string $notes = null): void
    {
        $this->forceFill([
            'status' => FindingStatus::Resolved,
            'resolved_at' => now(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ])->save();
    }

    public function acceptRisk(?string $notes = null): void
    {
        $this->forceFill([
            'status' => FindingStatus::Accepted,
            'resolved_at' => now(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ])->save();
    }

    /**
     * Human-readable reference for reports: F-00042.
     */
    public function reference(): string
    {
        return 'F-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
