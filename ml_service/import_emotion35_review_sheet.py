"""Import only completed Emotion35 human reviews into private train-ready JSONL.

The importer is intentionally fail-closed: an accepted row needs a reviewed
label *and* an explicit observed-label mask.  Blank/unreviewed, rejected, and
ambiguous rows are reported but never become training data.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TAXONOMY = ROOT / "config" / "emotion_taxonomy.json"
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow" / "reviewed_import"
ALLOWED_SPLITS = {"train", "validation", "test", "review_only"}
ALLOWED_STATUSES = {"unreviewed", "reviewed"}
ALLOWED_DECISIONS = {"accepted", "rejected", "ambiguous", "needs_correction", ""}


def labels(value: str) -> list[str]:
    return sorted({item.strip() for item in str(value or "").replace(",", ";").split(";") if item.strip()})


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--taxonomy", type=Path, default=DEFAULT_TAXONOMY)
    args = parser.parse_args()
    taxonomy = json.loads(args.taxonomy.read_text(encoding="utf-8"))
    approved = {item["key"] for item in taxonomy["emotions"]}
    accepted: list[dict[str, Any]] = []
    report = Counter()
    groups: dict[str, set[str]] = defaultdict(set)
    errors: list[dict[str, Any]] = []

    with args.input.open("r", encoding="utf-8-sig", newline="") as handle:
        for line_no, row in enumerate(csv.DictReader(handle), start=2):
            report["rows_seen"] += 1
            status = str(row.get("review_status") or "").strip().casefold()
            decision = str(row.get("reviewer_decision") or "").strip().casefold()
            if status not in ALLOWED_STATUSES:
                errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": ["invalid review_status"]})
                report["invalid_review_status"] += 1
                continue
            if status == "reviewed" and not decision:
                errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": ["reviewer_decision required for reviewed row"]})
                report["reviewed_rows_missing_decision"] += 1
                continue
            if decision not in ALLOWED_DECISIONS:
                errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": ["invalid reviewer_decision"]})
                report["invalid_reviewer_decision"] += 1
                continue
            if status == "reviewed" and decision in {"rejected", "ambiguous", "needs_correction"}:
                if decision == "ambiguous" and not str(row.get("ambiguity_notes") or "").strip():
                    errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": ["ambiguity_notes required"]})
                    report["invalid_ambiguous_rows"] += 1
                    continue
                if decision in {"rejected", "needs_correction"} and not str(row.get("rejection_reason") or "").strip():
                    errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": ["rejection_reason required"]})
                    report["invalid_rejected_rows"] += 1
                    continue
            if status != "reviewed" or decision != "accepted":
                report[f"not_imported_{status or 'blank'}_{decision or 'blank'}"] += 1
                continue
            row_errors: list[str] = []
            split = str(row.get("source_split") or "").strip().casefold()
            target = labels(row.get("reviewed_labels"))
            observed = labels(row.get("reviewed_observed_labels"))
            group = str(row.get("group_id") or "").strip()
            if split not in ALLOWED_SPLITS:
                row_errors.append("invalid source_split")
            if not row.get("text", "").strip():
                row_errors.append("empty text")
            if not target:
                row_errors.append("reviewed_labels required")
            if not observed:
                row_errors.append("reviewed_observed_labels required for masked loss")
            if not set(target).issubset(approved):
                row_errors.append("reviewed_labels contains non-taxonomy key")
            if not set(observed).issubset(approved):
                row_errors.append("reviewed_observed_labels contains non-taxonomy key")
            if not set(target).issubset(observed):
                row_errors.append("observed mask must include every reviewed label")
            if not group:
                row_errors.append("group_id required")
            if not str(row.get("reviewer_id") or "").strip():
                row_errors.append("reviewer_id required")
            if not str(row.get("reviewed_at") or "").strip():
                row_errors.append("reviewed_at required")
            if row_errors:
                errors.append({"line": line_no, "review_id": row.get("review_id", ""), "errors": row_errors})
                report["invalid_accepted_rows"] += 1
                continue
            groups[group].add(split)
            accepted.append({
                "text": row["text"],
                "labels": target,
                "observed_labels": observed,
                "language": str(row.get("language") or "").upper(),
                "source_dataset": row.get("source_dataset", ""),
                "source_id": row.get("source_id", ""),
                "conversation_id": row.get("conversation_id", ""),
                "source_split": split,
                "group_id": group,
                "synthetic": row.get("synthetic", "false").casefold() == "true",
                "review_status": "reviewed",
                "reviewer_decision": "accepted",
                "reviewer_id": row.get("reviewer_id", ""),
                "reviewed_at": row.get("reviewed_at", ""),
                "provenance": row.get("provenance", ""),
                "license": row.get("license", ""),
            })

    for group, splits in groups.items():
        if len(splits) > 1:
            errors.append({"group_id": group, "errors": ["group appears in multiple output splits"]})
            report["group_split_conflicts"] += 1

    label_counts = Counter(label for row in accepted for label in row["labels"])
    report["reviewed_label_counts"] = dict(sorted(label_counts.items()))
    label_language_counts = Counter((label, row["language"]) for row in accepted for label in row["labels"])
    label_split_counts = Counter((label, row["source_split"]) for row in accepted for label in row["labels"])
    report["reviewed_label_counts_by_language"] = {f"{label}|{language}": count for (label, language), count in sorted(label_language_counts.items())}
    report["reviewed_label_counts_by_split"] = {f"{label}|{split}": count for (label, split), count in sorted(label_split_counts.items())}
    complete_label_coverage = all(label_counts.get(label, 0) >= 30 for label in approved)
    import_ready = not errors and bool(accepted)
    training_ready = import_ready and complete_label_coverage and not any(row["source_split"] == "review_only" for row in accepted)
    args.output_dir.mkdir(parents=True, exist_ok=True)
    for split in ("train", "validation", "test", "review_only"):
        rows = [row for row in accepted if row["source_split"] == split]
        path = args.output_dir / f"{split}.jsonl"
        with path.open("w", encoding="utf-8") as handle:
            for row in rows:
                handle.write(json.dumps(row, ensure_ascii=False) + "\n")
        report[f"accepted_{split}"] = len(rows)

    provenance = {
        "source": "Human-reviewed Emotion35 annotations imported from the private review sheet; row-level source provenance is retained in each record.",
        "license": "Mixed row-level licenses; EmpatheticDialogues CC BY-NC-4.0 and internal synthetic examples. Research use only.",
        "usage": "Private non-commercial research training only; do not redistribute raw text or expose through Laravel.",
        "curator": "MindCare review team; reviewer IDs and timestamps are retained per accepted row.",
        "version": f"emotion35-reviewed-v1-taxonomy-{taxonomy.get('version', '')}",
    }
    (args.output_dir / "provenance.json").write_text(json.dumps(provenance, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    manifest = {
        "schema": "mindcare-emotion35-reviewed-v1",
        "created_at": datetime.now(timezone.utc).isoformat(),
        "taxonomy_version": str(taxonomy.get("version", "")),
        "source_sheet": str(args.input),
        "source_sheet_sha256": sha256(args.input),
        "counts": dict(report),
        "errors": errors,
        "import_ready": import_ready,
        "training_ready": training_ready,
        "training_gate": "at least 30 accepted examples for every approved label and no review_only rows",
        "note": "Accepted labels are human-reviewed; unobserved target labels remain masked by observed_labels.",
    }
    (args.output_dir / "import_report.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(manifest, indent=2, ensure_ascii=False))
    return 2 if errors else 0


if __name__ == "__main__":
    raise SystemExit(main())

