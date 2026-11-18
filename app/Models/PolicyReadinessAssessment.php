<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One imported policy-readiness workbook snapshot.
 *
 * Re-importing the same workbook creates a new snapshot so maturity can be
 * compared over time. Items within a snapshot are unique by policy code.
 */
class PolicyReadinessAssessment extends Model
{
    protected $fillable = [
        'name',
        'source_file',
        'source_sha256',
        'revision',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /**
     * @return HasMany<PolicyReadinessItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PolicyReadinessItem::class, 'assessment_id');
    }
}
