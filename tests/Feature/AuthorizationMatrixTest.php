<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\VendorUserResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Role-by-panel authorization matrix for the three-role model.
 *
 * Admin administers, Editor writes, Viewer reads. The dangerous regression is a
 * page that looks restricted but is not, so every registered resource is walked
 * at the index level and write pages are checked per role.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function every_role_can_open_every_admin_resource_index(): void
    {
        $failures = [];

        foreach ([UserRole::Admin, UserRole::Editor, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($this->resourceUrls() as $name => $url) {
                if (! $role->canManage() && in_array($name, ['UserResource', 'VendorUserResource'], true)) {
                    continue;
                }

                $status = $this->actingAs($user)->get($url)->status();

                if (! in_array($status, [200, 302], true)) {
                    $failures["{$role->value}:{$name}"] = $status;
                }
            }
        }

        $this->assertSame([], $failures, 'Resources not reachable by role: '.json_encode($failures));
    }

    #[Test]
    public function viewers_cannot_open_any_create_page(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        foreach ($this->resourceUrls('create') as $name => $url) {
            $status = $this->actingAs($viewer)->get($url)->status();

            $this->assertContains(
                $status,
                [403, 302],
                "Viewer could open the create page for {$name}."
            );
        }
    }

    #[Test]
    public function only_admins_can_manage_users_and_vendor_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        foreach ([
            UserResource::class,
            VendorUserResource::class,
        ] as $resource) {
            $adminStatus = $this->actingAs($admin)->get($resource::getUrl('index'))->status();
            $viewerStatus = $this->actingAs($viewer)->get($resource::getUrl('index'))->status();

            $this->assertContains($adminStatus, [200, 302]);
            $this->assertContains($viewerStatus, [403, 302], class_basename($resource).' was visible to a viewer.');
        }
    }

    #[Test]
    public function settings_abilities_are_admin_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->assertTrue($admin->can('Manage Preferences'));
        $this->assertFalse($editor->can('Manage Preferences'));
        $this->assertFalse($viewer->can('Manage Preferences'));

        $this->assertTrue($admin->can('Manage Trust Center'));
        $this->assertFalse($viewer->can('Manage Trust Center'));

        $this->assertTrue($viewer->can('Read Standards'));
        $this->assertFalse($viewer->can('Create Standards'));
        $this->assertTrue($editor->can('Create Standards'));
        $this->assertFalse($editor->can('Delete Standards'));
    }

    /**
     * Index or create URLs for every registered admin resource.
     *
     * @return array<string, string>
     */
    protected function resourceUrls(string $page = 'index'): array
    {
        $urls = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            try {
                $url = $resource::getUrl($page);
            } catch (Throwable) {
                continue;
            }

            $urls[class_basename($resource)] = $url;
        }

        $this->assertNotEmpty($urls, 'No admin resources were discovered; the matrix would be a no-op.');

        return $urls;
    }

    protected function urlFor(string $resource, string $page): string
    {
        return $resource::getUrl($page);
    }
}
