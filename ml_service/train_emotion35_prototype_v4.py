"""Train the isolated Emotion35 v4 weak-supervision research checkpoint.

The v4 objective keeps public-source masks intact and gives synthetic rows a
lower-loss weight.  Positive-only synthetic rows observe only their proposed
positive labels; explicit contrast rows additionally observe documented weak
negatives.  A weighted sampler increases Filipino/Taglish exposure without
duplicating source text.  The output is research-only and never replaces the
production six-class model.
"""
from __future__ import annotations

import argparse
import json
import random
import shutil
import time
from pathlib import Path

import numpy as np

from emotion_35_multilabel_pipeline import EXPECTED_SPLITS, DatasetAuditError, _jsonl_path, _labels, _observed_labels, _records, _text, audit_dataset, load_taxonomy
from emotion35_calibration import calibrate_validation, sha256_file


def sigmoid(values: np.ndarray) -> np.ndarray:
    return 1.0 / (1.0 + np.exp(-np.clip(values, -50, 50)))


def masked_weak_loss(logits, labels_tensor, mask_tensor, sample_weight, pos_weight):
    """Weighted masked BCE used by v4 and exercised by the preflight test."""
    import torch

    element = torch.nn.functional.binary_cross_entropy_with_logits(
        logits, labels_tensor, reduction="none", pos_weight=pos_weight
    )
    row_loss = (element * mask_tensor).sum(dim=1) / mask_tensor.sum(dim=1).clamp_min(1.0)
    return (row_loss * sample_weight).sum() / sample_weight.sum().clamp_min(1.0)


def run_loss_self_test() -> None:
    """Prove unknown labels are ignored and explicit negatives are penalized."""
    import torch

    pos_weight = torch.ones(2)
    row_weight = torch.ones(1)
    logits_a = torch.tensor([[0.0, -8.0]])
    logits_b = torch.tensor([[0.0, 8.0]])
    target = torch.tensor([[1.0, 0.0]])
    positive_only = torch.tensor([[1.0, 0.0]])
    contrast_mask = torch.tensor([[1.0, 1.0]])
    unknown_a = masked_weak_loss(logits_a, target, positive_only, row_weight, pos_weight)
    unknown_b = masked_weak_loss(logits_b, target, positive_only, row_weight, pos_weight)
    contrast_b = masked_weak_loss(logits_b, target, contrast_mask, row_weight, pos_weight)
    if not torch.isclose(unknown_a, unknown_b, atol=1e-5):
        raise AssertionError("unknown positive-only labels incorrectly affect loss")
    if not contrast_b > unknown_b:
        raise AssertionError("explicit weak negative does not penalize a false positive")
    print(json.dumps({"loss_self_test": "passed", "positive_only_unknown_ignored": True, "explicit_negative_penalized": True}))


