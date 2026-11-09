# Phase 5 — Independent Evaluation Protocol

## Purpose and boundary

This protocol evaluates the frozen, experimental Gemma interpreter only. It does
not change the six-class production classifier, deterministic crisis screening,
counselor escalation, consent rules, persistence, or Admin analytics. The final
set is synthetic and must remain outside prompt-development files.

## Required order

1. Review `PHASE5_COUNSELOR_VALIDATION_PACKAGE.json` and all cases in
   `phase5_final_unseen_cases.json` with at least two qualified reviewers.
2. Record disagreements with `PHASE5_COUNSELOR_REVIEW_FORM.md` and adjudicate them.
3. Replace only the `draft_*` fields with adjudicated labels. Preserve the original
   text, case IDs, and an audit copy of the pre-review file.
4. Freeze the prompt, schema, taxonomy, and evaluator. Record new hashes and a
   reviewer/adjudication version.
5. Run the final unseen set **once**. Do not tune the prompt on this set and rerun
   it as if it were independent validation.
6. Keep Gemma output in an evaluation artifact; do not write it to production
   emotion records or expose it to Admin analytics.

## Dataset coverage

The draft set contains one clear synthetic case for each of the 35 controlled
labels, plus challenge cases for mixed emotions, ambiguity, third-person and both
attribution, hypothetical language, no emotion, negation, English, Filipino,
Taglish, and slang. The draft annotations are review prompts, not ground truth.

## Metrics and denominators

Report numerator and denominator for every metric:

- Schema validity: valid structured responses / all cases.
- Primary detailed-label agreement: exact adjudicated primary labels / cases with a
  non-null adjudicated student primary label.
- Reporting-category agreement: exact derived category set / cases with an
  adjudicated student primary or secondary category.
- Mixed detection: precision, recall, and F1 over cases adjudicated `mixed`.
- Uncertainty: precision and recall over cases adjudicated `uncertain`.
- Attribution: exact scope agreement / cases with an adjudicated attribution scope.
- Per-category and per-label counts, including zero-support labels.
- Median and p95 latency, plus consistency on a separately repeated small sample.

Do not invent confidence scores. Null labels and `uncertain` are valid outcomes.
Category values must be derived from validated detailed keys using taxonomy version
`1.0.0`.

## Comparison and release gate

Run the six-class classifier as a separate comparator; do not merge its labels with
Gemma metrics. Treat the historical 68-case and 39-case results as development
history. Professional review, privacy approval, and safety review are required
before any consent-gated shadow test. No live Admin activation or retraining is
authorized by this protocol.
