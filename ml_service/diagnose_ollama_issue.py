#!/usr/bin/env python3
"""
Minimal diagnostic test to compare:
A: ollama run neural-chat:latest (CLI - works)
B: Python requests → http://localhost:11434 (HTTP - times out)

Purpose: Identify the exact difference causing timeouts in the Flask pipeline
"""

import requests
import time
import json
import socket
from datetime import datetime

print("=" * 70)
print("OLLAMA TIMEOUT DIAGNOSTIC TEST")
print("=" * 70)
print(f"Test started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")

# ============================================================================
# TEST 1: Network connectivity check
# ============================================================================
print("[TEST 1] Network Connectivity Check")
print("-" * 70)

try:
    # Try to connect to localhost:11434
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(5)
    result = sock.connect_ex(('127.0.0.1', 11434))
    sock.close()
    
    if result == 0:
        print("✓ Port 11434 is open on 127.0.0.1")
    else:
        print("✗ Port 11434 is NOT accessible on 127.0.0.1")
    
    # Also try localhost
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(5)
    result = sock.connect_ex(('localhost', 11434))
    sock.close()
    
    if result == 0:
        print("✓ Port 11434 is open on localhost")
    else:
        print("✗ Port 11434 is NOT accessible on localhost")
        
except Exception as e:
    print(f"✗ Socket test failed: {e}")

print()

# ============================================================================
# TEST 2: Simple HTTP GET to Ollama health endpoint
# ============================================================================
print("[TEST 2] HTTP Health Check - GET /api/tags")
print("-" * 70)