def score_masked(target: np.ndarray, probabilities: np.ndarray, mask: np.ndarray, labels: list[str], thresholds: dict[str, float | None]) -> dict:
    predicted = np.zeros_like(target, dtype=np.int32)
    for index, label in enumerate(labels):
        threshold = thresholds.get(label)
        if threshold is not None:
            predicted[:, index] = (probabilities[:, index] >= float(threshold)).astype(np.int32)
    per_label = {}
    tp = fp = fn = 0
    calibrated_labels = []
    for index, label in enumerate(labels):
        if thresholds.get(label) is None:
            per_label[label] = {"status": "uncalibrated", "support": int(((target[:, index] == 1) & (mask[:, index] > 0)).sum()), "predicted_positive": 0, "tp": 0, "fp": 0, "fn": 0, "precision": None, "recall": None, "f1": None}
            continue
        calibrated_labels.append(label)
        valid = mask[:, index] > 0
        t = target[valid, index].astype(np.int32)
        p = predicted[valid, index]
        a = int(((t == 1) & (p == 1)).sum())
        b = int(((t == 0) & (p == 1)).sum())
        c = int(((t == 1) & (p == 0)).sum())
        precision = a / (a + b) if a + b else 0.0
        recall = a / (a + c) if a + c else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_label[label] = {"support": int(t.sum()), "predicted_positive": int(p.sum()), "tp": a, "fp": b, "fn": c, "precision": precision, "recall": recall, "f1": f1}
        tp += a
        fp += b
        fn += c
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    micro_f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    macro_f1 = float(np.mean([per_label[label]["f1"] for label in calibrated_labels])) if calibrated_labels else 0.0
    # Exact/sample scores only use fully observed rows; partially observed rows
    # cannot support an exact 35-label claim.
    calibrated_indices = [index for index, label in enumerate(labels) if label in calibrated_labels]
    fully_observed = np.all(mask[:, calibrated_indices] > 0, axis=1) if calibrated_indices else np.zeros(mask.shape[0], dtype=bool)
    exact = float(np.mean(np.all(target[fully_observed][:, calibrated_indices] == predicted[fully_observed][:, calibrated_indices], axis=1))) if fully_observed.any() else None
    sample_scores = []
    for row_index in np.where(fully_observed)[0]:
        t = target[row_index, calibrated_indices]
        p = predicted[row_index, calibrated_indices]
        a = int(((t == 1) & (p == 1)).sum())
        b = int(((t == 0) & (p == 1)).sum())
        c = int(((t == 1) & (p == 0)).sum())
        pr = a / (a + b) if a + b else 0.0
        re = a / (a + c) if a + c else 0.0
        sample_scores.append(2 * pr * re / (pr + re) if pr + re else 0.0)
    return {
        "micro_precision": precision,
        "micro_recall": recall,
        "micro_f1": micro_f1,
        "macro_f1": macro_f1,
        "exact_match_accuracy_fully_observed": exact,
        "sample_f1_fully_observed": float(np.mean(sample_scores)) if sample_scores else None,
        "fully_observed_records": int(fully_observed.sum()),
        "observed_label_count": int(mask.sum()),
        "calibrated_labels": calibrated_labels,
        "uncalibrated_labels": [label for label in labels if thresholds.get(label) is None],
        "per_label": per_label,
    }


