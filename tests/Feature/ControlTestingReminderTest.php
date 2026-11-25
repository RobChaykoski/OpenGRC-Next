<?php

namespace Tests\Feature;

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
