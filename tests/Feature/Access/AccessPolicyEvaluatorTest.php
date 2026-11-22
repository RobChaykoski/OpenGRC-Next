<?php

namespace Tests\Feature\Access;

use App\Enums\AccessAction;
use App\Enums\AccessorProfile;
use App\Enums\AccessReason;
use App\Enums\ContextTrust;
use App\Models\AccessPolicy;
use App\Models\ProtectedResource;
use App\Services\Access\AccessDecisionResult;
use App\Services\Access\AccessPolicyEvaluator;
use App\Services\Access\AccessRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessPolicyEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_stale_classification_is_masked_not_trusted(): void
    {
        $resource = ProtectedResource::factory()->stale()->create();

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
        ));

        $this->assertSame(AccessAction::Mask, $result->action);
        $this->assertSame(AccessReason::StaleClassification, $result->reason);
    }

    #[Test]
    public function no_matching_policy_denies(): void
    {
        $resource = ProtectedResource::factory()->create();

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
        ));

        $this->assertSame(AccessAction::Deny, $result->action);
        $this->assertSame(AccessReason::NoPolicy, $result->reason);
    }

    #[Test]
    public function an_explicit_deny_wins_over_a_higher_priority_allow(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->create([
            'code' => 'ALLOW',
            'priority' => 10,
            'action' => AccessAction::Allow,
            'conditions' => [],
        ]);

        AccessPolicy::factory()->create([
            'code' => 'DENY',
            'priority' => 900,
            'action' => AccessAction::Deny,
            'conditions' => [],
        ]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
        ));

        $this->assertSame(AccessAction::Deny, $result->action);
        $this->assertSame('DENY', $result->policy?->code);
    }

    #[Test]
    public function an_active_grant_allows_with_audit_and_its_ttl(): void
    {
        $resource = ProtectedResource::factory()->create();

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Emergency,
            trust: ContextTrust::High,
            grantActive: true,
            grantTtlSeconds: 900,
        ));

        $this->assertSame(AccessAction::AllowAudited, $result->action);
        $this->assertSame(AccessReason::GrantActive, $result->reason);
        $this->assertSame(900, $result->ttlSeconds);
    }

    #[Test]
    public function break_glass_is_refused_outside_a_trusted_context(): void
    {
        $resource = ProtectedResource::factory()->create();

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Emergency,
            trust: ContextTrust::Medium,
            hasJustification: true,
        ));

        $this->assertSame(AccessAction::Deny, $result->action);
        $this->assertSame(AccessReason::ContextInsufficient, $result->reason);
    }

    #[Test]
    public function break_glass_is_time_boxed_and_creates_a_review(): void
    {
        $resource = ProtectedResource::factory()->create();

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Emergency,
            trust: ContextTrust::High,
            hasJustification: true,
            clinicalReason: 'trauma',
        ));

        $this->assertSame(AccessAction::TimeBoxed, $result->action);
        $this->assertSame(AccessReason::BreakGlass, $result->reason);
        $this->assertTrue($result->requiresReview);
        $this->assertSame(
            (int) config('access_governance.grants.break_glass_max_minutes') * 60,
            $result->ttlSeconds,
        );
    }

    #[Test]
    public function a_condition_failure_is_reported_as_insufficient_context(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->create([
            'conditions' => ['min_trust' => 'high'],
        ]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::Low,
        ));

        $this->assertSame(AccessAction::Deny, $result->action);
        $this->assertSame(AccessReason::ContextInsufficient, $result->reason);
    }

    #[Test]
    public function the_exposure_ceiling_blocks_even_a_matching_policy(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->create(['conditions' => []]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
            exposureWeight: (int) config('access_governance.exposure.thresholds.block'),
        ));

        $this->assertSame(AccessAction::Deny, $result->action);
        $this->assertSame(AccessReason::ExposureThreshold, $result->reason);
    }

    #[Test]
    public function the_exposure_step_up_threshold_requires_step_up(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->create(['conditions' => []]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
            exposureWeight: (int) config('access_governance.exposure.thresholds.step_up'),
        ));

        $this->assertSame(AccessAction::StepUp, $result->action);
        $this->assertSame(AccessReason::ExposureThreshold, $result->reason);
    }

    #[Test]
    public function a_dry_run_policy_returns_an_audited_allow_and_the_intended_action(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->dryRun()->create([
            'conditions' => [],
            'action' => AccessAction::Mask,
        ]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
        ));

        $this->assertSame(AccessAction::AllowAudited, $result->action);
        $this->assertSame(AccessReason::DryRun, $result->reason);
        $this->assertSame(AccessAction::Mask, $result->intendedAction);
    }

    #[Test]
    public function the_most_specific_policy_wins_at_the_same_priority(): void
    {
        $resource = ProtectedResource::factory()->create();

        AccessPolicy::factory()->create([
            'code' => 'GENERIC',
            'priority' => 100,
            'action' => AccessAction::Mask,
            'profiles' => [],
            'conditions' => [],
        ]);

        AccessPolicy::factory()->create([
            'code' => 'EMERGENCY',
            'priority' => 100,
            'action' => AccessAction::Allow,
            'profiles' => [AccessorProfile::Emergency->value],
            'conditions' => [],
        ]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Emergency,
            trust: ContextTrust::High,
        ));

        $this->assertSame(AccessAction::Allow, $result->action);
        $this->assertSame('EMERGENCY', $result->policy?->code);
    }

    #[Test]
    public function a_masked_policy_records_its_version_and_rule_hash(): void
    {
        $resource = ProtectedResource::factory()->create();

        $policy = AccessPolicy::factory()->create([
            'action' => AccessAction::Mask,
            'conditions' => [],
        ]);

        $result = $this->evaluate($resource, new AccessRequestContext(
            profile: AccessorProfile::Standard,
            trust: ContextTrust::High,
        ));

        $this->assertSame($policy->version, $result->policyVersion());
        $this->assertNotNull($result->rulesHash());
        $this->assertSame($policy->rules_hash, $result->rulesHash());
    }

    private function evaluate(ProtectedResource $resource, AccessRequestContext $context): AccessDecisionResult
    {
        return app(AccessPolicyEvaluator::class)->evaluate($resource, $context);
    }
}
