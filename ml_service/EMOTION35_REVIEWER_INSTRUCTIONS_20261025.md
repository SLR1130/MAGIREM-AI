# Emotion35 annotation reviewer instructions

Use the CSV in Excel, LibreOffice, or Google Sheets. Do not edit the raw JSONL
files. Work on a copy and preserve the `review_id`, `group_id`, `source_split`,
source text, provenance, and license columns.

## What is being labeled

Label the emotion actually expressed by the speaker in the text. Do not label
an emotion merely because a word appears, because it would be plausible in the
situation, or because the source dataset proposed it. A source `context` is a
situation-level hint, not proof that every utterance expresses that emotion.

For each row, decide whether the text expresses:

- one clear emotion;
- multiple emotions the speaker is experiencing;
- an ambiguous or insufficiently supported state;
- no specific emotion; or
- another person's, hypothetical, or quoted emotion rather than the student's.

## Fields and allowed values

`review_status`:

- `unreviewed` â€” untouched (the default);
- `reviewed` â€” a reviewer has completed the row.

`reviewer_decision`:

- `accepted` â€” the reviewed labels are supported by the text;
- `rejected` â€” unsuitable, not attributable, or not usable as training evidence;
- `ambiguous` â€” meaning or attribution is genuinely unclear;
- `needs_correction` â€” return for another reviewer;
- blank â€” leave blank only while `review_status=unreviewed`.

`reviewed_labels`: semicolon-separated approved taxonomy keys, for example
`overwhelm;anxiety`. Use one key for a clear single emotion. Leave blank for a
rejected, ambiguous, no-emotion, third-person-only, or hypothetical-only row.

`reviewed_observed_labels`: semicolon-separated approved keys for labels that
were actually observed or adjudicated in this text. It is an annotation mask,
not a list of all emotions that might be absent. Every `reviewed_labels` value
must also appear here; other unannotated emotions remain unknown rather than
negative. For a single accepted label, enter that label in both fields.

`rejection_reason`: required for rejected or unsuitable examples.

`ambiguity_notes`: explain uncertainty, mixed evidence, third-person attribution,
hypothetical wording, sarcasm, or why no emotion can be established.

`reviewer_id` and `reviewed_at`: required for an accepted row. Use a review-team
identifier and an ISO-8601 timestamp; do not enter student identities.

`language`: preserve `ENGLISH`, `FILIPINO`, or `TAGLISH` for synthetic rows.

## Examples

| Text | Decision | Reviewed labels | Observed labels | Reason |
|---|---|---|---|---|
| â€œSabay-sabay ang deadline kaya hindi ko alam ang uunahin.â€ | accepted | `overwhelm` | `overwhelm` | The speaker describes cognitive overload. |
| â€œExcited ako sa event pero overwhelmed sa paghahanda.â€ | accepted | `overwhelm;excitement` | `overwhelm;excitement` | Both feelings belong to the speaker. |
| â€œMasaya siya sa resulta; ako naman ay wala pang malinaw na nararamdaman.â€ | ambiguous | blank | blank | Emotion belongs to another person; student's state is unclear. |
| â€œIf I fail, I might feel ashamed.â€ | ambiguous | blank | blank | Hypothetical, not an experienced emotion. |
| "Wala naman akong partikular na nararamdaman ngayon." | ambiguous | blank | blank | No specific emotion is established; do not force `neutral`. |
| â€œThe list keeps growing faster than I can finish anything.â€ | accepted | `overwhelm` | `overwhelm` | Indirect expression with sufficient contextual evidence. |

Do not force `neutral` when the message is uncertain, numb, or emotionally
ambiguous. Use `ambiguous` and document the reason unless a reviewed protocol
explicitly supports a neutral label.

## Disagreement process

1. Reviewer A completes the row independently.
2. Reviewer B reviews the text without relying on A's proposed label.
3. If labels differ, record `reviewer_decision=needs_correction` or
   `ambiguous`, document the disagreement, and have a designated adjudicator
   resolve it.
4. Only the adjudicated decision should be changed to `accepted`.
5. Keep the original source split and `group_id`; never move a conversation
   between train, validation, and test during review.

The importer rejects unknown taxonomy keys, missing observed masks, missing
review provenance, and split/group conflicts. It never imports unreviewed,
rejected, or ambiguous rows as training data. A row being accepted by the
importer does not by itself establish sufficient class coverage for training.

