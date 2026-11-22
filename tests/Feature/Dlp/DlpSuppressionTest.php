<?php

namespace Tests\Feature\Dlp;

use App\Enums\DlpSuppressionState;
use App\Models\DlpFinding;
use App\Models\DlpPolicy;
use App\Models\DlpSuppression;
use App\Models\User;
use App\Services\Dlp\DlpSuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpSuppressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_suppression_requires_a_justification(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->request(
            DlpPolicy::factory()->create(),
            ['justification' => '   ', 'expires_at' => now()->addDays(10)],
            User::factory()->create(),
        );
    }

    #[Test]
    public function a_suppression_must_expire_in_the_future_and_within_a_year(): void
    {
        $policy = DlpPolicy::factory()->create();
        $requester = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->request(
            $policy,
            ['justification' => 'Public test data.', 'expires_at' => now()->subDay()],
            $requester,
        );
    }

    #[Test]
    public function a_suppression_cannot_last_longer_than_a_year(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->request(
            DlpPolicy::factory()->create(),
            ['justification' => 'Public test data.', 'expires_at' => now()->addDays(400)],
            User::factory()->create(),
        );
    }

    #[Test]
    public function the_approver_cannot_be_the_requester(): void
    {
        $requester = User::factory()->create();
        $suppression = DlpSuppression::factory()->create(['requested_by' => $requester->getKey()]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->approve($suppression, $requester);
    }

    #[Test]
    public function approving_puts_the_suppression_in_force(): void
    {
        $suppression = DlpSuppression::factory()->create([
            'requested_by' => User::factory()->create()->getKey(),
        ]);
        $approver = User::factory()->create();

        $this->service()->approve($suppression, $approver);

        $suppression->refresh();

        $this->assertSame(DlpSuppressionState::Active, $suppression->state);
        $this->assertSame($approver->getKey(), $suppression->approved_by);
        $this->assertNotNull($suppression->starts_at);
        $this->assertTrue($suppression->isActive());
        $this->assertSame(1, DlpSuppression::query()->inForce()->count());
    }

    #[Test]
    public function an_expired_suppression_is_marked_by_the_command(): void
    {
        $suppression = DlpSuppression::factory()->expired()->create();

        $this->artisan('opengrc:dlp:expire-suppressions')
            ->expectsOutputToContain('Marked 1 suppression(s) as expired.')
            ->assertExitCode(0);

        $this->assertSame(DlpSuppressionState::Expired, $suppression->refresh()->state);
        $this->assertSame(0, DlpSuppression::query()->inForce()->count());
    }

    #[Test]
    public function the_dry_run_reports_without_changing_state(): void
    {
        $suppression = DlpSuppression::factory()->expired()->create();

        $this->artisan('opengrc:dlp:expire-suppressions', ['--dry-run' => true])
            ->expectsOutputToContain('1 suppression(s) would be marked expired.')
            ->assertExitCode(0);

        $this->assertSame(DlpSuppressionState::Active, $suppression->refresh()->state);
    }

    #[Test]
    public function a_revoked_suppression_is_not_in_force(): void
    {
        $suppression = DlpSuppression::factory()->active()->create([
            'requested_by' => User::factory()->create()->getKey(),
        ]);

        $this->service()->revoke($suppression, User::factory()->create(), 'Detector fixed upstream.');

        $this->assertSame(DlpSuppressionState::Revoked, $suppression->refresh()->state);
        $this->assertSame(0, DlpSuppression::query()->inForce()->count());
    }

    #[Test]
    public function a_revocation_needs_a_reason(): void
    {
        $suppression = DlpSuppression::factory()->active()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->revoke($suppression, User::factory()->create(), '  ');
    }

    #[Test]
    public function only_an_open_suppression_can_be_revoked(): void
    {
        $suppression = DlpSuppression::factory()->create([
            'state' => DlpSuppressionState::Expired,
        ]);

        $this->expectException(LogicException::class);

        $this->service()->revoke($suppression, User::factory()->create(), 'Late.');
    }

    #[Test]
    public function a_suppression_applies_only_to_its_scope(): void
    {
        $policy = DlpPolicy::factory()->create();

        $finding = DlpFinding::factory()->create([
            'dlp_policy_id' => $policy->getKey(),
            'match_types' => ['Email address'],
            'identifier_types' => ['email'],
        ]);

        $wholePolicy = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
        ]);

        $sameIdentifier = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'identifier_type' => 'email',
        ]);

        $otherIdentifier = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'identifier_type' => 'phone',
        ]);

        $otherLabel = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => $policy->getKey(),
            'match_type' => 'Credit card',
        ]);

        $otherPolicy = DlpSuppression::factory()->active()->create([
            'dlp_policy_id' => DlpPolicy::factory()->create(['code' => 'DP-002'])->getKey(),
        ]);

        $this->assertTrue($wholePolicy->appliesTo($finding));
        $this->assertTrue($sameIdentifier->appliesTo($finding));
        $this->assertFalse($otherIdentifier->appliesTo($finding));
        $this->assertFalse($otherLabel->appliesTo($finding));
        $this->assertFalse($otherPolicy->appliesTo($finding));
    }

    protected function service(): DlpSuppressionService
    {
        return app(DlpSuppressionService::class);
    }
}
