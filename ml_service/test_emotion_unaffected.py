"""
Verify that emotion pipeline is unaffected by intent classification fix
"""

import sys
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')

sys.path.insert(0, '.')

from unified_analysis import analyze_unified
from semantic_analysis import analyze_semantic_layer

print("=" * 80)
print("EMOTION PIPELINE VERIFICATION (Should be unaffected by intent fix)")
print("=" * 80)
print()

# Test the three key messages that should maintain their emotions
test_cases = [
    {
        'message': "I'm really stressed with my studies lately and I don't know how to manage everything.",
        'expected_emotion': 'anxiety',
        'check_name': 'Message 1: Stressed with studies'
    },
    {
        'message': "Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul 😂",
        'expected_emotion': 'anxiety',
        'check_name': 'Message 2: Sarcasm with deadline stress'
    },
    {
        'message': "i dont have money bro",
        'expected_emotion': 'neutral',
        'check_name': 'Message 3: Financial statement'
    }
]

print(f"{'CHECK':<50} | {'DETECTED':<12} | {'EXPECTED':<12} | {'STATUS':<10}")
print("-" * 90)

all_pass = True
for test_case in test_cases:
    msg = test_case['message']
    expected = test_case['expected_emotion']
    
    semantic_result = analyze_semantic_layer(msg)
    result = analyze_unified(msg, semantic_result=semantic_result)
    detected = result.emotion['primary']
    
    status = "✓ PASS" if detected == expected else "✗ FAIL"
    print(f"{test_case['check_name']:<50} | {detected:<12} | {expected:<12} | {status:<10}")
    
    if detected != expected:
        all_pass = False
        print(f"  [FAILED] Got '{detected}' but expected '{expected}'")
        print(f"  Sentiment compound: {result.sentiment['compound']:.2f}")
        print(f"  Sarcasm detected: {result.sarcasm['detected']}")

print()
if all_pass:
    print("✓ EMOTION PIPELINE VERIFIED - Unaffected by intent fix")
else:
    print("✗ EMOTION PIPELINE AFFECTED - Check results above")

print()
print("=" * 80)
