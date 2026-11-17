<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Role-by-panel authorization matrix.
 *
 * The dangerous failure for a GRC platform is a panel or resource that looks
 * protected but is not. This walks every registered admin resource and checks
 * both ends of the matrix: a super admin can open it, and a user with no
 * permissions cannot.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function a_super_admin_can_open_every_admin_resource(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $failures = [];

        foreach ($this->resourceUrls() as $name => $url) {
            $response = $this->actingAs($admin)->get($url);

            if (! in_array($response->status(), [200, 302], true)) {
                $failures[$name] = $response->status();
            }
        }

        $this->assertSame([], $failures, 'Admin resources not reachable by Super Admin: '.json_encode($failures));
    }

    #[Test]
    public function a_user_with_no_permissions_cannot_open_any_admin_resource(): void
    {
        $nobody = User::factory()->create();

        $failures = [];

        foreach ($this->resourceUrls() as $name => $url) {
            $response = $this->actingAs($nobody)->get($url);

            if (! in_array($response->status(), [403, 302], true)) {
                $failures[$name] = $response->status();
            }
        }

        $this->assertSame([], $failures, 'Admin resources reachable without permissions: '.json_encode($failures));
    }

    #[Test]
    public function a_user_with_list_only_permissions_cannot_create_records(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Regular User');

        foreach ($this->resourceUrls('create') as $name => $url) {
            $response = $this->actingAs($viewer)->get($url);

            $this->assertContains(
                $response->status(),
                [403, 302],
                "Regular User could open the create page for {$name}."
            );
        }
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
                // Resource has no such page (custom pages, read-only resources).
                continue;
            }

            $urls[class_basename($resource)] = $url;
        }

        $this->assertNotEmpty($urls, 'No admin resources were discovered; the matrix would be a no-op.');

        return $urls;
    }
}
