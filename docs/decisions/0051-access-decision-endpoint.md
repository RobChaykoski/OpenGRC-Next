# 0051 — The decision endpoint is scoped, idempotent and computes its own inputs

- Status: accepted
- Date: 2026-11-21

## Context

ADR 0049 chose the governance objects for real-time access — protected
resources, policies, grants, append-only decisions — and left the enforcement
point out of scope. The integration contract was prose in
`docs/grc/access-governance.md`: an enforcement point submits a context, OpenGRC
answers with an action and a correlation id, and no answer means deny.

Without an endpoint the contract was untestable and nothing could consume the
policy table. Every evaluation on a fresh install still denied, which is the
safe default but not a usable one.

The endpoint is the one place where an external system drives a security
decision in OpenGRC, so the design question is not "what fields do we return"
but "what may the caller assert, and what may it replay".

## Decision

`POST /api/v1/access/decisions` serves the decision.

### Authentication and identity

- Sanctum personal access token with the `access.decide` ability, bound to a
  `client_id`. A browser session (TransientToken) is refused: a decision is
  requested by an enforcement point, not by a person.
- The token's `client_id` is the policy enforcement point. It is recorded on the
  decision as `pep`, used as the idempotency scope and used as the rate-limit
  key. One token per enforcement point.

### The caller asserts, OpenGRC computes

The request may carry only:

| Field | Meaning |
|---|---|
| `subject.type`, `subject.label` | who/what is asking, as a display name or service id — never an email in the label |
| `resource.ref` | the resource's external reference (`external_urn`), or `PR-00042` |
| `profile`, `trust` | the asserted accessor profile and context trust |
| `zone`, `purpose_code` | coarse labels, not a raw IP |
| `justification_present`, `managed_device` | booleans, not free text |
| `clinical_reason` | a reason code from the programme's vocabulary |
| `resource.urn` | accepted for contract compatibility, deliberately **not stored** |

The caller may not assert the classification, the grant state, the exposure
weight, the policy, or the answer. Exposure is computed from the decision log
for the rolling window; an `exposure_weight` in the body is ignored. The tier
comes from the protected resource. A resource that is not registered is an
audited deny (`unknown_classification`), not a 404 and not an allow.

### Response

```json
{
  "action": "time_boxed",
  "reason": "break_glass",
  "ttl_seconds": 1800,
  "correlation_id": "…",
  "policy_version": null,
  "rules_hash": null
}
```

No policy name, policy text, tier or weight is returned. The response is
`Cache-Control: no-store`. A 5xx, a timeout or an unparseable body is deny, per
the contract.

### Idempotency

- Optional `Idempotency-Key` header, 1–128 printable characters, scoped to the
  enforcement point.
- A replay returns the recorded decision with the same `correlation_id` and
  appends nothing, so a retry cannot consume exposure twice.
- The same key with a different asserted request is a `422`. The request hash
  covers the asserted fields only, not the derived context, so a retry after a
  policy or classification change still resolves to the decision that was made.
- A unique index on `(pep, idempotency_key)` is the authority; two concurrent
  retries resolve to the row the winner wrote.

### Recording

Every answer is an append-only `AccessDecision`: classification snapshot,
asserted context, matched policy version and rule hash, computed exposure
weight, action, reason, TTL, `pep`, latency and the hash chain. There is no
"preview" mode and no write path other than a decision.

### Exposure

Exposure counts **permissive** decisions only (`allow`, `allow_audited`,
`time_boxed`). A denied, masked or filtered request read no data; counting it
would let a noisy or misconfigured enforcement point push an innocent subject
into step-up. This corrects the pre-endpoint behaviour, which summed every
decision's weight.

### Availability

Decisions are on a request path, so the shared API throttle of 60/minute is
wrong for this route. The limit is per token, default 600/minute, configurable
with `ACCESS_DECISION_RATE_LIMIT`. The limit is a safety valve, not a security
control: the enforcement point must still fail closed.

## Consequences

- The contract in `docs/grc/access-governance.md` is now executable and tested
  (24 endpoint tests, 6 exposure tests).
- A break-glass `time_boxed` answer does **not** create an `AccessGrant`. The
  privacy review obligation is real but must be raised through the grant
  workflow; auto-granting needs its own decision about requester identity,
  justification text and post-hoc approval.
- Grants are matched by resource plus subject type and label, because the
  contract carries a label and not an account id. A label that drifts between
  the grant and the enforcement point silently stops matching. This is
  documented, not hidden.
- Decisions accumulate at request-path volume. Nothing prunes them: they are
  evidence. A retention policy is a legal decision, not a cron job.
- There is no batch endpoint. A per-request call is simple and attributable; a
  batch would need per-item idempotency keys and a partial-failure contract.
- Unknown resources are recorded as denies. That makes a coverage gap visible in
  the decision log instead of an operator wondering why nothing appears.

## Alternatives considered

- **No endpoint; leave the contract to the integrator.** Rejected: an untested
  prose contract is where access control quietly diverges from the design.
- **Accept the caller's exposure weight or grant state.** Rejected: it lets the
  enforcement point talk itself into an allow and destroys the evidence value.
- **404 for an unregistered resource.** Rejected: it hides a classification
  coverage gap and pushes the decision, and its record, out of OpenGRC.
- **Make `Idempotency-Key` mandatory.** Rejected for now: it would break the
  documented contract and every naive client. Optional keys are recorded, and an
  absent key is still a single, attributable decision.
- **Return the matched policy and tier.** Rejected: the enforcement point needs
  an action, not a map of the policy estate.
- **Auto-create a grant on break-glass.** Not rejected on principle, deferred:
  it needs a requester identity, a justification text the caller does not send,
  and a post-hoc approver model.

## References

- `docs/grc/access-governance.md` — contract, screens and operating guide
- ADR 0049 — access governance objects and precedence
- ADR 0047 — platform access rules (IP and email-domain)
- ADR 0008 — append-only evidence and hash chains
- NIST SP 800-207 — policy decision point separated from enforcement
