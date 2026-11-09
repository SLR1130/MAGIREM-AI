#!/usr/bin/env python
"""
Validation script for context classifier
Tests all major functionality to ensure production readiness
"""

import sys
sys.path.insert(0, '.')

def run_validation():
    # Verify imports work
    print('[1] Testing imports...')
    try:
        from context_classifier import classify_interaction, get_classifier
        print('    OK: context_classifier imports successful')
    except Exception as e:
        print(f'    FAIL: context_classifier - {e}')
        return False

    # Test classifier instantiation
    print('[2] Testing classifier instantiation...')
    try:
        classifier = get_classifier()
        print('    OK: Classifier instantiated')
    except Exception as e:
        print(f'    FAIL: Classifier - {e}')
        return False

    # Test basic classification
    print('[3] Testing CASUAL classification...')
    try:
        result = classify_interaction('yo whats up', None, 'neutral', 0.5)
        assert result['interaction_type'] == 'CASUAL', f"Expected CASUAL, got {result['interaction_type']}"
        print(f"    OK: {result['interaction_type']} (conf: {result['confidence']:.0%})")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test crisis detection
    print('[4] Testing CRISIS override...')
    try:
        result = classify_interaction('lol I want to kill myself', None, 'neutral', 0.5)
        assert result['interaction_type'] == 'CRISIS', f"Expected CRISIS, got {result['interaction_type']}"
        print(f"    OK: {result['interaction_type']}")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test playful detection
    print('[5] Testing PLAYFUL detection...')
    try:
        result = classify_interaction('nah bro I am just kidding', None, 'neutral', 0.5)
        assert result['interaction_type'] == 'PLAYFUL', f"Expected PLAYFUL, got {result['interaction_type']}"
        print(f"    OK: {result['interaction_type']} (conf: {result['confidence']:.0%})")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test emotional detection
    print('[6] Testing EMOTIONAL detection...')
    try:
        result = classify_interaction('I have been feeling really depressed', None, 'sadness', 0.8)
        assert result['interaction_type'] == 'EMOTIONAL', f"Expected EMOTIONAL, got {result['interaction_type']}"
        print(f"    OK: {result['interaction_type']} (conf: {result['confidence']:.0%})")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test support-seeking
    print('[7] Testing SUPPORT_SEEKING detection...')
    try:
        result = classify_interaction('can I talk to you about something?', None, 'neutral', 0.5)
        assert result['interaction_type'] == 'SUPPORT_SEEKING', f"Expected SUPPORT_SEEKING, got {result['interaction_type']}"
        print(f"    OK: {result['interaction_type']} (conf: {result['confidence']:.0%})")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test emotion override
    print('[8] Testing emotion override on CASUAL...')
    try:
        result = classify_interaction('yo what is up', None, 'sadness', 0.9)
        assert result['should_override_emotion'] == True, "Should override emotion on CASUAL"
        assert result['recommended_emotion'] == 'neutral', f"Should recommend neutral, got {result['recommended_emotion']}"
        print(f"    OK: Override emotion from sadness to neutral")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    # Test user correction
    print('[9] Testing user correction detection...')
    try:
        result = classify_interaction('I am just messing with you', None, 'neutral', 0.5)
        assert result['user_correction'] == True, "Should detect user correction"
        print(f"    OK: User correction detected")
    except Exception as e:
        print(f'    FAIL: {e}')
        return False

    print()
    print('='*60)
    print('SUCCESS: All validation checks passed!')
    print('='*60)
    print()
    print('Context classifier is ready for production.')
    return True

if __name__ == '__main__':
    success = run_validation()
    sys.exit(0 if success else 1)
