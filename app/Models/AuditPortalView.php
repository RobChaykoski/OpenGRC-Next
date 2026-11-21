<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One successful read of an auditor portal link.
 *
 * Only the network address and a user-agent string are kept, never the content
 * that was viewed. The grant already scopes what was visible, so the row answers
 * "who opened the link, when, and from where" without copying audit data.
 *
 * @property int $id
 * @property int $audit_portal_grant_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $viewed_at
 * @property-read AuditPortalGrant $grant
 */
class AuditPortalView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_portal_grant_id',
        'ip',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AuditPortalGrant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(AuditPortalGrant::class, 'audit_portal_grant_id');
    }
}
