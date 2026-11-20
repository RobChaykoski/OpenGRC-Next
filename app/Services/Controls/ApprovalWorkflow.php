<?php

namespace App\Services\Controls;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Create and advance a multi-step approval request.
 *
 * Only the current pending step is actionable. An admin can act as a fallback
 * approver, but the decision is always recorded against the step.
 */
class ApprovalWorkflow
{
    public function create(string $title, ?string $description, ?CarbonInterface $dueAt, User $requester): ApprovalRequest
    {
        return ApprovalRequest::query()->create([
            'title' => $title,
            'description' => $description,
            'status' => ApprovalRequestStatus::Pending,
            'requested_by' => $requester->getKey(),
            'due_at' => $dueAt,
        ]);
    }

    public function addStep(ApprovalRequest $request, User $approver): ApprovalRequestStep
    {
        $this->assertPending($request);

        if ($request->steps()->where('status', '!=', ApprovalStepStatus::Pending->value)->exists()) {
            throw new InvalidArgumentException('A step cannot be added after the chain has started.');
        }

        if ($request->steps()->where('approver_id', $approver->getKey())->exists()) {
            throw new InvalidArgumentException('The same person cannot approve more than one step.');
        }

        return $request->steps()->create([
            'approver_id' => $approver->getKey(),
            'status' => ApprovalStepStatus::Pending,
        ]);
    }

    public function approve(ApprovalRequest $request, ApprovalRequestStep $step, User $user, ?string $comment = null): ApprovalRequest
    {
        $this->assertCurrent($request, $step);
        $this->assertCanDecide($step, $user);

        $step->update([
            'status' => ApprovalStepStatus::Approved,
            'decided_at' => now(),
            'decided_by' => $user->getKey(),
            'comment' => $comment,
        ]);

        $remaining = $request->steps()->pending()->exists();

        if (! $remaining) {
            $request->update([
                'status' => ApprovalRequestStatus::Approved,
                'completed_at' => now(),
            ]);
        }

        return $request->refresh();
    }

    public function reject(ApprovalRequest $request, ApprovalRequestStep $step, User $user, string $comment): ApprovalRequest
    {
        $this->assertCurrent($request, $step);
        $this->assertCanDecide($step, $user);

        if (trim($comment) === '') {
            throw new InvalidArgumentException('A rejection needs a reason.');
        }

        $step->update([
            'status' => ApprovalStepStatus::Rejected,
            'decided_at' => now(),
            'decided_by' => $user->getKey(),
            'comment' => $comment,
        ]);

        $request->steps()->pending()->update([
            'status' => ApprovalStepStatus::Skipped->value,
            'decided_at' => now(),
            'comment' => 'Skipped because an earlier step was rejected.',
        ]);

        $request->update([
            'status' => ApprovalRequestStatus::Rejected,
            'completed_at' => now(),
        ]);

        return $request->refresh();
    }

    public function cancel(ApprovalRequest $request, User $user): ApprovalRequest
    {
        $this->assertPending($request);

        if (! $user->isAdmin() && (int) $request->requested_by !== (int) $user->getKey()) {
            throw new InvalidArgumentException('Only the requester or an admin can cancel this request.');
        }

        $request->steps()->pending()->update([
            'status' => ApprovalStepStatus::Skipped->value,
            'decided_at' => now(),
            'comment' => 'Request cancelled.',
        ]);

        $request->update([
            'status' => ApprovalRequestStatus::Cancelled,
            'completed_at' => now(),
        ]);

        return $request->refresh();
    }

    protected function assertPending(ApprovalRequest $request): void
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException('This approval request is no longer pending.');
        }
    }

    protected function assertCurrent(ApprovalRequest $request, ApprovalRequestStep $step): void
    {
        $this->assertPending($request);

        if ((int) $step->approval_request_id !== (int) $request->getKey()) {
            throw new InvalidArgumentException('That step does not belong to this request.');
        }

        if (! $step->status->isPending()) {
            throw new InvalidArgumentException('That step has already been decided.');
        }

        if (! $step->isCurrent()) {
            throw new InvalidArgumentException('An earlier approval step is still pending.');
        }
    }

    protected function assertCanDecide(ApprovalRequestStep $step, User $user): void
    {
        if (! $user->isAdmin() && (int) $step->approver_id !== (int) $user->getKey()) {
            throw new InvalidArgumentException('Only the named approver or an admin can decide this step.');
        }
    }
}
