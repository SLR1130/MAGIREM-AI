"""Create separate evidence-gated calibration artifacts for existing research checkpoints.

No checkpoint weights or historical thresholds.json files are modified.
Thresholds are derived only from the checkpoint's validation split.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from emotion35_calibration import calibrate_validation, sha256_file
from emotion_35_multilabel_pipeline import _jsonl_path, _records, _text, load_taxonomy


CHECKPOINTS = {
    "v2": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v2_20261026",
    "v3": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v3_20261026",
    "v4_quarantined": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v4_weak_20261103",
    "recovery_masks_fixed_diagnostic": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_recovery_masks_fixed_norms_20261103",
    "recovery_no_weak_diagnostic": ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_recovery_no_weak_20261103",
}


def checkpoint_labels(path: Path, labels: list[str], taxonomy_version: str) -> None:
    mapping_path = path / "label_mapping.json"
    config_path = path / "config.json"
    if not mapping_path.is_file() or not config_path.is_file():
        raise RuntimeError(f"checkpoint mapping/config missing: {path}")
    mapping = json.loads(mapping_path.read_text(encoding="utf-8"))
    config = json.loads(config_path.read_text(encoding="utf-8"))
    if mapping.get("labels") != labels or mapping.get("taxonomy_version") != taxonomy_version:
        raise RuntimeError(f"checkpoint taxonomy mapping mismatch: {path}")
    configured_width = config.get("num_labels", len(config.get("id2label") or {}))
    if int(configured_width) != len(labels):
        raise RuntimeError(f"checkpoint output width mismatch: {path}")
    configured = config.get("id2label") or {}
    if configured:
        resolved = [configured.get(str(index), configured.get(index)) for index in range(len(labels))]
        if resolved != labels:
            raise RuntimeError(f"checkpoint config id2label order mismatch: {path}")


def predict_validation(path: Path, rows: list[dict], labels: list[str], device: str, batch_size: int) -> np.ndarray:
    import torch
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(str(path), local_files_only=True)
    if int(model.config.num_labels) != len(labels):
        raise RuntimeError(f"loaded model has {model.config.num_labels}, expected {len(labels)} outputs")
    model.to(device).eval()
    result = []
    try:
        with torch.no_grad():
            for start in range(0, len(rows), batch_size):
                texts = [_text(row) for row in rows[start:start + batch_size]]
                encoded = tokenizer(texts, return_tensors="pt", padding=True, truncation=True, max_length=256)
                encoded = {key: value.to(device) for key, value in encoded.items()}
                logits = model(**encoded).logits
                result.append(torch.sigmoid(logits).detach().cpu().numpy())
    finally:
        del model, tokenizer
        if str(device).startswith("cuda"):
            torch.cuda.empty_cache()
    values = np.concatenate(result, axis=0) if result else np.empty((0, len(labels)))
    if values.shape != (len(rows), len(labels)):
        raise RuntimeError("validation inference returned an unexpected shape")
    return values


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", type=Path, default=ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final")
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    parser.add_argument("--batch-size", type=int, default=16)
    parser.add_argument("--minimum-positives", type=int, default=5)
    parser.add_argument("--minimum-negatives", type=int, default=5)
    parser.add_argument("--checkpoints", nargs="*", choices=tuple(CHECKPOINTS), default=list(CHECKPOINTS))
    args = parser.parse_args()

    import torch
    if args.batch_size < 1:
        parser.error("--batch-size must be positive")
    if args.device == "auto":
        device = "cuda" if torch.cuda.is_available() else "cpu"
    else:
        device = args.device
    if device == "cuda" and not torch.cuda.is_available():
        raise RuntimeError("CUDA requested but unavailable")

    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    taxonomy_version = str(taxonomy["version"])
    args.output_dir.mkdir(parents=True, exist_ok=True)
    run_path = args.output_dir / "recalibration_run.json"
    if run_path.exists():
        raise FileExistsError(f"refusing to overwrite prior calibration run: {run_path}")
    results: dict[str, dict] = {}
    for name in args.checkpoints:
        checkpoint = CHECKPOINTS[name]
        if not checkpoint.is_dir():
            raise FileNotFoundError(checkpoint)
        weights = checkpoint / "model.safetensors"
        manifest_path = checkpoint / "dataset_manifest.json"
        old_thresholds_path = checkpoint / "thresholds.json"
        if not weights.is_file() or not manifest_path.is_file() or not old_thresholds_path.is_file():
            raise RuntimeError(f"checkpoint calibration inputs missing for {name}")
        checkpoint_labels(checkpoint, labels, taxonomy_version)
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        dataset_dir = Path(manifest["dataset_dir"])
        if not dataset_dir.is_absolute():
            dataset_dir = ROOT / dataset_dir
        validation_path = _jsonl_path(dataset_dir, "validation")
        validation_rows = list(_records(validation_path))
        probabilities = predict_validation(checkpoint, validation_rows, labels, device, args.batch_size)
        artifact = calibrate_validation(
            validation_rows,
            probabilities,
            labels,
            taxonomy_version=taxonomy_version,
            checkpoint_id=name,
            checkpoint_sha256=sha256_file(weights),
            validation_sha256=sha256_file(validation_path),
            minimum_positives=args.minimum_positives,
            minimum_negatives=args.minimum_negatives,
        )
        artifact["historical_thresholds"] = {
            "path": str(old_thresholds_path.relative_to(ROOT)),
            "sha256": sha256_file(old_thresholds_path),
            "preserved_unchanged": True,
        }
        artifact["checkpoint_role"] = (
            "adviser_demo_baseline" if name == "v2" else
            "quarantined_raw_diagnostic" if name == "v4_quarantined" else
            "diagnostic_only"
        )
        output_path = args.output_dir / f"{name}_calibration.json"
        if output_path.exists():
            raise FileExistsError(f"refusing to overwrite calibration artifact: {output_path}")
        output_path.write_text(json.dumps(artifact, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        calibrated = [label for label in labels if artifact["label_status"][label]["status"] == "calibrated"]
        results[name] = {
            "artifact": str(output_path.relative_to(ROOT)),
            "checkpoint_sha256": artifact["checkpoint"]["sha256"],
            "validation_sha256": artifact["validation_provenance"]["sha256"],
            "validation_records": len(validation_rows),
            "calibrated_label_count": len(calibrated),
            "uncalibrated_labels": [label for label in labels if label not in calibrated],
            "label_support": {label: {"status": artifact["label_status"][label]["status"], "positives": artifact["label_status"][label]["observed_positive_count"], "negatives": artifact["label_status"][label]["observed_negative_count"], "unknown": artifact["label_status"][label]["unknown_count"]} for label in labels},
        }
        print(json.dumps({"checkpoint": name, "calibrated_labels": len(calibrated), "validation_records": len(validation_rows), "artifact": str(output_path)}, ensure_ascii=False))

    run = {
        "schema": "emotion35-checkpoint-recalibration-run-v1",
        "calibration_schema": "mindcare-emotion35-calibration-v1",
        "device": device,
        "minimum_observed_positive": args.minimum_positives,
        "minimum_observed_negative": args.minimum_negatives,
        "diagnostic_was_not_used_for_calibration": True,
        "models": results,
    }
    run_path.write_text(json.dumps(run, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"run_artifact": str(run_path), "device": device, "models": {key: value["calibrated_label_count"] for key, value in results.items()}}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
