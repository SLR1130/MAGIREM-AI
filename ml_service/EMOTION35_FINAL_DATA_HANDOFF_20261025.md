# Emotion35 data handoff (human-review boundary)

This is the final data-preparation handoff. It does **not** claim training
readiness: the accepted human-reviewed count is currently **0**. The production
six-class checkpoint is unchanged.

## Files for the review team

Private files under `storage/app/datasets/emotion35_review_workflow/`:

* `review_batch_001.csv` — first mixed EmpatheticDialogues/synthetic batch (220 rows).
* `review_batch_gap_001.csv` — first reserve for the seven direct-coverage gaps.
* `review_batch_gap_002.csv` — 210 additional candidates (30 per gap; proposed 20 train / 5 validation / 5 test, with English, Filipino, and Taglish). Every row is `unreviewed`.
* `emotion35_review_progress.csv` — Excel-friendly all-35-label progress workbook.
* `review_plan.json` and `review_batch_gap_002.csv.manifest.json` — targets, split plan, and hashes.

The seven priority gaps are `calmness`, `hopelessness`,
`rejection_emotional_hurt`, `stress`, `overwhelm`, `insecurity_self_doubt`,
and `boredom`. The progress workbook also identifies other labels whose
train/validation/test accepted counts are below 20/5/5.

## Reviewer procedure

1. Open a CSV in Excel or LibreOffice; do not edit the source JSONL or code.
2. Keep `review_status=unreviewed` until the row has been assessed.
3. Set `review_status=reviewed` and choose `reviewer_decision`:
   `accepted`, `rejected`, `ambiguous`, or `needs_correction`.
4. For `accepted`, put one or more approved taxonomy keys in
   `reviewed_labels` separated by semicolons. Put every label whose presence
   was actually assessed in `reviewed_observed_labels`; omitted labels remain
   unknown and are not negative examples.
5. Check who experiences the emotion. Reject or mark ambiguous hypothetical,
   quoted, and third-person statements that do not describe the student.
6. Record `reviewer_id` and an ISO timestamp in `reviewed_at`. Rejected rows
   need `rejection_reason`; ambiguous rows need `ambiguity_notes`.
7. A second reviewer should independently review accepted rows. Resolve
   disagreements in a separate adjudication pass; do not overwrite provenance.

Accepted labels must be exact keys from `config/emotion_taxonomy.json`. The
importer refuses unknown keys, missing masks, split/group conflicts, and
incomplete decisions. Proposed labels and proposed splits are not ground truth.

## Import completed reviews

```powershell
python ml_service/import_emotion35_review_sheet.py `
  --input storage/app/datasets/emotion35_review_workflow/review_batch_gap_002.csv `
  --output-dir storage/app/datasets/emotion35_review_workflow/reviewed_import
```

Run the same command for each completed sheet (use a reviewed combined CSV or
an output directory after the team consolidates sheets). The importer writes
private `train.jsonl`, `validation.jsonl`, `test.jsonl`, `review_only.jsonl`,
`provenance.json`, and `import_report.json`.

## Combine and audit

```powershell
python ml_service/merge_emotion35_sources.py
python ml_service/train_emotion_35_multilabel.py --audit-only `
  --dataset-dir storage/app/datasets/emotion35_merged_training `
  --provenance storage/app/datasets/emotion35_merged_training/provenance.json `
  --min-train-per-label 20 --min-validation-per-label 5 --min-test-per-label 5
```

The merge loads compatible GoEmotions rows and accepted review-import rows
only. It retains `source_dataset`, `source_id`, `group_id`, language, source
split, licensing/provenance, `labels`, and `observed_labels`. It refuses invalid
masks and split leakage and writes `coverage_report.json`. A non-zero audit
exit is expected until every label has accepted 20/5/5 coverage.

## Training command (only after a zero-blocker audit)

```powershell
python ml_service/train_emotion_35_multilabel.py --train `
  --dataset-dir storage/app/datasets/emotion35_merged_training `
  --provenance storage/app/datasets/emotion35_merged_training/provenance.json `
  --init-model ml_service/emotion_model_multilingual_deployable `
  --output-dir ml_service/emotion_model_35_multilabel_reviewed `
  --min-train-per-label 20 --min-validation-per-label 5 --min-test-per-label 5
```

Before any training, verify the separate head in memory only:

```powershell
python ml_service/verify_emotion35_head.py
```

This must report an original six-label head, a candidate 35-label head, and
unchanged encoder weights. It writes no checkpoint. Do not replace or modify
the deployed six-class model.
