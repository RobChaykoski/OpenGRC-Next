<?php

namespace App\Console\Commands;

use App\Enums\ModelArtifactFormat;
use App\Models\Application;
use App\Models\Asset;
use App\Models\ModelArtifact;
use App\Models\User;
use App\Services\Ai\ModelArtifactRegistrar;
use Illuminate\Console\Command;
use Throwable;

class RegisterModelArtifact extends Command
{
    protected $signature = 'opengrc:model-artifact:register
                            {path : Path to a model file or artifact directory}
                            {--name= : Display name; defaults to GGUF metadata or the path}
                            {--source= : Registry or vendor URL, or an internal source id}
                            {--revision= : Commit SHA, release id or content version}
                            {--license= : SPDX license identifier}
                            {--owner= : User ID for the artifact owner}
                            {--asset= : Asset ID that serves the artifact}
                            {--application= : Application ID that uses the artifact}
                            {--conversion-tool= : Tool that produced a GGUF or ONNX artifact}
                            {--conversion-version= : Version of the conversion tool}
                            {--scan-tool= : Tool that inspected the artifact}
                            {--scan-tool-version= : Version of the scan tool}
                            {--approve : Record a dated approval (refused for pickle files)}
                            {--approver= : User ID approving the artifact; defaults to --owner}
                            {--skip-hash : Read metadata only; provenance becomes partial}
                            {--dry-run : Inspect and report without writing}
                            {--json : Emit the result as JSON}';

    protected $description = 'Register an AI model artifact by format and provenance (ADR 0059)';

    public function handle(ModelArtifactRegistrar $registrar): int
    {
        $path = (string) $this->argument('path');
        $hashFiles = ! $this->option('skip-hash');

        try {
            $owner = $this->user('owner');
            $approver = $this->user('approver') ?? $owner;

            if ($this->option('approve') && $approver === null) {
                $this->error('Approval needs --approver or --owner so the decision has a person.');

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $preview = $registrar->preview($path, $hashFiles);

                return $this->reportPreview($preview);
            }

            $result = $registrar->register(
                $path,
                [
                    'name' => $this->option('name'),
                    'source' => $this->option('source'),
                    'revision' => $this->option('revision'),
                    'license' => $this->option('license'),
                    'owner_id' => $owner?->getKey(),
                    'asset_id' => $this->model('asset', Asset::class)?->getKey(),
                    'application_id' => $this->model('application', Application::class)?->getKey(),
                    'conversion_tool' => $this->option('conversion-tool'),
                    'conversion_version' => $this->option('conversion-version'),
                    'scan_tool' => $this->option('scan-tool'),
                    'scan_tool_version' => $this->option('scan-tool-version'),
                ],
                $hashFiles,
                (bool) $this->option('approve'),
                $approver?->getKey(),
            );
        } catch (Throwable $exception) {
            $this->error('Model registration failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        /** @var ModelArtifact $artifact */
        $artifact = $result['artifact'];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'id' => $artifact->getKey(),
                'name' => $artifact->name,
                'format' => $artifact->format?->value,
                'status' => $artifact->status?->value,
                'provenance_status' => $artifact->provenance_status,
                'content_hash' => $artifact->content_hash,
                'file_count' => $artifact->file_count,
                'created' => $result['created'],
                'findings' => $result['findings'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Artifact', $artifact->name],
                ['Format', $artifact->format?->getLabel() ?? 'Unknown'],
                ['Status', $artifact->status?->getLabel() ?? 'Unknown'],
                ['Provenance', $artifact->provenance_status],
                ['Files', $artifact->file_count],
                ['Content hash', $artifact->content_hash ?? 'not calculated'],
                ['Result', $result['created'] ? 'created' : 'updated'],
            ]
        );

        if ($result['findings'] === []) {
            $this->info('No format or provenance findings were raised.');

            return self::SUCCESS;
        }

        $this->warn('Open findings: '.implode(', ', $result['findings']));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function reportPreview(array $preview): int
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $preview['name']],
                ['Format', $preview['format'] instanceof ModelArtifactFormat ? $preview['format']->getLabel() : 'Unknown'],
                ['Architecture', $preview['architecture'] ?? 'not declared'],
                ['Quantization', $preview['quantization'] ?? 'not declared'],
                ['Context length', $preview['context_length'] ?? 'not declared'],
                ['Tensors', $preview['tensor_count'] ?? 'not read'],
                ['Files', $preview['file_count']],
                ['Total bytes', $preview['total_bytes']],
                ['Content hash', $preview['content_hash'] ?? 'not calculated'],
                ['License', $preview['license'] ?? 'not declared'],
            ]
        );

        foreach ($preview['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($preview['findings'] !== []) {
            $this->table(
                ['Finding', 'Severity'],
                array_map(
                    fn (string $code, array $finding): array => [$code, $finding['severity']->getLabel()],
                    array_keys($preview['findings']),
                    array_values($preview['findings']),
                )
            );
        }

        $this->comment('Dry run: nothing was written.');

        return self::SUCCESS;
    }

    private function user(string $option): ?User
    {
        $id = $this->option($option);

        if ($id === null) {
            return null;
        }

        $user = User::query()->find((int) $id);

        if ($user === null) {
            throw new \RuntimeException("No user found for --{$option}={$id}.");
        }

        return $user;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel|null
     */
    private function model(string $option, string $class): ?object
    {
        $id = $this->option($option);

        if ($id === null) {
            return null;
        }

        $model = $class::query()->find((int) $id);

        if ($model === null) {
            throw new \RuntimeException("No record found for --{$option}={$id}.");
        }

        return $model;
    }
}
