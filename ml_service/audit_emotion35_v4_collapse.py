"""Read-only audit for the Emotion35 v4 all-positive prediction failure."""

from __future__ import annotations

import argparse
import json
from collections import Counter, defaultdict
from pathlib import Path

import numpy as np
import torch
from safetensors.torch import load_file
from transformers import AutoModelForSequenceClassification, AutoTokenizer

ROOT = Path(__file__).resolve().parents[1]
AUDIT = ROOT / "storage" / "app" / "audit-recovery"
DATASET = AUDIT / "emotion35_v4_weak_supervision_20261103"
CHECKPOINTS = {
    "v2": AUDIT / "emotion_model_35_multilabel_prototype_v2_20261026",
    "v3": AUDIT / "emotion_model_35_multilabel_prototype_v3_20261026",
    "v4": AUDIT / "emotion_model_35_multilabel_prototype_v4_weak_20261103",
}


def rows(path: Path) -> list[dict]:
    return [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]


def labels() -> list[str]:
    taxonomy = json.loads((ROOT / "config/emotion_taxonomy.json").read_text(encoding="utf-8"))
    return [item["key"] for item in taxonomy["emotions"]]


def mask_counts(train_rows: list[dict], vocabulary: list[str]) -> dict:
    overall = Counter()
    by_source = defaultdict(Counter)
    by_label = {label: Counter() for label in vocabulary}
    for row in train_rows:
        positives = set(row.get("labels") or [])
        observed = row.get("observed_labels")
        observed_set = set(vocabulary if observed is None else observed)
        weak = bool(row.get("weak_supervision"))
        source = "weak_synthetic" if weak else str(row.get("source_dataset", "unknown"))
        for label in vocabulary:
            if label in observed_set:
                state = "positive" if label in positives else "negative"
            else:
                state = "unknown"
            overall[state] += 1
            by_source[source][state] += 1
            by_label[label][state] += 1
    return {
        "records": len(train_rows),
        "overall_cells": dict(overall),
        "by_source": {key: dict(value) for key, value in sorted(by_source.items())},
        "by_label": {key: dict(value) for key, value in by_label.items()},
    }


def load_model(path: Path, device: torch.device):
    tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(str(path), local_files_only=True)
    model.to(device).eval()
    threshold_data = json.loads((path / "thresholds.json").read_text(encoding="utf-8"))
    return tokenizer, model, threshold_data.get("thresholds", threshold_data)


def checkpoint_integrity(path: Path) -> dict:
    state = load_file(str(path / "model.safetensors"))
    nonfinite_tensors = [
        key for key, tensor in state.items() if not bool(torch.isfinite(tensor).all())
    ]
    config = json.loads((path / "config.json").read_text(encoding="utf-8"))
    return {
        "tensor_count": len(state),
        "nonfinite_tensor_keys": nonfinite_tensors,
        "classifier_weight_shape": list(state["classifier.out_proj.weight"].shape),
        "config_model_type": config.get("model_type"),
        "config_label_count": len(config.get("id2label", {})),
    }


def infer_rows(model_bundle, test_rows: list[dict], vocabulary: list[str], device: torch.device) -> dict:
    tokenizer, model, thresholds = model_bundle
    cardinalities = []
    by_provenance = defaultdict(list)
    with torch.no_grad():
        for row in test_rows:
            encoded = tokenizer(row["text"], return_tensors="pt", truncation=True, max_length=256)
            encoded = {key: value.to(device) for key, value in encoded.items()}
            logits = model(**encoded).logits[0]
            probabilities = torch.sigmoid(logits).detach().cpu().numpy()
            accepted = [
                label for index, label in enumerate(vocabulary)
                if float(probabilities[index]) >= float(thresholds.get(label, 0.5))
            ]
            cardinalities.append(len(accepted))
            provenance = "synthetic" if row.get("synthetic") else "public_source"
            by_provenance[provenance].append(len(accepted))
    arr = np.asarray(cardinalities, dtype=np.int64)
    return {
        "records": int(arr.size),
        "mean_accepted": float(arr.mean()),
        "median_accepted": float(np.median(arr)),
        "max_accepted": int(arr.max()),
        "zero_accepted": int(np.sum(arr == 0)),
        "accepted_10_or_more": int(np.sum(arr >= 10)),
        "accepted_20_or_more": int(np.sum(arr >= 20)),
        "accepted_all_35": int(np.sum(arr == 35)),
        "by_provenance": {
            key: {
                "records": len(values),
                "mean_accepted": float(np.mean(values)),
                "accepted_10_or_more": int(np.sum(np.asarray(values) >= 10)),
                "accepted_20_or_more": int(np.sum(np.asarray(values) >= 20)),
                "accepted_all_35": int(np.sum(np.asarray(values) == 35)),
            }
            for key, values in sorted(by_provenance.items())
        },
    }


