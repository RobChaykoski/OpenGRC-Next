<?php

namespace App\Services\Assessment;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Models\AssessmentReport;
use App\Models\Finding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

/**
 * Import a CSET JSON assessment export.
 *
 * Only the assessment summary, answer counts and observations are read. Contacts,
 * organisation demographics, comments, feedback and question text are ignored:
 * a CSET export can contain PCII, so the scrubbed JSON export is the expected
 * input (ADR 0038).
 */
class CsetImporter
{
    /**
     * @return array{name: string, standards: array<int, string>, models: array<int, string>, observations: int, requirements: int, questions: int, negative_answers: int}
     */
    public function preview(string $path): array
    {
        return $this->summarise($this->decode($path));
    }

    public function import(string $path, ?int $applicationId = null, ?int $assetId = null): AssessmentReport
    {
        $data = $this->decode($path);
        $hash = hash_file('sha256', $path);

        if (! is_string($hash)) {
            throw new InvalidArgumentException('The CSET export could not be hashed.');
        }

        $summary = $this->summarise($data);

        return DB::transaction(function () use ($data, $hash, $summary, $applicationId, $assetId): AssessmentReport {
            $report = AssessmentReport::query()->firstOrNew([
                'report_sha256' => $hash,
                'application_id' => $applicationId,
                'asset_id' => $assetId,
            ]);

            $assessment = $this->assessment($data);
            $sal = is_array($assessment['sal'] ?? null) ? $assessment['sal'] : [];

            $report->fill([
                'name' => $summary['name'],
                'format' => 'cset_json',
                'tool' => 'CSET',
                'tool_version' => null,
                'application_id' => $applicationId,
                'asset_id' => $assetId,
                'metadata' => [
                    'assessment_guid' => $this->string($assessment['assessment_guid'] ?? null),
                    'assessment_date' => $this->string($assessment['assessment_date'] ?? null),
                    'sal_level' => $this->string($sal['overall_level'] ?? null),
                    'standards' => $summary['standards'],
                    'maturity_models' => $summary['models'],
                    'requirements' => $summary['requirements'],
                    'questions' => $summary['questions'],
                    'negative_answers' => $summary['negative_answers'],
                    'observations' => $summary['observations'],
                ],
                'imported_at' => now(),
            ])->save();

            $stored = [];

            foreach ($this->observations($data) as $key => $observation) {
                $this->storeFinding($report, $key, $observation);
                $stored[$key] = true;
            }

            $report->forceFill(['finding_count' => count($stored)])->save();

            return $report->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $observation
     */
    protected function storeFinding(AssessmentReport $report, string $key, array $observation): void
    {
        $observationId = $this->string($observation['observation_id'] ?? null) ?? '0';
        $title = $this->string($observation['title'] ?? null)
            ?? $this->string($observation['summary'] ?? null)
            ?? 'CSET observation #'.$observationId;
        $severity = $this->severity($observation);
        $dedupeKey = 'cset:'.$key;

        $finding = Finding::query()->firstOrNew(['dedupe_key' => $dedupeKey]);
        $status = (! $finding->exists || $finding->status !== FindingStatus::Accepted)
            ? FindingStatus::Open
            : FindingStatus::Accepted;

        $parts = array_filter([
            $this->string($observation['summary'] ?? null),
            $this->string($observation['issue'] ?? null),
            $this->string($observation['impacts'] ?? null),
            $this->string($observation['recommendations'] ?? null) === null
                ? null
                : 'Recommendation: '.$this->string($observation['recommendations']),
            'Source: CSET observation #'.$observationId
                .($this->string($observation['importance'] ?? null) === null
                    ? ''
                    : ' (importance: '.$this->string($observation['importance']).')'),
        ], fn (?string $part): bool => $part !== null && $part !== '');

        $finding->fill([
            'title' => Str::limit($title, 250),
            'description' => Str::limit(implode("\n\n", $parts), 4000),
            'severity' => $severity,
            'status' => $status,
            'source' => FindingSource::Automated,
            'asset_id' => $report->asset_id,
            'assessment_report_id' => $report->getKey(),
            'owner_id' => $finding->owner_id,
            'dedupe_key' => $dedupeKey,
            'occurrences' => ($finding->occurrences ?? 0) + 1,
            'identified_at' => $finding->identified_at ?? now(),
            'last_seen_at' => now(),
            'due_at' => $finding->due_at ?? $this->dueAt($severity),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, standards: array<int, string>, models: array<int, string>, observations: int, requirements: int, questions: int, negative_answers: int}
     */
    protected function summarise(array $data): array
    {
        $assessment = $this->assessment($data);
        $standards = $this->standards($data);
        $models = $this->models($data);

        $requirements = 0;
        $questions = 0;
        $negative = 0;

        foreach ($standards as $standard) {
            foreach ($this->listOfArrays($standard['requirements'] ?? null) as $requirement) {
                $requirements++;
                $negative += $this->isNegative($requirement) ? 1 : 0;
            }

            foreach ($this->listOfArrays($standard['questions'] ?? null) as $question) {
                $questions++;
                $negative += $this->isNegative($question) ? 1 : 0;
            }
        }

        foreach ($models as $model) {
            foreach ($this->questionsIn($model) as $question) {
                $questions++;
                $negative += $this->isNegative($question) ? 1 : 0;
            }
        }

        return [
            'name' => $this->string($assessment['name'] ?? null) ?? 'CSET assessment',
            'standards' => array_values(array_filter(array_map(
                fn (array $standard): ?string => $this->string($standard['set_name'] ?? null),
                $standards
            ))),
            'models' => array_values(array_filter(array_map(
                fn (array $model): ?string => $this->string($model['model_name'] ?? null)
                    ?? $this->string($model['model_title'] ?? null),
                $models
            ))),
            'observations' => count($this->observations($data)),
            'requirements' => $requirements,
            'questions' => $questions,
            'negative_answers' => $negative,
        ];
    }

    /**
     * Unique observations keyed by a stable identity.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    protected function observations(array $data): array
    {
        $assessment = $this->assessment($data);
        $guid = $this->string($assessment['assessment_guid'] ?? null) ?? 'unknown';
        $observations = [];
        $seen = [];

        $candidates = [];

        foreach ($this->listOfArrays($assessment['observations'] ?? null) as $observation) {
            $candidates[] = $observation;
        }

        foreach ($this->standards($data) as $standard) {
            foreach ($this->listOfArrays($standard['requirements'] ?? null) as $requirement) {
                foreach ($this->listOfArrays($requirement['observations'] ?? null) as $observation) {
                    $candidates[] = $observation;
                }
            }

            foreach ($this->listOfArrays($standard['questions'] ?? null) as $question) {
                foreach ($this->listOfArrays($question['observations'] ?? null) as $observation) {
                    $candidates[] = $observation;
                }
            }
        }

        foreach ($this->models($data) as $model) {
            foreach ($this->questionsIn($model) as $question) {
                foreach ($this->listOfArrays($question['observations'] ?? null) as $observation) {
                    $candidates[] = $observation;
                }
            }
        }

        foreach ($candidates as $observation) {
            $id = $this->string($observation['observation_id'] ?? null);

            if ($id === null || $id === '0') {
                $fingerprint = $this->string($observation['title'] ?? null)
                    .'|'.$this->string($observation['summary'] ?? null)
                    .'|'.$this->string($observation['issue'] ?? null);
                $id = 'hash:'.substr(hash('sha256', $fingerprint), 0, 32);
            }

            $key = $guid.':'.$id;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $observations[$key] = $observation;
        }

        return $observations;
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array<int, array<string, mixed>>
     */
    protected function questionsIn(array $model): array
    {
        $questions = [];

        $walk = function (array $groupings) use (&$walk, &$questions): void {
            foreach ($this->listOfArrays($groupings) as $grouping) {
                foreach ($this->listOfArrays($grouping['questions'] ?? null) as $question) {
                    $questions[] = $question;

                    foreach ($this->listOfArrays($question['followup_questions'] ?? null) as $followup) {
                        $questions[] = $followup;
                    }
                }

                $walk($grouping['groupings'] ?? null);
            }
        };

        $walk($model['groupings'] ?? null);

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function standards(array $data): array
    {
        $standards = $data['standards'] ?? null;

        if (! is_array($standards)) {
            return [];
        }

        return array_merge(
            $this->listOfArrays($standards['standards'] ?? null),
            $this->listOfArrays($standards['questions'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function models(array $data): array
    {
        return $this->listOfArrays($data['maturity_models'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function assessment(array $data): array
    {
        $assessment = $data['assessment'] ?? null;

        if (! is_array($assessment)) {
            throw new InvalidArgumentException('The file is not a CSET JSON export: the assessment object is missing.');
        }

        return $assessment;
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    protected function isNegative(array $answer): bool
    {
        $value = $this->string($answer['answer_text'] ?? null);

        return $value !== null && in_array(strtolower($value), ['n', 'no', 'false'], true);
    }

    /**
     * @param  array<string, mixed>  $observation
     */
    protected function severity(array $observation): EvidenceSeverity
    {
        return match (strtolower($this->string($observation['importance'] ?? null) ?? '')) {
            'critical', 'very high' => EvidenceSeverity::Critical,
            'high' => EvidenceSeverity::High,
            'low', 'informational' => EvidenceSeverity::Low,
            default => EvidenceSeverity::Medium,
        };
    }

    protected function dueAt(EvidenceSeverity $severity): CarbonImmutable
    {
        return match ($severity) {
            EvidenceSeverity::Critical => CarbonImmutable::now()->addDays(7),
            EvidenceSeverity::High => CarbonImmutable::now()->addDays(30),
            EvidenceSeverity::Medium => CarbonImmutable::now()->addDays(60),
            EvidenceSeverity::Low => CarbonImmutable::now()->addDays(90),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("The CSET export is not readable: {$path}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The CSET export is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('The CSET export must be a JSON object.');
        }

        $data = $this->normalise($data);

        if (is_string($data['json'] ?? null)) {
            try {
                $nested = json_decode($data['json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('The wrapped CSET JSON is invalid: '.$exception->getMessage());
            }

            if (! is_array($nested)) {
                throw new InvalidArgumentException('The wrapped CSET JSON must be an object.');
            }

            $data = $this->normalise($nested);
        }

        $this->assessment($data);

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function normalise(array $data): array
    {
        $normalised = [];

        foreach ($data as $key => $value) {
            $normalisedKey = is_string($key) ? Str::snake($key) : $key;
            $normalised[$normalisedKey] = is_array($value) ? $this->normalise($value) : $value;
        }

        return $normalised;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private function string(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
