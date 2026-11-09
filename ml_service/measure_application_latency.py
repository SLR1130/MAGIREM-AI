#!/usr/bin/env python3
"""
Measure actual /chat endpoint latency to compare with direct Ollama test

This will show us where the 30+ second timeout is actually occurring.
"""

import requests
import time
import json

print("=" * 80)
print("APPLICATION /CHAT ENDPOINT LATENCY TEST")
print("=" * 80)
print()

TEST_MESSAGE = "Bro, my friends surprised me after class today. We went out for food and honestly I haven't had that much fun in a while."

print("[TEST] Sending message to /chat endpoint")
print("-" * 80)
print(f"Message: {TEST_MESSAGE}")
print()

payload = {
    "message": TEST_MESSAGE,
    "user_id": 1,
    "conversation_history": [],
}

try:
    start_time = time.time()
    
    response = requests.post(
        "http://127.0.0.1:5000/chat",
        json=payload,
        timeout=60
    )
    
    elapsed = time.time() - start_time
    
    print(f"Time elapsed: {elapsed:.2f} seconds")
    print(f"HTTP Status: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"✓ SUCCESS")
        print()
        print("Response details:")
        print(f"  Emotion: {data.get('emotion')}")
        print(f"  Confidence: {data.get('confidence', 0):.0%}")
        print(f"  Response length: {len(data.get('response', ''))} chars")
        print(f"  Response: {data.get('response', '')[:150]}...")
        print(f"  ML System: {data.get('ml_system', 'unknown')}")
        
        print()
        print("COMPARISON WITH DIRECT OLLAMA TEST:")
        print("-" * 80)
        print(f"Direct Ollama test (bypassing Flask): 23.91 seconds")
        print(f"Application /chat endpoint: {elapsed:.2f} seconds")
        print(f"Difference: {elapsed - 23.91:.2f} seconds")
        print(f"Overhead: {(elapsed - 23.91) / 23.91 * 100:.1f}%")
        
        if elapsed > 30:
            print()
            print(f"⚠️ Application took {elapsed:.2f}s - would timeout with 30s limit!")
            print()
            print("The extra time ({:.2f}s) is spent on:".format(elapsed - 23.91))
            print("  • Semantic analysis")
            print("  • Memory retrieval")
            print("  • Context classification")
            print("  • Flask request handling")
            print("  • Response processing")
            print("  • NOT the Ollama request itself (23.91s is fine)")
        else:
            print()
            print(f"✓ Application completed in {elapsed:.2f}s (within 30s timeout)")
            print()
            print("Conclusion: If application is timing out, it's due to:")
            print("  • Pre-Ollama overhead")
            print("  • Multiple requests queuing up")
            print("  • Memory/database slowness")
    else:
        print(f"✗ HTTP Error {response.status_code}")
        print(f"Response: {response.text[:200]}")
        
except requests.Timeout:
    print(f"✗ REQUEST TIMED OUT after 60 seconds")
    print()
    print("This means the /chat endpoint is taking >60 seconds")
    print("Much worse than the 30s read timeout")
    
except Exception as e:
    print(f"✗ ERROR: {type(e).__name__}: {e}")

print()
print("=" * 80)
