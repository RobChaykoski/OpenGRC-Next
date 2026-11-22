<?php

namespace App\Http\Controllers\API;

use App\Enums\AccessAction;
use App\Enums\AccessGrantState;
use App\Enums\AccessorProfile;
use App\Enums\AccessReason;
use App\Enums\ContextTrust;
use App\Enums\DocumentClassification;
use App\Http\Controllers\Controller;
use App\Models\AccessDecision;
use App\Models\AccessGrant;
use App\Models\ProtectedResource;
use App\Services\Access\AccessDecisionRecorder;
use App\Services\Access\AccessDecisionRequest;
use App\Services\Access\AccessDecisionResult;
use App\Services\Access\AccessPolicyEvaluator;
use App\Services\Access\AccessRequestContext;
use App\Services\Access\ExposureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A runtime access decision for an enforcement point (ADR 0051).
 *
 * The caller asserts the subject, the resource and the context. OpenGRC decides
 * from its own classification, policies, grants and exposure log, appends the
 * decision, and returns the action to honour. It never accepts the exposure
 * weight, the grant state or the answer from the caller.
 *
 * No decision means deny: a timeout, a 5xx or an unparseable answer is not
 * permission. An unknown resource is an audited deny, not a silent 404.
 */
class AccessDecisionController extends Controller
{
    /** Control characters have no place in a label, zone or reference. */
    protected const NO_CONTROL_CHARACTERS = 'not_regex:/[\x00-\x1F\x7F]/';

