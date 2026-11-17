<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequireTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_privileged_user_without_two_factor_is_redirected_to_the_profile(): void
    {
        $user = $this->userWithRole('Super Admin');

        $response = $this->handleFor($user);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin/me', $response->headers->get('Location'));
    }

    #[Test]
    public function a_privileged_user_with_confirmed_two_factor_passes(): void
    {
        $user = $this->userWithRole('Super Admin');
        $this->confirmTwoFactor($user);

        $response = $this->handleFor($user);

        $this->assertSame('passed-through', $response->getContent());
    }

    #[Test]
    public function a_regular_user_is_not_forced_to_enrol(): void
    {
        $user = $this->userWithRole('Regular User');

        $response = $this->handleFor($user);

        $this->assertSame('passed-through', $response->getContent());
    }

    #[Test]
    public function the_profile_route_is_exempt_so_enrolment_is_possible(): void
    {
        $user = $this->userWithRole('Super Admin');

        $request = Request::create('/app/me', 'GET');
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
        $request = Request::create('/app', 'GET');
        $request->setUserResolver(fn () => $user);

        return (new RequireTwoFactorAuthentication)->handle(
            $request,
            fn () => response('passed-through')
        );
    }

    protected function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function confirmTwoFactor(User $user): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user->enableTwoFactorAuthentication();
        $user->confirmTwoFactorAuthentication();
        $user->unsetRelation('breezySession');
    }
}
