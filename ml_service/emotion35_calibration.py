"""Evidence-gated calibration for isolated Emotion35 research checkpoints.

Only explicitly observed validation labels contribute positive/negative
support. Unknown labels remain masked and never become calibration negatives.
This module is shared by the research trainers, recalibration utility, and
research inference loaders; production six-class inference does not import it.
"""
from __future__ import annotations

import hashlib
import json
import math
import string
from pathlib import Path
from typing import Any, Mapping, Sequence

import numpy as np


CALIBRATION_SCHEMA = "mindcare-emotion35-calibration-v1"
MIN_OBSERVED_POSITIVES = 5
MIN_OBSERVED_NEGATIVES = 5
DEFAULT_THRESHOLDS = tuple(round(value / 100, 2) for value in range(10, 91, 5))


class CalibrationArtifactError(ValueError):
    """A calibration artifact is incomplete, inconsistent, or unsafe to use."""


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with Path(path).open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _row_labels(row: Mapping[str, Any]) -> set[str]:
    raw = row.get("labels", row.get("emotions", row.get("emotion", row.get("label", []))))
    if isinstance(raw, str):
        raw = [raw]
    if not isinstance(raw, Sequence) or isinstance(raw, (bytes, bytearray, str)):
        return set()
    return {str(value).strip().lower() for value in raw if str(value).strip()}


def _row_observed(row: Mapping[str, Any], ordered_labels: Sequence[str]) -> set[str]:
    """Return explicit observations; a missing mask means unknown, not negative."""
    allowed = set(ordered_labels)
    if "observed_labels" in row:
        raw = row["observed_labels"]
    elif "label_mask" in row:
        raw = row["label_mask"]
    elif row.get("annotation_completeness") == "all_taxonomy_labels_observed":
        raw = list(allowed)
    else:
        raw = []
    if isinstance(raw, Mapping):
        values = [key for key, value in raw.items() if bool(value)]
    elif isinstance(raw, Sequence) and not isinstance(raw, (bytes, bytearray, str)):
        # A boolean/numeric mask is accepted only at exactly taxonomy width.
        if len(raw) == len(allowed) and raw and all(isinstance(value, (bool, int, float)) for value in raw):
            values = [ordered_labels[index] for index, value in enumerate(raw) if bool(value)]
        else:
            values = raw
    else:
        values = []
    observed = {str(value).strip().lower() for value in values if str(value).strip()}
    invalid = observed - allowed
    if invalid:
        raise CalibrationArtifactError(f"unknown observed emotion keys: {sorted(invalid)}")
    return observed


def _source(row: Mapping[str, Any]) -> str:
    provenance = row.get("provenance")
    if isinstance(provenance, Mapping):
        value = provenance.get("source_dataset") or provenance.get("source")
        if value:
            return str(value)
    return str(row.get("source_dataset") or row.get("source") or "unspecified_source")


def _evidence_kind(row: Mapping[str, Any]) -> str:
    if row.get("independently_evaluated") or row.get("independent_evaluation"):
        return "independently_evaluated"
    if row.get("weak_supervision"):
        return "weak_supervision"
    if row.get("synthetic") or row.get("ai_assisted"):
        return "ai_assisted_synthetic"
    return "source_annotated"


def _source_descriptor(row: Mapping[str, Any]) -> str:
    return f"{_source(row)}|{_evidence_kind(row)}"


