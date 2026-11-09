"""Acquire and prepare licensed emotion data without silent relabeling.

The script consumes a locally cached GoEmotions dataset and writes only a
private, source-preserving JSONL subset. It maps exact taxonomy labels plus the
explicit legacy-compatible ``joy -> happiness_joy`` mapping. Labels that are
related but not equivalent are reported and excluded until reviewed.

No raw or processed dataset is written under ``public/``. The output directory
is intended for ``storage/app/datasets`` and should remain untracked.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CACHE = Path(os.getenv("HF_DATASETS_CACHE", r"C:\tmp\mindcare-hf\datasets"))
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_go_emotions_approved"

# GoEmotions names that are exactly represented by the approved taxonomy.
DIRECT_MAP = {
    "anger": "anger",
    "confusion": "confusion",
    "curiosity": "curiosity",
    "disappointment": "disappointment",
    "disgust": "disgust",
    "excitement": "excitement",
    "fear": "fear",
    "gratitude": "gratitude",
    "grief": "grief",
    "neutral": "neutral",
    "pride": "pride",
    "relief": "relief",
    "sadness": "sadness",
    "surprise": "surprise",
}

# Explicit compatibility, not a history rewrite. The taxonomy's canonical key
# is happiness_joy and explicitly lists joy as an alias.
COMPATIBILITY_MAP = {"joy": "happiness_joy"}

# These are related concepts, not safe automatic equivalences. They remain in
# the report so a reviewer can decide whether a separate annotation pass is
# warranted.
SEMANTIC_REVIEW = {
    "admiration": ["confidence", "pride", "love_affection"],
    "amusement": ["happiness_joy", "contentment"],
    "annoyance": ["frustration", "anger"],
    "approval": ["confidence", "happiness_joy"],
    "caring": ["compassion_empathy", "love_affection"],
    "desire": ["anticipation", "hope_optimism"],
    "disapproval": ["anger", "rejection_emotional_hurt"],
    "embarrassment": ["shame"],
    "love": ["love_affection"],
    "nervousness": ["anxiety"],
    "optimism": ["hope_optimism"],
    "realization": ["surprise", "curiosity"],
    "remorse": ["guilt", "shame"],
}


def taxonomy() -> dict[str, Any]:
    return json.loads((ROOT / "config" / "emotion_taxonomy.json").read_text(encoding="utf-8"))


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def normalize_text(text: str) -> str:
    return " ".join(text.casefold().split())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--cache-dir", type=Path, default=DEFAULT_CACHE)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--offline", action="store_true", help="never contact the Hub; require an existing cache")
    args = parser.parse_args()
    args.output_dir.mkdir(parents=True, exist_ok=True)

    if args.offline:
        os.environ["HF_HUB_OFFLINE"] = "1"
        os.environ["HF_DATASETS_OFFLINE"] = "1"
    from datasets import Dataset, DatasetDict, load_dataset

    if args.offline:
        version_root = args.cache_dir / "google-research-datasets___go_emotions" / "simplified" / "0.0.0"
        candidates = sorted([path for path in version_root.glob("*") if path.is_dir()])
        if not candidates:
            raise FileNotFoundError(f"no cached GoEmotions arrow dataset under {version_root}")
        cached = candidates[-1]
        dataset = DatasetDict({
            split: Dataset.from_file(str(cached / f"go_emotions-{split}.arrow"))
            for split in ("train", "validation", "test")
        })
    else:
        dataset = load_dataset(
            "google-research-datasets/go_emotions",
            "simplified",
            cache_dir=str(args.cache_dir),
            download_mode="reuse_dataset_if_exists",
        )
    tax = taxonomy()
    approved = {item["key"] for item in tax["emotions"]}
    source_labels = list(dataset["train"].features["labels"].feature.names)
    report: dict[str, Any] = {
        "schema": "mindcare-emotion35-source-mapping-v1",
        "prepared_at": datetime.now(timezone.utc).isoformat(),
        "source": {
            "name": "GoEmotions",
            "source": "Google Research GoEmotions simplified configuration",
            "url": "https://huggingface.co/datasets/google-research-datasets/go_emotions",
            "repository": "https://github.com/google-research/google-research/tree/master/goemotions",
            "license": "Apache-2.0 (as stated on the dataset card)",
            "usage": "Local research/training preparation only; do not redistribute raw Reddit-derived text without reviewing source terms and privacy requirements.",
            "curator": "Google Research, Amazon Alexa AI, Stanford contributors (dataset card)",
            "version": "go_emotions-simplified-0.0.0",
            "language": "English",
            "annotation": "multi-label; three English-speaking crowdworkers in India",
            "privacy_note": "Reddit-derived text; the raw configuration can contain usernames. This preparation uses simplified text/id fields only and stores output privately.",
            "taxonomy_version": str(tax.get("version", "")),
        },
        "source_split_counts": {split: len(dataset[split]) for split in ("train", "validation", "test")},
        "source_labels": source_labels,
        "direct_mapping": {label: DIRECT_MAP[label] for label in source_labels if label in DIRECT_MAP},
        "explicit_compatibility_mapping": {label: COMPATIBILITY_MAP[label] for label in source_labels if label in COMPATIBILITY_MAP},
        "semantic_review_required": {label: SEMANTIC_REVIEW[label] for label in source_labels if label in SEMANTIC_REVIEW},
        "unmapped_source_labels": [label for label in source_labels if label not in DIRECT_MAP and label not in COMPATIBILITY_MAP and label not in SEMANTIC_REVIEW],
        "missing_approved_labels_before": sorted(approved - set(DIRECT_MAP.values()) - set(COMPATIBILITY_MAP.values())),
    }

    # Keep only rows where every source annotation has an approved mapping. A
    # partial mapping would incorrectly turn the omitted source labels into
    # negative labels for BCE training.
    included_counts: Counter[str] = Counter()
    excluded_counts: Counter[str] = Counter()
    split_output_counts: dict[str, int] = {}
    all_hashes: defaultdict[str, set[str]] = defaultdict(set)
    for split in ("train", "validation", "test"):
        destination = args.output_dir / f"{split}.jsonl"
        rows_written = 0
        with destination.open("w", encoding="utf-8") as handle:
            for row in dataset[split]:
                original = [source_labels[index] for index in row["labels"]]
                if not original:
                    excluded_counts["empty_labels"] += 1
                    continue
                if any(label not in DIRECT_MAP and label not in COMPATIBILITY_MAP for label in original):
                    for label in original:
                        if label not in DIRECT_MAP and label not in COMPATIBILITY_MAP:
                            excluded_counts[label] += 1
                    continue
                mapped = []
                mapping_kind = "direct"
                for label in original:
                    canonical = DIRECT_MAP.get(label, COMPATIBILITY_MAP.get(label))
                    if canonical not in approved:
                        raise RuntimeError(f"mapping produced a non-taxonomy label: {canonical}")
                    mapped.append(canonical)
                    if label in COMPATIBILITY_MAP:
                        mapping_kind = "explicit_compatibility"
                    included_counts[canonical] += 1
                text = str(row["text"])
                all_hashes[normalize_text(text)].add(split)
                output = {
                    "text": text,
                    "labels": sorted(set(mapped)),
                    # GoEmotions annotates the complete source vocabulary for
                    # each record. The mapped source labels are observed; the
                    # remaining target emotions are unknown, not negatives.
                    "observed_labels": sorted({
                        DIRECT_MAP.get(label, COMPATIBILITY_MAP.get(label))
                        for label in source_labels
                        if label in DIRECT_MAP or label in COMPATIBILITY_MAP
                    }),
                    "language": "ENGLISH",
                    "source_dataset": "go_emotions",
                    "source_id": str(row["id"]),
                    "original_labels": original,
                    "mapping_kind": mapping_kind,
                }
                handle.write(json.dumps(output, ensure_ascii=False) + "\n")
                rows_written += 1
        split_output_counts[split] = rows_written

    # Remove exact normalized-text leakage deterministically. Training keeps
    # precedence, then validation, then test; later duplicates are dropped.
    seen_hashes: set[str] = set()
    deduplicated_counts: dict[str, int] = {}
    deduplicated_labels: Counter[str] = Counter()
    for split in ("train", "validation", "test"):
        path = args.output_dir / f"{split}.jsonl"
        kept: list[str] = []
        removed = 0
        for line in path.read_text(encoding="utf-8").splitlines():
            row = json.loads(line)
            key = normalize_text(str(row["text"]))
            if key in seen_hashes:
                removed += 1
                continue
            seen_hashes.add(key)
            kept.append(line)
            deduplicated_labels.update(row["labels"])
        path.write_text("\n".join(kept) + ("\n" if kept else ""), encoding="utf-8")
        deduplicated_counts[split] = removed

    report["included_counts"] = dict(sorted(deduplicated_labels.items()))
    report["excluded_counts"] = dict(sorted(excluded_counts.items()))
    report["prepared_split_counts"] = {
        split: sum(1 for _ in (args.output_dir / f"{split}.jsonl").open(encoding="utf-8"))
        for split in ("train", "validation", "test")
    }
    report["deduplicated_records_removed_by_split"] = deduplicated_counts
    report["cross_split_normalized_duplicates"] = 0
    report["missing_approved_labels_after"] = sorted(approved - set(included_counts))
    report["usable_for_35_label_training"] = not report["missing_approved_labels_after"] and report["cross_split_normalized_duplicates"] == 0
    write_json(args.output_dir / "mapping_report.json", report)
    write_json(args.output_dir / "provenance.json", report["source"])
    print(json.dumps(report, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())





