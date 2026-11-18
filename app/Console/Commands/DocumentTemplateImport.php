<?php

namespace App\Console\Commands;

use App\Enums\DocumentClassification;
use App\Enums\DocumentTemplateStatus;
use App\Enums\DocumentTemplateType;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentTemplateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import a batch of supplied document templates from a manifest.
 *
 * Vendor and customer documents are never bundled with the application; the
 * operator keeps them on the private disk and describes them in a manifest.
 */
class DocumentTemplateImport extends Command
{
    protected $signature = 'opengrc:document-templates:import
                            {manifest? : Path to the JSON manifest (defaults to the private document-templates directory)}
                            {--dry-run : Parse and report without writing records}';

    protected $description = 'Import supplied DOCX and HTML document templates from a manifest';

    public function handle(DocumentTemplateManager $manager): int
    {
        $manifestPath = $this->manifestPath();

        if ($manifestPath === null) {
            $this->error('No manifest found. Pass a path or create storage/app/private/document-templates/manifest.json.');

            return Command::FAILURE;
        }

        $manifest = $this->readManifest($manifestPath);

        if ($manifest === null) {
            return Command::FAILURE;
        }

        $rows = [];

        foreach ($manifest as $index => $entry) {
            $result = $this->importOne($manager, $entry, $index);
            $rows[] = $result;

            $this->line(sprintf(
                '%-10s %-40s %s',
                $result['status'],
                Str::limit($result['name'], 40),
                $result['detail'],
            ));
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run: no records were written.');
        }

        $failed = collect($rows)->where('status', 'failed')->count();

        $this->newLine();
        $this->line(sprintf(
            '%d template(s): %d imported, %d updated, %d unchanged, %d failed.',
            count($rows),
            collect($rows)->where('status', 'imported')->count(),
            collect($rows)->where('status', 'updated')->count(),
            collect($rows)->where('status', 'unchanged')->count(),
            $failed,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function manifestPath(): ?string
    {
        $argument = $this->argument('manifest');

        if (is_string($argument) && $argument !== '') {
            $resolved = is_file($argument) ? $argument : Storage::disk('private')->path($argument);

            return is_file($resolved) ? $resolved : null;
        }

        $default = Storage::disk('private')->path('document-templates/manifest.json');

        return is_file($default) ? $default : null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function readManifest(string $path): ?array
    {
        $raw = file_get_contents($path);

        if (! is_string($raw) || trim($raw) === '') {
            $this->error('The manifest is empty.');

            return null;
        }

        // A UTF-8 BOM from a Windows editor is not part of the JSON document.
        $raw = ltrim($raw, "\xEF\xBB\xBF");

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->error('The manifest is not valid JSON: '.json_last_error_msg());

            return null;
        }

        $templates = $decoded['templates'] ?? $decoded;

        if (! is_array($templates) || $templates === []) {
            $this->error('The manifest contains no templates.');

            return null;
        }

        return array_values(array_filter($templates, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{status: string, name: string, detail: string}
     */
    private function importOne(DocumentTemplateManager $manager, array $entry, int $index): array
    {
        $name = trim((string) ($entry['name'] ?? ''));

        if ($name === '') {
            return ['status' => 'failed', 'name' => "#{$index}", 'detail' => 'Missing name'];
        }

        try {
            $type = DocumentTemplateType::from((string) ($entry['type'] ?? 'other'));
            $classification = DocumentClassification::from((string) ($entry['classification'] ?? 'internal'));
            $status = DocumentTemplateStatus::from((string) ($entry['status'] ?? 'draft'));

            $sourceFile = $this->resolveSource((string) ($entry['file'] ?? ''));

            if ($sourceFile === null) {
                return ['status' => 'failed', 'name' => $name, 'detail' => 'File not found'];
            }

            $code = filled($entry['code'] ?? null) ? (string) $entry['code'] : null;

            $existing = DocumentTemplate::query()
                ->when($code !== null, fn ($query) => $query->where('code', $code))
                ->when($code === null, fn ($query) => $query->where('name', $name))
                ->first();

            $sourceSha = hash_file('sha256', Storage::disk('private')->path($sourceFile)) ?: null;

            if ($existing !== null && $sourceSha !== null && $existing->source_sha256 === $sourceSha) {
                return ['status' => 'unchanged', 'name' => $name, 'detail' => 'Source unchanged'];
            }

            $data = [
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'version' => (string) ($entry['version'] ?? '1.0'),
                'status' => $status,
                'classification' => $classification,
                'source_file' => $sourceFile,
            ];

            $prepared = $manager->prepare($data, $existing, force: true);

            if ($this->option('dry-run')) {
                return [
                    'status' => 'parsed',
                    'name' => $name,
                    'detail' => sprintf(
                        '%d placeholder(s), %d heading(s)',
                        count($prepared['placeholders'] ?? []),
                        count($prepared['outline'] ?? []),
                    ),
                ];
            }

            if ($existing !== null) {
                $existing->update($prepared);

                return ['status' => 'updated', 'name' => $name, 'detail' => Str::limit($existing->source_sha256 ?? '', 16)];
            }

            $template = DocumentTemplate::create($prepared);

            return [
                'status' => 'imported',
                'name' => $name,
                'detail' => sprintf(
                    '%d placeholder(s), %d heading(s)',
                    count($template->placeholderList()),
                    count($template->outline ?? []),
                ),
            ];
        } catch (Throwable $exception) {
            return ['status' => 'failed', 'name' => $name, 'detail' => $exception->getMessage()];
        }
    }

    /**
     * Return a path relative to the private disk, copying the file in when the
     * manifest points outside it.
     */
    private function resolveSource(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        $disk = Storage::disk('private');

        if (is_file($file)) {
            $relative = 'document-templates/'.Str::slug(pathinfo($file, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! $disk->exists($relative) || hash_file('sha256', $disk->path($relative)) !== hash_file('sha256', $file)) {
                $disk->put($relative, file_get_contents($file) ?: '');
            }

            return $relative;
        }

        return $disk->exists($file) ? $file : null;
    }
}
