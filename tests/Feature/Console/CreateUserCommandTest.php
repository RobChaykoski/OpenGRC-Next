<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_user_with_the_super_admin_role_by_default(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertExitCode(0);

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Super Admin'));
    }

    #[Test]
    public function a_second_user_is_not_granted_super_admin_automatically(): void
    {
        User::factory()->create();

        $this->artisan('opengrc:create-user', [
            'email' => 'second@example.com',
            'password' => 'password123',
        ])->assertExitCode(0);

        $user = User::where('email', 'second@example.com')->firstOrFail();

        $this->assertSame(0, $user->roles()->count());
    }

    #[Test]
    public function it_can_skip_role_assignment(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
            '--role' => '',
        ])->assertExitCode(0);

        $user = User::where('email', 'nobody@example.com')->firstOrFail();

        $this->assertSame(0, $user->roles()->count());
    }

    #[Test]
    public function it_can_assign_a_specific_role(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'auditor@example.com',
            'password' => 'password123',
            '--role' => 'Internal Auditor',
        ])->assertExitCode(0);

        $user = User::where('email', 'auditor@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Internal Auditor'));
    }
}
