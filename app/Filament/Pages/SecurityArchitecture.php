<?php

namespace App\Filament\Pages;

use App\Models\CanonicalFramework;
use App\Services\Assurance\SecurityArchitectureView;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * One-page current-state security architecture view (ADR 0048).
 *
 * It groups canonical outcomes by NIST CSF 2.0 function and reports coverage.
 * Location, target state and roadmap decisions are deliberately not inferred:
 * they belong in the architecture document, treatment and acceptance records.
 */
class SecurityArchitecture extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 31;

    protected static ?string $slug = 'security-architecture';

    protected string $view = 'filament.pages.security-architecture';

    #[Url]
    public ?string $framework = null;

    public static function getNavigationLabel(): string
    {
        return __('security_architecture.navigation');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Foundations';
    }

    public function getTitle(): string
    {
        return __('security_architecture.title');
    }

    public function mount(): void
    {
        if (blank($this->framework)) {
            $this->framework = CanonicalFramework::query()
                ->orderBy('priority')
                ->value('code');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('matrix')
                ->label(__('security_architecture.open_matrix'))
                ->icon('heroicon-o-table-cells')
                ->url(fn (): string => FrameworkMatrix::getUrl(['framework' => $this->framework])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $frameworks = CanonicalFramework::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (CanonicalFramework $framework): array => [
                (string) $framework->code => "{$framework->code} — {$framework->name}",
            ])
            ->all();

        $selected = $this->framework;
        $architecture = null;

        if (filled($selected) && array_key_exists($selected, $frameworks)) {
            $architecture = app(SecurityArchitectureView::class)->build($selected);
        }

        return [
            'frameworks' => $frameworks,
            'architecture' => $architecture,
            'selectedFramework' => $selected,
        ];
    }
}
