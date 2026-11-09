#!/usr/bin/env python3
"""
Trace trained signal source for forensic verification
"""

import os
import sys

# Set environment before imports
os.environ['FUSION_SHADOW_ENABLED'] = 'true'
os.environ['EMOTION_MODEL_SHADOW_ENABLED'] = 'false'

sys.path.insert(0, str(os.getcwd()))

from semantic_analysis import analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer

message = "I'm stressed because of school."

print("="*70)
print("FORENSIC TRACE: Trained Signal Source")
print("="*70)

print(f"\n[ENV] EMOTION_MODEL_SHADOW_ENABLED: {os.getenv('EMOTION_MODEL_SHADOW_ENABLED')}")
print(f"[ENV] FUSION_SHADOW_ENABLED: {os.getenv('FUSION_SHADOW_ENABLED')}")

print(f"\n[MSG] Processing: {message}")

# ===== SIGNAL 1: SEMANTIC =====
print("\n[SEMANTIC ANALYSIS]")
semantic_result = analyze_semantic_layer(message, None)
semantic_emotion = semantic_result.get('emotion', {}).get('dominant')
print(f"  Emotion: {semantic_emotion}")
print(f"  Confidence: {semantic_result.get('emotion', {}).get('confidence')}")

# ===== SIGNAL 2: UNIFIED =====
print("\n[UNIFIED ANALYSIS]")
unified_result = analyze_unified(message, None, semantic_result=semantic_result)
unified_emotion = unified_result.emotion.get('primary')
print(f"  Emotion: {unified_emotion}")
print(f"  Confidence: {unified_result.emotion.get('confidence')}")

# ===== SIGNAL 3: TRAINED (SHADOW MODE) =====
print("\n[TRAINED CLASSIFIER - SHADOW MODE]")
print(f"  EMOTION_MODEL_SHADOW_ENABLED: {os.getenv('EMOTION_MODEL_SHADOW_ENABLED')}")

shadow_enabled = os.getenv("EMOTION_MODEL_SHADOW_ENABLED", "false").lower() == "true"
print(f"  Shadow enabled (env check): {shadow_enabled}")

if shadow_enabled:
    print("  Attempting to load ShadowModeEmotionClassifier...")
    try:
        shadow_classifier = ShadowModeEmotionClassifier(
            enabled=True,
            deployable_path="emotion_model_multilingual_deployable"
        )
        print(f"  Classifier loaded: {shadow_classifier}")
        print(f"  Classifier enabled attr: {shadow_classifier.enabled}")
        
        trained_pred = shadow_classifier.predict(message)
        trained_emotion = trained_pred.get('emotion') if trained_pred else None
        print(f"  Trained emotion: {trained_emotion}")
        print(f"  Confidence: {trained_pred.get('confidence') if trained_pred else 'N/A'}")
        
    except Exception as e:
        print(f"  ERROR loading trained classifier: {e}")
        trained_emotion = None
else:
    print("  Shadow disabled (EMOTION_MODEL_SHADOW_ENABLED=false)")
    print("  _shadow_classifier would be: None")
    trained_emotion = None

# ===== SIGNAL 4: VADER =====
print("\n[VADER]")
sia = SentimentIntensityAnalyzer()
vader_scores = sia.polarity_scores(message)
print(f"  Compound: {vader_scores['compound']}")

# ===== FUSION INPUT CONSTRUCTION =====
print("\n[FUSION INPUT SIGNALS]")
print(f"  semantic['emotion']: {semantic_emotion}")
print(f"  unified['emotion']: {unified_emotion}")
print(f"  trained['emotion']: {trained_emotion if trained_emotion else '(NONE - shadow disabled)'}")

print("\n[CRITICAL FINDING]")
if trained_emotion is None:
    print("  *** TRAINED SIGNAL IS NULL/UNAVAILABLE ***")
    print("  Reason: EMOTION_MODEL_SHADOW_ENABLED=false")
    print("  _shadow_classifier is: None")
    print("  Fusion receives: trained['emotion'] = 'neutral' (fallback)")
else:
    print(f"  *** TRAINED SIGNAL IS REAL: {trained_emotion} ***")

print("\n" + "="*70)
