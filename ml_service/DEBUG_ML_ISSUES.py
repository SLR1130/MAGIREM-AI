#!/usr/bin/env python3

"""
DEBUG_ML_ISSUES.py
Comprehensive diagnostic script for investigating 4 ML/AI issues:

ISSUE 1: Emotion Classification - Why semantic happiness becomes neutral
ISSUE 2: Ollama Timeout - Read timeout=3 is too short
ISSUE 3: HYBRID_SUCCESS vs TEMPLATE_FALLBACK contradiction
ISSUE 4: Memory Regex Error - "missing ), unterminated subpattern"

Test message: "I've been feeling pretty good today...."
"""

import sys
import json
import re
from pathlib import Path

print("\n" + "="*70)
print("ML SYSTEM DIAGNOSTIC")
print("="*70 + "\n")

# ============================================================================
# ISSUE 4: MEMORY REGEX ERROR - Test regex patterns first
# ============================================================================
print("[ISSUE 4] MEMORY REGEX VALIDATION")
print("-" * 70)

try:
    from memory_extractor import MemoryExtractor
    
    print("✓ Importing MemoryExtractor...")
    extractor = MemoryExtractor()
    print("✓ MemoryExtractor instantiated successfully (patterns compiled)")
    
    # Test memory extraction on the message
    test_message = "I've been feeling pretty good today. Been working on my project and making progress."
    print(f"\nTesting memory extraction on: '{test_message}'")
    
    try:
        memories = extractor.extract(test_message, emotion='joy')
        print(f"✓ Memory extraction completed successfully")
        print(f"  Extracted {len(memories)} memories:")
        for i, mem in enumerate(memories, 1):
            print(f"    {i}. {mem['type']}: {mem['content'][:50]}...")
    except Exception as e:
        print(f"✗ Memory extraction failed: {e}")
        import traceback
        traceback.print_exc()
        
except Exception as e:
    print(f"✗ Failed to import/use MemoryExtractor: {e}")
    import traceback
    traceback.print_exc()

# ============================================================================
# ISSUE 2: OLLAMA TIMEOUT - Check timeout configuration
# ============================================================================
print("\n[ISSUE 2] OLLAMA TIMEOUT CONFIGURATION")
print("-" * 70)

# Check hybrid_response_engine.py for timeout values
print("Checking hybrid_response_engine.py for Ollama timeout settings...")

try:
    with open('hybrid_response_engine.py', 'r') as f:
        content = f.read()
    
    # Find timeout settings
    timeout_matches = re.findall(r'timeout=?[:\s]*\(?\s*(\d+)\s*,?\s*(\d+)?\)?', content)
    
    if timeout_matches:
        print(f"✓ Found timeout configurations:")
        for match in timeout_matches:
            if len(match) == 2 and match[1]:
                conn_timeout, read_timeout = int(match[0]), int(match[1])
                print(f"  timeout=({conn_timeout}, {read_timeout})")
                print(f"    - Connection timeout: {conn_timeout}s")
                print(f"    - Read timeout: {read_timeout}s")
                if read_timeout <= 3:
                    print(f"    ✗ READ TIMEOUT IS TOO SHORT ({read_timeout}s)")
                    print(f"      Ollama model loading can take 5-15s on first request")
                else:
                    print(f"    ✓ Read timeout seems reasonable")
            else:
                print(f"  Found: {match}")
    else:
        # Try different pattern
        timeout_pattern = r"timeout\s*=\s*(\d+)"
        matches = re.findall(timeout_pattern, content)
        if matches:
            print(f"✗ Found old timeout format (single value):")
            for match in matches:
                timeout_val = int(match)
                print(f"  timeout={timeout_val}s")
                if timeout_val <= 3:
                    print(f"  ✗ TIMEOUT TOO SHORT - Ollama needs more time")
        else:
            print("? Could not find explicit timeout configuration")
    
except Exception as e:
    print(f"✗ Failed to analyze timeout config: {e}")

# ============================================================================
# ISSUE 1: EMOTION CLASSIFICATION - Test trained model detection
# ============================================================================
print("\n[ISSUE 1] EMOTION CLASSIFICATION FLOW")
print("-" * 70)

test_message = "I've been feeling pretty good today. Been working on my project and making progress."

# Step 1: Try to use trained model directly
print(f"\nTest message: '{test_message}'")
print("\n1. Testing trained emotion detection:")

