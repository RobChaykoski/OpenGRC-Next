# Privacy requests (DSARs)

A privacy request is a data-subject or consumer rights request: access, deletion,
correction, portability, restriction, objection, opt-out of sale/share, or a limit
on sensitive personal information. It is not an audit evidence request; those live
in the audit module and have a different lifecycle.

The register keeps the regime, request type, clock, verification and decision. The
correspondence and the response package stay in the privacy case store; OpenGRC
holds the reference and the hash.

See ADR 0026 for the boundary. The day counts below are working defaults. Confirm
the current statutory period and extension rules with counsel.

## Regimes and clocks

| Regime | Base period | Extension | Notes |
|---|---|---|---|
| GDPR | One month | Two further months | Calendar month; notice required for an extension |
| CCPA / CPRA | 45 days | 45 more, to 90 total | Notice required; opt-out and sensitive-PI rights |
| PIPEDA | 30 days | 30 more | Notice and a permitted reason |
| FERPA | 45 days | Institutional policy | Education records; confirm the institution's procedure |
| Other | 30 days | 30 days | Set the date from your own policy |

The register stores both the original `due_at` and an `extended_at`. The effective
deadline is the extended date when present.

## Request types

Access, deletion, correction, portability, restriction, objection, opt-out of
sale/share, limit sensitive personal information, and other. A request that mixes
rights should be split, or the primary right recorded with the others in the
notes; the clock is per request.

## Lifecycle

| State | Meaning | Who acts |
|---|---|---|
| Received | Logged, clock started | Intake |
| Verification pending | Waiting for identity verification | Privacy team |
| In progress | Searching, compiling or applying the response | Owner |
| Extended | A recorded extension moved the deadline | Owner and approver |
| Overdue | The effective deadline passed | Owner and programme lead |
| Completed | Response delivered, with an evidence reference | Owner |
| Denied | A documented legal reason; the clock still matters | Privacy lead |
| Withdrawn | The requester withdrew | Intake |

Only Completed, Denied and Withdrawn close a request. Everything else is open and
can become Overdue.

## Verification

- Verify identity before disclosing personal data.
- Record the method and date; do not store the identity documents in the register.
- A request that cannot be verified is not automatically denied; record the
  attempt, the reason and the next action.
- An authorised agent needs their own authorisation record.

## Extensions and denials

- An extension is a decision with a reason and a new date. It is not a date edit.
- Check the statutory limit for the regime; CCPA extensions cap at 90 days in
  total, GDPR at three months.
- A denial needs a legal reason, an internal reviewer and a response to the
  requester. Denying does not stop the clock in every regime; record the advice.
- If the request is partly fulfilled, record what was withheld and why.

## Overdue enforcement

```bash
php artisan opengrc:privacy:deadlines --dry-run
php artisan opengrc:privacy:deadlines
```

The command marks open requests past their effective deadline as Overdue and
reports requests due in the next seven days. The scheduler runs it daily at 00:20.

An overdue request is a compliance event. Escalate it; do not edit the date to make
the dashboard green.

## Evidence and data minimisation

- Store a case reference rather than a full email address where your case system
  holds the contact details.
- Keep request correspondence, identity documents and the response package in the
  privacy case store; record a reference and a SHA-256 in the register.
- Do not put request content into activity log fields, exports or AI prompts.
- A completed request with no evidence reference is not proven; treat it as open
  until the reference exists.

## Metrics that matter

- Volume by regime and request type.
- On-time completion rate, not just completion count.
- Extension rate and the reasons.
- Denial rate and the legal basis.
- Requests older than the statutory period, by owner.
- Requests with no evidence reference.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| Every request is completed late | No clock or no owner | Set the regime and received date; assign an owner |
| Extensions are silent date edits | No decision record | Use `extended_at` and record the reason |
| Access request answered without verification | Verification is informal | Record the method and date before disclosure |
| Request content in the GRC database | No case-store boundary | Keep the content in the case system; store the reference |
| Metrics show 100% on time | Closed without an evidence reference | Require the reference before Completed |
| Audit requests and DSARs share a queue | The two processes were merged | Keep the models separate |

## Limits

- No consumer-facing intake portal or identity verification integration.
- No automated discovery or deletion across systems; use the discovery engine and
  the data map, then record the evidence.
- No legal advice; the regime clocks are defaults and counsel confirms the method.
- No cross-border transfer or special-category workflow in this slice.
- No notification to a supervisory authority; a breach workflow is separate.
