#!/usr/bin/env python3
"""Compare checkpoint-130 with an offline continual-training candidate.

The existing validation/test/frozen sets are explicitly diagnostic.  A final
human-reviewed set can be supplied once, after the candidate and manifests are
locked.  This script performs identical raw-classifier inference for baseline
and candidate and writes metrics outside the application tree.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path

import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

import continual_train_emotion as pipeline


def load_rows(path: Path) -> list[dict]:
    if path.suffix.casefold() in {".jsonl", ".json"}:
        raw = pipeline.load_jsonl(path)
        rows = []
        for index, row in enumerate(raw):
            emotion = str(row.get("emotion", "")).casefold()
            language = str(row.get("language", "")).upper()
            text = str(row.get("text", ""))
            if emotion not in pipeline.LABEL2ID or language not in pipeline.LANGUAGES or not text.strip():
                raise ValueError(f"Invalid evaluation row {index + 1} in {path}")
            rows.append({"id": str(row.get("id") or f"eval-{index:04d}"), "text": text, "emotion": emotion, "label": pipeline.LABEL2ID[emotion], "language": language})
        return rows
    return pipeline.load_final_evaluation_csv(path)


def evaluate_model(model_path: Path, tokenizer: object, datasets: dict[str, list[dict]], device: torch.device) -> dict:
    model = AutoModelForSequenceClassification.from_pretrained(str(model_path), local_files_only=True)
    pipeline.verify_label_mapping(model)
    model.to(device)
    return {name: pipeline.evaluate_rows(model, tokenizer, rows, device) for name, rows in datasets.items()}


def strip_predictions(value: dict) -> dict:
    result = dict(value)
    result.pop("predicted", None)
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--final-eval", type=Path, default=None)
    args = parser.parse_args()
    if args.output_dir.resolve().is_relative_to(pipeline.ROOT.resolve()):
        raise SystemExit("Evaluation output must remain outside the production project")
    if args.final_eval and args.final_eval.suffix.casefold() != ".csv":
        raise SystemExit("Final evaluation must be a canonical human-reviewed CSV")
    if not args.candidate.exists():
        raise SystemExit(f"Candidate model not found: {args.candidate}")
    pipeline.set_seed(pipeline.SEED)
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    tokenizer = AutoTokenizer.from_pretrained(str(pipeline.TOKENIZER), local_files_only=True)
    datasets = {
        "development_validation_88_diagnostic": pipeline.load_legacy_jsonl(pipeline.DATASET_DIR / "validation.jsonl", "development"),
        "test_96_diagnostic": pipeline.load_legacy_jsonl(pipeline.DATASET_DIR / "test.jsonl", "diagnostic"),
    }
    frozen = pipeline.ROOT / "ml_service" / "independent_emotion_routing_20261019.jsonl"
    if frozen.exists():
        datasets["frozen_36_diagnostic"] = load_rows(frozen)
    if args.final_eval:
        if not args.final_eval.exists():
            raise SystemExit(f"Final evaluation file not found: {args.final_eval}")
        datasets["fresh_final_human_reviewed"] = load_rows(args.final_eval)
    baseline = evaluate_model(pipeline.CHECKPOINT, tokenizer, datasets, device)
    candidate = evaluate_model(args.candidate, tokenizer, datasets, device)
    report = {
        "status": "DIAGNOSTIC_ONLY_UNLESS_FRESH_FINAL_SUPPLIED",
        "baseline_checkpoint": str(pipeline.CHECKPOINT),
        "candidate": str(args.candidate),
        "label_mapping": pipeline.LABEL2ID,
        "device": str(device),
        "datasets": {name: {"count": len(rows), "source": "fresh_human_reviewed" if name == "fresh_final_human_reviewed" else "diagnostic"} for name, rows in datasets.items()},
        "baseline": {name: strip_predictions(value) for name, value in baseline.items()},
        "candidate_metrics": {name: strip_predictions(value) for name, value in candidate.items()},
    }
    args.output_dir.mkdir(parents=True, exist_ok=True)
    (args.output_dir / "comparison.json").write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    lines = ["# Continual-training classifier comparison", "", "Existing 96-case, 36-case, and validation sets are diagnostic only. The fresh final set is the only set eligible for a promotion decision.", "", "| Dataset | Baseline accuracy | Candidate accuracy | Baseline macro-F1 | Candidate macro-F1 |", "|---|---:|---:|---:|---:|"]
    for name in datasets:
        left, right = baseline[name], candidate[name]
        lines.append(f"| {name} | {left['accuracy']:.4f} | {right['accuracy']:.4f} | {left['macro_f1']:.4f} | {right['macro_f1']:.4f} |")
    lines.extend(["", "Metrics include per-class precision/recall/F1, confusion matrices, sadness false-positive rate, confidence summary, language-stratified metrics, and inference latency in `comparison.json`.", "", "No candidate promotion is performed by this script."])
    (args.output_dir / "comparison.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"COMPARISON_REPORT={args.output_dir / 'comparison.json'}")
    print("CANDIDATE_PROMOTED=NO")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
