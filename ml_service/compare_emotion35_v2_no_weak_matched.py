"""Fair v2 versus no-weak recovery comparison on common, source-separated data.

This is evaluation-only. It does not tune thresholds or alter checkpoints.
The public GoEmotions intersection is the primary comparison; the existing
36-case synthetic diagnostic is reported separately as provisional evidence.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))

from emotion35_calibration import load_calibration_artifact, sha256_file
from emotion_35_multilabel_pipeline import _records, load_taxonomy


CHECKPOINTS = {
    "v2": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v2_20261026",
    "no_weak_recovery": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_recovery_no_weak_20261103",
}
DATASETS = {
    "v2": ROOT / "storage/app/audit-recovery/emotion35_prototype_multilabel_20261026",
    "no_weak_recovery": ROOT / "storage/app/audit-recovery/emotion35_v4_weak_supervision_masks_fixed_20261103",
}
CALIBRATIONS = {
    "v2": ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final/v2_calibration.json",
    "no_weak_recovery": ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final/recovery_no_weak_diagnostic_calibration.json",
}
FROZEN_DIAGNOSTIC = ROOT / "storage/app/audit-recovery/emotion35_recovery_complete_diagnostic_20261103.json"


def _read_jsonl(path: Path) -> list[dict[str, Any]]:
    return list(_records(path))


def _source_rows(dataset: Path) -> list[dict[str, Any]]:
    rows = _read_jsonl(dataset / "test.jsonl")
    return [row for row in rows if row.get("source_dataset") == "go_emotions"]


def _row_key(row: dict[str, Any]) -> tuple[str, str]:
    return str(row.get("source_dataset")), str(row.get("source_id"))


def _verify_common_source_records(v2_rows: list[dict], recovery_rows: list[dict]) -> list[dict]:
    left = {_row_key(row): row for row in v2_rows}
    right = {_row_key(row): row for row in recovery_rows}
    if len(left) != len(v2_rows) or len(right) != len(recovery_rows):
        raise RuntimeError("source test records contain duplicate source identifiers")
    common = sorted(set(left) & set(right))
    if not common:
        raise RuntimeError("there are no common source-native test examples")
    fields = ("text", "labels", "observed_labels", "language", "source_group", "split")
    for key in common:
        if any(left[key].get(field) != right[key].get(field) for field in fields):
            raise RuntimeError(f"common source record differs between datasets: {key}")
    return [left[key] for key in common]


def _predict(checkpoint: Path, texts: list[str], device: str, batch_size: int) -> tuple[np.ndarray, float]:
    import time
    import torch
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    tokenizer = AutoTokenizer.from_pretrained(str(checkpoint), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(str(checkpoint), local_files_only=True)
    model.to(device).eval()
    outputs: list[np.ndarray] = []
    started = time.perf_counter()
    try:
        with torch.no_grad():
            for offset in range(0, len(texts), batch_size):
                batch = tokenizer(texts[offset:offset + batch_size], return_tensors="pt", padding=True, truncation=True, max_length=256)
                batch = {key: value.to(device) for key, value in batch.items()}
                logits = model(**batch).logits
                outputs.append(torch.sigmoid(logits).cpu().numpy())
    finally:
        elapsed = time.perf_counter() - started
        del model, tokenizer
        if device.startswith("cuda"):
            torch.cuda.empty_cache()
    return np.concatenate(outputs, axis=0), elapsed


def _metrics(rows: list[dict], probabilities: np.ndarray, labels: list[str], eligible: list[str], thresholds: dict[str, float]) -> tuple[dict, list[dict]]:
    label_index = {label: index for index, label in enumerate(labels)}
    tp = fp = fn = observed_cells = observed_positive = observed_negative = 0
    counts = {label: {"tp": 0, "fp": 0, "fn": 0, "positive_support": 0, "negative_support": 0} for label in eligible}
    accepted_per_message: list[int] = []
    abstentions = 0
    exact_matches: list[bool] = []
    sample_f1: list[float] = []
    case_predictions: list[dict] = []
    for row_index, row in enumerate(rows):
        positives = set(row.get("labels") or [])
        observed = set(row.get("observed_labels") or [])
        accepted = [label for label in eligible if probabilities[row_index, label_index[label]] >= thresholds[label]]
        evaluated = [label for label in eligible if label in observed]
        if len(evaluated) != len(eligible):
            raise RuntimeError("matched source row does not observe every eligible emotion")
        local_tp = local_fp = local_fn = 0
        for label in eligible:
            truth = label in positives
            predicted = label in accepted
            observed_cells += 1
            observed_positive += int(truth)
            observed_negative += int(not truth)
            counts[label]["positive_support"] += int(truth)
            counts[label]["negative_support"] += int(not truth)
            if truth and predicted:
                tp += 1; local_tp += 1; counts[label]["tp"] += 1
            elif not truth and predicted:
                fp += 1; local_fp += 1; counts[label]["fp"] += 1
            elif truth and not predicted:
                fn += 1; local_fn += 1; counts[label]["fn"] += 1
        accepted_per_message.append(len(accepted))
        abstentions += int(not accepted)
        expected = positives.intersection(eligible)
        exact_matches.append(set(accepted) == expected)
        precision = local_tp / (local_tp + local_fp) if local_tp + local_fp else 0.0
        recall = local_tp / (local_tp + local_fn) if local_tp + local_fn else 0.0
        sample_f1.append(2 * precision * recall / (precision + recall) if precision + recall else 0.0)
        case_predictions.append({
            "case_id": row.get("case_id"),
            "source_id": row.get("source_id", row.get("case_id")),
            "language": row.get("language"),
            "text": row.get("text"),
            "expected_labels_eligible": sorted(expected),
            "accepted_labels": accepted,
            "correct_labels": sorted(expected.intersection(accepted)),
            "false_positive_labels": sorted(set(accepted) - expected),
            "missed_labels": sorted(expected - set(accepted)),
            "accepted_count": len(accepted),
        })
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    per_label = {}
    f1s = []
    for label, row_counts in counts.items():
        p = row_counts["tp"] / (row_counts["tp"] + row_counts["fp"]) if row_counts["tp"] + row_counts["fp"] else 0.0
        r = row_counts["tp"] / (row_counts["tp"] + row_counts["fn"]) if row_counts["tp"] + row_counts["fn"] else 0.0
        f1 = 2 * p * r / (p + r) if p + r else 0.0
        f1s.append(f1)
        per_label[label] = {**row_counts, "precision": p, "recall": r, "f1": f1, "threshold": thresholds[label]}
    return {
        "records": len(rows),
        "eligible_label_count": len(eligible),
        "eligible_labels": eligible,
        "observed_label_cells": observed_cells,
        "observed_positive_support": observed_positive,
        "observed_negative_support": observed_negative,
        "tp": tp, "fp": fp, "fn": fn,
        "micro_precision": precision,
        "micro_recall": recall,
        "micro_f1": 2 * precision * recall / (precision + recall) if precision + recall else 0.0,
        "macro_f1": float(np.mean(f1s)) if f1s else 0.0,
        "exact_set_accuracy": float(np.mean(exact_matches)) if exact_matches else 0.0,
        "mean_sample_f1": float(np.mean(sample_f1)) if sample_f1 else 0.0,
        "mean_accepted_emotions": float(np.mean(accepted_per_message)) if accepted_per_message else 0.0,
        "accepted_count_distribution": {str(count): accepted_per_message.count(count) for count in sorted(set(accepted_per_message))},
        "messages_with_10plus_accepted": sum(count >= 10 for count in accepted_per_message),
        "abstentions": abstentions,
        "abstention_rate": abstentions / len(rows) if rows else 0.0,
        "per_label": per_label,
    }, case_predictions


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    parser.add_argument("--batch-size", type=int, default=16)
    parser.add_argument("--output", type=Path, default=ROOT / "storage/app/audit-recovery/emotion35_v2_no_weak_fair_comparison_20261104.json")
    args = parser.parse_args()

    import torch
    device = "cuda" if args.device == "auto" and torch.cuda.is_available() else ("cpu" if args.device == "auto" else args.device)
    if device == "cuda" and not torch.cuda.is_available():
        raise RuntimeError("CUDA requested but unavailable")
    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    taxonomy_version = str(taxonomy["version"])
    calibrations = {
        name: load_calibration_artifact(CALIBRATIONS[name], labels, taxonomy_version=taxonomy_version, checkpoint_path=checkpoint)
        for name, checkpoint in CHECKPOINTS.items()
    }
    eligible = [label for label in labels if calibrations["no_weak_recovery"]["label_status"][label]["status"] == "calibrated"]
    v2_eligible = [label for label in labels if calibrations["v2"]["label_status"][label]["status"] == "calibrated"]
    if len(eligible) != 15 or not set(eligible).issubset(v2_eligible):
        raise RuntimeError("expected the recovery checkpoint's 15 calibrated labels to be supported by v2")

    test_rows = {name: _source_rows(DATASETS[name]) for name in DATASETS}
    common_rows = _verify_common_source_records(test_rows["v2"], test_rows["no_weak_recovery"])
    if len(common_rows) != 2988:
        raise RuntimeError(f"expected 2,988 identical public GoEmotions records, found {len(common_rows)}")
    texts = [str(row.get("text") or "") for row in common_rows]
    models: dict[str, dict] = {}
    for name, checkpoint in CHECKPOINTS.items():
        probabilities, elapsed = _predict(checkpoint, texts, device, args.batch_size)
        artifact = calibrations[name]
        thresholds = {label: float(artifact["thresholds"][label]) for label in eligible}
        metrics, cases = _metrics(common_rows, probabilities, labels, eligible, thresholds)
        misclassified = [case for case in cases if case["false_positive_labels"] or case["missed_labels"]]
        exact = [case for case in cases if not case["false_positive_labels"] and not case["missed_labels"]]
        models[name] = {
            "checkpoint": str(checkpoint.relative_to(ROOT)),
            "checkpoint_sha256": artifact["checkpoint"]["sha256"],
            "calibration_artifact": str(CALIBRATIONS[name].relative_to(ROOT)),
            "eligible_calibrated_labels": eligible,
            "inference_seconds": elapsed,
            "matched_public_source_metrics": metrics,
            "examples": {"misclassified_first_12": misclassified[:12], "exact_match_first_6": exact[:6]},
        }

    # Supplementary Filipino/Taglish comparison on the same frozen, fully
    # observed 36-case synthetic diagnostic. Scores are provisional only.
    diagnostic = json.loads(FROZEN_DIAGNOSTIC.read_text(encoding="utf-8"))
    diagnostic_rows = []
    for original in diagnostic["cases"]:
        row = dict(original)
        row["text"] = str(row.get("text") or row.get("message") or "")
        row["labels"] = list(row.get("labels") or row.get("provisional_positive_labels") or [])
        diagnostic_rows.append(row)
    diagnostic_texts = [row["text"] for row in diagnostic_rows]
    diagnostic_results = {}
    for name, checkpoint in CHECKPOINTS.items():
        probs, elapsed = _predict(checkpoint, diagnostic_texts, device, args.batch_size)
        artifact = calibrations[name]
        thresholds = {label: float(artifact["thresholds"][label]) for label in eligible}
        metrics, cases = _metrics(diagnostic_rows, probs, labels, eligible, thresholds)
        by_language = {}
        for language in sorted({str(row.get("language", "UNKNOWN")).upper() for row in diagnostic_rows}):
            subset_indices = [index for index, row in enumerate(diagnostic_rows) if str(row.get("language", "UNKNOWN")).upper() == language]
            subset_rows = [diagnostic_rows[index] for index in subset_indices]
            subset_probs = probs[subset_indices]
            by_language[language] = _metrics(subset_rows, subset_probs, labels, eligible, thresholds)[0]
        language_examples = [row for row in cases if str(row.get("language", "")).upper() in {"FILIPINO", "TAGLISH"}]
        diagnostic_results[name] = {
            "classification": "synthetic_provisional_diagnostic_not_independent_validation",
            "dataset_sha256": sha256_file(FROZEN_DIAGNOSTIC),
            "inference_seconds": elapsed,
            "matched_15_label_metrics": metrics,
            "by_language": by_language,
            "filipino_taglish_examples": language_examples,
        }

    result = {
        "schema": "emotion35-v2-no-weak-matched-comparison-v1",
        "evaluation_only": True,
        "device": device,
        "same_eligible_labels": eligible,
        "primary_evaluation": {
            "source": "public GoEmotions source-native test intersection",
            "records": len(common_rows),
            "record_identity_fields": ["source_dataset", "source_id"],
            "verified_equal_fields": ["text", "labels", "observed_labels", "language", "source_group", "split"],
            "observation_masks_preserved": True,
            "metrics": {name: value["matched_public_source_metrics"] for name, value in models.items()},
        },
        "models": models,
        "filipino_taglish_supplement": diagnostic_results,
        "independently_evaluated_records": 0,
        "limitations": [
            "The primary comparison contains only English GoEmotions source-native test records.",
            "The 36-case Filipino/Taglish supplement is synthetic and provisional, not independently validated.",
            "The models were calibrated on separate validation sets, but the common test records, eligible 15 labels, masks, and scoring rules are identical.",
            "The 20 labels uncalibrated for the no-weak checkpoint are excluded from the matched-15 metrics and cannot be emitted by its inference loader.",
        ],
        "deployment_recommendation": "Keep v2 as adviser-demo default pending independently reviewed Filipino/Taglish evaluation; do not promote the no-weak checkpoint from synthetic results alone.",
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    if args.output.exists():
        raise FileExistsError(f"refusing to overwrite comparison: {args.output}")
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    summary = {
        name: {
            "source_micro_f1": round(value["matched_public_source_metrics"]["micro_f1"], 5),
            "source_macro_f1": round(value["matched_public_source_metrics"]["macro_f1"], 5),
            "source_precision": round(value["matched_public_source_metrics"]["micro_precision"], 5),
            "source_recall": round(value["matched_public_source_metrics"]["micro_recall"], 5),
        }
        for name, value in models.items()
    }
    print(json.dumps({"output": str(args.output), "device": device, "common_source_records": len(common_rows), "eligible_labels": len(eligible), "summary": summary}, indent=2))


if __name__ == "__main__":
    main()
