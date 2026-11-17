<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_first_user_becomes_an_admin(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertExitCode(0);

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $user->role);
    }

    #[Test]
    public function later_users_default_to_viewer(): void
    {
        User::factory()->create(['role' => UserRole::Admin]);

        $this->artisan('opengrc:create-user', [
            'email' => 'second@example.com',
            'password' => 'password123',
        ])->assertExitCode(0);

        $this->assertSame(UserRole::Viewer, User::where('email', 'second@example.com')->firstOrFail()->role);
    }

    #[Test]
    public function a_specific_role_can_be_assigned(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'auditor@example.com',
            'password' => 'password123',
            '--role' => 'editor',
        ])->assertExitCode(0);

        $this->assertSame(UserRole::Editor, User::where('email', 'auditor@example.com')->firstOrFail()->role);
    }

    #[Test]
    public function an_unknown_role_is_rejected(): void
    {
        $this->artisan('opengrc:create-user', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
            '--role' => 'superuser',
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);
    }
}
