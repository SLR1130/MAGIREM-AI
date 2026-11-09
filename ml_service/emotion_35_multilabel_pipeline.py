"""Isolated pipeline for a future 35-emotion multilabel classifier.

This module is deliberately separate from ``trained_emotion_classifier``.  The
deployed classifier is a six-class single-label model and must remain the
production source until a real, reviewed 35-label corpus is available.

The audit is fail-closed: a dataset with missing labels, insufficient per-label
coverage, split leakage, or missing provenance cannot produce a model artifact.
"""

from __future__ import annotations

import hashlib
import json
import os
import random
import shutil
import tempfile
from collections import Counter, defaultdict
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Any, Iterable, Mapping, Sequence

try:
    from .emotion35_calibration import calibrate_validation, sha256_file
except ImportError:
    from emotion35_calibration import calibrate_validation, sha256_file


ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config" / "emotion_taxonomy.json"
EXPECTED_SPLITS = ("train", "validation", "test")


class DatasetAuditError(ValueError):
    """Raised when a corpus is not eligible for 35-label training."""


def load_taxonomy(path: Path = TAXONOMY_PATH) -> dict[str, Any]:
    with path.open("r", encoding="utf-8") as handle:
        taxonomy = json.load(handle)
    emotions = taxonomy.get("emotions")
    categories = taxonomy.get("categories")
    if not isinstance(emotions, list) or len(emotions) != 35:
        raise DatasetAuditError("taxonomy must contain exactly 35 emotions")
    keys = [item.get("key") for item in emotions]
    if len(set(keys)) != 35 or any(not isinstance(key, str) or not key for key in keys):
        raise DatasetAuditError("taxonomy emotion keys must be unique non-empty strings")
    category_keys = {item.get("key") for item in categories or []}
    if len(category_keys) != 8:
        raise DatasetAuditError("taxonomy must contain exactly 8 categories")
    if any(item.get("category_key") not in category_keys for item in emotions):
        raise DatasetAuditError("every emotion must map to a known category")
    return taxonomy


def _jsonl_path(dataset_dir: Path, split: str) -> Path:
    for candidate in (dataset_dir / f"{split}.jsonl", dataset_dir / f"{split}.json"):
        if candidate.exists():
            return candidate
    raise DatasetAuditError(f"missing required split: {split}.jsonl")


def _records(path: Path) -> Iterable[dict[str, Any]]:
    if path.suffix == ".json":
        with path.open("r", encoding="utf-8") as handle:
            payload = json.load(handle)
        if isinstance(payload, dict):
            payload = payload.get("data", payload.get("records", []))
        if not isinstance(payload, list):
            raise DatasetAuditError(f"{path} must contain a JSON array")
        yield from payload
        return
    with path.open("r", encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, 1):
            if not line.strip():
                continue
            try:
                value = json.loads(line)
            except json.JSONDecodeError as exc:
                raise DatasetAuditError(f"invalid JSON in {path}:{line_number}: {exc}") from exc
            if not isinstance(value, dict):
                raise DatasetAuditError(f"{path}:{line_number} is not an object")
            yield value


def _labels(record: Mapping[str, Any]) -> list[str]:
    value = record.get("labels", record.get("emotions", record.get("emotion", record.get("label"))))
    if isinstance(value, str):
        values = [value]
    elif isinstance(value, Sequence) and not isinstance(value, (bytes, bytearray)):
        values = list(value)
    else:
        values = []
    result = []
    for label in values:
        normalized = str(label).strip().lower()
        if normalized and normalized not in result:
            result.append(normalized)
    return result


def _observed_labels(record: Mapping[str, Any]) -> list[str] | None:
    """Return labels whose absence is actually annotated by the source.

    A missing field means no labels were observed. Complete-label sources must
    explicitly provide their observed-label set; absence is never a negative.
    """
    if "observed_labels" not in record and "label_mask" not in record:
        return None
    value = record.get("observed_labels", record.get("label_mask"))
    if isinstance(value, Mapping):
        values = [key for key, observed in value.items() if observed]
    elif isinstance(value, Sequence) and not isinstance(value, (bytes, bytearray, str)):
        values = list(value)
    else:
        values = []
    result = []
    for label in values:
        normalized = str(label).strip().lower()
        if normalized and normalized not in result:
            result.append(normalized)
    return result


