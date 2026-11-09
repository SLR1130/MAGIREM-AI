#!/usr/bin/env python3
"""
TASK 9 - Emotion Signal Diagnostic
Captures all available emotion signals for fusion architecture design
Shadow mode only - no modifications to production systems
"""

import sys
import json
from pathlib import Path

# Add ml_service to path
sys.path.insert(0, str(Path(__file__).parent))

# Test messages in 3 languages
TEST_MESSAGES = {
    "English": [
        "I'm stressed because of school.",
        "I'm not sad, I'm just exhausted.",
        "I'm furious about what they did.",
        "I feel empty and don't want to do anything today.",
        "My grandmother passed away and I really miss her.",
        "I'm doing pretty good today."
    ],
    "Tagalog": [
        "Medyo pagod at malungkot ako ngayong araw.",
        "Hindi naman ako malungkot, pagod lang talaga.",
        "Galit na galit ako sa ginawa nila.",
        "Kinakabahan ako sa mangyayari bukas.",
        "Miss na miss ko ang lola kong namatay.",
        "Okay lang naman ako ngayon."
    ],
    "Taglish": [
        "Medyo anxious ako sa presentation tomorrow.",
        "I'm not sad naman, sobrang pagod lang.",
        "Galit talaga ako because they ignored what I said.",
        "Parang wala akong gana gumawa ng anything today.",
        "Miss ko pa rin siya since namatay siya last year.",
        "Pretty okay naman ako today."
    ]
}

def capture_signals(message, language):
    """Capture all available emotion signals for a message"""
    
    signals = {
        "message": message,
        "language": language,
        "signals": {}
    }
    
    try:
        # Import analysis modules
        from semantic_analysis import analyze_semantic_layer
        from unified_analysis import analyze_unified
        from shadow_mode_loader import ShadowModeEmotionClassifier
        
        # SIGNAL 1: Semantic Analysis
        print(f"\n[DIAGNOSTIC] {language}: {message[:50]}...")
        
        try:
            semantic_result = analyze_semantic_layer(message, None)
            signals["signals"]["semantic"] = {
                "emotion": semantic_result.get("emotion", {}).get("dominant"),
                "confidence": semantic_result.get("emotion", {}).get("confidence", 0),
                "intensity": semantic_result.get("emotion", {}).get("intensity", 0),
                "all_scores": semantic_result.get("emotion", {}).get("scores", {}),
                "tone": semantic_result.get("tone", {}),
                "negation_detected": semantic_result.get("negation_detected", False),
            }
            print(f"[SEMANTIC] {signals['signals']['semantic']['emotion']} ({signals['signals']['semantic']['confidence']:.1%})")
        except Exception as e:
            print(f"[SEMANTIC_ERROR] {e}")
            signals["signals"]["semantic"] = {"error": str(e)}
        
        # SIGNAL 2: Unified Analysis
        try:
            unified_result = analyze_unified(message, None, semantic_result=semantic_result)
            signals["signals"]["unified"] = {
                "emotion": unified_result.emotion.get("primary"),
                "confidence": unified_result.emotion.get("confidence", 0),
                "source": unified_result.emotion.get("source"),
                "sarcasm_detected": unified_result.sarcasm.get("detected", False),
                "sarcasm_confidence": unified_result.sarcasm.get("confidence", 0),
                "underlying_emotion": unified_result.sarcasm.get("underlying_emotion"),
                "negation_detected": unified_result.negation.get("detected", False),
                "negation_type": unified_result.negation.get("type"),
                "intent": unified_result.intent.get("primary"),
                "language": unified_result.language.get("primary"),
            }
            print(f"[UNIFIED] {signals['signals']['unified']['emotion']} ({signals['signals']['unified']['confidence']:.1%})")
            if unified_result.sarcasm.get("detected"):
                print(f"[SARCASM] Detected (underlying: {unified_result.sarcasm.get('underlying_emotion')})")
        except Exception as e:
            print(f"[UNIFIED_ERROR] {e}")
            signals["signals"]["unified"] = {"error": str(e)}
        
        # SIGNAL 3: Trained Shadow Model
        try:
            shadow_classifier = ShadowModeEmotionClassifier(
                enabled=True,
                deployable_path=None  # Uses default module-relative path
            )
            shadow_pred = shadow_classifier.predict(message)
            if shadow_pred:
                signals["signals"]["trained"] = {
                    "emotion": shadow_pred.get("emotion"),
                    "confidence": shadow_pred.get("confidence", 0),
                    "probabilities": shadow_pred.get("probabilities", {}),
                }
                print(f"[TRAINED] {signals['signals']['trained']['emotion']} ({signals['signals']['trained']['confidence']:.1%})")
            else:
                signals["signals"]["trained"] = {"error": "Prediction returned None"}
        except Exception as e:
            print(f"[TRAINED_ERROR] {e}")
            signals["signals"]["trained"] = {"error": str(e)}
        
        # SIGNAL 4: VADER Sentiment (observational)
        try:
            from nltk.sentiment import SentimentIntensityAnalyzer
            vader = SentimentIntensityAnalyzer()
            vader_scores = vader.polarity_scores(message)
            signals["signals"]["vader"] = {
                "compound": vader_scores.get("compound", 0),
                "positive": vader_scores.get("pos", 0),
                "negative": vader_scores.get("neg", 0),
                "neutral": vader_scores.get("neu", 0),
            }
            print(f"[VADER] compound={signals['signals']['vader']['compound']:.2f}")
        except Exception as e:
            print(f"[VADER_ERROR] {e}")
            signals["signals"]["vader"] = {"error": str(e)}
        
    except Exception as e:
        print(f"[CAPTURE_ERROR] {e}")
        signals["error"] = str(e)
    
    return signals

def main():
    """Run diagnostics for all test messages"""
    
    print("\n" + "="*60)
    print("TASK 9 - EMOTION SIGNAL DIAGNOSTIC")
    print("="*60)
    print("Capturing emotion signals from test messages...")
    
    all_results = []
    
    for language, messages in TEST_MESSAGES.items():
        print(f"\n{'='*60}")
        print(f"Language: {language}")
        print(f"{'='*60}")
        
        for message in messages:
            signals = capture_signals(message, language)
            all_results.append(signals)
    
    # Save results to JSON
    output_file = Path(__file__).parent / "emotion_diagnostics_results.json"
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(all_results, f, indent=2, ensure_ascii=False)
    
    print(f"\n{'='*60}")
    print(f"Results saved to: {output_file}")
    print(f"Total messages analyzed: {len(all_results)}")
    print(f"{'='*60}\n")
    
    return all_results

if __name__ == "__main__":
    results = main()
