<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Admin\Pages\Settings\RiskSettings;
use App\Filament\Pages\RiskHeatMap;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Risk;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskHeatMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function it_counts_active_risks_in_the_inherent_and_residual_matrices(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        Risk::factory()->create([
            'name' => 'Public outage',
            'is_active' => true,
            'inherent_likelihood' => 5,
            'inherent_impact' => 5,
            'residual_likelihood' => 3,
            'residual_impact' => 3,
        ]);

        Risk::factory()->create([
            'name' => 'Retired risk',
            'is_active' => false,
            'inherent_likelihood' => 4,
            'inherent_impact' => 4,
            'residual_likelihood' => 2,
            'residual_impact' => 2,
        ]);

        $this->actingAs($viewer)
            ->get(RiskHeatMap::getUrl())
            ->assertOk()
            ->assertSee('Risk heat map')
            ->assertSee('Active risks')
            ->assertSee('Appetite threshold: High (13)')
            ->assertSee('1 active risk at or above it is outlined')
            ->assertSee('Very High (25): 1 risk')
            ->assertSee('Moderate (9): 1 risk')
            ->assertDontSee('High (16): 1 risk');
    }

    #[Test]
    public function the_appetite_threshold_comes_from_settings(): void
    {
        setting(['risk.appetite_threshold' => 9]);

        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        Risk::factory()->create([
            'is_active' => true,
            'inherent_likelihood' => 4,
            'inherent_impact' => 4,
            'residual_likelihood' => 2,
            'residual_impact' => 2,
        ]);

        $this->actingAs($viewer)
            ->get(RiskHeatMap::getUrl())
            ->assertOk()
            ->assertSee('Appetite threshold: Moderate (9)')
            ->assertSee('1 active risk at or above it is outlined');
    }

    #[Test]
    public function an_admin_can_open_risk_settings_but_a_viewer_cannot(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($admin)
            ->get(RiskSettings::getUrl())
            ->assertOk()
            ->assertSee('Risk appetite');

        $this->actingAs($viewer)
            ->get(RiskSettings::getUrl())
            ->assertForbidden();
    }
}
