<?php

namespace Tests\Feature\Access;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\UserRole;
use App\Filament\Pages\AccessGovernance;
use App\Filament\Resources\AccessDecisionResource;
use App\Filament\Resources\AccessGrantResource;
use App\Filament\Resources\AccessPolicyResource;
use App\Filament\Resources\ProtectedResourceResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use App\Models\AccessPolicy;
use App\Models\ProtectedResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessGovernanceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_every_access_governance_index(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([
            ProtectedResourceResource::class,
            AccessPolicyResource::class,
            AccessGrantResource::class,
            AccessDecisionResource::class,
        ] as $resource) {
            $this->actingAs($admin)
                ->get($resource::getUrl('index'))
                ->assertOk();
        }
    }

    #[Test]
    public function a_viewer_can_read_the_registers(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(AccessPolicyResource::getUrl('index'))
            ->assertOk();
    }

    #[Test]
    public function the_overview_is_available_to_every_role(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(AccessGovernance::getUrl())
            ->assertOk()
            ->assertSee('No decision means deny')
            ->assertSee('Protected resources');
    }

    #[Test]
    public function a_viewer_cannot_create_a_policy(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(AccessPolicyResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function a_policy_view_page_renders_the_rule(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $policy = AccessPolicy::factory()->create(['code' => 'AP-UI-1']);

        $this->actingAs($admin)
            ->get(AccessPolicyResource::getUrl('view', ['record' => $policy]))
            ->assertOk()
            ->assertSee('AP-UI-1');
    }

    #[Test]
    public function a_grant_view_page_renders_the_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $grant = AccessGrant::factory()->breakGlass()->create();

        $this->actingAs($admin)
            ->get(AccessGrantResource::getUrl('view', ['record' => $grant]))
            ->assertOk()
            ->assertSee('Record privacy review');
    }

    #[Test]
    public function a_decision_page_is_read_only_and_renders_the_asserted_context(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $decision = AccessDecision::factory()->create([
            'context' => ['trust' => 'high', 'zone' => 'ED_Zone_A'],
            'profile' => AccessorProfile::Emergency,
            'action' => AccessAction::TimeBoxed,
        ]);

        $this->actingAs($admin)
            ->get(AccessDecisionResource::getUrl('view', ['record' => $decision]))
            ->assertOk()
            ->assertSee('append-only')
            ->assertSee('ED_Zone_A')
            ->assertDontSee('client_ip');
    }

    #[Test]
    public function a_protected_resource_page_marks_a_stale_classification(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $resource = ProtectedResource::factory()->stale()->create(['name' => 'Clinical records']);

        $this->actingAs($admin)
            ->get(ProtectedResourceResource::getUrl('view', ['record' => $resource]))
            ->assertOk()
            ->assertSee('Stale');
    }
}
