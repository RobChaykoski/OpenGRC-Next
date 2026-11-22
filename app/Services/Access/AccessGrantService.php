<?php

namespace App\Services\Access;

use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Models\AccessGrant;
use App\Models\AccessPolicy;
use App\Models\ProtectedResource;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * The just-in-time grant lifecycle (ADR 0049).
 *
 * Requested -> Approved/Active -> Expired | Revoked | Rejected. A break-glass
 * grant always creates a privacy review with a due date. The approver cannot be
 * the requester: the highest-value targets are the people who ask.
 */
class AccessGrantService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function request(
        ProtectedResource $resource,
        array $data,
        User $requester,
        ?AccessPolicy $policy = null,
    ): AccessGrant {
        $justification = trim((string) ($data['justification'] ?? ''));

        if ($justification === '') {
            throw new InvalidArgumentException('A justification is required for every access grant.');
        }

        $isBreakGlass = (bool) ($data['is_break_glass'] ?? false);

        if ($isBreakGlass && trim((string) ($data['clinical_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('A break-the-glass grant requires a reason code.');
        }

        $cap = $isBreakGlass
            ? (int) config('access_governance.grants.break_glass_max_minutes', 30)
            : (int) ($policy?->max_duration_minutes ?? config('access_governance.grants.max_duration_minutes', 480));

        $requestedExpiry = $data['expires_at'] ?? null;
        $minutes = $requestedExpiry instanceof CarbonInterface
            ? (int) now()->diffInMinutes($requestedExpiry, false)
            : 60;

        $minutes = max(1, min($minutes, $cap));

        $reviewRequired = $isBreakGlass
            || (bool) $policy?->requires_review
            || $resource->requiresHeightenedReview();

        return AccessGrant::create([
            'subject_type' => (string) ($data['subject_type'] ?? 'user'),
            'subject_id' => $data['subject_id'] ?? null,
            'subject_label' => (string) ($data['subject_label'] ?? ''),
            'protected_resource_id' => $resource->getKey(),
            'access_policy_id' => $policy?->getKey(),
            'requested_by' => $requester->getKey(),
            'purpose_code' => (string) ($data['purpose_code'] ?? 'care'),
            'justification' => $justification,
            'is_break_glass' => $isBreakGlass,
            'clinical_reason' => $data['clinical_reason'] ?? null,
            'state' => AccessGrantState::Requested,
            'correlation_id' => (string) Str::uuid(),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes($minutes),
            'review_required' => $reviewRequired,
            'review_due_at' => $reviewRequired
                ? today()->addDays((int) config('access_governance.grants.break_glass_review_days', 5))
                : null,
            'review_outcome' => $reviewRequired ? PrivacyReviewOutcome::Pending : PrivacyReviewOutcome::NotApplicable,
            'risk_id' => $data['risk_id'] ?? null,
        ]);
    }

    public function approve(AccessGrant $grant, User $approver): AccessGrant
    {
        if ($grant->state !== AccessGrantState::Requested) {
            throw new LogicException('Only a requested grant can be approved.');
        }

        if ((int) $grant->requested_by === (int) $approver->getKey()) {
            throw new InvalidArgumentException('Separation of duties: the approver cannot be the requester.');
        }

        if ($grant->expires_at !== null && $grant->expires_at->isPast()) {
            throw new InvalidArgumentException('The requested window has passed. Create a new request.');
        }

        $grant->forceFill([
            'approved_by' => $approver->getKey(),
            'approved_at' => now(),
            'starts_at' => now(),
            'state' => AccessGrantState::Active,
        ])->save();

        return $grant;
    }

    public function reject(AccessGrant $grant, User $approver, ?string $reason = null): AccessGrant
    {
        if ($grant->state !== AccessGrantState::Requested) {
            throw new LogicException('Only a requested grant can be rejected.');
        }

        if ((int) $grant->requested_by === (int) $approver->getKey()) {
            throw new InvalidArgumentException('Separation of duties: the approver cannot be the requester.');
        }

        $grant->forceFill([
            'state' => AccessGrantState::Rejected,
            'approved_by' => $approver->getKey(),
            'rejected_at' => now(),
            'revoked_reason' => $reason,
        ])->save();

        return $grant;
    }

    public function revoke(AccessGrant $grant, User $actor, string $reason): AccessGrant
    {
        if (! $grant->state->isOpen()) {
            throw new LogicException('Only an open grant can be revoked.');
        }

        $grant->forceFill([
            'state' => AccessGrantState::Revoked,
            'revoked_at' => now(),
            'revoked_by' => $actor->getKey(),
            'revoked_reason' => $reason,
        ])->save();

        return $grant;
    }

    /**
     * Mark every grant past its expiry as expired. Expiry is the fail-safe when
     * the event that should have revoked the grant never arrived.
     */
    public function expire(): int
    {
        $expired = 0;

        AccessGrant::query()->expirable()->chunkById(200, function ($grants) use (&$expired): void {
            foreach ($grants as $grant) {
                $grant->forceFill([
                    'state' => AccessGrantState::Expired,
                ])->save();

                $expired++;
            }
        });

        return $expired;
    }

    public function recordReview(
        AccessGrant $grant,
        User $reviewer,
        PrivacyReviewOutcome $outcome,
        ?string $notes = null,
    ): AccessGrant {
        if (! $grant->review_required) {
            throw new LogicException('This grant has no privacy review obligation.');
        }

        if ($outcome === PrivacyReviewOutcome::Pending) {
            throw new InvalidArgumentException('A review must record an outcome, not leave it pending.');
        }

        $grant->forceFill([
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_outcome' => $outcome,
            'review_notes' => $notes,
        ])->save();

        return $grant;
    }
}
