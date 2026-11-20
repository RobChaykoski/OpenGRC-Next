<?php

namespace App\Services\Controls;

use App\Enums\CrosswalkStatus;
use App\Models\ControlCrosswalk;
use App\Models\Standard;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export reviewed crosswalks between two frameworks as CSV.
 *
 * The export is for review and audit hand-off. It is not a compliance report and
 * it does not include evidence. Cells that begin with a spreadsheet formula
 * character are escaped so a rationale cannot become a formula.
 */
class CrosswalkExporter
{
    public const HEADERS = [
        'reference',
        'source_framework',
        'source_control',
        'source_title',
        'target_framework',
        'target_control',
        'target_title',
        'confidence',
        'status',
        'rationale',
        'reviewer',
        'reviewed_at',
    ];

    /**
     * @return array<int, array<int, string>>
     */
    public function rows(Standard $source, Standard $target, bool $includeDrafts = false): array
    {
        $query = ControlCrosswalk::query()
            ->with(['sourceControl.standard', 'targetControl.standard', 'reviewer'])
            ->where(function (Builder $query) use ($source, $target): void {
                $query->where(function (Builder $query) use ($source, $target): void {
                    $query->whereHas('sourceControl', fn (Builder $control): Builder => $control->where('standard_id', $source->getKey()))
                        ->whereHas('targetControl', fn (Builder $control): Builder => $control->where('standard_id', $target->getKey()));
                })->orWhere(function (Builder $query) use ($source, $target): void {
                    $query->whereHas('sourceControl', fn (Builder $control): Builder => $control->where('standard_id', $target->getKey()))
                        ->whereHas('targetControl', fn (Builder $control): Builder => $control->where('standard_id', $source->getKey()));
                });
            });

        if (! $includeDrafts) {
            $query->where('status', CrosswalkStatus::Approved->value);
        }

        return $query->get()
            ->map(function (ControlCrosswalk $crosswalk) use ($source): array {
                $leftIsSource = (int) $crosswalk->sourceControl?->standard_id === (int) $source->getKey();
                $left = $leftIsSource ? $crosswalk->sourceControl : $crosswalk->targetControl;
                $right = $leftIsSource ? $crosswalk->targetControl : $crosswalk->sourceControl;

                return [
                    $crosswalk->reference(),
                    (string) $left?->standard?->code,
                    (string) $left?->code,
                    (string) $left?->title,
                    (string) $right?->standard?->code,
                    (string) $right?->code,
                    (string) $right?->title,
                    $crosswalk->confidence?->getLabel() ?? '',
                    $crosswalk->status?->getLabel() ?? '',
                    (string) $crosswalk->rationale,
                    (string) $crosswalk->reviewer?->name,
                    $crosswalk->reviewed_at?->toDateString() ?? '',
                ];
            })
            ->sortBy(fn (array $row): string => $row[2])
            ->values()
            ->all();
    }

    public function csv(Standard $source, Standard $target, bool $includeDrafts = false): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::HEADERS);

        foreach ($this->rows($source, $target, $includeDrafts) as $row) {
            fputcsv($handle, array_map(fn (string $value): string => $this->cell($value), $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    public function write(string $path, Standard $source, Standard $target, bool $includeDrafts = false): int
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $this->csv($source, $target, $includeDrafts));

        return count($this->rows($source, $target, $includeDrafts));
    }

    /**
     * Escape a value that a spreadsheet would treat as a formula.
     */
    protected function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
