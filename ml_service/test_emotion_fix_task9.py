#!/usr/bin/env python3
"""
TASK 9: Test Emotion Classification Fix
Tests the new priority system: Explicit emotion > Context indicators
"""

from semantic_analysis import SemanticAnalyzer

def test_emotion_classification():
    """Test all 7 cases from TASK 9 PHASE 7"""
    
    analyzer = SemanticAnalyzer()
    
    test_cases = [
        {
            'id': 1,
            'message': "I'm a little sad kasi hindi ako nakapasa sa exam today",
            'expected_emotion': 'sadness',
            'expected_min_confidence': 0.70,
            'description': 'Exam + Sadness (explicit emotion)'
        },
        {
            'id': 2,
            'message': "hindi ako nakapasa sa exam",
            'expected_emotion': 'sadness',  # Could also be 'neutral' with low confidence
            'expected_max_confidence': 0.65,  # Should be capped due to weak evidence
            'description': 'Exam alone (context only, no explicit emotion)'
        },
        {
            'id': 3,
            'message': "Kinakabihan ako ksi may exam ako bukas",
            'expected_emotion': 'anxiety',
            'expected_min_confidence': 0.70,
            'description': 'Future exam + Explicit anxiety'
        },
        {
            'id': 4,
            'message': "Masaya ako kasi pasado ako sa exam",
            'expected_emotion': 'happiness',
            'expected_min_confidence': 0.70,
            'description': 'Exam + Joy (explicit emotion)'
        },
        {
            'id': 5,
            'message': "Galit ako ksi unfair yung grade ko sa exam",
            'expected_emotion': 'anger',
            'expected_min_confidence': 0.70,
            'description': 'Exam + Anger (explicit emotion)'
        },
        {
            'id': 6,
            'message': "May exam ako bukas",
            'expected_emotion': 'neutral',
            'expected_max_confidence': 0.60,
            'description': 'Exam alone (truly neutral, no emotion)'
        },
        {
            'id': 7,
            'message': "Pakiramdam ko wala nang pag-asa at gusto ko nang mawala",
            'expected_emotion': 'sadness',  # Or crisis detection at higher level
            'expected_min_confidence': 0.70,
            'description': 'Crisis message (high risk)'
        },
    ]
    
    print("="*80)
    print("TASK 9: EMOTION CLASSIFICATION FIX - TEST SUITE")
    print("="*80)
    
    results = []
    
    for test in test_cases:
        print(f"\n{'='*80}")
        print(f"TEST {test['id']}: {test['description']}")
        print(f"Message: \"{test['message']}\"")
        print(f"Expected emotion: {test['expected_emotion']}")
        
        # Perform analysis
        result = analyzer.analyze(test['message'])
        
        emotion = result['emotion']['dominant']
        confidence = result['emotion']['confidence']
        scores = result['emotion']['scores']
        
        print(f"\nActual emotion: {emotion}")
        print(f"Actual confidence: {confidence:.0%}")
        print(f"Score distribution:")
        for e, score in scores.items():
            print(f"  - {e}: {score:.0%}")
        
        # Check expectations
        passed = True
        errors = []
        
        if emotion != test['expected_emotion']:
            # Allow some flexibility for sadness/neutral
            if not ((emotion in ['sadness', 'neutral'] and test['expected_emotion'] in ['sadness', 'neutral'])):
                passed = False
                errors.append(f"Emotion mismatch: got {emotion}, expected {test['expected_emotion']}")
        
        if 'expected_min_confidence' in test and confidence < test['expected_min_confidence']:
            passed = False
            errors.append(f"Confidence too low: {confidence:.0%} < {test['expected_min_confidence']:.0%}")
        
        if 'expected_max_confidence' in test and confidence > test['expected_max_confidence']:
            passed = False
            errors.append(f"Confidence too high: {confidence:.0%} > {test['expected_max_confidence']:.0%}")
        
        status = "[PASS]" if passed else "[FAIL]"
        print(f"\nResult: {status}")
        if errors:
            for error in errors:
                print(f"  - {error}")
        
        results.append({
            'id': test['id'],
            'passed': passed,
            'emotion': emotion,
            'confidence': confidence
        })
    
    # Summary
    print(f"\n{'='*80}")
    print("SUMMARY")
    print(f"{'='*80}")
    passed_count = sum(1 for r in results if r['passed'])
    total_count = len(results)
    print(f"Passed: {passed_count}/{total_count}")
    
    for r in results:
        status = "[PASS]" if r['passed'] else "[FAIL]"
        print(f"{status} Test {r['id']}: {r['emotion']} ({r['confidence']:.0%})")
    
    print(f"{'='*80}\n")
    
    return passed_count == total_count

if __name__ == '__main__':
    success = test_emotion_classification()
    exit(0 if success else 1)
