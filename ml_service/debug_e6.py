#!/usr/bin/env python3
"""Debug E6 fusion decision"""

import sys
from pathlib import Path
from typing import Optional
sys.path.insert(0, str(Path(__file__).parent))

from semantic_analysis import analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer
from emotion_fusion_service import EmotionFusionService

message = "I'm doing pretty good today."
print(f"Message: {message}\n")

# Get semantic
semantic = analyze_semantic_layer(message, None)
print(f"Semantic emotion: {semantic['emotion'].get('dominant')}")
print(f"Semantic confidence: {semantic['emotion'].get('confidence')}")
print()

# Get unified
unified = analyze_unified(message, None, semantic_result=semantic)
print(f"Unified emotion: {unified.emotion.get('primary')}")
print(f"Unified confidence: {unified.emotion.get('confidence')}")
print()

# Get trained
try:
    classifier = ShadowModeEmotionClassifier()
    trained_pred = classifier.predict(message)
    if trained_pred:
        print(f"Trained emotion: {trained_pred.get('emotion')}")
        print(f"Trained confidence: {trained_pred.get('confidence')}")
    else:
        print(f"Trained: None")
        trained_pred = {'emotion': 'neutral', 'confidence': 0.5}
except Exception as e:
    print(f"Trained error: {e}")
    trained_pred = {'emotion': 'neutral', 'confidence': 0.5}
print()

# Get VADER
vader = SentimentIntensityAnalyzer().polarity_scores(message)
print(f"VADER compound: {vader['compound']}")
print(f"VADER pos/neg/neu: {vader['pos']}/{vader['neg']}/{vader['neu']}")
print()

# Create signals dict as fusion expects (SAME AS V2 TEST)
# Pre-normalize all emotions to canonical classes
def normalize_emotion(emotion: Optional[str], source: str) -> str:
    """Normalize to canonical 6-class"""
    if not emotion:
        return 'neutral'
    
    emotion_lower = emotion.lower().strip()
    
    if source == 'semantic':
        mapping = {
            'happiness': 'joy',
            'sadness': 'sadness',
            'anxiety': 'anxiety',
            'anger': 'anger',
            'neutral': 'neutral',
        }
        return mapping.get(emotion_lower, 'neutral')
    
    # Already 6-class for unified and trained
    canonical = ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']
    if emotion_lower in canonical:
        return emotion_lower
    
    return 'neutral'

semantic_norm = normalize_emotion(semantic['emotion'].get('dominant'), 'semantic')
unified_norm = normalize_emotion(unified.emotion.get('primary'), 'unified')
trained_norm = normalize_emotion(trained_pred.get('emotion'), 'trained')

signals = {
    'semantic': {
        'emotion': semantic_norm,
        'confidence': semantic['emotion'].get('confidence'),
        'negation_detected': semantic.get('negation_detected', False),
    },
    'unified': {
        'emotion': unified_norm,
        'confidence': unified.emotion.get('confidence'),
        'language': unified.language.get('primary', 'english'),
        'negation_detected': unified.negation.get('detected', False),
        'sarcasm_detected': unified.sarcasm.get('detected', False),
    },
    'trained': {
        'emotion': trained_norm,
        'confidence': trained_pred.get('confidence'),
    },
    'vader': vader,
}

print(f"\nSignals dict before fusion (NORMALIZED):")
print(f"  semantic.emotion: {signals['semantic'].get('emotion')}")
print(f"  unified.emotion: {signals['unified'].get('emotion')}")
print(f"  trained.emotion: {signals['trained'].get('emotion')}")

# Fuse
fusion_service = EmotionFusionService()
result = fusion_service.fuse(message, signals)

print(f"\n=== FUSION RESULT ===")
print(f"Final emotion: {result.final_emotion}")
print(f"Confidence: {result.confidence}")
print(f"Uncertain: {result.uncertain_flag}")
print(f"Reason: {result.reason}")
print(f"Agreement pattern: {result.agreement_pattern}")
