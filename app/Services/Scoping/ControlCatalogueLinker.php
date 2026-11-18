<?php

namespace App\Services\Scoping;

use App\Models\CanonicalControl;
use App\Models\Control;
use App\Models\Standard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Apply the curated control-to-canonical links in
 * config/canonical_control_links.php.
 *
 * Links written from config carry `source = config` and are replaced on every
 * run for each standard the config covers, so removing an entry removes the
 * link. Links made by people in the UI carry `source = manual` and are never
 * touched. Nothing here guesses a match from titles or code prefixes.
 */
final class ControlCatalogueLinker
{
    /**
     * @return array{
     *     standards_linked: int,
     *     standards_missing: array<int, string>,
     *     controls_missing: array<int, string>,
     *     links_created: int,
     *     links_removed: int,
     *     controls_total: int,
     *     controls_linked: int,
     *     controls_unlinked: int
     * }
     */
    public function link(): array
    {
        $links = config('canonical_control_links', []);

        if (! is_array($links) || $links === []) {
            throw new RuntimeException('config/canonical_control_links.php defines no curated links.');
        }

        /** @var array<string, int> $canonicalIds */
        $canonicalIds = CanonicalControl::query()->pluck('id', 'code')->all();

        if ($canonicalIds === []) {
            throw new RuntimeException('The canonical catalogue is empty. Run opengrc:catalog:sync first.');
        }

        $this->assertCanonicalCodesExist($links, $canonicalIds);

        return DB::transaction(function () use ($links, $canonicalIds): array {
            $result = [
                'standards_linked' => 0,
                'standards_missing' => [],
                'controls_missing' => [],
                'links_created' => 0,
                'links_removed' => 0,
                'controls_total' => Control::query()->count(),
                'controls_linked' => 0,
                'controls_unlinked' => 0,
            ];

            foreach ($links as $standardCode => $mappings) {
                $standard = Standard::query()->where('code', $standardCode)->first();

                if ($standard === null) {
                    $result['standards_missing'][] = $standardCode;

                    continue;
                }

                $result['standards_linked']++;

                /** @var array<int, int> $controlIds */
                $controlIds = Control::query()
                    ->where('standard_id', $standard->getKey())
                    ->pluck('id')
                    ->all();

                if ($controlIds === []) {
                    $result['standards_missing'][] = $standardCode;

                    continue;
                }

                $existing = DB::table('control_canonical_outcome')
                    ->whereIn('control_id', $controlIds)
                    ->get(['id', 'control_id', 'canonical_control_id', 'source']);

                $knownPairs = [];
                $configPairIds = [];

                foreach ($existing as $row) {
                    $pair = $row->control_id.':'.$row->canonical_control_id;
                    $knownPairs[$pair] = true;

                    if ($row->source === 'config') {
                        $configPairIds[$pair] = (int) $row->id;
                    }
                }

                $desired = [];

                foreach ($mappings as $controlCode => $canonicalCodes) {
                    $matched = Control::query()
                        ->where('standard_id', $standard->getKey())
                        ->where('code', $controlCode)
                        ->pluck('id');

                    if ($matched->isEmpty()) {
                        $result['controls_missing'][] = $standardCode.'/'.$controlCode;

                        continue;
                    }

                    foreach ($matched as $controlId) {
                        foreach ($canonicalCodes as $canonicalCode) {
                            $desired[$controlId.':'.$canonicalIds[$canonicalCode]] = [
                                'control_id' => (int) $controlId,
                                'canonical_control_id' => $canonicalIds[$canonicalCode],
                                'source' => 'config',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                $toInsert = array_values(array_diff_key($desired, $knownPairs));

                foreach (array_chunk($toInsert, 500) as $chunk) {
                    DB::table('control_canonical_outcome')->insert($chunk);
                }

                $result['links_created'] += count($toInsert);

                $toDelete = array_values(array_diff_key($configPairIds, $desired));

                if ($toDelete !== []) {
                    DB::table('control_canonical_outcome')->whereIn('id', $toDelete)->delete();
                    $result['links_removed'] += count($toDelete);
                }
            }

            $result['controls_linked'] = DB::table('control_canonical_outcome')
                ->distinct()
                ->count('control_id');
            $result['controls_unlinked'] = Control::query()
                ->whereDoesntHave('canonicalOutcomes')
                ->count();

            return $result;
        });
    }

    /**
     * @param  array<string, array<string, array<int, string>>>  $links
     * @param  array<string, int>  $canonicalIds
     */
    private function assertCanonicalCodesExist(array $links, array $canonicalIds): void
    {
        $unknown = [];

        foreach ($links as $standardCode => $mappings) {
            foreach ($mappings as $controlCode => $canonicalCodes) {
                foreach ($canonicalCodes as $canonicalCode) {
                    if (! isset($canonicalIds[$canonicalCode])) {
                        $unknown[] = $standardCode.'/'.$controlCode.' -> '.$canonicalCode;
                    }
                }
            }
        }

        if ($unknown !== []) {
            throw new RuntimeException(
                'Curated links reference unknown canonical outcomes: '.implode(', ', array_unique($unknown)).'.'
            );
        }
    }
}
