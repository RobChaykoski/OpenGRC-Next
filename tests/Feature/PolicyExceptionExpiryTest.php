<?php

namespace Tests\Feature;

use App\Enums\PolicyExceptionStatus;
use App\Models\PolicyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyExceptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_expires_only_approved_exceptions_past_their_date(): void
    {
        $expired = PolicyException::factory()->approved()->create([
            'expiration_date' => today()->subDay(),
        ]);
        $future = PolicyException::factory()->approved()->create([
            'expiration_date' => today()->addDay(),
        ]);
        $pending = PolicyException::factory()->pending()->create([
            'expiration_date' => today()->subDay(),
        ]);
        $revoked = PolicyException::factory()->revoked()->create([
            'expiration_date' => today()->subDay(),
        ]);

        $this->artisan('opengrc:exceptions:expire')->assertSuccessful();

        $this->assertSame(PolicyExceptionStatus::Expired, $expired->fresh()?->status);
        $this->assertSame(PolicyExceptionStatus::Approved, $future->fresh()?->status);
        $this->assertSame(PolicyExceptionStatus::Pending, $pending->fresh()?->status);
        $this->assertSame(PolicyExceptionStatus::Revoked, $revoked->fresh()?->status);
    }

    #[Test]
    public function a_dry_run_does_not_change_a_status(): void
    {
        $exception = PolicyException::factory()->approved()->create([
            'expiration_date' => today()->subDay(),
        ]);

        $this->artisan('opengrc:exceptions:expire', ['--dry-run' => true])
            ->expectsOutputToContain('would be marked expired')
            ->assertSuccessful();

        $this->assertSame(PolicyExceptionStatus::Approved, $exception->fresh()?->status);
    }

    #[Test]
    public function it_reports_when_there_is_nothing_to_expire(): void
    {
        PolicyException::factory()->approved()->create([
            'expiration_date' => today()->addWeek(),
        ]);

        $this->artisan('opengrc:exceptions:expire')
            ->expectsOutputToContain('No exceptions to expire.')
            ->assertSuccessful();
    }
}
