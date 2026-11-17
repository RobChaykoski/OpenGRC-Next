---
name: OpenGRC User Interface
description: Use when building or changing screens in the admin panel, vendor portal, trust center, reports, or shared Blade views - Filament 4 resources, forms, tables, infolists, actions, widgets, navigation, Livewire components, and render hooks. Covers the patterns this codebase already uses and the pitfalls that come from four panels sharing one model layer.
---

# OpenGRC User Interface

Four surfaces, one domain:

| Surface | Path | Guard | Used by |
|---|---|---|---|
| App panel (`/app`) | `app/Filament/Resources`, `Pages`, `Widgets` | `web` | Internal team; main working UI |
| Admin panel (`/admin`) | `app/Filament/Admin` | `web` | Settings, users, system pages |
| Vendor portal (`/portal`) | `app/Filament/Vendor` | `vendor` | External vendors; own surveys and documents |
| Trust center (`/trust`) | `app/Http/Controllers/TrustCenterController.php`, `resources/views/trust-center` | none / magic link | Public and approved prospects |

Reports and PDFs are Blade, not Filament: `resources/views/reports`, `pdf`. They
are a UI surface too, with their own constraints.

Load `opengrc-design` for the visual language. This skill is the implementation.

## Pick the right container

| Need | Use |
|---|---|
| CRUD on a model | Resource + List/Create/Edit/View pages |
| Read-mostly detail with relations | Resource `View` page + relation managers |
| A workflow across models (approve, respond, import) | Custom Page with a Blade view |
| A number, list, or chart on a dashboard | Widget |
| A cross-cutting element (banner, guard, footer) | Render hook |
| A public or token-gated page | Controller + Blade view |

Do not build a custom page for something a resource already does, and do not
stretch a resource into a workflow it cannot express.

## Resource anatomy (Filament 4)

Follow `app/Filament/Resources/PolicyResource.php`:

```php
class PolicyResource extends Resource
{
    protected static ?string $model = Policy::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Policies';
    protected static string|\UnitEnum|null $navigationGroup = 'Entities';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema { ... }
    public static function table(Table $table): Table { ... }
    public static function infolist(Schema $schema): Schema { ... }
    public static function getPages(): array { ... }
}
```

- Imports come from `Filament\Schemas\Schema`, `Filament\Schemas\Components\*`,
  `Filament\Actions\*`, `Filament\Forms\Components\*`, `Filament\Tables\*`.
  Filament 3 namespaces (`Filament\Resources\Form`, `Filament\Forms\Form`) are
  wrong here.
- Navigation groups in the app panel are `Foundations` and `Entities`
  (`AppPanelProvider`). Picking a new group affects the whole sidebar; justify it.
- Icons are Heroicons (`heroicon-o-*` for navigation, `heroicon-m-*` for inline).
- Pages live in `{Resource}/Pages`, relation managers in
  `{Resource}/RelationManagers`. Register every page in `getPages()`.

## Forms

- Compose with `Section` → `Grid`/`Group`/`Flex`. The defaults provider makes
  sections, fieldsets, and grids span full width; set `->columns()` deliberately.
- Required fields: `->required()`, a real `->maxLength()`, and a `->unique()`
  that uses `ignoreRecord: true` on edit.
- `helperText()` explains consequence, not the field name.
- Relationship selects use `->relationship()` with `searchable()` and `preload()`
  only when the option list is bounded. An unbounded preload is a memory bug.
- Taxonomies use `HasTaxonomyFields` and `TaxonomySelectFilter` rather than
  hand-written option queries.
- Rich text uses `RichEditor` (sanitized by the `SanitizedHtml` cast). Never echo
  it raw in Blade without `HtmlSanitizerService`.
- File uploads use `UploadRules` for accepted types and size, and attach
  `UploadRules::validationRule($group)` so the server re-checks the bytes. Point
  evidence at the private disk.
- Repeaters are for genuinely repeated structure, not for avoiding a relation.

## Tables

`FilamentDefaultsServiceProvider` makes **every column toggleable, searchable, and
sortable** by default. That is convenient and occasionally wrong:

