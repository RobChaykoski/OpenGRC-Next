<?php

namespace App\Services\Scoping;

use App\Models\DataClassification;
use Illuminate\Support\Collection;

final class DataClassificationCatalog
{
    /**
     * Labels available to an organisation in an industry. Global labels and the
     * industry presets are included; custom labels are included when they are
     * global or scoped to the same industry.
     *
     * @return Collection<int, DataClassification>
     */
    public function forIndustry(?string $industry): Collection
    {
        return DataClassification::query()
            ->forIndustry($industry)
            ->orderBy('level')
            ->orderBy('label')
            ->get();
    }

    /**
     * @return array<string, string> code => label
     */
    public function options(?string $industry): array
    {
        return $this->forIndustry($industry)
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * Validate a profile's selected labels against the industry catalogue.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string> unknown codes
     */
    public function unknownCodes(array $codes, ?string $industry): array
    {
        $known = $this->forIndustry($industry)->pluck('code')->all();

        return array_values(array_diff($codes, $known));
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, array{code: string, label: string, level: string, rank: int}>
     */
    public function describe(array $codes, ?string $industry): array
    {
        return $this->forIndustry($industry)
            ->whereIn('code', $codes)
            ->map(fn (DataClassification $label) => [
                'code' => $label->code,
                'label' => $label->label,
                'level' => $label->level,
                'rank' => $label->levelRank(),
            ])
            ->values()
            ->all();
    }
}
