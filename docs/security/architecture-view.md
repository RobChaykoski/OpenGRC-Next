# Security architecture view

The **Foundations → Security architecture** page is a current-state view of the
canonical outcomes, grouped by NIST CSF 2.0 function. It answers a narrow
question: which outcomes are evidenced, documented only, stale, failing or
missing? It is not a target-state model and not a compliance conclusion.

The durable one-page architecture view is this document. It adds the dimensions
the database does not store: location, target state, roadmap and deliberate
non-implementation.

## The one-page structure

Use a single page with three axes:

1. **Location / surface.** Endpoints; on-premises intranet and extranet; private
   cloud / third-party hosting; public cloud (IaaS/PaaS); SaaS; operational
   technology; remote/BYOD. Keep the organisation's real sites, not a generic
   list.
2. **Function.** NIST CSF 2.0: Govern, Identify, Protect, Detect, Respond,
   Recover. Put each control under the function it serves, not the team that
   owns it.
3. **State.** Current, target/roadmap (with a date and owner), or deliberately
   not implemented (with the decision, rationale and acceptance).

Keep two separate lanes for functions that cross every location:

- **Information Security Office**: policy, risk, architecture, assurance,
  privacy, legal, awareness, third-party oversight.
- **Managed security services (MSSP)**: monitoring, detection, response,
  vulnerability management, device management — with the contract boundary and
  evidence access stated.

## What the OpenGRC page shows

| Column | Meaning |
|---|---|
| Outcome | Canonical outcome code and name, from the catalogue |
| Priority | Catalogue priority, not a risk score |
| Controls | Imported controls linked to the outcome |
| Evidence | Evidence records linked through those controls |
| State | Evidenced, documented only, stale, failing or gap |
| Latest evidence | Newest evidence or validation time |
| Confidence | Derived basis: documentation 20, manual 50, API 80, validation 100 |

States:

- **Evidenced** — a linked control and current evidence or validation.
- **Documented only** — a control and policy exist, but no evidence or validation.
- **Stale** — evidence is older than the outcome's expected frequency.
- **Failing** — the latest validation failed.
- **Gap** — no reviewed control, or no evidence and no policy.

The page does **not** show a target state, a location, a roadmap quarter or a
reason for non-implementation. Record those here or in the risk treatment and
acceptance records; do not infer them from vendor names or free text.

## Filling the dimensions OpenGRC does not store

| Dimension | Where to record it |
|---|---|
| Location / surface | This document; link the asset, application or vendor |
| Target state | Risk treatment plan or a dated programme decision |
| Roadmap quarter | Programme plan; link the remediation task |
| Deliberately not implemented | Accepted risk or not-applicable control with rationale and expiry |
| MSSP boundary | Contract, assurance report and evidence-access record |
| Architecture owner / review date | This document's header and governance minutes |

A target without an owner, a date and a decision is a wish. A control marked
"not implemented" without an accepted risk or an applicability reason is a gap.

## Zero-trust overlay

NIST SP 800-207 (Zero Trust Architecture, August 2020) is a useful second lens,
not a replacement for the function view. For each Protect/Detect control, ask:

- Is the **policy decision** explicit (policy engine) and separated from
  **enforcement** (policy enforcement point)?
- Are **subject and device** authenticated and authorised before the session, not
  only the network location?
- Is access **least privilege** and continuously evaluated, rather than granted
  once at the perimeter?
- Are **identity, device, network, application/workload and data** each covered,
  with telemetry feeding the decision?
- What happens when the policy engine or telemetry is unavailable? Fail closed or
  documented break-glass?

Do not label a VPN, a firewall or an identity provider "zero trust" by itself.
The architecture is the set of policy decisions, enforcement points and
telemetry, not a product.

## AI and data overlays

- **AI systems:** use NIST AI RMF and ISO/IEC 42001 as the durable governance
  references. Canada's AIDA companion document is **archived and was proposed,
  not enacted**; it is useful design input for high-impact AI obligations
  (human oversight, transparency, fairness, safety, accountability, validity and
  robustness, monitoring and notification), not a current legal obligation. See
  `docs/security/reference-register.md`.
- **Data:** NIST SP 1500-4r2 (Big Data Interoperability Framework, Volume 4:
  Security and Privacy) is a useful checklist for data governance, provenance,
  access, encryption, privacy and audit across a big-data estate. It is
  operational guidance, not a control catalogue. Use the DLP/DSPM skill for the
  data-security layer.
- **Roles:** the Canadian Centre for Cyber Security career guide's four
  categories (Govern and support; Protect and defend; Design and develop;
  Operate and maintain) are a useful role lens for training scenarios. It is a
  career guide, not a competency framework; validate role content against NICE
  or the organisation's job architecture.

## Design rules

- One page, one decision: what must change, who owns it, and by when.
- Show current and target together; a current-only map is a status report, not a
  roadmap.
- Show controls **deliberately not implemented** with the decision, so the
  reader does not mistake an informed acceptance for an oversight.
- Label the version, date and owner. A diagram without a date is a guess.
- Do not let vendor logos dominate; they are evidence of a product, not a control.
- Use accessible status encoding (label + colour), not red/green alone.
- Keep the location list to the organisation's real sites; a generic cloud icon
  is not an architecture.

## References

- Managed Sentinel, *Cybersecurity Architecture & Roadmap: Current and Future
  View* (2019, MIT). Structure only; the control set and NIST CSF version are
  dated. `https://www.managedsentinel.com/cybersecurity-architecture/`
- NIST SP 800-207, *Zero Trust Architecture* (August 2020).
  `https://doi.org/10.6028/NIST.SP.800-207`
- NIST SP 1500-4r2, *NIST Big Data Interoperability Framework, Volume 4:
  Security and Privacy* (October 2019).
  `https://doi.org/10.6028/NIST.SP.1500-4r2`
- Canadian Centre for Cyber Security, *Cyber security career guide* (2022).
  `https://www.cyber.gc.ca/en/guidance/cyber-security-career-guide`
- Innovation, Science and Economic Development Canada, *AIDA companion
  document* (archived; proposed Bill C-27, not enacted).
  `https://ised-isde.canada.ca/site/innovation-better-canada/en/artificial-intelligence-and-data-act-aida-companion-document`

See `docs/security/reference-register.md` for status, date and caveats.
