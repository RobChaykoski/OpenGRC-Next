<?php

namespace App\Services\SupplyChain;

use App\Models\SoftwareComponent;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read-only deps.dev client for package licenses and project Scorecard data.
 *
 * deps.dev covers public packages only. Internal or unsupported packages are
 * skipped; they are not guessed.
 */
class DepsDevClient
{
    /**
     * @return array<string, mixed>
     */
    public function packageVersion(string $system, string $name, string $version): array
    {
        return $this->get(
            '/v3/systems/'.rawurlencode($system).'/packages/'.rawurlencode($name).'/versions/'.rawurlencode($version)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function project(string $id): array
    {
        return $this->get('/v3/projects/'.rawurlencode($id));
    }

    /**
     * @return array{system: string, name: string, version: string}|null
     */
    public function queryFor(SoftwareComponent $component): ?array
    {
        $system = Purl::depsDevSystem($component->purl);
        $name = Purl::depsDevName($component);
        $version = is_string($component->version) && $component->version !== '' ? $component->version : null;

        if ($system === null || $name === null || $version === null) {
            return null;
        }

        return ['system' => $system, 'name' => $name, 'version' => $version];
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        $baseUrl = rtrim((string) config('deps_dev.base_url', 'https://api.deps.dev'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('The deps.dev base URL is not configured.');
        }

        $response = Http::grcReadOnly($baseUrl)
            ->acceptJson()
            ->timeout(max(1, min(120, (int) config('deps_dev.timeout', 20))))
            ->connectTimeout(5)
            ->get($path);

        $response->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("deps.dev returned an invalid payload for {$path}.");
        }

        return $payload;
    }
}
