<?php

namespace Tests\Feature\Dlp;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\DlpPolicyResource;
use App\Filament\Admin\Resources\DlpSuppressionResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DlpPolicy;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpGovernanceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_policy_and_suppression_screens(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(DlpPolicyResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(DlpPolicyResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(DlpSuppressionResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(DlpSuppressionResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function an_editor_can_create_a_policy_but_not_delete_it(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(DlpPolicyResource::getUrl('create'))
            ->assertOk();

        $this->assertFalse($editor->can('delete', DlpPolicy::factory()->create()));
    }

    #[Test]
    public function a_viewer_can_read_but_not_create(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(DlpPolicyResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(DlpPolicyResource::getUrl('create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(DlpSuppressionResource::getUrl('create'))
            ->assertForbidden();
    }
}
