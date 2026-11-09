"""Safety tests for the isolated 35-label pipeline."""

from __future__ import annotations

import json
import shutil
import unittest
from pathlib import Path

try:
    from .emotion_35_multilabel_classifier import Emotion35MultilabelClassifier
    from .emotion_35_multilabel_pipeline import _labels, audit_dataset, load_taxonomy, masked_bce_with_logits
except ImportError:
    from emotion_35_multilabel_classifier import Emotion35MultilabelClassifier
    from emotion_35_multilabel_pipeline import _labels, audit_dataset, load_taxonomy, masked_bce_with_logits


class Emotion35PipelineTests(unittest.TestCase):
    def test_taxonomy_has_35_labels_and_8_categories(self):
        taxonomy = load_taxonomy()
        self.assertEqual(len(taxonomy["emotions"]), 35)
        self.assertEqual(len(taxonomy["categories"]), 8)

    def test_scalar_and_multilabel_records_are_normalized(self):
        self.assertEqual(_labels({"emotion": "Shame"}), ["shame"])
        self.assertEqual(_labels({"labels": ["shame", "guilt", "shame"]}), ["shame", "guilt"])

    def test_current_six_class_corpus_is_refused(self):
        root = Path(__file__).resolve().parent / "emotion_data_multilingual_final_v2"
        audit = audit_dataset(root, provenance=None)
        self.assertFalse(audit.sufficient_for_training)
        self.assertEqual(len(audit.missing_labels), 30)
        self.assertIn("dataset provenance/licensing metadata is incomplete", audit.blockers)
        self.assertTrue(set(audit.invalid_labels).issubset({"sadness", "joy", "anxiety", "anger", "neutral", "grief"}))

    def test_split_leakage_is_blocked(self):
        root = Path(__file__).resolve().parents[1] / "storage" / "app" / "audit-recovery" / "pipeline-test-split-leakage"
        if root.exists():
            shutil.rmtree(root, ignore_errors=True)
        root.mkdir(parents=True, exist_ok=True)
        try:
            record = {"text": "same text", "emotion": "happiness_joy"}
            for split in ("train", "validation", "test"):
                (root / f"{split}.jsonl").write_text(json.dumps(record) + "\n", encoding="utf-8")
            audit = audit_dataset(root, min_train_per_label=0, min_validation_per_label=0, min_test_per_label=0, provenance={"source": "x", "license": "x", "usage": "x", "curator": "x", "version": "x"})
            self.assertFalse(audit.sufficient_for_training)
            self.assertTrue(audit.duplicate_texts_across_splits)
        finally:
            shutil.rmtree(root, ignore_errors=True)

    def test_partial_annotation_mask_allows_research_audit(self):
        root = Path(__file__).resolve().parents[1] / "storage" / "app" / "audit-recovery" / "pipeline-test-partial-mask"
        if root.exists():
            shutil.rmtree(root, ignore_errors=True)
        root.mkdir(parents=True, exist_ok=True)
        try:
            record = {"text": "partial observed example", "labels": ["happiness_joy"], "observed_labels": ["happiness_joy"]}
            for split in ("train", "validation", "test"):
                (root / f"{split}.jsonl").write_text(json.dumps({**record, "text": f"partial observed example {split}"}) + "\n", encoding="utf-8")
            audit = audit_dataset(root, min_train_per_label=0, min_validation_per_label=0, min_test_per_label=0, provenance={"source": "x", "license": "x", "usage": "x", "curator": "x", "version": "x"}, allow_partial_labels=True)
            self.assertTrue(audit.sufficient_for_training)
            self.assertTrue(audit.partial_labels_allowed)
            self.assertEqual(len(audit.missing_labels), 34)
        finally:
            shutil.rmtree(root, ignore_errors=True)

    def test_masked_bce_ignores_unobserved_target(self):
        import torch
        logits = torch.tensor([[0.0, 0.0]])
        mask = torch.tensor([[1.0, 0.0]])
        first = masked_bce_with_logits(logits, torch.tensor([[1.0, 0.0]]), mask)
        second = masked_bce_with_logits(logits, torch.tensor([[1.0, 1.0]]), mask)
        self.assertAlmostEqual(float(first), float(second), places=6)

    def test_disabled_by_default_and_never_falls_back(self):
        classifier = Emotion35MultilabelClassifier(enabled=False)
        result = classifier.predict("I feel hopeful")
        self.assertEqual(result["status"], "disabled")
        self.assertEqual(result["source"], "dedicated_35_ml")


if __name__ == "__main__":
    unittest.main()











