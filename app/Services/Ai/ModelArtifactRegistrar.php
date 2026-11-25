<?php

namespace App\Services\Ai;

use App\Enums\EvidenceSeverity;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\ModelArtifactFormat;
use App\Enums\ModelArtifactStatus;
use App\Models\Finding;
use App\Models\ModelArtifact;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Register a model artifact and turn format and provenance gaps into findings
 * (ADR 0059).
 *
 * Registration is idempotent by content hash. Re-registering the same bytes
 * updates the existing row; the same declared identity with different bytes is
 * a substitution finding.
 */
class ModelArtifactRegistrar
{
    public function __construct(
        protected ModelArtifactInspector $inspector,
        protected ModelRuntimeBaseline $runtimeBaseline,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function preview(string $path, bool $hashFiles = true, array $attributes = []): array
    {
        $inspection = $this->inspector->inspect($path, $hashFiles);
        $inspection['runtime'] = $attributes['runtime'] ?? null;
        $inspection['runtime_version'] = $attributes['runtime_version'] ?? null;
        $inspection['findings'] = $this->pendingFindings($inspection);

        return $inspection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{artifact: ModelArtifact, created: bool, findings: array<int, string>}
     */
    public function register(
        string $path,
        array $attributes = [],
        bool $hashFiles = true,
        bool $approve = false,
        ?int $approvedBy = null,
    ): array {
        $inspection = $this->inspector->inspect($path, $hashFiles);
        $existing = $this->existingArtifact($inspection, $attributes);

        $substitution = $existing !== null
            && $existing->content_hash !== null
            && $inspection['content_hash'] !== null
            && $existing->content_hash !== $inspection['content_hash'];

        $templateDrift = $existing !== null
            && $existing->chat_template_hash !== null
            && $inspection['chat_template_hash'] !== null
            && $existing->chat_template_hash !== $inspection['chat_template_hash'];

        if ($approve && $inspection['format'] === ModelArtifactFormat::Pytorch) {
            throw new RuntimeException('A pickle-format model cannot be approved. Convert it to safetensors or record a sandboxed scan first.');
        }

        if ($approve && blank($inspection['content_hash'])) {
            throw new RuntimeException('A model artifact cannot be approved without a content hash. Re-run without --skip-hash so the approved bytes are identified.');
        }

        $artifact = $existing ?? new ModelArtifact;
        $created = ! $artifact->exists;

        $artifact->fill($this->attributes($inspection, $attributes, $artifact));

        if ($substitution) {
            // A changed artifact under the same declared identity loses its
            // approval until a person reviews the new bytes.
            $artifact->status = ModelArtifactStatus::Proposed;
            $artifact->approved_by = null;
            $artifact->approved_at = null;
        }

        if ($approve && ! $substitution) {
            $artifact->status = ModelArtifactStatus::Approved;
            $artifact->approved_by = $approvedBy;
            $artifact->approved_at = now();
        }

        $artifact->provenance_status = $this->provenanceStatus($artifact);
        $artifact->provenance_verified_at = $artifact->provenance_status === 'verified'
            ? ($artifact->provenance_verified_at ?? now())
            : null;
        $artifact->save();

        $artifact->files()->delete();
        $artifact->files()->createMany($inspection['files']);

        // The stored record, not the raw scan, decides whether a conversion or
        // license gap is still open.
        $inspection['source'] = $artifact->source;
        $inspection['revision'] = $artifact->revision;
        $inspection['content_hash'] = $artifact->content_hash;
        $inspection['conversion_tool'] = $artifact->conversion_tool;
        $inspection['license'] = $artifact->license;
        $inspection['runtime'] = $artifact->runtime;
        $inspection['runtime_version'] = $artifact->runtime_version;

        $current = [];

        foreach ($this->pendingFindings($inspection, $substitution, $templateDrift) as $code => $finding) {
            $current[] = $code;
            $this->raise($artifact, $code, $finding);
        }

        $this->resolveCleared($artifact, $current);

        return [
            'artifact' => $artifact->refresh(),
            'created' => $created,
            'findings' => $current,
        ];
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, mixed>  $attributes
     */
    private function existingArtifact(array $inspection, array $attributes): ?ModelArtifact
    {
        if (filled($inspection['content_hash'])) {
            $match = ModelArtifact::query()
                ->where('content_hash', $inspection['content_hash'])
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        $name = $attributes['name'] ?? $inspection['name'];

        $query = ModelArtifact::query()->where('name', $name);

        foreach (['source', 'revision'] as $field) {
            $value = $attributes[$field] ?? null;

            if (filled($value)) {
                $query->where($field, $value);
            } else {
                $query->whereNull($field);
            }
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $inspection, array $attributes, ModelArtifact $artifact): array
    {
        return [
            'name' => $attributes['name'] ?? $artifact->name ?? $inspection['name'],
            'source' => $attributes['source'] ?? $artifact->source,
            'revision' => $attributes['revision'] ?? $artifact->revision,
            'format' => $inspection['format'],
            'license' => $attributes['license'] ?? $inspection['license'] ?? $artifact->license,
            'architecture' => $inspection['architecture'] ?? $artifact->architecture,
            'quantization' => $inspection['quantization'] ?? $artifact->quantization,
            'context_length' => $inspection['context_length'] ?? $artifact->context_length,
            'tensor_count' => $inspection['tensor_count'] ?? $artifact->tensor_count,
            'file_count' => $inspection['file_count'],
            'total_bytes' => $inspection['total_bytes'],
            'content_hash' => $inspection['content_hash'],
            'tokenizer_hash' => $inspection['tokenizer_hash'],
            'chat_template_hash' => $inspection['chat_template_hash'] ?? $artifact->chat_template_hash,
            'conversion_tool' => $attributes['conversion_tool'] ?? $artifact->conversion_tool,
            'conversion_version' => $attributes['conversion_version'] ?? $artifact->conversion_version,
            'conversion_recorded_at' => filled($attributes['conversion_tool'] ?? null)
                ? ($artifact->conversion_recorded_at ?? now())
                : $artifact->conversion_recorded_at,
            'owner_id' => $attributes['owner_id'] ?? $artifact->owner_id,
            'asset_id' => $attributes['asset_id'] ?? $artifact->asset_id,
            'application_id' => $attributes['application_id'] ?? $artifact->application_id,
            'runtime' => filled($attributes['runtime'] ?? null)
                ? strtolower(trim((string) $attributes['runtime']))
                : $artifact->runtime,
            'runtime_version' => filled($attributes['runtime_version'] ?? null)
                ? trim((string) $attributes['runtime_version'])
                : $artifact->runtime_version,
            'scan_tool' => $attributes['scan_tool'] ?? $artifact->scan_tool,
            'scan_tool_version' => $attributes['scan_tool_version'] ?? $artifact->scan_tool_version,
            // Registration is not a scan. Only a named scan tool moves the
            // timestamp; otherwise a later inspection would look like evidence
            // that the artifact was scanned.
            'last_scanned_at' => filled($attributes['scan_tool'] ?? null)
                ? now()
                : $artifact->last_scanned_at,
            'metadata' => $inspection['metadata'],
            'notes' => $attributes['notes'] ?? $artifact->notes,
        ];
    }

    private function provenanceStatus(ModelArtifact $artifact): string
    {
        $known = collect([$artifact->source, $artifact->revision, $artifact->content_hash])
            ->filter(fn (mixed $value): bool => filled($value))
            ->count();

        return match ($known) {
            3 => 'verified',
            0 => 'unverified',
            default => 'partial',
        };
    }

    /**
     * @return array<string, array{severity: EvidenceSeverity, title: string, description: string}>
     */
    private function pendingFindings(
        array $inspection,
        bool $substitution = false,
        bool $templateDrift = false,
    ): array {
        $findings = [];
        $name = $inspection['name'];
        $format = $inspection['format'];

        if ($format instanceof ModelArtifactFormat && $format->executesOnLoad()) {
            $findings['unsafe-serialization'] = [
                'severity' => EvidenceSeverity::High,
                'title' => "Unsafe model serialization: {$name}",
                'description' => 'The artifact is a pickle-based PyTorch file. Loading it can execute arbitrary code. Convert it to safetensors, or record an isolated scan and the conversion before approval.',
            ];
        }

        $missing = [];

        foreach (['source', 'revision', 'content_hash'] as $field) {
            if (blank($inspection[$field] ?? null)) {
                $missing[] = $field === 'content_hash' ? 'content hash' : $field;
            }
        }

        if ($missing !== []) {
            $findings['provenance'] = [
                'severity' => EvidenceSeverity::Medium,
                'title' => "Unverifiable model provenance: {$name}",
                'description' => 'The artifact is missing '.implode(', ', $missing).'. A self-declared model name is not provenance; record the source revision and a per-file hash.',
            ];
        }

        if (blank($inspection['license'] ?? null)) {
            $findings['license'] = [
                'severity' => EvidenceSeverity::Medium,
                'title' => "Model license not recorded: {$name}",
                'description' => 'No license was found in the artifact metadata and none was supplied. Record the SPDX license before approval.',
            ];
        }

        if (in_array($format, [ModelArtifactFormat::Gguf, ModelArtifactFormat::Onnx], true)
            && blank($inspection['conversion_tool'] ?? null)) {
            $findings['conversion'] = [
                'severity' => EvidenceSeverity::Medium,
                'title' => "Unrecorded model conversion: {$name}",
                'description' => 'The artifact is a derived format and the conversion tool is unknown. Record the conversion tool and version, or mark the provenance as deliberately unknown.',
            ];
        }

        if ($substitution) {
            $findings['substitution'] = [
                'severity' => EvidenceSeverity::High,
                'title' => "Model artifact substitution: {$name}",
                'description' => 'The declared source and revision were already registered with different content. Review the new bytes and re-approve the artifact.',
            ];
        }

        if ($templateDrift) {
            $findings['template-drift'] = [
                'severity' => EvidenceSeverity::Medium,
                'title' => "Model behaviour drift: {$name}",
                'description' => 'The chat template changed without a new approval. The weights may be identical, but prompts render differently.',
            ];
        }

        $runtimeFinding = $this->runtimeBaseline->finding(
            (string) $name,
            is_string($inspection['runtime'] ?? null) ? $inspection['runtime'] : null,
            is_string($inspection['runtime_version'] ?? null) ? $inspection['runtime_version'] : null,
        );

        if ($runtimeFinding !== null) {
            $findings['runtime'] = $runtimeFinding;
        }

        return $findings;
    }

    /**
     * Record or change the serving runtime without re-reading the artifact.
     *
     * A runtime upgrade changes deployment context, not the bytes, so this is
     * separate from registration: re-hashing a large model to record a version
     * bump would discourage the record being kept current.
     *
     * @return string|null the finding code raised, or null when inside baseline
     */
    public function recordRuntime(ModelArtifact $artifact, string $runtime, ?string $version): ?string
    {
        $artifact->forceFill([
            'runtime' => strtolower(trim($runtime)),
            'runtime_version' => filled($version) ? trim((string) $version) : null,
        ])->save();

        $finding = $this->runtimeBaseline->finding(
            (string) $artifact->name,
            is_string($artifact->runtime) ? $artifact->runtime : null,
            is_string($artifact->runtime_version) ? $artifact->runtime_version : null,
        );

        if ($finding === null) {
            $this->resolveCode($artifact, 'runtime');

            return null;
        }

        $this->raise($artifact, 'runtime', $finding);

        return 'runtime';
    }

    /**
     * @param  array{severity: EvidenceSeverity, title: string, description: string}  $definition
     */
    private function raise(ModelArtifact $artifact, string $code, array $definition): void
    {
        $dedupeKey = "model-artifact:{$artifact->getKey()}:{$code}";
        $finding = Finding::query()->firstOrNew(['dedupe_key' => $dedupeKey]);
        $status = $finding->status === FindingStatus::Accepted
            ? FindingStatus::Accepted
            : FindingStatus::Open;

        $finding->fill([
            'title' => $definition['title'],
            'description' => $definition['description'],
            'severity' => $definition['severity'],
            'status' => $status,
            'source' => FindingSource::Automated,
            'asset_id' => $artifact->asset_id,
            'model_artifact_id' => $artifact->getKey(),
            'owner_id' => $finding->owner_id ?? $artifact->owner_id,
            'dedupe_key' => $dedupeKey,
            'occurrences' => ($finding->occurrences ?? 0) + 1,
            'identified_at' => $finding->identified_at ?? now(),
            'last_seen_at' => now(),
            'due_at' => $finding->due_at ?? $this->dueAt($definition['severity']),
        ])->save();
    }

    /**
     * Resolve automated findings whose condition is no longer present.
     *
     * @param  array<int, string>  $current
     */
    private function resolveCleared(ModelArtifact $artifact, array $current): void
    {
        $prefix = "model-artifact:{$artifact->getKey()}:";

        Finding::query()
            ->where('model_artifact_id', $artifact->getKey())
            ->where('source', FindingSource::Automated->value)
            ->whereIn('status', [FindingStatus::Open->value, FindingStatus::InProgress->value])
            ->where('dedupe_key', 'like', $prefix.'%')
            ->get()
            ->each(function (Finding $finding) use ($prefix, $current): void {
                $code = substr((string) $finding->dedupe_key, strlen($prefix));

                if (in_array($code, $current, true)) {
                    return;
                }

                $finding->fill([
                    'status' => FindingStatus::Resolved,
                    'resolved_at' => now(),
                    'resolution_notes' => 'The condition is no longer present on the latest registration.',
                ])->save();
            });
    }

    /**
     * Resolve one automated finding by code when its condition has cleared.
     */
    private function resolveCode(ModelArtifact $artifact, string $code): void
    {
        Finding::query()
            ->where('model_artifact_id', $artifact->getKey())
            ->where('source', FindingSource::Automated->value)
            ->where('dedupe_key', "model-artifact:{$artifact->getKey()}:{$code}")
            ->whereIn('status', [FindingStatus::Open->value, FindingStatus::InProgress->value])
            ->get()
            ->each(function (Finding $finding): void {
                $finding->fill([
                    'status' => FindingStatus::Resolved,
                    'resolved_at' => now(),
                    'resolution_notes' => 'The condition is no longer present on the current record.',
                ])->save();
            });
    }

    private function dueAt(EvidenceSeverity $severity): CarbonImmutable
    {
        return match ($severity) {
            EvidenceSeverity::Critical => CarbonImmutable::now()->addDays(7),
            EvidenceSeverity::High => CarbonImmutable::now()->addDays(30),
            EvidenceSeverity::Medium => CarbonImmutable::now()->addDays(60),
            EvidenceSeverity::Low => CarbonImmutable::now()->addDays(90),
        };
    }
}
