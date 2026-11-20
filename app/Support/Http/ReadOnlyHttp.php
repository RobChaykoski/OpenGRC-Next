<?php

namespace App\Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Outbound HTTP for evidence connectors.
 *
 * A connector may pull data from a vendor API; it must never push a change back.
 * The macro disables redirects so a compromised endpoint cannot bounce a bearer
 * token to another host, keeps TLS verification on, and refuses any method
 * other than GET or HEAD before the request leaves the process.
 *
 * An API that mints a short-lived token with a POST needs one narrow exception.
 * `grcAuthenticate()` permits a POST to the single path the connector names,
 * with the same transport guards; it is for authentication only (ADR 0017).
 */
final class ReadOnlyHttp
{
    /** @var array<int, string> */
    public const ALLOWED_METHODS = ['GET', 'HEAD'];

    public static function register(): void
    {
        Http::macro('grcReadOnly', function (string $baseUrl, ?string $token = null, array $options = [], ?string $caBundle = null) {
            $request = Http::baseUrl($baseUrl)
                ->withOptions(array_merge($options, [
                    'allow_redirects' => false,
                    // A CA bundle keeps verification on; it never disables it.
                    'verify' => $caBundle ?? true,
                ]));

            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            return $request->withRequestMiddleware(
                static fn (RequestInterface $request): RequestInterface => ReadOnlyHttp::guard($request)
            );
        });

        Http::macro('grcAuthenticate', function (string $baseUrl, string $path, ?string $caBundle = null): PendingRequest {
            if (! str_starts_with($path, '/')) {
                throw new InvalidArgumentException('The authentication path must start with "/".');
            }

            return Http::baseUrl($baseUrl)
                ->withOptions([
                    'allow_redirects' => false,
                    // A CA bundle keeps verification on; it never disables it.
                    'verify' => $caBundle ?? true,
                ])
                ->acceptJson()
                ->withRequestMiddleware(
                    static fn (RequestInterface $request): RequestInterface => ReadOnlyHttp::guardAuthentication($request, $path)
                );
        });

        /*
         * Some read-only APIs use POST for a query (for example an OSV
         * querybatch). Permit POST to the single query path the caller names,
         * and keep GET/HEAD available for follow-up reads.
         */
        Http::macro('grcReadQuery', function (string $baseUrl, string $path, ?string $caBundle = null): PendingRequest {
            if (! str_starts_with($path, '/')) {
                throw new InvalidArgumentException('The query path must start with "/".');
            }

            return Http::baseUrl($baseUrl)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => $caBundle ?? true,
                ])
                ->acceptJson()
                ->withRequestMiddleware(
                    static fn (RequestInterface $request): RequestInterface => ReadOnlyHttp::guardReadQuery($request, $path)
                );
        });
    }

    public static function guard(RequestInterface $request): RequestInterface
    {
        $method = strtoupper($request->getMethod());

        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new RuntimeException(
                "The evidence collector only permits read requests; {$method} was blocked."
            );
        }

        return $request;
    }

    /**
     * Permit exactly one POST, to the authentication path the connector named.
     *
     * The path comes from connector code, never from source configuration, so
     * an operator cannot turn this into a general write client.
     */
    public static function guardAuthentication(RequestInterface $request, string $path): RequestInterface
    {
        $method = strtoupper($request->getMethod());
        $requestPath = $request->getUri()->getPath();

        if ($method !== 'POST') {
            throw new RuntimeException(
                "The evidence authentication client only permits POST; {$method} was blocked."
            );
        }

        if (! str_ends_with($requestPath, $path)) {
            throw new RuntimeException(
                "The evidence authentication client only permits POST to {$path}; {$requestPath} was blocked."
            );
        }

        return $request;
    }

    /**
     * Permit a POST query to the named path, and GET/HEAD for follow-up reads.
     *
     * The path comes from service code, never from a source configuration, so a
     * query API cannot become a general write client.
     */
    public static function guardReadQuery(RequestInterface $request, string $path): RequestInterface
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, self::ALLOWED_METHODS, true)) {
            return $request;
        }

        $requestPath = $request->getUri()->getPath();

        if ($method !== 'POST' || ! str_ends_with($requestPath, $path)) {
            throw new RuntimeException(
                "The read-query client only permits POST to {$path}; {$method} {$requestPath} was blocked."
            );
        }

        return $request;
    }
}
