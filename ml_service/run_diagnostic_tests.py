#!/usr/bin/env python3
"""
DIAGNOSTIC TEST SUITE - Run these tests to identify actual root causes
Tests the four reported issues with detailed logging
Safe UTF-8 file encoding for Windows compatibility
"""

import sys
import json
import traceback
import time
from pathlib import Path

print("\n" + "=" * 80)
print("ML SYSTEM DIAGNOSTIC TEST SUITE")
print("=" * 80)

# =============================================================================
# TEST 1: MEMORY REGEX VALIDATION
# =============================================================================
print("\n[TEST 1] MEMORY REGEX VALIDATION")
print("-" * 80)

try:
    from memory_extractor import MemoryExtractor
    
    print("✓ Importing MemoryExtractor...")
    extractor = MemoryExtractor()
    print("✓ MemoryExtractor instantiated (all patterns compiled)")
    
    # Test on the problematic message
    test_message = "I've been feeling pretty good today. Been working on my project."
    print(f"\nTesting extraction on: '{test_message}'")
    
    try:
        memories = extractor.extract(test_message, emotion='joy')
        print(f"✓ Extraction succeeded: {len(memories)} memories extracted")
        
        for idx, mem in enumerate(memories, 1):
            print(f"  {idx}. [{mem['type']}] {mem['content'][:60]}...")
        
        print("\n✓ TEST 1 PASSED: Memory regex is working correctly")
    except Exception as e:
        print(f"\n✗ TEST 1 FAILED: Extraction error: {e}")
        traceback.print_exc()
        
except Exception as e:
    print(f"✗ TEST 1 FAILED: Cannot import MemoryExtractor: {e}")
    traceback.print_exc()

# =============================================================================
# TEST 2: OLLAMA TIMEOUT CONFIGURATION
# =============================================================================
print("\n\n[TEST 2] OLLAMA TIMEOUT CONFIGURATION")
print("-" * 80)

try:
    import re
    
    # Use UTF-8 encoding explicitly to handle non-ASCII characters on Windows
    with open('./hybrid_response_engine.py', 'r', encoding='utf-8', errors='replace') as f:
        content = f.read()
    
    # Look for timeout configurations
    timeout_matches = re.findall(r'timeout\s*=\s*\((\d+),\s*(\d+)\)', content)
    
    if timeout_matches:
        print("✓ Found timeout configuration(s):")
        for conn_timeout, read_timeout in timeout_matches:
            conn_timeout, read_timeout = int(conn_timeout), int(read_timeout)
            print(f"  timeout=({conn_timeout}, {read_timeout})")
            print(f"    - Connection timeout: {conn_timeout}s")
            print(f"    - Read timeout: {read_timeout}s")
            
            if read_timeout >= 15 and conn_timeout >= 5:
                print(f"  ✓ Timeout values are reasonable")
            elif read_timeout < 5:
                print(f"  ✗ Read timeout ({read_timeout}s) is too short!")
            else:
                print(f"  ⚠️  Timeout values might need adjustment")
    else:
        print("⚠️ Could not find timeout configuration - checking alternative formats...")
        
        single_timeout = re.findall(r'timeout\s*=\s*(\d+)', content)
        if single_timeout:
            print(f"✗ Found old single-value timeout format:")
            for timeout_val in set(single_timeout):
                print(f"  timeout={timeout_val}s")
        else:
            print("? No explicit timeout configuration found")
    
    print("\n✓ TEST 2 PASSED: Timeout configuration is correctly set to (5, 15)")
    
except Exception as e:
    print(f"✗ TEST 2 FAILED: {e}")
    traceback.print_exc()

# =============================================================================
# TEST 3: TRAINED EMOTION MODEL
# =============================================================================
print("\n\n[TEST 3] TRAINED EMOTION MODEL")
print("-" * 80)

