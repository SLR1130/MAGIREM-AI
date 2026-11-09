from __future__ import annotations

import unittest
import json
import uuid
from pathlib import Path

import numpy as np

from emotion35_calibration import (
    CalibrationArtifactError,
    accepted_predictions,
    calibration_evidence_summary,
    calibrate_validation,
    cardinality_warning,
    validate_calibration_artifact,
)


def _rows(label_count: int, positives: dict[int, set[int]], observed: dict[int, set[int]] | None = None):
    labels = [f"emotion_{index}" for index in range(label_count)]
    result = []
    for row_index in range(max(positives, default=-1) + 1):
        observed_row = observed[row_index] if observed is not None else set(labels)
        positive_row = {labels[index] for index in positives.get(row_index, set())}
        result.append({
            "labels": sorted(positive_row),
            "observed_labels": sorted(observed_row),
            "source_dataset": "synthetic_fixture",
        })
    return labels, result


def _calibrate(labels, rows, probs):
    return calibrate_validation(
        rows,
        np.asarray(probs, dtype=np.float32),
        labels,
        taxonomy_version="test-taxonomy",
        checkpoint_id="fixture-checkpoint",
        checkpoint_sha256="a" * 64,
        validation_sha256="b" * 64,
        threshold_candidates=(0.25, 0.5, 0.75),
    )


