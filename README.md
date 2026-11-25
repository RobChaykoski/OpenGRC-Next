<p align="center">
  <img src="https://og-website.nyc3.digitaloceanspaces.com/image/logos/logo-256.png" alt="OpenGRC" width="160" />
</p>

<h1 align="center">OpenGRC</h1>

<p align="center">
  <strong>GRC anyone can do.</strong><br>
  Self-hosted governance, risk and compliance for small and mid-sized security teams.
</p>

<p align="center">
  <a href="https://opengrc.com">Website</a> ·
  <a href="https://docs.opengrc.com">Documentation</a> ·
  <a href="https://opengrc.com/community">Community Edition</a> ·
  <a href="https://opengrc.com/enterprise">Enterprise</a> ·
  <a href="https://trust.opengrc.com">Trust Center</a>
</p>

---

OpenGRC is a governance, risk and compliance platform built for teams who need to run a
real security program without a six-figure budget or a three-week onboarding. It is not
trying to replace a large enterprise GRC suite — but for a lot of organisations, it does.

We built it because we were tired of platforms that cost $30k a year, required a
consultant to operate, and still nickel-and-dimed on every module.

**Self-host it for free. Run your whole program. Own your data.**

## What it does

- **Frameworks and controls** — import common security frameworks, then connect standards,
  controls and your actual implementations rather than maintaining three spreadsheets.
- **Risk management** — a risk register that links to the controls and assets it actually
  concerns, with scoring and treatment tracking.
- **Audits and evidence** — run internal and external audits end to end: requests, evidence,
  findings and remediation, with reports you can hand to an auditor. Evidence is private,
  hashed and hash-chained, and a daily check verifies the chain.
- **Continuous control monitoring** — connectors turn system measurements into validation
  results and findings instead of screenshots and spreadsheets.
- **Vendor risk (TPRM)** — vendor inventory, security questionnaires and document tracking.
- **Access governance and privacy requests** — access reviews, just-in-time grants,
  break-glass records, and regime-aware privacy request clocks.
- **Data protection and supply chain** — DLP and DSPM governance, SBOM and vulnerability
  evidence, and an AI model artifact register.
- **Policy management** — author, review and publish policies with approval workflows and
  versioned acknowledgements.
- **Asset and application inventory** — know what you have before you try to secure it.
- **Dashboards and reporting** — progress you can show a board without building a deck.

Record integrity is part of the design, not an add-on: the evidence ledger and the audit
ledger are append-only hash chains, and `php artisan opengrc:integrity:verify` records a
dated verification run so a broken chain or a stopped schedule is visible.

## Requirements

- PHP 8.4+
- SQLite 3.35+ for a small install, or MySQL 8+ / MariaDB 10.6+ / PostgreSQL 14+
- Composer and Node.js 20.19+ (20.x) or 22.12+

Built on [Laravel 13](https://laravel.com) and [Filament](https://filamentphp.com).

## Quick start

The fastest path is Docker:

```bash
git clone https://github.com/LeeMangold/OpenGRC.git
cd OpenGRC
cp .env.docker.example .env
docker compose up -d
```

To install directly onto a server instead:

```bash
git clone https://github.com/LeeMangold/OpenGRC.git
cd OpenGRC
./install.sh
```

Full installation, upgrade and configuration guides live at
**[docs.opengrc.com](https://docs.opengrc.com)**.

For contributors, the repository also includes:

- `AGENTS.md` — conventions, commands, and the definition of done
- `docs/SUPPORTED-VERSIONS.md` — supported baseline and upgrade tiers
- `docs/UPGRADING.md` — upgrade and rollback runbook
- `docs/security/` — container hardening, audit ledger, DLP, forensics, and the
  boundaries that are not hardened yet
- `docs/grc/` — how the product is meant to be operated as a GRC programme, with
  the capability gaps stated plainly
- `docs/decisions/` — accepted architecture decisions
- `.opencode/skills/` — reusable workflows for GRC, framework mapping, training
  scenario authoring, OpenCyberRange integration, upgrades, security, privacy,
  design, and user interface

## Community and Enterprise

The **Community Edition** in this repository is the complete self-hosted platform. No seat
limits, no data caps, no crippled trial. It covers the core program: risk, controls and
implementations, vendor management, audits and evidence, continuous monitoring, access
governance, privacy requests, and reporting.

**[Enterprise](https://opengrc.com/enterprise)** is the hosted, supported edition. It adds
the AI and premium modules - AI risk assessments, AI gap assessments, automated vendor
survey responses - along with the hosted integrations. It comes with an uptime
commitment, SOC 2 Type II attestation
and support. See [pricing](https://opengrc.com/pricing) and
[security](https://opengrc.com/security).

Running GRC for a book of clients? There is an [MSSP edition](https://opengrc.com/mssp)
with isolated instances per client.

## Contributing

Contributions are welcome — issues, pull requests and ideas alike. Start with
[CONTRIBUTING.md](CONTRIBUTING.md), and please open an issue before starting anything
large so we can agree on the approach first.

Questions and ideas are best raised in
[Discussions](https://github.com/LeeMangold/OpenGRC/discussions).

## Security

Please do not report security issues in public issues. See [SECURITY.md](SECURITY.md) for
how to report a vulnerability, or use the contact details published at
[opengrc.com/.well-known/security.txt](https://opengrc.com/.well-known/security.txt).

## License

OpenGRC is **free software** under the [GNU Affero General Public License v3.0](LICENSE.txt)
(`AGPL-3.0-only`).

In plain terms, you may:

- ✅ Use OpenGRC for free, including inside a commercial business
- ✅ Self-host it on your own infrastructure, for your own organisation
- ✅ Read, modify and redistribute the source under the same license

What the license requires:

- 📄 If you modify OpenGRC and let other people use it over a network, you must
  offer them the corresponding source of your modified version
- 📄 Redistributions must keep the license and copyright notices

The AGPL is what keeps this project open: it prevents a competitor from taking the
community edition, hosting it as a closed service, and contributing nothing back.
Running OpenGRC internally, or for your own organisation's clients, does not
require you to publish anything.

If you need a commercial license, the hosted/managed service, or terms that are
not compatible with the AGPL, that is what the [MSSP edition](https://opengrc.com/mssp)
and [Enterprise](https://opengrc.com/enterprise) are for —
[talk to us](https://opengrc.com/contact).

> **License history.** Releases before 14 April 2025 were published under the MIT
> License. Releases from 14 April 2025 were published under CC BY-NC-SA 4.0 and
> later the Elastic License 2.0. Those releases remain available under the terms
> they were published with. The AGPL applies from the adoption commit forward.
> Details are in [NOTICE](NOTICE) and [docs/OSS-POLICY.md](docs/OSS-POLICY.md).

OpenGRC™ and the OpenGRC logo are trademarks of OpenGRC, LLC. See
[TRADEMARKS.md](TRADEMARKS.md).
