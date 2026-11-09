"""Run isolated v2/no-weak inference on the frozen Filipino/Taglish candidates."""
from __future__ import annotations

import json
import sys
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))
from compare_emotion35_v2_no_weak_matched import CALIBRATIONS, CHECKPOINTS, _metrics, _predict
from emotion35_calibration import load_calibration_artifact, sha256_file
from emotion_35_multilabel_pipeline import load_taxonomy


EVAL_DIR = ROOT / "storage/app/audit-recovery/emotion35_fil_tag_evaluation_20261104"
DATA_PATH = EVAL_DIR / "emotion35_fil_tag_eval_cases.jsonl"
MANIFEST_PATH = EVAL_DIR / "manifest.json"
OUTPUT_PATH = EVAL_DIR / "model_comparison_v2_vs_no_weak.json"


def main() -> None:
    import argparse
    import torch
    parser = argparse.ArgumentParser()
    parser.add_argument("--device", choices=("auto", "cpu", "cuda"), default="auto")
    parser.add_argument("--batch-size", type=int, default=16)
    args = parser.parse_args()
    device = "cuda" if args.device == "auto" and torch.cuda.is_available() else ("cpu" if args.device == "auto" else args.device)
    if device == "cuda" and not torch.cuda.is_available():
        raise RuntimeError("CUDA requested but unavailable")
    manifest = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    if manifest.get("training_eligible") is not False or manifest.get("independently_validated_cases") != 0:
        raise RuntimeError("evaluation package provenance is invalid")
    rows = [json.loads(line) for line in DATA_PATH.read_text(encoding="utf-8").splitlines() if line.strip()]
    if len(rows) != 90 or len({row["case_id"] for row in rows}) != len(rows):
        raise RuntimeError("evaluation cases are missing or duplicated")
    if sha256_file(DATA_PATH) != manifest["jsonl_sha256"]:
        raise RuntimeError("frozen evaluation set hash does not match manifest")
    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    artifacts = {name: load_calibration_artifact(CALIBRATIONS[name], labels, taxonomy_version=str(taxonomy["version"]), checkpoint_path=checkpoint) for name, checkpoint in CHECKPOINTS.items()}
    eligible = [label for label in labels if artifacts["no_weak_recovery"]["label_status"][label]["status"] == "calibrated"]
    if len(eligible) != 15:
        raise RuntimeError("expected 15 calibrated labels for the no-weak checkpoint")
    all_texts = [row["text"] for row in rows]
    scores_by_model = {}
    for name, checkpoint in CHECKPOINTS.items():
        scores, elapsed = _predict(checkpoint, all_texts, device, args.batch_size)
        scores_by_model[name] = {"probabilities": scores, "inference_seconds": elapsed}

    evaluable = [row for row in rows if row["provisional_expected_status"] != "uncertain"]
    metric_rows = []
    for row in evaluable:
        metric_rows.append({
            **row,
            "labels": row["provisional_expected_positive_labels"],
            "observed_labels": row["provisional_evaluated_labels"],
        })
        if len(row["provisional_evaluated_labels"]) != len(labels):
            raise RuntimeError(f"fully evaluated candidate has incomplete provisional mask: {row['case_id']}")

    model_results = {}
    for name in CHECKPOINTS:
        artifact = artifacts[name]
        thresholds = {label: float(artifact["thresholds"][label]) for label in eligible}
        scores = scores_by_model[name]["probabilities"]
        metrics, metric_cases = _metrics(metric_rows, scores[[rows.index(row) for row in evaluable]], labels, eligible, thresholds)
        indexed_cases = {case["case_id"]: case for case in metric_cases}
        predictions = []
        for index, row in enumerate(rows):
            expected = set(row["provisional_expected_positive_labels"])
            accepted = [label for label in eligible if scores[index, labels.index(label)] >= thresholds[label]]
            predictions.append({
                "case_id": row["case_id"],
                "language": row["language"],
                "provisional_expected_status": row["provisional_expected_status"],
                "text": row["text"],
                "expected_positive_labels": sorted(expected),
                "expected_positive_labels_outside_15_label_comparison": sorted(expected - set(eligible)),
                "accepted_15_label_predictions": accepted,
                "raw_probabilities_all_35": {label: float(scores[index, label_index]) for label_index, label in enumerate(labels)},
                "uncalibrated_labels": [label for label in labels if artifact["label_status"][label]["status"] == "uncalibrated"],
                "case_metrics": indexed_cases.get(row["case_id"]),
            })
        by_language = {}
        for language in ("FILIPINO", "TAGLISH"):
            lang_rows = [row for row in metric_rows if row["language"] == language]
            lang_indices = [next(index for index, original in enumerate(rows) if original["case_id"] == row["case_id"]) for row in lang_rows]
            lang_metrics, _ = _metrics(lang_rows, scores[lang_indices], labels, eligible, thresholds)
            by_language[language] = lang_metrics
        model_results[name] = {
            "checkpoint": str(CHECKPOINTS[name].relative_to(ROOT)),
            "calibration_artifact": str(CALIBRATIONS[name].relative_to(ROOT)),
            "calibrated_coverage": {"labels": len(eligible), "of": 35, "uncalibrated_labels": [label for label in labels if artifact["label_status"][label]["status"] == "uncalibrated"]},
            "inference_seconds": scores_by_model[name]["inference_seconds"],
            "provisional_metrics_on_evaluable_rows": metrics,
            "by_language": by_language,
            "uncertainty_probes": [prediction for prediction in predictions if prediction["provisional_expected_status"] == "uncertain"],
            "per_case_actual_predictions": predictions,
        }

    result = {
        "schema": "emotion35-fil-tag-candidate-model-comparison-v1",
        "device": device,
        "evaluation_file": str(DATA_PATH.relative_to(ROOT)),
        "evaluation_sha256": sha256_file(DATA_PATH),
        "record_count": len(rows),
        "evaluable_provisional_target_records": len(evaluable),
        "uncertainty_probes_not_scored_as_classification_truth": len(rows) - len(evaluable),
        "expected_labels_are_ai_authored_provisional_targets": True,
        "independently_validated_records": 0,
        "training_eligible": False,
        "matched_eligible_labels": eligible,
        "models": model_results,
        "warning": "Metrics use AI-authored synthetic provisional labels and are diagnostic only; they are not independent accuracy or human-reviewed ground truth. No thresholds were fitted on this set.",
    }
    if OUTPUT_PATH.exists():
        raise FileExistsError(f"refusing to overwrite evaluation result: {OUTPUT_PATH}")
    OUTPUT_PATH.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({
        "output": str(OUTPUT_PATH), "device": device, "rows": len(rows), "scored": len(evaluable),
        "models": {name: {"matched_15_micro_f1": round(value["provisional_metrics_on_evaluable_rows"]["micro_f1"], 4), "macro_f1": round(value["provisional_metrics_on_evaluable_rows"]["macro_f1"], 4), "filipino_f1": round(value["by_language"]["FILIPINO"]["micro_f1"], 4), "taglish_f1": round(value["by_language"]["TAGLISH"]["micro_f1"], 4)} for name, value in model_results.items()},
    }, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
