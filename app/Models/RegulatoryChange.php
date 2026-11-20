<?php

namespace App\Models;

use App\Enums\RegulatoryChangeImpact;
use App\Enums\RegulatoryChangeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A regulatory or standard change that the programme is tracking.
 *
 * The source stays upstream; the register holds a summary, a link and the
 * organisation's impact assessment. It is not an automated feed.
 */
class RegulatoryChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'regulator',
        'reference_url',
        'published_at',
        'effective_at',
        'status',
        'impact',
        'summary',
        'impact_assessment',
        'owner_id',
        'assessed_at',
        'due_at',
        'notes',
    ];

    protected $casts = [
        'published_at' => 'date',
        'effective_at' => 'date',
        'status' => RegulatoryChangeStatus::class,
        'impact' => RegulatoryChangeImpact::class,
        'assessed_at' => 'date',
        'due_at' => 'date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Standard, $this>
     */
    public function standards(): BelongsToMany
    {
        return $this->belongsToMany(Standard::class)->withTimestamps();
    }

    public function reference(): string
    {
        return 'REG-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, RegulatoryChangeStatus::openCases(), true)
            && $this->due_at !== null
            && $this->due_at->lt(today());
    }

    /**
     * @param  Builder<RegulatoryChange>  $query
     * @return Builder<RegulatoryChange>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', RegulatoryChangeStatus::openCases());
    }

    /**
     * @param  Builder<RegulatoryChange>  $query
     * @return Builder<RegulatoryChange>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', RegulatoryChangeStatus::openCases())
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }
}