def _evidence_quality(provenance_by_source: Mapping[str, Mapping[str, int]], *, calibrated: bool) -> dict[str, Any]:
    """Summarize evidence type without implying weak or synthetic labels are validated."""
    kinds = {"source_annotated": [0, 0, 0], "ai_assisted_synthetic": [0, 0, 0], "weak_supervision": [0, 0, 0], "independently_evaluated": [0, 0, 0]}
    for descriptor, counts in provenance_by_source.items():
        kind = descriptor.rsplit("|", 1)[-1]
        if kind not in kinds:
            continue
        kinds[kind][0] += int(counts.get("observed_positive", 0))
        kinds[kind][1] += int(counts.get("observed_negative", 0))
        kinds[kind][2] += int(counts.get("unknown", 0))
    observed_kinds = [kind for kind, values in kinds.items() if values[0] + values[1] > 0]
    if not calibrated:
        quality_class = "insufficient_observed_support"
    elif observed_kinds == ["source_annotated"]:
        quality_class = "source_annotated_only"
    elif observed_kinds == ["ai_assisted_synthetic"]:
        quality_class = "ai_assisted_synthetic_only"
    elif observed_kinds == ["weak_supervision"]:
        quality_class = "weak_supervision_only"
    elif observed_kinds == ["independently_evaluated"]:
        quality_class = "independently_evaluated_only"
    elif "independently_evaluated" in observed_kinds:
        quality_class = "includes_independent_evaluation"
    else:
        quality_class = "mixed_source_evidence"
    return {
        "quality_class": quality_class,
        "by_evidence_kind": {
            kind: {"observed_positive": values[0], "observed_negative": values[1], "unknown": values[2]}
            for kind, values in kinds.items()
        },
        "independently_evaluated_observed_cells": kinds["independently_evaluated"][0] + kinds["independently_evaluated"][1],
        "independent_evidence_present": kinds["independently_evaluated"][0] + kinds["independently_evaluated"][1] > 0,
        "note": "Calibration evidence provenance is not an independent model validation result; weakly supervised and AI-assisted synthetic labels are not human-approved ground truth.",
    }


def calibration_evidence_summary(artifact: Mapping[str, Any]) -> dict[str, Any]:
    """Summarize artifact evidence for research interfaces and reports."""
    statuses = artifact.get("label_status")
    if not isinstance(statuses, Mapping):
        raise CalibrationArtifactError("calibration label evidence metadata is missing")
    counts: dict[str, int] = {}
    for value in statuses.values():
        quality = value.get("evidence_quality") if isinstance(value, Mapping) else None
        if not isinstance(quality, Mapping):
            quality = _evidence_quality(value.get("provenance_by_source", {}), calibrated=value.get("status") == "calibrated")
        quality_class = str(quality.get("quality_class", "unavailable"))
        counts[quality_class] = counts.get(quality_class, 0) + 1
    independently_evaluated_rows = int(artifact.get("validation_provenance", {}).get("evidence_kind_counts", {}).get("independently_evaluated", 0))
    return {
        "label_counts_by_quality": counts,
        "independently_evaluated_validation_records": independently_evaluated_rows,
        "independent_validation_available": independently_evaluated_rows > 0,
        "note": "Calibration provenance is not independent validation; synthetic and weak-supervision evidence is provisional.",
    }


