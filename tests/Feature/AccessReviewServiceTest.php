<?php

namespace Tests\Feature;

use App\Enums\AccessReviewDecision as DecisionType;
use App\Enums\AccessReviewStatus;
use App\Models\AccessReview;
use App\Models\AccessReviewDecision;
use App\Models\User;
use App\Services\Access\AccessReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function starting_a_review_snapshots_every_account(): void
    {
        $reviewer = User::factory()->create();
        $viewer = User::factory()->create();
        $editor = User::factory()->create(['role' => 'editor']);

        $review = AccessReview::factory()->create([
            'reviewer_id' => $reviewer->getKey(),
            'created_by' => $reviewer->getKey(),
        ]);

        app(AccessReviewService::class)->start($review, $reviewer);

        $this->assertSame(AccessReviewStatus::Active, $review->fresh()?->status);
        $this->assertSame(3, $review->decisions()->count());

        $decision = $review->decisions()->where('user_id', $editor->getKey())->firstOrFail();
        $this->assertSame('editor', $decision->role_at_review);
        $this->assertSame(DecisionType::Pending, $decision->decision);

        $this->assertNotNull($review->fresh()?->started_at);
    }

    #[Test]
    public function a_reviewer_can_attest_and_revoke_accounts(): void
    {
        $reviewer = User::factory()->create();
        $one = User::factory()->create();
        $two = User::factory()->create();
        $review = AccessReview::factory()->create([
            'reviewer_id' => $reviewer->getKey(),
            'created_by' => $reviewer->getKey(),
        ]);

        $service = app(AccessReviewService::class);
        $service->start($review, $reviewer);

        $first = $review->decisions()->where('user_id', $one->getKey())->firstOrFail();
        $second = $review->decisions()->where('user_id', $two->getKey())->firstOrFail();
        $reviewerDecision = $review->decisions()->where('user_id', $reviewer->getKey())->firstOrFail();

        $service->decide($first, DecisionType::Attest, $reviewer, 'Still required.');
        $service->decide($second, DecisionType::Revoke, $reviewer, 'Left the team.');

        // The reviewer cannot attest their own account; a second person decides it.
        $secondReviewer = User::factory()->create();
        $service->decide($reviewerDecision, DecisionType::Attest, $secondReviewer);

        $this->assertSame(DecisionType::Attest, $first->fresh()?->decision);
        $this->assertSame(DecisionType::Revoke, $second->fresh()?->decision);
        $this->assertSame($reviewer->getKey(), $first->fresh()?->decided_by);
        $this->assertSame(0, $review->pendingCount());

        $service->complete($review);

        $this->assertSame(AccessReviewStatus::Completed, $review->fresh()?->status);
        $this->assertNotNull($review->fresh()?->completed_at);
    }

    #[Test]
    public function a_reviewer_cannot_attest_their_own_account(): void
    {
        $reviewer = User::factory()->create();
        $review = AccessReview::factory()->create(['reviewer_id' => $reviewer->getKey()]);

        $service = app(AccessReviewService::class);
        $service->start($review, $reviewer);

        $own = $review->decisions()->where('user_id', $reviewer->getKey())->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $service->decide($own, DecisionType::Attest, $reviewer);
    }

    #[Test]
    public function a_review_with_pending_accounts_cannot_be_completed(): void
    {
        $reviewer = User::factory()->create();
        User::factory()->create();
        $review = AccessReview::factory()->create(['reviewer_id' => $reviewer->getKey()]);

        $service = app(AccessReviewService::class);
        $service->start($review, $reviewer);

        $this->expectException(InvalidArgumentException::class);

        $service->complete($review);
    }

    #[Test]
    public function a_completed_review_cannot_be_restarted(): void
    {
        $review = AccessReview::factory()->completed()->create();
        AccessReviewDecision::query()->create([
            'access_review_id' => $review->getKey(),
            'user_id' => User::factory()->create()->getKey(),
            'role_at_review' => 'viewer',
            'mfa_at_review' => false,
            'decision' => DecisionType::Attest,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(AccessReviewService::class)->start($review);
    }
}
