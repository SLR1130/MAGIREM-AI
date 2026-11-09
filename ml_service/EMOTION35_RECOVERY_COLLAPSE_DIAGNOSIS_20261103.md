# Emotion35 recovery-collapse diagnosis — 2026-11-03

This is a read-only research diagnosis. No weights, thresholds, production six-class behavior, Student Chat, crisis handling, consent, or Admin analytics were changed.

## Frozen provisional diagnostic

Cases: **36**; SHA-256: `e52e2dd0b40cf58a76fa19946541e626ff60b1a8bd5568b72da0193f65e4c4cd`. The set is synthetic, fully observed for diagnostic purposes only, unreviewed, and was not used for training or threshold selection.

## Raw cardinality by checkpoint

| checkpoint | saved thresholds mean | 0.5 mean | 0.7 mean | 0.9 mean | saved 10+ | saved 20+ | saved all-35 |
|---|---:|---:|---:|---:|---:|---:|---:|
| v2 | 3.02 | 1.23 | 0.93 | 0.57 | 10 | 0 | 0 |
| recovery_weak | 17.94 | 2.49 | 1.91 | 1.46 | 3099 | 1273 | 72 |
| recovery_no_weak | 20.56 | 1.22 | 0.96 | 0.63 | 3113 | 2613 | 0 |

For the weak recovery, fixed thresholds expose a smaller but real raw-score problem: at 0.5 it still has 109/3,113 examples with 10+ labels and 72 examples with all 35. The no-weak control is normal at fixed thresholds (mean 1.22 at 0.5; no 10+ cases), so its 20.56 saved-threshold mean is primarily calibration-driven.

## Calibration evidence

Labels with fewer than five observed positives or negatives are marked `deployment_evidence_sufficient=false`; their thresholds are diagnostic only. Unknown labels were excluded from all support and score calculations.
- **v2:** 0 labels lack deployment-style observed support; saved threshold <=0.1 for 0 labels (none).
- **recovery_weak:** 20 labels lack deployment-style observed support; saved threshold <=0.1 for 20 labels (calmness, contentment, hope_optimism, love_affection, confidence, loneliness, homesickness, hopelessness, rejection_emotional_hurt, anxiety, stress, overwhelm, frustration, jealousy_envy, shame, guilt, insecurity_self_doubt, boredom, compassion_empathy, anticipation).
- **recovery_no_weak:** 20 labels lack deployment-style observed support; saved threshold <=0.1 for 20 labels (calmness, contentment, hope_optimism, love_affection, confidence, loneliness, homesickness, hopelessness, rejection_emotional_hurt, anxiety, stress, overwhelm, frustration, jealousy_envy, shame, guilt, insecurity_self_doubt, boredom, compassion_empathy, anticipation).

## Fully observed provisional diagnostic

| checkpoint | micro-F1 | macro-F1 | mean accepted | 10+ | 20+ | all-35 |
|---|---:|---:|---:|---:|---:|---:|
| v2 | 0.3546 | 0.4478 | 2.61 | 0 | 0 | 0 |
| recovery_weak | 0.0930 | 0.1085 | 26.78 | 36 | 35 | 12 |
| recovery_no_weak | 0.1065 | 0.3360 | 20.61 | 36 | 32 | 0 |

### Diagnostic-only recalibration with an evidence gate

The following uses validation data only, disables labels with fewer than 5 observed positives or 5 observed negatives, and does not modify saved thresholds or checkpoint weights.

| checkpoint | enabled labels | test mean accepted | diagnostic micro-F1 | diagnostic mean accepted | diagnostic 10+ |
|---|---:|---:|---:|---:|---:|
| v2 | 35 | 3.02 | 0.3546 | 2.61 | 0 |
| recovery_weak | 15 | 1.49 | 0.1497 | 6.86 | 14 |
| recovery_no_weak | 15 | 1.18 | 0.4146 | 0.97 | 0 |

### Diagnostic language slices

| checkpoint | language | n | micro-F1 | mean accepted | 10+ | all-35 |
|---|---|---:|---:|---:|---:|---:|
| v2 | ENGLISH | 24 | 0.3932 | 3.46 | 0 | 0 |
| v2 | FILIPINO | 7 | 0.0000 | 0.00 | 0 | 0 |
| v2 | TAGLISH | 5 | 0.2353 | 2.20 | 0 | 0 |
| recovery_weak | ENGLISH | 24 | 0.1176 | 22.67 | 24 | 0 |
| recovery_weak | FILIPINO | 7 | 0.0556 | 35.00 | 7 | 7 |
| recovery_weak | TAGLISH | 5 | 0.0663 | 35.00 | 5 | 5 |
| recovery_no_weak | ENGLISH | 24 | 0.1257 | 21.12 | 24 | 0 |
| recovery_no_weak | FILIPINO | 7 | 0.0426 | 19.14 | 7 | 0 |
| recovery_no_weak | TAGLISH | 5 | 0.0935 | 20.20 | 5 | 0 |

These labels are provisional synthetic annotations, not validated real-student ground truth.

## Training-change evidence

- **v2:** {'epochs': 2.0, 'learning_rate': 2e-05, 'batch_size': 8, 'masked_loss': True, 'positive_weighting': 'sqrt_observed_neg_over_pos_capped', 'sampling_weights': 'not configured', 'weak_loss_weight': 0.0}
- **recovery_weak:** {'epochs': 0.25, 'learning_rate': 2e-05, 'batch_size': 8, 'masked_loss': True, 'positive_weighting': 'max_pos_weight=6.0', 'sampling_weights': {'ENGLISH': 1.0, 'FILIPINO': 12.0, 'TAGLISH': 10.0}, 'weak_loss_weight': 0.35}
- **recovery_no_weak:** {'epochs': 0.25, 'learning_rate': 2e-05, 'batch_size': 8, 'masked_loss': True, 'positive_weighting': 'max_pos_weight=6.0', 'sampling_weights': {'ENGLISH': 1.0, 'FILIPINO': 12.0, 'TAGLISH': 10.0}, 'weak_loss_weight': 0.35}

Parameter movement from v2:

- **recovery_weak_vs_v2:** L2=2.332157, mean absolute update=0.00015957, max absolute update=0.003326; 82,144,263/82,145,315 parameters changed.
- **recovery_no_weak_vs_v2:** L2=1.933756, mean absolute update=0.00013624, max absolute update=0.003524; 82,144,208/82,145,315 parameters changed.

## Decision

Calibration is a primary cause for the no-weak control, while the weak recovery also has a measurable raw-score shift. The change from v2 combines a new 0.25-epoch update, max-positive weighting, and 12x/10x Filipino/Taglish sampling; the weak run additionally applies weak loss. The justified next action is to retain an evidence-gated, diagnostic-only calibration artifact and abstain on labels without observed negatives; do not overwrite saved thresholds or retrain yet. The weak recovery still has raw all-35 cases at fixed thresholds, so calibration alone cannot make it safe. Keep v2 as the research-demo baseline and keep both recovery checkpoints isolated.
