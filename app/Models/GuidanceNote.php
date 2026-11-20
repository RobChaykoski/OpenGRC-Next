<?php

namespace App\Models;

use App\Enums\GuidanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A guidance note or advisory: a publication that informs controls, not a
 * framework. The reference and link stay upstream; the adoption decision and
 * control mapping live here.
 */
class GuidanceNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'publisher',
        'reference',
        'version',
        'published_at',
        'url',
        'licence',
        'status',
        'owner_id',
        'applicability',
        'summary',
        'reviewed_at',
        'next_review_at',
        'notes',
    ];

    protected $casts = [
        'status' => GuidanceStatus::class,
        'published_at' => 'date',
        'reviewed_at' => 'date',
        'next_review_at' => 'date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Control, $this>
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class)->withTimestamps();
    }

    public function isReviewDue(): bool
    {
        return $this->next_review_at !== null && $this->next_review_at->lt(today());
    }

    /**
     * @param  Builder<GuidanceNote>  $query
     * @return Builder<GuidanceNote>
     */
    public function scopeReviewDue(Builder $query): Builder
    {
        return self::applyReviewDue($query);
    }

    public static function applyReviewDue(Builder $query): Builder
    {
        return $query->whereNotNull('next_review_at')->whereDate('next_review_at', '<', today());
    }

    public function label(): string
    {
        return $this->reference === null
            ? $this->title
            : $this->reference.' — '.$this->title;
    }
}
