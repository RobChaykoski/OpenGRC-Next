<?php

namespace App\Services\SupplyChain;

use App\Models\SoftwareComponent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Read-only client for the OSV.dev vulnerability database.
 *
 * The batch endpoint is a POST query; every follow-up advisory read is a GET.
 * No token is used, and the transport guard refuses any other method or path.
 */
class OsvClient
{
    public const QUERY_BATCH_PATH = '/v1/querybatch';

    public const VULN_PATH = '/v1/vulns/';

    public function queryBatch(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        $response = $this->client()
            ->post(self::QUERY_BATCH_PATH, ['queries' => $queries]);

        if (! $response->successful()) {
            throw new RuntimeException('OSV returned HTTP '.$response->status().' for the batch query.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['results'] ?? null)) {
            throw new InvalidArgumentException('OSV returned an invalid batch response.');
        }

        return $payload['results'];
    }

    /**
     * @return array<string, mixed>
     */
    public function vulnerability(string $id): array
    {
        $response = $this->client()
            ->get(self::VULN_PATH.rawurlencode($id));

        if (! $response->successful()) {
            throw new RuntimeException("OSV returned HTTP {$response->status()} for {$id}.");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new InvalidArgumentException("OSV returned an invalid advisory for {$id}.");
        }

        return $payload;
    }

    /**
     * Build OSV queries for components that have a supported purl ecosystem.
     *
     * @param  Collection<int, SoftwareComponent>  $components
     * @return array<int, array{query: array<string, mixed>, component: SoftwareComponent}>
     */
    public function queries(Collection $components): array
    {
        $queries = [];

        foreach ($components as $component) {
            $query = Purl::osvQuery($component);

            if ($query !== null) {
                $queries[] = ['query' => $query, 'component' => $component];
            }
        }

        return $queries;
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('osv.base_url', 'https://api.osv.dev'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('The OSV API base URL is not configured.');
        }

        return Http::grcReadQuery($baseUrl, self::QUERY_BATCH_PATH)
            ->acceptJson()
            ->timeout(max(1, min(120, (int) config('osv.timeout', 20))))
            ->connectTimeout(5);
    }
}
