<?php

namespace App\Http\Middleware;

use App\Enums\AccessRuleScope;
use App\Services\Access\AccessRuleEvaluator;
use App\Services\AppLogger;
use App\Support\Access\AccessRuleValue;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the platform-wide access scope on panels and API routes (ADR 0047).
 *
 * IP rules run before authentication. Email-domain rules run when a user is
 * resolvable; on the login page there is no email yet, so the domain decision is
 * made on the authenticated request that follows.
 *
 * The response never discloses the rule set or which rule matched.
 */
class EnforceAccessRules
{
    public function __construct(private readonly AccessRuleEvaluator $evaluator) {}

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        if (! (bool) config('security.access_rules.enabled', true)) {
            return $next($request);
        }

        $user = $guard !== null ? $request->user($guard) : $request->user();
        $ip = $request->ip();
        $email = $user?->email;

        $decision = $this->evaluator->evaluate(AccessRuleScope::Access, $ip, $email);

        if ($decision->allowed) {
            return $next($request);
        }

        AppLogger::warning(
            category: 'access_rules',
            event: 'AccessDenied',
            message: 'Access denied by platform access rules',
            context: [
                'ip' => $ip,
                'email_domain' => AccessRuleValue::emailDomain((string) $email),
                'user_id' => $user?->getKey(),
                'path' => $request->path(),
                'method' => $request->method(),
                'reason' => $decision->reason,
                'rule_id' => $decision->ruleId,
            ],
        );

        abort(403, __('access-rules.denied'));
    }
}
