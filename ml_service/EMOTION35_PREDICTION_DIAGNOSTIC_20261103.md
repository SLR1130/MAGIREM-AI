# Emotion35 prediction-quality diagnostic (2026-11-03)

## Scope

This audit compares the isolated v2 and v3 checkpoints only. The production
six-class checkpoint, Student Chat, crisis routing, consent, and Admin analytics
were not changed.

## Confirmed causes

1. **Severe language imbalance.** The v3 training split contains 24,085 English,
   270 Filipino, and 312 Taglish rows. The test split contains only 42 Filipino
   and 112 Taglish rows, while validation contains 112 Filipino and only 6
   Taglish rows. Validation thresholds therefore do not represent the test
   language mixture.
2. **Rare-label sparsity.** Most priority emotions have only 21–31 positive
   training rows and 5–15 positives in the test split. This makes thresholds and
   per-label scores unstable.
3. **Unreviewed synthetic supervision.** v3 adds 120 AI-assisted rows, all
   marked `synthetic=true`, `human_review_required=true`, and
   `review_status=unreviewed`. Their full masks encode curated negatives, but
   those negatives have not been independently verified. This is a data-quality
   risk, not human ground truth.
4. **Mixed-emotion scarcity.** Only a small number of mixed examples are in the
   training data; the model frequently drops secondary emotions or adds unrelated
   ones.
5. **Tokenizer/inference consistency is not the primary defect.** Training and
   adviser inference both use the local RoBERTa tokenizer, truncation at 256
   tokens, the same 35-label order, sigmoid outputs, and validation-only
   thresholds.

## Fresh independent holdout

`storage/app/audit-recovery/emotion35_independent_eval_20261103.json` contains
47 new synthetic cases: all 35 labels, mixed emotions, neutral/no-emotion,
uncertainty, hypothetical language, and third-person attribution. It was not
used for training or threshold calibration.

Results from the unchanged checkpoints:

| checkpoint | primary correct | label precision | label recall | label F1 | uncertainty correct |
|---|---:|---:|---:|---:|---:|
| v2 | 10/44 | 0.200 | 0.294 | 0.238 | 1/3 |
| v3 | 8/44 | 0.211 | 0.235 | 0.222 | 1/3 |

Additional holdout diagnostics (44 emotion-bearing cases; 7 mixed cases):

| checkpoint | strict category-set agreement | mixed-label precision | mixed-label recall | mixed-label F1 | primary exact by language |
|---|---:|---:|---:|---:|---|
| v2 | 5/44 (0.114) | 0.294 | 0.357 | 0.323 | EN 6/14, Taglish 4/16, Filipino 0/14 |
| v3 | 2/44 (0.045) | 0.267 | 0.286 | 0.276 | EN 4/14, Taglish 4/16, Filipino 0/14 |

“Strict category-set agreement” requires the complete predicted category set to
match the categories implied by the expected labels. It is intentionally stricter
than reporting whether any predicted category overlaps the expected category.

Both checkpoints achieved 0/14 primary matches on the Filipino cases. Common
failures were `homesickness -> disappointment`, `anxiety -> neutral`,
`overwhelm -> neutral`, and unrelated `hopelessness` predictions.

Representative fresh failures included Taglish excitement being rejected as
uncertain, Filipino pride being emitted as `hopelessness`, Filipino stress being
rejected, English homesickness being emitted as `disappointment`/`neutral`,
`shame + guilt` becoming `shame + frustration`, and a Taglish
`happiness_joy + homesickness` case becoming `hopelessness`.

## Training decision

Do **not** train v4 from the current unreviewed candidate pool. More epochs or
threshold changes would not fix the language and label-quality problems and
would risk another misleading checkpoint. The existing
`review_batch_gap_002.csv` (210 rows, seven priority labels, 10 examples per
language) is the next reviewable source. Accepted human-reviewed rows should
be imported with explicit observed-label masks before v4 training.

The independent holdout predictions are preserved as failure evidence. No
production activation is justified.