- Override when a column is not meaningfully searchable (a computed badge) or when
  sorting is expensive on SQLite.
- Keep the identifying columns visible on first load: code, name, status, owner.
  A table whose first column is hidden is a support ticket.
- Use `TextColumn::badge()` with enum colours for status; do not render status as
  free text in one table and a badge in the next.
- Filters belong near the table. `TrashedFilter` only appears where soft deletes
  are exposed and the role may restore.
- Bulk actions show the selected count and require confirmation when destructive.
- Exports are `app/Filament/Exports` classes, authorized like the screen. An export
  that returns rows the user cannot open is a leak.

## Actions, pages, and state

- Header actions change the record; table actions change the row; bulk actions
  change the selection. Do not put a row action in the page header.
- Confirmation text names the object and the consequence, especially for delete,
  revoke, and approve.
- Use `->visible()` for relevance and policy checks for permission. Hiding is not
  authorizing.
- Custom pages that carry workflow state (`ToDo`, `Import`, checklist approval,
  survey response) keep the logic in the page or a service, not in the Blade file.
- The panels are SPA-enabled. Test navigation between pages, not only a hard
  refresh; stale Livewire state shows up there first.

## Blade, Livewire, and hooks

- Views for Filament pages go under `resources/views/filament/...`; shared
  components under `resources/views/components`.
- Cross-cutting UI is registered with `PanelsRenderHook` in the panel provider
  (inactivity guard, session expiry, sidebar footer). That is the supported place
  for it; do not edit a vendor view.
- Livewire components live in `app/Livewire` and are rendered with
  `@livewire('component-name')`. The inactivity guard is the reference.
- No business queries in Blade. Pass data in; policies and scopes stay in PHP.
- Alpine is available for interaction. Inline `onclick` handlers are not the
  house style and break under CSP changes.
- The trust center is a standalone public page and loads Tailwind and Alpine from
  CDNs. It must never include internal Filament components, and it must not leak
  protected document metadata to the public branch.

## Vendor portal

- Separate guard, separate password broker, separate login page. Internal roles do
  not apply, and vendor users must not be able to reach `/app` or `/admin`.
- The portal shows the vendor's own surveys and documents only. Every query needs
  a vendor scope; the policy is a backstop, not the filter.
- Branding and portal name come from settings, not hard-coded strings.

## Language

- Every user-visible string is translatable. Add `lang/en` keys and align
  `es`, `fr`, and `hr` structurally; a missing key falls back but a mismatched
  structure drifts.
- Enum labels implement `HasLabel` (`UserRole`, status enums). Prefer that over a
  `match` in the resource.
- Do not hard-code "OpenGRC" inside a record label; the brand belongs to the
  panel, the record belongs to the customer's vocabulary.

## Verify by opening it

Filament is not fully covered by tests, so a green suite does not mean the screen
works. Before calling it done:

- [ ] Open the list, create, edit, and view pages as an **admin**.
- [ ] Open them as an **editor** and confirm no delete or settings affordances.
- [ ] Open them as a **viewer** and confirm read-only.
- [ ] Create with missing and invalid input; confirm messages are useful.
- [ ] Upload a file if the form has one; confirm the rejected types are rejected.
- [ ] Check the table with zero rows and with enough rows to paginate.
- [ ] Use search, each filter, and one bulk action.
- [ ] Toggle dark mode and narrow the window to tablet width.
- [ ] For vendor or trust center screens, use an account with that guard only.
- [ ] For reports, generate a PDF with a long record and a real logo.

## Pitfalls specific to this repository

- `AdminPanelProvider` registers `viteTheme()` twice. Harmless today, but if you
  touch the file, remove the duplicate rather than adding more calls.
- Shared resources appear in the app panel only. A resource added under
  `app/Filament/Admin/Resources` will not appear in `/app`, and vice versa.
- Widgets and exports bypass resource scoping unless you apply it yourself.
- By default every column is searchable, so a `SelectColumn` or computed column
  can generate a confusing query. Disable it explicitly.
- Score/status colour utilities in `theme.css` are global. A new colour name must
  not collide with an existing risk colour.
- `canAccessPanel()` returns true for every user by design; do not treat it as a
  role check.
