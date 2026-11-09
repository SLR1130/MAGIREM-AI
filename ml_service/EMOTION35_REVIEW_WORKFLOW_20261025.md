# Emotion35 review workflow and EmpatheticDialogues mapping (2026-10-25)

Taxonomy version: 1.0.0. This is a private research-review artifact; no row is training truth until human acceptance.

## Current data gates

- GoEmotions: 29,994 prepared records; 15 approved targets have directly mapped, complete source annotations.
- EmpatheticDialogues: 23,149 deduplicated situation prompts (99,646 source utterances), split train 17,844 / validation 2,763 / test 2,542; 32 contexts; zero cross-split conversation overlap. Every candidate is review-required because `context` describes a situation, not every utterance.
- EmpatheticDialogues proposed targets: 24; 11 overlap GoEmotions; the seven gaps with no source candidate are `calmness`, `hopelessness`, `rejection_emotional_hurt`, `stress`, `overwhelm`, `insecurity_self_doubt`, and `boredom`.
- Multilingual template seed: 60 rows (20 labels × English/Filipino/Taglish), all unreviewed. Three examples per target is a review seed only, not model coverage.

## Exact source-context mapping

| EmpatheticDialogues context | Proposed target(s) | Candidate rows | Status |
| afraid | fear | 750 | semantic_review_required |
| angry | anger | 805 | exact_candidate_needs_review |
| annoyed | frustration | 824 | semantic_review_required |
| anticipating | anticipation | 709 | exact_candidate_needs_review |
| anxious | anxiety | 732 | exact_candidate_needs_review |
| apprehensive | anxiety, fear | 566 | semantic_review_required |
| ashamed | shame | 579 | exact_candidate_needs_review |
| caring | compassion_empathy | 632 | semantic_review_required |
| confident | confidence | 737 | exact_candidate_needs_review |
| content | contentment | 680 | exact_candidate_needs_review |
| devastated | grief, sadness | 658 | semantic_review_required |
| disappointed | disappointment | 722 | exact_candidate_needs_review |
| disgusted | disgust | 737 | exact_candidate_needs_review |
| embarrassed | shame | 692 | semantic_review_required |
| excited | excitement | 874 | exact_candidate_needs_review |
| faithful | hope_optimism, confidence | 426 | semantic_review_required |
| furious | anger | 697 | semantic_review_required |
| grateful | gratitude | 757 | exact_candidate_needs_review |
| guilty | guilt | 710 | exact_candidate_needs_review |
| hopeful | hope_optimism | 724 | exact_candidate_needs_review |
| impressed | pride, confidence | 748 | semantic_review_required |
| jealous | jealousy_envy | 706 | exact_candidate_needs_review |
| joyful | happiness_joy | 725 | exact_candidate_needs_review |
| lonely | loneliness | 759 | exact_candidate_needs_review |
| nostalgic | homesickness, love_affection | 702 | semantic_review_required |
| prepared | anticipation, confidence | 712 | semantic_review_required |
| proud | pride | 819 | exact_candidate_needs_review |
| sad | sadness | 798 | exact_candidate_needs_review |
| sentimental | love_affection | 646 | semantic_review_required |
| surprised | surprise | 1200 | exact_candidate_needs_review |
| terrified | fear | 732 | semantic_review_required |
| trusting | confidence | 591 | semantic_review_required |
Exact lexical mappings remain review-required for training because they are situation-level labels. Semantic/intensity mappings are proposed only and must be corrected, rejected, or marked ambiguous by a reviewer.

## Human-review workflow

`storage/app/datasets/emotion35_review_workflow/emotion35_review_master.csv` contains 23,209 private rows. `review_batch_001.csv` contains 220 rows: five situation prompts per each of 32 contexts plus all 60 multilingual synthetic seeds. `build_emotion35_review_sheet.py` creates the sheet and manifest; `import_emotion35_review_sheet.py` fails closed unless `review_status=reviewed`, `reviewer_decision=accepted`, taxonomy-valid `reviewed_labels`, and explicit `reviewed_observed_labels` are present.

Recommended first pass: review all 220 rows, then expand to at least 30 accepted examples per uncovered target (at least 210 target decisions; two independent reviewers would be 420 decisions) before treating a class as research-trainable. The current seed is not sufficient for accuracy claims.

License/provenance: EmpatheticDialogues CC BY-NC-4.0; GoEmotions Apache-2.0 per dataset cards. Keep outputs private, retain source split/group IDs, and do not expose them through Laravel.
