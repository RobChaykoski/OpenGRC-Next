# Control-to-outcome links

OpenGRC has two kinds of control objects, and they answer different questions:

- An imported **control** (`AC-2`, `A.8.8`, `CSC-7`) is a published requirement.
  It belongs to a standard version and its text is not ours to edit.
- A **canonical control** (`CTRL-ACCESS-001`, `CTRL-VULN-001`) is an outcome the
  organisation is trying to achieve. One outcome is usually met by several
  requirements across several frameworks.

A link between them says: *this published requirement is one way we meet this
outcome.* Links are curated by a person. Nothing in OpenGRC guesses them from a
title, a code prefix, or a similarity score.

## What a link is not

- It is not a claim that two clauses are textually equivalent.
- It is not evidence that the control operates. Evidence and validation do that.
- It is not a certification or a legal opinion.
- It is not permanent. Frameworks revise; links are reviewed when they do.

## An unlinked control is a gap, not a failure

An imported control with no canonical outcome means one of two things:

1. nobody has reviewed it yet, or
2. it genuinely does not serve one of the outcomes in the catalogue.

Both are valid states. The control list marks these as **Not linked** and the
filter of the same name shows them. An unlinked control:

- does not fail an audit by itself,
- does not mean the control is ineffective,
- does not disappear from coverage totals — it shows where review is owed.

The pressure to "clear the list" is the main risk in this feature. A guessed link
hides a gap and is worse than an honest blank, because the coverage report will
count something that no reviewer stands behind. If you cannot point at the
requirement text and the outcome and explain the connection in one sentence,
leave it unlinked.

Large frameworks are intentionally partial. NIST SP 800-53 ships over a thousand
clauses; bulk-mapping all of them in one pass would be guessing at scale. The
shipped starter map covers CIS Critical Security Controls v8 and the reviewed
NIST CSF 2.0 subcategories. Extend it as your programme adopts specific clauses.

## Linking in the UI

Open a control and use the **Canonical outcomes** field. You can select more than
one outcome, because a clause such as "access control management" often serves
both least privilege and access review. Save the control. The link is recorded
with `source = manual` and is never removed by a command.

Who can link: the usual three roles apply. Editors and admins can change a
control; viewers can read the links and the coverage filter.

## Linking from a curated map

For repeatable installs, put reviewed links in
`config/canonical_control_links.php`:

```php
return [
    'CSCv8' => [
        'CSC-7' => ['CTRL-VULN-001'],
    ],
];
```

Then apply them:

```bash
php artisan opengrc:catalog:sync   # canonical outcomes must exist first
php artisan opengrc:catalog:link
```

The command:

- resolves the framework by its code and each control identifier exactly;
- writes links with `source = config`;
- replaces config-sourced links for every framework the map covers, so deleting
  an entry removes that link on the next run;
- never touches `source = manual` links;
- warns, without failing, when a framework in the map is not installed or an
  identifier does not exist in the imported version;
- fails before writing anything when the map names a canonical outcome that does
  not exist, because that is a configuration error.

A framework whose imported version differs from the map will produce
"identifier did not match" warnings. Fix the identifier; do not loosen the
matching.

## Policies and evidence

A canonical outcome is not supported by controls alone:

- **Policies and documents** link directly from the policy form. A policy counts
  for an outcome when it is linked directly, or when it is attached to a control
  that is linked to the outcome. The same policy counted both ways is still one
  policy. An unlinked policy is a coverage gap to review, not a failure.
- **Evidence** does not have its own outcome link. It inherits the outcomes of the
  control it names on the evidence record. This keeps the ledger append-only: no
  one can re-point a hashed record to change a report. Evidence with no control
  has no outcome, which is an honest gap; link the control to the outcome if the
  measurement really supports it.

The evidence view labels the outcomes as inherited from the control, and the
policy view marks an unlinked policy as a coverage gap, so the empty state is not
mistaken for a bug.

## Review checklist

Before merging a change to the curated map:

- [ ] Every new link points at a canonical outcome that exists in
      `config/framework_scoping.php`.
- [ ] The control identifier matches the imported version verbatim.
- [ ] A reviewer can explain each link from the requirement text.
- [ ] The mapping is not presented as complete when it is partial.
- [ ] If a framework was revised, the old identifiers were checked, not assumed.

## What comes next

The read-only framework matrix (Milestone A.4) will show, for one framework,
which canonical outcomes are required, which internal controls are linked, and
whether evidence exists. Unlinked controls remain visible there as review work,
not as zeros that can be improved by clicking.
