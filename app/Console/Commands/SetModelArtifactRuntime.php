<?php

namespace App\Console\Commands;

use App\Models\ModelArtifact;
use App\Services\Ai\ModelArtifactRegistrar;
use Illuminate\Console\Command;

/**
 * Record or change the inference runtime that serves a registered artifact
 * (ADR 0059, section 5).
 *
 * The runtime is deployment context, not artifact bytes, so it is recorded
 * without re-reading the model. The unsupported-runtime finding is re-evaluated
 * against config/ai_models.php on every change.
 */
class SetModelArtifactRuntime extends Command
{
    protected $signature = 'opengrc:model-artifact:runtime
                            {artifact : Model artifact ID}
                            {--runtime= : Inference runtime identifier (for example vllm)}
                            {--runtime-version= : Runtime version (for example 0.6.1 or b3600)}
                            {--json : Emit the result as JSON}';

    protected $description = 'Record the inference runtime and version for a model artifact';

    public function handle(ModelArtifactRegistrar $registrar): int
    {
        $artifact = ModelArtifact::query()->find((int) $this->argument('artifact'));

        if ($artifact === null) {
            $this->error('No model artifact found for that ID.');

            return self::FAILURE;
        }

        $runtime = trim((string) $this->option('runtime'));

        if ($runtime === '') {
            $this->error('Pass --runtime so the record names the inference runtime.');

            return self::FAILURE;
        }

        $version = filled($this->option('runtime-version'))
            ? trim((string) $this->option('runtime-version'))
            : null;

        $finding = $registrar->recordRuntime($artifact, $runtime, $version);
        $artifact->refresh();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'id' => $artifact->getKey(),
                'name' => $artifact->name,
                'runtime' => $artifact->runtime,
                'runtime_version' => $artifact->runtime_version,
                'finding' => $finding,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Artifact', $artifact->name],
                ['Runtime', $artifact->runtime],
                ['Version', $artifact->runtime_version ?? 'not recorded'],
                ['Finding', $finding ?? 'none'],
            ]
        );

        if ($finding !== null) {
            $this->warn('The runtime is outside the supported baseline; a finding was raised or updated.');
        } else {
            $this->info('The runtime is within the supported baseline.');
        }

        return self::SUCCESS;
    }
}
