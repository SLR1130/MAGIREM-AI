"""Bounded, read-only comparison for the corrected Emotion35 recovery run.

This evaluator normalizes legacy RoBERTa LayerNorm gamma/beta keys in memory,
so the preserved v2 checkpoint is compared without silently reinitializing its
encoder norms. It never writes to production model or student data locations.
"""
from __future__ import annotations

import argparse
import json
import time
from collections import Counter
from pathlib import Path

import numpy as np
import torch
from safetensors.torch import load_file
from transformers import AutoConfig, AutoModelForSequenceClassification, AutoTokenizer
from emotion35_calibration import accepted_predictions, cardinality_warning, load_calibration_artifact

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY = ROOT / "config" / "emotion_taxonomy.json"
CALIBRATION_DIR = ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final"


def read_rows(path: Path) -> list[dict]:
    return [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]


def load_labels() -> tuple[list[str], dict[str, str], str]:
    data = json.loads(TAXONOMY.read_text(encoding="utf-8"))
    emotions = data["emotions"]
    return [item["key"] for item in emotions], {item["key"]: item["category_key"] for item in emotions}, str(data["version"])


def load_research_model(path: Path, calibration_path: Path, labels: list[str], taxonomy_version: str, device: torch.device):
    tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
    config = AutoConfig.from_pretrained(str(path), local_files_only=True)
    config.num_labels = len(labels)
    config.id2label = {index: label for index, label in enumerate(labels)}
    config.label2id = {label: index for index, label in enumerate(labels)}
    config.problem_type = "multi_label_classification"
    model = AutoModelForSequenceClassification.from_config(config)
    state = load_file(str(path / "model.safetensors"), device="cpu")
    normalized = {}
    for key, value in state.items():
        if key.endswith(".LayerNorm.gamma"):
            key = key[:-len(".gamma")] + ".weight"
        elif key.endswith(".LayerNorm.beta"):
            key = key[:-len(".beta")] + ".bias"
        normalized[key] = value
    info = model.load_state_dict(normalized, strict=False)
    if info.missing_keys or info.unexpected_keys:
        raise RuntimeError(json.dumps({"missing": info.missing_keys, "unexpected": info.unexpected_keys}))
    calibration = load_calibration_artifact(calibration_path, labels, taxonomy_version=taxonomy_version, checkpoint_path=path)
    model.to(device).eval()
    return tokenizer, model, calibration


def infer_texts(bundle, texts: list[str], labels: list[str], device: torch.device) -> np.ndarray:
    tokenizer, model, _ = bundle
    values = []
    with torch.no_grad():
        for start in range(0, len(texts), 16):
            encoded = tokenizer(texts[start:start + 16], return_tensors="pt", padding=True, truncation=True, max_length=256)
            encoded = {key: value.to(device) for key, value in encoded.items()}
            values.append(torch.sigmoid(model(**encoded).logits).cpu().numpy())
    return np.concatenate(values, axis=0) if values else np.empty((0, len(labels)))