def fresh_predictions(bundles, vocabulary: list[str], device: torch.device) -> dict:
    tests = [
        {"id": "neutral", "language": "ENGLISH", "text": "The registrar opens at eight and my appointment is listed for tomorrow."},
        {"id": "sadness", "language": "ENGLISH", "text": "I have been crying since I found out that my grandmother died."},
        {"id": "happiness", "language": "ENGLISH", "text": "I am genuinely happy that my project was accepted."},
        {"id": "fear", "language": "ENGLISH", "text": "I am afraid I will freeze when everyone looks at me during the presentation."},
        {"id": "filipino_stress", "language": "FILIPINO", "text": "Sobrang stress ako sa requirements at hindi ko alam kung alin ang uunahin."},
        {"id": "mixed", "language": "TAGLISH", "text": "Excited ako sa graduation pero kinakabahan ako sa future."},
    ]
    result = {}
    for item in tests:
        result[item["id"]] = {"language": item["language"], "text": item["text"], "models": {}}
        for name, (tokenizer, model, thresholds) in bundles.items():
            with torch.no_grad():
                encoded = tokenizer(item["text"], return_tensors="pt", truncation=True, max_length=256)
                encoded = {key: value.to(device) for key, value in encoded.items()}
                logits = model(**encoded).logits[0]
                probabilities = torch.sigmoid(logits).detach().cpu().numpy()
            scores = [
                {
                    "emotion": label,
                    "logit": float(logits[index].detach().cpu()),
                    "probability": float(probabilities[index]),
                    "threshold": float(thresholds.get(label, 0.5)),
                    "accepted": bool(probabilities[index] >= float(thresholds.get(label, 0.5))),
                }
                for index, label in enumerate(vocabulary)
            ]
            scores.sort(key=lambda value: value["probability"], reverse=True)
            result[item["id"]]["models"][name] = {
                "accepted_count": sum(item["accepted"] for item in scores),
                "accepted": [item["emotion"] for item in scores if item["accepted"]],
                "top_predictions": scores[:8],
                "logit_min": float(np.min([item["logit"] for item in scores])),
                "logit_max": float(np.max([item["logit"] for item in scores])),
            }
    return result


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    parser.add_argument("--output", type=Path, default=AUDIT / "emotion35_v4_collapse_audit_20261103.json")
    args = parser.parse_args()
    device = torch.device("cuda" if args.device == "auto" and torch.cuda.is_available() else args.device if args.device != "auto" else "cpu")
    vocabulary = labels()
    train = rows(DATASET / "train.jsonl")
    test = rows(DATASET / "test.jsonl")
    bundles = {name: load_model(path, device) for name, path in CHECKPOINTS.items()}
    report = {
        "schema": "emotion35-v4-collapse-audit-v1",
        "device": str(device),
        "vocabulary_size": len(vocabulary),
        "checkpoint_integrity": {name: checkpoint_integrity(path) for name, path in CHECKPOINTS.items()},
        "training_masks": mask_counts(train, vocabulary),
        "test_rows": len(test),
        "cardinality": {name: infer_rows(bundle, test, vocabulary, device) for name, bundle in bundles.items()},
        "fresh_predictions": fresh_predictions(bundles, vocabulary, device),
        "sigmoid_application": "torch.sigmoid applied once to model logits before threshold comparison",
        "threshold_source": "checkpoint thresholds.json calibrated on validation split",
        "warning": "Raw diagnostic evidence only; v4 is quarantined and not valid student emotion output.",
    }
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))
    print(f"WROTE={args.output}")


if __name__ == "__main__":
    main()