def calibrate_validation(
    rows: Sequence[Mapping[str, Any]],
    probabilities: np.ndarray,
    labels: Sequence[str],
    *,
    taxonomy_version: str,
    checkpoint_id: str,
    checkpoint_sha256: str,
    validation_sha256: str,
    minimum_positives: int = MIN_OBSERVED_POSITIVES,
    minimum_negatives: int = MIN_OBSERVED_NEGATIVES,
    threshold_candidates: Sequence[float] = DEFAULT_THRESHOLDS,
) -> dict[str, Any]:
    """Calibrate thresholds using validation rows with explicit observation masks."""
    ordered_labels = list(labels)
    allowed = set(ordered_labels)
    scores = np.asarray(probabilities, dtype=np.float64)
    if not ordered_labels or len(allowed) != len(ordered_labels):
        raise CalibrationArtifactError("labels must be unique and non-empty")
    if scores.shape != (len(rows), len(ordered_labels)):
        raise CalibrationArtifactError("validation probability shape does not match rows and taxonomy")
    if not np.isfinite(scores).all() or (scores < 0).any() or (scores > 1).any():
        raise CalibrationArtifactError("validation probabilities must be finite values in [0, 1]")
    if minimum_positives < MIN_OBSERVED_POSITIVES or minimum_negatives < MIN_OBSERVED_NEGATIVES:
        raise CalibrationArtifactError("calibration evidence minimums cannot be lower than five observed positives and negatives")
    candidates = sorted({float(value) for value in threshold_candidates})
    if not candidates or any(not math.isfinite(value) or not 0 < value < 1 for value in candidates):
        raise CalibrationArtifactError("threshold candidates must be finite values strictly between 0 and 1")

    row_labels: list[set[str]] = []
    row_observed: list[set[str]] = []
    for row in rows:
        positives = _row_labels(row)
        if positives - allowed:
            raise CalibrationArtifactError(f"unknown positive emotion keys: {sorted(positives - allowed)}")
        observed = _row_observed(row, ordered_labels)
        if positives - observed:
            raise CalibrationArtifactError("positive labels must also be explicitly observed")
        row_labels.append(positives)
        row_observed.append(observed)

    thresholds: dict[str, float | None] = {}
    label_status: dict[str, dict[str, Any]] = {}
    for index, label in enumerate(ordered_labels):
        positive_count = 0
        negative_count = 0
        by_source: dict[str, dict[str, int]] = {}
        for row, positives, observed in zip(rows, row_labels, row_observed):
            source = _source_descriptor(row)
            counts = by_source.setdefault(source, {"observed_positive": 0, "observed_negative": 0, "unknown": 0})
            if label not in observed:
                counts["unknown"] += 1
            elif label in positives:
                counts["observed_positive"] += 1
                positive_count += 1
            else:
                counts["observed_negative"] += 1
                negative_count += 1

        unknown_count = len(rows) - positive_count - negative_count
        missing = []
        if positive_count < minimum_positives:
            missing.append(f"observed positives {positive_count} < required {minimum_positives}")
        if negative_count < minimum_negatives:
            missing.append(f"observed negatives {negative_count} < required {minimum_negatives}")
        common = {
            "observed_positive_count": positive_count,
            "observed_negative_count": negative_count,
            "unknown_count": unknown_count,
            "provenance_by_source": by_source,
            "evidence_quality": _evidence_quality(by_source, calibrated=not missing),
        }
        if missing:
            thresholds[label] = None
            label_status[label] = {
                "status": "uncalibrated",
                "threshold": None,
                "reason": "; ".join(missing),
                **common,
            }
            continue

        observed_indices = np.asarray([label in values for values in row_observed], dtype=bool)
        truth = np.asarray([int(label in values) for values in row_labels], dtype=np.int8)[observed_indices]
        label_scores = scores[observed_indices, index]
        best_threshold: float | None = None
        best_f1 = -1.0
        best_precision = best_recall = 0.0
        best_tp = best_fp = best_fn = 0
        # Conservative tie-break: choose the highest threshold among equal-F1
        # candidates. This is deterministic and avoids low-threshold ties
        # silently favoring broad positive output.
        for threshold in candidates:
            predicted = label_scores >= threshold
            tp = int(np.logical_and(truth == 1, predicted).sum())
            fp = int(np.logical_and(truth == 0, predicted).sum())
            fn = int(np.logical_and(truth == 1, ~predicted).sum())
            precision = tp / (tp + fp) if tp + fp else 0.0
            recall = tp / (tp + fn) if tp + fn else 0.0
            f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
            if f1 > best_f1 + 1e-12 or (abs(f1 - best_f1) <= 1e-12 and (best_threshold is None or threshold > best_threshold)):
                best_threshold, best_f1 = threshold, f1
                best_precision, best_recall = precision, recall
                best_tp, best_fp, best_fn = tp, fp, fn
        if best_threshold is None:
            raise CalibrationArtifactError(f"no threshold selected for eligible label {label}")
        thresholds[label] = best_threshold
        label_status[label] = {
            "status": "calibrated",
            "threshold": best_threshold,
            "reason": None,
            **common,
            "validation_precision": best_precision,
            "validation_recall": best_recall,
            "validation_f1": best_f1,
            "validation_tp": best_tp,
            "validation_fp": best_fp,
            "validation_fn": best_fn,
        }

    artifact = {
        "schema": CALIBRATION_SCHEMA,
        "research_only": True,
        "taxonomy_version": taxonomy_version,
        "labels": ordered_labels,
        "calibration_split": "validation",
        "minimum_observed_positives": minimum_positives,
        "minimum_observed_negatives": minimum_negatives,
        "threshold_selection": {"metric": "per_label_f1", "candidate_thresholds": candidates, "tie_break": "highest_threshold"},
        "checkpoint": {"id": checkpoint_id, "sha256": checkpoint_sha256},
        "validation_provenance": {
            "sha256": validation_sha256,
            "records": len(rows),
            "source_datasets": sorted({_source(row) for row in rows}),
            "evidence_kind_counts": {
                kind: sum(_evidence_kind(row) == kind for row in rows)
                for kind in sorted({_evidence_kind(row) for row in rows})
            },
            "unknown_masks_are_excluded": True,
        },
        "thresholds": thresholds,
        "label_status": label_status,
    }
    artifact["evidence_quality"] = calibration_evidence_summary(artifact)
    validate_calibration_artifact(artifact, ordered_labels, taxonomy_version=taxonomy_version, expected_checkpoint_sha256=checkpoint_sha256)
    return artifact


