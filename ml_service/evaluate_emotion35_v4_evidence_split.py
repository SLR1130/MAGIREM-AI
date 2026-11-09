"""Re-score v4 test evidence by provenance without changing the checkpoint."""
from __future__ import annotations

import argparse
import json
from collections import Counter
from pathlib import Path

import numpy as np
import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

from train_emotion35_prototype_v4 import score_masked, sigmoid


ROOT = Path(__file__).resolve().parents[1]


def read_rows(path: Path) -> list[dict]:
    return [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", type=Path, required=True)
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--batch-size", type=int, default=32)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    labels = json.loads((args.model / "label_mapping.json").read_text(encoding="utf-8"))["labels"]
    threshold_data = json.loads((args.model / "thresholds.json").read_text(encoding="utf-8"))
    thresholds = threshold_data.get("thresholds", threshold_data)
    rows = read_rows(args.dataset / "test.jsonl")
    target = np.asarray([[1.0 if label in set(row.get("labels") or []) else 0.0 for label in labels] for row in rows], dtype=np.float32)
    mask = np.asarray([[1.0 if label in set(row.get("observed_labels") or []) else 0.0 for label in labels] for row in rows], dtype=np.float32)

    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    tokenizer = AutoTokenizer.from_pretrained(str(args.model), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(str(args.model), local_files_only=True).to(device).eval()
    probabilities = []
    with torch.no_grad():
        for start in range(0, len(rows), args.batch_size):
            encoded = tokenizer(
                [row["text"] for row in rows[start : start + args.batch_size]],
                truncation=True,
                max_length=256,
                padding=True,
                return_tensors="pt",
            )
            encoded = {key: value.to(device) for key, value in encoded.items()}
            probabilities.append(sigmoid(model(**encoded).logits.detach().cpu().numpy()))
    probabilities = np.concatenate(probabilities, axis=0)

    def group_result(indices: np.ndarray) -> dict:
        if len(indices) == 0:
            return {"records": 0}
        result = score_masked(target[indices], probabilities[indices], mask[indices], labels, thresholds)
        result.pop("per_label", None)
        languages = Counter(str(rows[index].get("language", "UNKNOWN")).upper() for index in indices)
        result["records"] = int(len(indices))
        result["languages"] = dict(sorted(languages.items()))
        result["fully_observed_synthetic_records"] = int(
            sum(bool(rows[index].get("synthetic")) and np.all(mask[index] > 0) for index in indices)
        )
        return result

    synthetic_indices = np.asarray([index for index, row in enumerate(rows) if row.get("synthetic")], dtype=np.int64)
    source_indices = np.asarray([index for index, row in enumerate(rows) if not row.get("synthetic")], dtype=np.int64)
    result = {
        "schema": "mindcare-emotion35-v4-evidence-split-v1",
        "model": str(args.model),
        "dataset": str(args.dataset),
        "device": str(device),
        "test_records": len(rows),
        "observed_label_cells": int(mask.sum()),
        "unknown_label_cells_excluded": int((1.0 - mask).sum()),
        "human_reviewed_records": int(sum(row.get("review_status") == "human_reviewed" for row in rows)),
        "public_source": group_result(source_indices),
        "synthetic_diagnostic": group_result(synthetic_indices),
        "warning": "Synthetic rows are AI-assisted/unreviewed research evidence, not real-student or professionally validated labels.",
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
