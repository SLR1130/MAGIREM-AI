# Dedicated 35-emotion multilabel classifier status

## Scope

The six-class `TrainedEmotionClassifier` remains the production classifier. The
new files `emotion_35_multilabel_pipeline.py`,
`train_emotion_35_multilabel.py`, `emotion_35_multilabel_classifier.py`, and
`evaluate_emotion_35_multilabel.py` are an isolated, evaluation-only path. No
Flask route, Laravel endpoint, Admin aggregation, persistence path, or feature
flag was changed to use it.

## Local corpus audit (2026-10-25)

The audited corpus was `emotion_data_multilingual_final_v2`:

| split | records | labels present |
|---|---:|---|
| train | 406 | anger, anxiety, grief, joy, neutral, sadness |
| validation | 88 | anger, anxiety, grief, joy, neutral, sadness |
| test | 96 | anger, anxiety, grief, joy, neutral, sadness |

The taxonomy has 35 approved keys and eight reporting categories. Thirty
approved keys have zero examples in every split. The legacy `joy` key is not an
approved 35-label key (`happiness_joy` is), so it is reported as unsupported
rather than silently renamed. No cross-split text leakage was found in this
corpus. Provenance/licensing metadata is not present.

The audit therefore refuses training and writes no model artifact. This is an
intentional safety gate, not a failed training run. A real corpus must include
reviewed labels for all 35 keys, multilabel annotations where applicable,
independent train/validation/test splits, and provenance/licensing/usage
metadata before `--train` can proceed.

## Training and evaluation safeguards

The pipeline:

* validates the exact taxonomy version and all labels;
* rejects missing/low per-label coverage and split leakage;
* requires provenance fields (`source`, `license`, `usage`, `curator`,
  `version`);
* initializes a new 35-output BCE/multilabel head from an explicitly supplied
  local encoder without overwriting the six-class model;
* calibrates per-label thresholds on validation data only;
* writes a manifest, provenance, thresholds, training configuration, and test
  metrics only after a successful audit and training run.

The inference wrapper is default-off (`MINDCARE_35_ML_ENABLED=false`), requires
an artifact whose taxonomy version matches the approved config, and returns a
shadow-only result. It does not infer attribution or override crisis logic.

## Current decision

No dedicated 35-class model, 35-class accuracy, or deployment readiness claim
is made. The existing Gemma Option-B holdout remains experimental evidence, not
training ground truth. The next authorized step is to provide a legitimately
sourced, professionally reviewed 35-label corpus and run the untouched audit
and unseen evaluation once.


