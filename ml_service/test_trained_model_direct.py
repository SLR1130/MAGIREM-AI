#!/usr/bin/env python3
"""
PHASE 10: Direct Trained Model Test
Tests the trained classifier on all 8 test messages without Flask
"""

from trained_emotion_classifier import classify_emotion
import json

test_messages = [
    ("I'm a little sad kasi hindi ako nakapasa sa exam today", "Test 1: Exam + sadness (explicit)"),
    ("hindi ako nakapasa sa exam", "Test 2: Exam alone (context only)"),
    ("Kinakabihan ako ksi may exam ako bukas", "Test 3: Future exam + explicit anxiety"),
    ("Masaya ako kasi pasado ako sa exam", "Test 4: Exam + joy (passing)"),
    ("Galit ako ksi unfair yung grade ko sa exam", "Test 5: Exam + anger"),
    ("May exam ako bukas", "Test 6: Exam alone (neutral context)"),
    ("Pakiramdam ko wala nang pag-asa at gusto ko nang mawala", "Test 7: Crisis message"),
    ("hey", "Test 8: Simple greeting"),
]

print("="*80)
print("PHASE 10: DIRECT TRAINED MODEL TEST")
print("="*80)

results = []

for message, description in test_messages:
    print(f"\n{description}")
    print(f"Message: \"{message}\"")
    
    result = classify_emotion(message)
    
    if result:
        print(f"✓ Emotion: {result['emotion']}")
        print(f"  Confidence: {result['confidence']:.2%}")
        print(f"  Inference: {result['inference_time_ms']:.1f}ms")
        print(f"  Source: {result['source']}")
        print(f"  Probabilities:")
        for emotion, prob in sorted(result['probabilities'].items(), key=lambda x: x[1], reverse=True):
            print(f"    - {emotion}: {prob:.2%}")
        
        results.append({
            'message': message[:50],
            'emotion': result['emotion'],
            'confidence': result['confidence'],
            'inference_ms': result['inference_time_ms'],
        })
    else:
        print("✗ Prediction failed")
        results.append({
            'message': message[:50],
            'emotion': 'ERROR',
            'confidence': 0,
        })

print(f"\n{'='*80}")
print("SUMMARY")
print(f"{'='*80}")
print(f"Tests completed: {len([r for r in results if r['emotion'] != 'ERROR'])}/{len(results)}")

# Print JSON for reference
print("\nJSON Results:")
print(json.dumps(results, indent=2))

print(f"\n{'='*80}")
