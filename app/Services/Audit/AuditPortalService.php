<?php

namespace App\Services\Audit;

use App\Enums\Effectiveness;
use App\Enums\FindingStatus;
use App\Enums\WorkflowStatus;
use App\Models\Audit;
use App\Models\AuditItem;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\EvidenceRecord;
use App\Models\Finding;
use App\Models\Implementation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles the read-only view of one audit for the auditor portal (ADR 0045).
 *
 * The portal is an index, not an export. It shows the audit's own findings,
 * control and implementation identifiers, and evidence metadata with hashes. It
 * never reads an evidence payload, never exposes an attachment, and never shows
 * a licensed framework's control text. Those stay in OpenGRC behind access
 * control, exactly as the evidence pack does (ADR 0016).
 */
final class AuditPortalService
{
    private const EVIDENCE_PER_CONTROL = 5;

    private const VALIDATIONS_PER_CONTROL = 3;

    /**
     * @return array{
     *     audit: Audit,
     *     summary: array<string, int>,
     *     items: array<int, array<string, mixed>>,
     *     generated_at: Carbon
     * }
     */
    public function build(Audit $audit): array
    {
        $audit->load(['manager', 'auditItems.auditable']);

        /** @var Collection<int, AuditItem> $auditItems */
        $auditItems = $audit->auditItems->sortBy('id')->values();

        $implementations = $this->implementationsFor($auditItems);

        $controlIds = $auditItems
            ->flatMap(fn (AuditItem $item): array => $this->controlIdsFor($item, $implementations))
            ->unique()
            ->values()
            ->all();

        $evidence = $this->evidenceByControl($controlIds);
        $validations = $this->validationsByControl($controlIds);
        $findings = $this->openFindingsByControl($controlIds);

        $items = $auditItems->map(function (AuditItem $item) use ($implementations, $evidence, $validations, $findings): array {
            $auditable = $item->auditable;
            $itemControlIds = $this->controlIdsFor($item, $implementations);

            $isControl = $auditable instanceof Control;
            $isImplementation = $auditable instanceof Implementation;

            return [
                'type' => $isImplementation ? 'implementation' : 'control',
                'code' => $isControl || $isImplementation ? $auditable->code : null,
                'title' => $isControl || $isImplementation ? $auditable->title : null,
                'details' => $isImplementation ? $auditable->details : null,
                'status' => $item->status?->getLabel() ?? '—',
                'effectiveness' => $item->effectiveness?->getLabel() ?? '—',
                'applicability' => $item->applicability?->getLabel() ?? '—',
                'notes' => $item->auditor_notes,
                'last_tested_at' => $isImplementation ? $auditable->last_tested_at : null,
                'next_test_at' => $isImplementation ? $auditable->next_test_at : null,
                'evidence' => $this->mergeByControl($itemControlIds, $evidence),
                'validations' => $this->mergeByControl($itemControlIds, $validations),
                'findings' => $this->mergeByControl($itemControlIds, $findings),
            ];
        })->all();

        return [
            'audit' => $audit,
            'summary' => $this->summary($auditItems, $items),
            'items' => $items,
            'generated_at' => now(),
        ];
    }

    /**
     * @param  Collection<int, AuditItem>  $items
     * @return Collection<int, Implementation>
     */
    private function implementationsFor(Collection $items): Collection
    {
        $ids = $items
            ->filter(fn (AuditItem $item): bool => $item->auditable_type === Implementation::class)
            ->pluck('auditable_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Implementation::query()
            ->with('controls:id')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Implementation>  $implementations
     * @return array<int, int>
     */
    private function controlIdsFor(AuditItem $item, Collection $implementations): array
    {
        $auditable = $item->auditable;

        if ($auditable instanceof Control) {
            return [(int) $auditable->getKey()];
        }

        if ($auditable instanceof Implementation) {
            $implementation = $implementations->get($auditable->getKey()) ?? $auditable;

            return $implementation->controls->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return [];
    }

    /**
     * @param  array<int, int>  $controlIds
     * @return Collection<int, Collection<int, EvidenceRecord>>
     */
    private function evidenceByControl(array $controlIds): Collection
    {
        if ($controlIds === []) {
            return collect();
        }

        // Never select payload: the portal shows that evidence exists and what
        // its hash is, not the measurement itself.
        return EvidenceRecord::query()
            ->select(['id', 'control_id', 'source_id', 'evidence_type', 'status', 'collected_at', 'payload_sha256'])
            ->with('source:id,name')
            ->whereIn('control_id', $controlIds)
            ->orderByDesc('collected_at')
            ->get()
            ->groupBy('control_id')
            ->map(fn (Collection $records): Collection => $records->take(self::EVIDENCE_PER_CONTROL)->values());
    }

    /**
     * @param  array<int, int>  $controlIds
     * @return Collection<int, Collection<int, ControlValidation>>
     */
    private function validationsByControl(array $controlIds): Collection
    {
        if ($controlIds === []) {
            return collect();
        }

        return ControlValidation::query()
            ->select(['id', 'control_id', 'status', 'evaluated_at'])
            ->whereIn('control_id', $controlIds)
            ->orderByDesc('evaluated_at')
            ->get()
            ->groupBy('control_id')
            ->map(fn (Collection $results): Collection => $results->take(self::VALIDATIONS_PER_CONTROL)->values());
    }

    /**
     * @param  array<int, int>  $controlIds
     * @return Collection<int, Collection<int, Finding>>
     */
    private function openFindingsByControl(array $controlIds): Collection
    {
        if ($controlIds === []) {
            return collect();
        }

        return Finding::query()
            ->select(['id', 'title', 'severity', 'status', 'control_id', 'due_at', 'identified_at'])
            ->whereIn('control_id', $controlIds)
            ->whereIn('status', [FindingStatus::Open->value, FindingStatus::InProgress->value])
            ->orderByDesc('identified_at')
            ->get()
            ->groupBy('control_id');
    }

    /**
     * @param  array<int, int>  $controlIds
     * @param  Collection<int, Collection<int, mixed>>  $grouped
     * @return Collection<int, mixed>
     */
    private function mergeByControl(array $controlIds, Collection $grouped): Collection
    {
        return collect($controlIds)
            ->flatMap(fn (int $id): Collection => $grouped->get($id, collect()))
            ->values();
    }

    /**
     * @param  Collection<int, AuditItem>  $items
     * @param  array<int, array<string, mixed>>  $rendered
     * @return array<string, int>
     */
    private function summary(Collection $items, array $rendered): array
    {
        $effectiveness = $items->countBy(
            fn (AuditItem $item): string => $item->effectiveness instanceof Effectiveness
                ? $item->effectiveness->value
                : (string) $item->effectiveness
        );

        $evidenceRows = collect($rendered)->sum(fn (array $item): int => $item['evidence']->count());
        $findingRows = collect($rendered)->sum(fn (array $item): int => $item['findings']->count());

        return [
            'items' => $items->count(),
            'completed' => $items->filter(fn (AuditItem $item): bool => $item->status === WorkflowStatus::COMPLETED)->count(),
            'effective' => (int) ($effectiveness[Effectiveness::EFFECTIVE->value] ?? 0),
            'partial' => (int) ($effectiveness[Effectiveness::PARTIAL->value] ?? 0),
            'ineffective' => (int) ($effectiveness[Effectiveness::INEFFECTIVE->value] ?? 0),
            'not_assessed' => (int) ($effectiveness[Effectiveness::UNKNOWN->value] ?? 0),
            'evidence_records' => (int) $evidenceRows,
            'open_findings' => (int) $findingRows,
        ];
    }
}
