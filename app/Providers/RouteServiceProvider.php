<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $perMinute = $request->routeIs('access.decisions.store')
                ? max(1, (int) config('access_governance.decision.rate_limit_per_minute', 600))
                : 60;

            return Limit::perMinute($perMinute)->by($this->throttleKey($request));
        });

        RateLimiter::for('global', function (Request $request) {
            // Skip rate limiting for authenticated users and Livewire requests
            if ($request->user() || $request->is('livewire/*')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Throttle a machine caller by its token where possible. One token is one
     * enforcement point; a shared user account must not hide which one is noisy.
     */
    protected function throttleKey(Request $request): string
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->getKey();
        }

        return (string) ($user?->getAuthIdentifier() ?: $request->ip());
    }
}
