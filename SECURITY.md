# OpenGRC Security Policy

OpenGRC is self-hosted software. The security of a deployment depends on the
operator's host, TLS, database, backups and access choices as well as on this
codebase. This page covers the codebase: which versions get fixes, how to report
a problem, and what is in scope.

## Supported versions

OpenGRC is pre-1.0. Security fixes are made on `main` and in the latest tagged
release; older tags, forks and modified deployments are not supported.

| Version | Supported |
|---|---|
| Latest tagged release | Yes |
| `main` (development) | Fixes land here first |
| Earlier tags, forks, modified deployments | No |

The supported runtime, database and framework baseline is in
[`docs/SUPPORTED-VERSIONS.md`](docs/SUPPORTED-VERSIONS.md) and the machine-readable
[`versions.yaml`](versions.yaml). Running outside that baseline is a configuration
question, not automatically a vulnerability, unless it produces a security failure
in the application.

## Reporting a vulnerability

Please report privately, not in a public issue.

1. Use **GitHub private security reporting** on the
   [OpenGRC repository](https://github.com/LeeMangold/OpenGRC): **Security** →
   **Report a vulnerability**.
2. If you cannot use GitHub, use the contact published at
   <https://opengrc.com/.well-known/security.txt>.

Include:

- the affected version, tag or commit;
- the deployment shape (SQLite/MySQL/MariaDB/PostgreSQL, container or bare host,
  proxy/WAF in front);
- the smallest reproduction you can manage, and the impact you believe it has;
- whether the issue needs an authenticated account, a particular role, or only
  network access;
- any suggested remediation.

Do **not** include real customer data, production credentials, evidence files or
personal data in a report. Use a synthetic reproduction.

## What is in scope

- Authentication, session handling, MFA enforcement and password reset.
- Authorization: the three-role model, per-record policies, and vendor-portal
  isolation.
- Private file access, upload content checks, and the evidence ledger's
  append-only and hash-chain properties.
- Trust center and audit-portal disclosure, including expired or revoked links.
- Server-side request forgery and the connector boundary.
- Secret handling, logging of secrets or raw identifiers, and the audit ledger's
  coverage.
- Dependency and supply-chain issues in code shipped from this repository.

## Usually out of scope

- A self-hosted configuration mistake: debug mode left on, default credentials,
  missing TLS, an exposed database, or a permissive reverse proxy. Report it if
  the application makes the mistake easy to miss, but it is not automatically a
  code vulnerability.
- An attacker who already has host root, database superuser, or write access to
  the application key. The audit and evidence ledgers detect tampering; they do
  not prevent a privileged attacker from dropping a trigger or replacing the
  whole database.
- Vulnerabilities in separately deployed services (OpenMetadata, OpenBao, Wazuh,
  OpenCyberRange, a SIEM). Report those upstream. OpenGRC calls their APIs and
  does not vendor their code.
- Automated scanner output with no demonstrated impact on OpenGRC.
- Missing hardening headers on a deployment that terminates TLS and headers at a
  proxy, unless the application bypasses the proxy.

## What happens next

We will acknowledge the report, confirm whether we can reproduce it, and tell you
the next step. We follow coordinated disclosure: please give us a chance to fix
the issue before publishing details. Once a fix is released we will credit the
reporter if they want credit.

## Operator responsibilities

The security defaults are documented in
[`docs/security/`](docs/security/), and the container posture in
[`docs/security/container-hardening.md`](docs/security/container-hardening.md).
At minimum, an operator should:

- serve the application over HTTPS and keep the application key and `.env` out of
  backups that leave the trust boundary;
- enforce MFA for privileged roles and close client registration;
- keep `storage/app` (evidence and encrypted values) on persistent, access-
  controlled storage;
- run the scheduler, including `opengrc:integrity:verify`, and watch the
  dashboard's **Record integrity** tile;
- keep the host, PHP, database and dependencies patched.

No deployment is secure because the README says so. The defaults are a floor.
