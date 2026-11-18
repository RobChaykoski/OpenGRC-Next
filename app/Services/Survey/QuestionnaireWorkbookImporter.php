<?php

namespace App\Services\Survey;

use App\Enums\QuestionType;
use App\Enums\SurveyTemplateStatus;
use App\Enums\SurveyType;
use App\Models\SurveyQuestion;
use App\Models\SurveyTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Read a plain questionnaire workbook: section headings in one column, then a
 * question per row. Answer options may sit in the same cell, one per line,
 * prefixed with "?" (the format Excel questionnaires tend to arrive in).
 *
 * This is deliberately a narrow reader. It does not guess at scoring, risk
 * weights, or conditional logic; those are edited by hand after import.
 */
class QuestionnaireWorkbookImporter
{
    /**
     * @return array{sections: array<int, array{title: string, questions: array<int, array{text: string, type: QuestionType, options: array<int, string>}>}>}
     */
    public function read(string $path, ?string $sheetName = null): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The questionnaire file does not exist.');
        }

        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The questionnaire could not be opened: '.$exception->getMessage());
        }

        $sections = [];
        $currentIndex = null;
        $found = false;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetName !== null && strcasecmp($sheet->getName(), $sheetName) !== 0) {
                    continue;
                }

                $found = true;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = [];

                    foreach ($row->getCells() as $cell) {
                        $value = trim((string) preg_replace('/\x{00A0}/u', ' ', (string) $cell->getValue()));
                        $values[] = $value;
                    }

                    $text = trim(implode(' ', array_filter($values, fn (string $value): bool => $value !== '')));

                    if ($text === '' || $this->isHeader($text)) {
                        continue;
                    }

                    if ($this->isSectionHeading($text)) {
                        $sections[] = ['title' => $text, 'questions' => []];
                        $currentIndex = array_key_last($sections);

                        continue;
                    }

                    $question = $this->question($values);

                    if ($question === null) {
                        continue;
                    }

                    if ($currentIndex === null) {
                        $sections[] = ['title' => 'General', 'questions' => []];
                        $currentIndex = array_key_last($sections);
                    }

                    $sections[$currentIndex]['questions'][] = $question;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if (! $found) {
            throw new InvalidArgumentException('The workbook has no sheet named "'.($sheetName ?? '').'".');
        }

        $sections = array_values(array_filter($sections, fn (array $section): bool => $section['questions'] !== []));

        if ($sections === []) {
            throw new InvalidArgumentException('No questions were found in the workbook.');
        }

        return ['sections' => $sections];
    }

    /**
     * Import the workbook into a template. Existing questions are only removed
     * when the caller explicitly asks for a replacement.
     *
     * @return array{sections: int, questions: int, replaced: bool}
     */
    public function import(string $path, SurveyTemplate $template, bool $replace = false): array
    {
        return $this->importParsed($this->read($path), $template, $replace);
    }

    /**
     * @param  array{sections: array<int, array{title: string, questions: array<int, array{text: string, type: QuestionType, options: array<int, string>}>}>}  $parsed
     * @return array{sections: int, questions: int, replaced: bool}
     */
    public function importParsed(array $parsed, SurveyTemplate $template, bool $replace = false): array
    {
        return DB::transaction(function () use ($parsed, $template, $replace): array {
            $replaced = false;

            if ($template->questions()->exists()) {
                if (! $replace) {
                    throw new InvalidArgumentException('The template already has questions. Use replace to overwrite them.');
                }

                $template->questions()->delete();
                $replaced = true;
            }

            $sortOrder = 0;
            $count = 0;

            foreach ($parsed['sections'] as $section) {
                foreach ($section['questions'] as $question) {
                    SurveyQuestion::create([
                        'survey_template_id' => $template->getKey(),
                        'section' => $section['title'],
                        'question_text' => $question['text'],
                        'question_type' => $question['type'],
                        'options' => $question['options'] === []
                            ? null
                            : array_map(fn (string $label): array => ['label' => $label], $question['options']),
                        'is_required' => false,
                        'sort_order' => $sortOrder++,
                        'allow_comments' => true,
                        'risk_weight' => 0,
                    ]);

                    $count++;
                }
            }

            $template->update([
                'status' => $template->status ?? SurveyTemplateStatus::ACTIVE,
            ]);

            return ['sections' => count($parsed['sections']), 'questions' => $count, 'replaced' => $replaced];
        });
    }

    public function createTemplate(string $title, string $type = 'vendor_assessment', ?int $userId = null): SurveyTemplate
    {
        $userId ??= auth()->id();

        if ($userId === null) {
            throw new InvalidArgumentException('A survey template needs an owner. Pass a user ID.');
        }

        return SurveyTemplate::create([
            'title' => $title,
            'description' => null,
            'status' => SurveyTemplateStatus::ACTIVE,
            'type' => SurveyType::from($type),
            'created_by_id' => $userId,
        ]);
    }

    private function isHeader(string $text): bool
    {
        return in_array(strtolower(rtrim($text, ':')), ['answer', 'answers', 'question', 'questions', 'response'], true);
    }

    /**
     * A heading is a short line with no question mark and no option markers.
     * Questions in a worksheet almost always end with "?"; a heading is a few
     * words ("Remote Access", "Perimeter Controls") and nothing more.
     */
    private function isSectionHeading(string $text): bool
    {
        if (str_contains($text, '?') || str_contains($text, '☐') || str_contains($text, '□')) {
            return false;
        }

        if (str_ends_with($text, ':') || mb_strlen($text) > 80) {
            return false;
        }

        return str_word_count($text) <= 5;
    }

    /**
     * @param  array<int, string>  $values
     * @return array{text: string, type: QuestionType, options: array<int, string>}|null
     */
    private function question(array $values): ?array
    {
        $pieces = [];

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }

            foreach (preg_split('/\R/u', $value) ?: [] as $line) {
                foreach (explode("\t", (string) $line) as $fragment) {
                    $fragment = trim((string) preg_replace('/\s+/u', ' ', $fragment));

                    if ($fragment !== '') {
                        $pieces[] = $fragment;
                    }
                }
            }
        }

        $text = null;
        $options = [];

        foreach ($pieces as $piece) {
            $option = $this->option($piece);

            if ($option !== null) {
                $options[] = $option;

                continue;
            }

            if ($text === null) {
                $text = rtrim($piece, ": \t");
            }
        }

        if ($text === null || $text === '') {
            return null;
        }

        $options = array_values(array_unique($options));

        return [
            'text' => $text,
            'type' => $this->type($options),
            'options' => $options,
        ];
    }

    private function option(string $piece): ?string
    {
        foreach (['?', '☐', '□', '✓', '✔'] as $marker) {
            if (str_starts_with($piece, $marker)) {
                $label = trim(ltrim($piece, $marker.' '));

                return $label === '' ? null : $label;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $options
     */
    private function type(array $options): QuestionType
    {
        if ($options === []) {
            return QuestionType::LONG_TEXT;
        }

        if (count($options) === 1) {
            $only = strtolower($options[0]);

            if (in_array($only, ['yes', 'no', 'true', 'false'], true)) {
                return QuestionType::BOOLEAN;
            }
        }

        return count($options) === 1 ? QuestionType::SINGLE_CHOICE : QuestionType::MULTIPLE_CHOICE;
    }
}
