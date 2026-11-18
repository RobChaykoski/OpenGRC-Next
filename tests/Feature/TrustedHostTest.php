<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    #[Test]
    public function loopback_aliases_are_trusted_when_the_application_url_is_loopback(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $hosts = (new TrustHosts($this->app))->hosts();

        $this->assertContains('localhost', $hosts);
        $this->assertContains('127.0.0.1', $hosts);
    }

    #[Test]
    public function loopback_aliases_are_not_trusted_for_a_public_application_url(): void
    {
        config(['app.url' => 'https://grc.example.com']);

        $hosts = (new TrustHosts($this->app))->hosts();

        $this->assertNotContains('127.0.0.1', $hosts);
        $this->assertNotContains('localhost', $hosts);
        $this->assertSame(['^(.+\.)?grc\.example\.com$'], $hosts);
    }
}
