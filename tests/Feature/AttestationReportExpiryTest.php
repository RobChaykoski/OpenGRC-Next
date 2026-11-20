<?php

namespace Tests\Feature;

use App\Enums\AttestationStatus;
use App\Models\AttestationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttestationReportExpiryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_expires_only_current_reports_past_their_explicit_expiry(): void
    {
        $expired = AttestationReport::factory()->expired()->create();
        $future = AttestationReport::factory()->create([
            'expires_at' => today()->addDay(),
        ]);
        $noExpiry = AttestationReport::factory()->create([
            'expires_at' => null,
        ]);
        $superseded = AttestationReport::factory()->create([
            'status' => AttestationStatus::Superseded,
            'expires_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:attestations:expire')->assertSuccessful();

        $this->assertSame(AttestationStatus::Expired, $expired->fresh()?->status);
        $this->assertSame(AttestationStatus::Current, $future->fresh()?->status);
        $this->assertSame(AttestationStatus::Current, $noExpiry->fresh()?->status);
        $this->assertSame(AttestationStatus::Superseded, $superseded->fresh()?->status);
    }

    #[Test]
    public function a_dry_run_does_not_change_a_status(): void
    {
        $report = AttestationReport::factory()->expired()->create();

        $this->artisan('opengrc:attestations:expire', ['--dry-run' => true])
            ->expectsOutputToContain('would be marked expired')
            ->assertSuccessful();

        $this->assertSame(AttestationStatus::Current, $report->fresh()?->status);
    }

    #[Test]
    public function it_does_not_expire_a_report_without_an_explicit_expiry(): void
    {
        AttestationReport::factory()->create(['expires_at' => null]);

        $this->artisan('opengrc:attestations:expire')
            ->expectsOutputToContain('No assurance reports to expire.')
            ->assertSuccessful();
    }
}
