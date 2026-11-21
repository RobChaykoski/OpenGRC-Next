# 0048 — The security architecture view is current-state and function-based

Status: accepted
Date: 2026-11-21

## Context

The programme needs a one-page view that a CISO, control owner or auditor can
read: where the control estate is strong, where it is documented but not
evidenced, and where it is missing. The reference model is the one-page
current/future architecture diagram popularised by Managed Sentinel (2019, MIT):
controls placed by infrastructure location and grouped by NIST CSF function, with
roadmap status and an explicit lane for controls deliberately not implemented.

OpenGRC stores canonical outcomes, linked controls, policies, evidence and
validation results. It does **not** store a deployment location (endpoint,
on-prem, private cloud, public cloud, SaaS), a target state, a roadmap quarter,
or a reason a control was deliberately not implemented. Inferring those from
free-text or vendor names would produce a confident diagram that is not true.

The alternative is to add a target-state/location schema now. That is a larger
decision with its own workflow, permissions and history, and it would delay the
useful current-state view.

## Decision

1. Add a read-only **Security architecture** page that groups canonical outcomes
   by **NIST CSF 2.0 function** (Govern, Identify, Protect, Detect, Respond,
   Recover) and shows the current state: evidenced, documented only, stale,
   failing or gap.
2. The function mapping is a **presentation map** in
   `config/security_architecture.php`, keyed by the outcome's `risk_domain` and
   then category. It does not change the catalogue and an outcome appears once.
3. The page **does not invent a target state, roadmap, deployment location or
   non-implementation reason**. It states that those are programme records in the
   risk treatment, acceptance record or architecture document.
4. The durable **location × function × roadmap** view lives in
   `docs/security/architecture-view.md`, where it can be tailored to the
   organisation without pretending the database holds data it does not.
5. The page is read-only for every role. It reads live data; it is not a stored
   report.

## Consequences

- The current-state view is honest and ships without a schema change. It answers
  "what is evidenced and what is missing?" but not "what did we plan to deploy in
  Q3?"
- The target/roadmap view remains a document until a first-class planning object
  is designed. That is deliberate: a roadmap field with no workflow becomes stale
  on the first missed quarter.
- The function mapping is a judgement. It is visible in config and must be
  reviewed when the canonical catalogue changes; a wrong map misplaces an
  outcome, it does not change its coverage.
- Users who want a location view should model assets/applications/vendors and
  record the deployment surface in the architecture document; the page can link
  to those records later without changing this decision.
- The page is not compliance evidence. It reports the state of links and
  evidence, and says so.
