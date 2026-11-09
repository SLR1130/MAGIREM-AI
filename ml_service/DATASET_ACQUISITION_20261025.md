# 35-emotion data acquisition and training readiness (2026-10-25)

## Sources checked

### GoEmotions

Source: <https://huggingface.co/datasets/google-research-datasets/go_emotions>

The dataset card describes 58k English Reddit comments, multilabel emotion
annotations, and predefined train/validation/test splits of 43,410 / 5,426 /
5,427 records. It lists Apache-2.0 for the repository. The simplified
configuration used here contains text, label IDs, and IDs; the raw
configuration can include Reddit usernames, so only the simplified data was
cached and the prepared output remains under private `storage/app/datasets`.

### Filipino/English tweets (Lapitan et al., W16-3708)

Reference: <https://aclanthology.org/W16-3708/>

The paper reports 1,146 manually annotated Filipino/English tweets. The ACL
page provides the publication and abstract but no directly downloadable corpus
or explicit dataset license/redistribution terms. It was **not downloaded**.
No training use is claimed until the authors' data-access and licensing terms
are confirmed.

## Mapping policy

The approved taxonomy is version `1.0.0` with 35 keys. GoEmotions labels were
handled as follows:

* 14 exact labels were mapped directly (`anger`, `confusion`, `curiosity`,
  `disappointment`, `disgust`, `excitement`, `fear`, `gratitude`, `grief`,
  `neutral`, `pride`, `relief`, `sadness`, `surprise`).
* `joy -> happiness_joy` is the single explicit compatibility mapping because
  the approved taxonomy defines `happiness_joy` and lists `joy` as an alias.
* `admiration`, `amusement`, `annoyance`, `approval`, `caring`, `desire`,
  `disapproval`, `embarrassment`, `love`, `nervousness`, `optimism`,
  `realization`, and `remorse` require semantic review. They were excluded,
  not silently converted to plausible labels.
* Twenty approved labels remain uncovered: anticipation, anxiety, boredom,
  calmness, compassion_empathy, confidence, contentment, frustration, guilt,
  homesickness, hope_optimism, hopelessness, insecurity_self_doubt,
  jealousy_envy, loneliness, love_affection, overwhelm,
  rejection_emotional_hurt, shame, and stress.

## Prepared artifact

`storage/app/datasets/emotion35_go_emotions_approved/` contains 29,994
deduplicated English records:

| split | records |
|---|---:|
| train | 24,044 |
| validation | 2,925 |
| test | 3,025 |

The source mapping report, provenance, original labels, source IDs, and mapping
kind are retained. Exact normalized duplicates were removed deterministically
with train > validation > test precedence; no cross-split duplicates remain.

`storage/app/datasets/emotion35_annotation_template_20261025.jsonl` contains
60 synthetic English/Filipino/Taglish examples for the 20 missing labels. Every
row is marked `synthetic: true`, `review_status: needs_human_review`, and
`reviewer_count: 0`; it is not training data.

## Training decision

The existing trainer audit still refuses training because 20 labels have zero
examples and the prepared source is English-only. No checkpoint was produced,
and no production classifier or activation flag was changed. A reviewed
Filipino/Taglish corpus and qualified annotations for the missing labels are
required before a 35-output multilabel head can be trained or evaluated.

### EmpatheticDialogues (inspected, review-only)

Source: <https://huggingface.co/datasets/facebook/empathetic_dialogues>

The dataset card lists 99,646 English utterances (76,673 train, 12,030
validation, 10,943 test), 32 `context` situations, and a CC BY-NC-4.0
license. The original loader references the Facebook/ParlAI archive. The
source splits contain 23,149 conversation IDs with no cross-split conversation
ID overlap. Because `context` describes the situation that prompted a dialogue,
it is not an utterance-level ground-truth label. The prepared candidates retain
`conversation_id`, `speaker_idx`-safe provenance, context, and split, and are
marked `needs_human_review` and `training_eligible: false`. The non-commercial
license also keeps this source out of product training until usage is approved.

### SemEval-2018 Affect in Tweets (investigated, not acquired)

Reference: <https://aclanthology.org/S18-1001/>

The task covers manually annotated English, Arabic, and Spanish tweets for
emotion/intensity/valence and is not Filipino/Taglish. The official researcher
resource states that its data are free for research only, must not be
redistributed, and commercial use requires permission. It is therefore not
copied into this project or used for training. It would also require a separate
review because its intensity/coarse labels do not directly equal the approved
35 emotions.

## Source-aware masking

Prepared GoEmotions rows now include `observed_labels`: the 15 mapped source
labels are supervised; the other 20 approved taxonomy labels are masked rather
than treated as negative examples. The isolated trainer supports a masked BCE
loss and an explicit `--research-only-partial` mode. The default full-coverage
training path remains fail-closed. Any partial checkpoint is research-only and
separate from the six-class production model.

## Feasibility run (research-only)

A 67-record stratified probe (22 train / 22 validation / 23 test, one example
per each of the 15 observed labels where available) trained for 0.2 epochs on
the local CPU-only PyTorch environment. The run completed and wrote the
separate private checkpoint under
`storage/app/audit-recovery/emotion_model_35_multilabel_feasibility_20261025/`.
Masked evaluation reported 0.6145 micro-F1 and 0.1135 macro-F1 over 345 observed
target positions. These numbers are a hardware/pipeline smoke test, not an
emotion-quality result; the sample is intentionally too small and English-only
to support deployment. No production model or activation flag changed.

The annotation template has 60 candidates (20 English, 20 Filipino, 20 Taglish; one per missing target). All remain 
eeds_human_review with zero reviewers, so usable Filipino/Taglish coverage is still zero.
