<?php

namespace App\Services\Scoping;

use App\Models\CanonicalControl;
use Illuminate\Support\Facades\DB;

/**
 * What actually supports each canonical outcome.
 *
 * Controls are linked explicitly (ADR 0013). Policies count when they are linked
 * directly or when they are attached to a linked control, deduplicated per
 * outcome. Evidence counts when its control is linked: evidence itself is never
 * re-pointed, so the ledger stays append-only (ADR 0014).
 */
final class CanonicalOutcomeCoverage
{
    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     category: string|null,
     *     priority: int,
     *     frameworks: array<int, string>,
     *     controls: int,
     *     policies: int,
     *     evidence: int,
     *     latest_evidence_at: string|null
     * }>
     */
    public function summary(?string $frameworkCode = null): array
    {
        $outcomes = CanonicalControl::query()
            ->with('frameworks:id,code')
            ->when(
                $frameworkCode,
                fn ($query) => $query->whereHas(
                    'frameworks',
                    fn ($framework) => $framework->where('code', $frameworkCode)
                )
            )
            ->orderBy('code')
            ->get();

        if ($outcomes->isEmpty()) {
            return [];
        }

        $ids = $outcomes->modelKeys();

        $controls = DB::table('control_canonical_outcome as link')
            ->join('controls as control', 'control.id', '=', 'link.control_id')
            ->whereIn('link.canonical_control_id', $ids)
            ->whereNull('control.deleted_at')
            ->select('link.canonical_control_id', DB::raw('count(distinct link.control_id) as aggregate'))
            ->groupBy('link.canonical_control_id')
            ->pluck('aggregate', 'canonical_control_id');

        $policies = $this->policiesByOutcome($ids);

        $evidence = DB::table('evidence_records as record')
            ->join('control_canonical_outcome as link', 'link.control_id', '=', 'record.control_id')
            ->join('controls as control', 'control.id', '=', 'record.control_id')
            ->whereIn('link.canonical_control_id', $ids)
            ->whereNull('control.deleted_at')
            ->select(
                'link.canonical_control_id',
                DB::raw('count(distinct record.id) as aggregate'),
                DB::raw('max(record.collected_at) as latest')
            )
            ->groupBy('link.canonical_control_id')
            ->get()
            ->keyBy('canonical_control_id');

        return $outcomes
            ->map(function (CanonicalControl $outcome) use ($controls, $policies, $evidence): array {
                $evidenceRow = $evidence->get($outcome->getKey());

                return [
                    'code' => $outcome->code,
                    'name' => $outcome->name,
                    'category' => $outcome->category,
                    'priority' => $outcome->priority,
                    'frameworks' => $outcome->frameworks->pluck('code')->all(),
                    'controls' => (int) ($controls[$outcome->getKey()] ?? 0),
                    'policies' => count($policies[$outcome->getKey()] ?? []),
                    'evidence' => (int) ($evidenceRow->aggregate ?? 0),
                    'latest_evidence_at' => $evidenceRow->latest ?? null,
                ];
            })
            ->all();
    }

    /**
     * Policy IDs per outcome, from direct links and from linked controls,
     * deduplicated so a policy counted twice is still one policy.
     *
     * @param  array<int, int>  $outcomeIds
     * @return array<int, array<int, int>>
     */
    private function policiesByOutcome(array $outcomeIds): array
    {
        $direct = DB::table('policy_canonical_outcome as link')
            ->join('policies as policy', 'policy.id', '=', 'link.policy_id')
            ->whereIn('link.canonical_control_id', $outcomeIds)
            ->whereNull('policy.deleted_at')
            ->select('link.canonical_control_id', 'policy.id')
            ->distinct()
            ->get();

        $viaControls = DB::table('control_policy as link')
            ->join('control_canonical_outcome as outcome', 'outcome.control_id', '=', 'link.control_id')
            ->join('policies as policy', 'policy.id', '=', 'link.policy_id')
            ->join('controls as control', 'control.id', '=', 'link.control_id')
            ->whereIn('outcome.canonical_control_id', $outcomeIds)
            ->whereNull('policy.deleted_at')
            ->whereNull('control.deleted_at')
            ->select('outcome.canonical_control_id', 'policy.id')
            ->distinct()
            ->get();

        $grouped = [];

        foreach ($direct->concat($viaControls) as $row) {
            $grouped[$row->canonical_control_id][$row->id] = (int) $row->id;
        }

        return $grouped;
    }
}
