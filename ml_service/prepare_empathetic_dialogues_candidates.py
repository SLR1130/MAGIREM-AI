"""Prepare review-only EmpatheticDialogues candidates for emotion35 coverage.

The dataset's ``context`` is a situation label, not a ground-truth label for
all utterances. This script therefore preserves conversation IDs, emits only
one situation prompt candidate per conversation, and marks every candidate for
human review. It never writes candidates into a training split automatically.
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
DEFAULT_SOURCE = Path(r"C:\tmp\mindcare-empathetic\data\empatheticdialogues")
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "empathetic_dialogues_emotion35_review"

# Only lexical/context matches are proposed automatically. Related/intensity
# mappings remain review-only and are never used as training truth here.
EXACT_CONTEXT_MAP = {
    "excited": "excitement",
    "surprised": "surprise",
    "angry": "anger",
    "proud": "pride",
    "sad": "sadness",
    "lonely": "loneliness",
    "grateful": "gratitude",
    "confident": "confidence",
    "disgusted": "disgust",
    "anxious": "anxiety",
    "disappointed": "disappointment",
    "hopeful": "hope_optimism",
    "anticipating": "anticipation",
    "joyful": "happiness_joy",
    "jealous": "jealousy_envy",
    "guilty": "guilt",
    "content": "contentment",
    "ashamed": "shame",
}

REVIEW_ONLY_CONTEXT_MAP = {
    "afraid": ["fear"],
    "terrified": ["fear"],
    "furious": ["anger"],
    "annoyed": ["frustration"],
    "embarrassed": ["shame"],
    "caring": ["compassion_empathy"],
    "sentimental": ["love_affection"],
    "impressed": ["pride", "confidence"],
    "prepared": ["anticipation", "confidence"],
    "nostalgic": ["homesickness", "love_affection"],
    "devastated": ["grief", "sadness"],
    "trusting": ["confidence"],
    "apprehensive": ["anxiety", "fear"],
    "faithful": ["hope_optimism", "confidence"],
}


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def normalize(text: str) -> str:
    return " ".join(text.casefold().split())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-dir", type=Path, default=DEFAULT_SOURCE)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    args.output_dir.mkdir(parents=True, exist_ok=True)

    split_files = {"train": "train.csv", "validation": "valid.csv", "test": "test.csv"}
    contexts: Counter[str] = Counter()
    split_counts: Counter[str] = Counter()
    conversation_splits: dict[str, set[str]] = defaultdict(set)
    candidates_by_split: dict[str, list[dict[str, Any]]] = {key: [] for key in split_files}
    seen_conversations: set[str] = set()

    for split, filename in split_files.items():
        path = args.source_dir / filename
        if not path.exists():
            raise FileNotFoundError(path)
        conversations: dict[str, list[dict[str, str]]] = defaultdict(list)
        with path.open("r", encoding="utf-8", newline="") as handle:
            for row in csv.DictReader(handle):
                conv_id = str(row.get("conv_id", "")).strip()
                if not conv_id:
                    continue
                conversations[conv_id].append(row)
                contexts[str(row.get("context", "")).strip().casefold()] += 1
                split_counts[split] += 1
                conversation_splits[conv_id].add(split)
        for conv_id, rows in sorted(conversations.items()):
            if len(conversation_splits[conv_id]) != 1:
                raise ValueError(f"conversation appears in multiple splits: {conv_id}")
            first = rows[0]
            context = str(first.get("context", "")).strip().casefold()
            prompt = str(first.get("prompt", "")).strip()
            if not prompt:
                continue
            exact = EXACT_CONTEXT_MAP.get(context)
            proposed = [exact] if exact else REVIEW_ONLY_CONTEXT_MAP.get(context, [])
            candidates_by_split[split].append({
                "text": prompt,
                "labels": proposed,
                "observed_labels": proposed,
                "language": "ENGLISH",
                "source_dataset": "empathetic_dialogues",
                "source_id": conv_id,
                "conversation_id": conv_id,
                "source_context": context,
                "utterance_count": len(rows),
                "mapping_status": "needs_human_review",
                "annotation_scope": "situation_prompt_only; not an utterance-level ground truth",
                "review_status": "needs_human_review",
                "synthetic": False,
            })
            seen_conversations.add(conv_id)

    cross_split = sorted(conv for conv, splits in conversation_splits.items() if len(splits) > 1)
    candidate_counts = Counter()
    for split, rows in candidates_by_split.items():
        path = args.output_dir / f"{split}.jsonl"
        with path.open("w", encoding="utf-8") as handle:
            for row in rows:
                handle.write(json.dumps(row, ensure_ascii=False) + "\n")
                candidate_counts.update(row["labels"])

    matrix = []
    for context in sorted(contexts):
        proposed = EXACT_CONTEXT_MAP.get(context)
        status = "exact_candidate_needs_review" if proposed else "semantic_review_required"
        matrix.append({
            "target_emotion": proposed,
            "proposed_target_emotions": [proposed] if proposed else REVIEW_ONLY_CONTEXT_MAP.get(context, []),
            "source_dataset": "empathetic_dialogues",
            "source_label": context,
            "mapping_status": status,
            "source_utterance_count": contexts[context],
            "candidate_count": sum(1 for split in candidates_by_split.values() for row in split if row["source_context"] == context),
            "training_eligible": False,
            "note": "context describes an emotional situation; do not label every speaker utterance automatically",
        })
    report = {
        "schema": "mindcare-emotion35-empathetic-review-v1",
        "prepared_at": datetime.now(timezone.utc).isoformat(),
        "source": {
            "name": "EmpatheticDialogues",
            "url": "https://huggingface.co/datasets/facebook/empathetic_dialogues",
            "original_archive": "https://dl.fbaipublicfiles.com/parlai/empatheticdialogues/empatheticdialogues.tar.gz",
            "license": "CC BY-NC-4.0 (dataset card)",
            "usage": "Private research review only; non-commercial license and situation-level labels require human review before training or product use.",
            "language": "English",
            "splits": split_counts,
            "contexts": len(contexts),
            "conversation_ids": len(seen_conversations),
            "cross_split_conversation_ids": cross_split,
        },
        "candidate_split_counts": {split: len(rows) for split, rows in candidates_by_split.items()},
        "candidate_positive_counts": dict(sorted(candidate_counts.items())),
        "coverage_matrix": matrix,
        "training_eligible": False,
        "review_requirements": [
            "confirm that the situation prompt expresses the target emotion rather than merely describing it",
            "annotate each utterance only with evidence from that speaker's words",
            "obtain human adjudication before changing review_status or training_eligible",
            "retain conversation-group split provenance to prevent leakage",
        ],
        "manifest": {
            f"{split}.jsonl": hashlib.sha256((args.output_dir / f"{split}.jsonl").read_bytes()).hexdigest()
            for split in split_files
        },
    }
    write_json(args.output_dir / "coverage_matrix.json", matrix)
    write_json(args.output_dir / "provenance.json", report["source"])
    write_json(args.output_dir / "mapping_report.json", report)
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())




