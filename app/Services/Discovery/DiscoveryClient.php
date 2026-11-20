<?php

namespace App\Services\Discovery;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only HTTP client for the OpenDiscoveryEngine console API.
 *
 * The console exposes two list endpoints: scans and findings. Every request is
 * a GET with a bearer token; `Http::grcReadOnly()` refuses anything else, keeps
 * TLS verification on, and disables redirects so a compromised endpoint cannot
 * bounce the token to another host. The client never writes to the engine.
 *
 * The configuration array is injected so tests and the sync command can supply
 * values without touching global config.
 */
class DiscoveryClient
{
    public const SCANS_PATH = '/api/v1/console/scans';

    public const FINDINGS_PATH = '/api/v1/console/findings';

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>|null  $config  Null reads `services.discovery`.
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->configFromApplication();
    }

    public static function fromConfig(): self
    {
        return new self;
    }

    /**
     * A console URL, a token and a tenant are all required. Without any one of
     * them the mirror has nothing to read, or would mix tenants, so the command
     * must fail visibly.
     */
    public function configured(): bool
    {
        return $this->baseUrl() !== null
            && $this->token() !== null
            && $this->tenant() !== null;
    }

    public function tenant(): ?string
    {
        $tenant = $this->config['tenant'] ?? null;

        if (! is_string($tenant)) {
            return null;
        }

        $tenant = trim($tenant);

        return $tenant === '' ? null : $tenant;
    }

    /**
     * Probe the console with the cheapest read the contract allows.
     *
     * Returns metadata about the reachable console; throws when the endpoint is
     * not configured, unreachable, or answers with a non-2xx status.
     *
     * @return array{base_url: string, tenant: string|null, status: int}
     */
    public function health(): array
    {
        $response = $this->request()->get(self::SCANS_PATH);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenDiscoveryEngine returned HTTP '.$response->status().' for '.self::SCANS_PATH.'.'
            );
        }

        return [
            'base_url' => (string) $this->baseUrl(),
            'tenant' => $this->tenant(),
            'status' => $response->status(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scans(): array
    {
        return $this->items(self::SCANS_PATH, 'scans');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findings(): array
    {
        return $this->items(self::FINDINGS_PATH, 'findings');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function items(string $path, string $key): array
    {
        $payload = $this->payload($path);

        if (array_is_list($payload)) {
            $items = $payload;
        } else {
            // Accept a bare list, a `data` envelope, or a keyed envelope without
            // guessing beyond the documented shape.
            $items = $payload['data'] ?? $payload[$key] ?? $payload['items'] ?? null;
        }

        if (! is_array($items)) {
            throw new RuntimeException(
                "OpenDiscoveryEngine returned no [{$key}] list for {$path}."
            );
        }

        $normalised = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $normalised[] = $item;
            }
        }

        return $normalised;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $path): array
    {
        $response = $this->request()->get($path);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenDiscoveryEngine returned HTTP '.$response->status()." for {$path}."
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                "OpenDiscoveryEngine returned a non-object payload for {$path}."
            );
        }

        return $payload;
    }

    protected function request(): PendingRequest
    {
        $baseUrl = $this->baseUrl();
        $token = $this->token();

        if ($baseUrl === null || $token === null) {
            throw new RuntimeException(
                'The OpenDiscoveryEngine console is not configured. '
                .'Set DISCOVERY_CONSOLE_URL, DISCOVERY_CONSOLE_TOKEN and DISCOVERY_CONSOLE_TENANT.'
            );
        }

        if (
            filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new RuntimeException(
                'The OpenDiscoveryEngine console URL must be a valid http or https URL.'
            );
        }

        return Http::grcReadOnly($baseUrl, $token)
            ->acceptJson()
            ->timeout($this->timeout())
            ->connectTimeout(5);
    }

    protected function baseUrl(): ?string
    {
        $value = $this->config['base_url'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rtrim(trim($value), '/');
    }

    protected function token(): ?string
    {
        $value = $this->config['token'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function timeout(): int
    {
        $timeout = $this->config['timeout'] ?? 15;

        return is_numeric($timeout) ? max(1, (int) $timeout) : 15;
    }

    /**
     * @return array<string, mixed>
     */
    protected function configFromApplication(): array
    {
        $config = config('services.discovery');

        return is_array($config) ? $config : [];
    }
}
