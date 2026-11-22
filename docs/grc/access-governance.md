# Access governance

Access governance answers a question the rest of the platform could not: **why
was this person or service allowed to see this data, at this moment, under these
conditions — and who reviewed it afterwards?**

It has three parts, matching the menu:

| Part | Menu | What it holds |
|---|---|---|
| Data side | Access governance → Protected resources | Where sensitive data lives, its classification, consent directive, owner and freshness |
| Rules | Access governance → Access policies | classification level × accessor profile × context → action |
| Runtime | Access governance → Access grants, Access decisions | Time-bound grants and append-only decisions |

OpenGRC **governs** access. It does not enforce it: it does not sit in a query
path, mint tokens or proxy a database. The enforcement point (an EHR, a gateway,
OPA in front of a warehouse) submits the asserted context over
`POST /api/v1/access/decisions` and honours the answer. This is deliberate — see
ADR 0049 and ADR 0051.

## Objects

| Object | Holds | Does not hold |
|---|---|---|
| `ProtectedResource` | name, type, classification level and label, consent directive, jurisdiction, crown-jewel flag, owner, classification source and date | sample values, rows, file paths |
| `AccessPolicy` | tiers, profiles, conditions, action, priority, version, rule hash, owner, review date, dry-run flag | code executables, regular expressions |
| `AccessGrant` | subject, resource, purpose, justification, approver, start, expiry, state, break-glass reason, privacy review | the data itself |
| `AccessDecision` | subject, resource reference, tier snapshot, context snapshot, matched policy and version, action, reason, TTL, exposure weight, hash chain | content, raw IP, device identifiers |

## Decision order

The evaluator is deterministic and never returns "allow" because it could not
decide:

1. **Stale classification** on a confidential or restricted resource → mask.
   A tag older than `access_governance.classification.stale_after_days` is a
   coverage gap, not a clean result.
2. **Exposure ceiling** → step-up at the `step_up` threshold, deny at `block`.
3. **Explicit deny** from any matching policy → deny. Deny wins at any priority.
4. **Active grant** → audited allow with the grant's TTL.
5. **Break-the-glass** with a justification and high context trust → time-boxed
   allow with a privacy review. Break-glass outside a trusted context → deny.
6. **Best matching policy** by priority, then specificity (a rule naming tiers
   and profiles beats a generic one).
7. **Default deny.**

Dry-run policies record the decision but return an audited allow; the intended
action is shown on the decision record. Use them before blocking anyone.

## Exposure: the risk-adaptive input

Exposure is the weight of sensitive data consumed by one subject in a rolling
window, derived from decisions:

```
weight  = public 0, internal 1, confidential 3, restricted 8
window  = access_governance.exposure.window_minutes (60)
review  = 25   step_up = 50   block = 100
```

It is computed, never stored separately, so it cannot drift from the log. Only
**permissive** decisions (`allow`, `allow_audited`, `time_boxed`) contribute
weight: a denied, masked or filtered request read no data, and counting it would
let a noisy enforcement point push an innocent subject into step-up. The number
answers "is this much data in this window unusual", not "what did it cost". The
decision snapshot carries the weight at decision time even if the configuration
changes later.

## Worked scenario: emergency room break-the-glass

A physician opens an unassigned patient's record during a resuscitation.

1. **Enforcement point**. The EHR gateway authenticates the clinician, checks the
   facility and device posture, and sees that the patient is not on the
   physician's list. It prompts for an emergency reason and receives `trauma`.
2. **Request**. The gateway submits: subject, resource reference,
   profile `emergency`, trust `high`, zone `ED_Zone_A`, purpose `care`,
   `has_justification: true`.
3. **OpenGRC decides**. The resource is classified restricted and current. The
   exposure weight is below the ceiling. A matching policy says masked access
   for standard profiles, but the emergency break-glass path applies: the answer
   is `time_boxed` with a 30-minute TTL, reason `break_glass`.