try:
    from transformers import TextClassificationPipeline, AutoModelForSequenceClassification, AutoTokenizer
    import torch
    
    model_path = Path('./emotion_model/final')
    if model_path.exists():
        print(f"  ✓ Model found at {model_path}")
        
        tokenizer = AutoTokenizer.from_pretrained(model_path)
        model = AutoModelForSequenceClassification.from_pretrained(model_path)
        classifier = TextClassificationPipeline(model=model, tokenizer=tokenizer, device=-1)
        
        result = classifier(test_message, top_k=1)[0]
        label = result['label']
        confidence = result['score']
        
        print(f"  ✓ Raw model output: label={label}, confidence={confidence:.2%}")
        
        # Map label to emotion
        label_map = {
            'LABEL_0': 'sadness',
            'LABEL_1': 'joy',
            'LABEL_2': 'anxiety',
            'LABEL_3': 'anger',
            'LABEL_4': 'neutral',
            'LABEL_5': 'grief',
        }
        emotion = label_map.get(label, 'unknown')
        print(f"  ✓ Mapped to emotion: {emotion}")
        
        # Check for issues
        if emotion == 'neutral' and 'feeling pretty good' in test_message:
            print(f"  ✗ ISSUE: Message contains 'feeling pretty good' but emotion is neutral")
            print(f"     Expected: joy or happiness")
            print(f"     Got: neutral")
            print(f"     Likely cause: Semantic 'good' might not match training data")
    else:
        print(f"  ✗ Model not found at {model_path}")
        print(f"     Available models: {list(Path('./emotion_model').glob('*')) if Path('./emotion_model').exists() else 'No emotion_model directory'}")
        
except Exception as e:
    print(f"  ✗ Failed to test trained model: {e}")
    import traceback
    traceback.print_exc()

# Step 2: Test context classifier
print("\n2. Testing context classification:")

try:
    from context_classifier import ContextClassifier
    classifier = ContextClassifier()
    
    context_result = classifier.classify(
        current_message=test_message,
        conversation_history=None,
        emotion_from_model='joy',
        emotion_confidence=0.7
    )
    
    print(f"  ✓ Context classification result:")
    print(f"    - Interaction type: {context_result['interaction_type']}")
    print(f"    - Confidence: {context_result['confidence']:.0%}")
    print(f"    - Should override emotion: {context_result['should_override_emotion']}")
    if context_result['should_override_emotion']:
        print(f"    - Recommended emotion: {context_result['recommended_emotion']}")
        print(f"  ✗ ISSUE: Context classifier is overriding emotion from joy to {context_result['recommended_emotion']}")
        
except Exception as e:
    print(f"  ✗ Failed to test context classifier: {e}")
    import traceback
    traceback.print_exc()

# ============================================================================
# ISSUE 3: HYBRID_SUCCESS vs TEMPLATE_FALLBACK
# ============================================================================
print("\n[ISSUE 3] HYBRID SYSTEM vs FALLBACK RESPONSE")
print("-" * 70)

print("\nChecking hybrid_response_engine.py for contradictory logging...")

try:
    with open('hybrid_response_engine.py', 'r') as f:
        content = f.read()
    
    # Find HYBRID_SUCCESS and TEMPLATE_FALLBACK logs
    hybrid_success_logs = re.findall(r'\[HYBRID_SUCCESS\][^\n]*', content)
    template_fallback_logs = re.findall(r'\[.*FALLBACK.*\][^\n]*', content)
    
    print(f"Found {len(hybrid_success_logs)} [HYBRID_SUCCESS] log lines")
    print(f"Found {len(template_fallback_logs)} fallback-related log lines")
    
    if hybrid_success_logs and template_fallback_logs:
        print("\n✗ ISSUE DETECTED: Contradictory logging")
        print("  The code might log [HYBRID_SUCCESS] even when using fallback")
        print("  Or might use template fallback after claiming hybrid success")
    
    # Check the actual flow
    print("\nAnalyzing response generation flow:")
    if "return" in content and "[HYBRID_SUCCESS]" in content:
        print("  ✓ Found both return statements and [HYBRID_SUCCESS] log")
        
        # Look for where HYBRID_SUCCESS is logged
        lines = content.split('\n')
        for i, line in enumerate(lines):
            if '[HYBRID_SUCCESS]' in line:
                print(f"  Line {i}: {line.strip()}")
                # Check what happens after
                if i + 1 < len(lines):
                    next_line = lines[i+1].strip()
                    print(f"    Next: {next_line}")
    
