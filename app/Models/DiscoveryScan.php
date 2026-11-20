<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A read-only mirror of one OpenDiscoveryEngine scan.
 *
 * The engine owns the scan lifecycle; this row only reflects the last synced
 * snapshot. `remote_id` is the engine's identifier and is unique.
 */
class DiscoveryScan extends Model
{
    protected $fillable = [
        'tenant',
        'remote_id',
        'asset_id',
        'asset_name',
        'profile_id',
        'profile_name',
        'status',
        'files_seen',
        'files_scanned',
        'findings_count',
        'error',
        'started_at',
        'finished_at',
        'remote_created_at',
        'synced_at',
    ];

    protected $casts = [
        'files_seen' => 'integer',
        'files_scanned' => 'integer',
        'findings_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'remote_created_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * @return HasMany<DiscoveryFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(DiscoveryFinding::class, 'discovery_scan_id');
    }
}
