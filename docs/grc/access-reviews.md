# Access reviews

An access review asks a named reviewer to confirm that every account still needs
its access, or to revoke it. OpenGRC records the campaign, the snapshot and the
decisions. The identity provider still performs the actual disablement.

See ADR 0033 for the boundary.

## Create a campaign

**Entities → Access reviews → New**:

- name the campaign (for example "Quarterly access review — Q1");
- choose the reviewer;
- set a due date;
- record the scope and method.

Creating the campaign starts it and snapshots every account, its role and its MFA
state. Deactivated accounts are included.

## Decide each account

Open the campaign and use the **Accounts** relation:

| Decision | Meaning |
|---|---|
| Attest | The account and its role are still required |
| Revoke | The access should be removed |
| Not applicable | The account is out of scope for this campaign, with a reason |

Each decision records the actor, the time and any note.

- A reviewer cannot attest their own account; another reviewer decides it.
- A revoke decision is evidence, not an automatic disablement. Disable the
  account in the identity provider and keep that change record.
- A decision can be changed while the campaign is active; the latest decision is
  the record.

## Complete the campaign

The **Complete review** action appears when every account has a decision. A
completed campaign is immutable. If the scope changes, start a new campaign.

An incomplete campaign is not evidence of a review. Do not mark it complete by
deleting the pending accounts.

## Review cadence

| Frequency | Activity |
|---|---|
| Quarterly | Full user access review for OpenGRC accounts |
| On joiners/movers/leavers | A targeted review or an immediate decision |
| After a role change | Confirm the new role in the next campaign |
| Annually | Re-baseline the reviewer, scope and cadence |

A quarterly campaign is a common default. A high-risk or regulated environment may
need monthly or event-driven reviews.

## Evidence

For each campaign, keep:

- the campaign name, reviewer, scope, start and completion dates;
- the account snapshot: user, role, MFA state;
- each decision, actor, time and note;
- the identity-provider change record for every revoke.

The campaign is evidence for access-control controls (NIST AC-2/AC-6, ISO 27001
A.5.15/A.5.18/A.8.2, SOC 2 CC6.1–CC6.3). It does not prove the IdP change by
itself; pair the decision with the provider's audit record.

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| 100% attestation every quarter | The reviewer rubber-stamps | Require notes for sensitive roles; sample and challenge |
| Reviewer attests their own account | No independence | Use a second reviewer |
| Campaign never completes | Pending accounts are abandoned | Set a due date; follow up; complete or cancel explicitly |
| Revoke decided, access remains | Decision is not execution | Disable in the IdP; record the change |
| Live user list used as evidence | No snapshot | Use the campaign |
| Vendor or cloud roles missing | Scope was implicit | State the scope; plan the next campaign |

## Limits

- OpenGRC accounts only; vendor portal users and application entitlements are
  later.
- Role-level review, not entitlement-level; the IdP or IGA owns fine-grained
  entitlements.
- No automatic disablement, reminders or e-signature.
- No IP, device or session record; the snapshot is role and MFA state.
- A reviewer's own account always needs a second reviewer.
