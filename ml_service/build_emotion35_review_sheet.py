"""Build a private, reviewable annotation sheet for Emotion35 candidates.

This is deliberately a review workflow, not a relabeling step.  Every row is
``unreviewed`` until a human records a decision and observed labels.  Source
conversation groups and split membership are carried into the sheet so an
accepted annotation cannot silently leak across train/validation/test.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_EMPATHETIC = ROOT / "storage" / "app" / "datasets" / "empathetic_dialogues_emotion35_review"
DEFAULT_SYNTHETIC = ROOT / "storage" / "app" / "datasets" / "emotion35_annotation_template_20261025.jsonl"
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow"

FIELDS = [
    "review_id", "text", "source_dataset", "source_id", "conversation_id",
    "source_split", "source_context", "proposed_emotion",
    "optional_secondary_emotions", "language", "review_status",
    "reviewer_decision", "reviewed_labels", "reviewed_observed_labels",
    "rejection_reason", "ambiguity_notes", "provenance", "license",
    "group_id", "synthetic", "annotation_scope", "reviewer_id", "reviewed_at",
]


def stable_id(*parts: object) -> str:
    value = "|".join(str(part or "") for part in parts)
    return "r-" + hashlib.sha256(value.encode("utf-8")).hexdigest()[:20]


def split_labels(value: Any) -> list[str]:
    if isinstance(value, list):
        return [str(item).strip() for item in value if str(item).strip()]
    return [item.strip() for item in str(value or "").replace(";", ",").split(",") if item.strip()]


def read_jsonl(path: Path) -> Iterable[dict[str, Any]]:
    with path.open("r", encoding="utf-8") as handle:
        for line in handle:
            if line.strip():
                yield json.loads(line)


def source_row(row: dict[str, Any], split: str) -> dict[str, str]:
    proposed = split_labels(row.get("labels"))
    conversation_id = str(row.get("conversation_id") or row.get("source_id") or "").strip()
    source_id = str(row.get("source_id") or conversation_id).strip()
    context = str(row.get("source_context") or "").strip()
    return {
        "review_id": stable_id("empathetic_dialogues", source_id),
        "text": str(row.get("text") or "").strip(),
        "source_dataset": "empathetic_dialogues",
        "source_id": source_id,
        "conversation_id": conversation_id,
        "source_split": split,
        "source_context": context,
        "proposed_emotion": proposed[0] if proposed else "",
        "optional_secondary_emotions": ";".join(proposed[1:]),
        "language": str(row.get("language") or "ENGLISH").upper(),
        "review_status": "unreviewed",
        "reviewer_decision": "",
        "reviewed_labels": "",
        "reviewed_observed_labels": "",
        "rejection_reason": "",
        "ambiguity_notes": "",
        "provenance": "EmpatheticDialogues context/prompt; situation-level candidate only",
        "license": "CC BY-NC-4.0",
        "group_id": "empathetic_dialogues:" + conversation_id,
        "synthetic": "false",
        "annotation_scope": str(row.get("annotation_scope") or "situation_prompt_only; not utterance-level ground truth"),
        "reviewer_id": "",
        "reviewed_at": "",
    }


def synthetic_row(row: dict[str, Any], index: int) -> dict[str, str]:
    proposed = split_labels(row.get("proposed_labels"))
    language = str(row.get("language") or "").upper()
    label = proposed[0] if proposed else "unmapped"
    group = f"synthetic:{label}:{language.lower()}"
    return {
        "review_id": stable_id("synthetic_template", index, row.get("text")),
        "text": str(row.get("text") or "").strip(),
        "source_dataset": "synthetic_template",
        "source_id": f"synthetic-{index:04d}",
        "conversation_id": "",
        "source_split": "review_only",
        "source_context": "",
        "proposed_emotion": label if label != "unmapped" else "",
        "optional_secondary_emotions": ";".join(proposed[1:]),
        "language": language,
        "review_status": "unreviewed",
        "reviewer_decision": "",
        "reviewed_labels": "",
        "reviewed_observed_labels": "",
        "rejection_reason": "",
        "ambiguity_notes": "",
        "provenance": "Synthetic candidate generated from annotation template; not ground truth",
        "license": "Internal synthetic example",
        "group_id": group,
        "synthetic": "true",
        "annotation_scope": "student-style expression; human label and attribution review required",
        "reviewer_id": "",
        "reviewed_at": "",
    }


def write_csv(path: Path, rows: list[dict[str, str]]) -> str:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--empathetic-dir", type=Path, default=DEFAULT_EMPATHETIC)
    parser.add_argument("--synthetic", type=Path, default=DEFAULT_SYNTHETIC)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--batch-per-context", type=int, default=5)
    args = parser.parse_args()
    if args.batch_per_context < 1:
        parser.error("--batch-per-context must be positive")

    all_rows: list[dict[str, str]] = []
    batch: list[dict[str, str]] = []
    per_context: Counter[str] = Counter()
    source_counts: Counter[str] = Counter()
    split_counts: Counter[str] = Counter()

    for split in ("train", "validation", "test"):
        path = args.empathetic_dir / f"{split}.jsonl"
        if not path.exists():
            raise FileNotFoundError(path)
        for row in read_jsonl(path):
            normalized = source_row(row, split)
            all_rows.append(normalized)
            source_counts[normalized["source_dataset"]] += 1
            split_counts[split] += 1
            context = normalized["source_context"]
            if per_context[context] < args.batch_per_context:
                batch.append(normalized)
                per_context[context] += 1

    if not args.synthetic.exists():
        raise FileNotFoundError(args.synthetic)
    for index, row in enumerate(read_jsonl(args.synthetic), start=1):
        normalized = synthetic_row(row, index)
        all_rows.append(normalized)
        batch.append(normalized)
        source_counts[normalized["source_dataset"]] += 1

    args.output_dir.mkdir(parents=True, exist_ok=True)
    master_hash = write_csv(args.output_dir / "emotion35_review_master.csv", all_rows)
    batch_hash = write_csv(args.output_dir / "review_batch_001.csv", batch)

    metadata = {
        "schema": "mindcare-emotion35-review-sheet-v1",
        "created_at": datetime.now(timezone.utc).isoformat(),
        "taxonomy_version": "1.0.0",
        "rows": {"master": len(all_rows), "batch_001": len(batch)},
        "source_counts": dict(source_counts),
        "source_split_counts": dict(split_counts),
        "batch_per_empathetic_context": args.batch_per_context,
        "batch_synthetic_rows": sum(row["synthetic"] == "true" for row in batch),
        "review_status_defaults": {"review_status": "unreviewed", "reviewer_decision": "blank"},
        "required_reviewer_fields_for_training": ["review_status=reviewed", "reviewer_decision=accepted", "reviewed_labels", "reviewed_observed_labels", "reviewer_id", "reviewed_at"],
        "files": {
            "emotion35_review_master.csv": master_hash,
            "review_batch_001.csv": batch_hash,
        },
        "privacy": "Private local research artifact. Do not place under public/ or expose through Laravel.",
    }
    (args.output_dir / "manifest.json").write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")
    readme = f"""# Emotion35 review workflow

