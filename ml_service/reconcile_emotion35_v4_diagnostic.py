"""Reconcile record eligibility and model-comparison counts for the frozen
Emotion35 diagnostic benchmark.

This is reporting-only. It does not alter expected labels or model outputs and
does not tune any checkpoint.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CASES = ROOT / "storage/app/audit-recovery/emotion35_independent_eval_20261103.json"
DEFAULT_OUTPUT = ROOT / "storage/app/audit-recovery/emotion35_v4_diagnostic_reconciliation_20261103.json"
TAXONOMY = ROOT / "config/emotion_taxonomy.json"


def load_cases(path: Path) -> list[dict]:
    return json.loads(path.read_text(encoding="utf-8"))


def load_prediction_cases(path: Path) -> dict[str, dict]:
    data = json.loads(path.read_text(encoding="utf-8"))
    return {row["case_id"]: row for row in data["cases"]}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--cases", type=Path, default=DEFAULT_CASES)
    parser.add_argument(
        "--v2",
        type=Path,
        default=ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v2_20261026/independent_eval_20261103.json",
    )
    parser.add_argument(
        "--v3",
        type=Path,
        default=ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v3_20261026/independent_eval_20261103.json",
    )
    parser.add_argument(
        "--v4",
        type=Path,
        default=ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v4_weak_20261103/independent_eval_20261103.json",
    )
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()

    taxonomy = json.loads(TAXONOMY.read_text(encoding="utf-8"))
    categories = {
        item["key"]: item.get("category_key", "unclassified")
        for item in taxonomy["emotions"]
    }
    source_cases = load_cases(args.cases)
    predictions = {
        name: load_prediction_cases(path)
        for name, path in (("v2", args.v2), ("v3", args.v3), ("v4", args.v4))
        if path.exists()
    }
    source_ids = [row["case_id"] for row in source_cases]
    if any(set(source_ids) != set(rows) for rows in predictions.values()):
        raise SystemExit("Prediction case IDs do not match the 47-case source benchmark.")

    records = []
    for source in source_cases:
        case_id = source["case_id"]
        expected = list(source.get("expected_emotions") or [])
        eligible = bool(expected)
        record = {
            "case_id": case_id,
            "language": source["language"],
            "eligible_for_primary_exact": eligible,
            "expected_status": source.get("expected_status"),
            "expected_attribution": source.get("expected_attribution"),
            "expected_emotions": expected,
            "expected_categories": sorted({categories[label] for label in expected if label in categories}),
            "text": source["text"],
            "models": {},
        }
        for name, rows in predictions.items():
            row = rows[case_id]
            predicted = [item["emotion"] for item in row.get("accepted_detailed_emotions") or []]
            primary = predicted[0] if predicted else None
            record["models"][name] = {
                "accepted_emotions": predicted,
                "accepted_categories": sorted({categories[label] for label in predicted if label in categories}),
                "primary": primary,
                "primary_exact": bool(eligible and primary and primary == expected[0]),
                "exact_set": bool(sorted(predicted) == sorted(expected)),
                "abstained": not bool(predicted),
            }
        records.append(record)

    summary = {"raw_records": len(records), "eligible_records": sum(r["eligible_for_primary_exact"] for r in records)}
    for name in predictions:
        eligible = [r for r in records if r["eligible_for_primary_exact"]]
        summary[name] = {
            "case_ids_match_source": set(predictions[name]) == set(source_ids),
            "primary_exact": sum(r["models"][name]["primary_exact"] for r in eligible),
            "primary_denominator": len(eligible),
            "category_exact": sum(
                bool(r["models"][name]["accepted_categories"])
                and bool(r["expected_categories"])
                and r["models"][name]["accepted_categories"][0] == r["expected_categories"][0]
                for r in eligible
            ),
            "raw_abstentions": sum(r["models"][name]["abstained"] for r in records),
            "by_language": {},
        }
        for language in sorted({r["language"] for r in records}):
            language_rows = [r for r in eligible if r["language"] == language]
            summary[name]["by_language"][language] = {
                "eligible": len(language_rows),
                "primary_exact": sum(r["models"][name]["primary_exact"] for r in language_rows),
                "category_exact": sum(
                    bool(r["models"][name]["accepted_categories"])
                    and bool(r["expected_categories"])
                    and r["models"][name]["accepted_categories"][0] == r["expected_categories"][0]
                    for r in language_rows
                ),
                "raw_records": sum(r["language"] == language for r in records),
            }

    result = {
        "schema": "mindcare-emotion35-diagnostic-reconciliation-v1",
        "benchmark": str(args.cases),
        "eligibility_rule": "primary exact denominator excludes expected_status uncertain/no_emotion records with no expected_emotions",
        "excluded_case_ids": [r["case_id"] for r in records if not r["eligible_for_primary_exact"]],
        "summary": summary,
        "records": records,
        "warning": "Synthetic diagnostic evidence only; expected labels are unchanged and not professional ground truth.",
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"output": str(args.output), "raw_records": len(records), "eligible_records": summary["eligible_records"]}, indent=2))


if __name__ == "__main__":
    main()
