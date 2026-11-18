<?php

namespace App\Services\PolicyReadiness;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use App\Models\Policy;
use App\Models\PolicyReadinessAssessment;
use App\Models\PolicyReadinessItem;
use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Import a policy readiness checklist workbook.
 *
 * The workbook has title rows, a header row, then one row per policy. Columns
 * are matched by header text, not by position, so a reordered workbook still
 * imports correctly. Status labels are mapped onto the three status enums.
 */
class PolicyReadinessWorkbookImporter
{
    /**
     * Header aliases, normalised to lowercase alphanumerics only.
     *
     * @var array<string, array<int, string>>
     */
    protected const FIELDS = [
        'category' => ['category'],
        'title' => ['policydocument', 'policydocumenttitle', 'policydocumentname'],
        'priority' => ['priority'],
        'framework_applicability' => ['frameworkapplicability'],
        'nist_csf' => ['nistcsf20', 'nistcsf'],
        'itsg33' => ['itsg33families', 'itsg33'],
        'cmmc' => ['cmmcnist800171families', 'cmmcnist800171'],
        'objective' => ['minimumcontentcontrolobjective', 'minimumcontentobjective'],
        'expected_evidence' => ['suggestedevidence'],
        'applicability' => ['applicability'],
        'documentation_status' => ['documentationstatus'],
        'implementation_status' => ['implementationstatus'],
        'effectiveness_status' => ['effectivenessstatus'],
        'owner' => ['owner'],
        'target_date' => ['targetdate'],
        'last_reviewed' => ['lastreviewed'],
        'evidence_location' => ['evidencelinklocation', 'evidencelink', 'evidencelocation'],
        'notes' => ['notesgaps', 'notes'],
    ];

    public function importFile(
        string $path,
        string $name,
        ?string $sourceFile = null,
        string $sheetName = 'Checklist',
    ): PolicyReadinessAssessment {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The checklist file does not exist.');
        }

        $rows = $this->readRows($path, $sheetName);

        return $this->importRows(
            $rows,
            $name,
            $sourceFile,
            hash_file('sha256', $path) ?: null,
        );
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function importRows(
        array $rows,
        string $name,
        ?string $sourceFile = null,
        ?string $sourceSha256 = null,
    ): PolicyReadinessAssessment {
        [$headerIndex, $headerRow] = $this->findHeaderRow($rows);

        $assessment = PolicyReadinessAssessment::create([
            'name' => $name,
            'source_file' => $sourceFile,
            'source_sha256' => $sourceSha256,
            'imported_at' => now(),
        ]);

        $imported = 0;

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $code = $this->text($this->cell($row, $headerRow, ['id']));

            if ($code === null) {
                continue;
            }

            $title = $this->text($this->cell($row, $headerRow, self::FIELDS['title'])) ?? $code;

            $attributes = [
                'code' => mb_substr($code, 0, 32),
                'title' => mb_substr($title, 0, 512),
                'category' => $this->short($this->cell($row, $headerRow, self::FIELDS['category']), 64),
                'priority' => $this->short($this->cell($row, $headerRow, self::FIELDS['priority']), 32),
                'framework_applicability' => $this->text($this->cell($row, $headerRow, self::FIELDS['framework_applicability'])),
                'nist_csf' => $this->text($this->cell($row, $headerRow, self::FIELDS['nist_csf'])),
                'itsg33' => $this->text($this->cell($row, $headerRow, self::FIELDS['itsg33'])),
                'cmmc' => $this->text($this->cell($row, $headerRow, self::FIELDS['cmmc'])),
                'objective' => $this->text($this->cell($row, $headerRow, self::FIELDS['objective'])),
                'expected_evidence' => $this->text($this->cell($row, $headerRow, self::FIELDS['expected_evidence'])),
                'applicability' => $this->short($this->cell($row, $headerRow, self::FIELDS['applicability']), 32),
                'documentation_status' => PolicyDocumentationStatus::fromLabel(
                    $this->text($this->cell($row, $headerRow, self::FIELDS['documentation_status']))
                ),
                'implementation_status' => PolicyImplementationStatus::fromLabel(
                    $this->text($this->cell($row, $headerRow, self::FIELDS['implementation_status']))
                ),
                'effectiveness_status' => PolicyEffectivenessStatus::fromLabel(
                    $this->text($this->cell($row, $headerRow, self::FIELDS['effectiveness_status']))
                ),
                'owner' => $this->short($this->cell($row, $headerRow, self::FIELDS['owner']), 255),
                'target_date' => $this->short($this->cell($row, $headerRow, self::FIELDS['target_date']), 64),
                'last_reviewed' => $this->short($this->cell($row, $headerRow, self::FIELDS['last_reviewed']), 64),
                'evidence_location' => $this->short($this->cell($row, $headerRow, self::FIELDS['evidence_location']), 512),
                'notes' => $this->text($this->cell($row, $headerRow, self::FIELDS['notes'])),
                'policy_id' => $this->policyId($code, $title),
            ];

            PolicyReadinessItem::updateOrCreate(
                [
                    'assessment_id' => $assessment->getKey(),
                    'code' => $attributes['code'],
                ],
                $attributes,
            );

            $imported++;
        }

        if ($imported === 0) {
            $assessment->delete();

            throw new InvalidArgumentException('No checklist rows with an ID were found in the workbook.');
        }

        return $assessment;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function readRows(string $path, string $sheetName = 'Checklist'): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);

            $target = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                $target ??= $sheet;

                if (strcasecmp($sheet->getName(), $sheetName) === 0) {
                    $target = $sheet;
                    break;
                }
            }

            if ($target === null) {
                throw new InvalidArgumentException('The workbook has no sheets.');
            }

            $rows = [];

            foreach ($target->getRowIterator() as $row) {
                $rows[] = array_map(
                    fn ($cell) => $cell->getValue(),
                    $row->getCells(),
                );
            }

            return $rows;
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    protected function findHeaderRow(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $keys = array_map(fn ($value) => $this->key($value), $row);

            if (in_array('id', $keys, true)
                && (in_array('policydocument', $keys, true)
                    || in_array('policydocumenttitle', $keys, true)
                    || in_array('policydocumentname', $keys, true))) {
                $header = [];

                foreach ($keys as $position => $key) {
                    if ($key !== '') {
                        $header[$key] = $position;
                    }
                }

                return [$index, $header];
            }
        }

        throw new InvalidArgumentException('Could not find the checklist header row (ID and Policy / Document).');
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $header
     * @param  array<int, string>  $aliases
     */
    protected function cell(array $row, array $header, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $header)) {
                return $row[$header[$alias]] ?? null;
            }
        }

        return null;
    }

    protected function policyId(string $code, string $title): ?int
    {
        $policy = Policy::query()
            ->where('code', $code)
            ->orWhere('name', $title)
            ->first();

        return $policy?->getKey();
    }

    protected function key(mixed $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value));
    }

    protected function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function short(mixed $value, int $length): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_substr($text, 0, $length);
    }
}