try:
    start = time.time()
    response = requests.get(
        "http://localhost:11434/api/tags",
        timeout=(5, 5)
    )
    elapsed = time.time() - start
    
    print(f"✓ Request completed in {elapsed:.2f}s")
    print(f"✓ Status code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        models = data.get('models', [])
        print(f"✓ Available models: {len(models)}")
        for model in models:
            model_name = model.get('name', 'unknown')
            print(f"  - {model_name}")
    else:
        print(f"✗ Unexpected status code: {response.status_code}")
        
except requests.exceptions.Timeout as e:
    print(f"✗ TIMEOUT on health check: {e}")
except requests.exceptions.ConnectionError as e:
    print(f"✗ CONNECTION ERROR: {e}")
except Exception as e:
    print(f"✗ Error: {e}")

print()

# ============================================================================
# TEST 3: Check current Ollama processes (look for resource contention)
# ============================================================================
print("[TEST 3] Check Ollama Status via API")
print("-" * 70)

try:
    # This endpoint may not be available on all Ollama versions
    response = requests.get(
        "http://localhost:11434/api/status",
        timeout=(5, 5)
    )
    print(f"✓ API Status endpoint: {response.status_code}")
    if response.status_code == 200:
        print(f"  Response: {response.text[:200]}")
except Exception as e:
    print(f"⚠ Status endpoint not available: {e}")

print()

# ============================================================================
# TEST 4: Minimal prompt request (like the successful CLI command)
# ============================================================================
print("[TEST 4] Minimal Prompt Request - Very Short Prompt")
print("-" * 70)
print("Testing with: 'Respond in exactly one sentence.'")
print()

minimal_prompt = "Respond in exactly one sentence. User: My friends surprised me today and it made my whole day."

try:
    print("Sending request with stream=False...")
    start = time.time()
    
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": minimal_prompt,
            "stream": False,
        },
        timeout=(5, 15),  # Match the timeout from ollama_understand_message
    )
    
    elapsed = time.time() - start
    
    print(f"✓ Request completed in {elapsed:.2f}s")
    print(f"✓ Status code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        response_text = data.get('response', '')
        print(f"✓ Response length: {len(response_text)} chars")
        print(f"✓ Response: {response_text[:200]}")
    else:
        print(f"✗ Unexpected status: {response.status_code}")
        print(f"  Response body: {response.text[:200]}")
        
except requests.exceptions.Timeout as e:
    print(f"✗ TIMEOUT after {elapsed:.2f}s: {e}")
    print(f"  Timeout type: {type(e).__name__}")
except requests.exceptions.ConnectionError as e:
    print(f"✗ CONNECTION ERROR: {e}")
except Exception as e:
    print(f"✗ Error: {e}")
    print(f"  Error type: {type(e).__name__}")

print()

# ============================================================================
# TEST 5: Exact replica of ollama_understand_message request
# ============================================================================
print("[TEST 5] Replica of ollama_understand_message() Request")
print("-" * 70)

understanding_prompt = """You are analyzing a message to understand its structure and meaning.
        
User's message:
"My friends surprised me today and it made my whole day."

Analyze this message and provide a structured JSON response with:
- situation: What is happening in this message (1-2 sentences)
- topic: What is being discussed (e.g., relationship, work, school, family)
- intent: What does the user need/want (seeking_support, asking_advice, venting, asking_question, sharing_news)
- emotional_meaning: What is the actual emotional content (don't label as emotion, describe the meaning)
- important_details: Key facts or context from the message (as a list)
- sarcasm_or_figurative: True if user is being sarcastic, ironic, or using hyperbole
- is_question: True if user is asking a question
- severity_indicator: low/medium/high based on the content

Respond ONLY with valid JSON, no other text. Example format:
{
  "situation": "User is worried about an upcoming test",
  "topic": "academics",
  "intent": "seeking_support",
  "emotional_meaning": "anxious about performance and feeling unprepared",
  "important_details": ["test is tomorrow", "hasn't studied much", "worried about grade"],
  "sarcasm_or_figurative": false,
  "is_question": false,
  "severity_indicator": "low"
}

Now analyze this message and respond with ONLY the JSON:"""

print(f"Prompt size: {len(understanding_prompt)} chars")
print()

try:
    print("Sending ollama_understand_message-like request...")
    print(f"Timeout: (5, 15) - connection 5s, read 15s")
    print()
    
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
        timeout=(5, 15),  # Exact timeout from ollama_understand_message
    )
    
    elapsed = time.time() - start
    
    print(f"✓ Request completed in {elapsed:.2f}s")
    print(f"✓ Status code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        response_text = data.get('response', '')
        print(f"✓ Response length: {len(response_text)} chars")
        print(f"✓ First 300 chars: {response_text[:300]}")
        
        # Try to parse as JSON
        try:
            json_data = json.loads(response_text)
            print(f"✓ Valid JSON with keys: {list(json_data.keys())}")
        except json.JSONDecodeError:
            print(f"⚠ Response is not valid JSON")
    else:
        print(f"✗ Unexpected status: {response.status_code}")
        print(f"  Response: {response.text[:300]}")
        
except requests.exceptions.Timeout as e:
    print(f"✗ TIMEOUT after {elapsed:.2f}s: {e}")
    print(f"  This is the problem we're experiencing in the Flask pipeline")
    print(f"  Timeout type: {type(e).__name__}")
except requests.exceptions.ConnectionError as e:
    print(f"✗ CONNECTION ERROR: {e}")
except Exception as e:
    print(f"✗ Error: {e}")
    print(f"  Error type: {type(e).__name__}")

print()

# ============================================================================
# TEST 6: Exact replica of hybrid_response_engine generate() request
# ============================================================================
print("[TEST 6] Replica of hybrid_response_engine.generate() Request")
print("-" * 70)

large_prompt = """You are a supportive AI counselor helping a student with emotional wellbeing.

============================================================
MESSAGE UNDERSTANDING & CONTEXT
============================================================
✓ STRUCTURED UNDERSTANDING (from Ollama analysis):
  Situation: User's friends surprised them with something positive
  Topic: relationships
  User intent: sharing_news
  Emotional meaning: joy and appreciation for friends
  Key details: ['friends surprised', 'made whole day']
  User is NOT being sarcastic or using figurative language
  User is NOT asking a question
  Content severity: low

DETECTED EMOTION (for additional context):
✓ Emotion: HAPPY
✓ Confidence: 85% (HIGH)
✓ Intensity: 70% (MODERATE)
Response tone: Be upbeat and positive. Share in their joy.

============================================================
RELEVANT BACKGROUND (from past conversations)
============================================================
User enjoys spending time with friends. Previous messages show they value personal relationships and appreciate when people remember them.

============================================================
CONVERSATION SO FAR (context only - current message is priority)
============================================================
⚠️ NOTE: Previous message provides context, but THIS CURRENT MESSAGE is the primary focus.
User's emotions may have SHIFTED from previous messages.
Do NOT assume current emotion matches previous tone.

Previous Exchange:
  User: How are you?
  AI: I'm here and ready to chat whenever you need support.

============================================================
WHAT THE USER JUST SAID (PRIMARY FOCUS)
============================================================
"My friends surprised me today and it made my whole day."

============================================================
YOUR RESPONSE
============================================================
Be upbeat and positive. Share in their joy.

IMPORTANT INSTRUCTIONS:
1. Respond to the USER'S CURRENT MESSAGE, not just their emotion label.
2. Be SPECIFIC to what they just said - not generic support phrases.
3. Use detected emotion as CONTEXT, not the entire meaning.
4. Understand what is HAPPENING in their situation before responding.
5. Reference concrete details from their message when natural.
6. Ask specific rather than generic follow-up questions.
7. Don't diagnose or provide therapy - just respond naturally.
8. Match their sentiment and tone exactly.
9. Keep it natural and conversational (1-2 sentences).
10. DO NOT be overly therapeutic or use clichés.

Respond now:"""

print(f"Prompt size: {len(large_prompt)} chars")
print()

try:
    print("Sending hybrid_response_engine-like request...")
    print(f"Timeout: (5, 25) - connection 5s, read 25s")
    print()
    
    start = time.time()
    
    response = requests.post(
        "http://localhost:11434/api/generate",
        json={
            "model": "neural-chat",
            "prompt": large_prompt,
            "stream": False,  # Now testing with stream=False
            "temperature": 0.75,
            "top_p": 0.9,
            "top_k": 40,
        },
        timeout=(5, 25),  # Exact timeout from hybrid_response_engine
        stream=False
    )
    
    elapsed = time.time() - start
    
    print(f"✓ Request completed in {elapsed:.2f}s")
    print(f"✓ Status code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        response_text = data.get('response', '')
        print(f"✓ Response length: {len(response_text)} chars")
        print(f"✓ Response: {response_text[:300]}")
    else:
        print(f"✗ Unexpected status: {response.status_code}")
        print(f"  Response: {response.text[:300]}")
        
except requests.exceptions.Timeout as e:
    print(f"✗ TIMEOUT after {elapsed:.2f}s: {e}")
    print(f"  Timeout type: {type(e).__name__}")
except requests.exceptions.ConnectionError as e:
    print(f"✗ CONNECTION ERROR: {e}")
except Exception as e:
    print(f"✗ Error: {e}")
    print(f"  Error type: {type(e).__name__}")

print()

# ============================================================================
# TEST 7: Check for environment variable issues
# ============================================================================
print("[TEST 7] Environment Variable Check")
print("-" * 70)

import os

proxy_vars = ['HTTP_PROXY', 'HTTPS_PROXY', 'http_proxy', 'https_proxy', 'NO_PROXY', 'no_proxy']
for var in proxy_vars:
    value = os.environ.get(var)
    if value:
        print(f"⚠ {var}={value}")
    else:
        print(f"✓ {var} not set")

print()

# ============================================================================
# SUMMARY
# ============================================================================
print("=" * 70)
print("DIAGNOSTIC TEST COMPLETE")
print("=" * 70)
print("\nCheck the results above to identify:")
print("A. Python CAN communicate with Ollama correctly")
print("B. Python communicates but requests are much slower than CLI")
print("C. Ollama API inference is too slow under current workload")
print("D. Another configuration/process issue exists")
print()
