<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Outbound HTTP for evidence connectors.
 *
 * A connector may pull data from a vendor API; it must never push a change back.
 * The macro disables redirects so a compromised endpoint cannot bounce a bearer
 * token to another host, keeps TLS verification on, and refuses any method
 * other than GET or HEAD before the request leaves the process.
 */
final class ReadOnlyHttp
{
    /** @var array<int, string> */
    public const ALLOWED_METHODS = ['GET', 'HEAD'];

    public static function register(): void
    {
        Http::macro('grcReadOnly', function (string $baseUrl, ?string $token = null, array $options = []) {
            $request = Http::baseUrl($baseUrl)
                ->withOptions(array_merge($options, [
                    'allow_redirects' => false,
                    'verify' => true,
                ]));

            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            return $request->withRequestMiddleware(
                static fn (RequestInterface $request): RequestInterface => ReadOnlyHttp::guard($request)
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
}
