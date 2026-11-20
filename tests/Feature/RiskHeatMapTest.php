<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
            ->assertSee('Very High (25): 1 risk')
            ->assertSee('Moderate (9): 1 risk')
            ->assertDontSee('High (16): 1 risk');
    }
}
