<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalStepStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyApprovals;
use App\Filament\Resources\ApprovalRequestResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Controls\ApprovalWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_request_moves_through_its_steps_in_order(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Editor]);
        $first = User::factory()->create(['role' => UserRole::Editor]);
        $second = User::factory()->create(['role' => UserRole::Admin]);

        $workflow = app(ApprovalWorkflow::class);
        $request = $workflow->create('Approve a policy exception', 'The exception expires in 30 days.', today()->addDays(5), $requester);
        $stepOne = $workflow->addStep($request, $first);
        $stepTwo = $workflow->addStep($request, $second);

        $request = $workflow->approve($request, $stepOne, $first, 'Security review complete.');
        $this->assertSame(ApprovalRequestStatus::Pending, $request->status);
        $this->assertSame(ApprovalStepStatus::Approved, $stepOne->fresh()->status);

        $request = $workflow->approve($request, $stepTwo, $second);
        $this->assertSame(ApprovalRequestStatus::Approved, $request->status);
        $this->assertNotNull($request->completed_at);
        $this->assertSame($second->getKey(), $stepTwo->fresh()->decided_by);
    }

    #[Test]
    public function a_rejection_ends_the_chain_and_skips_later_steps(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Editor]);
        $first = User::factory()->create(['role' => UserRole::Editor]);
        $second = User::factory()->create(['role' => UserRole::Editor]);

        $workflow = app(ApprovalWorkflow::class);
        $request = $workflow->create('Approve a vendor', null, null, $requester);
        $stepOne = $workflow->addStep($request, $first);
        $stepTwo = $workflow->addStep($request, $second);

        $request = $workflow->reject($request, $stepOne, $first, 'The vendor assessment is incomplete.');

        $this->assertSame(ApprovalRequestStatus::Rejected, $request->status);
        $this->assertSame(ApprovalStepStatus::Rejected, $stepOne->fresh()->status);
        $this->assertSame(ApprovalStepStatus::Skipped, $stepTwo->fresh()->status);
        $this->assertSame('The vendor assessment is incomplete.', $stepOne->fresh()->comment);
    }

    #[Test]
    public function only_the_current_approver_or_an_admin_can_decide(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Editor]);
        $first = User::factory()->create(['role' => UserRole::Editor]);
        $other = User::factory()->create(['role' => UserRole::Editor]);

        $workflow = app(ApprovalWorkflow::class);
        $request = $workflow->create('Approve a change', null, null, $requester);
        $step = $workflow->addStep($request, $first);

        $this->expectException(InvalidArgumentException::class);
        $workflow->approve($request, $step, $other);
    }

    #[Test]
    public function a_step_cannot_be_added_after_the_chain_has_started_or_for_the_same_approver(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Editor]);
        $first = User::factory()->create(['role' => UserRole::Editor]);

        $workflow = app(ApprovalWorkflow::class);
        $request = $workflow->create('Approve a change', null, null, $requester);
        $step = $workflow->addStep($request, $first);

        try {
            $workflow->addStep($request, $first);
            $this->fail('The same approver should not be added twice.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $workflow->approve($request, $step, $first);

        $this->expectException(InvalidArgumentException::class);
        $workflow->addStep($request, User::factory()->create(['role' => UserRole::Editor]));
    }

    #[Test]
    public function the_approver_queue_shows_only_the_current_actionable_step(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $requester = User::factory()->create(['role' => UserRole::Editor]);
        $first = User::factory()->create(['role' => UserRole::Editor]);
        $second = User::factory()->create(['role' => UserRole::Editor]);

        $workflow = app(ApprovalWorkflow::class);
        $request = $workflow->create('Approve a vendor exception', 'Review the assessment.', today()->addDays(5), $requester);
        $workflow->addStep($request, $first);
        $workflow->addStep($request, $second);

        $this->actingAs($first)
            ->get(MyApprovals::getUrl())
            ->assertOk()
            ->assertSee('Approve a vendor exception')
            ->assertSee('Step 1 of 2');

        $this->actingAs($second)
            ->get(MyApprovals::getUrl())
            ->assertOk()
            ->assertDontSee('Approve a vendor exception');
    }

    #[Test]
    public function an_admin_can_open_the_approval_register_and_a_viewer_cannot_create(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        ApprovalRequest::query()->create([
            'title' => 'Approve a policy exception',
            'status' => ApprovalRequestStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(ApprovalRequestResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Approve a policy exception');

        $this->actingAs($admin)
            ->get(ApprovalRequestResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(ApprovalRequestResource::getUrl('create'))
            ->assertForbidden();
    }
}
