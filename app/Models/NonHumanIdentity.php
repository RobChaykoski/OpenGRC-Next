<?php

namespace App\Models;

use App\Enums\NonHumanIdentityStatus;
use App\Enums\NonHumanIdentityType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A machine identity: service account, API token, bot, integration credential
 * or certificate.
 *
 * The register stores metadata and a secret-store reference. It never stores the
 * secret, a password, a token value or a private key.
 */
class NonHumanIdentity extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'system',
        'environment',
        'owner_id',
        'purpose',
        'scope',
        'secret_reference',
        'rotation_days',
        'last_rotated_at',
        'next_rotation_at',
        'last_used_at',
        'expires_at',
        'status',
        'reviewed_at',
        'next_review_at',
        'revoked_at',
        'revocation_reason',
        'notes',
    ];

    protected $casts = [
        'type' => NonHumanIdentityType::class,
        'status' => NonHumanIdentityStatus::class,
        'rotation_days' => 'integer',
        'last_rotated_at' => 'date',
        'next_rotation_at' => 'date',
        'last_used_at' => 'date',
        'expires_at' => 'date',
        'reviewed_at' => 'date',
        'next_review_at' => 'date',
        'revoked_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (NonHumanIdentity $identity): void {
            if ($identity->next_rotation_at === null
                && $identity->last_rotated_at instanceof CarbonInterface
                && is_int($identity->rotation_days)
                && $identity->rotation_days > 0) {
                $identity->next_rotation_at = CarbonImmutable::parse($identity->last_rotated_at)
                    ->addDays($identity->rotation_days);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isRotationDue(): bool
    {
        return $this->next_rotation_at !== null && $this->next_rotation_at->lt(today());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function daysUntilRotation(): ?int
    {
        if ($this->next_rotation_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->next_rotation_at, false);
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->expires_at, false);
    }

    /**
     * @param  Builder<NonHumanIdentity>  $query
     * @return Builder<NonHumanIdentity>
     */
    public function scopeReviewable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            NonHumanIdentityStatus::Active->value,
            NonHumanIdentityStatus::RotationDue->value,
            NonHumanIdentityStatus::PendingReview->value,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'type',
                'system',
                'environment',
                'owner_id',
                'rotation_days',
                'last_rotated_at',
                'next_rotation_at',
                'expires_at',
                'status',
                'reviewed_at',
                'next_review_at',
                'revoked_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
