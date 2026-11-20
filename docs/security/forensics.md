# Forensic evidence handling

Forensic collection is not the same as attaching an audit artifact. Artifacts are
volatile, may belong to a person under investigation, may be privileged, and are
often acquired from a system an adversary controls. The technical controls here
exist to make a collection defensible: authorized before it starts, scoped,
hashed at acquisition, sealed, and traceable through every hand.

**These controls do not make a collection legal.** Requirements differ by
jurisdiction and by matter, and they change. Counsel must approve the legal basis,
the scope, the notices, cross-border transfers and the retention schedule before
collection. This page describes the technical implementation of an approved
process. It is not legal advice.

Status: accepted design (ADR 0019). OpenGRC owns the case record, custody ledger
and retention state. The collector and the WORM case store are separate
deployables; raw content does not enter OpenGRC. The evidence ledger (ADR 0008)
supplies the append-only hash chain this design extends.

## Roles

Case roles are assignments on a case record, not new application roles. The
`admin` / `editor` / `viewer` model (ADR 0007) still gates the screens; a case
role grants nothing outside its case.

| Role | Acts | Constraint |
|---|---|---|
| Case owner | Opens the case, agrees scope with counsel, owns the schedule and holds | Cannot collect, approve destruction of their own case, or approve an export alone |
| Approver | Approves authorization, privacy review, export and destruction | Independent of the case owner where counsel requires it; cannot collect |
| Collector | Acquires, hashes, seals and transfers artifacts | Least privilege, read-only on targets; may append custody events but cannot edit them or the authorization |
| Custodian | Runs the case store, Object Lock, replicas and keys | Cannot read artifact content as part of the role; cannot delete under hold alone |
| Reviewer / examiner | Analyzes working copies, produces findings | No write access to the sealed original; access is time-bound |
| Auditor | Reads the custody log, manifests and destruction certificates | Read-only; no artifact content by default |

One person may hold two roles only where counsel accepts the conflict and the
second pair of eyes is still independent for access, export and destruction.

## Lifecycle

Every step is an appended event with an actor, a UTC timestamp, the case id and
the hash of the previous event. A missing step is a defect, not a shortcut.

| Step | Who acts | Recorded | Integrity control |
|---|---|---|---|
| Authorize | Approver with case owner | Legal basis, jurisdiction, scope, custodian, DPIA and privacy review, special-category and cross-border decisions, start and expiry | Signed authorization event; immutable; scope hash; no collection without it |
| Scope | Case owner and collector | Targets, identifiers, time window, artifact types, exclusions, rationale, collection plan | Plan hash approved before acquisition; any change is a new authorization event |
| Collect | Collector | Host or account, method, tool name, version and binary hash, operator, start and end, commands run | Least privilege, read-only access, native APIs, targeted collection, no content in logs |
| Hash | Collector | SHA-256 and size per artifact, source path or identifier, acquisition time | Hash computed before transfer and stored in the encrypted spool manifest |
| Seal | Collector | Manifest, manifest hash, seal time, sealing identity | Manifest signed or timestamped; sealed set becomes immutable |
| Transfer | Collector to case store | Channel, source and destination, artifact count and bytes, retry state | TLS, encrypted spool, resumable upload, hash re-checked at the destination |
| Store | Custodian | Bucket and object prefix, Object Lock mode and expiry, replica locations, key ids | WORM/Object Lock, versioning, encryption at rest, keys held separately |
| Analyze | Reviewer | Working-copy id and hash, tools and versions, findings references | Original never mounted writable; working copy hash recorded; analysis is read-only on the sealed set |
| Retain / hold | Case owner | Schedule, hold id, authority, scope, review date | Active hold overrides expiry and blocks deletion on every copy |
| Export | Case owner and second approver | Package id, manifest hash, recipient, purpose, redaction applied | Dual control; hashes re-verified; recipient register; every access logged |
| Destroy | Two approvers | Approval, artifact list and hashes, method, certificate id | Dual control; certificate of destruction; backups, archives and keys included |

## Authorization

An authorization is required per matter and per scope. It records:

- case id and matter reference, with a short non-sensitive description;
- legal basis: warrant or court order, consent, corporate policy, or incident
  response, with the instrument or policy reference;
