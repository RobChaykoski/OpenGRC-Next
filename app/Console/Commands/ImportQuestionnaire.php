<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\Survey\QuestionnaireWorkbookImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import a sectioned questionnaire workbook into a survey template.
 */
class ImportQuestionnaire extends Command
{
    protected $signature = 'opengrc:survey:import-questionnaire
                            {file : Path to the XLSX questionnaire}
                            {--template= : Survey template ID to import into}
                            {--name= : Title for a new template}
                            {--type=vendor_assessment : Survey type for a new template}
                            {--sheet= : Worksheet name (defaults to the first sheet)}
                            {--user= : User ID to own a newly created template}
                            {--replace : Replace the questions on an existing template}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import a questionnaire workbook into a survey template';

    public function handle(QuestionnaireWorkbookImporter $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $candidate = Storage::disk('private')->path($path);
            $path = is_file($candidate) ? $candidate : $path;
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return Command::FAILURE;
        }

        $sheet = $this->option('sheet');
        $sheet = is_string($sheet) && $sheet !== '' ? $sheet : null;

        try {
            $parsed = $importer->read($path, $sheet);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        foreach ($parsed['sections'] as $section) {
            $this->line($section['title'].' ('.count($section['questions']).')');
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run: no records were written.');

            return Command::SUCCESS;
        }

        try {
            $template = $this->template($path);

            if ($template === null) {
                return Command::FAILURE;
            }

            $result = $importer->import($path, $template, (bool) $this->option('replace'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Imported %d question(s) across %d section(s) into "%s".',
            $result['questions'],
            $result['sections'],
            $template->title,
        ));

        return Command::SUCCESS;
    }

    private function template(string $path): ?SurveyTemplate
    {
        $id = $this->option('template');

        if (is_string($id) && $id !== '') {
            $template = SurveyTemplate::find($id);

            if ($template === null) {
                $this->error("Survey template {$id} does not exist.");
            }

            return $template;
        }

        $name = $this->option('name');
        $title = is_string($name) && $name !== ''
            ? $name
            : Str::headline(pathinfo($path, PATHINFO_FILENAME));

        $existing = SurveyTemplate::query()->where('title', $title)->first();

        if ($existing !== null) {
            return $existing;
        }

        $userId = $this->option('user');
        $userId = is_numeric($userId)
            ? (int) $userId
            : (int) User::query()->where('role', UserRole::Admin->value)->orderBy('id')->value('id');

        if ($userId === 0) {
            $this->error('No admin user exists to own the template. Pass --user=<id>.');

            return null;
        }

        return app(QuestionnaireWorkbookImporter::class)
            ->createTemplate($title, (string) $this->option('type'), $userId);
    }
}
