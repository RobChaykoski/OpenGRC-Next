# 0010 — Deterministic framework scoping before AI narrative

Status: proposed
Date: 2026-11-17

## Context

Customers and training cohorts need a defensible answer to "which frameworks
apply to us, and what does that oblige us to do?" A free-form AI answer is easy to
generate and hard to audit. Applicability depends on jurisdiction, industry,
data classes, and contractual drivers, all of which can be expressed as rules.

## Decision

1. A deterministic scoping service evaluates an organisation profile against
   `config/framework_scoping.php` and returns framework, control, document,
   evidence, automation, and roadmap matrices.
2. Industry data labels live in `data_classifications`, seeded from
   `config/data_classification.php`. Custom labels can be global or scoped to one
   or more industries. The scoping engine validates selected labels against the
   industry catalogue and reports unknown labels instead of guessing.
3. AI may add narrative, gap explanations, or draft documents later. It does not
   decide applicability or invent controls. Every recommendation must trace to a
   rule and a control code.
4. The command output is JSON so the result can be stored, diffed, reviewed, and
   later ingested into OpenGRC records.

```bash
php artisan opengrc:scope organization.json --output=assessment.json
```

## Consequences

- Recommendations are reproducible and reviewable.
- The framework catalogue and control mappings are curated data; adding a
  framework is a configuration change with tests, not a prompt change.
- The first release covers the major Canadian, US, and international frameworks
  in the catalogue. Niche schemes require a mapping entry.
- Unknown or unselected data labels reduce confidence and appear as gaps; they
  never silently produce a recommendation.
