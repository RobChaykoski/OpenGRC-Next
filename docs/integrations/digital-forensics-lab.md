# Digital forensics lab (external curriculum)

Date checked: 2026-11-21. Status: **reference only** — not an integration, not a
vendored dependency.

[frankwxu/digital-forensics-lab](https://github.com/frankwxu/digital-forensics-lab)
is a free digital-forensics curriculum by Weifeng Xu (University of Baltimore),
supported by NSF award 2333949, with stated DOJ, DHS and NSF support. It contains
Kali-centric slide decks and case files for disk, memory, network, mobile and IoT
forensics, two STIX forensic-intelligence datasets, and AI4Forensics notebooks.

It is curriculum, not a service: there is no API to connect. This page records
what OpenGRC may reference, what it must not copy, and how the case studies map
to governance work. It exists so that a later reader does not vendor a deck or an
evidence image because the repository says Apache-2.0.

## Boundary

- Lab runtime, course content and student progress belong to OpenCyberRange
  (ADR 0002). OpenGRC is the system of record for the *governance* around an
  investigation, not for the investigation itself.
- Raw forensic artifacts stay in the collector's case store, never in OpenGRC
  (ADR 0019).
- OpenGRC has no forensic-case or chain-of-custody object today. ADR 0019 is
  accepted design, not shipped schema. A scenario must not assume one exists.

## Licence and provenance

The repository declares Apache-2.0 (© 2024 Weifeng Xu). That covers the project's
own slides, scripts and notebooks. It does **not** cover the evidence images,
which are contributed by third parties under their own terms.

| Material | Owner | Terms | What OpenGRC may do |
|---|---|---|---|
| Decks, lab instructions, scripts | Weifeng Xu / project | Apache-2.0 | Link, cite the deck path and commit; do not copy decks into OpenGRC |
| NIST CFReDS images (data leakage, hacking case) | NIST | NIST CFReDS usage terms | Link only; never store or redistribute the image |
| Digital Corpora scenarios (Nitroba, Android 10, iOS 13) | Digital Corpora and contributors | Per-scenario terms on digitalcorpora.org | Link only |
| SEED Labs Morris-worm VM | SEED Labs, Syracuse University | SEED course-material terms | Link only |
| DJI drone dataset | VTO Labs | Vendor terms | Link only |
| Cellebrite 2022 CTF dataset | Cellebrite | Competition terms; faculty request | Link only; access is requested from the owner |
| STIX forensic-intelligence datasets | Project | Repository licence; content derived from real cases | Reference the schema; do not ingest case narratives |
| AI4Forensics LLM notebooks, including the Clinton email summaries | Project; upstream email dataset from a separate repository | Repository licence plus upstream terms; real political correspondence | Do not use. Real personal and political data is not training data for this platform |

Two rules follow:

1. An Apache-2.0 badge on the repository is not a licence for the images.
   Attribution is not a substitute for permission to redistribute.
2. Free to download is not free to include in a distribution. Any proposal to
   vendor the repository into OpenGRC or OpenCyberRange needs a licence review of
   every third-party dataset first.

## Case studies and the governance they can exercise

The value is the scenario, not the file. Each row names the governance records an
OpenGRC scenario would require; the forensic runtime stays in the range.

| Case study | What it exercises | OpenGRC records that grade it |
|---|---|---|
| NIST data leakage (IP theft via USB, email, cloud) | Data-flow control, egress, DLP response, insider investigation | Finding, risk, remediation task, implementation test result, evidence record |
| P2P data leakage (uTorrent) | Acceptable-use breach, egress detection, timeline reconstruction | Policy acknowledgement, policy exception, finding, risk |
| Illegal possession of images (Rhino Hunt) | Lawful scope, privacy, handling of distressing material | Privacy request, finding, risk |
| Email harassment (Nitroba) | Insider misuse, HR/legal handling, privacy | Finding, privacy request, evidence record, access review |
| Illegal file transfer (memory forensics) | Volatile acquisition, custodial integrity, timeline | Evidence record with hash and provenance, finding, remediation task |
| NIST hacking case | Unauthorised access investigation, wireless exposure | Finding, implementation test result, risk |
| Morris worm | Historical worm propagation, patching, response | Implementation test result (patch management), exercise record, remediation task |
| Eufy, Echo Show, Android, iOS, DJI drone | Mobile and IoT acquisition, BYOD and personal data | Asset record, privacy request, finding |
| STIX forensic-intelligence datasets | Intelligence as structured, attributable data | Evidence record with provenance; **not** the narrative content |

A scenario's acceptance criteria must check OpenGRC records — a finding with an
owner, a remediation task with a date, an evidence record with a hash — not "the
student finished Lab 4". That is the scenario contract in
`.opencode/skills/opengrc-training-scenario-authoring/SKILL.md`.

## Tooling cross-check

The repository's tool list overlaps `docs/integrations/forensic-collectors.md`
(Sleuth Kit, Volatility 2, RegRipper, libyal's `libesedb`/`libpff`/`libvshadow`,
`analyzeMFT`, `undark`, `stego-toolkit`). It is a useful sanity check on the
collector design, with three gaps worth remembering:

- it is anchored on Kali 2021.4 and some scripts are Python-2 era; Volatility 2
  is superseded by Volatility 3;
- there is no timeline layer (Plaso/Timesketch) and no remote acquisition agent
  (Velociraptor, GRR), which the collector design needs;
- there is no chain-of-custody tooling at all — because the lab owns the disc,
  not a case. That is precisely the part OpenGRC's design supplies.

## Staleness

The material ages: Kali 2021.4, Android 10, iOS 13.4.1, Windows XP-era NIST cases,
a 2022 CTF dataset. Before using a case in a cohort, check the image still runs
and that the tools have not changed behaviour. Record the commit SHA and the tool
versions in the scenario, so a grade can be reproduced.

## How to reference it

- In a scenario contract: `source: frankwxu/digital-forensics-lab`, the deck path,
  and the commit SHA. The runtime reference is the range's lab slug.
- In the exercise register (ADR 0034): source `Other`, with the repository name
  and case as the reference. The register records a package name, not the package.
- Never copy a deck, an evidence image or a case narrative into OpenGRC storage,
  an export, a prompt or a log.

## What not to do

- Do not vendor or fork the repository into OpenGRC or OpenCyberRange without the
  licence review above.
- Do not ingest the STIX case narratives or the LLM email summaries: they contain
  real personal data.
- Do not present a completed lab as a completed control test, a training
  completion or an audit finding. Labs teach; they do not attest.
