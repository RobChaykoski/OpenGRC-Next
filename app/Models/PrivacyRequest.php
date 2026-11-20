<?php

namespace App\Models;

use App\Enums\PrivacyRegime;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Services\Privacy\PrivacyDeadline;
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
 * A data-subject or consumer privacy request.
 *
 * The record is a register entry, not the request content. It keeps the regime,
 * type, clock, verification and decision; the request correspondence and the
 * response package stay in the privacy case store and are referenced, not copied.
 */
class PrivacyRequest extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'reference',
        'regime',
        'request_type',
        'status',
        'requester_name',
        'contact_reference',
        'owner_id',
        'received_at',
        'due_at',
        'extended_at',
        'extension_reason',
        'identity_verified',
        'verification_method',
        'verified_at',
        'denial_reason',
        'response_summary',
        'completed_at',
        'evidence_reference',
        'evidence_sha256',
        'notes',
    ];

    protected $casts = [
        'regime' => PrivacyRegime::class,
        'request_type' => PrivacyRequestType::class,
        'status' => PrivacyRequestStatus::class,
        'identity_verified' => 'boolean',
        'received_at' => 'date',
        'due_at' => 'date',
        'extended_at' => 'date',
        'verified_at' => 'date',
        'completed_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (PrivacyRequest $request): void {
            if (blank($request->reference)) {
                $request->reference = 'PR-'.str_pad(
                    (string) ((int) static::withTrashed()->max('id') + 1),
                    5,
                    '0',
                    STR_PAD_LEFT,
                );
            }
        });

        static::saving(function (PrivacyRequest $request): void {
            if ($request->due_at === null
                && $request->received_at instanceof CarbonInterface
                && $request->regime instanceof PrivacyRegime) {
                $request->due_at = PrivacyDeadline::dueAt($request->regime, $request->received_at);
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

    public function effectiveDueAt(): ?CarbonImmutable
    {
        $date = $this->extended_at ?? $this->due_at;

        return $date instanceof CarbonInterface
            ? CarbonImmutable::parse($date)->startOfDay()
            : null;
    }

    public function isOverdue(): bool
    {
        if (! $this->status instanceof PrivacyRequestStatus || $this->status->isClosed()) {
            return false;
        }

        $due = $this->effectiveDueAt();

        return $due !== null && $due->lt(today());
    }

    public function daysRemaining(): ?int
    {
        $due = $this->effectiveDueAt();

        return $due === null ? null : (int) today()->diffInDays($due, false);
    }

    /**
     * @param  Builder<PrivacyRequest>  $query
     * @return Builder<PrivacyRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PrivacyRequestStatus::Received->value,
            PrivacyRequestStatus::VerificationPending->value,
            PrivacyRequestStatus::InProgress->value,
            PrivacyRequestStatus::Extended->value,
            PrivacyRequestStatus::Overdue->value,
        ]);
    }

    /**
     * @param  Builder<PrivacyRequest>  $query
     * @return Builder<PrivacyRequest>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return self::applyOverdue($query);
    }

    public static function applyOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PrivacyRequestStatus::Received->value,
            PrivacyRequestStatus::VerificationPending->value,
            PrivacyRequestStatus::InProgress->value,
            PrivacyRequestStatus::Extended->value,
        ])->where(function (Builder $inner): void {
            $inner->where(function (Builder $extended): void {
                $extended->whereNotNull('extended_at')->whereDate('extended_at', '<', today());
            })->orWhere(function (Builder $base): void {
                $base->whereNull('extended_at')->whereNotNull('due_at')->whereDate('due_at', '<', today());
            });
        });
    }

    /**
     * @param  Builder<PrivacyRequest>  $query
     * @return Builder<PrivacyRequest>
     */
    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return self::applyDueSoon($query, $days);
    }

    public static function applyDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->whereIn('status', [
            PrivacyRequestStatus::Received->value,
            PrivacyRequestStatus::VerificationPending->value,
            PrivacyRequestStatus::InProgress->value,
            PrivacyRequestStatus::Extended->value,
        ])->where(function (Builder $inner) use ($days): void {
            $inner->where(function (Builder $extended) use ($days): void {
                $extended->whereNotNull('extended_at')
                    ->whereDate('extended_at', '>=', today())
                    ->whereDate('extended_at', '<=', today()->addDays($days));
            })->orWhere(function (Builder $base) use ($days): void {
                $base->whereNull('extended_at')
                    ->whereNotNull('due_at')
                    ->whereDate('due_at', '>=', today())
                    ->whereDate('due_at', '<=', today()->addDays($days));
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'reference',
                'regime',
                'request_type',
                'status',
                'received_at',
                'due_at',
                'extended_at',
                'owner_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
