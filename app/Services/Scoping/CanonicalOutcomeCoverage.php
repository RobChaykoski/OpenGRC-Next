<?php

namespace App\Services\Scoping;

use App\Enums\EvidenceSourceType;
use App\Models\CanonicalControl;
use Illuminate\Support\Facades\DB;

/**
 * What actually supports each canonical outcome.
 *
 * Controls are linked explicitly (ADR 0013). Policies count when they are linked
 * directly or when they are attached to a linked control, deduplicated per
 * outcome. Evidence counts when its control is linked: evidence itself is never
 * re-pointed, so the ledger stays append-only (ADR 0014).
 *
 * Confidence is derived, never typed in: documentation 20, manual upload 50,
 * API-collected 80, validation result 100. The best available basis wins.
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
     *     validations: int,
     *     latest_evidence_at: string|null,
     *     latest_validation_at: string|null,
     *     has_evidence: bool,
     *     monitored: bool,
     *     automated: bool,
     *     best_confidence: int,
     *     confidence_basis: string
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

        $machineEvidence = $this->machineEvidenceByOutcome($ids);

        $validations = DB::table('control_validations as validation')
            ->join('control_canonical_outcome as link', 'link.control_id', '=', 'validation.control_id')
            ->join('controls as control', 'control.id', '=', 'validation.control_id')
            ->whereIn('link.canonical_control_id', $ids)
            ->whereNull('control.deleted_at')
            ->select(
                'link.canonical_control_id',
                DB::raw('count(distinct validation.id) as aggregate'),
                DB::raw('max(validation.evaluated_at) as latest')
            )
            ->groupBy('link.canonical_control_id')
            ->get()
            ->keyBy('canonical_control_id');

        $monitored = $this->monitoredOutcomeIds($ids);

        return $outcomes
            ->map(function (CanonicalControl $outcome) use ($controls, $policies, $evidence, $machineEvidence, $validations, $monitored): array {
                $id = $outcome->getKey();
                $evidenceRow = $evidence->get($id);
                $validationRow = $validations->get($id);

                $evidenceCount = (int) ($evidenceRow->aggregate ?? 0);
                $validationCount = (int) ($validationRow->aggregate ?? 0);
                $policyCount = count($policies[$id] ?? []);

                $best = 0;
                $basis = 'none';

                if ($policyCount > 0) {
                    $best = 20;
                    $basis = 'documentation';
                }

                if ($evidenceCount > 0) {
                    $best = 50;
                    $basis = 'manual';
                }

                if (isset($machineEvidence[$id])) {
                    $best = 80;
                    $basis = 'api';
                }

                if ($validationCount > 0) {
                    $best = 100;
                    $basis = 'validation';
                }

                return [
                    'code' => $outcome->code,
                    'name' => $outcome->name,
                    'category' => $outcome->category,
                    'priority' => $outcome->priority,
                    'frameworks' => $outcome->frameworks->pluck('code')->all(),
                    'controls' => (int) ($controls[$id] ?? 0),
                    'policies' => $policyCount,
                    'evidence' => $evidenceCount,
                    'validations' => $validationCount,
                    'latest_evidence_at' => $evidenceRow->latest ?? null,
                    'latest_validation_at' => $validationRow->latest ?? null,
                    'has_evidence' => $evidenceCount > 0 || $validationCount > 0,
                    'monitored' => isset($monitored[$id]),
                    'automated' => in_array($basis, ['api', 'validation'], true),
                    'best_confidence' => $best,
                    'confidence_basis' => $basis,
                ];
            })
            ->all();
    }

    /**
     * Outcomes with at least one measurement from a connector or the ingestion
     * API. An `Other` source is manual unless the record carries a client_id,
     * which discovery snapshots do.
     *
     * @param  array<int, int>  $outcomeIds
     * @return array<int, int>
     */
    private function machineEvidenceByOutcome(array $outcomeIds): array
    {
        $machineSources = collect(EvidenceSourceType::cases())
            ->reject(fn (EvidenceSourceType $type): bool => $type === EvidenceSourceType::Other)
            ->map(fn (EvidenceSourceType $type): string => $type->value)
            ->all();

        /** @var array<int, int> $ids */
        $ids = DB::table('evidence_records as record')
            ->leftJoin('evidence_sources as source', 'source.id', '=', 'record.source_id')
            ->join('control_canonical_outcome as link', 'link.control_id', '=', 'record.control_id')
            ->join('controls as control', 'control.id', '=', 'record.control_id')
            ->whereIn('link.canonical_control_id', $outcomeIds)
            ->whereNull('control.deleted_at')
            ->where(function ($query) use ($machineSources): void {
                $query->whereNotNull('record.client_id')
                    ->orWhereIn('source.type', $machineSources);
            })
            ->distinct()
            ->pluck('link.canonical_control_id')
            ->all();

        return array_fill_keys($ids, 1);
    }

    /**
     * Outcomes with at least one enabled validation rule on a linked control.
     *
     * @param  array<int, int>  $outcomeIds
     * @return array<int, int>
     */
    private function monitoredOutcomeIds(array $outcomeIds): array
    {
        /** @var array<int, int> $ids */
        $ids = DB::table('control_validation_rules as rule')
            ->join('control_canonical_outcome as link', 'link.control_id', '=', 'rule.control_id')
            ->join('controls as control', 'control.id', '=', 'rule.control_id')
            ->where('rule.enabled', true)
            ->whereIn('link.canonical_control_id', $outcomeIds)
            ->whereNull('control.deleted_at')
            ->distinct()
            ->pluck('link.canonical_control_id')
            ->all();

        return array_fill_keys($ids, 1);
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
