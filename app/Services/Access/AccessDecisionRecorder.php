<?php

namespace App\Services\Access;

use App\Enums\DocumentClassification;
use App\Exceptions\DuplicateAccessDecisionException;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use Illuminate\Database\QueryException;

/**
 * Append the decision an enforcement point received (ADR 0051).
 *
 * The record is evidence: it carries the classification snapshot, the asserted
 * context, the matched policy version, the computed exposure weight, the
 * enforcement point that asked and the correlation id the two sides join on.
 * Nothing here is editable afterwards.
 *
 * A replayed request is not a new decision. The endpoint looks the key up before
 * evaluating; when two retries race, the unique index is the authority and this
 * class raises DuplicateAccessDecisionException so the caller's transaction —
 * including any break-glass grant — rolls back.
 */
class AccessDecisionRecorder
{
    public function findReplay(string $pep, string $idempotencyKey): ?AccessDecision
    {
        return AccessDecision::query()
            ->where('pep', $pep)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function record(
        AccessDecisionRequest $request,
        AccessDecisionResult $result,
        string $pep,
        ?int $latencyMs = null,
        ?string $correlationId = null,
        ?AccessGrant $grant = null,
    ): AccessDecision {
        $attributes = [
            'correlation_id' => $correlationId,
            'pep' => $pep,
            'idempotency_key' => $request->idempotencyKey,
            'request_hash' => $request->requestHash,
            'subject_type' => $request->subjectType,
            'subject_id' => null,
            'subject_label' => $request->subjectLabel,
            'protected_resource_id' => $request->resource?->getKey(),
            'resource_ref' => $request->resourceRef,
            'tier' => $request->resource?->tier ?? DocumentClassification::Restricted,
            'profile' => $request->context->profile,
            'purpose_code' => $request->context->purposeCode,
            'context' => $request->context->toSnapshot(),
            'matched_policy_id' => $result->policy?->getKey(),
            'policy_version' => $result->policyVersion(),
            'rules_hash' => $result->rulesHash(),
            'action' => $result->action,
            'reason' => $result->reason,
            'ttl_seconds' => $result->ttlSeconds,
            'risk_weight' => $result->riskWeight,
            'access_grant_id' => $grant?->getKey(),
            'latency_ms' => $latencyMs,
            'decided_at' => now(),
        ];

        try {
            return AccessDecision::create($attributes);
        } catch (QueryException $exception) {
            if ($request->idempotencyKey === null) {
                throw $exception;
            }

            $existing = $this->findReplay($pep, $request->idempotencyKey);

            if (! $existing instanceof AccessDecision) {
                throw $exception;
            }

            throw new DuplicateAccessDecisionException($existing);
        }
    }
}