- jurisdiction and governing law, including where the data and the subject are
  located;
- scope: targets, identifiers, time window, artifact types and explicit
  exclusions;
- custodian (who owns the data) and approver, with signatures and timestamps;
- data protection impact assessment reference and privacy review outcome;
- special-category or sensitive data expected, and the minimization or redaction
  plan;
- cross-border transfer mechanism, destination and any transfer safeguard;
- expected privilege (legal advice, third-party confidentiality) and handling;
- start date, expiry and review date.

Rules:

- No authorization, no collection. An expired authorization stops collection; it
  does not delete or release what was already collected.
- An amendment is a new event that supersedes the previous authorization. The old
  one stays readable.
- Evidence found outside the approved scope is not examined. Stop, record the
  fact, quarantine it, and ask counsel whether to seek an amended authorization.
- Special-category, privileged or cross-border content needs the review completed
  before acquisition, not after.

## Chain of custody

The custody log is append-only. Corrections are new events that name the event
they correct; the original remains. Updates and deletes are refused by the model
and blocked by database trigger, matching the evidence ledger.

Each event carries:

| Field | Meaning |
|---|---|
| `event_id` | Stable identifier, never reused |
| `case_id` | The authorization this event belongs to |
| `sequence` | Monotonic per case; gaps are detectable |
| `occurred_at` | UTC, from a synchronized clock |
| `actor` | User or service id, role on the case, and authentication method |
| `action` | One lifecycle verb, plus `access`, `verify`, `correct`, `hold`, `release` |
| `artifact_id` | Artifact or package reference, where applicable |
| `sha256` | Hash of the artifact, manifest or package at that moment |
| `location` | Spool, case store, replica or recipient, never raw content |
| `previous_event_hash` | Hash of the previous event for this case |
| `event_hash` | SHA-256 over the canonical event body, including `previous_event_hash` |
| `reason` | Required for access, correction, export and destruction |
| `approval_ref` | Second actor and approval for dual-control actions |

Dual control applies to retrieval of a sealed artifact, export, and destruction.
Both actors are recorded, both authenticate separately, and neither may be the
collector for the same action.

Because an append-only table can still be truncated by a database administrator,
the case periodically anchors its current event hash in WORM storage or a
timestamping service. Verification checks the chain and the anchor; a valid chain
that does not reach the last anchor is an incident.

## Integrity

- **Per artifact:** SHA-256 at acquisition, recorded before transfer, recomputed
  after transfer and before every export.
- **Manifest:** lists every artifact with hash, size, source, acquisition time and
  tool metadata; the manifest itself is hashed.
- **Seal:** the manifest hash is signed or timestamped (minisign, GPG, cosign, or
  an RFC 3161 authority). The sealing identity and public key are recorded.
- **Storage:** WORM or S3 Object Lock in compliance mode, versioning on, delete
  permission removed from the collector after seal. A local filesystem is not
  WORM; use an immutable store or accept that the control is absent.
- **Verification:** on every access and export, and on a schedule. A mismatch
  quarantines the set and opens an incident. Never "repair" a hash; a changed
  artifact is a new artifact with a new event.
- **Time:** authenticated NTP on collector, case store and OpenGRC. Timestamps
  are UTC. Observed skew is recorded; it is not silently corrected in old events.
- **Tools:** name, version, vendor or source, and binary SHA-256 are recorded in
  the tool inventory. A tool update during a case is a new inventory entry.

## Collection and extraction

- The collector runs with a dedicated least-privilege account, read-only on
  targets, and credentials that expire with the authorization. No domain-wide
  standing admin.
- Native APIs first: EDR telemetry, cloud audit logs, identity provider logs, SaaS
  export. Use OS-native tools only where the API does not expose the artifact, and
  record why.
- Collect to the approved scope. No full-disk image by default; a full image needs
  a written justification and an amended scope.
- For locked or in-use files, use a volume snapshot (VSS on Windows, APFS or LVM
  snapshots elsewhere) and record the snapshot id. Copying a live file without a
  snapshot produces a hash that may not reproduce.
- Memory acquisition only with explicit scope and a stated reason, such as
  volatile credentials or malware. Record the acquisition tool, its hash, the
  target, and the expected volatility.
