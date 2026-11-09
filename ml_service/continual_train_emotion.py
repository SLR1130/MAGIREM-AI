#!/usr/bin/env python3
"""Gated continual-training pipeline for the existing six-class RoBERTa model.

This module is deliberately offline-only.  It never writes to the deployed
model bundle and refuses a training run until human-adjudicated labels and a
separate final-evaluation manifest are supplied.  ``--dry-run`` loads the
production checkpoint read-only and verifies the replay/distillation path with
synthetic rows.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import math
import random
import re
import time
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any, Iterable, Sequence

import numpy as np
import torch
from sklearn.metrics import (
    accuracy_score,
    confusion_matrix,
    precision_recall_fscore_support,
)
from transformers import AutoModelForSequenceClassification, AutoTokenizer


ROOT = Path(__file__).resolve().parents[1]
CHECKPOINT = ROOT / "ml_service" / "emotion_model_multilingual_candidate" / "checkpoint-130"
TOKENIZER = ROOT / "ml_service" / "emotion_model_multilingual_deployable"
DATASET_DIR = ROOT / "ml_service" / "emotion_data_multilingual_final_v2"
DEFAULT_ADJUDICATED = Path(r"C:\tmp\mindcare-filipino-emotion-adjudicated-20261019.csv")
DEFAULT_LEAKAGE_MANIFEST = Path(r"C:\tmp\mindcare-filipino-final-eval-leakage-manifest-20261019.json")
LABELS = ("sadness", "joy", "anxiety", "anger", "neutral", "grief")
LABEL2ID = {label: index for index, label in enumerate(LABELS)}
ID2LABEL = {index: label for label, index in LABEL2ID.items()}
LANGUAGES = {"TAGALOG", "TAGLISH", "ENGLISH"}
MAX_LENGTH = 128
SEED = 42


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest().upper()


def normalized_text(text: str) -> str:
    return re.sub(r"\s+", " ", text.casefold()).strip()


def set_seed(seed: int = SEED) -> None:
    random.seed(seed)
    np.random.seed(seed)
    torch.manual_seed(seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed_all(seed)


def load_jsonl(path: Path) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    with path.open("r", encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, start=1):
            if not line.strip():
                continue
            try:
                value = json.loads(line)
            except json.JSONDecodeError as exc:
                raise ValueError(f"Invalid JSON at {path}:{line_number}: {exc}") from exc
            if not isinstance(value, dict):
                raise ValueError(f"Expected an object at {path}:{line_number}")
            rows.append(value)
    return rows


def load_legacy_jsonl(path: Path, split: str) -> list[dict[str, Any]]:
    rows = load_jsonl(path)
    result: list[dict[str, Any]] = []
    for index, row in enumerate(rows):
        text = str(row.get("text", ""))
        emotion = str(row.get("emotion", "")).casefold()
        language = str(row.get("language", "")).upper()
        result.append(
            {
                "id": str(row.get("id") or f"{split}-{index:04d}"),
                "text": text,
                "emotion": emotion,
                "label": LABEL2ID.get(emotion),
                "language": language,
                "split": split,
                "source": "legacy",
            }
        )
    return result


def _first_value(row: dict[str, str], names: Sequence[str]) -> str:
    for name in names:
        value = (row.get(name) or "").strip()
        if value:
            return value
    return ""


def load_adjudicated_csv(path: Path, allow_final_partition: bool = False) -> list[dict[str, Any]]:
    """Load only completed, independently reviewed adjudications.

    The reviewer and adjudicator fields are mandatory.  AI-provisional columns
    are intentionally ignored and cannot satisfy this contract.
    """
    if not path.exists():
        raise FileNotFoundError(f"Human-adjudication file is required and was not found: {path}")
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if not reader.fieldnames:
            raise ValueError(f"Adjudication file has no header: {path}")
        required = {
            "opaque_review_id", "original_text", "reviewer_a_primary",
            "reviewer_b_primary", "reviewer_a_language", "reviewer_b_language",
            "final_adjudicated_label", "final_adjudicated_language",
            "review_status", "partition", "human_validated",
        }
        missing = sorted(required - set(reader.fieldnames))
        if missing:
            raise ValueError(f"Adjudication file is missing required human-review fields: {missing}")
        rows = list(reader)
    result: list[dict[str, Any]] = []
    seen_ids: set[str] = set()
    seen_text: dict[str, str] = {}
    for index, row in enumerate(rows):
        source_id = (row.get("opaque_review_id") or "").strip()
        text = (row.get("original_text") or "").strip()
        reviewer_a = (row.get("reviewer_a_primary") or "").strip().casefold()
        reviewer_b = (row.get("reviewer_b_primary") or "").strip().casefold()
        reviewer_a_language = (row.get("reviewer_a_language") or "").strip().upper()
        reviewer_b_language = (row.get("reviewer_b_language") or "").strip().upper()
        emotion = (row.get("final_adjudicated_label") or "").strip().casefold()
        language = (row.get("final_adjudicated_language") or "").strip().upper()
        status = (row.get("review_status") or "").strip().upper()
        partition = (row.get("partition") or "").strip().casefold()
        human_validated = (row.get("human_validated") or "").strip().upper()
        if not source_id:
            raise ValueError(f"Adjudication row {index + 2} has no opaque_review_id")
        if source_id in seen_ids:
            raise ValueError(f"Duplicate adjudication ID: {source_id}")
        seen_ids.add(source_id)
        if not text:
            raise ValueError(f"Empty original_text for {source_id}")
        if reviewer_a not in LABEL2ID or reviewer_b not in LABEL2ID or emotion not in LABEL2ID:
            raise ValueError(f"Invalid reviewer or adjudicated emotion for {source_id}")
        if reviewer_a_language not in LANGUAGES or reviewer_b_language not in LANGUAGES or language not in LANGUAGES:
            raise ValueError(f"Invalid reviewer or adjudicated language for {source_id}")
        allowed_statuses = {"AGREED", "ADJUDICATED"} | ({"FINAL", "HUMAN_CONFIRMED"} if allow_final_partition else set())
        allowed_partitions = {"train", "development", "final"} if allow_final_partition else {"train", "development"}
        if status not in allowed_statuses:
            raise ValueError(f"Invalid or incomplete review_status for {source_id}: {status!r}")
        if partition not in allowed_partitions:
            raise ValueError(f"Invalid partition for {source_id}: {partition!r}")
        if human_validated != "YES":
            raise ValueError(f"human_validated must be YES for {source_id}")
        if status == "AGREED":
            if not (reviewer_a == reviewer_b == emotion):
                raise ValueError(f"AGREED row has contradictory reviewer/final emotion values: {source_id}")
            if not (reviewer_a_language == reviewer_b_language == language):
                raise ValueError(f"AGREED row has contradictory reviewer/final language values: {source_id}")
        elif status == "ADJUDICATED" and reviewer_a == reviewer_b and reviewer_a_language == reviewer_b_language:
            raise ValueError(f"ADJUDICATED requires a recorded reviewer disagreement: {source_id}")
        key = normalized_text(text)
        if key in seen_text:
            raise ValueError(f"Duplicate adjudicated text: {source_id} and {seen_text[key]}")
        seen_text[key] = source_id
        result.append({
            "id": source_id,
            "text": text,
            "emotion": emotion,
            "label": LABEL2ID[emotion],
            "language": language,
            "split": partition,
            "source": "human_adjudicated",
            "human_validated": True,
            "review_status": status,
        })
    if not result:
        raise ValueError("Adjudication file contains no rows")
    return result


def validate_against_reconciliation(adjudicated_rows: Sequence[dict[str, Any]], path: Path | None) -> None:
    """Cross-check the canonical adjudication file against an actual master record."""
    if path is None:
        return
    if not path.exists():
        raise FileNotFoundError(f"Reconciliation file was requested but not found: {path}")
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if not reader.fieldnames:
            raise ValueError(f"Reconciliation file has no header: {path}")
        required = {"opaque_review_id", "original_text", "reviewer_a_primary", "reviewer_b_primary"}
        missing = sorted(required - set(reader.fieldnames))
        if missing:
            raise ValueError(f"Reconciliation file lacks reviewer evidence: {missing}")
        master = {str(row.get("opaque_review_id") or "").strip(): row for row in reader}
    for item in adjudicated_rows:
        row = master.get(item["id"])
        if row is None:
            raise ValueError(f"Adjudication ID is absent from reconciliation records: {item['id']}")
        if normalized_text(str(row.get("original_text") or "")) != normalized_text(item["text"]):
            raise ValueError(f"Reconciliation text mismatch for {item['id']}")
        if (row.get("reviewer_a_primary") or "").strip().casefold() != item["emotion"] and item["review_status"] == "AGREED":
            raise ValueError(f"Reconciliation Reviewer A value disagrees for {item['id']}")
        if (row.get("reviewer_b_primary") or "").strip().casefold() != item["emotion"] and item["review_status"] == "AGREED":
            raise ValueError(f"Reconciliation Reviewer B value disagrees for {item['id']}")
        if (row.get("primary_status") or row.get("review_status") or "").strip().upper() in {"PENDING", "INCOMPLETE", "UNRESOLVED", "PROVISIONAL"}:
            raise ValueError(f"Reconciliation record is not complete for {item['id']}")


def normalized_fingerprint(text: str) -> str:
    return hashlib.sha256(normalized_text(text).encode("utf-8")).hexdigest()


def load_leakage_manifest(path: Path | None, required: bool = False) -> set[str]:
    """Load IDs and normalized-text hashes only; reference labels are forbidden."""
    if path is None:
        if required:
            raise FileNotFoundError("A label-free final-evaluation leakage manifest is required")
        return set()
    if not path.exists():
        raise FileNotFoundError(f"Leakage manifest was not found: {path}")
    with path.open("r", encoding="utf-8") as handle:
        payload = json.load(handle)
    records = payload.get("records") if isinstance(payload, dict) else payload
    if not isinstance(records, list) or not records:
        raise ValueError("Leakage manifest must contain a non-empty records array")
    fingerprints: set[str] = set()
    ids: set[str] = set()
    forbidden = {"text", "original_text", "emotion", "label", "language", "reference", "prediction"}
    for index, record in enumerate(records):
        if not isinstance(record, dict) or forbidden.intersection(record):
            raise ValueError(f"Leakage manifest record {index + 1} contains forbidden reference data")
        identifier = str(record.get("opaque_review_id") or record.get("id") or "").strip()
        fingerprint = str(record.get("normalized_text_sha256") or "").strip().lower()
        if not identifier or identifier in ids or not re.fullmatch(r"[0-9a-f]{64}", fingerprint):
            raise ValueError(f"Malformed leakage manifest record {index + 1}")
        ids.add(identifier)
        fingerprints.add(fingerprint)
    return fingerprints

def load_final_evaluation_csv(path: Path) -> list[dict[str, Any]]:
    """Load an independently reviewed final set; never used by training."""
    if path.suffix.casefold() != ".csv":
        raise ValueError("Final evaluation must be a canonical human-reviewed CSV")
    return load_adjudicated_csv(path, allow_final_partition=True)


def load_labeled_csv_or_jsonl(path: Path) -> list[dict[str, Any]]:
    if path.suffix.casefold() in {".jsonl", ".json"}:
        source_rows = load_jsonl(path)
        result: list[dict[str, Any]] = []
        for index, row in enumerate(source_rows):
            emotion = str(row.get("emotion") or row.get("adjudicated_emotion") or "").casefold()
            language = str(row.get("language") or row.get("adjudicated_language") or "").upper()
            text = str(row.get("text") or row.get("original_text") or "")
            if emotion not in LABEL2ID or language not in LANGUAGES or not text.strip():
                raise ValueError(f"Invalid final-evaluation row {index + 1} in {path}")
            result.append({"id": str(row.get("id") or f"final-{index:04d}"), "text": text, "emotion": emotion, "label": LABEL2ID[emotion], "language": language, "source": "human_final"})
        return result
    return load_adjudicated_csv(path)


def _near_pairs(rows_a: Sequence[dict[str, Any]], rows_b: Sequence[dict[str, Any]], threshold: float = 0.92) -> list[dict[str, Any]]:
    pairs: list[dict[str, Any]] = []
    for left in rows_a:
        left_text = normalized_text(left["text"])
        if not left_text:
            continue
        for right in rows_b:
            right_text = normalized_text(right["text"])
            if not right_text:
                continue
            score = SequenceMatcher(None, left_text, right_text).ratio()
            if score >= threshold:
                pairs.append({"left_id": left["id"], "right_id": right["id"], "similarity": round(score, 6), "left_split": left.get("split", ""), "right_split": right.get("split", "")})
    return pairs


def audit_legacy_and_leakage(
    legacy_train: list[dict[str, Any]],
    validation: list[dict[str, Any]],
    test: list[dict[str, Any]],
    new_rows: Sequence[dict[str, Any]] = (),
) -> dict[str, Any]:
    """Audit without changing source files; return an eligible replay subset."""
    invalid: list[dict[str, Any]] = []
    by_text: defaultdict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in legacy_train:
        key = normalized_text(row["text"])
        if not key or row.get("label") not in range(6) or row.get("language") not in LANGUAGES:
            invalid.append({"id": row["id"], "reason": "empty, invalid label, or invalid language"})
        by_text[key].append(row)
    contradictory: list[dict[str, Any]] = []
    duplicate_ids: set[str] = set()
    for key, group in by_text.items():
        labels = {row.get("label") for row in group}
        if len(group) > 1:
            duplicate_ids.update(row["id"] for row in group)
            if len(labels) > 1:
                contradictory.append({"text_key": key, "ids": [row["id"] for row in group], "labels": [ID2LABEL.get(row.get("label"), "invalid") for row in group]})
    cross_split = _near_pairs(legacy_train, validation + test)
    new_cross_split = _near_pairs(list(new_rows), legacy_train + validation + test)
    quarantine_ids = {item["id"] for item in invalid} | duplicate_ids
    for pair in cross_split:
        quarantine_ids.add(pair["left_id"])
    eligible = [row for row in legacy_train if row["id"] not in quarantine_ids and row.get("label") in range(6) and row.get("language") in LANGUAGES]
    return {
        "legacy_total": len(legacy_train),
        "eligible_replay_total": len(eligible),
        "invalid": invalid,
        "duplicate_ids": sorted(duplicate_ids),
        "contradictory": contradictory,
        "cross_split_near_duplicates": cross_split,
        "new_cross_split_near_duplicates": new_cross_split,
        "quarantined_ids": sorted(quarantine_ids),
        "eligible_replay": eligible,
    }


def partition_new_rows(rows: list[dict[str, Any]], dev_fraction: float = 0.2) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    explicit = [row.get("split", "") for row in rows]
    if any(explicit):
        if any(value not in {"train", "development"} for value in explicit):
            raise ValueError("Every adjudicated row must have partition=train or partition=development")
        train = [row for row in rows if row.get("split") == "train"]
        dev = [row for row in rows if row.get("split") == "development"]
        if not train or not dev:
            raise ValueError("Both train and development partitions are required")
        return train, dev
    grouped: defaultdict[int, list[dict[str, Any]]] = defaultdict(list)
    for row in sorted(rows, key=lambda item: item["id"]):
        grouped[row["label"]].append(row)
    train: list[dict[str, Any]] = []
    dev: list[dict[str, Any]] = []
    for group in grouped.values():
        n_dev = max(1, int(round(len(group) * dev_fraction))) if len(group) > 1 else 0
        dev.extend(group[:n_dev])
        train.extend(group[n_dev:])
    if not train or not dev:
        raise ValueError("Adjudicated data must produce non-empty train and development partitions")
    return train, dev

def verify_label_mapping(model: Any) -> None:
    model_label2id = {str(key): int(value) for key, value in model.config.label2id.items()}
    model_id2label = {int(key): str(value) for key, value in model.config.id2label.items()}
    if model_label2id != LABEL2ID or model_id2label != ID2LABEL or int(model.config.num_labels) != 6:
        raise RuntimeError(f"Checkpoint label mapping mismatch: {model_label2id} / {model_id2label}")


def load_checkpoint_pair(device: torch.device) -> tuple[Any, Any, Any]:
    tokenizer = AutoTokenizer.from_pretrained(str(TOKENIZER), local_files_only=True)
    student = AutoModelForSequenceClassification.from_pretrained(str(CHECKPOINT), local_files_only=True)
    teacher = AutoModelForSequenceClassification.from_pretrained(str(CHECKPOINT), local_files_only=True)
    verify_label_mapping(student)
    verify_label_mapping(teacher)
    student.to(device)
    teacher.to(device)
    teacher.eval()
    for parameter in teacher.parameters():
        parameter.requires_grad_(False)
    return tokenizer, student, teacher


def encode(tokenizer: Any, rows: Sequence[dict[str, Any]], device: torch.device) -> dict[str, torch.Tensor]:
    encoded = tokenizer(
        [row["text"] for row in rows],
        truncation=True,
        max_length=MAX_LENGTH,
        padding=True,
        return_tensors="pt",
    )
    return {key: value.to(device) for key, value in encoded.items()}


def distillation_losses(
    student: Any,
    teacher: Any,
    tokenizer: Any,
    rows: Sequence[dict[str, Any]],
    device: torch.device,
    replay_count: int = 8,
) -> tuple[torch.Tensor, torch.Tensor, torch.Tensor, torch.Tensor, torch.Tensor]:
    if len(rows) != 12 or replay_count != 8:
        raise ValueError("The approved batch contract is exactly 8 replay rows and 4 new rows")
    student_inputs = encode(tokenizer, rows, device)
    labels = torch.tensor([int(row["label"]) for row in rows], dtype=torch.long, device=device)
    student_logits = student(**student_inputs).logits
    ce = torch.nn.functional.cross_entropy(student_logits, labels)
    replay_inputs = encode(tokenizer, rows[:replay_count], device)
    with torch.no_grad():
        teacher_logits = teacher(**replay_inputs).logits
    student_replay_logits = student_logits[:replay_count]
    temperature = 2.0
    kd = torch.nn.functional.kl_div(
        torch.nn.functional.log_softmax(student_replay_logits / temperature, dim=-1),
        torch.nn.functional.softmax(teacher_logits / temperature, dim=-1),
        reduction="batchmean",
    ) * (temperature ** 2)
    total = 0.70 * ce + 0.30 * kd
    return total, ce, kd, student_logits, teacher_logits


def mixed_batches(
    replay_rows: Sequence[dict[str, Any]],
    new_rows: Sequence[dict[str, Any]],
    seed: int,
    batches: int | None = None,
) -> Iterable[list[dict[str, Any]]]:
    if len(replay_rows) < 8 or len(new_rows) < 4:
        raise ValueError("At least 8 eligible replay rows and 4 adjudicated-new rows are required")
    rng = random.Random(seed)
    replay_order = list(range(len(replay_rows)))
    new_order = list(range(len(new_rows)))
    count = batches or max(math.ceil(len(replay_rows) / 8), math.ceil(len(new_rows) / 4))
    for batch_index in range(count):
        if batch_index * 8 + 8 > len(replay_order):
            rng.shuffle(replay_order)
        if batch_index * 4 + 4 > len(new_order):
            rng.shuffle(new_order)
        replay_start = (batch_index * 8) % len(replay_order)
        new_start = (batch_index * 4) % len(new_order)
        yield [replay_rows[replay_order[(replay_start + offset) % len(replay_order)]] for offset in range(8)] + [new_rows[new_order[(new_start + offset) % len(new_order)]] for offset in range(4)]


def classification_metrics(logits: np.ndarray, labels: np.ndarray) -> dict[str, Any]:
    predicted = np.argmax(logits, axis=1)
    precision, recall, f1, support = precision_recall_fscore_support(labels, predicted, labels=list(range(6)), zero_division=0)
    probabilities = torch.softmax(torch.tensor(logits), dim=1).numpy()
    confidence = probabilities.max(axis=1)
    return {
        "count": int(len(labels)),
        "accuracy": float(accuracy_score(labels, predicted)),
        "macro_f1": float(np.mean(f1)),
        "macro_precision": float(np.mean(precision)),
        "macro_recall": float(np.mean(recall)),
        "per_class": {ID2LABEL[index]: {"precision": float(precision[index]), "recall": float(recall[index]), "f1": float(f1[index]), "support": int(support[index])} for index in range(6)},
        "confusion_matrix": confusion_matrix(labels, predicted, labels=list(range(6))).tolist(),
        "sadness_false_positive_rate": float(((predicted == LABEL2ID["sadness"]) & (labels != LABEL2ID["sadness"])).sum() / max(1, (labels != LABEL2ID["sadness"]).sum())),
        "mean_confidence": float(confidence.mean()) if len(confidence) else 0.0,
        "confidence_ge_099": int((confidence >= 0.99).sum()),
        "predicted": predicted.tolist(),
    }


def evaluate_rows(model: Any, tokenizer: Any, rows: Sequence[dict[str, Any]], device: torch.device, batch_size: int = 16) -> dict[str, Any]:
    model.eval()
    logits_parts: list[np.ndarray] = []
    started = time.perf_counter()
    with torch.no_grad():
        for start in range(0, len(rows), batch_size):
            batch = rows[start : start + batch_size]
            logits_parts.append(model(**encode(tokenizer, batch, device)).logits.detach().cpu().numpy())
    elapsed = time.perf_counter() - started
    logits = np.concatenate(logits_parts, axis=0) if logits_parts else np.empty((0, 6))
    labels = np.asarray([row["label"] for row in rows], dtype=np.int64)
    result = classification_metrics(logits, labels)
    result["elapsed_seconds"] = elapsed
    result["mean_inference_ms"] = elapsed * 1000 / max(1, len(rows))
    by_language: dict[str, Any] = {}
    for language in sorted({row.get("language", "") for row in rows}):
        indexes = [index for index, row in enumerate(rows) if row.get("language") == language]
        by_language[language] = classification_metrics(logits[indexes], labels[indexes])
    result["by_language"] = by_language
    return result


def source_manifest() -> dict[str, Any]:
    paths = {
        "checkpoint_config": CHECKPOINT / "config.json",
        "checkpoint_weights": CHECKPOINT / "model.safetensors",
        "tokenizer": TOKENIZER / "tokenizer.json",
        "tokenizer_config": TOKENIZER / "tokenizer_config.json",
        "label_mapping": DATASET_DIR / "label_mapping.json",
        "legacy_train": DATASET_DIR / "train.jsonl",
        "legacy_validation": DATASET_DIR / "validation.jsonl",
        "legacy_test": DATASET_DIR / "test.jsonl",
    }
    missing = [f"{name}: {path}" for name, path in paths.items() if not path.exists()]
    if missing:
        raise FileNotFoundError("Required source input(s) missing:\n" + "\n".join(missing))
    return {name: {"path": str(path), "sha256": sha256_file(path), "bytes": path.stat().st_size} for name, path in paths.items()}


def dry_run(output_dir: Path) -> int:
    output_dir.mkdir(parents=True, exist_ok=True)
    set_seed(SEED)
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    manifest = source_manifest()
    legacy = load_legacy_jsonl(DATASET_DIR / "train.jsonl", "train")
    validation = load_legacy_jsonl(DATASET_DIR / "validation.jsonl", "development")
    test = load_legacy_jsonl(DATASET_DIR / "test.jsonl", "diagnostic")
    audit = audit_legacy_and_leakage(legacy, validation, test)
    tokenizer, student, teacher = load_checkpoint_pair(device)
    synthetic_replay = [{"id": f"replay-{index}", "text": f"synthetic replay example {index}", "label": index % 6, "emotion": LABELS[index % 6], "language": ("TAGALOG", "TAGLISH", "ENGLISH")[index % 3]} for index in range(8)]
    synthetic_new = [{"id": f"new-{index}", "text": f"synthetic adjudicated example {index}", "label": (index + 1) % 6, "emotion": LABELS[(index + 1) % 6], "language": ("TAGALOG", "TAGLISH", "ENGLISH")[index % 3]} for index in range(4)]
    batch = next(iter(mixed_batches(synthetic_replay, synthetic_new, SEED, batches=1)))
    teacher_before = {name: value.detach().cpu().clone() for name, value in teacher.state_dict().items()}
    student.zero_grad(set_to_none=True)
    total, ce, kd, student_logits, _ = distillation_losses(student, teacher, tokenizer, batch, device)
    total.backward()
    student_has_grad = any(parameter.grad is not None for parameter in student.parameters() if parameter.requires_grad)
    teacher_has_grad = any(parameter.grad is not None for parameter in teacher.parameters())
    teacher_unchanged = all(torch.equal(value, teacher.state_dict()[name].detach().cpu()) for name, value in teacher_before.items())
    report = {
        "status": "TRAINING_BLOCKED_HUMAN_LABELS",
        "training_executed": False,
        "required_adjudication_file": str(DEFAULT_ADJUDICATED),
        "device": str(device),
        "checkpoint": str(CHECKPOINT),
        "tokenizer": str(TOKENIZER),
        "label_mapping": LABEL2ID,
        "source_manifest": manifest,
        "legacy_audit": {key: value for key, value in audit.items() if key != "eligible_replay"},
        "eligible_replay_count": len(audit["eligible_replay"]),
        "synthetic_batch": {"replay": 8, "new": 4, "total": len(batch)},
        "losses": {"total": float(total.detach().cpu()), "cross_entropy": float(ce.detach().cpu()), "distillation_kl_scaled": float(kd.detach().cpu())},
        "student_logits_shape": list(student_logits.shape),
        "student_received_gradients": student_has_grad,
        "teacher_received_gradients": teacher_has_grad,
        "teacher_parameters_unchanged": teacher_unchanged,
        "output_is_outside_production": not str(output_dir).lower().startswith(str(ROOT).lower()),
    }
    (output_dir / "dry_run_report.json").write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print("TRAINING_BLOCKED_HUMAN_LABELS")
    print(f"DRY_RUN_REPORT={output_dir / 'dry_run_report.json'}")
    print(f"REQUIRED_ADJUDICATION_FILE={DEFAULT_ADJUDICATED}")
    print(f"REPLAY_ELIGIBLE={len(audit['eligible_replay'])}/{len(legacy)}")
    print(f"BATCH_CONTRACT=8_replay+4_new losses_finite={bool(torch.isfinite(total).item())}")
    print(f"TEACHER_GRADIENTS={teacher_has_grad} TEACHER_UNCHANGED={teacher_unchanged}")
    return 0


def run_training(args: argparse.Namespace) -> int:
    try:
        adjudicated = load_adjudicated_csv(args.adjudicated_csv)
        validate_against_reconciliation(adjudicated, args.reconciliation_csv)
        leakage_fingerprints = load_leakage_manifest(args.leakage_manifest, args.require_leakage_manifest)
    except (FileNotFoundError, ValueError, json.JSONDecodeError) as exc:
        print("TRAINING_BLOCKED_INPUT_CONTRACT")
        print(f"REASON={exc}")
        return 4
    output_dir = args.output_dir
    if output_dir.resolve().is_relative_to(ROOT.resolve()):
        raise ValueError("Experimental output must remain outside the production project")
    output_dir.mkdir(parents=True, exist_ok=True)
    set_seed(SEED)
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    legacy = load_legacy_jsonl(DATASET_DIR / "train.jsonl", "train")
    validation = load_legacy_jsonl(DATASET_DIR / "validation.jsonl", "development")
    test = load_legacy_jsonl(DATASET_DIR / "test.jsonl", "diagnostic")
    audit = audit_legacy_and_leakage(legacy, validation, test, adjudicated)
    if audit["new_cross_split_near_duplicates"]:
        raise ValueError("Adjudicated rows overlap legacy/development data; inspect the quarantine report before training")
    new_train, new_dev = partition_new_rows(adjudicated)
    if _near_pairs(new_train, new_dev):
        raise ValueError("Near-duplicate leakage exists between adjudicated train and development partitions")
    replay = audit["eligible_replay"]
    if len(replay) < 8 or len(new_train) < 4:
        raise ValueError("Insufficient eligible rows for the 8+4 batch contract")
    all_fit = replay + new_train + new_dev
    fit_fingerprints = {normalized_fingerprint(row["text"]) for row in all_fit}
    if leakage_fingerprints.intersection(fit_fingerprints):
        raise ValueError("Final-evaluation leakage detected by normalized text fingerprint")
    tokenizer, student, teacher = load_checkpoint_pair(device)
    optimizer = torch.optim.AdamW(student.parameters(), lr=1e-5, weight_decay=0.01)
    batches_per_epoch = max(math.ceil(len(replay) / 8), math.ceil(len(new_train) / 4))
    total_steps = batches_per_epoch * 3
    warmup_steps = max(1, math.ceil(total_steps * 0.10))
    scheduler = torch.optim.lr_scheduler.LambdaLR(optimizer, lambda step: min(1.0, (step + 1) / warmup_steps) if step < warmup_steps else max(0.0, (total_steps - step) / max(1, total_steps - warmup_steps)))
    best_f1 = -1.0
    stale_epochs = 0
    history: list[dict[str, Any]] = []
    for epoch in range(1, 4):
        student.train()
        losses: list[float] = []
        for batch in mixed_batches(replay, new_train, SEED + epoch, batches=batches_per_epoch):
            optimizer.zero_grad(set_to_none=True)
            total, ce, kd, _, _ = distillation_losses(student, teacher, tokenizer, batch, device)
            total.backward()
            torch.nn.utils.clip_grad_norm_(student.parameters(), 1.0)
            optimizer.step()
            scheduler.step()
            losses.append(float(total.detach().cpu()))
        metrics = evaluate_rows(student, tokenizer, validation + new_dev, device)
        history.append({"epoch": epoch, "mean_loss": float(np.mean(losses)), "learning_rate": float(optimizer.param_groups[0]["lr"]), "development": metrics})
        if metrics["macro_f1"] > best_f1:
            best_f1 = metrics["macro_f1"]
            stale_epochs = 0
            (output_dir / "candidate").mkdir(exist_ok=True)
            student.save_pretrained(str(output_dir / "candidate"), safe_serialization=True)
            tokenizer.save_pretrained(str(output_dir / "candidate"))
        else:
            stale_epochs += 1
            if stale_epochs >= 1:
                break
    manifest = source_manifest()
    manifest["adjudicated_csv"] = {"path": str(args.adjudicated_csv), "sha256": sha256_file(args.adjudicated_csv), "bytes": args.adjudicated_csv.stat().st_size}
    if args.reconciliation_csv:
        manifest["reconciliation_csv"] = {"path": str(args.reconciliation_csv), "sha256": sha256_file(args.reconciliation_csv), "bytes": args.reconciliation_csv.stat().st_size}
    if args.leakage_manifest:
        manifest["leakage_manifest"] = {"path": str(args.leakage_manifest), "sha256": sha256_file(args.leakage_manifest), "bytes": args.leakage_manifest.stat().st_size}
    (output_dir / "training_manifest.json").write_text(json.dumps({"config": vars(args), "label_mapping": LABEL2ID, "batch_contract": {"replay": 8, "new": 4}, "loss": {"supervised_weight": 0.70, "distillation_weight": 0.30, "temperature": 2.0}, "hyperparameters": {"learning_rate": 1e-5, "max_epochs": 3, "early_stopping_patience": 1, "weight_decay": 0.01, "max_grad_norm": 1.0, "warmup_fraction": 0.10, "max_length": MAX_LENGTH, "seed": SEED}, "source_manifest": manifest, "legacy_audit": {key: value for key, value in audit.items() if key != "eligible_replay"}, "history": history, "status": "CANDIDATE_NOT_PROMOTED"}, indent=2, default=str), encoding="utf-8")
    print("TRAINING_COMPLETE_CANDIDATE_NOT_PROMOTED")
    print(f"CANDIDATE={output_dir / 'candidate'}")
    return 0

def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Verify loading, data audit, batch contract, and losses without training")
    parser.add_argument("--run", action="store_true", help="Run one gated offline candidate training job")
    parser.add_argument("--output-dir", type=Path, default=Path(r"C:\tmp\mindcare-continued-roberta-run-20261019"))
    parser.add_argument("--adjudicated-csv", type=Path, default=DEFAULT_ADJUDICATED)
    parser.add_argument("--leakage-manifest", type=Path, default=None, help="Label-free final-evaluation fingerprint manifest")
    parser.add_argument("--require-leakage-manifest", action="store_true")
    parser.add_argument("--reconciliation-csv", type=Path, default=None, help="Completed reviewer reconciliation master")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if args.dry_run == args.run:
        raise SystemExit("Choose exactly one of --dry-run or --run")
    if args.dry_run:
        return dry_run(args.output_dir)
    return run_training(args)


if __name__ == "__main__":
    raise SystemExit(main())
