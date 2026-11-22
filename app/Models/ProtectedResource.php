<?php

namespace App\Models;

use App\Enums\AccessResourceType;
use App\Enums\ConsentDirective;
use App\Enums\DocumentClassification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A governance record for a dataset, table, column set, record scope or API.
 *
 * The row records where sensitive data lives and how it is classified. It does
 * not store sample values, row content or a copy of the data.
 */
class ProtectedResource extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'resource_type',
        'external_urn',
        'asset_id',
        'tier',
        'classification_code',
        'consent_directive',
        'jurisdiction',
        'is_crown_jewel',
        'owner_id',
        'classification_source',
        'classification_version',
        'classified_at',
        'notes',
    ];

    protected $casts = [
        'resource_type' => AccessResourceType::class,
        'tier' => DocumentClassification::class,
        'consent_directive' => ConsentDirective::class,
        'is_crown_jewel' => 'boolean',
        'classified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<AccessGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(AccessGrant::class);
    }

    /**
     * @return HasMany<AccessDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(AccessDecision::class);
    }

    public function scopeStale(Builder $query): Builder
    {
        $cutoff = now()->subDays((int) config('access_governance.classification.stale_after_days', 30));

        return $query->where(function (Builder $query) use ($cutoff): void {
            $query->whereNull('classified_at')->orWhere('classified_at', '<', $cutoff);
        });
    }

    public function isStale(): bool
    {
        if ($this->classified_at === null) {
            return true;
        }

        return $this->classified_at->lt(
            now()->subDays((int) config('access_governance.classification.stale_after_days', 30))
        );
    }

    public function classificationAgeDays(): ?int
    {
        return $this->classified_at?->diffInDays(now());
    }

    /**
     * A consent directive or a crown-jewel resource raises the justification
     * and review bar. It does not block the access on its own.
     */
    public function requiresHeightenedReview(): bool
    {
        return $this->is_crown_jewel || $this->consent_directive->restrictsDisclosure();
    }

    public function reference(): string
    {
        return 'PR-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'tier', 'is_crown_jewel', 'classified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
