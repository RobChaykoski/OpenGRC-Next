<?php

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use App\Enums\MitigationType;
use App\Enums\RiskAcceptanceStatus;
use App\Enums\RiskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Risk extends Model
{
    use HasFactory, HasTaxonomy, LogsActivity;

    protected $casts = [
        'id' => 'integer',
        'action' => MitigationType::class,
        'status' => RiskStatus::class,
        'acceptance_status' => RiskAcceptanceStatus::class,
        'accepted_at' => 'date',
        'acceptance_expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'owner_id',
        'inherent_likelihood',
        'inherent_impact',
        'inherent_risk',
        'residual_likelihood',
        'residual_impact',
        'residual_risk',
        'acceptance_status',
        'accepted_by',
        'accepted_at',
        'acceptance_expires_at',
        'acceptance_rationale',
        'is_active',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isAcceptanceActive(): bool
    {
        return $this->acceptance_status === RiskAcceptanceStatus::Accepted
            && ! $this->isAcceptanceExpired();
    }

    public function isAcceptanceExpired(): bool
    {
        return $this->acceptance_expires_at !== null
            && $this->acceptance_expires_at->lt(today());
    }

    public function daysUntilAcceptanceExpiry(): ?int
    {
        if ($this->acceptance_expires_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->acceptance_expires_at, false);
    }

    public function implementations(): BelongsToMany
    {
        return $this->BelongsToMany(Implementation::class);
    }

    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(Policy::class, 'policy_risk')
            ->withTimestamps();
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class)
            ->withTimestamps();
    }

    public function mitigations(): MorphMany
    {
        return $this->morphMany(Mitigation::class, 'mitigatable');
    }

    public function latestMitigation(): ?Mitigation
    {
        return $this->mitigations()->latest('date_implemented')->first();
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'risks_index';
    }

    /**
     * Get the array representation of the model for search.
     */
    public function toSearchableArray(): array
    {
        return $this->toArray();
    }

    public static function next()
    {
        return static::max('id') + 1;
    }

    /**
     * @param  Builder<Risk>  $query
     * @return Builder<Risk>
     */
    public function scopeAcceptanceExpired(Builder $query): Builder
    {
        return $query->where('acceptance_status', RiskAcceptanceStatus::Accepted)
            ->whereNotNull('acceptance_expires_at')
            ->whereDate('acceptance_expires_at', '<', today());
    }

    /**
     * @param  Builder<Risk>  $query
     * @return Builder<Risk>
     */
    public function scopeAcceptanceExpiring(Builder $query, int $days = 30): Builder
    {
        return $query->where('acceptance_status', RiskAcceptanceStatus::Accepted)
            ->whereNotNull('acceptance_expires_at')
            ->whereDate('acceptance_expires_at', '>=', today())
            ->whereDate('acceptance_expires_at', '<=', today()->addDays($days));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code',
                'name',
                'description',
                'inherent_likelihood',
                'inherent_impact',
                'inherent_risk',
                'residual_likelihood',
                'residual_impact',
                'residual_risk',
                'status',
                'acceptance_status',
                'accepted_by',
                'accepted_at',
                'acceptance_expires_at',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
