<?php

namespace App\Services\SupplyChain;

use App\Models\SbomDocument;
use App\Models\SoftwareComponent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

/**
 * Import a CycloneDX JSON SBOM.
 *
 * The document hash is the identity of the snapshot. Re-importing the same file
 * for the same application/asset updates the components instead of creating a
 * second inventory.
 */
class CycloneDxImporter
{
    /**
     * @return array{name: string, format: string, spec_version: string|null, component_count: int}
     */
    public function preview(string $path): array
    {
        $data = $this->decode($path);

        return [
            'name' => $this->name($path, $data),
            'format' => 'cyclonedx_json',
            'spec_version' => $this->specVersion($data),
            'component_count' => count($this->components($data)),
        ];
    }

    public function import(
        string $path,
        ?int $applicationId = null,
        ?int $assetId = null,
        ?int $userId = null,
    ): SbomDocument {
        $data = $this->decode($path);
        $hash = hash_file('sha256', $path);

        if (! is_string($hash)) {
            throw new InvalidArgumentException('The SBOM file could not be hashed.');
        }

        return DB::transaction(function () use ($path, $data, $hash, $applicationId, $assetId, $userId): SbomDocument {
            $document = SbomDocument::query()->firstOrNew([
                'document_sha256' => $hash,
                'application_id' => $applicationId,
                'asset_id' => $assetId,
            ]);

            $document->fill([
                'name' => $this->name($path, $data),
                'format' => 'cyclonedx_json',
                'spec_version' => $this->specVersion($data),
                'source' => $path,
                'imported_by_id' => $userId,
                'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
                'imported_at' => now(),
            ])->save();

            $keys = [];

            foreach ($this->components($data) as $component) {
                $key = $this->componentKey($component);

                if ($key === null) {
                    continue;
                }

                $keys[$key] = true;

                SoftwareComponent::query()->updateOrCreate(
                    [
                        'sbom_document_id' => $document->getKey(),
                        'component_key' => $key,
                    ],
                    [
                        'purl' => $this->string($component['purl'] ?? null),
                        'name' => $this->string($component['name'] ?? null) ?? $key,
                        'version' => $this->string($component['version'] ?? null),
                        'supplier' => $this->supplier($component),
                        'license' => $this->license($component),
                        'ecosystem' => Purl::ecosystem($this->string($component['purl'] ?? null)),
                        'scope' => $this->string($component['scope'] ?? null),
                        'type' => $this->string($component['type'] ?? null),
                        'metadata' => $component,
                    ],
                );
            }

            $document->forceFill(['component_count' => count($keys)])->save();

            return $document->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("The SBOM file is not readable: {$path}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The SBOM is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('The SBOM must be a JSON object.');
        }

        $format = $this->string($data['bomFormat'] ?? null);

        if ($format === null || strtolower($format) !== 'cyclonedx') {
            throw new InvalidArgumentException('Only CycloneDX JSON SBOMs are supported.');
        }

        if (isset($data['components']) && ! is_array($data['components'])) {
            throw new InvalidArgumentException('The SBOM components field must be a list.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function components(array $data): array
    {
        $components = $data['components'] ?? [];

        return array_values(array_filter($components, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function componentKey(array $component): ?string
    {
        $purl = $this->string($component['purl'] ?? null);

        if ($purl !== null) {
            return $purl;
        }

        $name = $this->string($component['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $version = $this->string($component['version'] ?? null);

        return $name.'@'.($version ?? 'unknown');
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function supplier(array $component): ?string
    {
        $supplier = $component['supplier'] ?? null;

        return is_array($supplier) ? $this->string($supplier['name'] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private function license(array $component): ?string
    {
        $licenses = $component['licenses'] ?? null;

        if (! is_array($licenses) || $licenses === []) {
            return null;
        }

        $first = $licenses[0];

        if (! is_array($first)) {
            return $this->string($first);
        }

        if (isset($first['expression'])) {
            return $this->string($first['expression']);
        }

        $license = $first['license'] ?? null;

        if (! is_array($license)) {
            return null;
        }

        return $this->string($license['id'] ?? null)
            ?? $this->string($license['name'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function name(string $path, array $data): string
    {
        $component = is_array($data['metadata']['component'] ?? null)
            ? $data['metadata']['component']
            : [];

        return $this->string($component['name'] ?? null) ?? basename($path);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function specVersion(array $data): ?string
    {
        return $this->string($data['specVersion'] ?? null);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
