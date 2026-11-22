<?php

namespace App\Services\Dlp;

use App\Enums\DlpSuppressionState;
use App\Models\DlpPolicy;
use App\Models\DlpSuppression;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use LogicException;

/**
 * The suppression lifecycle (ADR 0053).
 *
 * Requested -> Active -> Expired | Revoked | Rejected. A suppression is an
 * acceptance with a clock, approved by someone other than the requester. It
 * never deletes a finding and it never becomes permanent: the end date is
 * required and capped.
 */
class DlpSuppressionService
{
    public const MAX_DAYS = 365;

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(DlpPolicy $policy, array $data, User $requester): DlpSuppression
    {
        $justification = trim((string) ($data['justification'] ?? ''));

        if ($justification === '') {
            throw new InvalidArgumentException('A DLP suppression requires a justification.');
        }

        $expiresAt = $data['expires_at'] ?? null;

        if (! $expiresAt instanceof CarbonInterface) {
            throw new InvalidArgumentException('A DLP suppression requires an expiry date.');
        }

        if ($expiresAt->isPast()) {
            throw new InvalidArgumentException('A DLP suppression must expire in the future.');
        }

        $cap = now()->addDays(self::MAX_DAYS);

        if ($expiresAt->greaterThan($cap)) {
            throw new InvalidArgumentException('A DLP suppression cannot last longer than '.self::MAX_DAYS.' days.');
        }

        return DlpSuppression::create([
            'dlp_policy_id' => $policy->getKey(),
            'identifier_type' => $this->nullable($data['identifier_type'] ?? null),
            'match_type' => $this->nullable($data['match_type'] ?? null),
            'justification' => $justification,
            'requested_by' => $requester->getKey(),
            'state' => DlpSuppressionState::Requested,
            'expires_at' => $expiresAt,
        ]);
    }

    public function approve(DlpSuppression $suppression, User $approver): DlpSuppression
    {
        if ($suppression->state !== DlpSuppressionState::Requested) {
            throw new LogicException('Only a requested suppression can be approved.');
        }

        if ((int) $suppression->requested_by === (int) $approver->getKey()) {
            throw new InvalidArgumentException('Separation of duties: the approver cannot be the requester.');
        }

        if ($suppression->expires_at->isPast()) {
            throw new InvalidArgumentException('The suppression window has passed. Create a new request.');
        }

        $suppression->forceFill([
            'approved_by' => $approver->getKey(),
            'approved_at' => now(),
            'starts_at' => now(),
            'state' => DlpSuppressionState::Active,
        ])->save();

        return $suppression;
    }

    public function reject(DlpSuppression $suppression, User $approver, ?string $reason = null): DlpSuppression
    {
        if ($suppression->state !== DlpSuppressionState::Requested) {
            throw new LogicException('Only a requested suppression can be rejected.');
        }

        if ((int) $suppression->requested_by === (int) $approver->getKey()) {
            throw new InvalidArgumentException('Separation of duties: the approver cannot be the requester.');
        }

        $suppression->forceFill([
            'state' => DlpSuppressionState::Rejected,
            'approved_by' => $approver->getKey(),
            'rejected_at' => now(),
            'review_notes' => $reason,
        ])->save();

        return $suppression;
    }

    public function revoke(DlpSuppression $suppression, User $actor, string $reason): DlpSuppression
    {
        if (! $suppression->state->isOpen()) {
            throw new LogicException('Only an open suppression can be revoked.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A revocation needs a reason.');
        }

        $suppression->forceFill([
            'state' => DlpSuppressionState::Revoked,
            'revoked_by' => $actor->getKey(),
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ])->save();

        return $suppression;
    }

    /**
     * Expiry is the fail-safe: it happens whether or not anyone remembers the
     * suppression. Expired is terminal; continuing the acceptance is a new
     * request with a new justification and approver.
     */
    public function expire(): int
    {
        $expired = 0;

        DlpSuppression::query()->expirable()->chunkById(200, function ($suppressions) use (&$expired): void {
            foreach ($suppressions as $suppression) {
                $suppression->forceFill([
                    'state' => DlpSuppressionState::Expired,
                ])->save();

                $expired++;
            }
        });

        return $expired;
    }

    protected function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
