<?php

namespace Tests\Feature;

use App\Enums\RiskAcceptanceStatus;
use App\Models\Risk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RiskAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_an_active_and_expired_acceptance(): void
    {
        $active = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'acceptance_expires_at' => today()->addDays(10),
        ]);

        $expired = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'acceptance_expires_at' => today()->subDay(),
        ]);

        $this->assertTrue($active->isAcceptanceActive());
        $this->assertSame(10, $active->daysUntilAcceptanceExpiry());
        $this->assertFalse($expired->isAcceptanceActive());
        $this->assertTrue($expired->isAcceptanceExpired());
    }

    #[Test]
    public function the_command_expires_only_accepted_risks_past_their_date(): void
    {
        $expired = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'acceptance_expires_at' => today()->subDay(),
        ]);
        $future = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'acceptance_expires_at' => today()->addDay(),
        ]);
        $pending = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Pending,
            'acceptance_expires_at' => today()->subDay(),
        ]);
        $revoked = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Revoked,
            'acceptance_expires_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:risks:acceptance')->assertSuccessful();

        $this->assertSame(RiskAcceptanceStatus::Expired, $expired->fresh()?->acceptance_status);
        $this->assertSame(RiskAcceptanceStatus::Accepted, $future->fresh()?->acceptance_status);
        $this->assertSame(RiskAcceptanceStatus::Pending, $pending->fresh()?->acceptance_status);
        $this->assertSame(RiskAcceptanceStatus::Revoked, $revoked->fresh()?->acceptance_status);
    }

    #[Test]
    public function a_dry_run_does_not_change_a_status(): void
    {
        $risk = Risk::factory()->create([
            'acceptance_status' => RiskAcceptanceStatus::Accepted,
            'acceptance_expires_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:risks:acceptance', ['--dry-run' => true])
            ->expectsOutputToContain('would expire')
            ->assertSuccessful();

        $this->assertSame(RiskAcceptanceStatus::Accepted, $risk->fresh()?->acceptance_status);
    }
}
