# Emotion35 review import procedure

The importer accepts one CSV at a time and writes a complete private output
directory. To avoid replacing earlier accepted reviews, the team should first
consolidate the completed sheets into one CSV with the same header as
`review_batch_001.csv` (append rows, preserve unique `review_id` values), then
run:

```powershell
python ml_service/import_emotion35_review_sheet.py `
  --input storage/app/datasets/emotion35_review_workflow/reviewed_combined.csv `
  --output-dir storage/app/datasets/emotion35_review_workflow/reviewed_import
```

The importer output is regenerated atomically by the command. Keep the input
CSV and its SHA-256 hash with the review record. Do not manually edit the
generated JSONL. Accepted rows must contain exact taxonomy keys, an explicit
`reviewed_observed_labels` mask, a source group, reviewer ID, and timestamp.
Rows marked unreviewed, rejected, ambiguous, or needs_correction are retained
only in the report and never enter train/validation/test.

After import, run `python ml_service/merge_emotion35_sources.py`, then the
`--audit-only` command in `EMOTION35_FINAL_DATA_HANDOFF_20261025.md`. The
progress workbook and `coverage_report.json` are the authoritative accepted
counts. A zero accepted-review count is not training-ready even if proposed
candidate counts are large.
