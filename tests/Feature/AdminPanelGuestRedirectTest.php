<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin panel has no login page of its own: the app panel owns the login
 * screen and the admin panel is reached after signing in. A guest must be sent
 * there rather than receiving a bare 401 or a 404.
 */
class AdminPanelGuestRedirectTest extends TestCase
{
    #[Test]
    public function a_guest_opening_the_admin_panel_is_sent_to_the_application_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('filament.app.auth.login'));
    }

    #[Test]
    public function the_admin_login_url_redirects_to_the_application_login(): void
    {
        $this->get('/admin/login')
            ->assertRedirect(route('filament.app.auth.login'));
    }
}
