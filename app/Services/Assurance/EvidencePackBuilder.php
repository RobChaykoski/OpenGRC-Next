<?php

namespace App\Services\Assurance;

use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\ControlValidation;
use App\Models\EvidenceRecord;
use App\Models\Finding;
use App\Models\Implementation;
use App\Services\Scoping\CanonicalOutcomeCoverage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Build an audit evidence pack for one framework.
 *
 * The pack is an index with verifiable hashes, not a copy of the evidence. Raw
 * payloads, rule definitions and attachments stay in OpenGRC, because exporting
 * them would move sensitive data into an unmanaged file and could expose matched
 * values that the ledger deliberately keeps out of reports.
 */
final class EvidencePackBuilder
{
    public function __construct(private readonly CanonicalOutcomeCoverage $coverage) {}

    /**
     * @return array{path: string, counts: array<string, int>}
     */
    public function build(string $frameworkCode, string $outputPath): array
    {
        $framework = CanonicalFramework::query()->where('code', $frameworkCode)->first();

        if ($framework === null) {
            throw new RuntimeException("Unknown framework [{$frameworkCode}]. Run opengrc:catalog:sync first.");
        }

        $outcomes = $this->coverage->summary($frameworkCode);

        if ($outcomes === []) {
            throw new RuntimeException("Framework [{$frameworkCode}] has no canonical outcomes in the catalogue.");
        }

        $codes = array_column($outcomes, 'code');
        $catalogue = CanonicalControl::query()->whereIn('code', $codes)->get()->keyBy('code');

        $links = DB::table('control_canonical_outcome as link')
            ->join('canonical_controls as outcome', 'outcome.id', '=', 'link.canonical_control_id')
            ->join('controls as control', 'control.id', '=', 'link.control_id')
            ->join('standards as standard', 'standard.id', '=', 'control.standard_id')
            ->whereIn('outcome.code', $codes)
            ->whereNull('control.deleted_at')
            ->select(
                'outcome.code as outcome_code',
                'control.id as control_id',
                'control.code as control_code',
                'control.title as control_title',
                'standard.code as standard_code'
            )
            ->orderBy('outcome.code')
            ->orderBy('control.code')
            ->get();

        /** @var array<int, array<int, string>> $outcomesByControl */
        $outcomesByControl = [];

        foreach ($links as $link) {
            $outcomesByControl[(int) $link->control_id][] = (string) $link->outcome_code;
        }

        $controlIds = array_keys($outcomesByControl);

        $implementations = $this->implementations($controlIds, $outcomesByControl);
        $evidence = $this->evidence($controlIds, $outcomesByControl);
        $validations = $this->validations($controlIds, $outcomesByControl);
        $findings = $this->findings($controlIds, $outcomesByControl);

        $counts = [
            'outcomes' => count($outcomes),
            'control_links' => $links->count(),
            'implementations' => count($implementations),
            'evidence_records' => count($evidence),
            'validation_results' => count($validations),
            'findings' => count($findings),
        ];

        $directory = storage_path('app/exports/.pack-'.Str::random(12));
        File::ensureDirectoryExists($directory);
        File::ensureDirectoryExists(dirname($outputPath));

        $this->writeCsv($directory.'/outcomes.csv', [
            'code', 'name', 'category', 'priority', 'automation_level', 'frequency',
            'evidence_expectation', 'controls', 'policies', 'evidence', 'validations',
            'best_confidence', 'confidence_basis', 'stale', 'monitored',
        ], array_map(function (array $row) use ($catalogue): array {
            $control = $catalogue->get($row['code']);

            return [
                $row['code'],
                $row['name'],
                $row['category'],
                $row['priority'],
                $control?->automation_level,
                $control?->frequency,
                $control?->evidence_expectation,
                $row['controls'],
                $row['policies'],
                $row['evidence'],
                $row['validations'],
                $row['best_confidence'],
                $row['confidence_basis'],
                $row['stale'] ? 'yes' : 'no',
                $row['monitored'] ? 'yes' : 'no',
            ];
        }, $outcomes));

        $this->writeCsv($directory.'/controls.csv', [
            'outcome_code', 'standard_code', 'control_code', 'control_title',
        ], $links->map(fn ($link): array => [
            $link->outcome_code,
            $link->standard_code,
            $link->control_code,
            $link->control_title,
        ])->all());

        $this->writeCsv($directory.'/implementations.csv', [
            'outcome_codes', 'control_code', 'implementation_code', 'title', 'status',
            'effectiveness', 'last_tested_at', 'next_test_at',
        ], $implementations);

        $this->writeCsv($directory.'/evidence.csv', [
            'outcome_codes', 'control_code', 'external_id', 'evidence_type', 'status',
            'collected_at', 'source', 'source_type', 'asset', 'payload_sha256',
        ], $evidence);

        $this->writeCsv($directory.'/validations.csv', [
            'outcome_codes', 'control_code', 'rule_id', 'rule_version', 'status', 'evaluated_at',
        ], $validations);

        $this->writeCsv($directory.'/findings.csv', [
            'reference', 'outcome_codes', 'control_code', 'title', 'severity', 'status',
            'source', 'asset', 'occurrences', 'identified_at', 'due_at', 'resolved_at',
        ], $findings);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'application' => config('app.name'),
            'framework' => [
                'code' => $framework->code,
                'name' => $framework->name,
            ],
            'counts' => $counts,
            'excluded' => [
                'evidence payloads',
                'validation rule definitions and results',
                'file attachments and documents',
                'personal contact details and account identifiers',
            ],
            'note' => 'This pack indexes reviewed links, evidence hashes, validation outcomes and findings. '
                .'It is not a compliance certificate. Raw evidence stays in OpenGRC and can be retrieved against the hashes.',
        ];

