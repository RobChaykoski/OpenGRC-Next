<?php

namespace Tests\Feature\Discovery;

use App\Enums\UserRole;
use App\Filament\Resources\DiscoveryFindingResource;
use App\Filament\Resources\DiscoveryScanResource;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The discovery console is a read-only mirror. Every role may read it, but no
 * role may write, and the detail page never renders raw matched content.
 */
class DiscoveryUiAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_scan_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->scan();

        $this->actingAs($admin)
            ->get(DiscoveryScanResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('discovery.scan.navigation'))
            ->assertSee('billing-01');
    }

    #[Test]
    public function an_admin_can_inspect_a_scan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $scan = $this->scan();

        $this->actingAs($admin)
            ->get(DiscoveryScanResource::getUrl('view', ['record' => $scan]))
            ->assertOk()
            ->assertSee($scan->remote_id)
            ->assertSee($scan->asset_id)
            ->assertSee($scan->profile_name)
            ->assertSee(__('discovery.scan.sections.timing'))
            ->assertSee(__('discovery.scan.entries.error'));
    }

    #[Test]
    public function an_admin_can_open_the_finding_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->finding();

        $this->actingAs($admin)
            ->get(DiscoveryFindingResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('discovery.finding.navigation'))
            ->assertSee('ssn.us');
    }

    #[Test]
    public function an_admin_can_inspect_a_finding_without_raw_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $finding = $this->finding();

        $this->actingAs($admin)
            ->get(DiscoveryFindingResource::getUrl('view', ['record' => $finding]))
            ->assertOk()
            ->assertSee($finding->remote_id)
            ->assertSee($finding->path)
            ->assertSee($finding->match_preview)
            ->assertSee($finding->match_sha256)
            ->assertSee(__('discovery.finding.sections.match'))
            ->assertDontSee('raw-content-should-not-be-stored');
    }

    #[Test]
    public function editors_and_viewers_can_read_the_console(): void
    {
        $scan = $this->scan();
        $finding = $this->finding($scan);

        foreach ([UserRole::Editor, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(DiscoveryScanResource::getUrl('index'))
                ->assertOk();

            $this->actingAs($user)
                ->get(DiscoveryFindingResource::getUrl('index'))
                ->assertOk();

            $this->actingAs($user)
                ->get(DiscoveryScanResource::getUrl('view', ['record' => $scan]))
                ->assertOk();

            $this->actingAs($user)
                ->get(DiscoveryFindingResource::getUrl('view', ['record' => $finding]))
                ->assertOk();
        }
    }

    #[Test]
    public function the_discovery_resources_never_offer_writes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $scan = $this->scan();
        $finding = $this->finding($scan);

        $this->actingAs($admin);

        foreach ([
            DiscoveryScanResource::class => $scan,
            DiscoveryFindingResource::class => $finding,
        ] as $resource => $record) {
            $this->assertFalse($resource::canCreate(), "{$resource} offers create.");
            $this->assertFalse($resource::canEdit($record), "{$resource} offers edit.");
            $this->assertFalse($resource::canDelete($record), "{$resource} offers delete.");
            $this->assertFalse($resource::canDeleteAny(), "{$resource} offers bulk delete.");
        }
    }

    protected function scan(): DiscoveryScan
    {
        return DiscoveryScan::create([
            'remote_id' => 'scan-1001',
            'asset_id' => 'asset-42',
            'asset_name' => 'billing-01',
            'profile_id' => 'profile-7',
            'profile_name' => 'Linux baseline',
            'status' => 'completed',
            'files_seen' => 1200,
            'files_scanned' => 1180,
            'findings_count' => 3,
            'error' => null,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(5),
            'remote_created_at' => now()->subMinutes(12),
            'synced_at' => now(),
        ]);
    }

    protected function finding(?DiscoveryScan $scan = null): DiscoveryFinding
    {
        $scan ??= $this->scan();

        return DiscoveryFinding::create([
            'remote_id' => 'finding-2001',
            'discovery_scan_id' => $scan->getKey(),
            'remote_scan_id' => $scan->remote_id,
            'asset_id' => $scan->asset_id,
            'asset_name' => $scan->asset_name,
            'detector_key' => 'ssn.us',
            'category' => 'pii',
            'severity' => 'high',
            'classification' => 'confidential',
            'path' => '/srv/data/customers.csv',
            'match_preview' => '...123-45-6789...',
            'match_sha256' => str_repeat('a', 64),
            'confidence' => 0.92,
            'status' => 'open',
            'remote_created_at' => now()->subMinutes(8),
            'synced_at' => now(),
        ]);
    }
}
