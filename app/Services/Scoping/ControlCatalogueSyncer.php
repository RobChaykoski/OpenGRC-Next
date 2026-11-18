<?php

namespace App\Services\Scoping;

use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seed the persisted control graph from config/framework_scoping.php.
 *
 * The config is the authored source of truth. Sync upserts by code and replaces
 * each framework's control set, so removing a code from config removes that
 * mapping on the next run. Canonical controls are never deleted here: upstream
 * records may already point at them, and an orphaned link is worse than a stale
 * row that an operator can see and retire deliberately.
 */
final class ControlCatalogueSyncer
{
    /**
     * @return array{
     *     controls_created: int,
     *     controls_updated: int,
     *     frameworks_created: int,
     *     frameworks_updated: int,
     *     mappings_attached: int,
     *     mappings_detached: int,
     *     stale_controls: array<int, string>,
     *     unused_controls: array<int, string>
     * }
     */
    public function sync(): array
    {
        $catalogue = config('framework_scoping.controls', []);
        $frameworks = config('framework_scoping.frameworks', []);

        if (! is_array($catalogue) || $catalogue === []) {
            throw new RuntimeException('config/framework_scoping.php defines no canonical controls.');
        }

        $this->assertReferencesResolve($catalogue, $frameworks);

        return DB::transaction(function () use ($catalogue, $frameworks): array {
            $result = [
                'controls_created' => 0,
                'controls_updated' => 0,
                'frameworks_created' => 0,
                'frameworks_updated' => 0,
                'mappings_attached' => 0,
                'mappings_detached' => 0,
                'stale_controls' => [],
                'unused_controls' => [],
            ];

            $controls = [];

            foreach ($catalogue as $code => $definition) {
                $control = CanonicalControl::query()->firstOrNew(['code' => $code]);
                $created = ! $control->exists;

                $control->fill([
                    'name' => $definition['name'] ?? $code,
                    'category' => $definition['category'] ?? null,
                    'risk_domain' => $definition['risk_domain'] ?? null,
                    'priority' => $definition['priority'] ?? 3,
                    'evidence_expectation' => $definition['evidence'] ?? null,
                    'automation_level' => $definition['automation'] ?? null,
                    'frequency' => $definition['frequency'] ?? null,
                    'sources' => array_values($definition['sources'] ?? []),
                ]);

                $dirty = $control->isDirty();
                $control->save();

                if ($created) {
                    $result['controls_created']++;
                } elseif ($dirty) {
                    $result['controls_updated']++;
                }

                $controls[$code] = $control;
            }

            foreach ($frameworks as $code => $definition) {
                $framework = CanonicalFramework::query()->firstOrNew(['code' => $code]);
                $created = ! $framework->exists;

                $framework->fill([
                    'name' => $definition['name'] ?? $code,
                    'category' => $definition['category'] ?? null,
                    'priority' => $definition['priority'] ?? 3,
                    'regions' => array_values($definition['regions'] ?? []),
                    'industries' => array_values($definition['industries'] ?? []),
                    'labels' => array_values($definition['labels'] ?? []),
                    'drivers' => array_values($definition['drivers'] ?? []),
                    'rationale' => $definition['rationale'] ?? null,
                ]);

                $dirty = $framework->isDirty();
                $framework->save();

                if ($created) {
                    $result['frameworks_created']++;
                } elseif ($dirty) {
                    $result['frameworks_updated']++;
                }

                $mapped = collect($definition['controls'] ?? [])
                    ->filter(fn (string $controlCode) => isset($controls[$controlCode]))
                    ->map(fn (string $controlCode) => $controls[$controlCode]->getKey())
                    ->unique()
                    ->values()
                    ->all();

                $changes = $framework->controls()->sync($mapped);

                $result['mappings_attached'] += count($changes['attached']);
                $result['mappings_detached'] += count($changes['detached']);
            }

            $result['stale_controls'] = CanonicalControl::query()
                ->whereNotIn('code', array_keys($catalogue))
                ->orderBy('code')
                ->pluck('code')
                ->all();

            $referenced = collect($frameworks)
                ->flatMap(fn (array $definition) => $definition['controls'] ?? [])
                ->unique()
                ->all();

            $result['unused_controls'] = array_values(array_diff(array_keys($catalogue), $referenced));

            return $result;
        });
    }

    /**
     * Fail before writing anything when a framework points at a code that does
     * not exist. The scoping engine skips unknown codes quietly; sync must not.
     *
     * @param  array<string, array<string, mixed>>  $catalogue
     * @param  array<string, array<string, mixed>>  $frameworks
     */
    private function assertReferencesResolve(array $catalogue, array $frameworks): void
    {
        $unknown = [];

        foreach ($frameworks as $code => $definition) {
            foreach ($definition['controls'] ?? [] as $controlCode) {
                if (! isset($catalogue[$controlCode])) {
                    $unknown[] = $code.' -> '.$controlCode;
                }
            }
        }

        if ($unknown !== []) {
            throw new RuntimeException(
                'Framework mappings reference unknown canonical controls: '.implode(', ', array_unique($unknown)).'.'
            );
        }
    }
}