class Emotion35CalibrationTests(unittest.TestCase):
    def test_zero_observed_negatives_is_uncalibrated(self):
        labels = ["target"]
        rows = [{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"} for _ in range(7)]
        artifact = _calibrate(labels, rows, [[0.9]] * len(rows))
        status = artifact["label_status"]["target"]
        self.assertEqual(status["status"], "uncalibrated")
        self.assertIsNone(artifact["thresholds"]["target"])
        self.assertIn("observed negatives 0", status["reason"])
        self.assertEqual(status["provenance_by_source"]["source-a|source_annotated"]["observed_positive"], 7)

    def test_zero_observed_positives_is_uncalibrated(self):
        labels = ["target"]
        rows = [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"} for _ in range(7)]
        artifact = _calibrate(labels, rows, [[0.1]] * len(rows))
        self.assertEqual(artifact["label_status"]["target"]["status"], "uncalibrated")
        self.assertIsNone(artifact["thresholds"]["target"])
        self.assertIn("observed positives 0", artifact["label_status"]["target"]["reason"])

    def test_insufficient_positive_support_is_uncalibrated(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 4
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"}] * 8)
        artifact = _calibrate(labels, rows, [[0.8]] * 4 + [[0.2]] * 8)
        status = artifact["label_status"]["target"]
        self.assertEqual(status["status"], "uncalibrated")
        self.assertIsNone(artifact["thresholds"]["target"])
        self.assertIn("4 < required 5", status["reason"])

    def test_unknown_labels_are_not_counted_as_negatives(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 2
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"}] * 2
                + [{"labels": [], "observed_labels": [], "source_dataset": "source-b"}] * 20
                + [{"labels": []}])
        artifact = _calibrate(labels, rows, [[0.8]] * 2 + [[0.2]] * 2 + [[0.99]] * 21)
        status = artifact["label_status"]["target"]
        self.assertEqual(status["observed_positive_count"], 2)
        self.assertEqual(status["observed_negative_count"], 2)
        self.assertEqual(status["unknown_count"], 21)
        self.assertEqual(status["status"], "uncalibrated")
        self.assertEqual(status["provenance_by_source"]["source-b|source_annotated"]["observed_negative"], 0)

    def test_sufficient_evidence_creates_consistent_threshold(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 6
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-b"}] * 8)
        artifact = _calibrate(labels, rows, [[0.8]] * 6 + [[0.2]] * 8)
        status = artifact["label_status"]["target"]
        self.assertEqual(status["status"], "calibrated")
        self.assertGreaterEqual(status["observed_positive_count"], 5)
        self.assertGreaterEqual(status["observed_negative_count"], 5)
        self.assertEqual(status["threshold"], artifact["thresholds"]["target"])
        validate_calibration_artifact(artifact, labels, taxonomy_version="test-taxonomy", expected_checkpoint_sha256="a" * 64)

    def test_inference_rejects_missing_or_inconsistent_threshold_metadata(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5)
        artifact = _calibrate(labels, rows, [[0.8]] * 5 + [[0.2]] * 5)
        missing = dict(artifact)
        missing["thresholds"] = {}
        with self.assertRaises(CalibrationArtifactError):
            validate_calibration_artifact(missing, labels, taxonomy_version="test-taxonomy")
        inconsistent = dict(artifact)
        inconsistent["thresholds"] = {"target": 0.1}
        with self.assertRaises(CalibrationArtifactError):
            validate_calibration_artifact(inconsistent, labels, taxonomy_version="test-taxonomy")

    def test_zero_f1_ties_choose_the_highest_candidate_safely(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5)
        artifact = _calibrate(labels, rows, [[0.1]] * 5 + [[0.9]] * 5)
        status = artifact["label_status"]["target"]
        self.assertEqual(status["status"], "calibrated")
        self.assertEqual(status["validation_f1"], 0.0)
        self.assertEqual(artifact["thresholds"]["target"], 0.75)
        self.assertEqual(status["threshold"], artifact["thresholds"]["target"])

    def test_threshold_metadata_json_round_trip_and_label_order_integrity(self):
        labels = ["first", "second"]
        rows = []
        scores = []
        for _ in range(6):
            rows.append({"labels": ["first"], "observed_labels": labels, "source_dataset": "source-a"})
            scores.append([0.9, 0.1])
        for _ in range(6):
            rows.append({"labels": ["second"], "observed_labels": labels, "source_dataset": "source-a"})
            scores.append([0.1, 0.9])
        artifact = _calibrate(labels, rows, scores)
        path = Path(__file__).with_name(f".calibration-roundtrip-{uuid.uuid4().hex}.json")
        try:
            path.write_text(json.dumps(artifact), encoding="utf-8")
            loaded = json.loads(path.read_text(encoding="utf-8"))
        finally:
            path.unlink(missing_ok=True)
        validate_calibration_artifact(loaded, labels, taxonomy_version="test-taxonomy")
        with self.assertRaises(CalibrationArtifactError):
            validate_calibration_artifact(loaded, list(reversed(labels)), taxonomy_version="test-taxonomy")

    def test_invalid_or_incomplete_checkpoint_metadata_is_rejected(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "source-a"}] * 5)
        artifact = _calibrate(labels, rows, [[0.8]] * 5 + [[0.2]] * 5)
        missing_provenance = json.loads(json.dumps(artifact))
        del missing_provenance["checkpoint"]
        with self.assertRaises(CalibrationArtifactError):
            validate_calibration_artifact(missing_provenance, labels, taxonomy_version="test-taxonomy")
        malformed_hash = json.loads(json.dumps(artifact))
        malformed_hash["checkpoint"]["sha256"] = "not-a-hash"
        with self.assertRaises(CalibrationArtifactError):
            validate_calibration_artifact(malformed_hash, labels, taxonomy_version="test-taxonomy")

    def test_uncalibrated_never_accepts_and_cardinality_warning_does_not_hide(self):
        labels = [f"emotion_{index}" for index in range(11)]
        rows = []
        probabilities = []
        for _ in range(5):
            rows.append({"labels": labels.copy(), "observed_labels": labels.copy(), "source_dataset": "fixture"})
            probabilities.append([0.95] * len(labels))
        for _ in range(5):
            rows.append({"labels": [], "observed_labels": labels.copy(), "source_dataset": "fixture"})
            probabilities.append([0.05] * len(labels))
        artifact = _calibrate(labels, rows, probabilities)
        scored = accepted_predictions([0.99] * len(labels), labels, artifact)
        self.assertEqual(sum(item["accepted"] for item in scored), len(labels))
        warning = cardinality_warning(sum(item["accepted"] for item in scored))
        self.assertTrue(warning["warning"])
        self.assertEqual(warning["accepted_count"], len(labels))
        artifact["label_status"][labels[0]]["status"] = "uncalibrated"
        artifact["label_status"][labels[0]]["reason"] = "fixture missing evidence"
        artifact["label_status"][labels[0]]["threshold"] = None
        artifact["thresholds"][labels[0]] = None
        # The helper still lists the score but cannot accept an uncalibrated label.
        uncalibrated_score = accepted_predictions([0.999] * len(labels), labels, artifact)[0]
        self.assertFalse(uncalibrated_score["accepted"])
        self.assertIsNone(uncalibrated_score["threshold"])

    def test_weak_only_calibration_is_labeled_provisional_not_independent(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "ai_candidate", "weak_supervision": True}] * 6
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "ai_candidate", "weak_supervision": True}] * 6)
        artifact = _calibrate(labels, rows, [[0.8]] * 6 + [[0.2]] * 6)
        quality = artifact["label_status"]["target"]["evidence_quality"]
        self.assertEqual(quality["quality_class"], "weak_supervision_only")
        self.assertFalse(quality["independent_evidence_present"])
        self.assertEqual(calibration_evidence_summary(artifact)["independently_evaluated_validation_records"], 0)
        accepted = accepted_predictions([0.99], labels, artifact)[0]
        self.assertEqual(accepted["evidence_quality"], "weak_supervision_only")

    def test_evidence_quality_counts_preserve_public_and_weak_sources(self):
        labels = ["target"]
        rows = ([{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "public_source"}] * 5
                + [{"labels": [], "observed_labels": ["target"], "source_dataset": "public_source"}] * 5
                + [{"labels": ["target"], "observed_labels": ["target"], "source_dataset": "synthetic", "weak_supervision": True}] * 2)
        artifact = _calibrate(labels, rows, [[0.8]] * 7 + [[0.2]] * 5)
        quality = artifact["label_status"]["target"]["evidence_quality"]
        self.assertEqual(quality["quality_class"], "mixed_source_evidence")
        self.assertEqual(quality["by_evidence_kind"]["source_annotated"]["observed_positive"], 5)
        self.assertEqual(quality["by_evidence_kind"]["weak_supervision"]["observed_positive"], 2)
        self.assertFalse(quality["independent_evidence_present"])


if __name__ == "__main__":
    unittest.main()
