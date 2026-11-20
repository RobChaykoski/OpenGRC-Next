<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\NonHumanIdentityResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\NonHumanIdentity;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NonHumanIdentityResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_register_and_create_an_identity(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $identity = $this->identity();

        $this->actingAs($admin)
            ->get(NonHumanIdentityResource::getUrl('index'))
            ->assertOk()
            ->assertSee($identity->name)
            ->assertSee('Wazuh');

        $this->actingAs($admin)
            ->get(NonHumanIdentityResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function a_viewer_can_read_an_identity_but_not_create_one(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $identity = $this->identity();

        $this->actingAs($viewer)
            ->get(NonHumanIdentityResource::getUrl('view', ['record' => $identity]))
            ->assertOk()
            ->assertSee('Read SCA results for evidence collection.')
            ->assertSee('vault://kv/opengrc/wazuh-api');

        $this->actingAs($viewer)
            ->get(NonHumanIdentityResource::getUrl('create'))
            ->assertForbidden();
    }

    #[Test]
    public function an_editor_can_open_the_create_form(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(NonHumanIdentityResource::getUrl('create'))
            ->assertOk();
    }

    private function identity(): NonHumanIdentity
    {
        return NonHumanIdentity::factory()->create([
            'name' => 'Wazuh API reader',
            'system' => 'Wazuh',
            'purpose' => 'Read SCA results for evidence collection.',
            'secret_reference' => 'vault://kv/opengrc/wazuh-api',
        ]);
    }
}
