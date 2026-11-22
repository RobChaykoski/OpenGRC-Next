<?php

namespace App\Services\Access;

use App\Models\ProtectedResource;

/**
 * One decision request as the enforcement point asserted it (ADR 0051).
 *
 * The context is built by the endpoint, never by the caller: a policy
 * enforcement point may assert who, what and under which conditions, but it may
 * not assert its own exposure weight, grant state or decision.
 */
final readonly class AccessDecisionRequest
{
    public function __construct(
        public string $subjectType,
        public string $subjectLabel,
        public string $resourceRef,
        public ?ProtectedResource $resource,
        public AccessRequestContext $context,
        public ?string $idempotencyKey = null,
        public ?string $requestHash = null,
    ) {}
}
