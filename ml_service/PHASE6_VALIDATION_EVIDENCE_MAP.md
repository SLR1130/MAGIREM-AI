# Phase 6 validation evidence reconciliation

**Reviewed:** 2026-10-24  
**Taxonomy version:** `1.0.0`

## Scope of the evidence

The project owner has confirmed that the 35 emotion definitions and eight
reporting categories were professionally evaluated outside the repository.
That confirmation is accepted as the project decision for engineering work;
confidential school documents, reviewer identities, signatures, and private
student material are not requested or copied into source control.

This is separate from classifier-performance validation. External taxonomy
review does not turn the local synthetic dataset into a final ground-truth
holdout, and no production accuracy claim is made from the development scores.

## What can be established locally

| Evidence | Local source | Engineering conclusion |
|---|---|---|
| Controlled vocabulary and mapping | `config/emotion_taxonomy.json`, `app/Services/EmotionTaxonomy.php`, taxonomy tests | The application has one deterministic 35-label to eight-category source of truth |
| Structured interpreter | `ml_service/emotion_interpreter_schema.py` and `detailed_emotion_shadow.py` | Validated clear, mixed, uncertain, no-emotion, and attributed-other output without numeric confidence |
| Final pilot boundary | `InferenceBridgeClient`, `AIChatController`, Flask internal route | Trusted server-side consent gates a non-persisting local shadow call; six-class output remains authoritative |
| Development examples | `ml_service/phase5_final_unseen_cases.json` | 50 synthetic cases remain development material with draft annotations |

## Dataset reconciliation

Inspection of `phase5_final_unseen_cases.json` still finds:

- status `pending_counselor_review`
- taxonomy version `1.0.0`
- 50 synthetic cases
- draft annotations on all 50 cases
- no locally stored adjudication manifest or final holdout hash

No field has been renamed and no draft label has been promoted to ground truth.
The final holdout evaluator should only run after a genuinely final dataset is
available. This limitation does not prevent synthetic runtime testing of the
non-persisting pilot.

## Deployment interpretation

The six-class classifier remains authoritative in production. Detailed Gemma
results can be exercised only in an explicitly approved local shadow pilot with
trusted Laravel consent, finite timeout, shared GPU admission, and a rollback
path. Shadow output is not persisted and is not included in Admin aggregates.

Moving beyond shadow mode requires a separate decision based on an independent
evaluation and authorized privacy/Admin review. This document does not claim
clinical validity or broad 35-emotion production support.
