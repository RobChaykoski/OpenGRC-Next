<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Policy;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplianceOperationsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function the_dashboard_shows_the_compliance_queues(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Policy::factory()->create([
            'effective_date' => today()->subMonth(),
            'retired_date' => null,
        ]);

        $this->actingAs($admin)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('Policies to acknowledge')
            ->assertSee('Open SBOM advisories')
            ->assertSee('Access reviews')
            ->assertSee('Machine credentials')
            ->assertSee('Privacy requests overdue');
    }
}
