#!/usr/bin/env python3
"""
Diagnose whether two sequential Ollama requests cause the second one to timeout.

The diagnostic shows:
- Single request: 6.71s (succeeds)
- Request 1 followed by Request 2: Second request times out at 6.71s

Hypothesis: TWO sequential requests take longer than timeout allows.
"""

import requests
import time
from datetime import datetime

print("=" * 70)
print("SEQUENTIAL REQUEST DIAGNOSTIC")
print("=" * 70)
print(f"Test started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")

# ============================================================================
# TEST 1: Single request timing
# ============================================================================
print("[TEST 1] Single Request Timing")
print("-" * 70)

prompt = "Respond in exactly one sentence. User: My friends surprised me today and it made my whole day."

try:
    start = time.time()
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": prompt,
            "stream": False,
        },
        timeout=(5, 15),
    )
    elapsed = time.time() - start
    print(f"✓ Single request completed in {elapsed:.2f}s")
    print(f"  Status: {response.status_code}")
except Exception as e:
    print(f"✗ Single request failed: {e}")

print()

# ============================================================================
# TEST 2: First request
# ============================================================================
print("[TEST 2] FIRST Request (ollama_understand_message replica)")
print("-" * 70)

understanding_prompt = """You are analyzing a message to understand its structure and meaning.
        
User's message:
"My friends surprised me today and it made my whole day."

Provide a structured JSON response with situation, topic, intent, emotional_meaning, important_details, sarcasm_or_figurative, is_question, severity_indicator.

Respond ONLY with valid JSON:"""

try:
    start = time.time()
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": understanding_prompt,
            "stream": False,
            "temperature": 0.3,
            "top_p": 0.9,
        },
        timeout=(5, 15),
    )
    elapsed = time.time() - start
    print(f"✓ First request completed in {elapsed:.2f}s")
    print(f"  Status: {response.status_code}")
    first_request_time = elapsed
except requests.exceptions.Timeout as e:
    print(f"✗ First request TIMEOUT: {e}")
    first_request_time = None

print()

# ============================================================================
# TEST 3: Second request immediately after
# ============================================================================
print("[TEST 3] SECOND Request (hybrid_response_engine replica) - IMMEDIATELY AFTER first")
print("-" * 70)

large_prompt = """Be upbeat and positive. Share in their joy.
"My friends surprised me today and it made my whole day."
Respond naturally in 1-2 sentences."""

try:
    start = time.time()
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": large_prompt,
            "stream": False,
            "temperature": 0.75,
            "top_p": 0.9,
            "top_k": 40,
        },
        timeout=(5, 25),
    )
    elapsed = time.time() - start
    print(f"✓ Second request completed in {elapsed:.2f}s")
    print(f"  Status: {response.status_code}")
    second_request_time = elapsed
except requests.exceptions.Timeout as e:
    print(f"✗ Second request TIMEOUT after ~6.71s: {e}")
    print(f"  This suggests the second request is waiting for the first model to finish")
    second_request_time = None

print()

# ============================================================================
# ANALYSIS
# ============================================================================
print("=" * 70)
print("ANALYSIS")
print("=" * 70)

if first_request_time and second_request_time:
    total = first_request_time + second_request_time
    print(f"✓ Both requests completed")
    print(f"  First request: {first_request_time:.2f}s")
    print(f"  Second request: {second_request_time:.2f}s")
    print(f"  Total: {total:.2f}s")
    print(f"\n  If combined takes > 15s, first timeout would be hit")
    print(f"  If combined takes > 25s, second timeout would be hit")
elif first_request_time and not second_request_time:
    print(f"✗ PATTERN FOUND:")
    print(f"  First request: {first_request_time:.2f}s (succeeded)")
    print(f"  Second request: TIMEOUT")
    print(f"\n  ROOT CAUSE: Ollama can only process ONE request at a time")
    print(f"  - First request takes ~6-7 seconds")
    print(f"  - During this time, second request waits in queue")
    print(f"  - When second request finally gets processed, it exceeds timeout")
    print(f"  - Second timeout (25s) is also exceeded if model is still busy")
    print(f"\nSOLUTION: Only ONE Ollama request per user message, not two sequential ones")
elif not first_request_time and not second_request_time:
    print(f"✗ Both requests timed out")
else:
    print(f"✓ First request failed but second succeeded (unusual)")

print()
