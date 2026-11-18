<?php

namespace App\Services\Evidence;

use App\Enums\ControlStatus;
use App\Models\Control;
use App\Models\ControlValidation;
use App\Models\ControlValidationRule;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Evaluate validation rules against the latest evidence per asset.
 *
 * One validation row is written per rule and asset. A rule that covers a fleet
 * therefore reports the failing host, not the last host that happened to send
 * data.
 */
class ControlValidator
{
    public function __construct(
        protected ValidationRuleEvaluator $evaluator,
        protected FindingManager $findings,
    ) {}

    public function validateAll(): ValidationRun
    {
        $run = new ValidationRun;

        ControlValidationRule::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->each(fn (ControlValidationRule $rule) => $this->validateRule($rule, $run));

        return $run;
    }

    public function validateControl(Control $control): ValidationRun
    {
        $run = new ValidationRun;

        ControlValidationRule::query()
            ->where('control_id', $control->getKey())
            ->where('enabled', true)
            ->orderBy('id')
            ->each(fn (ControlValidationRule $rule) => $this->validateRule($rule, $run));

        return $run;
    }

    public function validateSource(EvidenceSource $source): ValidationRun
    {
        $run = new ValidationRun;

        ControlValidationRule::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->where('source_id', $source->getKey())->orWhereNull('source_id'))
            ->orderBy('id')
            ->each(fn (ControlValidationRule $rule) => $this->validateRule($rule, $run, $source));

        return $run;
    }

    public function validateRule(
        ControlValidationRule $rule,
        ?ValidationRun $run = null,
        ?EvidenceSource $onlySource = null,
    ): ValidationRun {
        $run ??= new ValidationRun;

        if (! $rule->isEnabled()) {
            return $run;
        }

        $records = $this->latestRecords($rule, $onlySource);

        if ($records->isEmpty()) {
            $this->writeValidation(
                $rule,
                null,
                ControlStatus::Unknown,
                ['reason' => 'no evidence'],
                $run,
            );

            return $run;
        }

        $definition = is_array($rule->definition) ? $rule->definition : [];

        foreach ($records as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];

            try {
                $evaluation = $this->evaluator->evaluate($definition, $payload);
            } catch (Throwable $exception) {
                $this->writeValidation(
                    $rule,
                    $record,
                    ControlStatus::Unknown,
                    ['error' => $exception->getMessage()],
                    $run,
                );

                continue;
            }

            $this->writeValidation($rule, $record, $evaluation['status'], $evaluation['result'], $run);
        }

        return $run;
    }

    /**
     * Latest record per asset for this rule, newest collected_at first.
     *
     * @return Collection<int, EvidenceRecord>
     */
    protected function latestRecords(
        ControlValidationRule $rule,
        ?EvidenceSource $onlySource = null,
    ): Collection {
        $evidenceType = data_get($rule->definition, 'match.evidence_type');
        $sourceId = $rule->source_id ?? $onlySource?->getKey();

        return EvidenceRecord::query()
            ->when(is_numeric($sourceId), fn ($query) => $query->where('source_id', (int) $sourceId))
            ->when(
                is_string($evidenceType) && $evidenceType !== '',
                // A rule that names an evidence type is a cross-framework mapping:
                // it evaluates every measurement of that type, not only records
                // directly linked to its control.
                fn ($query) => $query->where('evidence_type', $evidenceType),
                fn ($query) => $query->where('control_id', $rule->control_id)
            )
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (EvidenceRecord $record) => $record->asset_id ?? $record->external_id)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function writeValidation(
        ControlValidationRule $rule,
        ?EvidenceRecord $record,
        ControlStatus $status,
        array $result,
        ValidationRun $run,
    ): void {
        $assetId = $record?->asset_id;
        $assetIdentifier = $record !== null && $assetId === null ? $record->external_id : null;

        $previous = ControlValidation::query()
            ->where('rule_id', $rule->getKey())
            ->when(
                $assetId !== null,
                fn ($query) => $query->where('asset_id', $assetId),
                fn ($query) => $query->whereNull('asset_id')->where('asset_identifier', $assetIdentifier)
            )
            ->latest('id')
            ->first();

        $result['previous_status'] = $previous?->status?->value;
        $result['drift'] = $previous !== null && $previous->status !== $status;

        $validation = ControlValidation::create([
            'control_id' => $rule->control_id,
            'source_id' => $record?->source_id ?? $rule->source_id,
            'asset_id' => $assetId,
            'asset_identifier' => $assetIdentifier,
            'evidence_record_id' => $record?->getKey(),
            'rule_id' => $rule->getKey(),
            'rule_version' => $rule->version,
            'status' => $status,
            'result' => $result,
            'evaluated_at' => now(),
        ]);

        $run->record($validation);

        if ($record === null) {
            return;
        }

        if ($status === ControlStatus::Failed) {
            $finding = $this->findings->raise($rule, $validation, $record);

            $finding->wasRecentlyCreated
                ? $run->findingsOpened++
                : $run->findingsUpdated++;

            return;
        }

        if ($status === ControlStatus::Effective) {
            $run->findingsResolved += $this->findings->resolve($rule, $record);
        }
    }
}
