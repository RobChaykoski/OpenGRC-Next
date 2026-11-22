<?php

namespace Tests\Feature\Assistant;

use App\Enums\AssistantProposalState;
use App\Enums\ImplementationStatus;
use App\Enums\UserRole;
use App\Models\AssistantProposal;
use App\Models\Implementation;
use App\Models\User;
use App\Services\Assistant\AssistantProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantProposalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_proposal_captures_the_current_value_and_the_requested_one(): void
    {
        $implementation = $this->implementation();

        $proposal = $this->service()->propose(
            [['op' => 'implementation.status', 'target' => $implementation->getKey(), 'after' => 'Implemented']],
            'Mark the control as implemented',
            'The evidence attached last week shows the procedure is operating.',
            'run-1',
        );

        $this->assertSame(AssistantProposalState::Proposed, $proposal->state);
        $this->assertSame('Unknown', $proposal->operations[0]['before']);
        $this->assertSame('Implemented', $proposal->operations[0]['after']);
        $this->assertSame(ImplementationStatus::UNKNOWN, $implementation->refresh()->status);
    }

    #[Test]
    public function approving_and_applying_changes_the_record(): void
    {
        $implementation = $this->implementation();
        $editor = $this->editor();
        $proposal = $this->proposal($implementation);

        $this->service()->approve($proposal, $editor);
        $this->service()->apply($proposal, $editor);

        $proposal->refresh();

        $this->assertSame(AssistantProposalState::Applied, $proposal->state);
        $this->assertSame($editor->getKey(), $proposal->applied_by);
        $this->assertSame(ImplementationStatus::FULL, $implementation->refresh()->status);
    }

    #[Test]
    public function applying_fails_closed_when_the_record_moved(): void
    {
        $implementation = $this->implementation();
        $editor = $this->editor();
        $proposal = $this->proposal($implementation);

        $this->service()->approve($proposal, $editor);

        $implementation->update(['status' => ImplementationStatus::PARTIAL]);

        try {
            $this->service()->apply($proposal, $editor);
            $this->fail('Apply should have failed.');
        } catch (InvalidArgumentException) {
            // Expected.
        }

        $this->assertSame(AssistantProposalState::Failed, $proposal->refresh()->state);
        $this->assertSame(ImplementationStatus::PARTIAL, $implementation->refresh()->status);
    }

    #[Test]
    public function rolling_back_restores_the_previous_value(): void
    {
        $implementation = $this->implementation();
        $editor = $this->editor();
        $proposal = $this->proposal($implementation);

        $this->service()->approve($proposal, $editor);
        $this->service()->apply($proposal, $editor);
        $this->service()->rollback($proposal, $editor, 'Evidence was withdrawn.');

        $this->assertSame(AssistantProposalState::RolledBack, $proposal->refresh()->state);
        $this->assertSame(ImplementationStatus::UNKNOWN, $implementation->refresh()->status);
    }

    #[Test]
    public function only_an_approved_proposal_can_be_applied(): void
    {
        $proposal = $this->proposal($this->implementation());

        $this->expectException(LogicException::class);

        $this->service()->apply($proposal, $this->editor());
    }

    #[Test]
    public function an_operation_outside_the_catalogue_is_refused(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->propose(
            [['op' => 'user.role', 'target' => $user->getKey(), 'after' => 'admin']],
            'Escalate the account',
            'This should never be possible.',
        );
    }

    #[Test]
    public function an_append_only_target_is_not_in_the_catalogue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->propose(
            [['op' => 'evidence_record.delete', 'target' => 1, 'after' => null]],
            'Remove evidence',
            'Append-only records cannot be changed.',
        );
    }

    #[Test]
    public function an_invalid_after_value_is_refused(): void
    {
        $implementation = $this->implementation();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->propose(
            [['op' => 'implementation.status', 'target' => $implementation->getKey(), 'after' => 'Compliant-ish']],
            'Change status',
            'Not a real status.',
        );
    }

    #[Test]
    public function a_proposal_is_bounded_in_size(): void
    {
        $implementation = $this->implementation();
        $operations = array_fill(0, 6, [
            'op' => 'implementation.status',
            'target' => $implementation->getKey(),
            'after' => 'Implemented',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->propose($operations, 'Too many changes', 'Six is more than the bound.');
    }

    #[Test]
    public function a_viewer_cannot_approve_a_proposal(): void
    {
        $proposal = $this->proposal($this->implementation());

        $this->expectException(InvalidArgumentException::class);

        $this->service()->approve($proposal, User::factory()->create(['role' => UserRole::Viewer]));
    }

    #[Test]
    public function a_rejection_needs_a_reason(): void
    {
        $proposal = $this->proposal($this->implementation());

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reject($proposal, $this->editor(), '  ');
    }

    #[Test]
    public function rejecting_records_the_decision(): void
    {
        $proposal = $this->proposal($this->implementation());
        $editor = $this->editor();

        $this->service()->reject($proposal, $editor, 'The evidence does not support it.');

        $proposal->refresh();

        $this->assertSame(AssistantProposalState::Rejected, $proposal->state);
        $this->assertSame($editor->getKey(), $proposal->rejected_by);
        $this->assertSame('The evidence does not support it.', $proposal->rejected_reason);
    }

    #[Test]
    public function a_date_change_is_applied_and_rolled_back(): void
    {
        $implementation = $this->implementation(['next_test_at' => '2026-12-01']);
        $editor = $this->editor();

        $proposal = $this->service()->propose(
            [['op' => 'implementation.next_test_at', 'target' => $implementation->getKey(), 'after' => '2027-03-01']],
            'Move the next test date',
            'The test window moved with the audit.',
        );

        $this->service()->approve($proposal, $editor);
        $this->service()->apply($proposal, $editor);

        $this->assertSame('2027-03-01', $implementation->refresh()->next_test_at?->toDateString());

        $this->service()->rollback($proposal, $editor, 'The audit moved back.');

        $this->assertSame('2026-12-01', $implementation->refresh()->next_test_at?->toDateString());
    }

    protected function implementation(array $overrides = []): Implementation
    {
        return Implementation::factory()->create(array_merge([
            'status' => ImplementationStatus::UNKNOWN,
            'next_test_at' => '2026-12-01',
        ], $overrides));
    }

    protected function proposal(Implementation $implementation): AssistantProposal
    {
        return $this->service()->propose(
            [['op' => 'implementation.status', 'target' => $implementation->getKey(), 'after' => 'Implemented']],
            'Mark the control as implemented',
            'The attached evidence shows the procedure is operating.',
        );
    }

    protected function editor(): User
    {
        return User::factory()->create(['role' => UserRole::Editor]);
    }

    protected function service(): AssistantProposalService
    {
        return app(AssistantProposalService::class);
    }
}
