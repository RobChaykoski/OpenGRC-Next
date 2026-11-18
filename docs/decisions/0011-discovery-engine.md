# 0011 — Discovery engine as a separate service, not an OpenDLP port

Status: accepted
Date: 2026-11-18

## Context

OpenGRC records controls, risks, policies, and evidence, but it does not know
where regulated data actually lives. Discovery is the missing half of
evidence-driven compliance: a control can be described and tested, but without a
data inventory the organisation cannot show that it knows where PHI, PII, card
data, or secrets are stored.

OpenDLP is the closest open-source precedent. The available snapshot
(`ezarko/opendlp`, commit `b02568f`, OpenDLP 0.5.1) is a single 2014 import:
Perl CGI web tier, MySQL schema defined only in the README, a Windows C agent, and
several missing pieces including the agent upload handler and compiled binaries.
It has no application authentication, stores target credentials in clear text,
passes passwords on the `winexe` command line, uses MD5, and disables agent
hostname verification. The licence is GPL-3.0.

Porting that code would import twelve years of security and maintenance debt into
a platform whose value depends on trustworthy evidence.

## Decision

1. Build a **separate service**, working name OpenGRC Discovery Engine
   (OpenDLP-NG), in the `opendiscoveryengine` repository.
2. **Clean-room implementation.** Do not copy the Perl CGI, the C agent, or the
   Metasploit bridge. Reuse the domain model and pattern data with attribution.
3. Licence the service **GPL-3.0**. Integrate with OpenGRC only over an HTTP/JSON
   API, so OpenGRC remains AGPL-3.0 and the licence boundary is explicit.
4. Scope Version 1.0 to the MVP in
   `opendiscoveryengine/docs/phase-0/mvp-scope.md`: local filesystem, SMB, and
   PostgreSQL discovery; regex detectors with validators; four classifications;
   hashed evidence export; OpenGRC import; four roles.
5. Exclude agents, cloud connectors, AI classification, OpenSearch, Kubernetes,
   and workflow automation from 1.0.
6. Never store raw matched values. Findings carry a bounded redacted preview and
   a SHA-256 of the match.
7. Record provenance and the GPL obligations in the repository `NOTICE`, and
   attempt maintainer contact before publishing a fork or using the OpenDLP name.

## Consequences

- OpenGRC gains a discovery capability without inheriting the legacy stack.
- Two deployables and a versioned API contract must be maintained; the OpenGRC
  import endpoint is a dependency for “done”.
- Pattern data and the domain model still derive from GPL-3.0 material; the
  notices and source obligations remain.
- The MVP can ship without agents, which removes the highest-privilege and
  hardest-to-test component.
- Accepted debt: local authentication before OIDC, regex-only detection before
  Presidio, and no tenant UI before hosting.
- If the original maintainers want a continuation of OpenDLP itself, that is a
  separate decision and must not be conflated with this service.
