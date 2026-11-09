"""Report-only comparison of historical and evidence-gated thresholds.

The frozen 36-case synthetic diagnostic is never used to fit thresholds.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from emotion35_calibration import accepted_predictions, load_calibration_artifact, sha256_file
from emotion_35_multilabel_pipeline import _jsonl_path, _records, _text, load_taxonomy


CHECKPOINTS = {
    "v2": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v2_20261026",
    "v3": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v3_20261026",
    "v4_quarantined": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v4_weak_20261103",
    "recovery_masks_fixed_diagnostic": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_recovery_masks_fixed_norms_20261103",
    "recovery_no_weak_diagnostic": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_recovery_no_weak_20261103",
}
ARTIFACTS = ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final"
DIAGNOSTIC = ROOT / "storage/app/audit-recovery/emotion35_recovery_complete_diagnostic_20261103.json"


def infer(path: Path, texts: list[str], labels: list[str], device: str, batch_size: int) -> tuple[np.ndarray, float]:
    import time
    import torch
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    started = time.perf_counter()
    tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(str(path), local_files_only=True).to(device).eval()
    if int(model.config.num_labels) != len(labels):
        raise RuntimeError(f"output width mismatch for {path}")
    chunks = []
    try:
        with torch.no_grad():
            for offset in range(0, len(texts), batch_size):
                encoded = tokenizer(texts[offset:offset + batch_size], return_tensors="pt", padding=True, truncation=True, max_length=256)
                encoded = {key: value.to(device) for key, value in encoded.items()}
                chunks.append(torch.sigmoid(model(**encoded).logits).cpu().numpy())
    finally:
        del model, tokenizer
        if device.startswith("cuda"):
            torch.cuda.empty_cache()
    return np.concatenate(chunks, axis=0), time.perf_counter() - started


def historical_thresholds(path: Path, labels: list[str]) -> tuple[dict[str, float], list[str]]:
    raw = json.loads((path / "thresholds.json").read_text(encoding="utf-8"))
    values = raw.get("thresholds", raw) if isinstance(raw, dict) else None
    if not isinstance(values, dict):
        raise RuntimeError(f"historical threshold map is malformed: {path}")
    # This fallback reproduces the legacy inference behavior only for the
    # before comparison. New inference uses strict artifacts and never falls
    # back to a default.
    missing = [label for label in labels if label not in values]
    result = {}
    for label in labels:
        if label in values and values[label] is None:
            raise RuntimeError(f"historical threshold is explicitly null for {label}; cannot reproduce default inference")
        value = values[label] if label in values else 0.5
        value = float(value)
        if not 0 < value < 1:
            raise RuntimeError(f"invalid historical threshold for {label}")
        result[label] = value
    return result, missing


def score_cases(rows: list[dict], probabilities: np.ndarray, labels: list[str], thresholds: dict[str, float | None], calibrated: set[str]) -> tuple[dict, list[dict]]:
    tp = fp = fn = 0
    per_label = {}
    accepted_counts = []
    case_details = []
    for index, row in enumerate(rows):
        positives = set(row["provisional_positive_labels"])
        observed = set(row["observed_labels"])
        accepted = []
        score_rows = []
        case_tp = case_fp = case_fn = 0
        for label_index, label in enumerate(labels):
            score = float(probabilities[index, label_index])
            threshold = thresholds.get(label)
            is_calibrated = label in calibrated
            decision = bool(is_calibrated and threshold is not None and score >= float(threshold))
            if decision:
                accepted.append(label)
            if is_calibrated and label in observed:
                truth = label in positives
                if truth and decision:
                    tp += 1; case_tp += 1
                elif not truth and decision:
                    fp += 1; case_fp += 1
                elif truth and not decision:
                    fn += 1; case_fn += 1
                values = per_label.setdefault(label, {"support": 0, "predicted_positive": 0, "tp": 0, "fp": 0, "fn": 0})
                values["support"] += int(truth)
                values["predicted_positive"] += int(decision)
                values["tp"] += int(truth and decision)
                values["fp"] += int((not truth) and decision)
                values["fn"] += int(truth and not decision)
            score_rows.append({
                "emotion": label,
                "probability": score,
                "threshold": float(threshold) if is_calibrated and threshold is not None else None,
                "calibration_status": "calibrated" if is_calibrated else "uncalibrated",
                "accepted": decision,
                "expected_positive": label in positives,
            })
        accepted_counts.append(len(accepted))
        case_details.append({
            "case_id": row["case_id"],
            "language": row.get("language", "UNKNOWN"),
            "expected_positive_labels": sorted(positives),
            "accepted_labels": accepted,
            "accepted_count": len(accepted),
            "false_positive_count": case_fp,
            "false_negative_count": case_fn,
            "abstained": len(accepted) == 0,
            "top_scores": sorted(score_rows, key=lambda item: item["probability"], reverse=True)[:8],
        })
    for value in per_label.values():
        p = value["tp"] / (value["tp"] + value["fp"]) if value["tp"] + value["fp"] else 0.0
        r = value["tp"] / (value["tp"] + value["fn"]) if value["tp"] + value["fn"] else 0.0
        value["precision"] = p
        value["recall"] = r
        value["f1"] = 2 * p * r / (p + r) if p + r else 0.0
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    per_label_macro = [per_label[label]["f1"] for label in labels if label in per_label]
    by_language = {}
    for language in sorted({str(row.get("language", "UNKNOWN")).upper() for row in rows}):
        positions = [i for i, row in enumerate(rows) if str(row.get("language", "UNKNOWN")).upper() == language]
        local_tp = local_fp = local_fn = 0
        local_observed_cells = local_positive = local_negative = 0
        for i in positions:
            detail = case_details[i]
            expected = set(detail["expected_positive_labels"])
            accepted = set(detail["accepted_labels"])
            local_tp += len((expected & accepted) & calibrated)
            local_fp += len((accepted - expected) & calibrated)
            local_fn += len((expected - accepted) & calibrated)
        local_precision = local_tp / (local_tp + local_fp) if local_tp + local_fp else 0.0
        local_recall = local_tp / (local_tp + local_fn) if local_tp + local_fn else 0.0
        by_language[language] = {
            "records": len(positions),
            "micro_precision": local_precision,
            "micro_recall": local_recall,
            "micro_f1": 2 * local_precision * local_recall / (local_precision + local_recall) if local_precision + local_recall else 0.0,
            "mean_accepted_labels": float(np.mean([accepted_counts[i] for i in positions])) if positions else 0.0,
            "abstentions": sum(case_details[i]["abstained"] for i in positions),
        }
    metrics = {
        "records": len(rows),
        "calibrated_label_count": len(calibrated),
        "uncalibrated_label_count": len(labels) - len(calibrated),
        "evaluated_label_decisions": len(rows) * len(calibrated),
        "provisional_expected_positive_support_calibrated_labels": sum(item["support"] for item in per_label.values()),
        "tp": tp,
        "fp": fp,
        "fn": fn,
        "micro_precision": precision,
        "micro_recall": recall,
        "micro_f1": 2 * precision * recall / (precision + recall) if precision + recall else 0.0,
        "macro_f1_over_evaluated_labels": float(np.mean(per_label_macro)) if per_label_macro else 0.0,
        "accepted_labels_per_message": {
            "mean": float(np.mean(accepted_counts)),
            "median": float(np.median(accepted_counts)),
            "min": int(min(accepted_counts, default=0)),
            "max": int(max(accepted_counts, default=0)),
            "ten_or_more_messages": int(sum(value >= 10 for value in accepted_counts)),
            "twenty_or_more_messages": int(sum(value >= 20 for value in accepted_counts)),
        },
        "abstentions_no_accepted_calibrated_label": int(sum(value == 0 for value in accepted_counts)),
        "uncalibrated_label_decisions_abstained": len(rows) * (len(labels) - len(calibrated)),
        "per_label": per_label,
        "by_language": by_language,
    }
    return metrics, case_details


def score_source_native(rows: list[dict], probabilities: np.ndarray, labels: list[str], thresholds: dict[str, float | None], calibrated: set[str]) -> dict:
    tp = fp = fn = observed_positive = observed_negative = observed_cells = 0
    per_label = {}
    cardinality = []
    by_language_counts: dict[str, list[int]] = {}
    by_source_counts: dict[str, list[int]] = {}
    for row_index, row in enumerate(rows):
        positives = set(row.get("labels") or [])
        observed = set(row.get("observed_labels") or [])
        local_tp = local_fp = local_fn = 0
        local_observed_cells = local_positive = local_negative = 0
        local_cardinality = 0
        for index, label in enumerate(labels):
            if label not in calibrated or label not in observed:
                continue
            threshold = thresholds[label]
            decision = bool(threshold is not None and probabilities[row_index, index] >= float(threshold))
            truth = label in positives
            observed_cells += 1
            observed_positive += int(truth)
            observed_negative += int(not truth)
            local_observed_cells += 1
            local_positive += int(truth)
            local_negative += int(not truth)
            counts = per_label.setdefault(label, {"support": 0, "observed_negative_support": 0, "predicted_positive": 0, "tp": 0, "fp": 0, "fn": 0})
            counts["support"] += int(truth)
            counts["observed_negative_support"] += int(not truth)
            counts["predicted_positive"] += int(decision)
            counts["tp"] += int(truth and decision)
            counts["fp"] += int(not truth and decision)
            counts["fn"] += int(truth and not decision)
            if decision:
                local_cardinality += 1
            if truth and decision:
                tp += 1; local_tp += 1
            elif not truth and decision:
                fp += 1; local_fp += 1
            elif truth and not decision:
                fn += 1; local_fn += 1
        cardinality.append(local_cardinality)
        language = str(row.get("language", "UNKNOWN")).upper()
        language_counts = by_language_counts.setdefault(language, [0, 0, 0, 0, 0, 0, 0])
        language_counts[0] += local_tp; language_counts[1] += local_fp; language_counts[2] += local_fn; language_counts[3] += 1
        language_counts[4] += local_observed_cells; language_counts[5] += local_positive; language_counts[6] += local_negative
        source = str(row.get("source_dataset") or row.get("source") or "unspecified_source")
        source_counts = by_source_counts.setdefault(source, [0, 0, 0, 0, 0, 0, 0])
        source_counts[0] += local_tp; source_counts[1] += local_fp; source_counts[2] += local_fn; source_counts[3] += 1
        source_counts[4] += local_observed_cells; source_counts[5] += local_positive; source_counts[6] += local_negative
    for value in per_label.values():
        precision = value["tp"] / (value["tp"] + value["fp"]) if value["tp"] + value["fp"] else 0.0
        recall = value["tp"] / (value["tp"] + value["fn"]) if value["tp"] + value["fn"] else 0.0
        value.update({"precision": precision, "recall": recall, "f1": 2 * precision * recall / (precision + recall) if precision + recall else 0.0})
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    def grouped(values: dict[str, list[int]]) -> dict[str, dict[str, float | int]]:
        output = {}
        for key, (group_tp, group_fp, group_fn, count, observed_count, positive_count, negative_count) in values.items():
            group_precision = group_tp / (group_tp + group_fp) if group_tp + group_fp else 0.0
            group_recall = group_tp / (group_tp + group_fn) if group_tp + group_fn else 0.0
            output[key] = {"records": count, "observed_cells": observed_count, "positive_support": positive_count, "negative_support": negative_count, "tp": group_tp, "fp": group_fp, "fn": group_fn, "precision": group_precision, "recall": group_recall, "micro_f1": 2 * group_precision * group_recall / (group_precision + group_recall) if group_precision + group_recall else 0.0}
        return output
    return {
        "records": len(rows),
        "calibrated_label_count": len(calibrated),
        "uncalibrated_labels": [label for label in labels if label not in calibrated],
        "observed_label_cells_in_calibrated_outputs": observed_cells,
        "observed_positive_support": observed_positive,
        "observed_negative_support": observed_negative,
        "tp": tp, "fp": fp, "fn": fn,
        "micro_precision": precision,
        "micro_recall": recall,
        "micro_f1": 2 * precision * recall / (precision + recall) if precision + recall else 0.0,
        "macro_f1_over_calibrated_labels_with_observed_test_cells": float(np.mean([v["f1"] for v in per_label.values()])) if per_label else 0.0,
        "mean_accepted_labels_per_message": float(np.mean(cardinality)) if cardinality else 0.0,
        "messages_with_10plus_accepted": int(sum(value >= 10 for value in cardinality)),
        "messages_with_20plus_accepted": int(sum(value >= 20 for value in cardinality)),
        "messages_with_all_35_accepted": int(sum(value == len(labels) for value in cardinality)),
        "abstentions_no_accepted_calibrated_label": int(sum(value == 0 for value in cardinality)),
        "false_positive_rate_over_observed_negatives": fp / observed_negative if observed_negative else None,
        "per_label": per_label,
        "by_language": grouped(by_language_counts),
        "by_source_dataset": grouped(by_source_counts),
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    parser.add_argument("--batch-size", type=int, default=16)
    parser.add_argument("--output", type=Path, default=ARTIFACTS / "frozen_36_case_before_after_20261104_v2.json")
    args = parser.parse_args()
    import torch
    device = "cuda" if args.device == "auto" and torch.cuda.is_available() else ("cpu" if args.device == "auto" else args.device)
    if device == "cuda" and not torch.cuda.is_available():
        raise RuntimeError("CUDA requested but unavailable")
    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    diagnostic = json.loads(DIAGNOSTIC.read_text(encoding="utf-8"))
    rows = diagnostic["cases"]
    if len(rows) != 36:
        raise RuntimeError(f"expected the frozen 36-case diagnostic, found {len(rows)}")
    seen_ids = [row["case_id"] for row in rows]
    if len(set(seen_ids)) != len(rows):
        raise RuntimeError("diagnostic contains duplicate case IDs")
    allowed = set(labels)
    for row in rows:
        positives = set(row["provisional_positive_labels"])
        negatives = set(row["provisional_negative_labels"])
        observed = set(row["observed_labels"])
        if positives | negatives != observed or positives & negatives or observed != allowed:
            raise RuntimeError(f"diagnostic is not fully observed and internally consistent: {row['case_id']}")
        if (positives | negatives) - allowed:
            raise RuntimeError(f"invalid taxonomy key in diagnostic: {row['case_id']}")
    result = {
        "schema": "emotion35-calibration-frozen-diagnostic-comparison-v1",
        "diagnostic_path": str(DIAGNOSTIC.relative_to(ROOT)),
        "diagnostic_sha256": sha256_file(DIAGNOSTIC),
        "diagnostic_records": len(rows),
        "diagnostic_kind": "frozen synthetic provisional diagnostic; not ground-truth validation and not used for calibration",
        "device": device,
        "models": {},
    }
    for name, checkpoint in CHECKPOINTS.items():
        artifact_name = "v4_quarantined" if name == "v4_quarantined" else name
        artifact_path = ARTIFACTS / f"{artifact_name}_calibration.json"
        artifact = load_calibration_artifact(artifact_path, labels, taxonomy_version=str(taxonomy["version"]), checkpoint_path=checkpoint)
        historical, missing_old = historical_thresholds(checkpoint, labels)
        model_manifest = json.loads((checkpoint / "dataset_manifest.json").read_text(encoding="utf-8"))
        dataset_dir = Path(model_manifest["dataset_dir"])
        if not dataset_dir.is_absolute():
            dataset_dir = ROOT / dataset_dir
        test_path = _jsonl_path(dataset_dir, "test")
        source_test_rows = list(_records(test_path))
        texts = [_text(row) for row in rows] + [_text(row) for row in source_test_rows]
        all_probabilities, seconds = infer(checkpoint, texts, labels, device, args.batch_size)
        diagnostic_probabilities = all_probabilities[:len(rows)]
        source_test_probabilities = all_probabilities[len(rows):]
        historical_calibrated = set(labels)
        old_metrics, old_cases = score_cases(rows, diagnostic_probabilities, labels, historical, historical_calibrated)
        new_calibrated = {label for label in labels if artifact["label_status"][label]["status"] == "calibrated"}
        new_thresholds = artifact["thresholds"]
        old_matched_metrics, old_matched_cases = score_cases(rows, diagnostic_probabilities, labels, historical, new_calibrated)
        new_metrics, new_cases = score_cases(rows, diagnostic_probabilities, labels, new_thresholds, new_calibrated)
        source_before = score_source_native(source_test_rows, source_test_probabilities, labels, historical, historical_calibrated)
        source_before_matched = score_source_native(source_test_rows, source_test_probabilities, labels, historical, new_calibrated)
        source_after = score_source_native(source_test_rows, source_test_probabilities, labels, new_thresholds, new_calibrated)
        result["models"][name] = {
            "checkpoint": str(checkpoint.relative_to(ROOT)),
            "checkpoint_sha256": artifact["checkpoint"]["sha256"],
            "calibration_artifact": str(artifact_path.relative_to(ROOT)),
            "role": artifact.get("checkpoint_role"),
            "v4_quarantined": name == "v4_quarantined",
            "inference_seconds": seconds,
            "source_native_test": {
                "path": str(test_path.relative_to(ROOT)),
                "sha256": sha256_file(test_path),
                "records": len(source_test_rows),
                "interpretation": "source-native observed-label masked evaluation; unobserved label cells excluded; synthetic/weak provenance remains separate by source metadata",
                "historical_before": source_before,
                "historical_before_same_calibrated_label_coverage": source_before_matched,
                "evidence_gated_after": source_after,
            },
            "historical_before": {
                "legacy_missing_threshold_fallback_count": len(missing_old),
                "missing_threshold_labels": missing_old,
                "metrics": old_metrics,
                "cases": old_cases,
                "same_calibrated_label_coverage": {"metrics": old_matched_metrics, "cases": old_matched_cases},
            },
            "evidence_gated_after": {
                "calibrated_labels": sorted(new_calibrated),
                "uncalibrated_labels": [label for label in labels if label not in new_calibrated],
                "metrics": new_metrics,
                "cases": new_cases,
            },
        }
    result["isolation"] = {
        "v2_remains_adviser_demo_default": True,
        "v4_remains_quarantined": True,
        "recovery_models_remain_diagnostic_only": True,
        "production_six_class_untouched": True,
        "no_retraining_performed": True,
        "threshold_selection_used_only_validation": True,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    if args.output.exists():
        raise FileExistsError(f"refusing to overwrite comparison report: {args.output}")
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    summary = {name: {"before_micro_f1_same_coverage": data["historical_before"]["same_calibrated_label_coverage"]["metrics"]["micro_f1"], "after_micro_f1_same_coverage": data["evidence_gated_after"]["metrics"]["micro_f1"], "before_mean_cardinality_same_coverage": data["historical_before"]["same_calibrated_label_coverage"]["metrics"]["accepted_labels_per_message"]["mean"], "after_mean_cardinality_same_coverage": data["evidence_gated_after"]["metrics"]["accepted_labels_per_message"]["mean"], "after_calibrated_labels": data["evidence_gated_after"]["metrics"]["calibrated_label_count"], "after_fp": data["evidence_gated_after"]["metrics"]["fp"], "after_fn": data["evidence_gated_after"]["metrics"]["fn"], "after_abstentions": data["evidence_gated_after"]["metrics"]["abstentions_no_accepted_calibrated_label"]} for name, data in result["models"].items()}
    print(json.dumps({"output": str(args.output), "device": device, "summary": summary}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
