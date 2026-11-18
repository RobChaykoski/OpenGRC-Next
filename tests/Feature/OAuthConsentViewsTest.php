<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Passport is headless and registers browser routes without views. If the
 * response contracts are not bound, /oauth/authorize and /oauth/device return
 * a 500 before authentication is even checked.
 */
class OAuthConsentViewsTest extends TestCase
{
    #[Test]
    public function the_device_code_page_renders(): void
    {
        $this->get('/oauth/device')
            ->assertOk()
            ->assertSee('Device code');
    }

    #[Test]
    public function the_authorize_endpoint_returns_a_client_error_not_a_crash(): void
    {
        $response = $this->get('/oauth/authorize');

        $this->assertLessThan(500, $response->getStatusCode());
    }
}
