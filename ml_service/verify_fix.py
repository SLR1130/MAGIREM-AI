#!/usr/bin/env python3
"""
Quick verification that the label swap fix works
"""

import sys
import time

print("\n" + "=" * 80)
print("LABEL SWAP FIX VERIFICATION")
print("=" * 80)

try:
    from unified_ml_api_with_model import (
        load_trained_model,
        detect_emotion_trained,
    )
    from semantic_analysis import analyze_semantic_layer
    
    # Load model
    model_loaded = load_trained_model()
    print(f"\n[OK] Model loaded: {model_loaded}")
    
    test_cases = [
        {
            'text': "I've been feeling pretty good today.",
            'expected_emotion': 'joy',
            'reason': 'Positive statement'
        },
        {
            'text': "I've been really stressed because of my deadlines.",
            'expected_emotion': 'anxiety',  # or sadness
            'reason': 'Stress-related'
        },
        {
            'text': "I feel really sad and down.",
            'expected_emotion': 'sadness',
            'reason': 'Explicitly sad'
        },
        {
            'text': "I'm so happy and excited!",
            'expected_emotion': 'joy',
            'reason': 'Explicitly happy'
        }
    ]
    
    print("\n" + "-" * 80)
    print("Testing emotion detection with FIXED label mapping:")
    print("-" * 80)
    
    correct = 0
    total = 0
    
    for test in test_cases:
        text = test['text']
        expected = test['expected_emotion']
        reason = test['reason']
        
        # Get trained model prediction
        result = detect_emotion_trained(text)
        
        # Get semantic for comparison
        semantic = analyze_semantic_layer(text)
        sem_emotion = semantic['emotion']['dominant']
        
        total += 1
        match = "[OK]" if result == expected else "[FAIL]"
        if result == expected:
            correct += 1
        
        print(f"\n{match} \"{text[:50]}...\"")
        print(f"   Reason: {reason}")
        print(f"   Semantic: {sem_emotion}")
        print(f"   Trained: {result}")
        print(f"   Expected: {expected}")
    
    print("\n" + "=" * 80)
    print(f"RESULTS: {correct}/{total} correct")
    print("=" * 80)
    
    if correct >= 3:  # At least 3 out of 4
        print("\n[SUCCESS] FIX SUCCESSFUL: Emotion predictions are now sensible!")
        sys.exit(0)
    else:
        print(f"\n[PARTIAL] FIX INCOMPLETE: Only {correct} out of {total} correct")
        sys.exit(1)

except Exception as e:
    print(f"\n[ERROR] Verification failed: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)
