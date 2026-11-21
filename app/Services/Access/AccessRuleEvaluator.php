<?php

namespace App\Services\Access;

use App\Enums\AccessRuleEffect;
use App\Enums\AccessRuleScope;
use App\Enums\AccessRuleType;
use App\Models\AccessRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates the platform-wide access rules (ADR 0047).
 *
 * Semantics:
 * 1. Any matching active deny rule denies.
 * 2. If the scope has active IP allow rules, a known IP must match one.
 * 3. If the scope has active email-domain allow rules, a known email must match
 *    one.
 * 4. Otherwise allow.
 *
 * The two allow dimensions are independent. An email-domain allowlist does not
 * whitelist an unknown IP, and an IP allowlist does not whitelist an unknown
 * email. A missing IP or email skips that dimension; the middleware always has
 * an IP, and authenticated requests have an email.
 */
final class AccessRuleEvaluator
{
    public function evaluate(AccessRuleScope|string $scope, ?string $ip, ?string $email): AccessDecision
    {
        if (! (bool) config('security.access_rules.enabled', true)) {
            return AccessDecision::allow();
        }

        try {
            $rules = $this->rulesForScope($scope);
        } catch (\Throwable) {
            // A missing table during install or an unmigrated database must not
            // lock the application out.
            return AccessDecision::allow();
        }

        return $this->decide($rules, $ip, $email);
    }

    /**
     * @param  Collection<int, AccessRule>  $rules
     */
    public function decide(Collection $rules, ?string $ip, ?string $email): AccessDecision
    {
        foreach ($rules as $rule) {
            if ($rule->effect !== AccessRuleEffect::Deny || ! $rule->isActiveNow()) {
                continue;
            }

            if ($rule->matchesIp($ip) || $rule->matchesEmail($email)) {
                return AccessDecision::deny('denied_by_rule', $rule->getKey());
            }
        }

        $ipAllows = $rules->filter(
            fn (AccessRule $rule): bool => $rule->isActiveNow()
                && $rule->type === AccessRuleType::Ip
                && $rule->effect === AccessRuleEffect::Allow
        );

        if ($ip !== null && $ip !== '' && $ipAllows->isNotEmpty() && ! $ipAllows->contains(
            fn (AccessRule $rule): bool => $rule->matchesIp($ip)
        )) {
            return AccessDecision::deny('ip_not_allowed');
        }

        $domainAllows = $rules->filter(
            fn (AccessRule $rule): bool => $rule->isActiveNow()
                && $rule->type === AccessRuleType::EmailDomain
                && $rule->effect === AccessRuleEffect::Allow
        );

        if ($email !== null && $email !== '' && $domainAllows->isNotEmpty() && ! $domainAllows->contains(
            fn (AccessRule $rule): bool => $rule->matchesEmail($email)
        )) {
            return AccessDecision::deny('email_domain_not_allowed');
        }

        return AccessDecision::allow();
    }

    /**
     * @return Collection<int, AccessRule>
     */
    public function rulesForScope(AccessRuleScope|string $scope): Collection
    {
        $scope = $scope instanceof AccessRuleScope ? $scope->value : $scope;

        if (! Schema::hasTable('access_rules')) {
            return collect();
        }

        return Cache::remember(
            AccessRule::CACHE_PREFIX.$scope,
            now()->addMinutes(5),
            fn (): Collection => AccessRule::query()
                ->active()
                ->forScope($scope)
                ->orderBy('id')
                ->get()
        );
    }
}
