<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceSourceType;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\EvidenceSourceResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\EvidenceSource;
use App\Models\User;
use App\Policies\EvidenceSourcePolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EvidenceSourceUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_source_editor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(EvidenceSourceResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(EvidenceSourceResource::getUrl('create'))
            ->assertOk();
    }

    #[Test]
    public function an_editor_cannot_open_the_source_editor(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($editor)
            ->get(EvidenceSourceResource::getUrl('index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_edit_form_does_not_leak_the_stored_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $source = $this->source();

        $this->actingAs($admin)
            ->get(EvidenceSourceResource::getUrl('edit', ['record' => $source]))
            ->assertOk()
            ->assertDontSee('super-secret-password')
            ->assertSee(__('evidence.source.form.password_help'));
    }

    #[Test]
    public function merging_a_blank_password_preserves_the_stored_one(): void
    {
        $existing = ['password' => 'old-secret', 'base_url' => 'https://old.test'];
        $submitted = ['base_url' => 'https://new.test'];

        $merged = EvidenceSource::mergeConfiguration($existing, $submitted);

        $this->assertSame('old-secret', $merged['password']);
        $this->assertSame('https://new.test', $merged['base_url']);
    }

    #[Test]
    public function a_new_password_replaces_the_stored_one(): void
    {
        $merged = EvidenceSource::mergeConfiguration(
            ['password' => 'old-secret'],
            ['password' => 'new-secret']
        );

        $this->assertSame('new-secret', $merged['password']);
    }

    #[Test]
    public function only_admins_pass_the_source_policy(): void
    {
        $source = $this->source();
        $policy = new EvidenceSourcePolicy;

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->update($admin, $source));

        foreach ([$editor, $viewer] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->view($user, $source));
            $this->assertFalse($policy->update($user, $source));
        }
    }

    protected function source(): EvidenceSource
    {
        return EvidenceSource::create([
            'name' => 'PatchMon',
            'type' => EvidenceSourceType::Patchmon,
            'enabled' => true,
            'configuration' => [
                'base_url' => 'https://patchmon.test/api',
                'username' => 'opengrc',
                'password' => 'super-secret-password',
            ],
        ]);
    }
}
