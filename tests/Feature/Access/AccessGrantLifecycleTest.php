<?php

namespace Tests\Feature\Access;

use App\Enums\AccessAction;
use App\Enums\AccessGrantState;
use App\Enums\PrivacyReviewOutcome;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use App\Models\ProtectedResource;
use App\Models\User;
use App\Services\Access\AccessGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessGrantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_grant_requires_a_justification(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->request(
            ProtectedResource::factory()->create(),
            ['justification' => '   ', 'subject_label' => 'Dr Example'],
            User::factory()->create(),
        );
    }

    #[Test]
    public function break_glass_requires_a_reason_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->request(
            ProtectedResource::factory()->create(),
            [
                'justification' => 'Unconscious patient in the emergency department.',
                'subject_label' => 'Dr Example',
                'is_break_glass' => true,
            ],
            User::factory()->create(),
        );
    }

    #[Test]
    public function the_ttl_is_capped_by_the_break_glass_limit(): void
    {
        $grant = $this->service()->request(
            ProtectedResource::factory()->create(),
            [
                'justification' => 'Unconscious patient in the emergency department.',
                'subject_label' => 'Dr Example',
                'is_break_glass' => true,
                'clinical_reason' => 'trauma',
                'expires_at' => now()->addHours(10),
            ],
            User::factory()->create(),
        );

        $cap = (int) config('access_governance.grants.break_glass_max_minutes');

        $this->assertLessThanOrEqual($cap + 1, (int) now()->diffInMinutes($grant->expires_at));
    }

    #[Test]
    public function break_glass_creates_a_privacy_review_obligation(): void
    {
        $grant = $this->service()->request(
            ProtectedResource::factory()->create(),
            [
                'justification' => 'Unconscious patient in the emergency department.',
                'subject_label' => 'Dr Example',
                'is_break_glass' => true,
                'clinical_reason' => 'trauma',
            ],
            User::factory()->create(),
        );

        $this->assertTrue($grant->review_required);
        $this->assertNotNull($grant->review_due_at);
        $this->assertSame(PrivacyReviewOutcome::Pending, $grant->review_outcome);
    }

    #[Test]
    public function a_lockbox_resource_creates_a_review_obligation(): void
    {
        $grant = $this->service()->request(
            ProtectedResource::factory()->lockbox()->create(),
            ['justification' => 'Audit evidence request.', 'subject_label' => 'Analyst'],
            User::factory()->create(),
        );

        $this->assertTrue($grant->review_required);
    }

    #[Test]
    public function the_approver_cannot_be_the_requester(): void
    {
        $requester = User::factory()->create();

        $grant = AccessGrant::factory()->create([
            'requested_by' => $requester->getKey(),
            'state' => AccessGrantState::Requested,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->approve($grant, $requester);
    }

    #[Test]
    public function approving_moves_the_grant_to_active(): void
    {
        $grant = AccessGrant::factory()->create([
            'state' => AccessGrantState::Requested,
            'requested_by' => User::factory()->create()->getKey(),
        ]);

        $this->service()->approve($grant, User::factory()->create());

        $grant->refresh();

        $this->assertSame(AccessGrantState::Active, $grant->state);
        $this->assertNotNull($grant->approved_at);
        $this->assertNotNull($grant->starts_at);
    }

    #[Test]
    public function revoking_records_the_actor_and_the_reason(): void
    {
        $grant = AccessGrant::factory()->active()->create();
        $actor = User::factory()->create();

        $this->service()->revoke($grant, $actor, 'Patient transferred.');

        $grant->refresh();

        $this->assertSame(AccessGrantState::Revoked, $grant->state);
        $this->assertSame($actor->getKey(), $grant->revoked_by);
        $this->assertSame('Patient transferred.', $grant->revoked_reason);
        $this->assertNotNull($grant->revoked_at);
    }

    #[Test]
    public function a_review_must_record_an_outcome(): void
    {
        $grant = AccessGrant::factory()->breakGlass()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->recordReview($grant, User::factory()->create(), PrivacyReviewOutcome::Pending);
    }

    #[Test]
    public function the_expiry_command_expires_past_grants_and_reports_overdue_reviews(): void
    {
        $expired = AccessGrant::factory()->active()->create([
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('opengrc:access:expire-grants')
            ->expectsOutputToContain('Marked 1 grant(s) as expired.')
            ->assertExitCode(0);

        $this->assertSame(AccessGrantState::Expired, $expired->refresh()->state);
    }

    #[Test]
    public function a_decision_cannot_be_updated(): void
    {
        $decision = AccessDecision::factory()->create();

        $this->expectException(LogicException::class);

        $decision->update(['action' => AccessAction::Deny]);
    }

    #[Test]
    public function a_decision_cannot_be_deleted(): void
    {
        $decision = AccessDecision::factory()->create();

        $this->expectException(LogicException::class);

        $decision->delete();
    }

    #[Test]
    public function the_decision_chain_is_intact_and_detects_tampering(): void
    {
        $first = AccessDecision::factory()->create();
        $second = AccessDecision::factory()->create();
        $third = AccessDecision::factory()->create();

        $this->assertNull(AccessDecision::firstBrokenLink());
        $this->assertSame($first->entry_hash, $second->previous_hash);
        $this->assertSame($second->entry_hash, $third->previous_hash);

        // A direct database write bypasses the model guard; the chain still shows it.
        DB::table('access_decisions')->where('id', $second->getKey())->update(['action' => 'deny']);

        $this->assertSame((int) $second->getKey(), AccessDecision::firstBrokenLink());
    }

    private function service(): AccessGrantService
    {
        return app(AccessGrantService::class);
    }
}
