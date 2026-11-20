<?php

namespace Tests\Feature;

use App\Enums\PrivacyRegime;
use App\Enums\PrivacyRequestStatus;
use App\Models\PrivacyRequest;
use App\Services\Privacy\PrivacyDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivacyRequestDeadlineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_computes_the_regime_deadline(): void
    {
        $received = CarbonImmutable::parse('2026-01-31');

        $this->assertSame('2026-02-28', PrivacyDeadline::dueAt(PrivacyRegime::Gdpr, $received)->toDateString());
        $this->assertSame('2026-03-17', PrivacyDeadline::dueAt(PrivacyRegime::Ccpa, $received)->toDateString());
        $this->assertSame('2026-03-02', PrivacyDeadline::dueAt(PrivacyRegime::Pipeda, $received)->toDateString());
        $this->assertSame('2026-03-17', PrivacyDeadline::dueAt(PrivacyRegime::Ferpa, $received)->toDateString());
    }

    #[Test]
    public function it_generates_a_reference_and_due_date_on_create(): void
    {
        $request = PrivacyRequest::factory()->gdpr()->create([
            'reference' => null,
            'received_at' => today(),
        ]);

        $this->assertSame('PR-00001', $request->reference);
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $request->due_at?->toDateString());
    }

    #[Test]
    public function it_marks_overdue_requests_and_reports_due_soon(): void
    {
        $overdue = PrivacyRequest::factory()->create([
            'received_at' => today()->subDays(60),
            'due_at' => today()->subDay(),
            'status' => PrivacyRequestStatus::InProgress,
        ]);

        $dueSoon = PrivacyRequest::factory()->ccpa()->create([
            'received_at' => today()->subDays(43),
            'status' => PrivacyRequestStatus::InProgress,
        ]);

        $completed = PrivacyRequest::factory()->completed()->create([
            'received_at' => today()->subDays(60),
            'due_at' => today()->subDay(),
        ]);

        $extended = PrivacyRequest::factory()->create([
            'received_at' => today()->subDays(60),
            'due_at' => today()->subDay(),
            'extended_at' => today()->addDays(5),
            'status' => PrivacyRequestStatus::Extended,
        ]);

        $this->artisan('opengrc:privacy:deadlines')->assertSuccessful();

        $this->assertSame(PrivacyRequestStatus::Overdue, $overdue->fresh()?->status);
        $this->assertSame(PrivacyRequestStatus::InProgress, $dueSoon->fresh()?->status);
        $this->assertSame(PrivacyRequestStatus::Completed, $completed->fresh()?->status);
        $this->assertSame(PrivacyRequestStatus::Extended, $extended->fresh()?->status);
    }

    #[Test]
    public function a_dry_run_does_not_change_a_status(): void
    {
        $overdue = PrivacyRequest::factory()->create([
            'received_at' => today()->subDays(60),
            'due_at' => today()->subDay(),
            'status' => PrivacyRequestStatus::InProgress,
        ]);

        $this->artisan('opengrc:privacy:deadlines', ['--dry-run' => true])
            ->expectsOutputToContain('would be marked overdue')
            ->assertSuccessful();

        $this->assertSame(PrivacyRequestStatus::InProgress, $overdue->fresh()?->status);
    }

    #[Test]
    public function an_extended_deadline_overrides_the_original_due_date(): void
    {
        $request = PrivacyRequest::factory()->create([
            'received_at' => today()->subDays(60),
            'due_at' => today()->subDay(),
            'extended_at' => today()->addDays(10),
            'status' => PrivacyRequestStatus::Extended,
        ]);

        $this->assertFalse($request->isOverdue());
        $this->assertSame(10, $request->daysRemaining());
    }
}
