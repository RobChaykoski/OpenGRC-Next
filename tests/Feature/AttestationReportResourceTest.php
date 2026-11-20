<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\AttestationReportResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\AttestationReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttestationReportResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_register_and_create_a_report(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $report = $this->report();

        $this->actingAs($admin)
            ->get(AttestationReportResource::getUrl('index'))
            ->assertOk()
            ->assertSee($report->name)
            ->assertSee('Unqualified');

        $this->actingAs($admin)
            ->get(AttestationReportResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function an_editor_can_create_a_report(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(AttestationReportResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_a_report_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $report = $this->report();

        $this->actingAs($viewer)
            ->get(AttestationReportResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(AttestationReportResource::getUrl('view', ['record' => $report]))
            ->assertOk()
            ->assertSee('Customer platform')
            ->assertSee('Bridge letter');

        $this->actingAs($viewer)
            ->get(AttestationReportResource::getUrl('create'))
            ->assertForbidden();
    }

    private function report(): AttestationReport
    {
        return AttestationReport::factory()->create([
            'name' => 'SOC 2 Type 2 report',
            'subject' => 'Customer platform',
            'summary' => 'Unqualified opinion over the customer platform.',
        ]);
    }
}