def masked_metrics(rows: list[dict], probabilities: np.ndarray, labels: list[str], calibration: dict) -> dict:
    target = np.zeros((len(rows), len(labels)), dtype=np.int8)
    mask = np.zeros_like(target)
    for row_index, row in enumerate(rows):
        positives = set(row.get("labels") or [])
        observed = set(row.get("observed_labels") or [])
        for index, label in enumerate(labels):
            target[row_index, index] = int(label in positives)
            mask[row_index, index] = int(label in observed)
    predicted = np.zeros_like(target)
    calibrated = {label for label in labels if calibration["label_status"][label]["status"] == "calibrated"}
    for row_index in range(len(rows)):
        for item in accepted_predictions(probabilities[row_index], labels, calibration):
            if item["accepted"]:
                predicted[row_index, labels.index(item["emotion"])] = 1
    tp = fp = fn = 0
    per_label = {}
    for index, label in enumerate(labels):
        if label not in calibrated:
            per_label[label] = {"status": "uncalibrated", "support": int(target[mask[:, index] > 0, index].sum()), "predicted_positive": 0, "tp": 0, "fp": 0, "fn": 0, "precision": None, "recall": None, "f1": None}
            continue
        valid = mask[:, index] > 0
        truth, pred = target[valid, index], predicted[valid, index]
        a = int(((truth == 1) & (pred == 1)).sum())
        b = int(((truth == 0) & (pred == 1)).sum())
        c = int(((truth == 1) & (pred == 0)).sum())
        precision = a / (a + b) if a + b else 0.0
        recall = a / (a + c) if a + c else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_label[label] = {"support": int(truth.sum()), "predicted_positive": int(pred.sum()), "tp": a, "fp": b, "fn": c, "precision": precision, "recall": recall, "f1": f1}
        tp += a
        fp += b
        fn += c
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    cardinality = predicted.sum(axis=1)
    return {
        "records": len(rows),
        "observed_cells": int(mask.sum()),
        "evaluated_observed_cells_calibrated_labels": int(mask[:, [labels.index(label) for label in calibrated]].sum()) if calibrated else 0,
        "positive_support_calibrated_labels": int(target[:, [labels.index(label) for label in calibrated]][mask[:, [labels.index(label) for label in calibrated]] > 0].sum()) if calibrated else 0,
        "micro_precision": precision,
        "micro_recall": recall,
        "micro_f1": f1,
        "macro_f1_calibrated_labels": float(np.mean([per_label[label]["f1"] for label in calibrated])) if calibrated else 0.0,
        "calibrated_labels": sorted(calibrated),
        "uncalibrated_labels": [label for label in labels if label not in calibrated],
        "accepted_cardinality": {"mean": float(cardinality.mean()), "median": float(np.median(cardinality)), "max": int(cardinality.max()), "zero": int((cardinality == 0).sum()), "ten_or_more": int((cardinality >= 10).sum()), "twenty_or_more": int((cardinality >= 20).sum()), "all_35": int((cardinality == 35).sum())},
        "abstentions_no_accepted_calibrated_label": int((predicted.sum(axis=1) == 0).sum()),
        "uncalibrated_label_decisions_abstained": len(rows) * (len(labels) - len(calibrated)),
        "messages_with_10plus_accepted_labels": int((cardinality >= 10).sum()),
        "messages_with_20plus_accepted_labels": int((cardinality >= 20).sum()),
        "per_label": per_label,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--baseline", type=Path, required=True)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--baseline-calibration", type=Path, default=CALIBRATION_DIR / "v2_calibration.json")
    parser.add_argument("--candidate-calibration", type=Path, default=CALIBRATION_DIR / "recovery_masks_fixed_diagnostic_calibration.json")
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    args = parser.parse_args()
    device = torch.device("cuda" if args.device == "auto" and torch.cuda.is_available() else args.device if args.device != "auto" else "cpu")
    labels, categories, taxonomy_version = load_labels()
    test_rows = read_rows(args.dataset / "test.jsonl")
    fresh = [
        {"id": "neutral_english", "language": "ENGLISH", "text": "The registrar opens at eight and my appointment is listed for tomorrow."},
        {"id": "neutral_filipino", "language": "FILIPINO", "text": "Bukas ang opisina ng registrar ng alas otso at bukas ang appointment ko."},
        {"id": "filipino_stress", "language": "FILIPINO", "text": "Sobrang bigat ng requirements at hindi ko alam kung alin ang uunahin."},
        {"id": "filipino_sadness", "language": "FILIPINO", "text": "Nalulungkot ako mula nang umalis ang kaibigan ko para mag-aral sa ibang lugar."},
        {"id": "filipino_happiness", "language": "FILIPINO", "text": "Masayang-masaya ako dahil pumasa ako sa subject na pinaghirapan ko."},
        {"id": "homesickness", "language": "ENGLISH", "text": "I miss home so much that campus still feels unfamiliar after several weeks."},
        {"id": "guilt", "language": "TAGLISH", "text": "Guilty ako kasi hindi ko natupad yung promise ko sa kapatid ko."},
        {"id": "mixed_excitement_anxiety", "language": "TAGLISH", "text": "Excited ako sa graduation pero kinakabahan din ako sa mga susunod na hakbang."},
    ]
    texts = [row["text"] for row in test_rows]
    bundles = {
        "v2": load_research_model(args.baseline, args.baseline_calibration, labels, taxonomy_version, device),
        "recovery": load_research_model(args.candidate, args.candidate_calibration, labels, taxonomy_version, device),
    }
    report = {"schema": "emotion35-recovery-comparison-v1", "device": str(device), "dataset": str(args.dataset), "records": len(test_rows), "models": {}}
    for name, bundle in bundles.items():
        probabilities = infer_texts(bundle, texts, labels, device)
        report["models"][name] = masked_metrics(test_rows, probabilities, labels, bundle[2])
        language_metrics = {}
        for language in sorted({str(row.get("language", "UNKNOWN")).upper() for row in test_rows}):
            indexes = [index for index, row in enumerate(test_rows) if str(row.get("language", "UNKNOWN")).upper() == language]
            subset_rows = [test_rows[index] for index in indexes]
            language_metrics[language] = masked_metrics(subset_rows, probabilities[indexes], labels, bundle[2])
        report["models"][name]["language_metrics"] = language_metrics
    for row in fresh:
        report.setdefault("fresh_cases", []).append({"id": row["id"], "language": row["language"], "text": row["text"], "models": {}})
        for name, bundle in bundles.items():
            started = time.perf_counter()
            probs = infer_texts(bundle, [row["text"]], labels, device)[0]
            elapsed = (time.perf_counter() - started) * 1000
            scores = accepted_predictions(probs, labels, bundle[2])
            for item in scores:
                item["category"] = categories[item["emotion"]]
            scores.sort(key=lambda item: item["probability"], reverse=True)
            accepted_count = sum(item["accepted"] for item in scores)
            report["fresh_cases"][-1]["models"][name] = {"latency_ms": elapsed, "accepted_count": accepted_count, "cardinality_warning": cardinality_warning(accepted_count), "accepted": [item["emotion"] for item in scores if item["accepted"]], "top_predictions": scores[:8]}
    report["warning"] = "Research-only comparison; masked public-source metrics are not fully observed 35-emotion accuracy and fresh cases are synthetic diagnostics."
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({"output": str(args.output), "device": str(device), "test_records": len(test_rows), "models": list(bundles)}, indent=2))


if __name__ == "__main__":
    main()
