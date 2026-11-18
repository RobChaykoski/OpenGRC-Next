<?php

namespace App\Models;

use App\Enums\ControlStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of applying one rule version to the evidence at a point in time.
 * Results are kept so drift and regressions remain visible after a fix.
 */
class ControlValidation extends Model
{
    protected $fillable = [
        'control_id',
        'source_id',
        'asset_id',
        'asset_identifier',
        'evidence_record_id',
        'rule_id',
        'rule_version',
        'status',
        'result',
        'evaluated_at',
    ];

    protected $casts = [
        'status' => ControlStatus::class,
        'result' => 'array',
        'rule_version' => 'integer',
        'evaluated_at' => 'datetime',
    ];

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class, 'control_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EvidenceSource::class, 'source_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function evidenceRecord(): BelongsTo
    {
        return $this->belongsTo(EvidenceRecord::class, 'evidence_record_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ControlValidationRule::class, 'rule_id');
    }
}
