<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateApiTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_scoped_client_bound_token(): void
    {
        $user = User::factory()->create(['email' => 'discovery@example.com']);

        $this->artisan('opengrc:token:create', [
            'email' => 'discovery@example.com',
            '--client' => 'acme',
            '--ability' => ['evidence.write'],
        ])->assertExitCode(0);

        $token = PersonalAccessToken::query()->sole();

        $this->assertSame('acme', $token->client_id);
        $this->assertSame(['evidence.write'], $token->abilities);
        $this->assertSame($user->getKey(), $token->tokenable_id);
    }

    #[Test]
    public function it_requires_a_client_binding(): void
    {
        User::factory()->create(['email' => 'discovery@example.com']);

        $this->artisan('opengrc:token:create', [
            'email' => 'discovery@example.com',
        ])->assertExitCode(1);

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    #[Test]
    public function it_rejects_an_unknown_user(): void
    {
        $this->artisan('opengrc:token:create', [
            'email' => 'nobody@example.com',
            '--client' => 'acme',
        ])->assertExitCode(1);
    }
}
