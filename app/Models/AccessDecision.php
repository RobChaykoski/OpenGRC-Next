<?php

namespace App\Models;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\AccessReason;
use App\Enums\DocumentClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Append-only evidence of a runtime access decision (ADR 0049).
 *
 * A decision is never updated or deleted. Each row carries the classification
 * snapshot, the asserted context, the matched policy version and a SHA-256
 * hash chain over the previous entry, so a reviewer can detect alteration.
 *
 * The chain detects tampering and ordering. It does not prove intent, and it
 * is only as good as the serialisation of concurrent writers.
 */
class AccessDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'correlation_id',
        'subject_type',
        'subject_id',
        'subject_label',
        'protected_resource_id',
        'resource_ref',
        'tier',
        'profile',
        'purpose_code',
        'context',
        'matched_policy_id',
        'policy_version',
        'rules_hash',
        'access_grant_id',
        'action',
        'reason',
        'ttl_seconds',
        'risk_weight',
        'pep',
        'idempotency_key',
        'request_hash',
        'latency_ms',
        'decided_at',
        'previous_hash',
        'entry_hash',
    ];

    protected $casts = [
        'tier' => DocumentClassification::class,
        'profile' => AccessorProfile::class,
        'action' => AccessAction::class,
        'reason' => AccessReason::class,
        'context' => 'array',
        'decided_at' => 'datetime',
        'policy_version' => 'integer',
        'ttl_seconds' => 'integer',
        'risk_weight' => 'integer',
        'latency_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $decision): void {
            $decision->correlation_id ??= (string) Str::uuid();
            $decision->decided_at ??= now();

            $previous = static::query()->orderByDesc('id')->value('entry_hash');
            $decision->previous_hash = $previous;
            $decision->entry_hash = $decision->calculateEntryHash($previous);
        });

        static::updating(function (): void {
            throw new LogicException('Access decisions are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Access decisions are append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<ProtectedResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProtectedResource::class, 'protected_resource_id');
    }

    /**
     * @return BelongsTo<AccessPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(AccessPolicy::class, 'matched_policy_id');
    }

    /**
     * The grant this decision created, if any. A break-glass answer mints one.
     *
     * @return BelongsTo<AccessGrant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(AccessGrant::class, 'access_grant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    public function calculateEntryHash(?string $previousHash): string
    {
        $payload = [
            'correlation_id' => (string) $this->correlation_id,
            'subject_type' => (string) $this->subject_type,
            'subject_label' => (string) $this->subject_label,
            'resource_ref' => (string) $this->resource_ref,
            'tier' => $this->tier instanceof DocumentClassification ? $this->tier->value : (string) $this->tier,
            'profile' => $this->profile instanceof AccessorProfile ? $this->profile->value : (string) $this->profile,
            'action' => $this->action instanceof AccessAction ? $this->action->value : (string) $this->action,
            'reason' => $this->reason instanceof AccessReason ? $this->reason->value : (string) $this->reason,
            'context' => $this->sortContext($this->context ?? []),
            'policy_version' => $this->policy_version,
            'rules_hash' => $this->rules_hash,
            'decided_at' => $this->decided_at?->toIso8601ZuluString(),
            'previous_hash' => $previousHash,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Verify the chain. Returns the id of the first entry that does not match,
     * or null when the chain is intact.
     */
    public static function firstBrokenLink(): ?int
    {
        $previous = null;

        foreach (static::query()->orderBy('id')->cursor() as $decision) {
            if ($decision->previous_hash !== $previous) {
                return (int) $decision->getKey();
            }

            if ($decision->calculateEntryHash($previous) !== $decision->entry_hash) {
                return (int) $decision->getKey();
            }

            $previous = $decision->entry_hash;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sortContext(array $context): array
    {
        ksort($context);

        return $context;
    }
}
