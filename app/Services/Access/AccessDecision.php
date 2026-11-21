<?php

namespace App\Services\Access;

/**
 * The result of evaluating access rules.
 *
 * Reasons are deliberately coarse: the user sees one message, the log records
 * which dimension or rule failed. Never disclose the rule list in the response.
 */
final class AccessDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?int $ruleId = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, ?int $ruleId = null): self
    {
        return new self(false, $reason, $ruleId);
    }
}
