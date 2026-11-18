<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequireTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_admin_without_two_factor_is_redirected_to_the_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->handleFor($user);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin/me', $response->headers->get('Location'));
    }

    #[Test]
    public function an_admin_opening_the_app_panel_is_redirected_to_the_app_profile(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $request = Request::create('/app', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new RequireTwoFactorAuthentication)->handle(
            $request,
            fn () => response('passed-through')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/app/me', $response->headers->get('Location'));

        Filament::setCurrentPanel(null);
    }

    #[Test]
    public function an_admin_with_confirmed_two_factor_passes(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->confirmTwoFactor($user);

        $response = $this->handleFor($user);

        $this->assertSame('passed-through', $response->getContent());
    }

    #[Test]
    public function an_editor_is_not_forced_to_enrol(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);

        $response = $this->handleFor($user);

        $this->assertSame('passed-through', $response->getContent());
    }

    #[Test]
    public function a_viewer_is_not_forced_to_enrol(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $response = $this->handleFor($user);

        $this->assertSame('passed-through', $response->getContent());
    }

    #[Test]
    public function the_profile_route_is_exempt_so_enrolment_is_possible(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $request = Request::create('/admin/me', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class
        {
            public function named(string ...$names): bool
            {
                return true;
            }

            public function getName(): string
            {
                return 'filament.admin.pages.me';
            }
        });

        $response = (new RequireTwoFactorAuthentication)->handle(
            $request,
            fn () => response('passed-through')
        );

        $this->assertSame('passed-through', $response->getContent());
    }

    protected function handleFor(User $user)
    {
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        return (new RequireTwoFactorAuthentication)->handle(
            $request,
            fn () => response('passed-through')
        );
    }

    protected function confirmTwoFactor(User $user): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user->enableTwoFactorAuthentication();
        $user->confirmTwoFactorAuthentication();
        $user->unsetRelation('breezySession');
    }
}
