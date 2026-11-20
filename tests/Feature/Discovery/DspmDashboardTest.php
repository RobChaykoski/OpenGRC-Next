<?php

namespace Tests\Feature\Discovery;

use App\Enums\UserRole;
use App\Filament\Pages\DataPosture;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\DiscoveryFinding;
use App\Models\DiscoveryScan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DspmDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
    }

    #[Test]
    public function an_admin_can_open_the_data_posture_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(DataPosture::getUrl())
            ->assertOk()
            ->assertSee(__('dspm.title'))
            ->assertSee('DLP')
            ->assertSee('DSPM')
            ->assertSee(__('dspm.metrics.coverage'))
            ->assertSee(__('dspm.metrics.freshness'));
    }

    #[Test]
    public function a_viewer_can_read_the_page(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(DataPosture::getUrl())
            ->assertOk();
    }

    #[Test]
    public function the_dashboard_shows_aggregates_without_paths_or_previews(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $scan = DiscoveryScan::create([
            'remote_id' => 'scan-dspm-1',
            'asset_id' => 'asset-dspm-1',
            'asset_name' => 'billing-01',
            'profile_id' => 'profile-dspm-1',
            'profile_name' => 'Finance baseline',
            'status' => 'completed',
            'files_seen' => 10,
            'files_scanned' => 10,
            'findings_count' => 1,
            'synced_at' => now(),
        ]);

        DiscoveryFinding::create([
            'remote_id' => 'finding-dspm-1',
            'discovery_scan_id' => $scan->getKey(),
            'remote_scan_id' => $scan->remote_id,
            'asset_id' => $scan->asset_id,
            'asset_name' => $scan->asset_name,
            'detector_key' => 'ca_sin',
            'category' => 'pii',
            'severity' => 'critical',
            'classification' => 'restricted',
            'path' => '/srv/finance/customers.csv',
            'match_preview' => '04****86',
            'match_sha256' => str_repeat('b', 64),
            'confidence' => 0.9,
            'status' => 'open',
            'remote_created_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(DataPosture::getUrl())
            ->assertOk()
            ->assertSee('billing-01')
            ->assertDontSee('/srv/finance/customers.csv')
            ->assertDontSee('04****86');
    }
}