def main(args: argparse.Namespace) -> None:
    import torch
    from datasets import Dataset, DatasetDict
    from sklearn.metrics import f1_score
    from transformers import AutoConfig, AutoModelForSequenceClassification, AutoTokenizer, DataCollatorWithPadding, Trainer, TrainingArguments, set_seed
    from safetensors.torch import load_file as load_safetensors

    run_loss_self_test()

    taxonomy = load_taxonomy()
    labels = [item["key"] for item in taxonomy["emotions"]]
    label_to_id = {label: index for index, label in enumerate(labels)}
    provenance = json.loads(args.provenance.read_text(encoding="utf-8"))
    # Research recovery runs may intentionally use partial per-label coverage.
    # They must opt in explicitly and keep the resulting checkpoint isolated;
    # production training continues to use the stricter defaults.
    audit = audit_dataset(
        args.dataset,
        provenance=provenance,
        min_train_per_label=args.min_train_per_label,
        min_validation_per_label=args.min_validation_per_label,
        min_test_per_label=args.min_test_per_label,
        allow_partial_labels=args.allow_partial_labels,
    )
    if not audit.sufficient_for_training:
        raise DatasetAuditError(json.dumps(audit.to_dict(), indent=2))
    if args.output.exists():
        raise DatasetAuditError(f"refusing to overwrite {args.output}")

    set_seed(args.seed)
    random.seed(args.seed)
    np.random.seed(args.seed)
    torch.manual_seed(args.seed)

    rows_by_split = {}
    masks_by_split = {}
    targets_by_split = {}
    sampling_weights = []
    strong_pos = np.zeros(len(labels), dtype=np.float64)
    strong_neg = np.zeros(len(labels), dtype=np.float64)
    split_summaries = {}
    raw_rows_by_split = {}
    for split in EXPECTED_SPLITS:
        rows = []
        masks = []
        targets = []
        raw_rows = []
        weak_count = 0
        for record in _records(_jsonl_path(args.dataset, split)):
            positives = set(_labels(record))
            observed = _observed_labels(record)
            if observed is None:
                observed = []
            observed = set(observed)
            if positives - observed:
                raise DatasetAuditError("positive targets must be present in observed_labels")
            target = [1.0 if label in positives else 0.0 for label in labels]
            mask = [1.0 if label in observed else 0.0 for label in labels]
            weak = bool(record.get("weak_supervision") or record.get("synthetic") and record.get("human_review_required"))
            if split == "train" and args.exclude_weak_training and weak:
                continue
            language = str(record.get("language", "UNKNOWN")).upper()
            language_weight = float({
                "ENGLISH": args.english_sampling_weight,
                "FILIPINO": args.filipino_sampling_weight,
                "TAGLISH": args.taglish_sampling_weight,
            }.get(language, args.english_sampling_weight))
            source_weight = float(args.weak_sampling_multiplier if weak else 1.0)
            row = {
                "text": _text(record),
                "labels": target,
                "label_mask": mask,
                "sample_weight": float(args.weak_weight if weak else 1.0),
                "language_weight": language_weight,
                "source_weight": source_weight,
                "weak_supervision": bool(weak),
            }
            rows.append(row)
            masks.append(mask)
            targets.append(target)
            raw_rows.append(record)
            # Report weakly supervised rows for every split.  Only training
            # rows contribute to class-weight statistics; validation/test
            # provenance must still be reported accurately.
            if weak:
                weak_count += 1
            elif split == "train":
                for index, is_observed in enumerate(mask):
                    if is_observed:
                        (strong_pos if target[index] else strong_neg)[index] += 1
            if split == "train":
                sampling_weights.append(row["language_weight"] * row["source_weight"])
        rows_by_split[split] = Dataset.from_list(rows)
        raw_rows_by_split[split] = raw_rows
        masks_by_split[split] = np.asarray(masks, dtype=np.float32)
        targets_by_split[split] = np.asarray(targets, dtype=np.float32)
        split_summaries[split] = {"records": len(rows), "weak_records": weak_count}

    # Use observed strong negatives for class weighting.  Positive-only weak
    # rows are deliberately excluded from the negative denominator.
    pos_weight = np.clip(np.sqrt((strong_neg + 1.0) / (strong_pos + 1.0)), 1.0, args.max_pos_weight).astype(np.float32)
    encoded = DatasetDict(rows_by_split)
    tokenizer = AutoTokenizer.from_pretrained(str(args.init_model), local_files_only=True)
    # Some preserved research checkpoints use the legacy RoBERTa LayerNorm
    # names ``gamma``/``beta``.  Construct from config and normalize those
    # keys before loading so the encoder is never silently reinitialized.
    model_config = AutoConfig.from_pretrained(str(args.init_model), local_files_only=True)
    model_config.num_labels = len(labels)
    model_config.id2label = {index: label for index, label in enumerate(labels)}
    model_config.label2id = label_to_id
    model_config.problem_type = "multi_label_classification"
    model = AutoModelForSequenceClassification.from_config(model_config)
    weights_path = args.init_model / "model.safetensors"
    if weights_path.exists():
        state = load_safetensors(str(weights_path), device="cpu")
    else:
        legacy_path = args.init_model / "pytorch_model.bin"
        if not legacy_path.exists():
            raise DatasetAuditError(f"initial checkpoint weights not found under {args.init_model}")
        state = torch.load(str(legacy_path), map_location="cpu", weights_only=True)
        if isinstance(state, dict) and "state_dict" in state:
            state = state["state_dict"]
    normalized_state = {}
    for key, tensor in state.items():
        if key.endswith(".LayerNorm.gamma"):
            key = key[:-len(".gamma")] + ".weight"
        elif key.endswith(".LayerNorm.beta"):
            key = key[:-len(".beta")] + ".bias"
        normalized_state[key] = tensor
    load_info = model.load_state_dict(normalized_state, strict=False)
    if load_info.missing_keys or load_info.unexpected_keys:
        raise DatasetAuditError(json.dumps({"checkpoint_load_missing": load_info.missing_keys, "checkpoint_load_unexpected": load_info.unexpected_keys}, indent=2))

    def tokenize(batch):
        return tokenizer(batch["text"], truncation=True, max_length=256)

    encoded = encoded.map(tokenize, batched=True, remove_columns=["text"])
    validation_mask = masks_by_split["validation"]

    def unpack(pred):
        logits = pred.predictions if hasattr(pred, "predictions") else pred[0]
        target = pred.label_ids if hasattr(pred, "label_ids") else pred[1]
        if isinstance(logits, tuple):
            logits = logits[0]
        return np.asarray(logits), np.asarray(target)

    def metric_fn(pred):
        logits, target = unpack(pred)
        probabilities = sigmoid(logits)
        mask = validation_mask if len(target) == len(validation_mask) else masks_by_split["test"]
        result = score_masked(target, probabilities, mask, labels, {label: 0.5 for label in labels})
        return {key: value for key, value in result.items() if key in {"micro_precision", "micro_recall", "micro_f1", "macro_f1"} and value is not None}

    class WeakSupervisionTrainer(Trainer):
        def get_train_dataloader(self):
            from torch.utils.data import DataLoader, WeightedRandomSampler
            sampler = WeightedRandomSampler(torch.as_tensor(sampling_weights, dtype=torch.double), num_samples=len(sampling_weights), replacement=True)
            return DataLoader(self.train_dataset, batch_size=self._train_batch_size, sampler=sampler, collate_fn=self.data_collator, drop_last=self.args.dataloader_drop_last, num_workers=self.args.dataloader_num_workers, pin_memory=self.args.dataloader_pin_memory)

        def compute_loss(self, model, inputs, return_outputs=False, num_items_in_batch=None):
            labels_tensor = inputs.pop("labels")
            mask_tensor = inputs.pop("label_mask")
            sample_weight = inputs.pop("sample_weight", None)
            inputs.pop("language_weight", None)
            inputs.pop("source_weight", None)
            inputs.pop("weak_supervision", None)
            outputs = model(**inputs)
            if sample_weight is None:
                sample_weight = torch.ones(labels_tensor.shape[0], device=labels_tensor.device, dtype=labels_tensor.dtype)
            loss = masked_weak_loss(
                outputs.logits, labels_tensor, mask_tensor, sample_weight,
                torch.as_tensor(pos_weight, device=labels_tensor.device, dtype=labels_tensor.dtype),
            )
            return (loss, outputs) if return_outputs else loss

    staging = args.output.parent / f".{args.output.name}.staging-{__import__('os').getpid()}"
    staging.mkdir(parents=True, exist_ok=False)
    start = time.perf_counter()
    peak = 0
    try:
        kwargs = dict(
            output_dir=str(staging / "checkpoints"), learning_rate=args.learning_rate,
            per_device_train_batch_size=args.batch_size, per_device_eval_batch_size=args.batch_size,
            num_train_epochs=args.epochs, weight_decay=0.01, seed=args.seed,
            logging_strategy="epoch", save_strategy="epoch", load_best_model_at_end=True,
            metric_for_best_model="eval_macro_f1", greater_is_better=True, report_to=[],
            remove_unused_columns=False, fp16=bool(torch.cuda.is_available()), save_total_limit=2,
        )
        try:
            train_args = TrainingArguments(evaluation_strategy="epoch", **kwargs)
        except TypeError:
            train_args = TrainingArguments(eval_strategy="epoch", **kwargs)
        trainer_kwargs = dict(model=model, args=train_args, train_dataset=encoded["train"], eval_dataset=encoded["validation"], compute_metrics=metric_fn, data_collator=DataCollatorWithPadding(tokenizer))
        try:
            trainer = WeakSupervisionTrainer(tokenizer=tokenizer, **trainer_kwargs)
        except TypeError:
            trainer = WeakSupervisionTrainer(processing_class=tokenizer, **trainer_kwargs)
        trainer.train()
        if torch.cuda.is_available():
            peak = int(torch.cuda.max_memory_allocated())

        validation_pred = trainer.predict(encoded["validation"])
        validation_logits, validation_target = unpack(validation_pred)
        validation_probabilities = sigmoid(validation_logits)
        trainer.save_model(str(staging / "model"))
        tokenizer.save_pretrained(str(staging / "model"))
        calibration = calibrate_validation(
            raw_rows_by_split["validation"], validation_probabilities, labels,
            taxonomy_version=audit.taxonomy_version, checkpoint_id=args.output.name,
            checkpoint_sha256=sha256_file(staging / "model" / "model.safetensors"),
            validation_sha256=sha256_file(_jsonl_path(args.dataset, "validation")),
        )
        thresholds = calibration["thresholds"]

        test_pred = trainer.predict(encoded["test"])
        test_logits, test_target = unpack(test_pred)
        test_probabilities = sigmoid(test_logits)
        test_metrics = score_masked(test_target, test_probabilities, masks_by_split["test"], labels, thresholds)
        model_dir = staging / "model"
        (model_dir / "label_mapping.json").write_text(json.dumps({"taxonomy_version": audit.taxonomy_version, "labels": labels, "label_to_id": label_to_id}, indent=2) + "\n", encoding="utf-8")
        (model_dir / "thresholds.json").write_text(json.dumps(calibration, indent=2) + "\n", encoding="utf-8")
        (model_dir / "dataset_manifest.json").write_text(json.dumps({"dataset_dir": str(args.dataset), "audit": audit.to_dict(), "weak_supervision": True}, indent=2) + "\n", encoding="utf-8")
        config = {
            "schema": "mindcare-emotion35-v4-training-v1", "seed": args.seed, "epochs": args.epochs,
            "learning_rate": args.learning_rate, "batch_size": args.batch_size, "weak_loss_weight": args.weak_weight,
            "masked_loss": True, "weak_positive_only_unknowns_masked": True, "explicit_contrast_negatives_only": True,
            "sampling_weights": {
                "language": {"ENGLISH": args.english_sampling_weight, "FILIPINO": args.filipino_sampling_weight, "TAGLISH": args.taglish_sampling_weight},
                "source": {"weak_supervision_multiplier": args.weak_sampling_multiplier, "source_annotated_multiplier": 1.0},
            },
            "max_pos_weight": args.max_pos_weight, "strong_pos_counts": strong_pos.tolist(), "strong_neg_counts": strong_neg.tolist(),
            "split_summaries": split_summaries, "cuda": bool(torch.cuda.is_available()),
            "gpu_name": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,
            "peak_cuda_memory_bytes": peak, "production_model_untouched": True, "prototype_only": True,
            "validation_calibration_minimum_observed_positive": 5, "validation_calibration_minimum_observed_negative": 5,
            "human_reviewed_rows": 0, "student_chat_enabled": False, "admin_analytics_enabled": False,
        }
        (model_dir / "training_config.json").write_text(json.dumps(config, indent=2) + "\n", encoding="utf-8")
        (model_dir / "training_log.json").write_text(json.dumps(trainer.state.log_history, indent=2) + "\n", encoding="utf-8")
        metrics = {"schema": "mindcare-emotion35-v4-metrics-v1", "device": "cuda" if torch.cuda.is_available() else "cpu", "test_metrics_masked": test_metrics, "test_observed_targets": int(masks_by_split["test"].sum()), "training_wall_seconds": time.perf_counter() - start, "peak_cuda_memory_bytes": peak, "validation_calibration": calibration}
        (model_dir / "metrics.json").write_text(json.dumps(metrics, indent=2) + "\n", encoding="utf-8")
        (model_dir / "prototype_only.json").write_text(json.dumps({"prototype_only": True, "student_chat_enabled": False, "admin_analytics_enabled": False, "human_reviewed": False, "weak_supervision": True}, indent=2) + "\n", encoding="utf-8")
        shutil.move(str(model_dir), str(args.output))
        print(json.dumps({"trained": True, "output": str(args.output), "cuda": bool(torch.cuda.is_available()), "gpu": torch.cuda.get_device_name(0) if torch.cuda.is_available() else None, "peak_cuda_memory_bytes": peak, "metrics": metrics}, indent=2))
    finally:
        shutil.rmtree(staging, ignore_errors=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--provenance", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--init-model", type=Path, required=True)
    parser.add_argument("--epochs", type=float, default=2.0)
    parser.add_argument("--batch-size", type=int, default=8)
    parser.add_argument("--learning-rate", type=float, default=2e-5)
    parser.add_argument("--weak-weight", type=float, default=0.35)
    parser.add_argument("--english-sampling-weight", type=float, default=1.0)
    parser.add_argument("--filipino-sampling-weight", type=float, default=12.0)
    parser.add_argument("--taglish-sampling-weight", type=float, default=10.0)
    parser.add_argument("--weak-sampling-multiplier", type=float, default=1.25)
    parser.add_argument("--max-pos-weight", type=float, default=6.0)
    parser.add_argument("--seed", type=int, default=20261103)
    parser.add_argument("--allow-partial-labels", action="store_true", help="explicit research-only opt-in for incomplete per-label coverage")
    parser.add_argument("--min-train-per-label", type=int, default=20)
    parser.add_argument("--min-validation-per-label", type=int, default=5)
    parser.add_argument("--min-test-per-label", type=int, default=5)
    parser.add_argument("--exclude-weak-training", action="store_true", help="control run: exclude weak rows from the training split while retaining validation/test")
    main(parser.parse_args())
