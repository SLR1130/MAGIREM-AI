"""Regression tests for Emotion35 weak-label observation masks."""

from __future__ import annotations

import importlib
import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
LABELS = [
    item["key"]
    for item in json.loads((ROOT / "config/emotion_taxonomy.json").read_text(encoding="utf-8"))["emotions"]
]


class WeakMaskRegressionTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.prototype = importlib.import_module("prepare_emotion35_prototype_dataset")
        cls.prototype.TAXONOMY_KEYS = LABELS
        cls.augmentation = importlib.import_module("prepare_emotion35_v3_augmentation")
        cls.v4 = importlib.import_module("prepare_emotion35_v4_weak_supervision")

    def test_positive_only_prototype_row_does_not_observe_all_taxonomy(self):
        row = self.prototype._synthetic_row("a calm example", ["calmness"], "ENGLISH", "train", "r1")
        self.assertEqual(row["observed_labels"], ["calmness"])
        self.assertEqual(row["weak_negative_labels"], [])
        self.assertEqual(row["weak_label_policy"], "positive_only_unknown_negatives")
        self.assertNotEqual(set(row["observed_labels"]), set(LABELS))

    def test_positive_only_v3_row_does_not_observe_all_taxonomy(self):
        row = self.augmentation.make_record("a proud example", "TAGLISH", ["pride"], 1)
        self.assertEqual(row["observed_labels"], ["pride"])
        self.assertEqual(row["weak_negative_labels"], [])
        self.assertNotEqual(set(row["observed_labels"]), set(LABELS))

    def test_legacy_all_taxonomy_mask_is_repaired_to_positive_only(self):
        row = {
            "source_id": "legacy-1",
            "source_dataset": "synthetic_ai_assisted_emotion35_prototype",
            "labels": ["shame"],
            "observed_labels": list(LABELS),
            "synthetic": True,
            "weak_supervision": True,
        }
        repaired, changes = self.v4.repair_inherited_weak_masks([row])
        self.assertEqual(repaired[0]["observed_labels"], ["shame"])
        self.assertEqual(repaired[0]["weak_negative_labels"], [])
        self.assertEqual(len(changes), 1)

    def test_explicit_contrast_negative_is_preserved(self):
        row = {
            "source_id": "contrast-1",
            "labels": ["stress"],
            "observed_labels": ["stress", "calmness"],
            "weak_negative_labels": ["calmness"],
            "weak_label_policy": "contrastive_weak_negative",
            "synthetic": True,
            "weak_supervision": True,
        }
        repaired, changes = self.v4.repair_inherited_weak_masks([row])
        self.assertEqual(repaired[0]["observed_labels"], ["stress", "calmness"])
        self.assertEqual(repaired[0]["weak_negative_labels"], ["calmness"])
        self.assertEqual(changes, [])

    def test_invalid_taxonomy_label_fails_closed(self):
        row = {"source_id": "bad", "labels": ["panic"], "observed_labels": ["panic"], "synthetic": True, "weak_supervision": True}
        with self.assertRaises(ValueError):
            self.v4.repair_inherited_weak_masks([row])

    def test_mask_validation_rejects_positive_outside_mask(self):
        with self.assertRaises(ValueError):
            self.v4.validate_weak_records({"train": [{"source_id": "bad-mask", "labels": ["guilt"], "observed_labels": [], "text": "x"}]})

    def test_mask_validation_rejects_unjustified_negative(self):
        row = {
            "source_id": "bad-negative",
            "labels": ["guilt"],
            "observed_labels": ["guilt", "shame"],
            "weak_negative_labels": [],
            "weak_label_policy": "positive_only_unknown_negatives",
            "synthetic": True,
            "weak_supervision": True,
            "text": "x",
        }
        with self.assertRaises(ValueError):
            self.v4.validate_weak_records({"train": [row]})

    def test_mask_validation_rejects_cross_split_duplicate_text(self):
        base = {"source_id": "a", "labels": ["guilt"], "observed_labels": ["guilt"], "text": "same text"}
        other = dict(base, source_id="b")
        with self.assertRaises(ValueError):
            self.v4.validate_weak_records({"train": [base], "validation": [other]})


if __name__ == "__main__":
    unittest.main()
