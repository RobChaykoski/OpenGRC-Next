<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnforceAccessRules;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use DB;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Exception;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AppPanelProvider extends PanelProvider
{
    private function getSessionTimeout(): int
    {
        try {
            // Check if database is connected
            DB::connection()->getPdo();

            return setting('security.session_timeout', 15);
        } catch (Exception $e) {
            // Return default value if database is not available
            return 15;
        }
    }

    public function panel(Panel $panel): Panel
    {
        $socialProviders = [];

        // Build social providers array using FilamentSocialite v3 API
        // Check if settings service is available (may not be during early boot)
        if (app()->bound('setting')) {
            try {
                // Check if database is available before accessing settings
                DB::connection()->getPdo();

                if (setting('auth.okta.enabled')) {
                    $socialProviders[] = Provider::make('okta')
                        ->label('Okta')
                        ->icon('heroicon-o-lock-closed')
                        ->color(Color::Slate);
                }

                if (setting('auth.microsoft.enabled')) {
                    $socialProviders[] = Provider::make('microsoft')
                        ->label('Microsoft')
                        ->icon('heroicon-o-window')
                        ->color(Color::Slate);
                }

                if (setting('auth.azure.enabled')) {
                    $socialProviders[] = Provider::make('azure')
                        ->label('Azure AD')
                        ->icon('heroicon-o-cloud')
                        ->color(Color::Slate);
                }

                if (setting('auth.google.enabled')) {
                    $socialProviders[] = Provider::make('google')
                        ->label('Google')
                        ->icon('heroicon-o-globe-alt')
                        ->color(Color::Slate);
                }

                if (setting('auth.auth0.enabled')) {
                    $socialProviders[] = Provider::make('auth0')
                        ->label('Auth0')
                        ->icon('heroicon-o-lock-closed')
                        ->color(Color::Slate);
                }

                // Authentik is configured from the environment, not a database
                // toggle: the client secret belongs in the container secret,
                // and an unset client id keeps the button hidden.
                if (config('services.authentik.client_id')) {
                    $socialProviders[] = Provider::make('authentik')
                        ->label('Authentik')
                        ->icon('heroicon-o-shield-check')
                        ->color(Color::Slate);
                }

            } catch (Exception $e) {
                // Log the error to help debug SSO issues
                logger()->warning('SSO providers could not be loaded: '.$e->getMessage());
            }
        }

        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->loginRouteSlug('login')
            ->colors([
                'primary' => Color::Slate,
            ])
            ->brandName('OpenGRC')
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->globalSearch(true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->viteTheme('resources/css/filament/app/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->plugins([
                FilamentApexChartsPlugin::make(),
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: false,
                        slug: 'me',
                        navigationGroup: 'Settings',
                    )
                    ->enableTwoFactorAuthentication(
                        force: false,
                    )
                    ->passwordUpdateRules(
                        rules: [Password::default()->mixedCase()->uncompromised(3)->min(12)],
                    )
                    ->enableSanctumTokens(),
                FilamentSocialitePlugin::make()
                    ->providers($socialProviders)
                    // Link an IdP identity to an existing OpenGRC account by
                    // email. Unknown identities are refused rather than
                    // auto-provisioned: the panel grants real GRC access.
                    ->resolveUserUsing(fn (string $provider, SocialiteUserContract $oauthUser) => User::query()
                        ->where('email', $oauthUser->getEmail())
                        ->whereNull('deleted_at')
                        ->first())
                    ->registration(fn (string $provider, SocialiteUserContract $oauthUser, ?Authenticatable $user) => $user !== null)
                    ->rememberLogin(true),
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render("@livewire('multi-window-inactivity-guard')")
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.session-expiration-handler')
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.app.sidebar-bottom-links')
            )
            ->navigationGroups([
                'Foundations',
                'Entities',
                'Access governance',
            ])
            ->navigationItems([
                NavigationItem::make('Administration')
                    ->url('/admin')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnforceAccessRules::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authGuard('web')
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactorAuthentication::class,
            ]);
    }
}
