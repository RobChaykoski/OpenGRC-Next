# Open-source policy

This repository is the community edition of OpenGRC. This page states the rules
that keep it genuinely open source: what license applies, what dependencies are
acceptable, how contributions are licensed, and where the community edition ends.

## License

- **Code:** `AGPL-3.0-only`. The full text is in `LICENSE.txt`; the copyright and
  history notice is in `NOTICE`.
- **Documentation and skill files:** the same license, unless a file says
  otherwise.
- **Third-party content:** remains under its own license and is listed in
  `config/framework_catalog.php` (data) and in package metadata (code).

The AGPL's network clause means that if you modify OpenGRC and let other people
use it over a network, you must offer them the corresponding source. Deployers
should publish a source link for the version they run (a footer link is enough).
Running OpenGRC internally does not trigger this.

## Contribution licensing

Contributions are accepted under **inbound = outbound**: you license your
contribution under `AGPL-3.0-only`, and you certify that you have the right to do
so using the Developer Certificate of Origin (`git commit -s`). See
`CONTRIBUTING.md`.

We do not use a contributor license agreement. That means the project cannot be
relicensed to a proprietary license without the consent of every contributor —
which is the point.

## Dependency policy

New dependencies must be:

1. **OSI-approved** (or a permissive public-domain equivalent).
2. **Actively maintained** — a release or commit in the last 12 months.
3. **Compatible with the AGPL** — no proprietary, SSPL, BUSL, Commons Clause, or
   "source-available" terms.
4. **Stated in this file** if they carry obligations we have to meet.

Allowed license families:

| Family | Status | Notes |
|---|---|---|
| MIT, ISC, BSD-2/3, Apache-2.0, 0BSD, CC0, Unlicense | Allowed | |
| MPL-2.0 | Allowed | File-level copyleft; keep the file's notices |
| LGPL-2.1/3.0 | Allowed | We ship source, so the relinking obligation is satisfied |
| GPL-2.0/3.0 | Allowed for tools | Acceptable for separately invoked tools; do not link into the app without review |
| AGPL-3.0 | Allowed | Same license as the project |
| SSPL, BUSL, Elastic-2.0, Commons Clause, "source-available" | **Forbidden** | Not open source |
| Proprietary / no license | **Forbidden** | |

Current exceptions, and why:

- `dompdf` and its `php-font-lib` / `php-svg-lib` are LGPL. They are distributed
  as source through Composer, which satisfies the license.
- Frontend build dependencies include MPL-2.0 packages. Their file-level notices
  are preserved.
- ClamAV, if you enable malware scanning, is GPLv2 and is invoked as a separate
  process, not linked.

Separately deployed services are not dependencies. OpenDiscoveryEngine is
GPL-3.0-only and runs as its own deployment; OpenGRC calls its HTTP API and links
or copies no engine code, which is the same boundary as ClamAV. OpenMetadata is
Apache-2.0 and is also read over HTTP as a separate deployment. `nmap` and NAPALM
are likewise executed as processes. See ADRs 0018 and 0020. Vendoring or linking
code from either service into this repository would need an ADR and a license
review before it merged.

`composer licenses` and `license-checker` run in CI against an allowlist. A
dependency that is not on the list fails the build rather than being discovered
during an acquisition review.

## Content licensing

Framework control text is not code. Its license is tracked per framework in
`config/framework_catalog.php` and explained in `docs/grc/framework-catalog.md`.
The short rules:

- Public-domain US government works (NIST, HIPAA, DFARS) may be shipped.
- CIS controls may be shipped with attribution, subject to CIS terms.
- ISO/IEC 27001, PCI DSS and AICPA TSC text are **referenced, not bundled**.
  Users obtain their own licensed copies.
- Adding a framework requires a license review and a catalog entry.

## Community and enterprise boundary

The community edition in this repository is complete for self-hosted GRC work. It
is not a time-limited trial and does not require a license key.

Features that are planned as commercial (hosted service, managed multi-tenancy,
support contracts) live outside this repository so that the community edition can
remain AGPL without a CLA. If a feature is added here, it is AGPL, and it stays
here.

The MCP server was removed from this repository so that the boundary is
unambiguous: AI tooling that exposes the data model to external assistants is an
enterprise/hosted capability, not part of the community edition.

## No telemetry

OpenGRC does not phone home. There is no analytics, usage reporting, or license
check. AI features call the provider you configure, with your key; they do not
route through OpenGRC infrastructure.

Any future telemetry must be opt-in, documented, and disclosed in the release
notes before it ships.

## Trademarks

The license covers copyright, not the name or logo. See `TRADEMARKS.md`.

## Reporting a problem

If you believe a dependency, bundled asset, or contribution is not compliant with
this policy, open a private security report per `SECURITY.md` or email
[contact@opengrc.com](mailto:contact@opengrc.com).
