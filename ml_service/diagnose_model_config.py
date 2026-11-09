#!/usr/bin/env python3
"""
Diagnose whether the issue is:
1. neural-chat model itself is slow with complex prompts
2. Ollama configuration (context window, num_predict, etc)
3. System resource constraints

Check model info via Ollama API
"""

import requests
import json
import time

print("=" * 70)
print("MODEL & CONFIGURATION DIAGNOSTIC")
print("=" * 70)
print()

# ============================================================================
# TEST 1: Get model info
# ============================================================================
print("[TEST 1] Get Model Information")
print("-" * 70)

try:
    response = requests.get("http://localhost:11434/api/tags")
    if response.status_code == 200:
        data = response.json()
        models = data.get('models', [])
        
        for model in models:
            name = model.get('name')
            size = model.get('size', 0) / (1024**3)  # Convert to GB
            modified = model.get('modified_at')
            
            print(f"Model: {name}")
            print(f"  Size: {size:.2f} GB")
            print(f"  Modified: {modified}")
            print(f"  Details: {json.dumps(model, indent=4)}")
            print()
            
except Exception as e:
    print(f"✗ Error getting model info: {e}")

print()

# ============================================================================
# TEST 2: Send a prompt with explicit parameters to see defaults
# ============================================================================
print("[TEST 2] Check Request Parameters & Ollama Response Headers")
print("-" * 70)

prompt = "Analyze this: User said 'I'm happy today'. What is the emotional tone?"

try:
    start = time.time()
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": prompt,
            "stream": False,
            # Explicitly set these to see if they matter
            "temperature": 0.7,
            "top_p": 0.95,
            "top_k": 40,
            "repeat_penalty": 1.1,
            "repeat_last_n": 64,
            "num_predict": 128,  # Limit output tokens
            "top_k": 40,
        },
        timeout=(5, 30),  # Longer timeout to see if it completes
    )
    elapsed = time.time() - start
    
    if response.status_code == 200:
        print(f"✓ Request completed in {elapsed:.2f}s")
        data = response.json()
        
        print(f"\nResponse fields:")
        print(f"  - response: {len(data.get('response', ''))} chars")
        print(f"  - model: {data.get('model')}")
        print(f"  - created_at: {data.get('created_at')}")
        print(f"  - done: {data.get('done')}")
        print(f"  - context: {len(data.get('context', []))} tokens")
        print(f"  - total_duration: {data.get('total_duration')} ns")
        print(f"  - load_duration: {data.get('load_duration')} ns")
        print(f"  - prompt_eval_count: {data.get('prompt_eval_count')}")
        print(f"  - prompt_eval_duration: {data.get('prompt_eval_duration')} ns")
        print(f"  - eval_count: {data.get('eval_count')}")
        print(f"  - eval_duration: {data.get('eval_duration')} ns")
        
        # Calculate speed
        if data.get('eval_duration') and data.get('eval_count'):
            tokens_per_sec = data['eval_count'] / (data['eval_duration'] / 1e9)
            print(f"\n  Speed: {tokens_per_sec:.1f} tokens/second")
            
        print(f"\nFirst 200 chars of response:")
        print(f"  {data.get('response', '')[:200]}")
        
    else:
        print(f"✗ Status code: {response.status_code}")
        
except requests.exceptions.Timeout as e:
    elapsed = time.time() - start
    print(f"✗ TIMEOUT after {elapsed:.2f}s: {e}")
    
except Exception as e:
    print(f"✗ Error: {e}")

print()

# ============================================================================
# TEST 3: Check if adding num_predict helps
# ============================================================================
print("[TEST 3] Test with Limited Output (num_predict=50)")
print("-" * 70)

prompt = "Analyze this: User said 'I'm happy today'. What is the emotional tone?"

try:
    start = time.time()
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": prompt,
            "stream": False,
            "num_predict": 50,  # Strictly limit output
        },
        timeout=(5, 20),
    )
    elapsed = time.time() - start
    
    if response.status_code == 200:
        print(f"✓ Completed in {elapsed:.2f}s with num_predict=50")
        data = response.json()
        print(f"  Output tokens: {data.get('eval_count')}")
        print(f"  Response: {data.get('response', '')[:150]}")
    else:
        print(f"✗ Status: {response.status_code}")
        
except requests.exceptions.Timeout as e:
    elapsed = time.time() - start
    print(f"✗ TIMEOUT after {elapsed:.2f}s")
    
except Exception as e:
    print(f"✗ Error: {e}")

print()
print("=" * 70)
print("INTERPRETATION:")
print("=" * 70)
print("If model info shows num_ctx=2048, that's fine for our prompts")
print("If response is slow for analysis prompts but not simple ones,")
print("  then neural-chat may not be optimized for the instruction type")
print()
