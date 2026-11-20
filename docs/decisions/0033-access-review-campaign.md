# 0033 — Access review is a campaign snapshot, not a live role list

Status: accepted
Date: 2026-11-20

## Context

An access review asks a reviewer to confirm that every account still needs its
access, or to revoke it. The evidence is the campaign and the decisions, not a
current user list. If the evidence is a live table, a later role change silently
rewrites what the reviewer saw.

OpenGRC has three roles and a user list; it does not have an entitlement graph or
an identity provider. The enterprise identity governance tool (OpenIAM, an IdP or
a cloud IAM) owns entitlements and revocation. OpenGRC owns the campaign, the
snapshot and the attestation record.

## Decision

1. **A review is a campaign with a snapshot.** Starting a campaign captures every
   account, its role and its MFA state at that moment. Deactivated accounts are
   included; they are exactly what a reviewer needs to see.
2. **The reviewer decides each account.** Attest, revoke or not applicable, with
   an optional note. The decision records the actor and the time.
3. **No self-attestation.** A reviewer cannot attest their own account; another
   reviewer must decide it. Revoking your own account is allowed but unusual.
4. **A campaign completes only when nothing is pending.** A completed campaign is
   immutable; a later review is a new campaign.
5. **The decision is evidence, not execution.** Revoking an account in OpenGRC
   records the decision. Disabling it in the IdP, removing a role or closing a
   vendor account is a separate action with its own audit trail.
6. **No automatic attestation or revocation.** A login, a manager's silence or a
   bulk script is not a decision.
7. **Scope is explicit.** This slice covers OpenGRC user accounts. Vendor portal
   users, application entitlements and cloud roles are later campaigns or
   integrations with the identity governance tool.
8. **Minimal personal data.** The snapshot stores role and MFA state, not an IP
   address, device fingerprint or password metadata.

## Consequences

- An auditor can inspect the campaign, the snapshot and every decision without
  relying on a current user list.
- The reviewer's own account needs a second reviewer; small teams must plan for
  that.
- Completing a campaign is blocked until every account is decided. That is
  deliberate; a partially decided campaign is not evidence of review.
- A role change after the campaign does not rewrite the snapshot. The next
  campaign sees the new role.
- The campaign does not prove the IdP change happened. Pair it with the identity
  provider's change record for a revocation.

## Alternatives considered

- **Export the user list and review it in a spreadsheet.** Rejected: no snapshot
  version, no actor, no completion rule and no register.
- **Rely only on the IGA tool.** Rejected: OpenGRC's own accounts still need an
  attestation record, and the GRC evidence should not depend on a separate
  product's UI.
- **Use the live user table as the evidence.** Rejected: it changes after the
  review and cannot show what the reviewer saw.
- **Auto-attest unchanged accounts.** Rejected: silence is not a decision.
- **Auto-revoke on a revoke decision.** Rejected for this slice: it needs an IdP
  integration, a failure path and a confirmation step.
