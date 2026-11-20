# 0032 — Policy acknowledgement is a versioned attestation, not a click

Status: accepted
Date: 2026-11-20

## Context

The capability map said policies had acknowledgements, but the product had no
acknowledgement record. A policy could be published, and nobody could show who had
read it, when, or which version they had seen.

A policy changes. An acknowledgement of last year's text is not evidence that the
current policy was presented. Overwriting a single "acknowledged" flag would
destroy that distinction. A click also proves only that the policy was presented
and accepted; it does not prove comprehension.

## Decision

1. **An acknowledgement is tied to a policy revision.** The revision is a hash of
   the content that matters: code, name, document type, purpose, scope, body and
   effective date. A material change produces a new revision.
2. **History is append-only.** Re-acknowledging a changed policy creates a new
   record; the old record remains and becomes stale. There is one current
   acknowledgement per user and policy revision.
3. **The user sees a queue.** **My policies** lists active policies, shows whether
   the current revision is acknowledged, and records the acknowledgement with the
   user, time and method.
4. **The admin sees coverage.** A read-only register lists acknowledgements by
   policy and user, with a current/stale badge. It is not an editable compliance
   field; the user's action creates the record.
5. **Retired and future policies are out of scope.** A policy outside its
   effective window does not appear in the queue.
6. **No automatic acknowledgement.** A login, a page view or an email open is not
   an acknowledgement. Bulk import is allowed only with evidence of the
   acknowledgement event.
7. **A click is not training.** For high-risk policies, pair the acknowledgement
   with a quiz, a briefing or a training record. Do not describe a click as
   comprehension.
8. **No sensitive tracking data.** The record stores the method and time, not an
   IP address, device fingerprint or biometric. That is enough for the control
   and avoids a new personal-data pool.

## Consequences

- An auditor can answer "who acknowledged which version of this policy?" without
  a spreadsheet.
- A policy update creates a visible re-acknowledgement task instead of silently
  carrying an old acknowledgement forward.
- The queue is all active policies for every user. Audience targeting is a later
  feature; until then, a policy that applies only to one team is still shown to
  everyone.
- The revision hash changes when content changes. A change to a non-content field
  may still change the hash if it is in the hashed set; that is a deliberate,
  reviewable trade-off.
- A click remains weak evidence of awareness. The register records the event; the
  programme decides whether training is also required.

## Alternatives considered

- **A single acknowledged flag on the user or policy.** Rejected: it cannot say
  which version was acknowledged or when, and it is overwritten on the next click.
- **Use training completion as the only record.** Rejected: training is a
  different control; policy acceptance needs its own event.
- **Auto-acknowledge on login.** Rejected: it records a page load, not a decision.
- **Paper sign-off.** Rejected: no consistent register, no revision link and no
  coverage view.
- **Track IP and device.** Rejected for this slice: it adds personal data without
  improving the control.
