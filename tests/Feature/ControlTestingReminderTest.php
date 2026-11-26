<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Implementation;
use App\Models\User;
use App\Services\ControlTestingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlTestingReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function a_test_entering_the_window_notifies_its_owner(): void
    {
        $owner = User::factory()->create();
        $implementation = $this->implementation($owner, today()->addDays(3));

        $this->artisan('opengrc:controls:testing:notify')->assertSuccessful();

        $notification = $owner->notifications()->sole();

        $this->assertSame('Control test due', $notification->data['title']);
        $this->assertSame('warning', $notification->data['status']);
        $this->assertStringContainsString(
            'A.8.16 — Access review is due on '.today()->addDays(3)->toDateString(),
            $notification->data['body'],
        );
        $this->assertStringContainsString(
            '/app/implementations/'.$implementation->getKey(),
            (string) json_encode($notification->data, JSON_UNESCAPED_SLASHES),
        );
    }

    #[Test]
    public function an_overdue_test_is_raised_as_a_danger(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today()->subDays(2));

        $this->artisan('opengrc:controls:testing:notify')->assertSuccessful();

        $notification = $owner->notifications()->sole();

        $this->assertSame('Control test overdue', $notification->data['title']);
        $this->assertSame('danger', $notification->data['status']);
        $this->assertStringContainsString(
            'was due on '.today()->subDays(2)->toDateString().', 2 days ago',
            $notification->data['body'],
        );
    }

    #[Test]
    public function a_test_due_today_is_due_not_overdue(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today());

        $this->artisan('opengrc:controls:testing:notify')->assertSuccessful();

        $notification = $owner->notifications()->sole();

        $this->assertSame('Control test due', $notification->data['title']);
        $this->assertStringContainsString('is due on '.today()->toDateString(), $notification->data['body']);
    }

    #[Test]
    public function a_test_outside_the_window_is_not_notified(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today()->addDays(30));

        $this->artisan('opengrc:controls:testing:notify')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
    }

    #[Test]
    public function an_overdue_test_is_escalated_to_administrators_once(): void
    {
        config(['control_testing.escalation.after_days_overdue' => 14]);

        $owner = User::factory()->create();
        $adminA = User::factory()->create(['role' => UserRole::Admin]);
        $adminB = User::factory()->create(['role' => UserRole::Admin]);
        $implementation = $this->implementation($owner, today()->subDays(20));

        $first = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $first['escalated']);
        $this->assertSame(2, $first['escalation_admins']);
        $this->assertSame(1, $adminA->notifications()->count());
        $this->assertSame(1, $adminB->notifications()->count());

        $notification = $adminA->notifications()->sole();

        $this->assertSame('Control test escalation', $notification->data['title']);
        $this->assertStringContainsString(
            'A.8.16 — Access review is 20 days overdue and '.$owner->name.' has not recorded a test',
            $notification->data['body'],
        );
        $this->assertStringContainsString(
            '/app/implementations/'.$implementation->getKey(),
            (string) json_encode($notification->data, JSON_UNESCAPED_SLASHES),
        );

        $second = app(ControlTestingReminder::class)->send();

        $this->assertSame(0, $second['escalated']);
        $this->assertSame(1, $second['escalation_skipped']);
        $this->assertSame(1, $adminA->notifications()->count());
        $this->assertSame(1, $adminB->notifications()->count());
    }

    #[Test]
    public function a_test_below_the_escalation_threshold_is_not_escalated(): void
    {
        config(['control_testing.escalation.after_days_overdue' => 14]);

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->implementation($owner, today()->subDays(5));

        $report = app(ControlTestingReminder::class)->send();

        $this->assertSame(0, $report['escalated']);
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(1, $report['overdue']);
    }

    #[Test]
    public function escalation_is_off_by_default(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->implementation($owner, today()->subDays(30));

        $report = app(ControlTestingReminder::class)->send();

        $this->assertSame(0, $report['escalation_after_days']);
        $this->assertSame(0, $report['escalated']);
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $report['escalation_admins']);
    }

    #[Test]
    public function an_unowned_overdue_test_becomes_one_weekly_admin_digest(): void
    {
        config(['control_testing.escalation.after_days_overdue' => 14]);

        $adminA = User::factory()->create(['role' => UserRole::Admin]);
        $adminB = User::factory()->create(['role' => UserRole::Admin]);

        Implementation::factory()->create([
            'code' => 'U-1',
            'title' => 'Unowned control',
            'implementation_owner_id' => null,
            'next_test_at' => today()->subDays(20),
        ]);

        $first = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $first['unassigned']);
        $this->assertSame(1, $first['escalated']);

        foreach ([$adminA, $adminB] as $admin) {
            $notification = $admin->notifications()->sole();

            $this->assertSame('Control tests overdue without an owner', $notification->data['title']);
            $this->assertStringContainsString('U-1 — Unowned control', $notification->data['body']);
        }

        $second = app(ControlTestingReminder::class)->send();

        $this->assertSame(0, $second['escalated']);
        $this->assertSame(1, $second['escalation_skipped']);
        $this->assertSame(1, $adminA->notifications()->count());
    }

    #[Test]
    public function a_dry_run_does_not_escalate_or_consume_the_marker(): void
    {
        config(['control_testing.escalation.after_days_overdue' => 14]);

        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->implementation($owner, today()->subDays(20));

        $dryRun = app(ControlTestingReminder::class)->send(7, true);

        $this->assertSame(0, $dryRun['escalated']);
        $this->assertSame(1, $dryRun['would_escalate']);
        $this->assertSame(0, $admin->notifications()->count());

        $real = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $real['escalated']);
        $this->assertSame(1, $admin->notifications()->count());
    }

    #[Test]
    public function escalation_without_an_administrator_sends_nothing(): void
    {
        config(['control_testing.escalation.after_days_overdue' => 14]);

        $owner = User::factory()->create();
        $this->implementation($owner, today()->subDays(20));

        $report = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $report['overdue']);
        $this->assertSame(0, $report['escalation_admins']);
        $this->assertSame(0, $report['escalated']);
        $this->assertSame(1, $report['escalation_skipped']);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    #[Test]
    public function a_daily_run_does_not_repeat_the_same_reminder(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today()->addDays(2));

        $first = app(ControlTestingReminder::class)->send();
        $second = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $first['notified']);
        $this->assertSame(1, $second['due']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(0, $second['notified']);
        $this->assertSame(1, $owner->notifications()->count());
    }

    #[Test]
    public function a_new_test_date_can_notify_again(): void
    {
        $owner = User::factory()->create();
        $implementation = $this->implementation($owner, today()->addDays(2));

        app(ControlTestingReminder::class)->send();

        $implementation->forceFill(['next_test_at' => today()->addDays(5)])->save();

        $report = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $report['notified']);
        $this->assertSame(2, $owner->notifications()->count());
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_does_not_consume_the_reminder(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today()->addDays(2));

        $dryRun = app(ControlTestingReminder::class)->send(7, true);

        $this->assertSame(1, $dryRun['due']);
        $this->assertSame(0, $dryRun['notified']);
        $this->assertSame(1, $dryRun['would_notify']);
        $this->assertSame(0, $owner->notifications()->count());

        $real = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $real['notified']);
        $this->assertSame(1, $owner->notifications()->count());
    }

    #[Test]
    public function an_unassigned_test_is_counted_but_not_routed(): void
    {
        User::factory()->create();
        Implementation::factory()->create([
            'implementation_owner_id' => null,
            'next_test_at' => today()->subDay(),
        ]);

        $report = app(ControlTestingReminder::class)->send();

        $this->assertSame(1, $report['unassigned']);
        $this->assertSame(0, $report['notified']);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    #[Test]
    public function the_json_report_is_machine_readable(): void
    {
        $owner = User::factory()->create();
        $this->implementation($owner, today()->addDays(2));

        Artisan::call('opengrc:controls:testing:notify', ['--json' => true]);

        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(7, $report['window_days']);
        $this->assertSame(1, $report['due']);
        $this->assertSame(1, $report['notified']);
        $this->assertFalse($report['dry_run']);
    }

    private function implementation(User $owner, \DateTimeInterface $nextTest): Implementation
    {
        return Implementation::factory()->create([
            'code' => 'A.8.16',
            'title' => 'Access review',
            'implementation_owner_id' => $owner->getKey(),
            'next_test_at' => $nextTest,
        ]);
    }
}
