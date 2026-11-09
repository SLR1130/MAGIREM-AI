#!/usr/bin/env python3
"""
Signal Flow Tracer - Diagnose where anxiety/anger/sadness emotions become neutral
"""

import sys
import os
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))

# Enable debug mode
os.environ['FUSION_DEBUG'] = 'true'

# Real signal sources
from semantic_analysis import analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer
from emotion_fusion_service import EmotionFusionService


# Test messages - the 6 failing examples
TEST_MESSAGES = {
    'E1': "I'm stressed because of school.",
    'E3': "I'm furious about what they did.",
    'E4': "I feel empty and don't want to do anything today.",
    'T3': "Galit na galit ako sa ginawa nila.",
    'T4': "Kinakabahan ako sa mangyayari bukas.",
    'X1': "Medyo anxious ako sa presentation tomorrow.",
}

EXPECTED = {
    'E1': 'anxiety',
    'E3': 'anger',
    'E4': 'sadness',
    'T3': 'anger',
    'T4': 'anxiety',
    'X1': 'anxiety',
}


def normalize_emotion(emotion: str, source: str) -> str:
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
    
    canonical = ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']
    if emotion_lower in canonical:
        return emotion_lower
    
    return 'neutral'


def trace_message(msg_id: str, message: str):
    """Trace signal flow for a single message"""
    print(f"\n{'='*80}")
    print(f"TRACING: [{msg_id}] {message[:70]}")
    print(f"EXPECTED: {EXPECTED[msg_id]}")
    print(f"{'='*80}")
    
    service = EmotionFusionService()
    vader = SentimentIntensityAnalyzer()
    shadow_classifier = ShadowModeEmotionClassifier(enabled=True, deployable_path=None)
    
    # Step 1: Collect signals from analyzers
    print("\n[STEP 1] COLLECTING REAL SIGNALS FROM ANALYZERS\n")
    
    signals = {}
    
    # Semantic
    try:
        print("[A] SEMANTIC ANALYSIS")
        semantic_result = analyze_semantic_layer(message, None)
        print(f"    Raw result: {semantic_result}")
        semantic_emotion_raw = semantic_result.get('emotion', {}).get('dominant')
        semantic_confidence = semantic_result.get('emotion', {}).get('confidence', 0)
        semantic_emotion_norm = normalize_emotion(semantic_emotion_raw, 'semantic')
        print(f"    Dominant emotion: {semantic_emotion_raw}")
        print(f"    Normalized: {semantic_emotion_norm}")
        print(f"    Confidence: {semantic_confidence}")
        
        signals['semantic'] = {
            'emotion': semantic_emotion_norm,
            'confidence': semantic_confidence,
        }
    except Exception as e:
        print(f"    ERROR: {e}")
        signals['semantic'] = {'error': str(e)}
    
    # Unified
    try:
        print("\n[B] UNIFIED ANALYSIS")
        unified_result = analyze_unified(message, None, semantic_result=semantic_result)
        print(f"    Raw result type: {type(unified_result)}")
        print(f"    Emotion object: {unified_result.emotion}")
        unified_emotion_raw = unified_result.emotion.get('primary')
        unified_confidence = unified_result.emotion.get('confidence', 0)
        unified_emotion_norm = normalize_emotion(unified_emotion_raw, 'unified')
        print(f"    Primary emotion: {unified_emotion_raw}")
        print(f"    Normalized: {unified_emotion_norm}")
        print(f"    Confidence: {unified_confidence}")
        print(f"    Language: {unified_result.language.get('primary')}")
        print(f"    Negation detected: {unified_result.negation.get('detected', False)}")
        print(f"    Sarcasm detected: {unified_result.sarcasm.get('detected', False)}")
        
        signals['unified'] = {
            'emotion': unified_emotion_norm,
            'confidence': unified_confidence,
            'language': unified_result.language.get('primary'),
            'negation_detected': unified_result.negation.get('detected', False),
            'sarcasm_detected': unified_result.sarcasm.get('detected', False),
        }
    except Exception as e:
        print(f"    ERROR: {e}")
        signals['unified'] = {'error': str(e)}
    
    # Trained
    try:
        print("\n[C] TRAINED SHADOW MODEL")
        shadow_pred = shadow_classifier.predict(message)
        if shadow_pred:
            trained_emotion_raw = shadow_pred.get('emotion')
            trained_confidence = shadow_pred.get('confidence', 0)
            trained_emotion_norm = normalize_emotion(trained_emotion_raw, 'trained')
            probs = shadow_pred.get('probabilities', {})
            print(f"    Raw emotion: {trained_emotion_raw}")
            print(f"    Normalized: {trained_emotion_norm}")
            print(f"    Confidence: {trained_confidence}")
            print(f"    Top 3 probabilities: {dict(sorted(probs.items(), key=lambda x: x[1], reverse=True)[:3])}")
            
            signals['trained'] = {
                'emotion': trained_emotion_norm,
                'confidence': trained_confidence,
                'probabilities': probs,
            }
        else:
            print(f"    Prediction returned None")
            signals['trained'] = {'error': 'Prediction returned None'}
    except Exception as e:
        print(f"    ERROR: {e}")
        signals['trained'] = {'error': str(e)}
    
    # VADER
    try:
        print("\n[D] VADER SENTIMENT")
        vader_scores = vader.polarity_scores(message)
        print(f"    Compound: {vader_scores.get('compound', 0):.2f}")
        print(f"    Pos: {vader_scores.get('pos', 0):.2f}, Neg: {vader_scores.get('neg', 0):.2f}, Neu: {vader_scores.get('neu', 0):.2f}")
        
        signals['vader'] = {
            'compound': vader_scores.get('compound', 0),
            'pos': vader_scores.get('pos', 0),
            'neg': vader_scores.get('neg', 0),
            'neu': vader_scores.get('neu', 0),
        }
    except Exception as e:
        print(f"    ERROR: {e}")
        signals['vader'] = {'error': str(e)}
    
    # Step 2: Call fusion with debug enabled
    print("\n\n[STEP 2] FUSION SERVICE PROCESSING (debug output below)\n")
    print("-" * 80)
    
    try:
        fusion_result = service.fuse(message, signals)
    except Exception as e:
        print(f"ERROR: Fusion crashed: {e}")
        return
    
    print("-" * 80)
    
    # Step 3: Summary
    print(f"\n[STEP 3] SUMMARY\n")
    print(f"Expected: {EXPECTED[msg_id]}")
    print(f"Got: {fusion_result.final_emotion}")
    print(f"Confidence: {fusion_result.confidence:.2f}")
    print(f"Status: {'PASS' if fusion_result.final_emotion == EXPECTED[msg_id] else 'FAIL'}")
    reason_safe = fusion_result.reason.encode('utf-8', errors='replace').decode('utf-8')
    print(f"Reason: {reason_safe[:80]}")


def main():
    """Run trace for all 6 failing messages"""
    for msg_id, message in TEST_MESSAGES.items():
        try:
            trace_message(msg_id, message)
        except Exception as e:
            print(f"\nERROR tracing {msg_id}: {str(e)[:100]}")
            continue
    
    print(f"\n{'='*80}")
    print("TRACE COMPLETE")
    print(f"{'='*80}")


if __name__ == "__main__":
    main()