        File::put($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        File::put($directory.'/README.txt', $this->readme($framework->name, $counts));

        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($directory);

            throw new RuntimeException("Could not create the evidence pack at [{$outputPath}].");
        }

        foreach (File::files($directory) as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();
        File::deleteDirectory($directory);

        return ['path' => $outputPath, 'counts' => $counts];
    }

    /**
     * @param  array<int, int>  $controlIds
     * @param  array<int, array<int, string>>  $outcomesByControl
     * @return array<int, array<int, string|int|null>>
     */
    private function implementations(array $controlIds, array $outcomesByControl): array
    {
        if ($controlIds === []) {
            return [];
        }

        return Implementation::query()
            ->whereHas('controls', fn ($query) => $query->whereIn('controls.id', $controlIds))
            ->with(['controls:id,code'])
            ->orderBy('code')
            ->get()
            ->flatMap(fn (Implementation $implementation): array => $implementation->controls
                ->filter(fn ($control): bool => isset($outcomesByControl[$control->getKey()]))
                ->map(fn ($control): array => [
                    implode(', ', $outcomesByControl[$control->getKey()]),
                    $control->code,
                    $implementation->code,
                    $implementation->title,
                    $implementation->status?->value,
                    $implementation->effectiveness?->value,
                    $implementation->last_tested_at?->toIso8601String(),
                    $implementation->next_test_at?->toIso8601String(),
                ])
                ->all())
            ->all();
    }

    /**
     * @param  array<int, int>  $controlIds
     * @param  array<int, array<int, string>>  $outcomesByControl
     * @return array<int, array<int, string|null>>
     */
    private function evidence(array $controlIds, array $outcomesByControl): array
    {
        if ($controlIds === []) {
            return [];
        }

        return EvidenceRecord::query()
            ->whereIn('control_id', $controlIds)
            ->with(['source:id,name,type', 'asset:id,name', 'control:id,code'])
            ->orderBy('collected_at')
            ->get()
            ->map(fn (EvidenceRecord $record): array => [
                implode(', ', $outcomesByControl[(int) $record->control_id] ?? []),
                $record->control?->code,
                $record->external_id,
                $record->evidence_type,
                $record->status?->value,
                $record->collected_at?->toIso8601String(),
                $record->source?->name,
                $record->source?->type?->value,
                $record->asset?->name,
                $record->payload_sha256,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $controlIds
     * @param  array<int, array<int, string>>  $outcomesByControl
     * @return array<int, array<int, string|int|null>>
     */
    private function validations(array $controlIds, array $outcomesByControl): array
    {
        if ($controlIds === []) {
            return [];
        }

        return ControlValidation::query()
            ->whereIn('control_id', $controlIds)
            ->with('control:id,code')
            ->orderBy('evaluated_at')
            ->get()
            ->map(fn (ControlValidation $validation): array => [
                implode(', ', $outcomesByControl[(int) $validation->control_id] ?? []),
                $validation->control?->code,
                $validation->rule_id,
                $validation->rule_version,
                $validation->status?->value,
                $validation->evaluated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $controlIds
     * @param  array<int, array<int, string>>  $outcomesByControl
     * @return array<int, array<int, string|int|null>>
     */
    private function findings(array $controlIds, array $outcomesByControl): array
    {
        if ($controlIds === []) {
            return [];
        }

        return Finding::query()
            ->whereIn('control_id', $controlIds)
            ->with(['control:id,code', 'asset:id,name'])
            ->orderBy('identified_at')
            ->get()
            ->map(fn (Finding $finding): array => [
                $finding->reference(),
                implode(', ', $outcomesByControl[(int) $finding->control_id] ?? []),
                $finding->control?->code,
                $finding->title,
                $finding->severity?->value,
                $finding->status?->value,
                $finding->source?->value,
                $finding->asset?->name,
                $finding->occurrences,
                $finding->identified_at?->toIso8601String(),
                $finding->due_at?->toIso8601String(),
                $finding->resolved_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not write [{$path}].");
        }

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function readme(string $frameworkName, array $counts): string
    {
        return <<<TEXT
        Evidence pack: {$frameworkName}
        Generated: {$this->timestamp()}

        Contents
        - outcomes.csv: required outcomes, coverage counts and derived confidence
        - controls.csv: imported controls linked to each outcome
        - implementations.csv: how the organisation implements those controls
        - evidence.csv: evidence metadata and SHA-256 hashes (no raw payloads)
        - validations.csv: validation outcomes (no rule definitions)
        - findings.csv: findings attached to the linked controls

        Integrity
        Every evidence row carries payload_sha256. Verify a file or payload against
        that hash before relying on it. Raw payloads and attachments are deliberately
        excluded; retrieve them from OpenGRC under access control.

        Interpretation
        A link means a person reviewed the requirement against the outcome. Evidence
        means a measurement exists. Neither is an audit opinion or a compliance
        certificate. Counts are: {$this->summary($counts)}.
        TEXT;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function summary(array $counts): string
    {
        return collect($counts)
            ->map(fn (int $value, string $key): string => "{$key} {$value}")
            ->implode(', ');
    }

    private function timestamp(): string
    {
        return now()->toIso8601String();
    }
}
