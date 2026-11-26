<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    #[Test]
    public function it_sends_hsts_in_production_over_https(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://grc.example.com', 'security.hsts.enabled' => true]);

        $response = $this->handle('https://grc.example.com/app/login');

        $this->assertSame('max-age=15552000', $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function it_does_not_send_hsts_outside_production(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $response = $this->handle('https://grc.example.com/app/login');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function it_can_include_subdomains_when_configured(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.url' => 'https://grc.example.com',
            'security.hsts.include_subdomains' => true,
            'security.hsts.max_age' => 31536000,
        ]);

        $response = $this->handle('https://grc.example.com/app/login');

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security')
        );
    }

    #[Test]
    public function it_sets_baseline_headers_on_non_html_responses(): void
    {
        $response = $this->handle('https://grc.example.com/api/thing', 'application/json');

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function session_encryption_is_on_by_default(): void
    {
        $this->assertTrue((bool) config('session.encrypt'));
    }

    #[Test]
    public function it_does_not_allow_google_fonts(): void
    {
        $response = $this->handle('https://grc.example.com/app/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('fonts.gstatic.com', $csp);
    }

    #[Test]
    public function it_does_not_allow_third_party_trust_center_assets(): void
    {
        $response = $this->handle('https://grc.example.com/trust');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('cdn.tailwindcss.com', $csp);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $csp);
        $this->assertStringNotContainsString('fonts.bunny.net', $csp);
    }

    #[Test]
    public function it_keeps_a_stricter_referrer_policy_set_by_the_route(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('https://grc.example.com/audit-portal/token', 'GET');
        $response = new Response('<html></html>', 200, [
            'Content-Type' => 'text/html',
            'Referrer-Policy' => 'no-referrer',
        ]);

        $result = $middleware->handle($request, fn () => $response);

        $this->assertSame('no-referrer', $result->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function it_does_not_upgrade_insecure_requests_on_a_plain_http_production_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'http://172.16.16.88:8080']);

        $response = $this->handle('http://172.16.16.88:8080/app/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        // A browser would rewrite every asset to https:// on port 8080, fail
        // the TLS handshake, and render the login page unstyled.
        $this->assertStringNotContainsString('upgrade-insecure-requests', $csp);
    }

    #[Test]
    public function it_upgrades_insecure_requests_on_https_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://grc.example.com']);

        $response = $this->handle('https://grc.example.com/app/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    protected function handle(string $url, string $contentType = 'text/html'): Response
    {
        $middleware = new SecurityHeaders;
        $request = Request::create($url, 'GET');
        $response = new Response('<html></html>', 200, ['Content-Type' => $contentType]);

        return $middleware->handle($request, fn () => $response);
    }
}
