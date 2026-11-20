<?php

namespace App\Filament\Pages;

use App\Enums\CrosswalkStatus;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use App\Services\Controls\CrosswalkExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Approved crosswalks between two imported frameworks.
 *
 * A mapping is a reviewed judgement, not equivalence and not evidence. The
 * report shows where approved mappings exist and where a control still has none.
 */
class CrosswalkReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 44;

    protected static ?string $slug = 'crosswalk-report';

    protected string $view = 'filament.pages.crosswalk-report';

    #[Url]
    public ?string $source = null;

    #[Url]
    public ?string $target = null;

    public static function getNavigationLabel(): string
    {
        return 'Crosswalk report';
    }

    public function getTitle(): string
    {
        return 'Framework crosswalk report';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => is_numeric($this->source)
                    && is_numeric($this->target)
                    && $this->source !== $this->target)
                ->action(fn () => $this->export()),
        ];
    }

    public function export(): ?StreamedResponse
    {
        $source = is_numeric($this->source) ? Standard::query()->find((int) $this->source) : null;
        $target = is_numeric($this->target) ? Standard::query()->find((int) $this->target) : null;

        if (! $source instanceof Standard || ! $target instanceof Standard || $source->is($target)) {
            Notification::make()
                ->title('Choose two different frameworks first.')
                ->warning()
                ->send();

            return null;
        }

        $csv = app(CrosswalkExporter::class)->csv($source, $target);

        return response()->streamDownload(
            fn () => print $csv,
            "crosswalk-{$source->code}-{$target->code}.csv",
            ['Content-Type' => 'text/csv'],
        );
    }

    public function mount(): void
    {
        $standards = Standard::query()->orderBy('name')->limit(2)->pluck('id');

        $this->source ??= $standards->get(0) !== null ? (string) $standards->get(0) : null;
        $this->target ??= $standards->get(1) !== null ? (string) $standards->get(1) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $standards = Standard::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Standard $standard): array => [
                (string) $standard->getKey() => "{$standard->code} — {$standard->name}",
            ])
            ->all();

        $sourceId = is_numeric($this->source) ? (int) $this->source : null;
        $targetId = is_numeric($this->target) ? (int) $this->target : null;

        $sourceStandard = $sourceId !== null ? Standard::query()->find($sourceId) : null;
        $targetStandard = $targetId !== null ? Standard::query()->find($targetId) : null;

        $base = ControlCrosswalk::query()
            ->with(['sourceControl.standard', 'targetControl.standard', 'reviewer'])
            ->where(function (Builder $query) use ($sourceId, $targetId): void {
                $query->where(function (Builder $query) use ($sourceId, $targetId): void {
                    $query->whereHas('sourceControl', fn (Builder $control): Builder => $control->where('standard_id', $sourceId))
                        ->whereHas('targetControl', fn (Builder $control): Builder => $control->where('standard_id', $targetId));
                })->orWhere(function (Builder $query) use ($sourceId, $targetId): void {
                    $query->whereHas('sourceControl', fn (Builder $control): Builder => $control->where('standard_id', $targetId))
                        ->whereHas('targetControl', fn (Builder $control): Builder => $control->where('standard_id', $sourceId));
                });
            });

        $hasPair = $sourceId !== null && $targetId !== null && $sourceId !== $targetId;

        $rows = $hasPair
            ? (clone $base)->where('status', CrosswalkStatus::Approved->value)->get()
                ->map(function (ControlCrosswalk $crosswalk) use ($sourceId): array {
                    $leftIsSource = (int) $crosswalk->sourceControl?->standard_id === $sourceId;

                    return [
                        'left' => $leftIsSource ? $crosswalk->sourceControl : $crosswalk->targetControl,
                        'right' => $leftIsSource ? $crosswalk->targetControl : $crosswalk->sourceControl,
                        'confidence' => $crosswalk->confidence,
                        'reviewer' => $crosswalk->reviewer?->name,
                        'reviewed_at' => $crosswalk->reviewed_at,
                    ];
                })
                ->sortBy(fn (array $row): string => (string) $row['left']?->code)
                ->values()
            : collect();

        $draftCount = $hasPair
            ? (clone $base)->where('status', CrosswalkStatus::Draft->value)->count()
            : 0;

        $leftIds = $rows->map(fn (array $row): int => (int) $row['left']?->getKey())->unique();
        $rightIds = $rows->map(fn (array $row): int => (int) $row['right']?->getKey())->unique();

        $leftTotal = $sourceStandard !== null ? Control::query()->where('standard_id', $sourceStandard->getKey())->count() : 0;
        $rightTotal = $targetStandard !== null ? Control::query()->where('standard_id', $targetStandard->getKey())->count() : 0;

        return [
            'standards' => $standards,
            'sourceStandard' => $sourceStandard,
            'targetStandard' => $targetStandard,
            'hasPair' => $hasPair,
            'rows' => $rows,
            'approvedCount' => $rows->count(),
            'draftCount' => $draftCount,
            'leftTotal' => $leftTotal,
            'rightTotal' => $rightTotal,
            'leftUnmapped' => max(0, $leftTotal - $leftIds->count()),
            'rightUnmapped' => max(0, $rightTotal - $rightIds->count()),
        ];
    }
}
