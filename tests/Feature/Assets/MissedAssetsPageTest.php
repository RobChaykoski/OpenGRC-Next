<?php

namespace Tests\Feature\Assets;

use App\Enums\UserRole;
use App\Filament\Pages\MissedAssets;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\Asset;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MissedAssetsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withoutMiddleware(RequireTwoFactorAuthentication::class);
        config([
            'agent_deployment.wazuh.manager' => '172.16.16.5',
            'agent_deployment.wazuh.agent_group' => 'test',
        ]);
    }

    #[Test]
    public function an_admin_sees_the_report_and_deployment_commands(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Asset::factory()->create(['name' => 'web-01', 'hostname' => 'web-01', 'asset_tag' => 'AT-WEB-01']);

        $this->actingAs($admin)
            ->get(MissedAssets::getUrl())
            ->assertOk()
            ->assertSee(__('coverage.title'))
            ->assertSee('web-01')
            ->assertSee('WAZUH_MANAGER="172.16.16.5"')
            ->assertSee('wazuh-agent');
    }

    #[Test]
    public function a_viewer_can_read_the_report_but_not_the_commands(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        Asset::factory()->create(['name' => 'web-01', 'hostname' => 'web-01', 'asset_tag' => 'AT-WEB-01']);

        $this->actingAs($viewer)
            ->get(MissedAssets::getUrl())
            ->assertOk()
            ->assertSee(__('coverage.title'))
            ->assertDontSee('WAZUH_MANAGER="172.16.16.5"')
            ->assertSee(__('coverage.deploy.only_writers'));
    }

    #[Test]
    public function the_gap_filter_and_search_are_available(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        Asset::factory()->create(['name' => 'web-01', 'hostname' => 'web-01', 'asset_tag' => 'AT-WEB-01']);

        $this->actingAs($viewer)
            ->get(MissedAssets::getUrl())
            ->assertOk()
            ->assertSee(__('coverage.filters.gap'))
            ->assertSee(__('coverage.filters.search'));
    }
}