- Live response changes the host. Record every command and hash its output, and
  note the state change in the case.
- No content in logs. Log identifiers, counts, hashes, durations and errors. Never
  log file bodies, memory strings, message text or credentials.
- The spool is encrypted at rest, with a key held outside the collector host.
  Upload is resumable and each resumed segment is hash-checked.
- Cloud or third-party sources: preserve provider-side logs with a legal hold or
  export request, and record the provider case id, request time and retention
  window. Do not rely on a dashboard screenshot as the artifact.

## Retention and legal hold

Retention is set per case at authorization and reviewed at closure. The longer of
the schedule and an active hold applies.

| Matter | Example default | Note |
|---|---|---|
| Incident response | 1 year after closure | Enough for review, insurer and a late claim; volatile data may be shorter |
| HR or employment | 3 years after the matter closes | Employment law and limitation periods vary |
| Litigation or regulatory | Indefinite while held | Release only on counsel's written instruction |
| Regulated records | Per the applicable statute | Often 6 to 7 years for financial records; confirm locally |

These are examples, not legal advice. Counsel approves the schedule and the event
that starts the clock (closure, termination, final judgment).

- A hold overrides expiry and blocks deletion on the original, replicas, exports
  under control and backups within the retention window.
- At expiry the case owner reviews, then requests deletion. Two approvers are
  required; the second is independent of the case owner.
- Destruction produces a certificate of destruction: case id, artifact list and
  hashes, method, media or key destroyed, operators, approvers, date, and
  verification result.
- Deleting an object without its key is not destruction if a copy of the key
  exists. Key destruction is recorded as its own event.
- Backup and archive copies are included in the approval and the certificate.
  "It aged out of the backup rotation" is not a certificate.
- The custody log itself is retained; destruction appends a tombstone event
  rather than deleting history.

## Access

- Role separation as above: collector, reviewer, auditor and approver are
  different people where the case is significant.
- No standing access. Access is granted per case, for a stated purpose, with an
  expiry, and is revoked at closure or on role change.
- MFA is required for every case role. The admin panel's privileged-account rule
  applies to the accounts that administer cases.
- Every read, download and export is logged with actor, artifact, time, purpose
  and approval reference. Reading a sealed artifact is a custody event.
- Reviewers receive redacted working copies where the question does not require
  the original. The redaction record travels with the copy.
- The auditor sees manifests, custody events and certificates, not content by
  default. Content access is a separate, approved grant.
- Break-glass access exists, is dual-controlled, time-boxed, and reviewed after
  use. It is not a standing administrator bypass.

## Admissibility support

These controls support admissibility; a court, regulator or opposing party
decides. What helps:

- a documented method that matches what was actually done, including deviations;
- reproducible commands and tool versions, so a second examiner can repeat the
  acquisition or verify the output;
- tool validation records and known limitations, not just a vendor claim;
- a sealed original and separate working copies, with hashes for both;
- hash verification at acquisition, transfer, access and export;
- an unbroken custody log with named actors and dual control;
- contemporaneous notes, including clock skew and any change to the source;
- expert review of method and findings before the package is relied on;
- an export package that carries its own verification instructions.

What undermines it: a gap in the log, an unrecorded tool or command, a modified
"original", an unexplained hash mismatch, a screenshot instead of an artifact, or
collection that started before authorization.

## Threat model

| Threat | Realistic path | Control |
|---|---|---|
| Tampering | Collector host or storage admin alters an artifact | WORM/Object Lock, per-artifact hash, sealed manifest, verification on access |
| Repudiation | An actor denies collecting, reading or exporting | Append-only actor-attributed events, MFA, dual control, anchored root hash |
| Credential theft | Stolen collector token or admin session | Per-case short-lived credentials, scoped tokens, MFA, rotation, no standing access |
| Scope creep | Analyst collects a whole mailbox "to be safe" | Approved scope hash, target allowlist, plan change requires new authorization |
| Clock skew | Timestamps out of order or shifted by a host | Authenticated NTP, UTC, monotonic sequence, recorded skew |
| Tool compromise | Malicious or unreviewed acquisition binary | Pinned versions, binary hashes, allowlisted sources, tool inventory, validation |
| Storage deletion | Insider removes objects or a lifecycle rule expires them | Object Lock, versioning, replicas, delete denied, hold blocks expiry |
| Cross-tenant leakage | Case store or index keyed to the wrong tenant | Tenant-scoped paths and keys, isolation tests, no shared bucket across tenants |
| Insider access | Reviewer browses an unrelated or sensitive case | Least privilege, purpose-bound grants, dual control, read audit, access review |
| Adversary-controlled host | Live response is observed or altered | Read-only collection, volatile-order capture, command logging, output hashing |
| Export leakage | Recipient forwards a package | Dual control, recipient register, watermark, redaction, expiring transfer link |