def _text(record: Mapping[str, Any]) -> str:
    value = record.get("text", record.get("message", record.get("content")))
    if not isinstance(value, str) or not value.strip():
        raise DatasetAuditError("each record requires a non-empty text field")
    return " ".join(value.split())


def _text_hash(text: str) -> str:
    return hashlib.sha256(text.strip().casefold().encode("utf-8")).hexdigest()


@dataclass
class DatasetAudit:
    dataset_dir: str
    taxonomy_version: str
    total_records: int
    split_counts: dict[str, int]
    label_counts: dict[str, dict[str, int]]
    languages: dict[str, int]
    missing_labels: list[str]
    low_coverage: dict[str, dict[str, int]]
    duplicate_texts_across_splits: list[str]
    invalid_labels: list[str]
    missing_provenance_fields: list[str]
    sufficient_for_training: bool
    partial_labels_allowed: bool
    blockers: list[str]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


def audit_dataset(
    dataset_dir: Path,
    *,
    min_train_per_label: int = 20,
    min_validation_per_label: int = 5,
    min_test_per_label: int = 5,
    provenance: Mapping[str, Any] | None = None,
    allow_partial_labels: bool = False,
) -> DatasetAudit:
    taxonomy = load_taxonomy()
    allowed = {item["key"] for item in taxonomy["emotions"]}
    split_records: dict[str, list[dict[str, Any]]] = {}
    split_counts: dict[str, int] = {}
    label_counts: dict[str, dict[str, int]] = {}
    languages: Counter[str] = Counter()
    invalid: set[str] = set()
    text_locations: defaultdict[str, list[str]] = defaultdict(list)
    blockers: list[str] = []

    for split in EXPECTED_SPLITS:
        path = _jsonl_path(dataset_dir, split)
        rows = list(_records(path))
        split_records[split] = rows
        split_counts[split] = len(rows)
        counts: Counter[str] = Counter()
        for row_number, record in enumerate(rows, 1):
            text = _text(record)
            labels = _labels(record)
            observed = _observed_labels(record)
            if not labels and not observed:
                blockers.append(f"{split}:{row_number} has no label")
            for label in labels:
                counts[label] += 1
                if label not in allowed:
                    invalid.add(label)
            observed_set = set(observed or [])
            for label in observed_set:
                if label not in allowed:
                    invalid.add(label)
            if set(labels) - observed_set:
                blockers.append(f"{split}:{row_number} has positive labels outside observed annotation mask")
            language = record.get("language", record.get("lang", "unknown"))
            languages[str(language).upper() if language else "UNKNOWN"] += 1
            text_locations[_text_hash(text)].append(split)
        label_counts[split] = dict(sorted(counts.items()))

    duplicate_hashes = sorted(
        hash_value for hash_value, locations in text_locations.items() if len(set(locations)) > 1
    )
    if invalid:
        blockers.append("labels outside the approved 35-emotion vocabulary")
    if duplicate_hashes:
        blockers.append("exact normalized text leakage across splits")

    all_counts = Counter()
    for counts in label_counts.values():
        all_counts.update(counts)
    missing = sorted(allowed - set(all_counts))
    if missing and not allow_partial_labels:
        blockers.append("one or more approved emotions have no examples")

    thresholds = {
        "train": min_train_per_label,
        "validation": min_validation_per_label,
        "test": min_test_per_label,
    }
    low: dict[str, dict[str, int]] = {}
    for split, minimum in thresholds.items():
        for label in sorted(allowed):
            count = label_counts[split].get(label, 0)
            if count < minimum:
                low.setdefault(label, {})[split] = count
    if allow_partial_labels:
        present_low = {label: splits for label, splits in low.items() if label not in missing}
    else:
        present_low = low
    if present_low:
        blockers.append("per-label train/validation/test coverage is below the configured minimum")

    required_provenance = ("source", "license", "usage", "curator", "version")
    missing_provenance = [
        field for field in required_provenance
        if not provenance or not str(provenance.get(field, "")).strip()
    ]
    if missing_provenance:
        blockers.append("dataset provenance/licensing metadata is incomplete")

    return DatasetAudit(
        dataset_dir=str(dataset_dir),
        taxonomy_version=str(taxonomy.get("version", "")),
        total_records=sum(split_counts.values()),
        split_counts=split_counts,
        label_counts=label_counts,
        languages=dict(sorted(languages.items())),
        missing_labels=missing,
        low_coverage=low,
        duplicate_texts_across_splits=duplicate_hashes,
        invalid_labels=sorted(invalid),
        missing_provenance_fields=missing_provenance,
        sufficient_for_training=not blockers,
        partial_labels_allowed=allow_partial_labels,
        blockers=sorted(set(blockers)),
    )


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def dataset_manifest(dataset_dir: Path, audit: DatasetAudit) -> dict[str, Any]:
    files = {}
    for split in EXPECTED_SPLITS:
        path = _jsonl_path(dataset_dir, split)
        files[path.name] = hashlib.sha256(path.read_bytes()).hexdigest()
    return {
        "schema": "mindcare-35-multilabel-manifest-v1",
        "taxonomy_version": audit.taxonomy_version,
        "dataset_files": files,
        "audit": audit.to_dict(),
    }