except Exception as e:
    print(f"  ✗ Failed to analyze: {e}")

# ============================================================================
# UNIFIED TEST: Call the actual endpoints
# ============================================================================
print("\n[UNIFIED TEST] CALLING ML ENDPOINTS")
print("-" * 70)

print("\nStarting Python ML server in background to test endpoints...")
print("(This requires the ML service to be running)")

# Check if Flask server is running
import subprocess
import time

# Try to test the endpoints
print("\nTesting /analyze endpoint...")

try:
    import requests
    
    test_payload = {
        "message": "I've been feeling pretty good today. Been working on my project and making progress.",
        "conversation_history": []
    }
    
    # Try to call the ML API
    response = requests.post('http://127.0.0.1:5000/analyze', json=test_payload, timeout=5)
    
    if response.status_code == 200:
        result = response.json()
        print(f"✓ /analyze endpoint responded")
        print(f"  Semantic analysis:")
        semantic = result.get('semantic_analysis', {})
        emotion = semantic.get('emotion', {})
        print(f"    Emotion: {emotion.get('dominant', 'N/A')}")
        print(f"    Confidence: {emotion.get('confidence', 'N/A')}")
    else:
        print(f"✗ /analyze returned {response.status_code}")
        
except requests.exceptions.ConnectionError:
    print("✗ Cannot connect to ML server at http://127.0.0.1:5000")
    print("  Start the ML server with: cd ml_service && python unified_ml_api_with_model.py")
except Exception as e:
    print(f"✗ Error calling /analyze: {e}")

print("\nTesting /chat endpoint...")

try:
    import requests
    
    test_payload = {
        "message": "I've been feeling pretty good today. Been working on my project and making progress.",
        "user_id": "test_user",
        "conversation_history": []
    }
    
    response = requests.post('http://127.0.0.1:5000/chat', json=test_payload, timeout=10)
    
    if response.status_code == 200:
        result = response.json()
        print(f"✓ /chat endpoint responded")
        print(f"  Emotion: {result.get('emotion', 'N/A')}")
        print(f"  Confidence: {result.get('confidence', 'N/A'):.0%}")
        print(f"  Response generator: {result.get('analysis', {}).get('model_type', 'N/A')}")
        print(f"  Response (first 60 chars): {result.get('response', '')[:60]}...")
        
        # Check for contradictions
        memory_info = result.get('memory', {})
        if memory_info and 'error' in memory_info:
            print(f"\n  ✗ ISSUE 4 DETECTED: Memory storage error")
            print(f"    Error: {memory_info['error']}")
            
    else:
        print(f"✗ /chat returned {response.status_code}")
        print(f"  Response: {response.text[:200]}")
        
except requests.exceptions.Timeout:
    print("✗ Request timed out - ML server might be slow")
    print("  This could be ISSUE 2: Ollama timeout problem")
except requests.exceptions.ConnectionError:
    print("✗ Cannot connect to ML server")
except Exception as e:
    print(f"✗ Error calling /chat: {e}")

# ============================================================================
# SUMMARY
# ============================================================================
print("\n" + "="*70)
print("DIAGNOSTIC SUMMARY")
print("="*70)

print("""
ISSUES TO INVESTIGATE:

✓ ISSUE 1: Emotion Classification
  - Semantic says "happiness" but final is "neutral"
  - Check if context classifier is incorrectly overriding
  - Verify trained model is being used and not falling back

✓ ISSUE 2: Ollama Timeout
  - Current timeout=3 is too short for model loading
  - Should be timeout=(5, 15) for connection and read
  - Check hybrid_response_engine.py line ~80

✓ ISSUE 3: Hybrid Success vs Template Fallback  
  - Logs show both [HYBRID_SUCCESS] and [DEBUG5] Using TEMPLATE FALLBACK
  - Need to trace actual response path in unified_ml_api_with_model.py
  - Check if fallback is being used but marked as hybrid

✓ ISSUE 4: Memory Regex Error
  - "missing ), unterminated subpattern at position 0"
  - Check all regex patterns in memory_extractor.py
  - Verify all patterns in _compile_all_patterns() are valid

NEXT STEPS:
1. Run this script with ML server running
2. Check actual vs expected outputs
3. Review the code paths identified above
4. Fix issues without changing architecture
""")

print("\nDiagnostic complete.\n")
