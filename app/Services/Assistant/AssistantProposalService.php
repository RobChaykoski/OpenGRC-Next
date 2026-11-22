<?php

namespace App\Services\Assistant;

use App\Enums\AssistantProposalState;
use App\Models\AssistantProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * The proposal lifecycle (ADR 0055).
 *
 * The model never writes: it proposes a typed change set. A human approves it,
 * a human applies it, and the application re-checks every `before` so a record
 * that moved since the proposal fails closed instead of being overwritten.
 */
class AssistantProposalService
{
    public function __construct(protected AssistantOperations $operations) {}

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    public function propose(
        array $operations,
        string $summary,
        string $rationale,
        ?string $runId = null,
    ): AssistantProposal {
        $summary = trim($summary);
        $rationale = trim($rationale);

        if ($summary === '' || $rationale === '') {
            throw new InvalidArgumentException('A proposal needs a summary and a rationale.');
        }

        if ($operations === [] || count($operations) > AssistantOperations::MAX_OPERATIONS) {
            throw new InvalidArgumentException(
                'A proposal needs between 1 and '.AssistantOperations::MAX_OPERATIONS.' operations.'
            );
        }

        $normalised = [];

        foreach ($operations as $operation) {
            if (! is_array($operation)) {
                throw new InvalidArgumentException('Each operation must be an object.');
            }

            $normalised[] = $this->operations->normalise($operation);
        }

        return AssistantProposal::create([
            'run_id' => $runId,
            'summary' => mb_substr($summary, 0, 255),
            'rationale' => $rationale,
            'operations' => $normalised,
            'state' => AssistantProposalState::Proposed,
        ]);
    }

    public function approve(AssistantProposal $proposal, User $approver): AssistantProposal
    {
        $this->requireState($proposal, AssistantProposalState::Proposed, 'Only a proposed change set can be approved.');
        $this->requireWriter($approver);

        $proposal->forceFill([
            'state' => AssistantProposalState::Approved,
            'approved_by' => $approver->getKey(),
            'approved_at' => now(),
        ])->save();

        return $proposal;
    }

    public function reject(AssistantProposal $proposal, User $approver, string $reason): AssistantProposal
    {
        $this->requireState($proposal, AssistantProposalState::Proposed, 'Only a proposed change set can be rejected.');
        $this->requireWriter($approver);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rejection needs a reason.');
        }

        $proposal->forceFill([
            'state' => AssistantProposalState::Rejected,
            'rejected_by' => $approver->getKey(),
            'rejected_at' => now(),
            'rejected_reason' => mb_substr($reason, 0, 255),
        ])->save();

        return $proposal;
    }

    public function apply(AssistantProposal $proposal, User $actor): AssistantProposal
    {
        $this->requireState($proposal, AssistantProposalState::Approved, 'Only an approved change set can be applied.');
        $this->requireWriter($actor);

        try {
            DB::transaction(function () use ($proposal): void {
                foreach ($proposal->operations ?? [] as $operation) {
                    $this->operations->apply($operation);
                }
            });
        } catch (Throwable $exception) {
            $proposal->forceFill([
                'state' => AssistantProposalState::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 255),
            ])->save();

            throw $exception;
        }

        $proposal->forceFill([
            'state' => AssistantProposalState::Applied,
            'applied_by' => $actor->getKey(),
            'applied_at' => now(),
            'error' => null,
        ])->save();

        return $proposal;
    }

    public function rollback(AssistantProposal $proposal, User $actor, string $reason): AssistantProposal
    {
        $this->requireState($proposal, AssistantProposalState::Applied, 'Only an applied change set can be rolled back.');
        $this->requireWriter($actor);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rollback needs a reason.');
        }

        DB::transaction(function () use ($proposal): void {
            foreach ($proposal->operations ?? [] as $operation) {
                $this->operations->apply($this->operations->inverse($operation));
            }
        });

        $proposal->forceFill([
            'state' => AssistantProposalState::RolledBack,
            'rolled_back_by' => $actor->getKey(),
            'rolled_back_at' => now(),
            'rollback_reason' => mb_substr($reason, 0, 255),
        ])->save();

        return $proposal;
    }

    protected function requireState(
        AssistantProposal $proposal,
        AssistantProposalState $expected,
        string $message,
    ): void {
        if ($proposal->state !== $expected) {
            throw new LogicException($message);
        }
    }

    protected function requireWriter(User $user): void
    {
        if (! $user->role->canWrite()) {
            throw new InvalidArgumentException('Only an editor or administrator can decide on a proposal.');
        }
    }
}