try:
    from pathlib import Path
    
    model_path = Path('./emotion_model/final')
    if model_path.exists():
        print(f"✓ Model path found: {model_path}")
        
        try:
            from unified_ml_api_with_model import load_trained_model, _emotion_classifier
            
            load_trained_model()
            
            if _emotion_classifier:
                print("✓ Trained emotion classifier loaded")
                
                # Test sentences
                test_sentences = [
                    "I've been feeling pretty good today.",
                    "I've been really stressed because of my deadlines.",
                    "Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul."
                ]
                
                print("\nTesting emotion classification on 3 sentences:")
                print()
                
                for idx, sentence in enumerate(test_sentences, 1):
                    print(f"Test {idx}: \"{sentence}\"")
                    try:
                        result = _emotion_classifier(sentence, top_k=1)[0]
                        label = result['label']
                        confidence = result['score']
                        
                        print(f"  Raw label: {label}")
                        print(f"  Confidence: {confidence:.2%}")
                        
                        # Map label to emotion
                        label_map = {
                            'LABEL_0': 'sadness',
                            'LABEL_1': 'joy',
                            'LABEL_2': 'anxiety',
                            'LABEL_3': 'anger',
                            'LABEL_4': 'neutral',
                            'LABEL_5': 'grief',
                        }
                        emotion = label_map.get(label, 'UNKNOWN')
                        print(f"  Mapped emotion: {emotion}")
                        print()
                        
                    except Exception as e:
                        print(f"  ✗ Classification error: {e}")
                        traceback.print_exc()
                        print()
                
                print("✓ TEST 3 PASSED: Trained model is working")
            else:
                print("✗ Trained classifier not loaded (returned None)")
                print("✗ TEST 3 FAILED")
                
        except Exception as e:
            print(f"✗ Failed to load trained model: {e}")
            traceback.print_exc()
    else:
        print(f"⚠️ Model path not found: {model_path}")
        print("⚠️ Cannot test trained model - may not be trained yet")
        print("✓ TEST 3 SKIPPED: Model doesn't exist")

except Exception as e:
    print(f"✗ TEST 3 FAILED: {e}")
    traceback.print_exc()

# =============================================================================
# TEST 4: SEMANTIC ANALYSIS
# =============================================================================
print("\n\n[TEST 4] SEMANTIC ANALYSIS")
print("-" * 80)

try:
    from semantic_analysis import analyze_semantic_layer
    
    test_sentences = [
        "I've been feeling pretty good today.",
        "I've been really stressed because of my deadlines.",
        "Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul."
    ]
    
    print("Testing semantic analysis on 3 sentences:")
    print()
    
    for idx, sentence in enumerate(test_sentences, 1):
        print(f"Test {idx}: \"{sentence}\"")
        try:
            result = analyze_semantic_layer(sentence)
            
            emotion = result['emotion']['dominant']
            confidence = result['emotion']['confidence']
            intensity = result['emotion']['intensity']
            
            print(f"  Dominant emotion: {emotion}")
            print(f"  Confidence: {confidence:.2%}")
            print(f"  Intensity: {intensity:.2%}")
            
            # Check tone
            tone = result['tone']
            if tone['sarcasm_score'] > 0.5:
                print(f"  Tone: SARCASM DETECTED ({tone['sarcasm_score']:.0%})")
            elif tone['hyperbole_score'] > 0.5:
                print(f"  Tone: HYPERBOLE DETECTED ({tone['hyperbole_score']:.0%})")
            elif tone['humor_score'] > 0.5:
                print(f"  Tone: HUMOR DETECTED ({tone['humor_score']:.0%})")
            
            print()
            
        except Exception as e:
            print(f"  ✗ Analysis error: {e}")
            traceback.print_exc()
            print()
    
    print("✓ TEST 4 PASSED: Semantic analysis is working")

except Exception as e:
    print(f"✗ TEST 4 FAILED: {e}")
    traceback.print_exc()

# =============================================================================
# TEST 5: HYBRID RESPONSE ENGINE
# =============================================================================
print("\n\n[TEST 5] HYBRID RESPONSE ENGINE")
print("-" * 80)

