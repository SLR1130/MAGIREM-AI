"""Build a review-required multilingual Emotion35 v4 candidate bank.

This is a data-quality handoff, not trusted training ground truth. Every row
keeps provenance and remains excluded from the trainer until human review
confirms labels, attribution, mixed emotions, and observed-label masks.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY = ROOT / "config/emotion_taxonomy.json"
GAP_CSV = ROOT / "storage/app/datasets/emotion35_review_workflow/review_batch_gap_002.csv"
TEMPLATE_JSONL = ROOT / "storage/app/datasets/emotion35_annotation_template_20261025.jsonl"
PROMPT_BANK = ROOT / "storage/app/audit-recovery/emotion35_adviser_prompt_bank.json"
INDEPENDENT_EVAL = ROOT / "storage/app/audit-recovery/emotion35_independent_eval_20261103.json"
DEFAULT_OUT = ROOT / "storage/app/audit-recovery/emotion35_v4_ai_candidate_bank_20261103.jsonl"


def stable_id(source: str, text: str) -> str:
    return f"v4-candidate-{source}-{hashlib.sha256(text.encode('utf-8')).hexdigest()[:16]}"


def row(text: str, language: str, labels: list[str], source: str, source_id: str, note: str) -> dict:
    return {
        "candidate_id": stable_id(source, text),
        "text": text,
        "language": language,
        "proposed_labels": labels,
        "observed_labels": [],
        "source_dataset": source,
        "source_id": source_id,
        "source_group": f"{source}:{source_id}",
        "split": "candidate",
        "synthetic": True,
        "ai_assisted": True,
        "weak_supervision": True,
        "human_review_required": True,
        "review_status": "unreviewed",
        "training_eligibility": "research_only_candidate_not_for_training",
        "provenance_note": note,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()
    taxonomy = json.loads(TAXONOMY.read_text(encoding="utf-8"))
    allowed = {item["key"] for item in taxonomy["emotions"]}
    holdout_texts = {
        item["text"].casefold().strip()
        for item in json.loads(INDEPENDENT_EVAL.read_text(encoding="utf-8"))
    }
    records: list[dict] = []

    with GAP_CSV.open(encoding="utf-8-sig", newline="") as stream:
        for item in csv.DictReader(stream):
            labels = [item["proposed_emotion"]] if item.get("proposed_emotion") else []
            if item.get("text") and set(labels).issubset(allowed):
                records.append(row(item["text"], item["language"], labels, item["source_dataset"], item["source_id"], "Existing synthetic review reserve; proposed label is not human-verified."))

    for item in (json.loads(line) for line in TEMPLATE_JSONL.read_text(encoding="utf-8").splitlines() if line.strip()):
        labels = list(item.get("proposed_labels") or [])
        if item.get("text") and set(labels).issubset(allowed):
            records.append(row(item["text"], item["language"], labels, item.get("source", "synthetic_template"), hashlib.sha256(item["text"].encode()).hexdigest()[:16], item.get("notes", "Synthetic template requiring review.")))

    for item in json.loads(PROMPT_BANK.read_text(encoding="utf-8")):
        labels = list(item.get("expected_emotions") or [])
        if item.get("text") and set(labels).issubset(allowed):
            records.append(row(item["text"], item["language"], labels, "adviser_prompt_bank", item["prompt_id"], "AI-generated adviser example; not human-reviewed and not a holdout label."))

    unique = {}
    for item in records:
        unique.setdefault(item["text"].casefold().strip(), item)
    records = [item for text, item in unique.items() if text not in holdout_texts]
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text("".join(json.dumps(item, ensure_ascii=False) + "\n" for item in records), encoding="utf-8")
    counts = {}
    languages = {}
    for item in records:
        for label in item["proposed_labels"]:
            counts[label] = counts.get(label, 0) + 1
        languages[item["language"]] = languages.get(item["language"], 0) + 1
    manifest = {
        "schema": "emotion35-v4-ai-candidate-bank-v1",
        "rows": len(records),
        "labels": len(counts),
        "by_label": dict(sorted(counts.items())),
        "by_language": languages,
        "review_status": "unreviewed",
        "human_reviewed_rows": 0,
        "training_ready": False,
        "note": "Candidate bank only; do not count toward coverage or feed the trainer until accepted human review supplies observed_labels.",
    }
    args.output.with_suffix(".manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(manifest, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
