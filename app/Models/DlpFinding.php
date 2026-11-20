<?php

namespace App\Models;

use App\Enums\DlpDisposition;
use Illuminate\Database\Eloquent\Builder;
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
        'identifier_types',
        'action',
        'disposition',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'content_sha256',
    ];

    protected $casts = [
        'match_types' => 'array',
        'identifier_types' => 'array',
        'disposition' => DlpDisposition::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<DlpFinding>  $query
     * @return Builder<DlpFinding>
     */
    public function scopeReviewed(Builder $query): Builder
    {
        return $query->whereNotNull('disposition');
    }

    /**
     * @param  Builder<DlpFinding>  $query
     * @return Builder<DlpFinding>
     */
    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('disposition');
    }

    /**
     * @param  Builder<DlpFinding>  $query
     * @return Builder<DlpFinding>
     */
    public function scopeFalsePositive(Builder $query): Builder
    {
        return $query->where('disposition', DlpDisposition::FalsePositive->value);
    }

    public function isFalsePositive(): bool
    {
        return $this->disposition === DlpDisposition::FalsePositive;
    }
}
