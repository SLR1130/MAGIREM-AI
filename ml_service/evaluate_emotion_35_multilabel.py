"""Evaluate a trained 35-label multilabel artifact without tuning it."""

from __future__ import annotations

import argparse
import json
import statistics
import time
from collections import Counter
from pathlib import Path

try:
    from .emotion_35_multilabel_pipeline import _labels, _records, _text, _jsonl_path, load_taxonomy
    from .emotion_35_multilabel_classifier import Emotion35MultilabelClassifier
    from .emotion_taxonomy import classify_label
except ImportError:
    from emotion_35_multilabel_pipeline import _labels, _records, _text, _jsonl_path, load_taxonomy
    from emotion_35_multilabel_classifier import Emotion35MultilabelClassifier
    from emotion_taxonomy import classify_label


def categories(labels: set[str]) -> set[str]:
    return {mapped["category_key"] for label in labels if (mapped := classify_label(label))}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--model-dir", type=Path, required=True)
    parser.add_argument("--dataset-dir", type=Path, required=True)
    parser.add_argument("--split", default="test")
    args = parser.parse_args()
    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    classifier = Emotion35MultilabelClassifier(args.model_dir, enabled=True)
    if not classifier.available:
        raise SystemExit(f"model unavailable: {classifier.load_error or 'unknown error'}")
    rows = list(_records(_jsonl_path(args.dataset_dir, args.split)))
    exact = category_exact = 0
    true_count = pred_count = intersection = 0
    mixed_tp = mixed_fp = mixed_fn = 0
    uncertain_tp = uncertain_fp = uncertain_fn = 0
    per_label = {label: Counter(tp=0, fp=0, fn=0) for label in labels}
    latencies = []
    for row in rows:
        truth = set(_labels(row))
        started = time.perf_counter()
        result = classifier.predict(_text(row), include_scores=False)
        latencies.append((time.perf_counter() - started) * 1000)
        predicted = set([result.get("primary_emotion")] if result.get("primary_emotion") else []) | set(result.get("secondary_emotions") or [])
        if predicted == truth:
            exact += 1
        if categories(predicted) == categories(truth):
            category_exact += 1
        true_count += len(truth)
        pred_count += len(predicted)
        intersection += len(truth & predicted)
        truth_mixed, predicted_mixed = len(truth) > 1, len(predicted) > 1
        mixed_tp += int(truth_mixed and predicted_mixed)
        mixed_fp += int(not truth_mixed and predicted_mixed)
        mixed_fn += int(truth_mixed and not predicted_mixed)
        truth_uncertain, predicted_uncertain = not truth, not predicted
        uncertain_tp += int(truth_uncertain and predicted_uncertain)
        uncertain_fp += int(not truth_uncertain and predicted_uncertain)
        uncertain_fn += int(truth_uncertain and not predicted_uncertain)
        for label in labels:
            per_label[label]["tp"] += int(label in truth and label in predicted)
            per_label[label]["fp"] += int(label not in truth and label in predicted)
            per_label[label]["fn"] += int(label in truth and label not in predicted)
    micro_precision = intersection / pred_count if pred_count else 0.0
    micro_recall = intersection / true_count if true_count else 0.0
    micro_f1 = (2 * micro_precision * micro_recall / (micro_precision + micro_recall)) if (micro_precision + micro_recall) else 0.0
    mixed_precision = mixed_tp / (mixed_tp + mixed_fp) if mixed_tp + mixed_fp else 0.0
    mixed_recall = mixed_tp / (mixed_tp + mixed_fn) if mixed_tp + mixed_fn else 0.0
    mixed_f1 = 2 * mixed_precision * mixed_recall / (mixed_precision + mixed_recall) if mixed_precision + mixed_recall else 0.0
    uncertain_precision = uncertain_tp / (uncertain_tp + uncertain_fp) if uncertain_tp + uncertain_fp else 0.0
    uncertain_recall = uncertain_tp / (uncertain_tp + uncertain_fn) if uncertain_tp + uncertain_fn else 0.0
    per_label_result = {}
    for label, counts in per_label.items():
        precision = counts["tp"] / (counts["tp"] + counts["fp"]) if counts["tp"] + counts["fp"] else 0.0
        recall = counts["tp"] / (counts["tp"] + counts["fn"]) if counts["tp"] + counts["fn"] else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_label_result[label] = {**counts, "precision": precision, "recall": recall, "f1": f1}
    ordered_latencies = sorted(latencies)
    print(json.dumps({
        "split": args.split,
        "cases": len(rows),
        "exact_set_accuracy": exact / len(rows) if rows else 0.0,
        "category_set_agreement": category_exact / len(rows) if rows else 0.0,
        "micro_precision": micro_precision,
        "micro_recall": micro_recall,
        "micro_f1": micro_f1,
        "mixed_precision": mixed_precision,
        "mixed_recall": mixed_recall,
        "mixed_f1": mixed_f1,
        "uncertainty_precision": uncertain_precision,
        "uncertainty_recall": uncertain_recall,
        "median_latency_ms": statistics.median(latencies) if latencies else None,
        "p95_latency_ms": ordered_latencies[max(0, int(len(ordered_latencies) * 0.95) - 1)] if ordered_latencies else None,
        "attribution": "not_supported_by_text_only_classifier",
        "per_label": per_label_result,
    }, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
