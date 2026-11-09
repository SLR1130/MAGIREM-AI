"""Optional inference wrapper for a separately trained 35-label model.

The wrapper is disabled unless ``MINDCARE_35_ML_ENABLED=true`` and a validated
artifact exists.  It never replaces ``TrainedEmotionClassifier`` and it does
not make attribution or crisis decisions: those remain separate safeguards.
"""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any

import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

try:
    from .emotion_taxonomy import classify_label, load_taxonomy, taxonomy_version
    from .emotion35_calibration import accepted_predictions, cardinality_warning, load_calibration_artifact
except ImportError:
    from emotion_taxonomy import classify_label, load_taxonomy, taxonomy_version
    from emotion35_calibration import accepted_predictions, cardinality_warning, load_calibration_artifact


DEFAULT_MODEL_DIR = Path(__file__).resolve().parent / "emotion_model_35_multilabel"


def _enabled() -> bool:
    return os.getenv("MINDCARE_35_ML_ENABLED", "false").strip().lower() in {"1", "true", "yes", "on"}


class Emotion35MultilabelClassifier:
    """Load only a validated, separate multilabel artifact."""

    def __init__(self, model_dir: Path | None = None, *, enabled: bool | None = None):
        self.model_dir = Path(model_dir or os.getenv("MINDCARE_35_ML_MODEL_DIR", DEFAULT_MODEL_DIR))
        self.enabled = _enabled() if enabled is None else bool(enabled)
        self.available = False
        self.model = None
        self.tokenizer = None
        self.labels: list[str] = []
        self.thresholds: dict[str, float | None] = {}
        self.calibration: dict[str, Any] | None = None
        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        self.load_error: str | None = None
        if self.enabled:
            self._load()

    def _load(self) -> None:
        try:
            mapping_path = self.model_dir / "label_mapping.json"
            if not mapping_path.exists():
                raise FileNotFoundError("35-label artifact has no label_mapping.json")
            mapping = json.loads(mapping_path.read_text(encoding="utf-8"))
            taxonomy = load_taxonomy()
            expected = [item["key"] for item in taxonomy["emotions"]]
            self.labels = list(mapping.get("labels", []))
            if self.labels != expected or mapping.get("taxonomy_version") != taxonomy_version():
                raise ValueError("artifact taxonomy/version does not match the approved taxonomy")
            threshold_path = Path(os.getenv("MINDCARE_35_ML_CALIBRATION_PATH", self.model_dir / "thresholds.json"))
            self.calibration = load_calibration_artifact(
                threshold_path, self.labels, taxonomy_version=taxonomy_version(), checkpoint_path=self.model_dir,
            )
            self.thresholds = dict(self.calibration["thresholds"])
            self.tokenizer = AutoTokenizer.from_pretrained(str(self.model_dir), local_files_only=True)
            self.model = AutoModelForSequenceClassification.from_pretrained(str(self.model_dir), local_files_only=True)
            if int(getattr(self.model.config, "num_labels", 0)) != len(self.labels):
                raise ValueError("35-label artifact has an unexpected number of output labels")
            configured_labels = self.model.config.id2label or {}
            if configured_labels:
                configured_order = [configured_labels.get(index, configured_labels.get(str(index))) for index in range(len(self.labels))]
                if configured_order != self.labels:
                    raise ValueError("35-label model output mapping does not match the approved taxonomy order")
            self.model.to(self.device)
            self.model.eval()
            self.available = True
        except Exception as exc:  # fail closed: no fallback to the six-class model here
            self.load_error = str(exc)
            self.available = False

    def predict(self, text: str, *, include_scores: bool = False) -> dict[str, Any]:
        if not self.enabled:
            return {"status": "disabled", "source": "dedicated_35_ml", "shadow_only": True}
        if not self.available or self.model is None or self.tokenizer is None:
            return {"status": "unavailable", "source": "dedicated_35_ml", "shadow_only": True}
        if not isinstance(text, str) or not text.strip():
            return {"status": "uncertain", "source": "dedicated_35_ml", "shadow_only": True}
        started = time.perf_counter()
        try:
            inputs = self.tokenizer(text, truncation=True, max_length=256, return_tensors="pt")
            inputs = {key: value.to(self.device) for key, value in inputs.items()}
            with torch.no_grad():
                logits = self.model(**inputs).logits[0]
            probabilities = torch.sigmoid(logits).detach().cpu().tolist()
            scores = dict(zip(self.labels, probabilities))
            if self.calibration is None:
                raise RuntimeError("Emotion35 calibration metadata was not loaded")
            scored = accepted_predictions(probabilities, self.labels, self.calibration)
            selected = [item["emotion"] for item in scored if item["accepted"]]
            selected.sort(key=lambda label: scores[label], reverse=True)
            primary = selected[0] if selected else None
            secondary = selected[1:] if len(selected) > 1 else []
            if not primary:
                status = "uncertain"
            elif secondary:
                status = "mixed"
            else:
                status = "clear"
            mapped = [classify_label(label) for label in selected]
            category_keys = []
            for item in mapped:
                if item and item["category_key"] not in category_keys:
                    category_keys.append(item["category_key"])
            result: dict[str, Any] = {
                "status": status,
                "primary_emotion": primary,
                "secondary_emotions": secondary,
                "category_keys": category_keys,
                "attribution": "unresolved",
                "attribution_supported": False,
                "source": "dedicated_35_ml",
                "taxonomy_version": taxonomy_version(),
                "shadow_only": True,
                "calibrated_label_count": sum(item["calibration_status"] == "calibrated" for item in scored),
                "uncalibrated_label_count": sum(item["calibration_status"] == "uncalibrated" for item in scored),
                "prediction_cardinality": cardinality_warning(len(selected)),
                "inference_time_ms": round((time.perf_counter() - started) * 1000, 3),
            }
            # Scores are internal evaluation evidence only and are opt-in.
            if include_scores:
                result["scores"] = scores
            return result
        except Exception:
            return {
                "status": "unavailable",
                "source": "dedicated_35_ml",
                "shadow_only": True,
                "inference_time_ms": round((time.perf_counter() - started) * 1000, 3),
            }


_classifier: Emotion35MultilabelClassifier | None = None


def get_classifier() -> Emotion35MultilabelClassifier:
    global _classifier
    if _classifier is None:
        _classifier = Emotion35MultilabelClassifier()
    return _classifier