def validate_calibration_artifact(
    artifact: Mapping[str, Any],
    expected_labels: Sequence[str],
    *,
    taxonomy_version: str,
    expected_checkpoint_sha256: str | None = None,
) -> None:
    """Validate the full calibration contract. Never supplies threshold defaults."""
    labels = list(expected_labels)
    if artifact.get("schema") != CALIBRATION_SCHEMA or artifact.get("research_only") is not True:
        raise CalibrationArtifactError("missing or unsupported research calibration schema")
    if artifact.get("taxonomy_version") != taxonomy_version or artifact.get("labels") != labels:
        raise CalibrationArtifactError("calibration taxonomy version/order does not match the model")
    if artifact.get("calibration_split") != "validation":
        raise CalibrationArtifactError("threshold metadata must identify validation as its source split")
    minimum_positives = artifact.get("minimum_observed_positives")
    minimum_negatives = artifact.get("minimum_observed_negatives")
    if not isinstance(minimum_positives, int) or minimum_positives < MIN_OBSERVED_POSITIVES or not isinstance(minimum_negatives, int) or minimum_negatives < MIN_OBSERVED_NEGATIVES:
        raise CalibrationArtifactError("calibration evidence minimums must be at least 5 positive and 5 negative")
    thresholds = artifact.get("thresholds")
    statuses = artifact.get("label_status")
    if not isinstance(thresholds, Mapping) or set(thresholds) != set(labels):
        raise CalibrationArtifactError("threshold metadata must contain every and only taxonomy label")
    if not isinstance(statuses, Mapping) or set(statuses) != set(labels):
        raise CalibrationArtifactError("label status metadata must contain every and only taxonomy label")
    checkpoint = artifact.get("checkpoint")
    if not isinstance(checkpoint, Mapping) or not checkpoint.get("id"):
        raise CalibrationArtifactError("calibration checkpoint provenance is missing")
    checkpoint_hash = checkpoint.get("sha256")
    if not isinstance(checkpoint_hash, str) or len(checkpoint_hash) != 64 or any(char not in string.hexdigits for char in checkpoint_hash):
        raise CalibrationArtifactError("calibration checkpoint hash is missing or malformed")
    if expected_checkpoint_sha256 is not None and checkpoint_hash != expected_checkpoint_sha256:
        raise CalibrationArtifactError("calibration artifact belongs to a different checkpoint")
    validation = artifact.get("validation_provenance")
    if not isinstance(validation, Mapping) or validation.get("unknown_masks_are_excluded") is not True:
        raise CalibrationArtifactError("validation provenance or unknown-mask guarantee is missing")
    if not isinstance(validation.get("sha256"), str) or len(validation["sha256"]) != 64 or any(char not in string.hexdigits for char in validation["sha256"]) or not isinstance(validation.get("records"), int):
        raise CalibrationArtifactError("validation data provenance is incomplete")
    if not isinstance(validation.get("source_datasets"), list) or not validation["source_datasets"]:
        raise CalibrationArtifactError("validation source dataset provenance is missing")
    evidence_counts = validation.get("evidence_kind_counts")
    if not isinstance(evidence_counts, Mapping) or sum(evidence_counts.values()) != validation["records"]:
        raise CalibrationArtifactError("validation evidence-kind provenance is missing or inconsistent")
    if any(kind not in {"source_annotated", "ai_assisted_synthetic", "weak_supervision", "independently_evaluated"} or not isinstance(count, int) or count < 0 for kind, count in evidence_counts.items()):
        raise CalibrationArtifactError("validation evidence-kind provenance contains invalid entries")
    selection = artifact.get("threshold_selection")
    if not isinstance(selection, Mapping) or selection.get("metric") != "per_label_f1" or selection.get("tie_break") != "highest_threshold":
        raise CalibrationArtifactError("threshold selection metadata is missing or unsupported")
    candidates = selection.get("candidate_thresholds")
    if not isinstance(candidates, list) or not candidates or any(not isinstance(value, (int, float)) or not math.isfinite(float(value)) or not 0 < float(value) < 1 for value in candidates):
        raise CalibrationArtifactError("threshold candidate metadata is malformed")

    for label in labels:
        status = statuses[label]
        if not isinstance(status, Mapping):
            raise CalibrationArtifactError(f"invalid status for {label}")
        pos = status.get("observed_positive_count")
        neg = status.get("observed_negative_count")
        unknown = status.get("unknown_count")
        sources = status.get("provenance_by_source")
        if not all(isinstance(value, int) and value >= 0 for value in (pos, neg, unknown)):
            raise CalibrationArtifactError(f"invalid support counts for {label}")
        if not isinstance(sources, Mapping):
            raise CalibrationArtifactError(f"calibration source provenance missing for {label}")
        for source_value in sources.values():
            if not isinstance(source_value, Mapping) or any(not isinstance(source_value.get(key), int) or source_value[key] < 0 for key in ("observed_positive", "observed_negative", "unknown")):
                raise CalibrationArtifactError(f"invalid source evidence counts for {label}")
        source_pos = sum(int(value["observed_positive"]) for value in sources.values())
        source_neg = sum(int(value["observed_negative"]) for value in sources.values())
        source_unknown = sum(int(value["unknown"]) for value in sources.values())
        if (source_pos, source_neg, source_unknown) != (pos, neg, unknown):
            raise CalibrationArtifactError(f"source-level evidence does not reconcile for {label}")
        quality = status.get("evidence_quality")
        if quality is not None:
            if not isinstance(quality, Mapping) or not isinstance(quality.get("quality_class"), str):
                raise CalibrationArtifactError(f"invalid evidence-quality metadata for {label}")
            quality_kinds = quality.get("by_evidence_kind")
            if not isinstance(quality_kinds, Mapping):
                raise CalibrationArtifactError(f"evidence-quality kind counts missing for {label}")
            for kind in ("source_annotated", "ai_assisted_synthetic", "weak_supervision", "independently_evaluated"):
                expected = {"observed_positive": 0, "observed_negative": 0, "unknown": 0}
                for descriptor, source_value in sources.items():
                    if descriptor.rsplit("|", 1)[-1] == kind:
                        for key in expected:
                            expected[key] += source_value[key]
                values = quality_kinds.get(kind)
                if not isinstance(values, Mapping) or dict(values) != expected:
                    raise CalibrationArtifactError(f"evidence-quality counts do not reconcile for {label}/{kind}")
        threshold = thresholds[label]
        if status.get("status") == "calibrated":
            if pos < minimum_positives or neg < minimum_negatives:
                raise CalibrationArtifactError(f"{label} is marked calibrated without minimum observed evidence")
            if not isinstance(threshold, (int, float)) or not math.isfinite(float(threshold)) or not 0 < float(threshold) < 1:
                raise CalibrationArtifactError(f"{label} has a missing or invalid calibrated threshold")
            if float(threshold) not in {float(value) for value in candidates}:
                raise CalibrationArtifactError(f"{label} threshold was not selected from the declared calibration grid")
            if status.get("threshold") != threshold or status.get("reason") is not None:
                raise CalibrationArtifactError(f"{label} threshold/status metadata is inconsistent")
        elif status.get("status") == "uncalibrated":
            if threshold is not None or status.get("threshold") is not None:
                raise CalibrationArtifactError(f"uncalibrated label {label} must not have a threshold")
            if pos >= minimum_positives and neg >= minimum_negatives:
                raise CalibrationArtifactError(f"{label} is marked uncalibrated despite sufficient evidence")
            if not isinstance(status.get("reason"), str) or not status["reason"].strip():
                raise CalibrationArtifactError(f"uncalibrated label {label} needs a reason")
        else:
            raise CalibrationArtifactError(f"invalid calibration status for {label}")


