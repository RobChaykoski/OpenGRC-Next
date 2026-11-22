<?php

namespace App\Services\Access;

use App\Enums\AccessAction;
use App\Enums\AccessReason;
use App\Models\AccessPolicy;

/**
 * The outcome of policy evaluation: what to do, why, and which rule decided it.
 */
final readonly class AccessDecisionResult
{
    public function __construct(
        public AccessAction $action,
        public AccessReason $reason,
        public ?AccessPolicy $policy = null,
        public ?int $ttlSeconds = null,
        public bool $requiresReview = false,
        public bool $requiresJustification = false,
        public ?AccessAction $intendedAction = null,
        public int $riskWeight = 0,
    ) {}

    public function rulesHash(): ?string
    {
        return $this->policy?->rules_hash;
    }

    public function policyVersion(): ?int
    {
        return $this->policy?->version;
    }

    public function isPermissive(): bool
    {
        return $this->action->isPermissive();
    }
}