def masked_bce_with_logits(logits: Any, labels: Any, label_mask: Any) -> Any:
    """BCE loss that ignores target labels the source never annotated."""
    import torch

    if logits.shape != labels.shape or logits.shape != label_mask.shape:
        raise DatasetAuditError("logits, targets, and observed-label mask must have identical shapes")
    if not torch.isfinite(label_mask).all() or torch.any((label_mask < 0) | (label_mask > 1)):
        raise DatasetAuditError("observed-label mask must contain finite values in [0, 1]")

    element_loss = torch.nn.functional.binary_cross_entropy_with_logits(
        logits, labels, reduction="none"
    )
    mask = label_mask.to(dtype=element_loss.dtype)
    return (element_loss * mask).sum() / mask.sum().clamp_min(1.0)


def train_model(
    dataset_dir: Path,
    output_dir: Path,
    *,
    init_model: Path,
    provenance: Mapping[str, Any],
    epochs: float = 3.0,
    learning_rate: float = 2e-5,
    batch_size: int = 8,
    seed: int = 20261025,
    min_train_per_label: int = 20,
    min_validation_per_label: int = 5,
    min_test_per_label: int = 5,
    allow_partial_labels: bool = False,
) -> DatasetAudit:
    audit = audit_dataset(
        dataset_dir,
        min_train_per_label=min_train_per_label,
        min_validation_per_label=min_validation_per_label,
        min_test_per_label=min_test_per_label,
        provenance=provenance,
        allow_partial_labels=allow_partial_labels,
    )
    if not audit.sufficient_for_training:
        raise DatasetAuditError(json.dumps(audit.to_dict(), indent=2))
    if output_dir.exists():
        raise DatasetAuditError(f"refusing to overwrite existing output: {output_dir}")
    if not init_model.exists():
        raise DatasetAuditError(f"initial encoder/model does not exist: {init_model}")

    # Optional training dependencies are loaded only after the audit passes.
    from datasets import Dataset, DatasetDict
    from transformers import AutoModelForSequenceClassification, AutoTokenizer, Trainer, TrainingArguments, set_seed
    import numpy as np
    from sklearn.metrics import f1_score

    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    label_to_id = {label: index for index, label in enumerate(labels)}
    set_seed(seed)
    random.seed(seed)
    np.random.seed(seed)

    rows_by_split = {}
    raw_masks = {}
    raw_rows = {}
    for split in EXPECTED_SPLITS:
        path = _jsonl_path(dataset_dir, split)
        rows = []
        masks = []
        split_raw_rows = []
        for record in _records(path):
            row = {"text": _text(record)}
            positives = _labels(record)
            observed = _observed_labels(record)
            if observed is None:
                observed = []
            if set(positives) - set(observed):
                raise DatasetAuditError("positive targets must be present in observed_labels")
            vector = [0.0] * len(labels)
            mask = [0.0] * len(labels)
            for label in positives:
                vector[label_to_id[label]] = 1.0
            for label in observed:
                if label in label_to_id:
                    mask[label_to_id[label]] = 1.0
            row["labels"] = vector
            row["label_mask"] = mask
            rows.append(row)
            masks.append(mask)
            split_raw_rows.append(record)
        rows_by_split[split] = Dataset.from_list(rows)
        raw_masks[split] = np.asarray(masks, dtype=np.float32)
        raw_rows[split] = split_raw_rows
    datasets = DatasetDict({"train": rows_by_split["train"], "validation": rows_by_split["validation"], "test": rows_by_split["test"]})

    tokenizer = AutoTokenizer.from_pretrained(str(init_model), local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(
        str(init_model),
        local_files_only=True,
        num_labels=len(labels),
        id2label={index: label for index, label in enumerate(labels)},
        label2id=label_to_id,
        problem_type="multi_label_classification",
        ignore_mismatched_sizes=True,
    )

    def tokenize(batch):
        return tokenizer(batch["text"], truncation=True, max_length=256)

    encoded = datasets.map(tokenize, batched=True, remove_columns=["text"])
    validation_mask = raw_masks["validation"]

    def _prediction_parts(eval_prediction):
        logits = eval_prediction.predictions if hasattr(eval_prediction, "predictions") else eval_prediction[0]
        target = eval_prediction.label_ids if hasattr(eval_prediction, "label_ids") else eval_prediction[1]
        if isinstance(logits, tuple):
            logits = logits[0]
        return np.asarray(logits), np.asarray(target)

    def _positive_micro_metrics(target, probabilities, mask, threshold=0.5):
        """Compute positive-label micro metrics while honoring annotation masks."""
        valid = mask.astype(bool)
        if not valid.any():
            return {"precision": 0.0, "recall": 0.0, "f1": 0.0}
        observed_target = target[valid].astype(int)
        observed_prediction = (probabilities[valid] >= threshold).astype(int)
        true_positive = int(((observed_target == 1) & (observed_prediction == 1)).sum())
        false_positive = int(((observed_target == 0) & (observed_prediction == 1)).sum())
        false_negative = int(((observed_target == 1) & (observed_prediction == 0)).sum())
        precision = true_positive / (true_positive + false_positive) if true_positive + false_positive else 0.0
        recall = true_positive / (true_positive + false_negative) if true_positive + false_negative else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if precision + recall else 0.0
        return {"precision": precision, "recall": recall, "f1": f1}

    def masked_metrics(eval_prediction):
        logits, target = _prediction_parts(eval_prediction)
        probabilities = 1.0 / (1.0 + np.exp(-logits))
        mask = validation_mask if target.shape[0] == validation_mask.shape[0] else raw_masks["test"]
        per_label = []
        for index in range(len(labels)):
            valid = mask[:, index] > 0
            if not valid.any():
                continue
            prediction = (probabilities[valid, index] >= 0.5).astype(int)
            per_label.append(f1_score(target[valid, index], prediction, zero_division=0))
        if not mask.astype(bool).any():
            return {"micro_f1": 0.0, "macro_f1": 0.0}
        micro = _positive_micro_metrics(target, probabilities, mask)
        return {
            "micro_precision": micro["precision"],
            "micro_recall": micro["recall"],
            "micro_f1": micro["f1"],
            "macro_f1": float(np.mean(per_label) if per_label else 0.0),
        }

    class MaskedBCETrainer(Trainer):
        def compute_loss(self, model, inputs, return_outputs=False, num_items_in_batch=None):
            labels_tensor = inputs.pop("labels")
            mask_tensor = inputs.pop("label_mask", None)
            outputs = model(**inputs)
            if mask_tensor is None:
                import torch
                mask_tensor = torch.zeros_like(labels_tensor)
            loss = masked_bce_with_logits(outputs.logits, labels_tensor, mask_tensor)
            return (loss, outputs) if return_outputs else loss

    # Python 3.13's Windows ``tempfile.mkdtemp`` may create a staging folder
    # with an ACL that prevents the Trainer from creating ``checkpoints``.
    # Create the private, collision-resistant staging directory through the
    # normal project filesystem instead; it remains removed in ``finally``.
    staging = output_dir.parent / f".{output_dir.name}.staging-{os.getpid()}-{random.randrange(1_000_000_000):09d}"
    staging.mkdir(parents=True, exist_ok=False)
    try:
        args_kwargs = dict(
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
            args = TrainingArguments(evaluation_strategy="epoch", **args_kwargs)
        except TypeError:
            args = TrainingArguments(eval_strategy="epoch", **args_kwargs)
        trainer_kwargs = dict(
            model=model,
            args=args,
            train_dataset=encoded["train"],
            eval_dataset=encoded["validation"],
            compute_metrics=masked_metrics,
        )
        try:
            trainer = MaskedBCETrainer(tokenizer=tokenizer, **trainer_kwargs)
        except TypeError:
            trainer = MaskedBCETrainer(processing_class=tokenizer, **trainer_kwargs)
        trainer.train()
        validation_prediction = trainer.predict(encoded["validation"])
        validation_logits, validation_target = _prediction_parts(validation_prediction)
        validation_probabilities = 1.0 / (1.0 + np.exp(-validation_logits))
        trainer.save_model(str(staging / "model"))
        tokenizer.save_pretrained(str(staging / "model"))
        calibration = calibrate_validation(
            raw_rows["validation"], validation_probabilities, labels,
            taxonomy_version=audit.taxonomy_version, checkpoint_id=output_dir.name,
            checkpoint_sha256=sha256_file(staging / "model" / "model.safetensors"),
            validation_sha256=sha256_file(_jsonl_path(dataset_dir, "validation")),
        )
        thresholds = calibration["thresholds"]
        test_prediction = trainer.predict(encoded["test"])
        test_logits, test_target = _prediction_parts(test_prediction)
        test_probabilities = 1.0 / (1.0 + np.exp(-test_logits))
        test_mask = raw_masks["test"]
        test_per_label = []
        test_tp = test_fp = test_fn = 0
        calibrated_test_labels = []
        for index in range(len(labels)):
            threshold = thresholds[labels[index]]
            if threshold is None:
                continue
            calibrated_test_labels.append(labels[index])
            valid = test_mask[:, index] > 0
            if valid.any():
                truth = test_target[valid, index].astype(int)
                predicted = (test_probabilities[valid, index] >= threshold).astype(int)
                test_per_label.append(f1_score(truth, predicted, zero_division=0))
                test_tp += int(((truth == 1) & (predicted == 1)).sum())
                test_fp += int(((truth == 0) & (predicted == 1)).sum())
                test_fn += int(((truth == 1) & (predicted == 0)).sum())
        test_precision = test_tp / (test_tp + test_fp) if test_tp + test_fp else 0.0
        test_recall = test_tp / (test_tp + test_fn) if test_tp + test_fn else 0.0
        test_metrics = {
            "test_micro_precision_masked_calibrated_labels_only": test_precision,
            "test_micro_recall_masked_calibrated_labels_only": test_recall,
            "test_micro_f1_masked_calibrated_labels_only": 2 * test_precision * test_recall / (test_precision + test_recall) if test_precision + test_recall else 0.0,
            "test_macro_f1_masked": float(np.mean(test_per_label) if test_per_label else 0.0),
            "test_observed_targets": int(test_mask.sum()),
            "calibrated_labels": calibrated_test_labels,
            "uncalibrated_labels": [label for label in labels if thresholds[label] is None],
        }
        write_json(staging / "model" / "label_mapping.json", {"taxonomy_version": audit.taxonomy_version, "labels": labels, "label_to_id": label_to_id})
        write_json(staging / "model" / "thresholds.json", calibration)
        write_json(staging / "model" / "dataset_manifest.json", dataset_manifest(dataset_dir, audit))
        write_json(staging / "model" / "provenance.json", dict(provenance))
        write_json(staging / "model" / "training_config.json", {"seed": seed, "epochs": epochs, "learning_rate": learning_rate, "batch_size": batch_size, "problem_type": "multi_label_classification", "base_model": str(init_model), "masked_loss": True, "allow_partial_labels": allow_partial_labels, "research_only": allow_partial_labels})
        write_json(staging / "model" / "metrics.json", test_metrics)
        shutil.move(str(staging / "model"), str(output_dir))
        return audit
    finally:
        shutil.rmtree(staging, ignore_errors=True)




