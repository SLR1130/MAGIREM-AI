"""
Trained Multilingual Emotion Classifier - PRIMARY INFERENCE ENGINE
=====================================================================

This module loads and runs the validated multilingual emotion classifier
trained on real conversations in English, Tagalog, and Taglish.

Model: emotion_model_multilingual_candidate/checkpoint-130
Tokenizer: distilroberta-base (verified working)
Validation F1: 0.8034 (excellent)
Test Accuracy: 73.96%

This classifier is the PRIMARY emotion detection source.
Semantic analysis serves as BACKUP when classifier unavailable.
"""

import os
import json
import torch
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification
import time

class TrainedEmotionClassifier:
    """Load and run the validated multilingual emotion classifier"""
    
    def __init__(self):
        """Initialize the trained emotion classifier at startup"""
        self.model = None
        self.tokenizer = None
        self.id2label = None
        self.device = None
        self.load_time = None
        self.first_inference_time = None
        self.subsequent_inference_times = []
        self.enabled = False
        
        self._load()
    
    def _load(self):
        """Load model and tokenizer from checkpoint-130 or deployable"""
        try:
            load_start = time.time()
            print("[TRAINED_MODEL] Loading validated multilingual classifier...")
            
            # Force CPU for faster startup (checkpoint is small enough)
            self.device = "cpu"  # FORCE CPU for reliability during startup
            print(f"[TRAINED_MODEL] Device: {self.device}")
            
            # Resolve checkpoint path - TRY DEPLOYABLE FIRST (more reliable)
            module_dir = Path(__file__).resolve().parent
            
            # Try deployable version first (fully prepared)
            deployable_path = module_dir / "emotion_model_multilingual_deployable"
            if deployable_path.exists():
                model_path = deployable_path
                print(f"[TRAINED_MODEL] Using deployable version: {model_path}")
            else:
                # Fall back to checkpoint-130
                checkpoint_path = module_dir / "emotion_model_multilingual_candidate" / "checkpoint-130"
                if checkpoint_path.exists():
                    model_path = checkpoint_path
                    print(f"[TRAINED_MODEL] Using checkpoint-130: {model_path}")
                else:
                    raise FileNotFoundError(f"Neither deployable nor checkpoint found")
            
            print(f"[TRAINED_MODEL] Model path: {model_path}")
            
            # Load model and tokenizer
            print("[TRAINED_MODEL] Loading model from checkpoint...")
            self.model = AutoModelForSequenceClassification.from_pretrained(
                str(model_path),
                local_files_only=True  # Use checkpoint files only
            )
            
            print("[TRAINED_MODEL] Loading tokenizer from checkpoint...")
            self.tokenizer = AutoTokenizer.from_pretrained(
                str(model_path),
                local_files_only=True
            )
            
            # Move model to device and set eval mode
            self.model.to(self.device)
            self.model.eval()
            
            # Load label mapping
            # Try id2label.json first (deployable)
            label_file = model_path / "id2label.json"
            if label_file.exists():
                with open(label_file, 'r') as f:
                    config_data = json.load(f)
                    self.id2label = {int(k): v for k, v in config_data.get("id2label", {}).items()}
                    print(f"[TRAINED_MODEL] Loaded id2label from: {label_file}")
            else:
                # Fall back to config.json
                config_path = model_path / "config.json"
                with open(config_path, 'r') as f:
                    config = json.load(f)
                    self.id2label = {int(k): v for k, v in config.get("id2label", {}).items()}
                    print(f"[TRAINED_MODEL] Loaded id2label from: {config_path}")
            
            self.enabled = True
            self.load_time = time.time() - load_start
            
            print(f"[TRAINED_MODEL] [OK] Loaded successfully on {self.device}")
            print(f"[TRAINED_MODEL] Load time: {self.load_time:.2f}s")
            print(f"[TRAINED_MODEL] Label mapping: {self.id2label}")
            print(f"[TRAINED_MODEL] Tokenizer vocab size: {len(self.tokenizer)}")
            print("[TRAINED_MODEL] PRIMARY EMOTION CLASSIFIER ACTIVE")
            
            return True
            
        except Exception as e:
            print(f"[TRAINED_MODEL] [FAILED] Failed to load: {e}")
            import traceback
            traceback.print_exc()
            self.enabled = False
            return False
    
    def predict(self, text, return_all_logits=False):
        """
        Predict emotion for text
        
        Args:
            text: User message to classify
            return_all_logits: If True, return raw logits for all classes
        
        Returns:
            dict with:
                - emotion: primary emotion label
                - confidence: softmax probability of primary emotion
                - probabilities: dict of all class probabilities
                - logits: raw model logits
                - source: "trained_model"
                - inference_time_ms: time taken for prediction
                OR None if model not enabled
        """
        if not self.enabled or self.model is None:
            return None
        
        try:
            inference_start = time.time()
            
            # Tokenize input
            inputs = self.tokenizer(
                text,
                truncation=True,
                max_length=128,  # Same as training
                padding="max_length",
                return_tensors="pt"
            )
            
            # Move inputs to device
            inputs = {k: v.to(self.device) for k, v in inputs.items()}
            
            # Forward pass
            with torch.no_grad():
                outputs = self.model(**inputs)
                logits = outputs.logits[0].cpu().numpy()  # Shape: (num_labels,)
            
            # Get predictions
            pred_idx = logits.argmax()
            pred_emotion = self.id2label[pred_idx]
            
            # Convert logits to probabilities using softmax
            import numpy as np
            exp_logits = np.exp(logits - logits.max())  # Numerical stability
            probs = exp_logits / exp_logits.sum()
            
            # Build result
            inference_time = (time.time() - inference_start) * 1000  # milliseconds
            
            # Track timing stats
            if self.first_inference_time is None:
                self.first_inference_time = inference_time
                print(f"[TRAINED_MODEL] First inference: {inference_time:.1f}ms")
            else:
                self.subsequent_inference_times.append(inference_time)
            
            result = {
                "emotion": pred_emotion,
                "confidence": float(probs[pred_idx]),
                "probabilities": {
                    self.id2label[i]: float(probs[i])
                    for i in range(len(self.id2label))
                },
                "source": "trained_model",
                "inference_time_ms": float(inference_time),
            }
            
            if return_all_logits:
                result["logits"] = logits.tolist()
            
            return result
            
        except Exception as e:
            print(f"[TRAINED_MODEL] Prediction error: {e}")
            return None
    
    def get_timing_stats(self):
        """Return timing statistics for model loading and inference"""
        stats = {
            "load_time_s": self.load_time,
            "first_inference_ms": self.first_inference_time,
        }
        
        if self.subsequent_inference_times:
            import statistics
            stats["avg_subsequent_inference_ms"] = statistics.mean(self.subsequent_inference_times)
            stats["min_inference_ms"] = min(self.subsequent_inference_times)
            stats["max_inference_ms"] = max(self.subsequent_inference_times)
            stats["inference_count"] = len(self.subsequent_inference_times)
        
        return stats


# Global instance - load once at startup
_classifier_instance = None

def get_classifier():
    """Get or initialize the global classifier instance"""
    global _classifier_instance
    if _classifier_instance is None:
        _classifier_instance = TrainedEmotionClassifier()
    return _classifier_instance

def classify_emotion(text):
    """
    Convenience function to classify emotion
    
    Returns:
        dict with emotion, confidence, probabilities, source, or None if error
    """
    classifier = get_classifier()
    if classifier.enabled:
        return classifier.predict(text)
    return None
