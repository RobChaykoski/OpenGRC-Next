<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A read-only mirror of one OpenDiscoveryEngine finding.
 *
 * `remote_id` is the engine's identifier and is unique. The local scan link is
 * nullable because findings can be mirrored before their scan is available.
 */
class DiscoveryFinding extends Model
{
    protected $fillable = [
        'tenant',
        'remote_id',
        'discovery_scan_id',
        'remote_scan_id',
        'asset_id',
        'asset_name',
        'detector_key',
        'category',
        'severity',
        'classification',
        'path',
        'match_preview',
        'match_sha256',
        'confidence',
        'status',
        'remote_created_at',
        'synced_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'remote_created_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DiscoveryScan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(DiscoveryScan::class, 'discovery_scan_id');
    }
}
