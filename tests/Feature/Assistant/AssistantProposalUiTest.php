<?php

namespace Tests\Feature\Assistant;

use App\Enums\ImplementationStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AssistantProposalResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\AssistantProposal;
use App\Models\Implementation;
use App\Models\User;
use App\Services\Assistant\AssistantProposalService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantProposalUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_read_the_proposal_queue_and_a_proposal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $proposal = $this->proposal();

        $this->actingAs($admin)
            ->get(AssistantProposalResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(AssistantProposalResource::getUrl('view', ['record' => $proposal]))
            ->assertOk()
            ->assertSee($proposal->summary);
    }

    #[Test]
    public function a_viewer_can_read_but_not_decide(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $proposal = $this->proposal();

        $this->actingAs($viewer)
            ->get(AssistantProposalResource::getUrl('index'))
            ->assertOk();

        $this->assertFalse($viewer->can('update', $proposal));
    }

    #[Test]
    public function there_is_no_create_screen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertFalse(AssistantProposalResource::canCreate());

        $this->actingAs($admin)
            ->get('/admin/assistant-proposals/create')
            ->assertNotFound();
    }

    protected function proposal(): AssistantProposal
    {
        $implementation = Implementation::factory()->create(['status' => ImplementationStatus::UNKNOWN]);

        return app(AssistantProposalService::class)->propose(
            [['op' => 'implementation.status', 'target' => $implementation->getKey(), 'after' => 'Implemented']],
            'Mark the control as implemented',
            'The attached evidence shows the procedure is operating.',
        );
    }
}