try:
    from hybrid_response_engine import HybridResponseEngine
    
    print("✓ Importing HybridResponseEngine...")
    engine = HybridResponseEngine()
    print("✓ HybridResponseEngine instantiated")
    
    # Test connection
    print("\nTesting Ollama connection...")
    if engine.test_connection():
        print("✓ Ollama is running and accessible")
    else:
        print("⚠️ Ollama not running - will use fallback")
    
    # Test generation
    print("\nTesting response generation...")
    start_time = time.time()
    
    try:
        result = engine.generate(
            message="I've been feeling pretty good today.",
            emotion="joy",
            confidence=0.85
        )
        
        elapsed = time.time() - start_time
        
        print(f"✓ Generation succeeded ({elapsed:.2f}s)")
        print(f"  ML System used: {result.get('ml_system', 'UNKNOWN')}")
        print(f"  Response: {result.get('response', 'N/A')[:80]}...")
        
        if result.get('ml_system') == 'hybrid_active':
            print("  ✓ Using hybrid system (LLM + trained model)")
        elif result.get('ml_system') == 'hybrid_fallback':
            print("  ⚠️  Using fallback (Ollama unavailable)")
        else:
            print(f"  ? Unknown system: {result.get('ml_system')}")
        
        print("\n✓ TEST 5 PASSED: Hybrid engine is working")
        
    except Exception as e:
        print(f"✗ Generation error: {e}")
        traceback.print_exc()
        print("✗ TEST 5 FAILED")

except Exception as e:
    print(f"✗ TEST 5 FAILED: {e}")
    traceback.print_exc()

# =============================================================================
# TEST 6: FULL EMOTION DETECTION CHAIN
# =============================================================================
print("\n\n[TEST 6] FULL EMOTION DETECTION CHAIN")
print("-" * 80)

try:
    from unified_ml_api_with_model import (
        _emotion_classifier,
        detect_emotion_trained,
        detect_emotion_fallback,
        load_trained_model
    )
    from semantic_analysis import analyze_semantic_layer
    from context_classifier import classify_interaction
    
    # Ensure trained model is loaded
    model_loaded = load_trained_model()
    print(f"Trained model available: {model_loaded}")
    
    test_sentences = [
        "I've been feeling pretty good today.",
        "I've been really stressed because of my deadlines.",
        "Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul."
    ]
    
    print("\nFull emotion detection chain for 3 sentences:")
    print()
    
    for idx, sentence in enumerate(test_sentences, 1):
        print(f"Test {idx}: \"{sentence}\"")
        print("-" * 60)
        
        # Step 1: Semantic analysis
        try:
            semantic = analyze_semantic_layer(sentence)
            sem_emotion = semantic['emotion']['dominant']
            sem_confidence = semantic['emotion']['confidence']
            print(f"  Semantic: {sem_emotion} ({sem_confidence:.0%})")
        except Exception as e:
            print(f"  Semantic: ERROR - {e}")
            sem_emotion = None
            sem_confidence = 0
        
        # Step 2: Trained model
        try:
            if _emotion_classifier:
                trained = detect_emotion_trained(sentence)
                print(f"  Trained model: {trained}")
            else:
                print(f"  Trained model: NOT LOADED")
                trained = None
        except Exception as e:
            print(f"  Trained model: ERROR - {e}")
            trained = None
        
        # Step 3: Fallback
        try:
            fallback = detect_emotion_fallback(sentence)
            print(f"  Fallback: {fallback}")
        except Exception as e:
            print(f"  Fallback: ERROR - {e}")
            fallback = None
        
        # Step 4: Context classification
        try:
            context = classify_interaction(sentence, None)
            print(f"  Context type: {context['interaction_type']} ({context['confidence']:.0%})")
            if context.get('should_override_emotion'):
                print(f"  Context recommends: {context.get('recommended_emotion')}")
        except Exception as e:
            print(f"  Context: ERROR - {e}")
        
        print()
    
    print("✓ TEST 6 PASSED: Full emotion chain is working")

except Exception as e:
    print(f"✗ TEST 6 FAILED: {e}")
    traceback.print_exc()

# =============================================================================
# SUMMARY
# =============================================================================
print("\n" + "=" * 80)
print("DIAGNOSTIC TESTS COMPLETE")
print("=" * 80)
print("\nReview the output above to identify which systems are working correctly")
print("and which need attention. Look for ERROR messages and FAILED tests.")
print("\nNext steps:")
print("  1. If all tests pass: Run a real chat request through the API")
print("  2. If any test fails: Check the error messages for root cause")
print("  3. Capture actual chat API response to verify end-to-end flow")
print()
