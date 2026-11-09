# Phase 5 — Admin Analytics Readiness

## Current production boundary

The deployed trained classifier recognizes six labels only: `sadness`, `joy`,
`anxiety`, `anger`, `neutral`, and `grief`. The 35-label Gemma interpreter is
evaluation-only and must not be presented as if those detailed emotions are live
production measurements.

## Dashboard requirements

- Label the eight category charts as taxonomy reporting categories, not diagnoses.
- Show a clear note that detailed Gemma interpretation is not deployed.
- Keep unknown/unclassified historical records visible in denominators where
  appropriate, without guessing a label.
- Preserve consent-scoped aggregation, legitimate Admin authorization, and
  small-group suppression for every chart, filter, and drill-down combination.
- Avoid showing raw student messages or identities in aggregate views.
- Do not silently reclassify historical records.

## Evidence status

The existing runtime health and Laravel regression evidence remain valid. A new
authenticated Admin screenshot is **pending** because no legitimate Admin browser
session was available during this preparation pass. No authentication bypass was
used and no private student data was accessed. The prior unauthenticated redirect
artifact is retained at:
`storage/app/audit-recovery/emotion-taxonomy-20261024/admin-reports-browser-redirect.png`.

Before release, an authorized administrator must capture desktop and mobile views
of the category overview, percentages, date filters, detailed drill-down,
unclassified records, consent scope, and small-group suppression.
