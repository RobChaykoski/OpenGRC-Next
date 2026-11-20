<?php

namespace App\Services\Access;

use App\Enums\AccessReviewDecision as DecisionType;
use App\Enums\AccessReviewStatus;
use App\Models\AccessReview;
use App\Models\AccessReviewDecision as Decision;
use App\Models\User;
use InvalidArgumentException;

/**
 * Start, decide and complete an access review campaign.
 *
 * The snapshot is taken at start: every account, its role and its MFA state. The
 * decision is evidence; disabling an account in the identity provider stays a
 * separate, auditable action.
 */
class AccessReviewService
{
    public function start(AccessReview $review, ?User $actor = null): void
    {
        if ($review->status === AccessReviewStatus::Completed) {
            throw new InvalidArgumentException('A completed access review cannot be restarted.');
        }

        $users = User::query()->withTrashed()->orderBy('name')->get();

        foreach ($users as $user) {
            Decision::query()->firstOrCreate(
                [
                    'access_review_id' => $review->getKey(),
                    'user_id' => $user->getKey(),
                ],
                [
                    'role_at_review' => $user->role()->value,
                    'mfa_at_review' => $user->hasConfirmedTwoFactor(),
                    'decision' => DecisionType::Pending,
                ]
            );
        }

        $review->forceFill([
            'status' => AccessReviewStatus::Active,
            'started_at' => $review->started_at ?? now(),
            'created_by' => $review->created_by ?? $actor?->getKey(),
        ])->save();
    }

    public function decide(Decision $decision, DecisionType $type, User $actor, ?string $notes = null): void
    {
        $review = $decision->review;

        if (! $review instanceof AccessReview || ! $review->isActive()) {
            throw new InvalidArgumentException('Only an active access review can be decided.');
        }

        if (! $type->isDecided()) {
            throw new InvalidArgumentException('A decision must attest, revoke or mark not applicable.');
        }

        if ($type === DecisionType::Attest && (int) $decision->user_id === (int) $actor->getKey()) {
            throw new InvalidArgumentException('You cannot attest your own account; another reviewer must decide it.');
        }

        $decision->forceFill([
            'decision' => $type,
            'decided_by' => $actor->getKey(),
            'decided_at' => now(),
            'notes' => $notes,
        ])->save();
    }

    public function complete(AccessReview $review): void
    {
        if (! $review->isActive()) {
            throw new InvalidArgumentException('Only an active access review can be completed.');
        }

        if ($review->pendingCount() > 0) {
            throw new InvalidArgumentException('Every account must be decided before the review is completed.');
        }

        $review->forceFill([
            'status' => AccessReviewStatus::Completed,
            'completed_at' => now(),
        ])->save();
    }
}
