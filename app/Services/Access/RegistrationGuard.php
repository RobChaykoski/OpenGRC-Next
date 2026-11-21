<?php

namespace App\Services\Access;

use App\Enums\AccessRuleScope;
use App\Services\AppLogger;
use App\Support\Access\AccessRuleValue;
use Illuminate\Validation\ValidationException;

/**
 * Applies registration rules to account, vendor and public-request creation.
 *
 * Self-service signup is closed in OpenGRC, so the registration boundary is the
 * admin-created account and the public request form. CLI account creation still
 * applies the email-domain rule but has no request IP to evaluate; that bypass is
 * deliberate and documented so an operator can recover from a lockout.
 */
final class RegistrationGuard
{
    public function __construct(private readonly AccessRuleEvaluator $evaluator) {}

    public function decision(?string $email, ?string $ip): AccessDecision
    {
        return $this->evaluator->evaluate(AccessRuleScope::Registration, $ip, $email);
    }

    /**
     * @throws ValidationException
     */
    public function assertAllowed(string $field, string $email, ?string $ip = null): void
    {
        $decision = $this->decision($email, $ip);

        if ($decision->allowed) {
            return;
        }

        $this->logDenied($email, $ip, $decision);

        throw ValidationException::withMessages([
            $field => __('access-rules.registration_denied'),
        ]);
    }

    public function logDenied(?string $email, ?string $ip, AccessDecision $decision): void
    {
        AppLogger::warning(
            category: 'access_rules',
            event: 'RegistrationDenied',
            message: 'Registration denied by access rules',
            context: [
                'email_domain' => AccessRuleValue::emailDomain((string) $email),
                'ip' => $ip,
                'reason' => $decision->reason,
                'rule_id' => $decision->ruleId,
            ],
            causer: auth()->user(),
        );
    }
}
