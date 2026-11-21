<?php

namespace App\Services\Access;

use App\Enums\AccessRuleScope;
use App\Models\AccessRule;
use App\Models\User;

/**
 * Prevents an administrator from saving a rule that locks out their own session.
 *
 * This is an affordance as much as a control: the form explains the consequence
 * and refuses the self-lockout instead of discovering it on the next request.
 * The environment kill switch remains the recovery path for any other lockout.
 */
final class AccessRuleLockoutGuard
{
    public function __construct(private readonly AccessRuleEvaluator $evaluator) {}

    public function wouldLockOutCurrentUser(AccessRule $candidate, ?int $ignoreId = null): bool
    {
        if (! (bool) config('security.access_rules.enabled', true)) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($candidate->scope !== AccessRuleScope::Access) {
            return false;
        }

        $rules = AccessRule::query()
            ->active()
            ->forScope(AccessRuleScope::Access)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get();

        if ($candidate->isActiveNow()) {
            $rules->push($candidate);
        }

        $decision = $this->evaluator->decide($rules, request()->ip(), $user->email);

        return ! $decision->allowed;
    }

    public function wouldLockOutWithout(AccessRule $removed): bool
    {
        if (! (bool) config('security.access_rules.enabled', true)) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User || $removed->scope !== AccessRuleScope::Access) {
            return false;
        }

        $rules = AccessRule::query()
            ->active()
            ->forScope(AccessRuleScope::Access)
            ->whereKeyNot($removed->getKey())
            ->get();

        $decision = $this->evaluator->decide($rules, request()->ip(), $user->email);

        return ! $decision->allowed;
    }
}
