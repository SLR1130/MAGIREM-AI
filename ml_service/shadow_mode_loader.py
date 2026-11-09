"""
Shadow Mode Loader for Emotion Classifier
===========================================
Optional feature to run trained emotion classifier alongside existing system.
Produces diagnostics without affecting production routing.
"""

import os
import json
import torch
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification

class ShadowModeEmotionClassifier:
    """Load and run trained emotion classifier in shadow mode (diagnostics only)"""
    
    def __init__(self, enabled=False, deployable_path=None):
        """
        Initialize shadow mode classifier
        
        Args:
            enabled: If False, all methods return None (no overhead)
            deployable_path: Optional explicit path. If None, resolves relative to this module.
                            If provided as absolute path, uses as-is.
                            If provided as relative path, resolves relative to ml_service module.
        """
        self.enabled = enabled
        self.model = None
        self.tokenizer = None
        self.id2label = None
        self.device = None
        
        if not enabled:
            return
            
        try:
            print("[SHADOW_MODE] Initializing trained emotion classifier...")
            self.device = "cuda" if torch.cuda.is_available() else "cpu"
            
            # Resolve deployable path robustly
            if deployable_path is None:
                # Default: resolve relative to this module's location
                module_dir = Path(__file__).resolve().parent
                deployable_path = module_dir / "emotion_model_multilingual_deployable"
            else:
                # If explicit path provided
                deployable_path = Path(deployable_path)
                if not deployable_path.is_absolute():
                    # Relative paths resolve relative to this module, NOT cwd
                    module_dir = Path(__file__).resolve().parent
                    deployable_path = module_dir / deployable_path
                else:
                    # Already absolute, use as-is
                    deployable_path = deployable_path.resolve()
            
            # Ensure path is absolute and resolved
            deployable_path = deployable_path.resolve()
            
            print(f"[SHADOW_MODE] Resolved deployable path: {deployable_path}")
            
            if not deployable_path.exists():
                raise FileNotFoundError(f"Deployable directory not found: {deployable_path}")
            
            os.environ['HF_HUB_OFFLINE'] = '1'
            self.model = AutoModelForSequenceClassification.from_pretrained(
                str(deployable_path),
                local_files_only=True
            )
            self.tokenizer = AutoTokenizer.from_pretrained(
                str(deployable_path),
                local_files_only=True
            )
            del os.environ['HF_HUB_OFFLINE']
            
            self.model.to(self.device)
            self.model.eval()
            
            # Load label mapping
            label_file = deployable_path / "id2label.json"
            with open(label_file, 'r') as f:
                config = json.load(f)
            self.id2label = {int(k): v for k, v in config["id2label"].items()}
            
            print(f"[SHADOW_MODE] [OK] Classifier loaded on {self.device}")
            print(f"[SHADOW_MODE] Emotions: {', '.join(self.id2label.values())}")
        except Exception as e:
            print(f"[SHADOW_MODE] [ERROR] Failed to load: {e}")
            self.enabled = False
    
    def predict(self, text):
        """
        Predict emotion for text in shadow mode
        
        Returns:
            dict with emotion, confidence, probabilities or None if disabled
        """
        if not self.enabled or self.model is None:
            return None
            
        try:
            # Tokenize
            inputs = self.tokenizer(
                text,
                truncation=True,
                max_length=128,
                padding="max_length",
                return_tensors="pt"
            )
            inputs = {k: v.to(self.device) for k, v in inputs.items()}
            
            # Predict
            with torch.no_grad():
                outputs = self.model(**inputs)
                logits = outputs.logits.cpu().numpy()[0]
                
            pred_idx = logits.argmax()
            probs = torch.nn.functional.softmax(torch.tensor(logits), dim=0).numpy()
            
            return {
                "emotion": self.id2label[pred_idx],
                "confidence": float(probs[pred_idx]),
                "probabilities": {
                    self.id2label[i]: float(p) for i, p in enumerate(probs)
                },
                "logits": logits.tolist()
            }
        except Exception as e:
            print(f"[SHADOW_MODE] Prediction error: {e}")
            return None
