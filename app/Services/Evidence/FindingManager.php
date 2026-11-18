<?php

namespace App\Services\Evidence;

use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Models\ControlValidation;
use App\Models\ControlValidationRule;
use App\Models\EvidenceRecord;
use App\Models\Finding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turn failed validations into one finding per rule and asset.
 *
 * A repeat failure updates the open finding instead of creating a second one.
 * A passing validation only resolves the finding for the same asset; other
 * hosts covered by the same rule stay open.
 */
class FindingManager
{
    public function raise(
        ControlValidationRule $rule,
        ControlValidation $validation,
        EvidenceRecord $record,
    ): Finding {
        $key = $this->dedupeKey($rule, $record);

        return DB::transaction(function () use ($rule, $validation, $record, $key): Finding {
            $finding = Finding::query()
                ->open()
                ->where('dedupe_key', $key)
                ->lockForUpdate()
                ->first();

            if ($finding instanceof Finding) {
                $finding->forceFill([
                    'severity' => $rule->severity,
                    'description' => $this->describe($validation),
                    'last_seen_at' => now(),
                    'occurrences' => $finding->occurrences + 1,
                ])->save();

                return $finding;
            }

            return Finding::create([
                'title' => $this->title($rule, $record),
                'description' => $this->describe($validation),
                'severity' => $rule->severity,
                'status' => FindingStatus::Open,
                'source' => FindingSource::Automated,
                'control_id' => $rule->control_id,
                'asset_id' => $record->asset_id,
                'evidence_source_id' => $record->source_id,
                'rule_id' => $rule->getKey(),
                'owner_id' => $this->ownerId($rule, $record),
                'dedupe_key' => $key,
                'occurrences' => 1,
                'identified_at' => now(),
                'last_seen_at' => now(),
                'due_at' => $this->dueAt($rule),
            ]);
        });
    }

    /**
     * Resolve open findings for this rule and asset after a passing check.
     *
     * @return int number of findings resolved
     */
    public function resolve(ControlValidationRule $rule, EvidenceRecord $record): int
    {
        $key = $this->dedupeKey($rule, $record);

        return Finding::query()
            ->open()
            ->where('dedupe_key', $key)
            ->get()
            ->each(fn (Finding $finding) => $finding->resolve(
                'Resolved automatically: the latest validation passed.'
            ))
            ->count();
    }

    /**
     * A finding is about a rule applied to one asset. Two sources reporting on
     * the same asset must not collapse into one finding.
     */
    protected function dedupeKey(ControlValidationRule $rule, EvidenceRecord $record): string
    {
        return hash('sha256', implode('|', [
            'rule:'.$rule->getKey(),
            'source:'.$record->source_id,
            'asset:'.($record->asset_id ?? $record->external_id),
        ]));
    }

    protected function title(ControlValidationRule $rule, EvidenceRecord $record): string
    {
        $rule->loadMissing('control');
        $record->loadMissing('asset');

        $controlCode = $rule->control?->getAttribute('code');
        $prefix = is_string($controlCode) && $controlCode !== '' ? "{$controlCode}: " : '';

        $host = $record->asset?->getAttribute('name')
            ?? data_get($record->payload, 'host.hostname')
            ?? $record->external_id;

        return "{$prefix}{$host} {$record->evidence_type} check failed";
    }

    protected function describe(ControlValidation $validation): string
    {
        $result = $validation->result;
        $checks = is_array($result) && is_array($result['checks'] ?? null) ? $result['checks'] : [];

        $lines = [];

        foreach ($checks as $check) {
            if (! is_array($check) || ($check['result'] ?? null) !== 'failed') {
                continue;
            }

            $lines[] = sprintf(
                '%s %s %s (actual: %s)',
                (string) ($check['path'] ?? 'value'),
                (string) ($check['op'] ?? 'eq'),
                json_encode($check['expected'] ?? null, JSON_UNESCAPED_SLASHES),
                json_encode($check['actual'] ?? null, JSON_UNESCAPED_SLASHES),
            );
        }

        return $lines === []
            ? 'The latest evidence did not meet the validation rule.'
            : implode(PHP_EOL, $lines);
    }

    protected function ownerId(ControlValidationRule $rule, EvidenceRecord $record): ?int
    {
        $rule->loadMissing('control');
        $record->loadMissing('asset');

        $controlOwner = $rule->control?->getAttribute('control_owner_id');
        $assetOwner = $record->asset?->getAttribute('assigned_to_user_id');
        $owner = $controlOwner ?? $assetOwner;

        return is_numeric($owner) ? (int) $owner : null;
    }

    protected function dueAt(ControlValidationRule $rule): Carbon
    {
        $days = config('evidence.finding_due_days.'.$rule->severity->value, 30);

        return now()->addDays(is_numeric($days) ? (int) $days : 30);
    }
}
