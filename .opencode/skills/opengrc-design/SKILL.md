---
name: OpenGRC Design
description: Use when designing a new screen or changing layout, colour, typography, spacing, density, states, or visual consistency across the admin panel, vendor portal, trust center, reports, and PDFs. Covers the design tokens that exist, the semantics of status colour, and the rules that keep dense compliance data readable.
---

# OpenGRC Design

The interface is used for hours at a time by people reading control text, comparing
risk levels, and hunting for one failed audit item. Design for that, not for a
marketing screenshot: density is a feature, decoration is not.

This skill is the visual language. For the code that implements it, load
`opengrc-user-interface`.

## Tokens that already exist

| Token | Value | Where |
|---|---|---|
| Primary panel colour | Slate | `AppPanelProvider`, `AdminPanelProvider` |
| Vendor panel colour | Teal | `VendorPanelProvider` |
| Brand accent | `grcblue-400/500` `#1375a0`, 50–900 scale | `resources/css/filament/app/theme.css` |
| Display font | Bruno Ace SC | logo only, `resources/fonts` |
| UI font | Filament/Tailwind sans stack | do not override |
| Framework | Tailwind 4 CSS-first `@theme`, Filament 4 theme | `resources/css/filament/app/theme.css` |

The `grcblue` palette is defined once in `theme.css`. Tailwind utilities for it
are also registered in `tailwind.config.js` so they survive purging. Do not
redefine the palette in a component, a Blade style block, or an inline style.

## The rules

1. **Use the token, not the hex.** If a colour is not in the palette or a Filament
   semantic colour, the answer is usually "use a semantic colour", not "add a
   new hex".
2. **Semantic colour carries meaning, and only one meaning.**
   - Risk: green → yellow → orange → red, lowest to highest. Never reuse risk red
     for "new" or "featured".
   - Status: neutral for planned/unknown, blue for in progress, green for
     effective/completed, amber for partial/pending, red for failed/rejected.
   - Destructive actions are red and always confirmed.
   - Links and primary actions are the accent, not the body text colour.
3. **Colour is never the only carrier.** Pair a badge with its label or icon.
   People print these pages, and about one in twelve men cannot separate the
   red/green pair.
4. **Hierarchy before borders.** Prefer a section heading, spacing, and weight.
   Boxes inside boxes inside boxes turn a form into a maze.
5. **Consistency beats local cleverness.** If a pattern exists for enums,
   dates, owners, or evidence, reuse it even if a slightly different treatment
   would look better on this one page.

## Layout and density

- Forms use Filament `Section` → `Grid`/`Group` with at most four columns. A
  field that needs a paragraph of help text gets more width, not a smaller font.
- Long-form content (control text, policy body, survey question) is rendered as
  prose with real line length. Full-width 9px text is unreadable; so is a 40rem
  column on a 4K monitor.
- Tables are the primary way to scan lists. View pages are for objects people
  read rather than edit. Do not force an edit form on a read-first object.
- Detail pages put identity first (code, name, status, owner), then relations,
  then history. The user's first question is "what is this and who owns it".
- Actions that change state sit near the state they change, not in a distant
  toolbar.
- Keep view and edit layouts recognisably the same so switching modes does not
  feel like a different product.

## Typography

- Sentence case for labels, headings, buttons, and menu items. The exception is
  acronyms and framework identifiers (`AC-2`, `A.8.8`, `CC6.1`), which stay
  verbatim.
- Three weights are enough: regular body, medium for labels, semibold for
  headings. Bold paragraphs read as shouting.
- Numbers in tables are right-aligned and use tabular figures where the column is
  compared (scores, counts, dates).
- Identifiers, file names, and codes use a monospace face when alignment matters.
- Helper text explains the consequence ("Used on the report cover"), not the
  field name ("Enter name").

## States

Every screen that lists or edits something needs four states designed, not just
the happy one:

| State | What the user must see |
|---|---|
| Empty | Why it is empty and the next action, not a blank table |
| Loading | A skeleton or spinner that preserves layout; never a frozen button |
| Error | What failed, whether the data was saved, and what to do next |
| Partial/unknown | An explicit "Unknown" or "Not assessed", never a blank cell that looks like zero |

Compliance data has a fifth state that most designs forget: **stale**. If a
control was last tested two years ago, say so where the status is shown.

## Interaction

- Destructive actions name the object and the consequence: "Delete audit
  'Q3 SOC 2'? Evidence attached to its items will be orphaned."
- Bulk actions state the count and what will change. If the selection includes
  records the user cannot edit, say so before running.
- Do not use optimistic UI for compliance writes. The record is the evidence;
  show the saved state or the failure.
- Warn on unsaved changes when leaving a long form. Silently discarding a policy
  edit is a data-loss bug with a polite face.
- Filters and search persist long enough to be useful; a table that forgets the
  filter on every navigation is hostile.

## Accessibility and language

- Label every input. A placeholder is not a label.
- Keep focus visible and tab order logical; Filament's defaults are good until
  custom markup removes them.
- Provide alt text for meaningful images and `alt=""` for decoration.
- Text meets WCAG AA contrast in both light and dark mode. Check the `grcblue`
  accent on dark backgrounds before using it for body text.
- Translations expand. German-style compounds and French phrasing run 20–30%
  longer than English; do not size a button to the English string.
- There is no RTL support. Do not imply it by mirroring one component.

## Reports and PDF

- PDFs are a separate medium: no hover, no truncation tooltips, no colour-only
  status. Repeat the status text in the PDF.
- DomPDF supports a subset of CSS. Prefer tables, simple flex, and explicit page
  breaks. Test with real long content, not the demo record.
- The report header carries the organisation, the report type, and the date. A
  printed page with no context is not evidence.

## Review checklist

- [ ] Colours come from the palette or a semantic Filament colour.
- [ ] Risk and status colours match their meaning everywhere on the screen.
- [ ] Status is readable without colour and in greyscale print.
- [ ] Empty, loading, error, and unknown states are handled.
- [ ] Long text has a readable measure and hierarchy.
- [ ] Destructive and bulk actions state consequence and count.
- [ ] Labels are sentence case, identifiers are verbatim, numbers align.
- [ ] Light and dark mode both checked.
- [ ] Strings are translatable and survive a longer locale.
- [ ] The layout is recognisably the same as sibling screens.

## Anti-patterns seen in this codebase

- Stacking `!important` overrides in `theme.css` to win a specificity fight.
  Fix the selector or use a Filament hook instead of adding another layer.
- A one-off hex in a Blade template because the palette lacked the exact shade.
- Icon-only navigation with no label and no tooltip.
- A table that hides the only column a user needs behind "toggleable" defaults.
- A dashboard widget that counts records the viewer is not allowed to open.
