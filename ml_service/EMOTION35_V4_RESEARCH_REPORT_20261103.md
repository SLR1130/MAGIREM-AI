# Emotion35 v4 weak-supervision research report

## Scope and safety

This is an isolated research checkpoint. It is not connected to Student Chat,
Admin Trends, crisis assessment, counselor escalation, or production student
records. The production six-class model is unchanged. The dataset contains no
human-reviewed rows; synthetic and weakly supervised rows are explicitly
marked as research-only.

## Dataset and supervision

- Dataset: \`storage/app/audit-recovery/emotion35_v4_weak_supervision_20261103\`
- Records: 31,467 (\`train\` 25,201; \`validation\` 3,083; \`test\` 3,183)
- Existing 304 AI-assisted candidates were retained as weak training evidence.
- Additional multilingual training-only contexts: 210, expanded to 534 weak
  rows (English 179, Filipino 177, Taglish 178 across generated, contrast, and
  mixed additions).
- Weak rows in training: 1,368. Positive-only rows mask unmentioned labels;
  only the 15 explicit contrast rows carry weak negative observations.
- Validation and test files were not augmented by v4.
- Audit passed with no invalid labels, missing provenance, split leakage, or
  duplicate text across splits.
- Twenty labels remain weak-only in the strong-source counts: calmness,
  contentment, hope_optimism, love_affection, confidence, loneliness,
  homesickness, hopelessness, rejection_emotional_hurt, anxiety, stress,
  overwhelm, frustration, jealousy_envy, shame, guilt, insecurity_self_doubt,
  boredom, compassion_empathy, and anticipation.

The loss is masked BCE. Unknown labels are excluded, while observed negatives
from the public source and explicitly documented contrast examples are
penalized. A preflight self-test passed for both behaviors. Language-aware
sampling used weights English 1.0, Filipino 12.0, and Taglish 10.0, with a
1.25 multiplier for weak rows.

## Training run

- Interpreter: \`.venv\Scripts\python.exe\`
- Device: CUDA, NVIDIA GeForce RTX 3050 6GB Laptop GPU
- Initialization: v3 research checkpoint (separate output)
- Epochs: 2; batch size 8; learning rate \`2e-5\`; seed \`20261103\`
- Training wall time: 1,176.98 seconds (19m37s)
- Peak allocated VRAM: 1,563,956,736 bytes (~1.46 GiB)
- Epoch 1: loss 0.1946; validation micro-F1 0.6493; macro-F1 0.3249
- Epoch 2: loss 0.1411; validation micro-F1 0.6474; macro-F1 0.3021

Checkpoint:
\`storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v4_weak_20261103\`

The run emitted a Transformers warning about legacy RoBERTa LayerNorm
\`gamma/beta\` keys when loading the v3 initializer. The v4 artifact is valid,
but this compatibility warning is a remaining quality risk and should be
resolved before another research run.

The warning is a legacy state-dict naming conversion: the checkpoint stores
LayerNorm parameters as beta/gamma, while the current model class names them
bias/weight. A direct load with output_loading_info=True reported zero missing,
unexpected, mismatched, or error keys for both v3 and v4. The v3/v4 state
dictionaries contain the same 105 keys and shapes (101 encoder keys); no
encoder tensor is exactly unchanged after training, as expected. The tokenizer
vocabulary is byte-identical and the model architecture and 35-label mapping
match. The v4 tokenizer config only adds truncation metadata. The warning did
not invalidate the checkpoint, but loader compatibility should be normalized
before future training.

## Provenance-separated held-out metrics

Unknown labels are excluded from the loss and metrics. The test split contains
2,988 public source rows (44,820 observed cells) and 195 unreviewed synthetic
rows (6,825 fully observed cells). The earlier aggregate 0.6992 v4 micro-F1
therefore mixes two different evidence types.

| evidence | v2 micro-F1 | v3 micro-F1 | v4 micro-F1 | v4 macro-F1 |
|---|---:|---:|---:|---:|
| public source rows only | 0.7329 | 0.7309 | **0.7372** | 0.2470 |
| synthetic diagnostic rows | 0.3691 | 0.3554 | 0.3391 | 0.3648 |

V4 public-source precision/recall were 0.7087/0.7682. Its synthetic diagnostic
precision/recall were 0.2453/0.5488, with exact-set 0.2923 (57/195) and sample
F1 0.4284. No rows were human-reviewed. These are research metrics, not
validated student recognition.

Selected v4 per-label results (test positive support):

| emotion | support | precision | recall | F1 |
|---|---:|---:|---:|---:|
| gratitude | 264 | 0.937 | 0.898 | 0.917 |
| neutral | 1,661 | 0.782 | 0.871 | 0.824 |
| fear | 78 | 0.709 | 0.718 | 0.713 |
| happiness_joy | 125 | 0.596 | 0.792 | 0.680 |
| pride | 15 | 0.636 | 0.467 | 0.538 |
| anxiety | 6 | 0.182 | 0.333 | 0.235 |
| overwhelm | 6 | 0.118 | 0.333 | 0.174 |
| shame | 6 | 0.056 | 0.167 | 0.083 |
| loneliness | 6 | 0.042 | 0.167 | 0.067 |
| guilt | 7 | 0.000 | 0.000 | 0.000 |
| boredom | 6 | 0.000 | 0.000 | 0.000 |

## Frozen synthetic holdout

The 47-case holdout was not used for training or threshold calibration. It is
synthetic adviser evidence only.

- Primary exact: **15/44 labelled cases (34.1%)**
- By language: English **6/14**, Filipino **4/14**, Taglish **5/16**
- Mixed cases: 7; label-level mixed precision **0.200**, recall **0.500**,
  F1 **0.286** (7 TP, 28 FP, 7 FN)
- Expected uncertain/no-emotion cases: 3; the model abstained on 1 of 3.
  It incorrectly returned \`neutral\` for the no-emotion case and \`confusion\`
  for one uncertain case.
- Nine of 47 cases produced no accepted detailed label, including several
  clear emotions (abstention is therefore not a reliable confidence signal).

The holdout has 47 raw records but only 44 label-eligible records. The excluded
IDs are ind-42 (uncertain), ind-43 (no emotion), and ind-46 (uncertain). V2,
v3, and v4 all use the same 47 case IDs. The holdout contains no real student
messages and is not professional ground truth.

## Adviser demo

The loopback-only demo now loads v2, v3, and v4 when their checkpoints exist.
The production six-class prediction remains separate and authoritative. The
local evaluation CSV was migrated to add \`v4_predictions\` without overwriting
prior records:

\`storage/app/audit-recovery/emotion35_adviser_evaluation.csv\`

Start it with:

\`\`\`powershell
.\.venv\Scripts\python.exe ml_service\emotion35_adviser_demo.py --host 127.0.0.1 --port 8765 --device cuda
\`\`\`

Open \`http://127.0.0.1:8765/\`. A real HTTP check returned 200 and displayed
the six-class model plus v2/v3/v4 sections. A synthetic evaluation submission
returned 303 and persisted a row containing \`v4_predictions\`. No production
database tables are used.

## Decision

The v4 run is a genuine GPU-trained prototype, but the all-positive/domain
collapse audit means it is **not** suitable as a valid adviser emotion list.
Use v2 as the default adviser comparison and retain v3/v4 only for diagnostic
inspection. V4 does **not** justify activation: its public-source micro-F1 is
slightly above v2 but its synthetic precision and multilingual behavior are
unsafe, mixed-emotion precision is low, and rare labels (especially guilt,
boredom, shame, and loneliness) are unreliable. Keep v4 isolated, correct the
inherited weak masks, and normalize LayerNorm aliases before any future run.

## Ranked quality-improvement backlog

1. **Rare-label supervision:** guilt, boredom, shame, loneliness, homesickness,
   stress, overwhelm, and anxiety have only about 5–7 public test positives.
   Add reviewed, source-separated multilingual examples before threshold tuning.
2. **Filipino coverage:** the public-source test rows are English-only; Filipino
   results here come from synthetic diagnostic rows. Natural Filipino wording
   remains unreliable (for example, `ind-27` shame was predicted as curiosity).
3. **Unrelated secondary predictions:** `ind-38` accepted nearly every label,
   while `ind-47` added contentment, loneliness, and jealousy to relief/hope.
   This is a supervision/threshold problem, not a LayerNorm problem.
4. **Mixed emotions:** v4 mixed-label precision/recall/F1 are .200/.500/.286,
   with both unsupported secondaries and omitted valid secondaries.
5. **Related-label boundaries:** guilt/shame, homesickness/loneliness,
   stress/overwhelm, and excitement/anticipation need hard-negative examples.
6. **Loader normalization:** explicitly convert legacy LayerNorm `beta/gamma`
   names to `bias/weight` before a future run and fail loudly on any remaining
   missing or unexpected backbone keys.

The record-level reconciliation is saved at
`storage/app/audit-recovery/emotion35_v4_diagnostic_reconciliation_20261103.json`.
The provenance-separated metrics are saved as
`evidence_split_metrics_20261103.json` in each v2, v3, and v4 checkpoint folder.
The reproducible checkpoint-loading and state-dictionary audit is saved at
`storage/app/audit-recovery/emotion35_v4_integrity_20261103.json`.

## V4 all-positive collapse audit

The raw checkpoint is not uniformly all-positive for every input, so the
failure is not a double-sigmoid or UI formatting bug. However, it is unsafe:
the fresh Filipino stress example
`Sobrang stress ako sa requirements at hindi ko alam kung alin ang uunahin.`
produced **35/35** accepted labels in v4. The held-out test cardinality audit
found v4 examples with up to 34 accepted labels, 14 records with 10 or more,
and 5 records with 20 or more. V2 and v3 did not produce any 20+ cardinality
examples on that split.

The direct inference path applies `sigmoid` once and uses the checkpoint's
validation thresholds. The loopback demo uses the same path; no inference
transformation explains the behavior.

The training-mask audit found a data-quality defect in the inherited weak
synthetic rows: 1,368 weak rows were present, but 834 of them had all 35
labels in `observed_labels` despite having only one or two proposed positive
labels. Those rows supplied 28,343 observed negatives that were not supported
by the weak-label provenance. The correctly constructed v4 additions use
positive-only masks (519 rows) or explicit contrast negatives (15 rows), but
the inherited rows contaminated the objective. Overall train cells were
26,780 positive, 360,459 observed negative, and 494,796 unknown; unknown cells
were masked, but the inherited all-label masks were themselves incorrect.

V4 is therefore quarantined as failed research evidence. It must not be
presented as a valid list of student emotions or used to justify activation.
The raw accepted labels remain available in the adviser demo under a clearly
marked diagnostic section.

All v2/v3/v4 checkpoints have finite tensors, 105 state-dictionary entries,
and 35-label RoBERTa classifier heads. An isolated loopback HTTP check of the
updated demo reproduced the direct result: the neutral test was quarantined as
v4 diagnostic output, and the Filipino stress test returned 35 accepted labels
with the explicit collapse warning. V2 remains the default research comparison.
During verification, port 8765 intermittently served a stale demo process, so
the corrected handler was verified on an isolated loopback port (8766). Stop
any stale process before using the normal 8765 command.
