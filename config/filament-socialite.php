<?php

use App\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth callback middleware
    |--------------------------------------------------------------------------
    |
    | This option defines the middleware that is applied to the OAuth callback url.
    |
    */

    'middleware' => [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        AuthenticateSession::class,
        ShareErrorsFromSession::class,
    ],

    // 'login_route' => 'socialite.redirect',
    'login_route' => 'socialite.app.oauth.redirect',
    'callback_route' => 'socialite.callback',
    'user_model' => User::class,

    'providers' => [
        'okta' => [
            'label' => 'Okta',
            'icon' => 'heroicon-o-lock-closed',
            'color' => 'primary',
        ],
        'azure' => [
            'label' => 'Azure AD',
            'icon' => 'heroicon-o-cloud',
            'color' => 'primary',
        ],
    ],
];