4. **Grant**. The break-glass workflow (or the enforcement point, through it)
   records the grant with the justification, the reason code and a privacy review
   due in five days. The decision endpoint answers the request; it does not mint
   the grant. The approver must be someone other than the requester.
5. **Review**. The privacy officer records an outcome — justified, not justified
   or escalated — with notes. Overdue reviews are reported by the expiry
   command.
6. **Expiry**. The scheduled command marks the grant expired when the window
   ends. Revocation is event-driven (stabilised, transferred, encounter closed);
   expiry is the fail-safe for the event that never arrives.

What OpenGRC does **not** do: it does not unmask anything, does not hold the
token, and is not in the clinical path. If OpenGRC is unavailable, the gateway
still needs a local answer. That answer is deny or mask.

## Integration contract

```
POST  /api/v1/access/decisions
Authorization: Bearer <token with access.decide>
Idempotency-Key: <optional, 1–128 printable characters>
Content-Type: application/json

{
  "subject":      {"type": "user", "label": "dr.example"},
  "resource":     {"ref": "clinical.records", "urn": "ehr://patient/1234"},
  "profile":      "emergency",
  "trust":        "high",
  "zone":         "ED_Zone_A",
  "purpose_code": "care",
  "justification_present": true,
  "managed_device": true,
  "clinical_reason": "trauma"
}

200 OK
{
  "action":       "time_boxed",
  "reason":       "break_glass",
  "ttl_seconds":  1800,
  "correlation_id": "…",
  "policy_version": null,
  "rules_hash":   null
}
```

Authentication is a Sanctum token with the `access.decide` ability, bound to a
client. The `client_id` is the enforcement point: it is recorded as `pep`, used
to scope the idempotency key and to rate-limit the token. A browser session is
refused.

Create a dedicated service account and mint the token once:

```bash
php artisan opengrc:create-user ehr-gateway@example.com --role=viewer
php artisan opengrc:token:create ehr-gateway@example.com \
  --name=ehr-gateway \
  --client=acme-ehr \
  --ability=access.decide
```

The role does not grant or withhold a decision: the endpoint authorises the
token's ability and client, not the user's panel role. Use the least privileged
role anyway, and keep the service account's email domain inside the domain rules
you have configured (ADR 0047).

Rotate by minting a new token and deleting the old one
(`$user->tokens()->where('name', 'ehr-gateway')->delete()`). One token per
enforcement point: the `pep` record and the rate limit both depend on it.

The platform access rules (ADR 0047) run before the endpoint. A denied IP or
email domain is a `403`, and the enforcement point must treat it as deny.

`resource.ref` matches `protected_resources.external_urn`. The human reference
(`PR-00042`) is also accepted so an operator or a test can call the endpoint
without inventing a URN. An unregistered reference is a recorded **deny** with
reason `unknown_classification`, not a 404 — the coverage gap belongs in the
decision log.

The caller asserts the subject and context. It cannot assert the classification,
the grant, the exposure weight or the answer: `exposure_weight` and
`grant_active` in the body are ignored, and exposure is computed from the
decision log for the rolling window.

`resource.urn` is accepted for contract compatibility and deliberately **not
stored**. It is a patient- or row-level identifier; the enforcement point keeps
it beside the `correlation_id`.

Response codes:

| Code | Meaning |
|---|---|
| 200 | A decision, including `deny` |
| 401 | No token |
| 403 | Browser session, missing `access.decide`, or a token not bound to a client |
| 422 | Invalid context, or an `Idempotency-Key` reused with a different request |
| 429 | Per-token throttle exceeded |

The response is `Cache-Control: no-store` and carries no policy name, policy text
or classification. The per-token limit defaults to 600 requests/minute
(`ACCESS_DECISION_RATE_LIMIT`), independent of the 60/minute shared API limit.

## Idempotency and retries

