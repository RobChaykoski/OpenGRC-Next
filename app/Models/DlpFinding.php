<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A DLP event: content was blocked or flagged. The content itself is not
 * stored; the hash is the link to the record or upload.
 */
class DlpFinding extends Model
{
    protected $fillable = [
        'source',
        'context_type',
        'context_id',
        'user_id',
        'severity',
        'match_types',
        'action',
        'content_sha256',
    ];

    protected $casts = [
        'match_types' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