## Evidence package

An export is a directory or archive with a fixed layout. The manifest is the
index; the artifacts are the content; the rest is what makes the package usable
and checkable.

```
case-2026-0007/
  manifest.json            case, authorization, scope, artifact list, hashes, tools
  manifest.json.sha256     detached hash of the manifest
  artifacts/
    0001-host01-security.evtx
    0002-host01-memory.raw
  chain-of-custody.jsonl   one event per line, hash-linked
  tool-inventory.json      tool, version, source, binary SHA-256, validation note
  README.md                what this is, how to verify, what was excluded
  verify_manifest.py       offline verifier, no network, read-only
  redaction-notes.md       what was removed, why, and by whom
```

`manifest.json` records the case id, authorization reference, scope, creation
time, creator, previous package id (if any), and for each artifact its path,
SHA-256, size, source, acquisition time and tool. The `README.md` states the
package is not a compliance certificate and names the contact for questions.

Verification, on a clean machine:

```bash
sha256sum -c manifest.json.sha256
python3 verify_manifest.py . --expect-root <sealed-root-hash>
```

`verify_manifest.py` checks the detached manifest hash, every artifact against the
manifest, the custody chain, and the expected root. It prints one line per check
and exits non-zero on any mismatch. A package that cannot be verified is not
evidence; quarantine it and open an incident.

## Operating checklist

Before collection:

- [ ] Authorization signed, scope hashed, expiry set.
- [ ] DPIA and privacy review complete for personal or special-category data.
- [ ] Cross-border transfer approved, or data kept in jurisdiction.
- [ ] Collector credentials issued for this case only.
- [ ] Collection plan and tool inventory reviewed.

At collection and transfer:

- [ ] Hash every artifact at acquisition; never collect without hashing.
- [ ] Record commands, tool versions and binary hashes.
- [ ] Seal the manifest before transfer.
- [ ] Verify hashes at the destination; treat a mismatch as an incident.

At closure:

- [ ] Working copies and derived data accounted for or destroyed.
- [ ] Retention date and any hold recorded.
- [ ] Access grants revoked.
- [ ] Package regenerated if the export is the record of record.

## Known gaps

- No forensic case resource ships in OpenGRC yet. This page is the contract for
  it; case records are not a finished screen.
- Manifest signing is external today. Use minisign, GPG, cosign or an RFC 3161
  timestamp; do not call an unsigned manifest sealed.
- Object Lock depends on an S3-compatible backend. A local disk deployment does
  not provide WORM and must use an external immutable store.
- The collector is outside this repository and needs its own hardening baseline,
  update process and monitoring.
- `verify_manifest.py` travels with the package; it is not yet a maintained
  OpenGRC command.
- Privilege handling and cross-border templates still need counsel-approved
  wording per jurisdiction.

## Related

- `docs/decisions/0008-evidence-ledger-and-migration-integrity.md` — append-only
  evidence chain and verification.
- `docs/decisions/0016-evidence-pack-boundary.md` — packs index evidence; they do
  not export payloads.
- `docs/decisions/0019-forensic-evidence-handling.md` — the decision and its
  alternatives.
- `docs/integrations/forensic-collectors.md` — collector design, native engines
  and tool licensing.
- `docs/security/forensic-compliance-mapping.md` — control families, evidence
  types and framework mapping.
- `docs/security/data-loss-prevention.md` — no content in findings or logs.
- `docs/security/tenant-and-access-roadmap.md` — tenant isolation and access
  rules the case store must follow.
- `docs/decisions/0005-security-defaults.md` — MFA, private files and deployment
  defaults.
