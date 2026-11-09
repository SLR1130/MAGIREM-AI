# Phase 4 counselor-review guide (synthetic)

This guide accompanies `phase4-holdout-gemma-results.json`. It contains only
synthetic messages. Reviewers should confirm the expected status, attribution
scope, and allowed detailed labels before any shadow evaluation is considered.

## Structured statuses

- `clear`: one lived student emotion has sufficient evidence.
- `mixed`: two or more lived student emotions are simultaneously expressed;
  `primary_emotion` and `secondary_emotions` must be approved taxonomy keys.
- `uncertain`: the student may be describing a feeling, but evidence or
  wording is ambiguous. Labels may be null and this must not become Neutral by
  default.
- `no_emotion`: a neutral question or explicit absence of feeling, not a
  forced `neutral` emotion label.
- `attributed_other`: the emotion belongs to another person; the student's
  labels remain null and `other_emotions` may contain validated labels.

## Attribution scopes

- `student`: the student describes their own experience.
- `other`: only another person is experiencing the emotion.
- `both`: the student and another person have separate emotions; keep the
  labels in `primary_emotion`/`secondary_emotions` versus `other_emotions`.
- `hypothetical`: conditional or imagined emotion, not a lived student fact.
- `none`: no clearly attributable emotional experience.

## Representative holdout prompts for review

| Synthetic message | Expected interpretation |
| --- | --- |
| “Excited ako sa graduation pero kinakabahan ako sa future.” | `mixed`: excitement + anxiety, `student` |
| “I feel calm, but my sister is panicking about the results.” | `clear`: calmness, `both`; sister's anxiety is `other_emotions` |
| “If I failed the exam, I would probably feel ashamed.” | `uncertain`, `hypothetical`, no lived label |
| “My cousin says she is devastated, and I am asking how to help her.” | `attributed_other`, `other`, grief |
| “Parang okay lang ako, pero hindi ko ma-explain kung bakit.” | `uncertain`, nullable labels |
| “What does anxiety mean in a psychology class?” | `no_emotion`, `none` |

The review should record disagreements by message hash and should not copy
student conversations into the evaluation set. Disagreements must be resolved
before any detailed Gemma result is persisted or displayed in Admin analytics.
