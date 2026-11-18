<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A framework in the scoping catalogue, as authored in
 * config/framework_scoping.php.
 *
 * This is the applicability definition used by the scoping engine, not the
 * imported Standard/Control library. Imported frameworks live in `standards`.
 */
class CanonicalFramework extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'priority',
        'regions',
        'industries',
        'labels',
        'drivers',
        'rationale',
    ];

    protected $casts = [
        'priority' => 'integer',
        'regions' => 'array',
        'industries' => 'array',
        'labels' => 'array',
        'drivers' => 'array',
    ];

    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(CanonicalControl::class, 'canonical_control_framework')
            ->withTimestamps();
    }
}
