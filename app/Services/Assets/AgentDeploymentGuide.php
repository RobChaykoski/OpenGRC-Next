<?php

namespace App\Services\Assets;

use App\Enums\EvidenceSourceType;
use App\Models\EvidenceSource;

/**
 * Copyable deployment commands for the missed-assets report.
 *
 * Commands never contain a registration password, API token or enrollment
 * secret. Those come from the operator's secret store and are deliberately
 * left as placeholders.
 */
class AgentDeploymentGuide
{
    /**
     * @return array<string, string|null>
     */
    public function commands(): array
    {
        return [
            'wazuh_debian' => $this->wazuhDebian(),
            'wazuh_rhel' => $this->wazuhRhel(),
            'wazuh_windows' => $this->wazuhWindows(),
            'patchmon' => $this->patchmon(),
            'fleet' => $this->fleet(),
        ];
    }

    public function wazuhManager(): ?string
    {
        $configured = trim((string) config('agent_deployment.wazuh.manager', ''));

        if ($configured !== '') {
            return $configured;
        }

        $source = EvidenceSource::query()
            ->where('type', EvidenceSourceType::Wazuh)
            ->first();

        $baseUrl = $source?->baseUrl();

        if ($baseUrl === null) {
            return null;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    public function wazuhDebian(): ?string
    {
        $manager = $this->wazuhManager();

        if ($manager === null) {
            return null;
        }

        $group = $this->shell(config('agent_deployment.wazuh.agent_group', 'default'));
        $repository = rtrim((string) config('agent_deployment.wazuh.apt_repository'), '/');
        $manager = $this->shell($manager);

        return <<<BASH
# Debian / Ubuntu
curl -fsSL https://packages.wazuh.com/key/GPG-KEY-WAZUH | sudo gpg --dearmor -o /usr/share/keyrings/wazuh.gpg
echo "deb [signed-by=/usr/share/keyrings/wazuh.gpg] {$repository}/ stable main" | sudo tee /etc/apt/sources.list.d/wazuh.list
sudo apt-get update
sudo env WAZUH_MANAGER="{$manager}" WAZUH_REGISTRATION_SERVER="{$manager}" WAZUH_AGENT_GROUP="{$group}" apt-get install -y wazuh-agent
sudo systemctl daemon-reload
sudo systemctl enable --now wazuh-agent
BASH;
    }

    public function wazuhRhel(): ?string
    {
        $manager = $this->wazuhManager();

        if ($manager === null) {
            return null;
        }

        $group = $this->shell(config('agent_deployment.wazuh.agent_group', 'default'));
        $repository = rtrim((string) config('agent_deployment.wazuh.yum_repository'), '/');
        $manager = $this->shell($manager);

        return <<<BASH
# RHEL / Rocky / AlmaLinux
sudo rpm --import https://packages.wazuh.com/key/GPG-KEY-WAZUH
sudo tee /etc/yum.repos.d/wazuh.repo >/dev/null <<'EOF'
[wazuh]
gpgcheck=1
gpgkey=https://packages.wazuh.com/key/GPG-KEY-WAZUH
enabled=1
name=EL-\$releasever - Wazuh
baseurl={$repository}/
protect=1
EOF
sudo env WAZUH_MANAGER="{$manager}" WAZUH_REGISTRATION_SERVER="{$manager}" WAZUH_AGENT_GROUP="{$group}" yum install -y wazuh-agent
sudo systemctl enable --now wazuh-agent
BASH;
    }

    public function wazuhWindows(): ?string
    {
        $manager = $this->wazuhManager();

        if ($manager === null) {
            return null;
        }

        $group = $this->shell(config('agent_deployment.wazuh.agent_group', 'default'));
        $manager = $this->shell($manager);
        $msi = trim((string) config('agent_deployment.wazuh.windows_msi_url', ''));
        $msi = $msi !== '' ? $msi : '<set WAZUH_WINDOWS_MSI_URL>';

        return <<<POWERSHELL
# Windows PowerShell (Administrator)
\$msi = "\$env:TEMP\wazuh-agent.msi"
Invoke-WebRequest -Uri "{$msi}" -OutFile \$msi
msiexec.exe /i \$msi /q WAZUH_MANAGER="{$manager}" WAZUH_REGISTRATION_SERVER="{$manager}" WAZUH_AGENT_GROUP="{$group}"
Start-Service WazuhSvc
POWERSHELL;
    }

    public function patchmon(): ?string
    {
        $template = trim((string) config('agent_deployment.patchmon.install_command', ''));

        if ($template === '') {
            return null;
        }

        return str_replace(
            ['{{server_url}}', '{{agent_group}}'],
            [
                $this->shell(config('agent_deployment.patchmon.server_url', '')),
                $this->shell(config('agent_deployment.wazuh.agent_group', 'default')),
            ],
            $template
        );
    }

    public function fleet(): ?string
    {
        $url = trim((string) config('agent_deployment.fleet.url', ''));

        if ($url === '') {
            return null;
        }

        return 'fleetctl package --type=deb --fleet-url='.$this->shell($url)." --enroll-secret='<from-secret-store>'";
    }

    /**
     * @return array<int, string>
     */
    public function notes(): array
    {
        return [
            'Use the manager address that matches the Wazuh API certificate SAN; the default certificate often expects a hostname.',
            'Registration passwords, API keys and enrollment secrets come from your secret store. Do not paste them into tickets or screenshots.',
            'Allow the agent to reach the manager on the agent-enrollment and event ports; allow OpenGRC to reach the API on 55000.',
            'Verify the host appears in Wazuh and PatchMon, and that a fresh measurement reaches OpenGRC after the next collection.',
            'Install agents only on hosts in the approved scope; do not enrol personal or out-of-scope devices.',
        ];
    }

    private function shell(mixed $value): string
    {
        return str_replace(['"', "\n", "\r"], '', (string) $value);
    }
}
