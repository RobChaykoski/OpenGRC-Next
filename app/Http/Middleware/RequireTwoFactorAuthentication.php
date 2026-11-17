<?php

namespace App\Http\Middleware;

use Closure;
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
     * Roles that must have confirmed 2FA.
     *
     * @var array<int, string>
     */
    protected array $privilegedRoles = ['Super Admin', 'Security Admin'];

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

        return redirect()
            ->route('filament.admin.pages.me')
            ->with('require_two_factor', true);
    }

    /**
     * Determine whether the user holds a role that requires 2FA.
     */
    protected function isPrivileged(object $user): bool
    {
        return method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($this->privilegedRoles);
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
