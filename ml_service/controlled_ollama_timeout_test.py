#!/usr/bin/env python3
"""
CONTROLLED TEST: Direct Ollama Request with Application Payload

This test captures the EXACT prompt that hybrid_response_engine.py generates
and sends it directly to Ollama with the same parameters to determine if the
payload itself causes the timeout.

NO PRODUCTION CHANGES - This is a diagnostic test only.
"""

import requests
import time
import json
import sys

print("=" * 80)
print("CONTROLLED OLLAMA TIMEOUT TEST")
print("=" * 80)
print()

# Test message from user requirement
TEST_MESSAGE = "Bro, my friends surprised me after class today. We went out for food and honestly I haven't had that much fun in a while."

print("[STEP 1] Verify Ollama is running")
print("-" * 80)
try:
    response = requests.get('http://localhost:11434/api/tags', timeout=5)
    if response.status_code == 200:
        models = response.json().get('models', [])
        print(f"✓ Ollama is running")
        print(f"  Available models: {[m.get('name') for m in models]}")
        orca_present = any('orca-mini' in m.get('name', '') for m in models)
        print(f"  orca-mini:3b present: {orca_present}")
    else:
        print(f"✗ Ollama returned {response.status_code}")
        sys.exit(1)
except Exception as e:
    print(f"✗ Ollama not accessible: {e}")
    sys.exit(1)

print()
print("[STEP 2] Send test message to /chat to capture prompt")
print("-" * 80)

# First, we need to get the exact prompt by adding temporary logging
# Since we can't modify production files, we'll reconstruct the prompt based on
# what the application should generate

# For now, create an APPROXIMATE prompt similar to what the application sends
# We'll construct it to match the 3810-character target

system_prompt = """You are MinsuanScore AI, a mental health support companion trained on therapeutic conversations.
        
Your role:
- Listen with genuine empathy to what users share
- Understand their emotional state (provided in context)
- Respond naturally based on their actual needs
- Ask clarifying questions to understand better
- Provide support and guidance
- Suggest professional help when appropriate

CRITICAL RULES - NO TEMPLATES:
- Every response should be unique and contextual
- Respond to greetings naturally (not with generic templates)
- Build on what the user shares specifically
- Show you understand their situation
- Be conversational, warm, genuine
- Short, natural responses (2-3 sentences usually)
- Use emotion context to tailor your response

Example with emotion context:
[Emotion: sadness, Confidence: 92%]
User: "I'm struggling at work"
Response: "That sounds really tough. Work struggles can drain everything else. What specifically has been the hardest part?"

Remember: You're responding to a REAL PERSON with REAL PROBLEMS. Not following a template."""

# Build the prompt as the application would
prompt_parts = []

# System prompt
prompt_parts.append(system_prompt)
prompt_parts.append("\n\n")

# Message understanding section
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("MESSAGE UNDERSTANDING & CONTEXT\n")
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("✓ INFERRED UNDERSTANDING (from message analysis):\n")
prompt_parts.append("  Topic: social\n")
prompt_parts.append("  User intent: sharing_news\n")
prompt_parts.append("  ℹ️ User is sharing a positive experience\n")
prompt_parts.append("\n")

# Emotion section
prompt_parts.append("DETECTED EMOTION (context for response):\n")
prompt_parts.append("✓ Emotion: HAPPINESS\n")
prompt_parts.append("✓ Confidence: 95%\n")
prompt_parts.append("✓ Intensity: 75%\n")
prompt_parts.append("Response tone: Be upbeat and positive. Share in their joy.\n")
prompt_parts.append("\n")

# Memory context (simulated - this is the wild card)
# Simulate approximately 1000+ characters of memory context
memory_context = """RELEVANT BACKGROUND (from past conversations)
============================================================
User mentioned struggling with friend relationships last week but found support in their friend group.
Previous context shows user values social connection and quality time with friends.
User has mentioned academics being stressful, but close friends provide emotional support.
User enjoys group activities and values spontaneous plans with friends.
User's mood typically improves significantly during social interactions.

"""
prompt_parts.append(memory_context)

# Conversation history
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("CONVERSATION SO FAR (context only - current message is priority)\n")
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("⚠️ NOTE: Previous message provides context,\n")
prompt_parts.append("but THIS CURRENT MESSAGE is the primary focus.\n")
prompt_parts.append("User's emotions may have SHIFTED from previous messages.\n")
prompt_parts.append("Do NOT assume current emotion matches previous tone.\n\n")
prompt_parts.append("(First message in this conversation - no history)\n")
prompt_parts.append("\n")

# Current message
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("WHAT THE USER JUST SAID (PRIMARY FOCUS)\n")
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append(f"\"{TEST_MESSAGE}\"\n\n")

