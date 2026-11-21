# 0046 — The assistant is a fixed read-only catalogue, not an agent

Status: accepted
Date: 2026-11-20

## Context

Milestone D asks for an internal assistant over the GRC graph. The obvious
implementation is a general agent with tools: let the model choose queries, read
records, and eventually write with human approval. That is the failure mode ADR
0006 already rejected once. An open endpoint that exposes the whole data model to
a third-party client, with tool output treated as instruction, turns every
retrieved field into a potential control-path input.

The requirements are narrower:

1. Answer read-only questions about the programme ("which PHIPA controls lack
   evidence?") using the same permissions as the screens.
2. Treat retrieved content as untrusted: data is not instruction.
3. Log every action with the actor and the arguments.
4. Do not copy the data model into a third-party client.

A model that chooses its own queries also makes audits impossible: "why did it
read the risk register?" has no deterministic answer. A fixed catalogue does.

## Decision

1. **A reviewed query catalogue, not tool calling.** `AssistantIntent` names each
   supported question. `AssistantQuestionRouter` maps the question to one intent
   by reviewed phrases. A question outside the catalogue is refused and recorded;
   it is not sent to the provider and does not become SQL.
2. **The model narrates; it does not retrieve.** `AssistantQueryRunner` runs the
   fixed query, bounds the rows and returns identifiers, statuses, dates and
   counts. Only that result is sent, inside a delimited `FACTS` block. There is
   no multi-turn tool loop, no model-selected query and no write path.
3. **Facts stay visible beside the narrative.** The page renders the
   deterministic counts and rows next to a labelled AI interpretation, so the
   reader checks the answer instead of trusting it. The output is plain text: no
   HTML, markdown tables or links from the model.
4. **The same policies gate the assistant.** Every query checks
   `viewAny` on the model it reads. A future restriction cannot be bypassed by
   asking the assistant instead of opening the screen.
5. **Egress goes through `AiService`.** DLP blocks HR, special-category and
   credential content before the HTTP call. If no provider is configured, or the
   provider fails, the facts are returned without a narrative ("facts only").
6. **Prompt injection is treated as data, and recorded.** The system prompt fixes
   the role and rules; the facts block is explicitly untrusted; `PromptGuard`
   records common instruction-override patterns on the run. A flag never blocks a
   legitimate question, but it is visible to the reviewer.
7. **Every run is recorded.** `ai_assistant_runs` stores the actor, question,
   resolved intent, arguments, status, result counts and SHA-256, narrative,
   provider/model, tokens, injection flags and error class. Retrieved rows are
   not stored again; the hash identifies the result and the question can be
   re-run. A retention command deletes runs older than 90 days by default.
8. **The catalogue retrieves no payloads.** Evidence payloads, attachments,
   document and policy bodies, and personal contact details are out of scope.

## Consequences

- The assistant is less capable than a general agent. A question outside the
  catalogue needs a code change, a reviewed query and a test. That is the point.
- The narrative can still be wrong or overconfident. The UI labels it, shows the
  facts and tells the reader to review it; it is never the only output.
- Questions and answers are stored as accountability evidence. They may contain
  programme detail, so access is limited to the asker and admins, and retention
  is explicit.
- Provider-side retention (for example OpenAI `store`) is a provider setting, not
  something OpenGRC can undo. Only bounded, non-payload facts are sent, and DLP is
  the hard block.
- Human-approved writes remain unbuilt. Adding one is a new decision with an
  approval object, an actor, a diff and a rollback; it must not be smuggled in as
  "the assistant can now edit".
