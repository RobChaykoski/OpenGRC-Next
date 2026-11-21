<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * Adds security headers to responses including CSP, X-Content-Type-Options, etc.
     * CSP is configured for compatibility with Filament, Livewire, and Alpine.js.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers for all responses
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // A route that needs a stricter policy (for example the auditor portal,
        // whose URL contains a capability token) keeps the value it set.
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS - only in production, and only over HTTPS. Sending it over plain
        // HTTP is ignored by browsers; sending it from a development host can
        // poison localhost for months.
        if ($this->shouldSendHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        // Prevent MIME sniffing-based policy bypasses on downloads.
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // CSP only for HTML responses
        if ($this->shouldAddCsp($response)) {
            $response->headers->set('Content-Security-Policy', $this->buildPolicy($request));
        }

        return $response;
    }

    /**
     * Determine if the request should receive an HSTS header.
     */
    protected function shouldSendHsts(Request $request): bool
    {
        if (! config('security.hsts.enabled', true)) {
            return false;
        }

        if (! app()->environment('production')) {
            return false;
        }

        return $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');
    }

    /**
     * Build the HSTS header value.
     */
    protected function hstsValue(): string
    {
        $value = 'max-age='.(int) config('security.hsts.max_age', 15552000);

        if (config('security.hsts.include_subdomains', false)) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload', false)) {
            $value .= '; preload';
        }

        return $value;
    }

    /**
     * Determine if CSP header should be added to the response.
     */
    protected function shouldAddCsp(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') || empty($contentType);
    }

    /**
     * Build the Content-Security-Policy directive string.
     */
    protected function buildPolicy(Request $request): string
    {
        $storageEndpoints = $this->getStorageEndpoints();
        $formAction = $this->getFormAction($request);

        $directives = [
            // Default fallback - restrict to same origin
            "default-src 'self'",

            // Scripts: self + unsafe-inline/eval required for Livewire/Alpine.js
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",

            // Styles: self + unsafe-inline required for Filament's dynamic styles
            "style-src 'self' 'unsafe-inline'",

            // Fonts: self only; the brand font and trust-centre styles are built
            "font-src 'self' data:",

            // Images: self + data URIs + ui-avatars for default avatars + external storage
            "img-src 'self' data: blob: https://ui-avatars.com".$storageEndpoints,

            // Forms - relaxed for OAuth routes that need to redirect to external clients
            'form-action '.$formAction,

            // Prevent site from being embedded in frames (clickjacking protection)
            "frame-ancestors 'self'",

            // Base URI restriction
            "base-uri 'self'",

            // Connect (XHR/WebSocket): same origin for Livewire + external storage for file uploads
            "connect-src 'self'".$storageEndpoints,

            // Workers: blob URLs required for Filament file uploads
            "worker-src 'self' blob:",

            // Object/embed restrictions
            "object-src 'none'",

            // Upgrade insecure requests in production
            ...$this->productionDirectives(),
        ];

        return implode('; ', array_filter($directives));
    }

    /**
     * Get external storage endpoints for CSP allowlist.
     */
    protected function getStorageEndpoints(): string
    {
        $endpoints = [];

        // Check S3/DigitalOcean Spaces endpoint
        $defaultDisk = config('filesystems.default');

        if (in_array($defaultDisk, ['s3', 'digitalocean'])) {
            $endpoint = config("filesystems.disks.{$defaultDisk}.endpoint");
            $url = config("filesystems.disks.{$defaultDisk}.url");

            if ($endpoint) {
                // Extract hostname from endpoint URL
                $parsed = parse_url($endpoint);
                if (! empty($parsed['host'])) {
                    $endpoints[] = $parsed['scheme'].'://'.$parsed['host'];
                    // Also allow wildcard for regional subdomains
                    $endpoints[] = $parsed['scheme'].'://*.'.$parsed['host'];
                }
            }

            if ($url && $url !== config('app.url').'/media') {
                $parsed = parse_url($url);
                if (! empty($parsed['host'])) {
                    $endpoints[] = $parsed['scheme'].'://'.$parsed['host'];
                }
            }
        }

        return $endpoints ? ' '.implode(' ', array_unique($endpoints)) : '';
    }

    /**
     * Get form-action directive value based on the request path.
     *
     * OAuth authorization routes need relaxed form-action to allow
     * redirecting to external OAuth client redirect URIs.
     */
    protected function getFormAction(Request $request): string
    {
        // OAuth routes need to submit forms that redirect to external clients
        if ($request->is('oauth/*')) {
            return "'self' https:";
        }

        return "'self'";
    }

    /**
     * Get additional directives for production environment.
     */
    protected function productionDirectives(): array
    {
        if (app()->environment('production')) {
            return ['upgrade-insecure-requests'];
        }

        return [];
    }
}
