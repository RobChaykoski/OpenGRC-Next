<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Implementation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ControlTestingReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_overdue_control_tests(): void
    {
        $owner = User::factory()->create(['name' => 'Alex Owner', 'role' => UserRole::Editor]);

        Implementation::factory()->create([
            'code' => 'IMP-OVERDUE',
            'title' => 'Quarterly access review',
            'implementation_owner_id' => $owner->getKey(),
            'next_test_at' => today()->subDays(9),
        ]);

        Implementation::factory()->create([
            'code' => 'IMP-FUTURE',
            'next_test_at' => today()->addDays(90),
        ]);

        Implementation::factory()->create([
            'code' => 'IMP-UNSCHEDULED',
            'next_test_at' => null,
        ]);

        $this->artisan('opengrc:controls:testing')
            ->expectsOutputToContain('IMP-OVERDUE')
            ->doesntExpectOutputToContain('IMP-FUTURE')
            ->doesntExpectOutputToContain('IMP-UNSCHEDULED')
            ->assertSuccessful();
    }

    #[Test]
    public function the_days_option_includes_upcoming_tests(): void
    {
        Implementation::factory()->create([
            'code' => 'IMP-SOON',
            'next_test_at' => today()->addDays(10),
        ]);

        $this->artisan('opengrc:controls:testing', ['--days' => 30])
            ->expectsOutputToContain('IMP-SOON')
            ->assertSuccessful();
    }

    #[Test]
    public function it_can_fail_when_a_test_is_overdue(): void
    {
        Implementation::factory()->create([
            'next_test_at' => today()->subDay(),
        ]);

        $this->artisan('opengrc:controls:testing', ['--fail-on-overdue' => true])
            ->assertFailed();
    }

    #[Test]
    public function the_json_output_is_machine_readable(): void
    {
        Implementation::factory()->create([
            'code' => 'IMP-JSON',
            'next_test_at' => today()->subDays(3),
        ]);

        $exitCode = Artisan::call('opengrc:controls:testing', ['--json' => true]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['overdue']);
        $this->assertSame('IMP-JSON', $payload['implementations'][0]['code']);
    }

    #[Test]
    public function an_unknown_owner_is_rejected(): void
    {
        $this->artisan('opengrc:controls:testing', ['--owner' => 999999])
            ->assertFailed();
    }
}
