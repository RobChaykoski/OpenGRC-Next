# Vendor subcontractors (fourth parties)

A vendor's subcontractor can touch your data or provide part of the service you
depend on. OpenGRC records those fourth parties on the vendor, without creating a
second vendor record for each one.

This is a register, not a supply-chain graph. The subcontractor's own suppliers
are out of scope.

## What to record

| Field | Why it exists |
|---|---|
| Name | Who the fourth party is |
| Service | What they do for the vendor |
| Country | Where the service or data is handled |
| Personal data | Whether they can see or process personal data |
| Risk rating | Your view of the fourth-party risk, using the vendor rating scale |
| Last reviewed | When the relationship was last checked |
| Notes | Contract, assurance or access detail |

## Adding a subcontractor

Open the vendor, then use the **Subcontractors** relation on the vendor page. The
vendor list shows a count so a supplier with unexamined fourth parties is visible.

The record is intentionally flat:

- It belongs to one vendor.
- It has no link to a control, risk or application of its own.
- It is not scored automatically from the vendor or from questionnaires.
- Its risk rating is a human judgement, not an inherited vendor score.

## Review

A subcontractor with no review date, or a review older than twelve months, is
shown as overdue in the relation table. That is a visual cue, not a scheduled
reminder.

For the review, confirm:

1. The service is still used and still in scope.
2. The country and data access are still correct.
3. The contract and security terms still cover the data.
4. A current assurance report or questionnaire exists where required.
5. The risk rating still reflects what you know.

## When to create a separate vendor

Create a full vendor record instead when:

- you contract with the fourth party directly;
- it provides a service you depend on independently of the vendor;
- it needs its own questionnaires, documents or owner.

Use the subcontractor register when the fourth party is only reachable through
the vendor and you do not manage the relationship directly.

## Evidence and limits

- The subcontractor record is evidence that the fourth party was identified and
  reviewed. It is not evidence that the subcontractor is secure.
- The vendor's assurance report or questionnaire is the supporting evidence.
- No inheritance: a low-risk vendor does not make its subcontractor low risk.
- No graph: a subcontractor's own suppliers are not modelled. Add them as
  separate vendors if you manage them.
- No notification or automatic review schedule.
- No personal data beyond a business contact if you choose to put one in the
  notes; do not store customer data here.
