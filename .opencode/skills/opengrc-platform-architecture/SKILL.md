---
name: OpenGRC Platform Architecture
description: Use when adding or changing features in the OpenGRC Laravel/Filament codebase - new resources, migrations, permissions, MCP tools, modules, or services. Covers the code map, conventions, and the checklist that keeps changes consistent and upgradeable.
---

# OpenGRC Platform Architecture

OpenGRC is a Laravel 13 + Filament 4 + Livewire 3 application. It is a system of
record, not a demo: schema, permissions, and auditability matter as much as UI.

Read `docs/SUPPORTED-VERSIONS.md` before proposing anything that changes a
dependency or a runtime requirement.

## Code map

| Area | Path | Notes |
|---|---|---|
| Admin panel | `app/Filament/Admin` | Primary UI: resources, pages, widgets |
| Vendor panel | `app/Filament/Vendor` | Vendor portal, separate navigation and access |
| Resources | `app/Filament/Resources` | One resource per major model |
| Reusable UI | `app/Filament/Forms`, `Tables`, `Columns`, `Filters`, `Concerns` | Shared schema pieces |
| Models | `app/Models` | `Standard`, `Control`, `Implementation`, `Risk`, `Audit`, `Asset`, `Vendor`, `Policy`, `Surveys`, trust center models |
| Domain services | `app/Services` | Scoring, vendor assessment, AI, mail rendering, sanitizers |
| MCP tools | `app/Mcp` | AI-facing tools over the same domain, with permission checks |
| Commands | `app/Console/Commands` | `Install`, `Doctor`, `QueueShow`, user management |
| Modules | `Modules/*` | `nwidart/laravel-modules`; enabled in `modules_statuses.json` |
| Migrations | `database/migrations` | Append-only, ordered, self-documenting |
| Settings | `config/settings.php`, settings seeders | Stored settings override some env values at boot |

## Adding a feature: the path through the stack

1. **Model and schema.** Add a migration, then the Eloquent model with casts and
   relations. Look at a sibling model first; naming is predictable.
2. **Permissions.** Add permission names and seed them (`RolePermissionSeeder` or a
   dedicated seeder). Without this the UI silently disappears for non-super-admins.
3. **Resource.** Create the Filament resource with form schema, table, and
   policies. Reuse shared field builders instead of hand-rolling.
4. **Navigation and language.** Register navigation groups and add `lang/en` keys.
   Keep other locales structurally aligned.
5. **Jobs and events.** Anything slow (imports, exports, AI calls, mail) goes on the
   queue. The queue worker must be running; `QUEUE_AUTO_START` is a dev convenience,
   not a production strategy.
6. **Tests.** Feature test for behaviour, unit test for pure logic. Filament UI is
   not fully covered, so note what you checked by hand.
7. **Docs.** Update `docs/` when behaviour, configuration, or a supported version
   changes.

## Rules that prevent expensive mistakes

- **Migrations are append-only.** Editing an applied migration breaks every
  existing install and cannot be rolled forward.
- **SQLite is a real target.** Avoid MySQL-only SQL in migrations and queries.
  SQLite rebuilds tables for many ALTERs; long migration chains get slow.
- **Settings vs env.** Some values live in the settings table and override `.env`
  at boot. Changing `.env` alone may change nothing. Check `settings:set` too.
- **Panels are separate.** A resource in the admin panel is not automatically in
  the vendor panel, and vice versa.
- **Everything user-facing is translatable.** Hard-coded strings are a regression.
- **Do not bypass policies.** Direct model queries in widgets and exports still
  need the same access rules as the resource.

## Testing

```bash
php artisan test
php artisan test --filter=Vendor
./vendor/bin/pint --dirty
php artisan opengrc:doctor
```

Use `RefreshDatabase` for anything touching schema. Prefer factories over fixtures;
`database/factories` already covers the core models.

## When you are unsure

Read the nearest existing implementation of the same shape: another resource, a
sibling migration, the module's service provider. The codebase is consistent
enough that copying the pattern is usually correct and inventing a new one is
usually wrong.
