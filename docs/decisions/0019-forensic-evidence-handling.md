# 0019 — Forensic evidence handling is an authorized, append-only process

Status: accepted
Date: 2026-11-20

## Context

The evidence ledger (ADR 0008) protects audit artifacts: a file is hashed on
upload, changes are append-only, and withdrawal needs a reason. Forensic
collection is a harder problem. Artifacts are volatile, may belong to a person
under investigation, may be the subject of litigation, and are acquired from
systems an adversary may control. A mistake does not just weaken an audit
finding; it can destroy evidence, breach privacy law, or make the organization
look as though it tampered with the record.

Collection today is ad hoc. An analyst copies files to a workstation, zips them,
and attaches the archive to a ticket or a chat thread. There is no authorization
record, no hash taken at acquisition, no custody log, and no retention or legal
hold that survives the ticket. Raw content then spreads into places where no
access rule, no hash and no deletion control applies.

Software cannot guarantee legal compliance. Requirements vary by jurisdiction,
by matter and by the role of the organization in the matter, and they change.
Counsel must approve the process, the notices, the scope of any search, and the
retention schedule. This decision defines technical controls that support legal
defensibility. It is not legal advice, and passing every check here does not make
a collection lawful.

## Decision

1. **Collection is an authorized, scoped, append-only process.** No artifact is
   acquired before a case authorization exists. The authorization is the root
   record; every scope decision, artifact, access and export references it.
2. **A separate collector service acquires artifacts.** OpenGRC does not read
   endpoints, mount disks or run acquisition tools. The collector is a separately
   deployed, least-privilege service, on the same boundary as ADRs 0002 and 0018.
3. **OpenGRC owns the legal and compliance record, not the data.** It holds the
   case authorization, the chain-of-custody ledger, evidence references (hash,
   size, source, custodian, storage location), the retention schedule and legal
   holds. Raw content stays in the collector's WORM case store.
4. **Raw content never enters OpenGRC and never enters logs.** No upload path,
   preview, search index, AI prompt, export or log line carries artifact content.
   Only references, hashes and metadata cross the boundary.
5. **The lifecycle is fixed:** authorize, scope, collect, hash, seal, transfer,
   store, analyze, retain/hold, export, destroy. Every transition is an event with
   an actor, a UTC time and a hash link to the previous event.
6. **Chain of custody is append-only.** Corrections are new events that reference
   the event they correct. Updates and deletes are refused by the model and
   blocked by database trigger, as with the evidence ledger in ADR 0008.
7. **Dual control covers access, export and destruction.** One person cannot
   retrieve a sealed artifact for analysis, release it to a third party, or
   destroy it alone.
8. **Integrity is verified, not asserted.** SHA-256 per artifact, a manifest hash,
   a sealed manifest, WORM/Object Lock storage, hash verification on every access
   and export, synchronized time, and recorded tool versions and binary hashes.
9. **Retention is per case and a legal hold overrides it.** Deletion needs
   approval and produces a certificate of destruction. Backups, archives and
   encryption keys are in scope.
10. **The process is documented, reproducible and reviewed.** Commands, tool
    versions and collection methods are recorded so a second examiner can repeat
    them. Counsel and, where required, an expert reviewer approve the method
    before the package is relied on.

The step-by-step procedure, threat model and package layout are in
`docs/security/forensics.md`.

## Consequences

- Legal and privacy work comes before technical work. A case without an
  authorization cannot collect, which is slower on day one and defensible later.
- There are two deployables again: collector plus OpenGRC, with separate upgrade,
  health, backup and monitoring duties. `opengrc:doctor` cannot see the
  collector, so the case view must show last contact and spool state; a silent
  collector must read as stale, never as "no evidence".
- OpenGRC is not a forensic browser. Review happens in the collector's case store
  or in an export package under dual control, so the convenient "click the
  attachment" workflow is deliberately absent.
- The export package is the sharing unit. Manifest, artifacts, custody log, tool
  inventory and verification instructions travel together; a ZIP without them is
  not evidence.
- A legal hold blocks deletion across every copy. Storage cost is the price of
  not destroying held material, so every hold names an owner and a review date
  and does not become permanent by neglect.
- Cross-border transfer and special-category data need a data protection impact
  assessment and privacy review before collection, not after. Some collections
  will be refused or narrowed. That is a correct outcome, not a failure of the
  tooling.
- Hash verification adds CPU and latency to every read and export, proportional
  to artifact size. It is a deliberate cost.
- Retention examples in the security doc are defaults. Jurisdiction, statute and
  counsel override them.
- The collector becomes a high-value target: it holds the only raw copy and runs
  with read access to endpoints. Its credentials, host and updates need the same
  scrutiny as the OpenGRC admin panel.

## Alternatives considered

- **Tool-only approach: use an acquisition tool's case database as the record.**
  Rejected as the system of record. The authorization, retention and holds would
  live outside the compliance platform, so the organization still could not
  answer "what do we hold, for which matter, and may we still hold it?" across
  tools. The tool remains a collector, not the register.
- **Full-disk imaging by default.** Rejected: disproportionate collection, large
  privacy exposure, slow transfer, and most matters need targeted files, logs and
  volatile data from specific hosts. Full-disk imaging stays an explicit,
  justified scope decision.
- **Store raw content in OpenGRC.** Rejected: it puts hostile, personal and
  possibly privileged content inside the web application, its backups and its
  search index, and defeats the evidence-pack boundary in ADR 0016. The ledger
  holds hashes and references; the case store holds content.
- **Use a proprietary EDR as the only source.** Rejected: one vendor decides what
  evidence exists, retention follows the subscription, exports are not verifiable
  by the customer, and the process fails where the agent is absent or the
  adversary disabled it. EDR is one source among several.
- **Let the collector write its own case record and skip OpenGRC.** Rejected:
  the legal basis, privacy review, retention and holds are GRC records, and
  keeping them next to controls, risks and findings is what makes the case
  reviewable. The collector would grow a second, weaker compliance system.
