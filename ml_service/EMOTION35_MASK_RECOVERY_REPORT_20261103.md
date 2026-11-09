# Emotion35 mask recovery and controlled experiment — 2026-11-03

## Scope and safety

This was a research-only recovery experiment. The production six-class model,
Gemma conversation path, crisis handling, consent controls, Student Chat, and
Admin analytics were not changed. v2, v3, and the quarantined v4 checkpoints
were preserved. No new checkpoint is connected to production or the adviser
demo.

## Root cause of the 834 contaminated rows

The contamination originated in the weak-record generators, not in the exported
v4 checkpoint:

- `prepare_emotion35_prototype_dataset.py` wrote every taxonomy key into
  `observed_labels` for synthetic rows.
- `prepare_emotion35_v3_augmentation.py` repeated the same behavior.
- `prepare_emotion35_v4_weak_supervision.py` inherited those rows unchanged.

As a result, 834 inherited weak rows (within the 1,368-row weak set used by the
old v4 run) exposed all 35 labels to the loss even when only one or two labels
were intended positives. That created 28,343 unsupported negative cells. These
were not verified negatives; they were unknown labels incorrectly treated as
observed negatives.

## Source correction

The generators now write:

- `weak_positive_labels`: intended positive labels only;
- `weak_negative_labels`: only explicitly justified contrast labels;
- `observed_labels`: the union of those two sets;
- `weak_label_policy`: positive-only unknown negatives unless a contrast record
  explicitly documents a negative.

The v4 preparation step repairs legacy inherited rows before validation, rejects
invalid taxonomy keys, rejects positives outside the observation mask, rejects
unjustified negatives, and rejects normalized text leakage across splits. Public
duplicate and synthetic near-duplicate filtering remains source-aware.

Clean dataset:
`storage/app/audit-recovery/emotion35_v4_weak_supervision_masks_fixed_20261103`

| Split | Records |
|---|---:|
| train | 25,236 |
| validation | 3,117 |
| test | 3,113 |

Clean train mask totals: **26,815 positive**, **332,131 explicit negative**,
**524,314 unknown** cells. Among weak rows, 1,355 observe one label and 48
observe two labels; **0** weak rows observe all 35 labels. The clean audit found
no invalid labels, no exact normalized text leakage across splits, and no
missing provenance fields. Human-reviewed rows remain 0.

## Regression tests

`ml_service/test_emotion35_weak_masks.py` — **8 tests passed**:

- positive-only rows cannot observe all taxonomy labels;
- legacy all-taxonomy masks are repaired;
- explicit contrast negatives are retained;
- invalid labels fail closed;
- positives outside the mask fail closed;
- unjustified negatives fail closed;
- cross-split duplicate text fails closed.

The loss preflight also passed: unknown labels do not affect loss, while an
explicit negative does penalize a false positive. Python compilation passed for
all changed preparation, training, and evaluation scripts.

## Controlled CUDA recovery runs

Environment: `.venv\\Scripts\\python.exe`, PyTorch 2.14.1+cu126,
`torch.cuda.is_available() = True`, NVIDIA RTX 3050 6 GB, peak allocation about
1.34 GB. Both runs were 0.25 epoch, batch 8, learning rate 2e-5, seed
20261103, initialized from the preserved v2 checkpoint, with validation-only
threshold calibration.

### Corrected weak-data run

Checkpoint:
`storage/app/audit-recovery/emotion_model_35_multilabel_recovery_masks_fixed_norms_20261103`

- CUDA training completed; wall time 124.8 s.
- Masked public-source test micro-F1: **0.7424**; macro-F1: **0.8356**.
- Language slices on the same masked test were English 0.7346 (3,029 rows),
  Filipino 1.0000 (42 rows), and Taglish 1.0000 (42 rows). The Filipino and
  Taglish figures are not credible recognition evidence here: those slices are
  tiny, partially observed, and the model accepts many unrelated labels.
- These are not full 35-label accuracy because the test mask is partial and has
  zero fully observed records.
- Calibrated cardinality: mean **17.94**, max **35**; 3,099/3,113 test rows
  accepted 10+ labels, 1,273 accepted 20+, and 72 accepted all 35.
- At a uniform 0.5 threshold, the same checkpoint still had mean 2.49 labels,
  109 rows with 10+, 90 with 20+, and 72 with all 35. This confirms a learned
  false-positive collapse in addition to an overly permissive rare-label
  calibration, not merely a display bug.

### No-weak training control

Checkpoint:
`storage/app/audit-recovery/emotion_model_35_multilabel_recovery_no_weak_20261103`

- CUDA training completed; wall time 143.7 s.
- Masked public-source test micro-F1: **0.7429**; macro-F1: **0.8175**.
- Language slices were English 0.7397 (3,029 rows), Filipino 0.7595 (42 rows),
  and Taglish 0.9792 (42 rows). The lower cardinality than the weak-data run is
  still unusable (10+ labels on every calibrated test row).
- Calibrated cardinality: mean **20.56**, max **23**; all 3,113 rows accepted
  10+ labels and 2,613 accepted 20+.

The no-weak control did not restore usable cardinality. The corrected objective
and masks are necessary but not sufficient; the present short run and
validation calibration still produce excessive positives. The weak data cannot
be credited with a genuine improvement.

## Fresh synthetic diagnostic cases

The candidate recovery model accepted all 35 labels for neutral Filipino,
Filipino stress, Filipino sadness, Filipino happiness, guilt, and mixed
excitement/anxiety. It accepted 18 labels for neutral English and 22 for
homesickness. The preserved v2 baseline accepted 0–4 labels on these cases
(with some omissions and related-label false positives), but did not show the
10+ or all-35 collapse on the same corrected test set.

These cases are synthetic diagnostics only; they are not real-student accuracy
evidence.

## Decision

**Do not promote either recovery checkpoint.** Keep v2 as the default adviser
comparison, keep v4 quarantined, and leave the new checkpoints outside the
adviser demo. The next engineering work should target objective/cardinality
control and calibration on a separately held-out, fully annotated diagnostic
set before another training run. No production activation is justified by this
experiment.
