<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A canonical outcome in the unified control catalogue.
 *
 * One row per outcome, not per framework: frameworks point at the same row,
 * which is what lets coverage de-duplicate overlapping requirements. The
 * catalogue is authored in config/framework_scoping.php and synced here by
 * `opengrc:catalog:sync`, so treat rows as derived rather than hand-edited.
 */
class CanonicalControl extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'risk_domain',
        'priority',
        'evidence_expectation',
        'automation_level',
        'frequency',
        'sources',
    ];

    protected $casts = [
        'priority' => 'integer',
        'sources' => 'array',
    ];

    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(CanonicalFramework::class, 'canonical_control_framework')
            ->withTimestamps();
    }

    /**
     * Imported controls curated against this outcome.
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_canonical_outcome')
            ->withPivot('source')
            ->withTimestamps();
    }

    /**
     * Policies and documents that support this outcome.
     */
    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(Policy::class, 'policy_canonical_outcome')
            ->withPivot('source')
            ->withTimestamps();
    }
}
