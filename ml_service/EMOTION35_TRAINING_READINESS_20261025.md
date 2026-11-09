# Emotion35 training readiness and next command

No human-reviewed rows are present yet. The current import report is therefore
expected to show `import_ready=false` and `training_ready=false`.

## Reviewer handoff

1. Open `storage/app/datasets/emotion35_review_workflow/review_batch_001.csv`
   and `review_batch_gap_001.csv` in a spreadsheet editor.
2. Apply the instructions in
   `ml_service/EMOTION35_REVIEWER_INSTRUCTIONS_20261025.md`.
3. Save the completed, combined sheet as
   `storage/app/datasets/emotion35_review_workflow/emotion35_review_completed.csv`.
4. Keep EmpatheticDialogues conversation groups in their original split. Keep
   synthetic candidates as `review_only` until an adjudicator assigns disjoint
   train/validation/test groups; never assign the same group to two splits.

## Import and coverage audit

```powershell
python ml_service/import_emotion35_review_sheet.py `
  --input storage/app/datasets/emotion35_review_workflow/emotion35_review_completed.csv `
  --output-dir storage/app/datasets/emotion35_review_workflow/reviewed_import
```

Inspect `reviewed_import/import_report.json`. It reports accepted counts by
emotion, language, and split. The import is not training-ready until every
approved emotion has at least 30 accepted examples, each required split has
coverage, no group/text leakage exists, and no row remains only in
`review_only`.

Run the existing audit before training:

```powershell
python ml_service/train_emotion_35_multilabel.py --audit-only `
  --dataset-dir storage/app/datasets/emotion35_review_workflow/reviewed_import `
  --provenance storage/app/datasets/emotion35_review_workflow/reviewed_import/provenance.json `
  --min-train-per-label 20 --min-validation-per-label 5 --min-test-per-label 5
```

The command must exit successfully and report no blockers before a full run.

## Full research training command

Only after the audit passes, train a separate research artifact:

```powershell
python ml_service/train_emotion_35_multilabel.py --train `
  --dataset-dir storage/app/datasets/emotion35_review_workflow/reviewed_import `
  --output-dir storage/app/audit-recovery/emotion_model_35_multilabel_reviewed_20261025 `
  --init-model ml_service/emotion_model_multilingual_deployable `
  --provenance storage/app/datasets/emotion35_review_workflow/reviewed_import/provenance.json `
  --min-train-per-label 20 --min-validation-per-label 5 --min-test-per-label 5 `
  --epochs 3
```

Do not add `--research-only-partial` for the intended full model. That flag is
reserved for an explicitly partial research probe and must not be presented as
complete 35-emotion coverage. The production six-class checkpoint remains
untouched.
