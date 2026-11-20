<?php

namespace Tests\Feature\Assets;

use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;
use App\Services\Assets\AgentDeploymentGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentDeploymentGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_wazuh_commands_use_the_manager_without_a_secret(): void
    {
        config([
            'agent_deployment.wazuh.manager' => '172.16.16.5',
            'agent_deployment.wazuh.agent_group' => 'test',
            'agent_deployment.wazuh.windows_msi_url' => 'https://example.test/wazuh-agent.msi',
        ]);

        $commands = app(AgentDeploymentGuide::class)->commands();

        $this->assertStringContainsString('WAZUH_MANAGER="172.16.16.5"', $commands['wazuh_debian']);
        $this->assertStringContainsString('wazuh-agent', $commands['wazuh_debian']);
        $this->assertStringNotContainsString('password', strtolower($commands['wazuh_debian']));
        $this->assertStringContainsString('WAZUH_MANAGER="172.16.16.5"', $commands['wazuh_windows']);
        $this->assertStringContainsString('https://example.test/wazuh-agent.msi', $commands['wazuh_windows']);
    }

    public function test_optional_installers_are_not_invented_when_unconfigured(): void
    {
        config([
            'agent_deployment.wazuh.manager' => '',
            'agent_deployment.patchmon.install_command' => '',
            'agent_deployment.fleet.url' => '',
        ]);

        $guide = app(AgentDeploymentGuide::class);

        $this->assertNull($guide->patchmon());
        $this->assertNull($guide->fleet());
    }

    public function test_a_patchmon_template_replaces_placeholders(): void
    {
        config([
            'agent_deployment.patchmon.server_url' => 'https://patchmon.test',
            'agent_deployment.patchmon.install_command' => 'install-agent --server {{server_url}} --group {{agent_group}}',
            'agent_deployment.wazuh.agent_group' => 'servers',
        ]);

        $this->assertSame(
            'install-agent --server https://patchmon.test --group servers',
            app(AgentDeploymentGuide::class)->patchmon()
        );
    }

    public function test_the_wazuh_manager_falls_back_to_the_configured_source(): void
    {
        config(['agent_deployment.wazuh.manager' => '']);

        EvidenceSource::create([
            'name' => 'Wazuh manager',
            'type' => EvidenceSourceType::Wazuh,
            'enabled' => true,
            'configuration' => ['base_url' => 'https://wazuh.example:55000'],
        ]);

        $guide = app(AgentDeploymentGuide::class);

        $this->assertSame('wazuh.example', $guide->wazuhManager());
        $this->assertStringContainsString('WAZUH_MANAGER="wazuh.example"', $guide->wazuhDebian());
    }
}
