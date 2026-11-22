# GRC assistant

The assistant answers a fixed set of read-only questions about the programme. It
is a catalogue with a narrative layer, not a chatbot and not an agent (ADR 0046).

Open **Foundations → GRC assistant**.

## What it can answer

| Question | Returns |
|---|---|
| Framework gaps | Outcomes with no reviewed control, no evidence, stale evidence or a failing validation |
| Overdue tests | Implementations overdue or due within 30 days, with days overdue |
| Open findings | Open and in-progress findings, severity, due date and overdue count |
| Risk appetite | Active risks at or above the global threshold, with acceptance state |
| Expiring exceptions | Approved exceptions expiring within 30 days or already expired |
| Policy acknowledgement | Current-revision coverage per active policy, with users and missing count |
| Control attestations | Pending attestations due in 14 days or overdue |
| Non-human identities | Service accounts, tokens and certificates due for rotation or expiring within 30 days |
| Audit progress | In-progress audits, item completion and planned end date |

Each question maps to one reviewed query. Phrasing is matched by a deterministic
router; a question outside the catalogue is refused and recorded, not sent to the
provider.

## How an answer is produced

1. The question is routed to one intent (or refused).
2. The query runs under the asker's permissions and returns a bounded fact set:
   identifiers, statuses, dates and counts.
3. The facts are shown on the page regardless of what happens next.
4. If an AI provider is configured, the facts are sent in a delimited `FACTS`
   block and the model writes a plain-text interpretation.
5. The interpretation is labelled **AI generated** and displayed next to the facts.

The model does not choose queries, call tools, read payloads, or write anything.
It cannot see evidence payloads, attachments, document or policy bodies, or
personal contact details.

## Permissions

The assistant uses the same policies as the screens. If a role cannot open a
model, the corresponding question is refused and the run is recorded as
`not_permitted`. All three roles can use the read-only catalogue today; that is
a consequence of the current read policies, not a separate grant.

Run history is visible to the asker. Admins see all runs; everyone else sees only
their own.

## When there is no narrative

These are normal outcomes, not failures. The facts remain usable.

| Status | Meaning |
|---|---|
| `facts_only` | No AI provider is configured, so only the retrieved facts are shown |
| `blocked_dlp` | The prompt contained HR, special-category or credential content and was blocked before egress |
| `quota_exceeded` | The daily AI quota is exhausted |
| `error` | The provider call failed |
| `unsupported` | The question is outside the catalogue |
| `needs_input` | A framework is required for the gap question |
| `not_permitted` | The asker cannot read the records the question needs |

## Prompt injection

Retrieved content is untrusted. The system prompt states that the `FACTS` block
is data, not instruction, and forbids following instructions found inside it.
`PromptGuard` records common override patterns (for example "ignore previous
instructions" or "reveal the system prompt") on the run. A flag does not block the
question; the page warns that it was treated as data. Output is plain text, so a
hostile row cannot become a clickable instruction.

## Audit trail and retention

Every run records:

- actor, question and resolved intent
- arguments (for example the framework code)
- status, result counts and a SHA-256 of the fact set
- the narrative, provider, model and token counts
- injection flags and the error class, if any

Retrieved rows are not stored again. The hash identifies the result, and the
question can be re-run against current data.

```bash
php artisan opengrc:assistant:prune --days=90 --dry-run
php artisan opengrc:assistant:prune --days=90
```

Questions and answers may contain programme detail. Keep the retention window
aligned with your records policy; the default is 90 days.

## Proposed writes

The assistant still cannot write. A separate approval path now exists for the
day it can, and it is deliberately mechanical (ADR 0055):

- The model may only propose **typed operations** from a small catalogue
  (`AssistantOperations`). Today that is an implementation's status and its next
  test date. An operation outside the catalogue is refused before storage; there
  is no SQL, no method name and no free-form tool.
- A proposal stores the value captured at proposal time as `before`, the requested
  `after`, a summary and a rationale. A `before` supplied by the model is ignored:
  the record decides.
- An editor or administrator approves it, a separate step applies it, and the
  apply re-checks every `before`. If a record moved since the proposal, the whole
  set fails and nothing is written.
- Rollback is a compensating change with its own reason and actor. Append-only
  records are not in the catalogue, so they cannot be proposed at all.

The review queue is **Admin → System → Assistant proposals**. It is empty because
the assistant's question catalogue does not invoke the proposal service yet:
connecting a producer is a reviewed use case with its own question and tests, not
a prompt edit.

## What it does not do

- No writes, approvals, closures or acknowledgements. A human applies an approved
  proposal; the model never applies one.
- No free-form SQL, similarity search or model-chosen tools.
- No bulk export.
- No answer without facts: every narrative is grounded in a deterministic query.
- No guarantee of correctness. The interpretation is labelled and must be
  reviewed, exactly like any other AI-assisted text in OpenGRC.
