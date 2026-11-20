<?php

namespace Tests\Feature;

use App\Enums\RegulatoryChangeStatus;
use App\Enums\UserRole;
use App\Filament\Resources\RegulatoryChangeResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\RegulatoryChange;
use App\Models\Standard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegulatoryChangeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_register_and_create_a_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $standard = Standard::factory()->create(['name' => 'NIST CSF']);
        $change = RegulatoryChange::factory()->create([
            'title' => 'NIST CSF 2.0 update',
            'status' => RegulatoryChangeStatus::Assessing,
        ]);
        $change->standards()->attach($standard);

        $this->actingAs($admin)
            ->get(RegulatoryChangeResource::getUrl('index'))
            ->assertOk()
            ->assertSee('NIST CSF 2.0 update')
            ->assertSee('NIST CSF');

        $this->actingAs($admin)
            ->get(RegulatoryChangeResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_a_change_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $change = RegulatoryChange::factory()->create([
            'title' => 'PCI DSS v4.0.1 update',
            'summary' => 'New authentication requirements apply.',
        ]);

        $this->actingAs($viewer)
            ->get(RegulatoryChangeResource::getUrl('view', ['record' => $change]))
            ->assertOk()
            ->assertSee('New authentication requirements apply.');

        $this->actingAs($viewer)
            ->get(RegulatoryChangeResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(RegulatoryChangeResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function the_overdue_scope_only_returns_open_changes(): void
    {
        RegulatoryChange::factory()->overdue()->create();
        RegulatoryChange::factory()->create([
            'status' => RegulatoryChangeStatus::Implemented,
            'due_at' => today()->subDays(10),
        ]);

        $this->assertSame(1, RegulatoryChange::query()->overdue()->count());
    }
}