Generated {metadata['created_at']} for taxonomy version 1.0.0.

* `emotion35_review_master.csv`: {len(all_rows):,} private candidates.
* `review_batch_001.csv`: {len(batch):,} rows for the first manageable review batch ({args.batch_per_context} EmpatheticDialogues situation prompts per source context plus {metadata['batch_synthetic_rows']} synthetic multilingual candidates).
* `review_batch_gap_001.csv`: expanded synthetic candidates for the seven uncovered labels; every row remains unreviewed.

Reviewer instructions: `ml_service/EMOTION35_REVIEWER_INSTRUCTIONS_20261025.md`.

Every row starts as **unreviewed**. A reviewer must set `review_status=reviewed`, `reviewer_decision=accepted`, `reviewed_labels`, and `reviewed_observed_labels` before import. Use semicolon-separated taxonomy keys for multiple labels. Mark uncertain or ambiguous examples as `ambiguous`; reject rows with a reason. Do not infer labels from the source context alone, and do not assign a situation label to every conversational utterance.

The importer (`ml_service/import_emotion35_review_sheet.py`) fails closed on unknown labels, missing observed masks, split/group conflicts, or incomplete reviewer decisions. Accepted output remains private JSONL and retains source, language, license, and group provenance. Accepted rows also require reviewer_id and reviewed_at for audit provenance.

The seven target gaps with no directly usable source mapping are `calmness`, `hopelessness`, `rejection_emotional_hurt`, `stress`, `overwhelm`, `insecurity_self_doubt`, and `boredom`. The three synthetic examples per template label are a review seed, not sufficient coverage for a model. See review_plan.json for the seven-gap goal of at least 30 accepted examples per target; current accepted count is zero.
"""
    gaps = ["calmness", "hopelessness", "rejection_emotional_hurt", "stress", "overwhelm", "insecurity_self_doubt", "boredom"]
    review_plan = {
        "schema": "mindcare-emotion35-review-plan-v1",
        "taxonomy_version": "1.0.0",
        "target_minimum_accepted_reviews_per_gap": 30,
        "independent_reviewers_recommended": 2,
        "targets": [{"target_emotion": label, "synthetic_seed_rows": 15, "accepted_reviews_current": 0, "additional_reviews_needed": 30, "languages_required": ["ENGLISH", "FILIPINO", "TAGLISH"], "status": "needs_human_review"} for label in gaps],
        "note": "Counts are review goals, not training truth. Add examples only through human-authored or human-verified annotation; do not infer labels from aliases or translations.",
    }
    (args.output_dir / "review_plan.json").write_text(json.dumps(review_plan, indent=2) + "\n", encoding="utf-8")
    (args.output_dir / "README.md").write_text(readme, encoding="utf-8")
    print(json.dumps(metadata, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())



