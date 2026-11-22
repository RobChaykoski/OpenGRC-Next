<?php

namespace App\Exceptions;

use App\Models\AccessDecision;
use RuntimeException;

/**
 * A decision request lost the unique-index race for its idempotency key.
 *
 * The existing row is carried so the endpoint can answer with the decision that
 * was actually recorded, after checking the request hash. The transaction that
 * was about to write a new decision — and possibly a break-glass grant — rolls
 * back, so a retry cannot leave an orphan grant behind.
 */
class DuplicateAccessDecisionException extends RuntimeException
{
    public function __construct(public readonly AccessDecision $decision)
    {
        parent::__construct('This idempotency key was already recorded.');
    }
}
