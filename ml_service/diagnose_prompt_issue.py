#!/usr/bin/env python3
"""
Test different prompt sizes and content to find what causes timeout.

From previous tests:
- Very short prompt: 6.71s ✓
- 1328 char prompt: TIMEOUT at 6.71s
- 2638 char prompt: TIMEOUT at 6.71s

Hypothesis: The prompt content itself (JSON extraction request) causes Ollama to take longer
"""

import requests
import time

print("=" * 70)
print("PROMPT CONTENT/SIZE DIAGNOSTIC")
print("=" * 70)
print()

# ============================================================================
# TEST 1: Progressively larger prompts
# ============================================================================

test_cases = [
    ("Very short", "Say hello"),
    ("Short", "Respond in one sentence: What is your name?"),
    ("Medium", """You are a helpful assistant. 
User message: "My friends surprised me today and it made my whole day."
Respond naturally."""),
    ("Large - JSON extraction", """You are analyzing a message to understand its structure.
User's message: "My friends surprised me today and it made my whole day."
Analyze and provide JSON with: situation, topic, intent, emotional_meaning, important_details, sarcasm_or_figurative, is_question, severity_indicator.
Respond ONLY with valid JSON."""),
    ("Very Large - Full context", """SYSTEM PROMPT WITH FULL CONTEXT

MESSAGE UNDERSTANDING & CONTEXT
✓ STRUCTURED UNDERSTANDING (from Ollama analysis):
  Situation: User's friends surprised them
  Topic: relationships
  User intent: sharing_news
  Emotional meaning: joy and appreciation
  Key details: ['friends surprised', 'made whole day']
  Sarcasm: False
  Is question: False
  Severity: low

DETECTED EMOTION: HAPPY (85% confidence)
Response tone: Be upbeat and positive.

RELEVANT BACKGROUND:
User enjoys spending time with friends. They value relationships.

CONVERSATION HISTORY:
Previous: How are you?
AI: I'm here to listen.

CURRENT MESSAGE:
"My friends surprised me today and it made my whole day."

YOUR RESPONSE:
Be upbeat and positive. Share in their joy. Respond in 1-2 sentences naturally.
Respond now:"""),
]

for test_name, prompt in test_cases:
    print(f"[TEST] {test_name} (prompt size: {len(prompt)} chars)")
    print("-" * 70)
    
    try:
        start = time.time()
        response = requests.post(
            "http://localhost:11434/api/generate",
            json={
                "model": "neural-chat",
                "prompt": prompt,
                "stream": False,
                "temperature": 0.75,
                "top_p": 0.9,
            },
            timeout=(5, 15),  # 15 second timeout
        )
        elapsed = time.time() - start
        print(f"✓ COMPLETED in {elapsed:.2f}s")
        print(f"  Status: {response.status_code}")
        print(f"  Response length: {len(response.json().get('response', ''))} chars")
        
    except requests.exceptions.Timeout as e:
        elapsed = time.time() - start
        print(f"✗ TIMEOUT after {elapsed:.2f}s")
        print(f"  Error: {str(e)[:80]}")
        
    except Exception as e:
        print(f"✗ ERROR: {e}")
    
    print()

print("=" * 70)
print("OBSERVATIONS:")
print("=" * 70)
print()
print("If timeouts increase with prompt size → Ollama is too slow for large prompts")
print("If timeouts are inconsistent → Ollama queuing/concurrency issue")
print("If all succeed → Something else is wrong in the Flask pipeline")
print()
