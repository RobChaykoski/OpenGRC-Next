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
OPA in front of a warehouse) submits the asserted context and honours the answer.
This is deliberate — see ADR 0049.

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

It is computed, never stored separately, so it cannot drift from the log. The
number answers "is this much data in this window unusual", not "what did it
cost". The decision snapshot carries the weight at decision time even if the
configuration changes later.

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
4. **Grant**. The grant is recorded with the justification, the reason code and
   a privacy review due in five days. The approver must be someone other than
   the requester.
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
POST  <decision endpoint>
{
  "subject":      {"type": "user", "label": "dr.example"},
  "resource":     {"ref": "clinical.records", "urn": "ehr://patient/1234"},
  "profile":      "emergency",
  "trust":        "high",
  "zone":         "ED_Zone_A",
  "purpose_code": "care",
  "justification_present": true
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

Rules for the enforcement point:

- **No decision means deny.** A timeout, a 5xx or an unparseable answer is not
  permission.
- **Never re-ask until you get an allow.** A denied request is a decision.
- Send a **zone or a coarse label**, not a raw IP address. Send a device posture
  boolean, not a device identifier.
- Record the `correlation_id` on your side; that is how the two records join.

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

- No enforcement, no token issuance, no query rewriting.
- No exact-data-match or content fingerprinting: the decision is about a
  resource and a context, not a document.
- No automated revocation from a clinical system; the enforcement point or an
  integration must revoke, and expiry is the backstop.
- The hash chain detects alteration and ordering, not intent, and depends on
  serialised writers.
- A lockbox is recorded and raises the bar. Whether it may be overridden in an
  emergency is a legal question for counsel.