def load_calibration_artifact(
    path: Path,
    expected_labels: Sequence[str],
    *,
    taxonomy_version: str,
    checkpoint_path: Path | None = None,
) -> dict[str, Any]:
    artifact_path = Path(path)
    if not artifact_path.is_file():
        raise CalibrationArtifactError(f"calibration artifact not found: {artifact_path}")
    artifact = json.loads(artifact_path.read_text(encoding="utf-8"))
    expected_hash = None
    if checkpoint_path is not None:
        model_weights = Path(checkpoint_path) / "model.safetensors"
        if not model_weights.is_file():
            raise CalibrationArtifactError(f"checkpoint weights not found: {model_weights}")
        expected_hash = sha256_file(model_weights)
    validate_calibration_artifact(artifact, expected_labels, taxonomy_version=taxonomy_version, expected_checkpoint_sha256=expected_hash)
    return dict(artifact)


def accepted_predictions(probabilities: Sequence[float], labels: Sequence[str], artifact: Mapping[str, Any]) -> list[dict[str, Any]]:
    """Return scores with uncalibrated labels explicitly abstaining."""
    values = list(probabilities)
    if len(values) != len(labels):
        raise CalibrationArtifactError("prediction width does not match taxonomy")
    thresholds = artifact["thresholds"]
    statuses = artifact["label_status"]
    result = []
    for label, raw_score in zip(labels, values):
        score = float(raw_score)
        if not math.isfinite(score) or not 0 <= score <= 1:
            raise CalibrationArtifactError(f"invalid probability for {label}")
        state = statuses[label]
        threshold = thresholds[label]
        calibrated = state["status"] == "calibrated"
        quality = state.get("evidence_quality") or _evidence_quality(state.get("provenance_by_source", {}), calibrated=calibrated)
        result.append({
            "emotion": label,
            "probability": score,
            "threshold": float(threshold) if calibrated else None,
            "calibration_status": state["status"],
            "uncalibrated_reason": state.get("reason") if not calibrated else None,
            "evidence_quality": quality["quality_class"],
            "accepted": bool(calibrated and score >= float(threshold)),
        })
    return result


def cardinality_warning(accepted_count: int, warning_limit: int = 10) -> dict[str, Any]:
    """Flag high cardinality without hiding or dropping any prediction."""
    return {
        "warning": accepted_count >= warning_limit,
        "accepted_count": int(accepted_count),
        "warning_limit": int(warning_limit),
        "status": "excessive_prediction_cardinality" if accepted_count >= warning_limit else "within_cardinality_warning_limit",
    }
