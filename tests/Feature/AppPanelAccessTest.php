<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The app panel and the admin panel share a web session. These tests guard the
 * hand-off so an admin is not bounced into /admin when they open /app.
 */
class AppPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    #[Test]
    public function an_editor_lands_in_the_app_panel(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $response = $this->actingAs($editor)->get('/app');

        $response->assertRedirect();
        $this->assertStringContainsString('/app', (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString('/admin', (string) $response->headers->get('Location'));
    }

    #[Test]
    public function an_admin_without_two_factor_is_kept_in_the_app_panel_to_enrol(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/app');

        $response->assertRedirect();
        $this->assertStringContainsString('/app/me', (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString('/admin', (string) $response->headers->get('Location'));
    }

    #[Test]
    public function an_editor_can_open_the_new_app_resources(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)->get('/app/findings')->assertOk();
        $this->actingAs($editor)->get('/app/evidence-records')->assertOk();
        $this->actingAs($editor)->get('/app/policy-readiness-items')->assertOk();
    }

    #[Test]
    public function an_admin_can_open_the_dlp_findings_page(): void
    {
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin/dlp-findings')->assertOk();
    }
}
