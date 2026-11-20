<?php

namespace Tests\Feature;

use App\Enums\RiskAcceptanceStatus;
use App\Enums\UserRole;
use App\Filament\Resources\RiskResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Risk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskAcceptanceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function the_risk_list_shows_the_acceptance_state(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $acceptor = User::factory()->create(['name' => 'Risk Acceptor']);

        Risk::factory()->create([
            'name' => 'Legacy vendor access',
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'accepted_by' => $acceptor->getKey(),
            'accepted_at' => today(),
            'acceptance_expires_at' => today()->addMonth(),
            'acceptance_rationale' => 'Compensating network controls are in place.',
        ]);

        $this->actingAs($admin)
            ->get(RiskResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Legacy vendor access')
            ->assertSee('Accepted');
    }

    #[Test]
    public function an_editor_can_open_the_risk_view(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $risk = Risk::factory()->create();

        $this->actingAs($editor)
            ->get(RiskResource::getUrl('view', ['record' => $risk]))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_open_the_risk_register(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(RiskResource::getUrl('index'))
            ->assertOk();
    }
}
