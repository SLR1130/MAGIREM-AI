"""Research-only categorical classifier for EmpatheticDialogues context labels.

EmpatheticDialogues ``context`` describes the situation that prompted a
conversation. It is not an utterance-level, 35-emotion multilabel annotation.
This trainer therefore uses a separate single-label categorical objective and
produces an isolated artifact that is never loaded by the Emotion35 runtime.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import random
import shutil
import time
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
SPLITS = ("train", "validation", "test")


def _records(path: Path):
    with path.open("r", encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, 1):
            if not line.strip():
                continue
            try:
                record = json.loads(line)
            except json.JSONDecodeError as exc:
                raise ValueError(f"invalid JSON at {path}:{line_number}") from exc
            if not isinstance(record, dict):
                raise ValueError(f"record at {path}:{line_number} is not an object")
            yield record


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _load_rows(dataset_dir: Path):
    rows_by_split: dict[str, list[dict[str, Any]]] = {}
    context_to_label: dict[str, str] = {}
    for split in SPLITS:
        path = dataset_dir / f"{split}.jsonl"
        if not path.exists():
            raise ValueError(f"missing split: {path}")
        rows: list[dict[str, Any]] = []
        for record in _records(path):
            labels = record.get("labels")
            if not isinstance(labels, list) or len(labels) != 1:
                raise ValueError("categorical context training requires exactly one mapped label per record")
            target = str(labels[0]).strip().lower()
            context = str(record.get("source_context") or "").strip().lower()
            if not context:
                raise ValueError("each record requires source_context")
            previous = context_to_label.setdefault(context, target)
            if previous != target:
                raise ValueError(f"source context maps to multiple targets: {context}")
            text = str(record.get("text") or "").strip()
            if not text:
                raise ValueError("each record requires non-empty text")
            rows.append({"text": " ".join(text.split()), "context": context, "target": target})
        rows_by_split[split] = rows
    if len(context_to_label) < 2:
        raise ValueError("at least two source-context classes are required")
    return rows_by_split, context_to_label


def train(
    dataset_dir: Path,
    output_dir: Path,
    *,
    init_model: Path,
    epochs: float = 1.0,
    batch_size: int = 8,
    learning_rate: float = 2e-5,
    seed: int = 20261026,
) -> dict[str, Any]:
    if output_dir.exists():
        raise ValueError(f"refusing to overwrite existing output: {output_dir}")
    if not init_model.exists():
        raise ValueError(f"missing initial model: {init_model}")

    rows_by_split, context_to_label = _load_rows(dataset_dir)
    labels = sorted(set(context_to_label.values()))
    label_to_id = {label: index for index, label in enumerate(labels)}
    for rows in rows_by_split.values():
        for row in rows:
            row["labels"] = label_to_id[row["target"]]

    from datasets import Dataset, DatasetDict
    import numpy as np
    import torch
    from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, precision_score, recall_score
    from transformers import AutoModelForSequenceClassification, AutoTokenizer, Trainer, TrainingArguments, set_seed

    set_seed(seed)
    random.seed(seed)
    np.random.seed(seed)
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    if device.type == "cuda":
        torch.cuda.reset_peak_memory_stats()

    datasets = DatasetDict({split: Dataset.from_list(rows_by_split[split]) for split in SPLITS})
    tokenizer = AutoTokenizer.from_pretrained(str(init_model), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(
        str(init_model),
        local_files_only=True,
        num_labels=len(labels),
        id2label={index: label for index, label in enumerate(labels)},
        label2id=label_to_id,
        problem_type="single_label_classification",
        ignore_mismatched_sizes=True,
    )

    def tokenize(batch):
        return tokenizer(batch["text"], truncation=True, max_length=256)

    encoded = datasets.map(tokenize, batched=True, remove_columns=["text", "context", "target"])

    def metrics(eval_prediction):
        logits = eval_prediction.predictions if hasattr(eval_prediction, "predictions") else eval_prediction[0]
        target = eval_prediction.label_ids if hasattr(eval_prediction, "label_ids") else eval_prediction[1]
        if isinstance(logits, tuple):
            logits = logits[0]
        prediction = np.asarray(logits).argmax(axis=-1)
        target = np.asarray(target)
        return {
            "accuracy": float(accuracy_score(target, prediction)),
            "macro_precision": float(precision_score(target, prediction, average="macro", zero_division=0)),
            "macro_recall": float(recall_score(target, prediction, average="macro", zero_division=0)),
            "macro_f1": float(f1_score(target, prediction, average="macro", zero_division=0)),
            "weighted_f1": float(f1_score(target, prediction, average="weighted", zero_division=0)),
        }

    staging = output_dir.parent / f".{output_dir.name}.staging-{random.randrange(1_000_000_000):09d}"
    staging.mkdir(parents=True, exist_ok=False)
    try:
        kwargs = dict(
            output_dir=str(staging / "checkpoints"),
            learning_rate=learning_rate,
            per_device_train_batch_size=batch_size,
            per_device_eval_batch_size=batch_size,
            num_train_epochs=epochs,
            weight_decay=0.01,
            seed=seed,
            logging_strategy="epoch",
            save_strategy="epoch",
            load_best_model_at_end=True,
            metric_for_best_model="eval_macro_f1",
            greater_is_better=True,
            report_to=[],
            remove_unused_columns=False,
        )
        try:
            training_args = TrainingArguments(evaluation_strategy="epoch", **kwargs)
        except TypeError:
            training_args = TrainingArguments(eval_strategy="epoch", **kwargs)
        trainer_kwargs = {
            "model": model,
            "args": training_args,
            "train_dataset": encoded["train"],
            "eval_dataset": encoded["validation"],
            "compute_metrics": metrics,
        }
        try:
            trainer = Trainer(tokenizer=tokenizer, **trainer_kwargs)
        except TypeError:
            trainer = Trainer(processing_class=tokenizer, **trainer_kwargs)

        start = time.perf_counter()
        train_result = trainer.train()
        train_seconds = time.perf_counter() - start
        validation = trainer.evaluate(encoded["validation"])
        test_start = time.perf_counter()
        prediction = trainer.predict(encoded["test"])
        test_seconds = time.perf_counter() - test_start
        logits = prediction.predictions if hasattr(prediction, "predictions") else prediction[0]
        target = prediction.label_ids if hasattr(prediction, "label_ids") else prediction[1]
        if isinstance(logits, tuple):
            logits = logits[0]
        predicted = np.asarray(logits).argmax(axis=-1)
        target = np.asarray(target)
        test = metrics((logits, target))
        matrix = confusion_matrix(target, predicted, labels=list(range(len(labels))))
        support = np.bincount(target, minlength=len(labels)).astype(int).tolist()

        model_dir = staging / "model"
        trainer.save_model(str(model_dir))
        tokenizer.save_pretrained(str(model_dir))
        model_dir.mkdir(parents=True, exist_ok=True)
        (model_dir / "label_mapping.json").write_text(json.dumps({
            "schema": "mindcare-empathetic-context18-v1",
            "classes": labels,
            "label_to_id": label_to_id,
            "context_to_label": context_to_label,
        }, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        manifest = {
            "schema": "mindcare-empathetic-context18-manifest-v1",
            "objective": "categorical source-context classification",
            "source_dataset": "empathetic_dialogues",
            "source_license": "CC BY-NC-4.0; private non-commercial research use",
            "source_context_is_ground_truth": False,
            "human_reviewed": False,
            "synthetic_rows": 0,
            "split_counts": {split: len(rows_by_split[split]) for split in SPLITS},
            "source_files": {f"{split}.jsonl": _sha256(dataset_dir / f"{split}.jsonl") for split in SPLITS},
            "excluded_duplicates": str(dataset_dir / "excluded_duplicates.json"),
        }
        (model_dir / "dataset_manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        (model_dir / "provenance.json").write_text(json.dumps({
            "artifact": "research_only_context_classifier",
            "warning": "This predicts EmpatheticDialogues situation-context classes, not validated student emotions or the approved 35-label multilabel target.",
            "source_context_mapping": context_to_label,
            "production_connected": False,
        }, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        report = {
            "schema": "mindcare-empathetic-context18-report-v1",
            "objective": "categorical source-context classification; separate from Emotion35 multilabel BCE",
            "labels": labels,
            "context_to_label": context_to_label,
            "split_counts": {split: len(rows_by_split[split]) for split in SPLITS},
            "train": {**train_result.metrics, "wall_seconds": train_seconds},
            "validation": validation,
            "test": {**test, "prediction_seconds": test_seconds},
            "test_support": {label: support[index] for index, label in enumerate(labels)},
            "confusion_matrix_rows_true_columns_predicted": matrix.tolist(),
            "device": str(device),
            "gpu_name": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,
            "peak_cuda_memory_bytes": int(torch.cuda.max_memory_allocated()) if torch.cuda.is_available() else 0,
            "warning": "Held-out metrics measure source-context classification only. They are not 35-emotion recognition metrics because EmpatheticDialogues context describes a situation rather than the speaker's utterance-level emotion.",
            "production_model_modified": False,
        }
        (model_dir / "metrics.json").write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        shutil.move(str(model_dir), str(output_dir))
        return report
    finally:
        shutil.rmtree(staging, ignore_errors=True)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dataset-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--init-model", type=Path, required=True)
    parser.add_argument("--epochs", type=float, default=1.0)
    parser.add_argument("--batch-size", type=int, default=8)
    parser.add_argument("--learning-rate", type=float, default=2e-5)
    args = parser.parse_args()
    report = train(
        args.dataset_dir,
        args.output_dir,
        init_model=args.init_model,
        epochs=args.epochs,
        batch_size=args.batch_size,
        learning_rate=args.learning_rate,
    )
    print(json.dumps(report, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
