<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Services\Assets\AgentCoverageReport;
use App\Services\Assets\AgentDeploymentGuide;
use Filament\Pages\Page;

/**
 * Missed assets and agent coverage.
 *
 * The report is read-only. Deployment commands are only rendered for admins
 * and editors, and never contain a password, token or enrollment secret.
 */
class MissedAssets extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'missed-assets';

    protected string $view = 'filament.pages.missed-assets';

    /** @var array<string, mixed> */
    public array $report = [];

    /** @var array<string, string|null> */
    public array $commands = [];

    /** @var array<int, string> */
    public array $notes = [];

    public string $gapFilter = 'all';

    public string $search = '';

    public function mount(AgentCoverageReport $report, AgentDeploymentGuide $guide): void
    {
        $this->report = $report->build();
        $this->notes = $guide->notes();

        if ($this->canDeploy()) {
            $this->commands = $guide->commands();
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('coverage.navigation');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('discovery.navigation.group');
    }

    public function getTitle(): string
    {
        return __('coverage.title');
    }

    public function canDeploy(): bool
    {
        $user = auth()->user();

        return $user !== null
            && in_array($user->role(), [UserRole::Admin, UserRole::Editor], true);
    }

    /**
     * @return array<string, string>
     */
    public function gapOptions(): array
    {
        return [
            'undocumented' => __('coverage.gaps.undocumented'),
            'unowned' => __('coverage.gaps.unowned'),
            'missing_wazuh' => __('coverage.gaps.missing_wazuh'),
            'missing_patchmon' => __('coverage.gaps.missing_patchmon'),
            'unscanned' => __('coverage.gaps.unscanned'),
            'stale_wazuh' => __('coverage.gaps.stale_wazuh'),
            'stale_patchmon' => __('coverage.gaps.stale_patchmon'),
            'stale_discovery' => __('coverage.gaps.stale_discovery'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filteredRows(): array
    {
        $search = strtolower(trim($this->search));
        $rows = $this->report['rows'] ?? [];

        return array_values(array_filter($rows, function (array $row) use ($search): bool {
            if ($this->gapFilter !== 'all' && ! in_array($this->gapFilter, $row['gaps'], true)) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains(strtolower((string) $row['asset_name']), $search);
        }));
    }
}
