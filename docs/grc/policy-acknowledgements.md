# Policy acknowledgements

A policy acknowledgement is a record that a user was presented with a specific
policy revision and accepted it. It is not proof of comprehension. Pair
high-risk policies with training, a briefing or a quiz.

See ADR 0032 for the boundary.

## How it works

1. **My policies** lists the policies active today.
2. The user opens a policy, reads it and clicks **Acknowledge**.
3. The acknowledgement records the user, the time, the method and the policy
   revision.
4. If the policy content changes, the revision changes and the policy reappears
   as **Policy updated — acknowledge again**.
5. The old acknowledgement stays in the register as **Stale**.

## Revision

The revision is a SHA-256 of:

- policy code and name;
- document type;
- purpose, scope and body;
- effective date.

A material change produces a new revision. An acknowledgement is current only
when its revision matches the policy's current revision.

## Status

| Status | Meaning |
|---|---|
| Not acknowledged | No acknowledgement for any revision |
| Current | The acknowledgement matches the current revision |
| Stale | An older revision was acknowledged; the policy changed |

Retired and not-yet-effective policies are not in the queue.

## The admin view

**Entities → Policy acknowledgements** lists every acknowledgement with the
policy, user, time and current/stale state. It is read-only. Filter by policy or
user for coverage.

A coverage number is not an awareness verdict:

- a current acknowledgement means the policy was presented and accepted;
- it does not mean the person understood it;
- it does not replace training, a quiz or a manager briefing;
- a missing acknowledgement is a gap, not proof of misconduct.

## Review cadence

| Frequency | Activity |
|---|---|
| Weekly | Review policies with low current coverage |
| On change | Publish the revision; watch the re-acknowledgement queue |
| Quarterly | Review coverage for high-risk policies and follow up |
| Annually | Re-baseline which policies require acknowledgement and training |

## Common failures

| Symptom | Underlying problem | Fix |
|---|---|---|
| One "acknowledged" flag for everyone | No revision or user record | Use the versioned acknowledgement |
| Policy changed, coverage stayed 100% | Old acknowledgements were carried forward | Re-acknowledge after a revision |
| Click counted as training | Awareness and comprehension conflated | Pair with a quiz or briefing |
| No one knows who is outstanding | No queue or register | Use My policies and the admin register |
| Bulk sign-off by an assistant | The user did not act | Require the user's own acknowledgement |

## Limits

- The queue shows every active policy to every user; audience targeting is later.
- There is no quiz or training record in this slice.
- There is no reminder email; the navigation badge and the register are the
  current signals.
- The method is recorded as `web`; other channels need a separate import with
  evidence.
- The revision hash is content-based; a cosmetic edit may still trigger
  re-acknowledgement. That is deliberate and reviewable.
