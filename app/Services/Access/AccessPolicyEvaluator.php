<?php

namespace App\Services\Access;

use App\Enums\AccessAction;
use App\Enums\AccessReason;
use App\Enums\ContextTrust;
use App\Enums\DocumentClassification;
use App\Models\AccessPolicy;
use App\Models\ProtectedResource;
use Illuminate\Support\Collection;

/**
 * Deterministic policy evaluation (ADR 0049).
 *
 * The evaluator is a pure decision function: given a protected resource and the
 * asserted context, it returns an action and a reason. It never returns "allow"
 * because it could not decide. Order is fixed and documented:
 *
 *   stale classification -> exposure ceiling -> explicit deny -> active grant
 *   -> break-the-glass -> matched policy -> default deny
 *
 * An explicit deny always wins, whatever its priority, because a deny is a
 * decision someone made on purpose.
 */
class AccessPolicyEvaluator
{
    public function __construct(protected ExposureService $exposure) {}

    public function evaluate(
        ProtectedResource $resource,
        AccessRequestContext $context,
        ?Collection $policies = null,
    ): AccessDecisionResult {
        $tier = $resource->tier ?? DocumentClassification::Restricted;
        $weight = $this->exposure->weightFor($tier);

        // A stale tag is a coverage gap, not a clean result.
        if ($tier->atLeast(DocumentClassification::Confidential) && $resource->isStale()) {
            return new AccessDecisionResult(
                action: AccessAction::Mask,
                reason: AccessReason::StaleClassification,
                riskWeight: $weight,
            );
        }

        $thresholds = $this->exposure->thresholds();

        if ($context->exposureWeight >= $thresholds['block']) {
            return new AccessDecisionResult(
                action: AccessAction::Deny,
                reason: AccessReason::ExposureThreshold,
                riskWeight: $weight,
            );
        }

        if ($context->exposureWeight >= $thresholds['step_up']) {
            return new AccessDecisionResult(
                action: AccessAction::StepUp,
                reason: AccessReason::ExposureThreshold,
                riskWeight: $weight,
            );
        }

        $nearMiss = false;
        $matching = $this->matchingPolicies($policies, $resource, $context, $nearMiss);

        $deny = $matching->first(fn (AccessPolicy $policy): bool => $policy->action === AccessAction::Deny);

        if ($deny instanceof AccessPolicy) {
            return new AccessDecisionResult(
                action: AccessAction::Deny,
                reason: AccessReason::PolicyMatch,
                policy: $deny,
                riskWeight: $weight,
            );
        }

        if ($context->grantActive) {
            return new AccessDecisionResult(
                action: AccessAction::AllowAudited,
                reason: AccessReason::GrantActive,
                ttlSeconds: $context->grantTtlSeconds,
                riskWeight: $weight,
            );
        }

        if ($context->hasJustification && $context->profile->canBreakGlass()) {
            if (! $context->trust->atLeast(ContextTrust::High)) {
                return new AccessDecisionResult(
                    action: AccessAction::Deny,
                    reason: AccessReason::ContextInsufficient,
                    riskWeight: $weight,
                );
            }

            return new AccessDecisionResult(
                action: AccessAction::TimeBoxed,
                reason: AccessReason::BreakGlass,
                ttlSeconds: $this->breakGlassTtlSeconds(),
                requiresReview: true,
                requiresJustification: true,
                riskWeight: $weight,
            );
        }

        $winner = $this->bestPolicy($matching);

        if (! $winner instanceof AccessPolicy) {
            return new AccessDecisionResult(
                action: AccessAction::Deny,
                reason: $nearMiss ? AccessReason::ContextInsufficient : AccessReason::NoPolicy,
                riskWeight: $weight,
            );
        }

        if ($winner->dry_run) {
            return new AccessDecisionResult(
                action: AccessAction::AllowAudited,
                reason: AccessReason::DryRun,
                policy: $winner,
                intendedAction: $winner->action,
                riskWeight: $weight,
            );
        }

        return new AccessDecisionResult(
            action: $winner->action,
            reason: AccessReason::PolicyMatch,
            policy: $winner,
            ttlSeconds: $this->policyTtlSeconds($winner),
            requiresReview: $winner->requires_review
                || ($resource->requiresHeightenedReview() && $winner->action->isPermissive()),
            requiresJustification: $winner->requires_justification,
            riskWeight: $weight,
        );
    }

    /**
     * @param  Collection<int, AccessPolicy>|null  $policies
     * @return Collection<int, AccessPolicy>
     */
    protected function matchingPolicies(
        ?Collection $policies,
        ProtectedResource $resource,
        AccessRequestContext $context,
        bool &$nearMiss,
    ): Collection {
        $tier = $resource->tier ?? DocumentClassification::Restricted;

        $contextArray = [
            'trust' => $context->trust->value,
            'managed_device' => $context->managedDevice,
            'has_justification' => $context->hasJustification,
            'zone' => $context->zone,
            'purpose_code' => $context->purposeCode,
        ];

        $policies ??= AccessPolicy::query()->active()->get();

        return $policies
            ->filter(function (AccessPolicy $policy) use ($tier, $context, $contextArray, &$nearMiss): bool {
                if (! $policy->is_active) {
                    return false;
                }

                if ($policy->effective_from !== null && $policy->effective_from->isFuture()) {
                    return false;
                }

                if (! $policy->matchesTier($tier) || ! $policy->matchesProfile($context->profile)) {
                    return false;
                }

                if ($policy->requires_justification && ! $context->hasJustification) {
                    $nearMiss = true;

                    return false;
                }

                if (! $policy->conditionsMet($contextArray)) {
                    $nearMiss = true;

                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  Collection<int, AccessPolicy>  $matching
     */
    protected function bestPolicy(Collection $matching): ?AccessPolicy
    {
        return $matching
            ->sort(function (AccessPolicy $a, AccessPolicy $b): int {
                return [$a->priority, -$a->specificity(), (int) $a->getKey()]
                    <=> [$b->priority, -$b->specificity(), (int) $b->getKey()];
            })
            ->first();
    }

    protected function policyTtlSeconds(AccessPolicy $policy): ?int
    {
        if (! $policy->action->requiresTimeBox()) {
            return null;
        }

        $minutes = $policy->max_duration_minutes
            ?? (int) config('access_governance.grants.max_duration_minutes', 480);

        return $minutes * 60;
    }

    protected function breakGlassTtlSeconds(): int
    {
        return (int) config('access_governance.grants.break_glass_max_minutes', 30) * 60;
    }
}
