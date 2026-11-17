<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected string $redirectTo = '/app/login';

    /**
     * Model to policy mappings.
     *
     * Policy resolution lives in AppServiceProvider::boot(), where one default
     * policy covers ordinary models and a handful of models with ownership
     * rules are mapped explicitly.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Set token expiration times
        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(7));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // UUIDs are configured via config/passport.php 'client_uuids' => true
    }
}
