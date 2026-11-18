<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Send guests of the admin panel to the application login page.
 *
 * The admin panel deliberately has no login screen of its own: the app panel
 * owns the branded login (SSO providers, passkeys, password rules), and the
 * admin panel is reached after signing in. Filament's default middleware
 * returns a bare 401 when the current panel has no login route, which leaves a
 * guest with no way forward. Point the redirect at the app panel instead.
 */
class AuthenticateAdminPanel extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        return Filament::getPanel('app')->getLoginUrl();
    }
}
