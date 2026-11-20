<?php

namespace App\Models;

use App\Enums\AttestationOutcome;
use App\Enums\AttestationStatus;
use App\Enums\AttestationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An assurance report or attestation about a system, service or vendor.
 *
 * The report body stays in the document store; this record carries the scope,
 * period, auditor, opinion, exceptions, bridge letter and review date. It is not
 * a copy of the standard and it does not make a control effective by itself.
 */
class AttestationReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'provider',
        'subject',
        'framework_code',
        'vendor_id',
        'owner_id',
        'period_start',
        'period_end',
        'issued_at',
        'expires_at',
        'bridge_letter_date',
        'next_review_at',
        'outcome',
        'status',
        'summary',
        'exceptions',
        'document_reference',
        'document_sha256',
    ];

    protected $casts = [
        'type' => AttestationType::class,
        'outcome' => AttestationOutcome::class,
        'status' => AttestationStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'issued_at' => 'date',
        'expires_at' => 'date',
        'bridge_letter_date' => 'date',
        'next_review_at' => 'date',
    ];

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function isCurrent(): bool
    {
        return $this->status === AttestationStatus::Current && ! $this->isExpired();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->expires_at, false);
    }

    public function isPositive(): bool
    {
        return $this->outcome instanceof AttestationOutcome && $this->outcome->isPositive();
    }
}
