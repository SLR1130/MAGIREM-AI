#!/usr/bin/env python3
"""
Detailed diagnostic test to trace exact emotion detection and response generation flow
Captures every step: semantic analysis → validation → fallback → response
"""

import json
import sys
import requests
from pathlib import Path

# Add ml_service to path
sys.path.insert(0, str(Path(__file__).parent))

TEST_CASES = [
    {
        'name': 'HAPPY: Friends Surprise',
        'message': 'My friends surprised me today and it made my whole day.',
        'expected_emotion': 'happiness',
    },
    {
        'name': 'SADNESS: Failed Exam',
        'message': 'I failed my exam after studying for weeks.',
        'expected_emotion': 'sadness',
    },
    {
        'name': 'ANXIETY: Projects Due',
        'message': 'I have three projects due tomorrow and I haven\'t finished any of them.',
        'expected_emotion': 'anxiety',
    },
    {
        'name': 'ANGER: Ignored Opinions',
        'message': 'I\'m so frustrated. Everyone keeps ignoring my opinions at work.',
        'expected_emotion': 'anger',
    },
    {
        'name': 'SARCASM: Drowning Positive',
        'message': 'Yeah, sure, everything is going great and I\'m definitely not drowning.',
        'expected_emotion': 'sadness',  # sarcasm flips it
    },
]

def test_semantic_analysis(message):
    """Test semantic analysis directly"""
    print(f"\n  [SEMANTIC_ANALYSIS]")
    try:
        # Import semantic analyzer directly
        from semantic_analysis import analyze_semantic_layer
        
        result = analyze_semantic_layer(message, [])
        
        emotion_data = result.get('emotion', {})
        print(f"    Dominant emotion: {emotion_data.get('dominant')}")
        print(f"    Confidence: {emotion_data.get('confidence', 0):.0%}")
        print(f"    All scores: {emotion_data.get('all_scores', {})}")
        
        tone = result.get('tone', {})
        if tone:
            print(f"    Sarcasm score: {tone.get('sarcasm_score', 0):.0%}")
            print(f"    Satire score: {tone.get('satire_score', 0):.0%}")
            print(f"    Hyperbole score: {tone.get('hyperbole_score', 0):.0%}")
        
        return result
    except Exception as e:
        print(f"    ERROR: {e}")
        return None

def test_ml_endpoint(message, test_name):
    """Test the complete ML endpoint"""
    print(f"\n{'='*75}")
    print(f"TEST: {test_name}")
    print(f"{'='*75}")
    print(f"Message: {message}\n")
    
    # First test semantic analysis directly
    semantic_result = test_semantic_analysis(message)
    
    print(f"\n  [ML_ENDPOINT_CALL]")
    try:
        response = requests.post(
            'http://localhost:5000/chat',
            json={
                'message': message,
                'user_id': 'test_user',
                'conversation_history': []
            },
            timeout=30
        )
        
        if response.status_code != 200:
            print(f"    ❌ HTTP {response.status_code}")
            print(f"    Response: {response.text[:200]}")
            return None
        
        data = response.json()
        
        if not data.get('success'):
            print(f"    ❌ Success = False")
            return None
        
        emotion = data.get('emotion', 'UNKNOWN')
        confidence = data.get('confidence', 0)
        response_text = data.get('response', '')
        analysis = data.get('analysis', {})
        model_type = analysis.get('model_type', 'unknown')
        
        print(f"    Emotion: {emotion}")
        print(f"    Confidence: {confidence:.0%}")
        print(f"    Model type: {model_type}")
        print(f"    Response length: {len(response_text)} chars")
        print(f"    Response preview: {response_text[:80]}...")
        
        # Check if response is generic fallback
        generic_fallback = "i'm here to listen and support you. what's on your mind"
        if generic_fallback in response_text.lower():
            print(f"    ⚠️  GENERIC FALLBACK DETECTED")
        else:
            print(f"    ✅ Response is contextual")
        
        return {
            'emotion': emotion,
            'confidence': confidence,
            'response': response_text,
            'model_type': model_type,
            'semantic': semantic_result
        }
        
    except requests.ConnectionError:
        print(f"    ❌ Cannot connect to ML server at localhost:5000")
        return None
    except Exception as e:
        print(f"    ❌ ERROR: {e}")
        return None

def evaluate_result(result, test_case):
    """Evaluate if result matches expectations"""
    if not result:
        return False, "No response from ML"
    
    emotion = result['emotion'].lower()
    expected = test_case['expected_emotion'].lower()
    
    print(f"\n  [EVALUATION]")
    
    # Check emotion
    if emotion == expected:
        print(f"    ✅ Emotion correct: {emotion}")
        emotion_pass = True
    else:
        print(f"    ❌ Emotion wrong: got '{emotion}', expected '{expected}'")
        emotion_pass = False
    
    # Check response
    response = result['response'].lower()
    generic = "i'm here to listen and support you. what's on your mind"
    
    if generic in response:
        print(f"    ❌ Response is generic fallback")
        response_pass = False
    else:
        print(f"    ✅ Response is not generic fallback")
        response_pass = True
    
    return emotion_pass and response_pass, f"Emotion: {emotion_pass}, Response: {response_pass}"

def main():
    print("="*75)
    print("DETAILED EMOTION DETECTION DIAGNOSTIC")
    print("="*75)
    
    results = []
    
    for test_case in TEST_CASES:
        result = test_ml_endpoint(test_case['message'], test_case['name'])
        passed, reason = evaluate_result(result, test_case)
        
        results.append({
            'name': test_case['name'],
            'passed': passed,
            'reason': reason,
            'emotion': result.get('emotion', 'ERROR') if result else 'ERROR'
        })
    
    # Summary
    print(f"\n{'='*75}")
    print("SUMMARY")
    print(f"{'='*75}")
    
    passed_count = sum(1 for r in results if r['passed'])
    total = len(results)
    
    print(f"Passed: {passed_count}/{total}\n")
    
    for r in results:
        status = "✅ PASS" if r['passed'] else "❌ FAIL"
        print(f"{status}: {r['name']}")
        print(f"       Emotion: {r['emotion']}")
        print(f"       {r['reason']}\n")

if __name__ == '__main__':
    main()
