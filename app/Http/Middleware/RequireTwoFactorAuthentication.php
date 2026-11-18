<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require confirmed two-factor authentication for privileged roles.
 *
 * Availability of 2FA is not the same as use of 2FA. The users who can change
 * permissions, export data, and mint API tokens are the ones whose account
 * compromise matters most, so they are the ones required to enrol.
 */
class RequireTwoFactorAuthentication
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->isPrivileged($user)) {
            return $next($request);
        }

        if (! method_exists($user, 'hasConfirmedTwoFactor')) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor() || $this->isExempt($request)) {
            return $next($request);
        }

        // Keep the user in the panel they came from. An admin who opens /app
        // must not be bounced into /admin just to enrol.
        $route = Filament::getCurrentPanel()?->getId() === 'app'
            ? 'filament.app.pages.me'
            : 'filament.admin.pages.me';

        return new RedirectResponse(
            route($route, ['require_two_factor' => 1])
        );
    }

    /**
     * Determine whether the user holds the role that requires 2FA.
     */
    protected function isPrivileged(object $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Routes that must remain reachable so the user can actually enrol.
     */
    protected function isExempt(Request $request): bool
    {
        return $request->routeIs(
            'filament.admin.pages.me',
            'filament.app.pages.me',
            'filament.admin.auth.two-factor',
            'filament.app.auth.two-factor',
            'filament.admin.auth.logout',
            'filament.app.auth.logout',
        );
    }
}
