# Control owner attestations

A control test asks "did someone check this?". An attestation asks the owner "is
this still true?". OpenGRC records both, separately.

See ADR 0041.

## What it is

A dated request to the implementation owner:

- what should be confirmed;
- who requested it and when;
- when the response is due;
- the owner's statement, or a decline with a reason.

It is not a test, not a policy acknowledgement and not an access review.

## Requesting an attestation

1. Open the implementation.
2. Use the **Attestations** tab.
3. Choose **Request attestation**.
4. Write a specific request, for example: "Confirm the Q4 access review ran and
   the exceptions are tracked."
5. Set a due date. The default is 14 days.

Only one request can be pending per implementation. A second request returns the
existing one.

## Responding

The implementation owner sees the request in **My attestations** and in the
dashboard's **Control attestations** tile. An admin can also respond from the
implementation page.

The owner can:

- **Attest** with a statement of what is true and any caveat;
- **Decline** with a reason.

A statement is required for both. "Looks fine" is not a statement; say what was
confirmed or why it cannot be.

## Expiry

A daily command marks pending requests past their due date as expired:

```bash
php artisan opengrc:controls:attestations:expire
```

Expiry does not create a response and does not change the implementation. It
records that no answer arrived in time. The owner can still be asked again with a
new request.

## What it does not do

- It does not update `last_tested_at` or `next_test_at`.
- It does not prove the control is effective; it is the owner's statement.
- It does not create a finding automatically on decline. Raise one, or update the
  implementation, as part of the response.
- It has no email, chat or ticket notification.
- It has no campaign, sampling or escalation engine.
- It does not replace a test or an audit item.

## Evidence

For an audit, keep:

- the request and its due date;
- the owner's statement and the time it was given;
- a decline reason, if applicable;
- the linked implementation and its owner;
- the next test date, which is separate from the attestation.

An attestation is level 3 evidence: a dated statement by the accountable person.
It becomes stronger when paired with the test that checked the same control.

## Cadence

Attest on a schedule that matches how often the implementation can change:

| Control type | Suggested cadence |
|---|---|
| Access and identity | Quarterly |
| Change management | Quarterly |
| Backup and recovery | Twice a year |
| Policy and governance | Annually or on revision |
| Stable physical controls | Annually |

The product does not schedule requests. The programme does.

## Limits

- No campaign or bulk request.
- No reminder or escalation.
- No sampling.
- No automatic finding on decline.
- One pending request per implementation.
- The statement is plain text; attach supporting evidence to the implementation.
