"""Merge compatible GoEmotions and completed human reviews for Emotion35.

Only accepted rows emitted by ``import_emotion35_review_sheet.py`` are loaded;
candidate CSVs and ``review_only`` rows never enter the merged corpus.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from emotion_35_multilabel_pipeline import EXPECTED_SPLITS, _jsonl_path, _records, _text, _text_hash, load_taxonomy

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_GO = ROOT / "storage" / "app" / "datasets" / "emotion35_go_emotions_approved"
DEFAULT_REVIEWS = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow" / "reviewed_import"
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_merged_training"


def as_labels(value: Any) -> list[str]:
    if isinstance(value, str):
        values = value.replace(",", ";").split(";")
    elif isinstance(value, (list, tuple, set)):
        values = list(value)
    else:
        values = []
    return sorted({str(item).strip().casefold() for item in values if str(item).strip()})


def normalize(record: dict[str, Any], source: str, split: str) -> dict[str, Any]:
    labels = as_labels(record.get("labels"))
    observed = as_labels(record.get("observed_labels"))
    if not observed:
        raise ValueError("record is missing observed_labels")
    text = _text(record)
    source_id = str(record.get("source_id") or "").strip()
    group_id = str(record.get("group_id") or "").strip()
    if not group_id:
        group_id = f"{source}:{source_id or hashlib.sha256(text.encode('utf-8')).hexdigest()[:20]}"
    return {
        "text": text,
        "labels": labels,
        "observed_labels": observed,
        "language": str(record.get("language") or "UNKNOWN").upper(),
        "source_dataset": str(record.get("source_dataset") or source),
        "source": source,
        "source_id": source_id,
        "conversation_id": str(record.get("conversation_id") or ""),
        "source_split": split,
        "group_id": group_id,
        "synthetic": bool(record.get("synthetic", False)),
        "provenance": str(record.get("provenance") or record.get("mapping_kind") or ""),
        "license": str(record.get("license") or ""),
    }


def load_source(directory: Path, source: str, required: bool) -> list[dict[str, Any]]:
    if not directory.exists():
        if required:
            raise FileNotFoundError(directory)
        return []
    result: list[dict[str, Any]] = []
    for split in EXPECTED_SPLITS:
        path = _jsonl_path(directory, split) if required or (directory / f"{split}.jsonl").exists() else None
        if path is not None:
            for raw in _records(path):
                result.append(normalize(raw, source, split))
    return result


def provenance(version: str) -> dict[str, str]:
    return {
        "source": "Compatible GoEmotions annotations plus accepted human-reviewed Emotion35 records; row-level provenance is retained.",
        "license": "Mixed row-level licenses. EmpatheticDialogues material remains CC BY-NC-4.0; synthetic material is internal. Research use only.",
        "usage": "Private, non-commercial research training only; raw text must not be exposed through Laravel or Admin.",
        "curator": "MindCare Emotion35 review workflow; reviewer IDs and timestamps remain in accepted review rows.",
        "version": f"emotion35-merged-v1-taxonomy-{version}",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--go-dir", type=Path, default=DEFAULT_GO)
    parser.add_argument("--reviewed-dir", type=Path, default=DEFAULT_REVIEWS)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--min-train", type=int, default=20)
    parser.add_argument("--min-validation", type=int, default=5)
    parser.add_argument("--min-test", type=int, default=5)
    args = parser.parse_args()
    taxonomy = load_taxonomy()
    allowed = {item["key"] for item in taxonomy["emotions"]}
    rows = load_source(args.go_dir, "go_emotions", True)
    rows.extend(load_source(args.reviewed_dir, "human_review", False))

    invalid: list[dict[str, Any]] = []
    group_splits: dict[str, str] = {}
    split_hashes: dict[str, dict[str, str]] = defaultdict(dict)
    valid_indices: set[int] = set()
    for index, row in enumerate(rows):
        errors: list[str] = []
        labels = set(row["labels"])
        observed = set(row["observed_labels"])
        if not labels:
            errors.append("missing positive labels")
        if not labels.issubset(allowed) or not observed.issubset(allowed):
            errors.append("label outside approved taxonomy")
        if not labels.issubset(observed):
            errors.append("positive label outside observed mask")
        old_split = group_splits.setdefault(row["group_id"], row["source_split"])
        if old_split != row["source_split"]:
            errors.append("group appears in multiple splits")
        digest = _text_hash(row["text"])
        if digest in split_hashes[row["source_split"]]:
            errors.append("duplicate normalized text in split")
        else:
            split_hashes[row["source_split"]][digest] = row["source_id"]
        if errors:
            invalid.append({"row": index + 1, "source_id": row["source_id"], "errors": errors})
        else:
            valid_indices.add(index)

    args.output_dir.mkdir(parents=True, exist_ok=True)
    for split in EXPECTED_SPLITS:
        with (args.output_dir / f"{split}.jsonl").open("w", encoding="utf-8") as handle:
            for index, row in enumerate(rows):
                if index in valid_indices and row["source_split"] == split:
                    handle.write(json.dumps(row, ensure_ascii=False) + "\n")

    label_split = {label: {split: 0 for split in EXPECTED_SPLITS} for label in sorted(allowed)}
    label_sources = {label: Counter() for label in sorted(allowed)}
    label_languages = {label: Counter() for label in sorted(allowed)}
    for index in sorted(valid_indices):
        row = rows[index]
        for label in row["labels"]:
            label_split[label][row["source_split"]] += 1
            label_sources[label][row["source_dataset"]] += 1
            label_languages[label][row["language"]] += 1
    thresholds = {"train": args.min_train, "validation": args.min_validation, "test": args.min_test}
    coverage: dict[str, Any] = {}
    blockers = ["merged records failed schema/provenance validation"] if invalid else []
    for label in sorted(allowed):
        counts = label_split[label]
        remaining = {split: max(0, thresholds[split] - counts[split]) for split in EXPECTED_SPLITS}
        if any(remaining.values()):
            blockers.append(f"{label} is below minimum coverage")
        coverage[label] = {
            "counts_by_split": counts,
            "remaining_to_minimum": remaining,
            "counts_by_source": dict(sorted(label_sources[label].items())),
            "counts_by_language": dict(sorted(label_languages[label].items())),
            "accepted_total": sum(counts.values()),
        }
    prov = provenance(str(taxonomy.get("version", "")))
    (args.output_dir / "provenance.json").write_text(json.dumps(prov, indent=2) + "\n", encoding="utf-8")
    manifest: dict[str, Any] = {
        "schema": "mindcare-emotion35-merged-v1",
        "created_at": datetime.now(timezone.utc).isoformat(),
        "taxonomy_version": taxonomy.get("version", ""),
        "source_counts": dict(Counter(rows[index]["source_dataset"] for index in valid_indices)),
        "split_counts": dict(Counter(rows[index]["source_split"] for index in valid_indices)),
        "accepted_review_rows": sum(rows[index]["source"] == "human_review" for index in valid_indices),
        "coverage": coverage,
        "invalid_rows": invalid,
        "blockers": sorted(set(blockers)),
        "training_ready": not blockers,
        "note": "Unreviewed review candidates are excluded. Source omissions remain masked by observed_labels.",
    }
    (args.output_dir / "coverage_report.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    manifest["dataset_files"] = {f"{split}.jsonl": hashlib.sha256((args.output_dir / f"{split}.jsonl").read_bytes()).hexdigest() for split in EXPECTED_SPLITS}
    (args.output_dir / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(manifest, indent=2, ensure_ascii=False))
    return 0 if not blockers else 2


if __name__ == "__main__":
    raise SystemExit(main())
