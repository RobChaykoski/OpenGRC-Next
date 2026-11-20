<?php

namespace App\Services\Secrets;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Read-only OpenBao metadata client.
 *
 * It reads KV v2 metadata only: version numbers and created times. It never
 * requests the secret data path, so a token leak cannot read a secret through
 * this client. The token is sent as X-Vault-Token, not a bearer token.
 */
class OpenBaoClient
{
    public function configured(): bool
    {
        return $this->baseUrl() !== null && $this->token() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function kvMetadata(string $mount, string $path): array
    {
        $response = $this->client()
            ->get('/v1/'.rawurlencode($mount).'/metadata/'.ltrim($path, '/'));

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('OpenBao returned an invalid metadata payload.');
        }

        return $payload;
    }

    /**
     * The creation time of the current KV v2 version, when present.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function rotationDate(array $metadata): ?CarbonImmutable
    {
        $data = is_array($metadata['data'] ?? null) ? $metadata['data'] : [];
        $current = $data['current_version'] ?? null;
        $versions = is_array($data['versions'] ?? null) ? $data['versions'] : [];
        $version = is_numeric($current) ? ($versions[(string) $current] ?? null) : null;
        $created = is_array($version) ? ($version['created_time'] ?? null) : null;

        $created ??= $data['updated_time'] ?? $data['created_time'] ?? null;

        if (! is_string($created) || $created === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($created)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{mount: string, path: string}|null
     */
    public function parseReference(string $reference): ?array
    {
        if (! str_starts_with($reference, 'openbao://')) {
            return null;
        }

        $remainder = ltrim(substr($reference, strlen('openbao://')), '/');

        if ($remainder === '') {
            return null;
        }

        $segments = explode('/', $remainder);
        $defaultMount = (string) config('services.openbao.kv_mount', 'secret');

        /*
         * Preferred form: openbao://<mount>/<path>. If the first segment is not
         * the configured mount, treat the whole remainder as the path in the
         * default mount, so openbao://opengrc/wazuh-api works too.
         */
        if (count($segments) >= 2 && $segments[0] === $defaultMount) {
            $mount = array_shift($segments);
            $path = implode('/', $segments);
        } else {
            $mount = $defaultMount;
            $path = $remainder;
        }

        if (str_starts_with($path, 'data/')) {
            $path = substr($path, strlen('data/'));
        }

        return $path === '' ? null : ['mount' => $mount, 'path' => $path];
    }

    protected function client(): PendingRequest
    {
        $baseUrl = $this->baseUrl();
        $token = $this->token();

        if ($baseUrl === null || $token === null) {
            throw new RuntimeException(
                'OpenBao is not configured. Set OPENBAO_URL and OPENBAO_TOKEN.'
            );
        }

        return Http::grcReadOnly($baseUrl)
            ->withHeaders(['X-Vault-Token' => $token])
            ->acceptJson()
            ->timeout(max(1, min(120, (int) config('services.openbao.timeout', 15))))
            ->connectTimeout(5);
    }

    protected function baseUrl(): ?string
    {
        $value = config('services.openbao.base_url');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rtrim(trim($value), '/');
    }

    protected function token(): ?string
    {
        $value = config('services.openbao.token');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