An enforcement point retries when it does not get an answer. Send the same
`Idempotency-Key` on the retry:

- the recorded decision is returned, with the same `correlation_id`;
- no second decision is appended, so exposure is not consumed twice;
- the same key with a different asserted request is rejected with `422`.

The key is optional. Without one, every call is a new, individually attributable
decision.

Rules for the enforcement point:

- **No decision means deny.** A timeout, a 5xx or an unparseable answer is not
  permission.
- **Never re-ask until you get an allow.** A denied request is a decision.
- Send a **zone or a coarse label**, not a raw IP address. Send a device posture
  boolean, not a device identifier.
- Record the `correlation_id` on your side; that is how the two records join.
- Do not put an email address in `subject.label`; use a display name or a service
  identifier.

## Screens and roles

| Screen | Viewer | Editor | Admin |
|---|---|---|---|
| Protected resources | Read | Create and edit | Delete |
| Access policies | Read | Create and edit | Delete |
| Access grants | Read | Request, approve, revoke, review | Same |
| Access decisions | Read | Read | Read |

Decisions are append-only for every role, including administrators. A correction
is a new decision, never an edit.

## Metrics that matter

- Coverage: resources with a current classification ÷ resources registered.
- Freshness: age of the oldest classification in use.
- Decisions by action and reason, especially `context_insufficient` and
  `no_policy` — those are configuration gaps, not user behaviour.
- Break-glass volume per period, and the share with a completed review.
- Overdue privacy reviews.
- Repeat grants to the same subject and resource: a sign the policy is wrong.

Avoid: total decisions, total grants, "blocked" counts without a denominator.

## Privacy and retention

- `subject_label` is personal data. Keep it to a display name or service
  identifier; do not put an email address in the label if the subject is already
  linked to a user.
- The context snapshot holds profile, trust, zone, purpose and time. It must not
  hold raw IP addresses, device identifiers, user agents or clinical free text.
- `justification` is free text written by a professional. It will sometimes
  contain personal data. Treat it as access-controlled and include it in any
  retention decision.
- Decisions and grants are evidence. Retain them for the audit or legal period
  that applies; do not delete a break-glass decision to tidy the list. Redact
  free text where needed, keep the record.
- Soft deletes apply to resources and policies, not to decisions.

## Operating it

1. Register the resource and its classification. Confirm the owner and the
   classification date.
2. Write a policy that starts in **dry run**, with the smallest scope that
   covers the decision you actually need.
3. Run the enforcement point against recorded decisions for a period. Review
   `no_policy` and `context_insufficient` before enforcing.
4. Turn dry run off. Keep the deny rule explicit.
5. For break-glass, agree the reason vocabulary, the TTL cap and the review
   owner with the clinical or business owner.
6. Run `php artisan opengrc:access:expire-grants --dry-run` weekly, and the
   scheduled command keeps state honest.
7. Review policies on their due date. An overdue policy is reported, not
   silently trusted.

## Limits

- No enforcement, no token issuance, no query rewriting. The endpoint serves the
  decision; the enforcement point still enforces it, and a missing answer is
  deny.
- A break-glass decision does not create a grant, and therefore does not create
  the privacy review obligation by itself. Raising the grant is a separate step.
- Grants are matched by resource plus subject type and label. The contract has no
  account id, so a label that drifts between the grant and the enforcement point
  stops matching silently.
- No batch evaluation. One request is one decision and one idempotency key.
- No OPA/Trino adapter is shipped. The policy table and rule hash are the inputs;
  the adapter is a separate service.
- No exact-data-match or content fingerprinting: the decision is about a
  resource and a context, not a document.
- No automated revocation from a clinical system; the enforcement point or an
  integration must revoke, and expiry is the backstop.
- The hash chain detects alteration and ordering, not intent, and depends on
  serialised writers.
- A lockbox is recorded and raises the bar. Whether it may be overridden in an
  emergency is a legal question for counsel.