    public function store(
        Request $request,
        AccessPolicyEvaluator $evaluator,
        ExposureService $exposure,
        AccessDecisionRecorder $recorder,
    ): JsonResponse {
        $startedAt = hrtime(true);
        $pep = $this->clientId($request);

        $validated = $request->validate([
            'subject' => ['required', 'array'],
            'subject.type' => ['required', 'string', Rule::in(['user', 'machine', 'role'])],
            'subject.label' => ['required', 'string', 'max:191', self::NO_CONTROL_CHARACTERS],
            'resource' => ['required', 'array'],
            'resource.ref' => ['required', 'string', 'max:191', self::NO_CONTROL_CHARACTERS],
            'resource.urn' => ['nullable', 'string', 'max:255'],
            'profile' => ['required', 'string', Rule::enum(AccessorProfile::class)],
            'trust' => ['required', 'string', Rule::enum(ContextTrust::class)],
            'zone' => ['nullable', 'string', 'max:64', self::NO_CONTROL_CHARACTERS],
            'purpose_code' => ['nullable', 'string', 'max:32', self::NO_CONTROL_CHARACTERS],
            'justification_present' => ['nullable', 'boolean'],
            'managed_device' => ['nullable', 'boolean'],
            'clinical_reason' => ['nullable', 'string', 'max:32', self::NO_CONTROL_CHARACTERS],
        ]);

        $idempotencyKey = $this->idempotencyKey($request);
        $requestHash = $this->requestHash($validated);

        if ($idempotencyKey !== null) {
            $answer = $recorder->findReplay($pep, $idempotencyKey);

            if ($answer instanceof AccessDecision) {
                if (! hash_equals((string) $answer->request_hash, $requestHash)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => __('access_governance.decision.idempotency_conflict'),
                    ]);
                }

                return $this->answer($answer);
            }
        }

        $subjectType = (string) $validated['subject']['type'];
        $subjectLabel = (string) $validated['subject']['label'];
        $resourceRef = (string) $validated['resource']['ref'];

        $resource = $this->resolveResource($resourceRef);
        $grant = $this->activeGrant($resource, $subjectType, $subjectLabel);

        $grantTtlSeconds = $grant?->remainingMinutes();
        $context = new AccessRequestContext(
            profile: AccessorProfile::from((string) $validated['profile']),
            trust: ContextTrust::from((string) $validated['trust']),
            hasJustification: (bool) ($validated['justification_present'] ?? false),
            managedDevice: (bool) ($validated['managed_device'] ?? false),
            zone: $validated['zone'] ?? null,
            purposeCode: $validated['purpose_code'] ?? null,
            grantActive: $grant instanceof AccessGrant,
            grantTtlSeconds: $grantTtlSeconds === null ? null : $grantTtlSeconds * 60,
            // Computed from the decision log, never asserted by the caller.
            exposureWeight: $exposure->consumedBy($subjectType, $subjectLabel),
            clinicalReason: $validated['clinical_reason'] ?? null,
        );

        $result = $resource instanceof ProtectedResource
            ? $evaluator->evaluate($resource, $context)
            : new AccessDecisionResult(
                action: AccessAction::Deny,
                reason: AccessReason::UnknownClassification,
                riskWeight: $exposure->weightFor(DocumentClassification::Restricted),
            );

        $decision = $recorder->record(
            new AccessDecisionRequest(
                subjectType: $subjectType,
                subjectLabel: $subjectLabel,
                resourceRef: $resourceRef,
                resource: $resource,
                context: $context,
                idempotencyKey: $idempotencyKey,
                requestHash: $requestHash,
            ),
            $result,
            $pep,
            max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
        );

        return $this->answer($decision);
    }

    /**
     * The response is deliberately narrow: what to do, why, for how long and the
     * id that joins the two records. Policy text, tier and weights stay inside.
     */
    protected function answer(AccessDecision $decision): JsonResponse
    {
        return response()->json([
            'action' => $decision->action->value,
            'reason' => $decision->reason->value,
            'ttl_seconds' => $decision->ttl_seconds,
            'correlation_id' => $decision->correlation_id,
            'policy_version' => $decision->policy_version,
            'rules_hash' => $decision->rules_hash,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The enforcement point is the token's client. The middleware guarantees it.
     */
    protected function clientId(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! is_string($token->client_id) || $token->client_id === '') {
            throw ValidationException::withMessages([
                'client' => __('access_governance.decision.no_client'),
            ]);
        }

        return $token->client_id;
    }

    protected function idempotencyKey(Request $request): ?string
    {
        $value = $request->header('Idempotency-Key');

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '' || mb_strlen($value) > 128
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('access_governance.decision.idempotency_key'),
            ]);
        }

        return $value;
    }

    /**
     * The asserted request, with defaults applied, in a fixed key order. The
     * derived context is not part of the hash: exposure and grant state move.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function requestHash(array $validated): string
    {
        $payload = [
            'subject' => [
                'type' => (string) $validated['subject']['type'],
                'label' => (string) $validated['subject']['label'],
            ],
            'resource' => [
                'ref' => (string) $validated['resource']['ref'],
            ],
            'profile' => (string) $validated['profile'],
            'trust' => (string) $validated['trust'],
            'zone' => $validated['zone'] ?? null,
            'purpose_code' => $validated['purpose_code'] ?? null,
            'justification_present' => (bool) ($validated['justification_present'] ?? false),
            'managed_device' => (bool) ($validated['managed_device'] ?? false),
            'clinical_reason' => $validated['clinical_reason'] ?? null,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Resolve the resource the enforcement point named. The external reference is
     * the contract; the human PR-00001 id is accepted so a test or an operator can
     * call the endpoint without inventing a URN.
     */
    protected function resolveResource(string $reference): ?ProtectedResource
    {
        if (preg_match('/^PR-(\d+)$/', $reference, $matches) === 1) {
            return ProtectedResource::query()->find((int) $matches[1]);
        }

        return ProtectedResource::query()
            ->where('external_urn', $reference)
            ->orderBy('id')
            ->first();
    }

    protected function activeGrant(
        ?ProtectedResource $resource,
        string $subjectType,
        string $subjectLabel,
    ): ?AccessGrant {
        if (! $resource instanceof ProtectedResource) {
            return null;
        }

        return AccessGrant::query()
            ->where('protected_resource_id', $resource->getKey())
            ->where('subject_type', $subjectType)
            ->where('subject_label', $subjectLabel)
            ->where('state', AccessGrantState::Active)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }
}