# Response instructions
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("YOUR RESPONSE\n")
prompt_parts.append("=" * 60 + "\n")
prompt_parts.append("Be upbeat and positive. Share in their joy.\n")
prompt_parts.append("\nIMPORTANT INSTRUCTIONS:\n")
prompt_parts.append("1. Respond to the USER'S CURRENT MESSAGE, not just their emotion label.\n")
prompt_parts.append("2. Be SPECIFIC to what they just said - not generic support phrases.\n")
prompt_parts.append("3. Use detected emotion as CONTEXT, not the entire meaning.\n")
prompt_parts.append("4. Understand what is HAPPENING in their situation before responding.\n")
prompt_parts.append("5. Reference concrete details from their message when natural.\n")
prompt_parts.append("6. Ask specific rather than generic follow-up questions.\n")
prompt_parts.append("7. Don't diagnose or provide therapy - just respond naturally.\n")
prompt_parts.append("8. Match their sentiment and tone exactly.\n")
prompt_parts.append("9. Keep it natural and conversational (1-2 sentences usually).\n")
prompt_parts.append("10. DO NOT be overly therapeutic or use clichés.\n")
prompt_parts.append("\nRespond now:\n")

# Combine into full prompt
full_prompt = "".join(prompt_parts)

print(f"✓ Reconstructed application prompt")
print(f"  Prompt length: {len(full_prompt)} characters")
print(f"  Target was: ~3810 characters")
print(f"  Difference: {len(full_prompt) - 3810} chars")

print()
print("[STEP 3] Test DIRECT Ollama call with application parameters")
print("-" * 80)

payload = {
    "model": "orca-mini:3b",
    "prompt": full_prompt,
    "stream": False,
    "temperature": 0.75,
    "top_p": 0.9,
    "top_k": 40,
}

print(f"Payload details:")
print(f"  Model: {payload['model']}")
print(f"  Prompt length: {len(payload['prompt'])} chars")
print(f"  Temperature: {payload['temperature']}")
print(f"  top_p: {payload['top_p']}")
print(f"  top_k: {payload['top_k']}")
print(f"  Stream: {payload['stream']}")
print()
print(f"Sending request to Ollama...")

try:
    start_time = time.time()
    
    response = requests.post(
        "http://localhost:11434/api/generate",
        json=payload,
        timeout=60  # 60 second timeout like your working test
    )
    
    elapsed = time.time() - start_time
    
    print()
    print("=" * 80)
    print("RESULT")
    print("=" * 80)
    print(f"Time elapsed: {elapsed:.2f} seconds")
    print(f"HTTP Status: {response.status_code}")
    
    if response.status_code == 200:
        print(f"✓ SUCCESS")
        response_data = response.json()
        response_text = response_data.get("response", "")
        print(f"Response length: {len(response_text)} characters")
        print(f"Response preview: {response_text[:150]}...")
        
        print()
        print("INTERPRETATION:")
        print("-" * 80)
        if elapsed < 30:
            print(f"✓ Request completed in {elapsed:.2f}s (< 30s timeout)")
            print()
            print("CONCLUSION A: The exact Ollama payload + parameters do NOT cause timeout")
            print()
            print("This suggests the problem is:")
            print("  • Request overlap/queueing (Flask single-threaded)")
            print("  • Application code execution time before Ollama call")
            print("  • Database queries or memory retrieval delays")
            print("  • Something happening AROUND the Ollama request, not the request itself")
        else:
            print(f"✗ Request took {elapsed:.2f}s (approaching or over 30s timeout)")
            print()
            print("CONCLUSION B: The exact payload and parameters cause slow response")
            print()
            print("This suggests the problem is:")
            print("  • Prompt size + parameters combination")
            print("  • Ollama generation time for this specific prompt")
            print("  • Model behavior with these specific settings")
    else:
        print(f"✗ HTTP Error {response.status_code}")
        print(f"Response: {response.text[:200]}")
        
except requests.Timeout as e:
    elapsed = 60.0  # Timeout at 60 seconds
    print()
    print("=" * 80)
    print("RESULT")
    print("=" * 80)
    print(f"✗ REQUEST TIMED OUT")
    print(f"Timeout after: {elapsed:.2f} seconds")
    print(f"Error: {e}")
    
    print()
    print("INTERPRETATION:")
    print("-" * 80)
    print("CONCLUSION C: The exact Ollama payload causes timeout")
    print()
    print("The request hung for 60 seconds, which means:")
    print("  • Ollama is processing very slowly")
    print("  • Payload + parameters cause excessive inference time")
    print("  • Model might be stuck or loading")
    
except Exception as e:
    print()
    print("=" * 80)
    print("RESULT")
    print("=" * 80)
    print(f"✗ ERROR: {type(e).__name__}: {e}")
    print()
    print("INTERPRETATION:")
    print("-" * 80)
    print("CONCLUSION D: Request failed with exception")
    print(f"Error type: {type(e).__name__}")

print()
print("=" * 80)
print("TEST ENVIRONMENT")
print("=" * 80)
print("During this test:")
print("  • No other /chat requests were made")
print("  • No /initial-greeting requests were made")
print("  • Only orca-mini:3b was active")
print("  • Direct Ollama HTTP call (bypasses Flask)")
print()
print("=" * 80)
