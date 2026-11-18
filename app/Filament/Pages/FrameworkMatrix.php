<?php

namespace App\Filament\Pages;

use App\Models\AssuranceSnapshot;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Services\Scoping\AssuranceScore;
use App\Services\Scoping\CanonicalOutcomeCoverage;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of one framework's required outcomes and what supports them.
 *
 * It reports links and evidence, not compliance. An outcome with no reviewed
 * control link is shown as work to do, never as a zero to clear by guessing.
 */
class FrameworkMatrix extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'assurance-matrix';

    protected string $view = 'filament.pages.framework-matrix';

    public ?string $framework = null;

    /**
     * @var array<string, string>
     */
    public array $frameworks = [];

    /**
     * Per-request coverage cache, keyed by canonical outcome code.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $coverage = null;

    public static function getNavigationLabel(): string
    {
        return __('assurance.navigation.label');
    }

    public static function getNavigationGroup(): string
    {
        return __('assurance.navigation.group');
    }

    public function getTitle(): string
    {
        return __('assurance.page.title');
    }

    public function mount(): void
    {
        $this->frameworks = CanonicalFramework::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();

        $this->framework = array_key_first($this->frameworks);
    }

    public function updatedFramework(): void
    {
        $this->coverage = null;
        $this->resetTable();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function score(): ?array
    {
        if (blank($this->framework)) {
            return null;
        }

        return app(AssuranceScore::class)->forFramework((string) $this->framework);
    }

    /**
     * @return array{change: int, days: int}|null
     */
    public function trend(): ?array
    {
        if (blank($this->framework)) {
            return null;
        }

        $snapshots = AssuranceSnapshot::query()
            ->where('framework_code', $this->framework)
            ->where('snapshot_date', '>=', now()->subDays(30)->startOfDay())
            ->orderBy('snapshot_date')
            ->get();

        if ($snapshots->count() < 2) {
            return null;
        }

        $first = $snapshots->first();
        $last = $snapshots->last();

        return [
            'change' => $last->score - $first->score,
            'days' => (int) $first->snapshot_date->diffInDays($last->snapshot_date),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->matrixQuery())
            ->defaultSort('priority')
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label(__('assurance.columns.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label(__('assurance.columns.outcome'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('category')
                    ->label(__('assurance.columns.category'))
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('assurance.not_set')),

                TextColumn::make('priority')
                    ->label(__('assurance.columns.priority'))
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => __('assurance.priority.'.$this->priorityKey($state)))
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'danger',
                        2 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('automation_level')
                    ->label(__('assurance.columns.automation'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : __('assurance.automation.'.$state))
                    ->color(fn (?string $state): string => match ($state) {
                        'high' => 'success',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->placeholder(__('assurance.not_set')),

                TextColumn::make('controls_count')
                    ->label(__('assurance.columns.controls'))
                    ->state(fn (CanonicalControl $record): int => $this->row($record)['controls'])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('policies_count')
                    ->label(__('assurance.columns.policies'))
                    ->state(fn (CanonicalControl $record): int => $this->row($record)['policies'])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('evidence_count')
                    ->label(__('assurance.columns.evidence'))
                    ->state(fn (CanonicalControl $record): int => $this->row($record)['evidence'])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->tooltip(function (CanonicalControl $record): ?string {
                        $latest = $this->row($record)['latest_evidence_at'];

                        return $latest === null ? null : __('assurance.latest_evidence', ['date' => $latest]);
                    }),

                TextColumn::make('coverage_status')
                    ->label(__('assurance.columns.status'))
                    ->state(fn (CanonicalControl $record): string => $this->status($record))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('assurance.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'covered' => 'success',
                        'no_evidence' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('assurance.columns.category'))
                    ->options(fn (): array => CanonicalControl::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),

                Filter::make('gaps')
                    ->label(__('assurance.filters.gaps'))
                    ->schema([
                        Toggle::make('only_gaps')
                            ->label(__('assurance.filters.only_gaps'))
                            ->helperText(__('assurance.filters.only_gaps_helper')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => ($data['only_gaps'] ?? false)
                        ? $query->where(function (Builder $query): void {
                            $query
                                ->whereDoesntHave('controls')
                                ->orWhereDoesntHave('controls', fn (Builder $controls) => $controls->whereHas('evidenceRecords'));
                        })
                        : $query),
            ])
            ->emptyStateHeading(__('assurance.empty.heading'))
            ->emptyStateDescription(__('assurance.empty.description'));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CanonicalControl $outcome): array
    {
        $this->coverage ??= collect(app(CanonicalOutcomeCoverage::class)->summary($this->framework))
            ->keyBy('code')
            ->all();

        return $this->coverage[$outcome->code] ?? [
            'controls' => 0,
            'policies' => 0,
            'evidence' => 0,
            'latest_evidence_at' => null,
        ];
    }

    private function status(CanonicalControl $outcome): string
    {
        $row = $this->row($outcome);

        if ($row['controls'] === 0) {
            return 'no_control';
        }

        return $row['has_evidence'] ? 'covered' : 'no_evidence';
    }

    private function matrixQuery(): Builder
    {
        if (blank($this->framework)) {
            // No catalogue yet: an empty matrix is honest, showing every outcome
            // would imply the selected framework requires all of them.
            return CanonicalControl::query()->whereRaw('1 = 0');
        }

        return CanonicalControl::query()
            ->whereHas('frameworks', fn (Builder $framework) => $framework->where('code', $this->framework));
    }

    private function priorityKey(int $priority): string
    {
        return match ($priority) {
            1 => 'high',
            2 => 'medium',
            default => 'low',
        };
    }
}
