# Support policy

OpenGRC is pre-1.0. This page states what is supported, for how long, and what to
expect when reporting a problem. It is deliberately conservative: a promise that
cannot be kept is worse than a narrow one that can.

## Supported versions

| Line | Status | Security fixes | Bug fixes | Notes |
|---|---|---|---|---|
| Latest release | Supported | Yes | Yes | Always the recommended target |
| Previous minor | Supported | Yes | Critical only | Gets fixes for 90 days after a new minor ships |
| Older releases | Unsupported | No | No | Upgrade to a supported line |

Until 1.0, breaking changes can occur in a minor release. They are called out in
the release notes, and every upgrade follows `docs/UPGRADING.md`. After 1.0, a
minor release will not break documented behaviour.

## Supported environment

The tested baseline is `versions.yaml`, explained in
`docs/SUPPORTED-VERSIONS.md`. In short:

- PHP 8.4 or newer, with the extensions listed in the baseline
- Composer 2.5+, Node 20.19+ (24 LTS recommended), npm 9+
- SQLite 3.35+, MySQL 8.0+, MariaDB 10.6+, or PostgreSQL 14+

Anything outside that table is best effort. It may work; we cannot claim it does.

## What is supported

- Installation using the documented installer or Docker image
- Upgrades from the previous supported release, following the runbook
- Defects in the application: incorrect behaviour, broken permissions, data
  integrity problems
- Security vulnerabilities, through the process in `SECURITY.md`

## What is not supported

- Customised forks, patched core files, or hand-edited migrations
- Unsupported PHP, Node, or database versions
- Hosting, network, TLS, or backup infrastructure
- Third-party plugins that are not listed as compatible
- Recovery from running `composer update` or `npm install` on a production host

## Reporting

- **Bugs and feature requests:** GitHub issues, using the templates. Include the
  output of `php artisan opengrc:doctor --json` and `php artisan about`.
- **Security issues:** follow `SECURITY.md`. Do not open a public issue.
- **Upgrade problems:** include the from/to versions, the migration output, and
  whether a restore-tested backup exists.

`opengrc:doctor` output is the difference between a support conversation and a
guessing game, because it separates environment faults from application faults.

## Response expectations

Best effort, by severity:

| Severity | Meaning | Target |
|---|---|---|
| Critical | Data loss, auth bypass, remote code execution | Acknowledge within 3 business days |
| High | Major feature broken, security weakness without direct exploit | Triage within 10 business days |
| Normal | Defect with a workaround, feature request | Reviewed as capacity allows |

These are targets, not contractual response times. If you need contractual
support, use the enterprise offering.

## End-of-life policy

A component is unsupported when any of the following is true:

- It is no longer maintained upstream (PHP, Node, database engine)
- It is affected by a vulnerability without an available fix
- The framework it depends on no longer supports it (Laravel, Filament)

When a line goes out of support it is removed from `versions.yaml`, and the
removal is announced in the release notes at least one minor release before the
CI matrix drops it.
