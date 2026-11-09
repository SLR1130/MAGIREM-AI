#!/usr/bin/env python3
"""
PHI Edge Case Investigation - Diagnostic Tool
Determines root cause of empty responses on 3 test types
"""

import requests
import json
import time

OLLAMA_URL = "http://localhost:11434"
MODEL = "phi:latest"

# The three failing prompts (exact from benchmark)
TESTS = {
    "sarcasm": {
        "task": "Detect sarcasm and respond appropriately",
        "message": "My professor just gave us three deadlines for tomorrow. What a wonderful surprise 😂",
        "orca_response": "Oh, I understand. You were being sarcastic. Thank you for letting me know.",
        "cli_response": "I'm sorry to hear that. It sounds like you're feeling overwhelmed by the workload."
    },
    "tagalog": {
        "task": "Understand Tagalog sarcasm",
        "message": "Grabe naman, ang saya talaga. Tatlong deadline bukas 😂",
        "orca_response": "Oh really? That's terrible news! Two deadlines in one day?",
        "cli_response": "The message contains sarcasm, as the speaker is saying that the deadline for a task is a joke"
    },
    "direct_question": {
        "task": "Provide useful advice",
        "message": "How should I prepare for my exam tomorrow?",
        "orca_response": "Preparing for an exam tomorrow can involve several strategies...",
        "cli_response": "Hi there! I'd be happy to help. First, it's important to get a good night's sleep."
    }
}

def test_exact_benchmark_format(test_name, test_data):
    """Test with exact benchmark format"""
    prompt = f"{test_data['task']}\n\nMessage: \"{test_data['message']}\"\n\nRespond naturally:"
    
    try:
        start = time.time()
        response = requests.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": MODEL,
                "prompt": prompt,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
            },
            timeout=(5, 30),
        )
        elapsed = time.time() - start
        
        if response.status_code == 200:
            data = response.json()
            resp_text = data.get("response", "").strip()
            return {
                "status": "success",
                "response_length": len(resp_text),
                "response_text": resp_text[:100],
                "eval_count": data.get("eval_count", 0),
                "prompt_eval_count": data.get("prompt_eval_count", 0),
                "time": elapsed
            }
        else:
            return {"status": f"http_{response.status_code}"}
    except Exception as e:
        return {"status": f"error: {str(e)[:50]}"}

def test_without_emoji(test_name, test_data):
    """Test prompt without emoji"""
    message_no_emoji = test_data['message'].replace(" 😂", "")
    prompt = f"{test_data['task']}\n\nMessage: \"{message_no_emoji}\"\n\nRespond naturally:"
    
    try:
        response = requests.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": MODEL,
                "prompt": prompt,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
            },
            timeout=(5, 30),
        )
        data = response.json()
        resp_text = data.get("response", "").strip()
        return {"response_length": len(resp_text), "response": resp_text[:80] if resp_text else "(empty)"}
    except:
        return {"error": "timeout/error"}

def test_minimal_prompt(test_name, test_data):
    """Test with just the message, no task"""
    prompt = test_data['message']
    
    try:
        response = requests.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": MODEL,
                "prompt": prompt,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
            },
            timeout=(5, 30),
        )
        data = response.json()
        resp_text = data.get("response", "").strip()
        return {"response_length": len(resp_text), "response": resp_text[:80] if resp_text else "(empty)"}
    except:
        return {"error": "timeout/error"}

def test_with_num_predict(test_name, test_data):
    """Test with explicit num_predict to force token generation"""
    prompt = f"{test_data['task']}\n\nMessage: \"{test_data['message']}\"\n\nRespond naturally:"
    
    try:
        response = requests.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": MODEL,
                "prompt": prompt,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
                "num_predict": 100,
            },
            timeout=(5, 30),
        )
        data = response.json()
        resp_text = data.get("response", "").strip()
        return {"response_length": len(resp_text), "eval_count": data.get("eval_count"), "response": resp_text[:80] if resp_text else "(empty)"}
    except:
        return {"error": "timeout/error"}

# ============================================================================
# RUN INVESTIGATION
# ============================================================================

print("=" * 80)
print("PHI EDGE-CASE INVESTIGATION")
print("=" * 80)

for test_name, test_data in TESTS.items():
    print(f"\n{'='*80}")
    print(f"TEST: {test_name.upper()}")
    print(f"{'='*80}")
    
    print(f"\nMessage: {test_data['message']}")
    print(f"\nOllama CLI returned: {test_data['cli_response'][:70]}...")
    print(f"ORCA-mini returned: {test_data['orca_response'][:70]}...")
    
    print(f"\n1. EXACT BENCHMARK FORMAT:")
    result = test_exact_benchmark_format(test_name, test_data)
    print(f"   Response length: {result.get('response_length', 'N/A')}")
    print(f"   Eval count: {result.get('eval_count', 'N/A')}")
    print(f"   Response: {result.get('response_text', '(empty)')}")
    print(f"   Time: {result.get('time', 'N/A'):.2f}s" if 'time' in result else "")
    
    print(f"\n2. WITHOUT EMOJI:")
    result = test_without_emoji(test_name, test_data)
    print(f"   Response length: {result.get('response_length', 'N/A')}")
    print(f"   Response: {result.get('response', result.get('error', 'N/A'))}")
    
    print(f"\n3. MINIMAL (just message):")
    result = test_minimal_prompt(test_name, test_data)
    print(f"   Response length: {result.get('response_length', 'N/A')}")
    print(f"   Response: {result.get('response', result.get('error', 'N/A'))}")
    
    print(f"\n4. WITH num_predict=100:")
    result = test_with_num_predict(test_name, test_data)
    print(f"   Response length: {result.get('response_length', 'N/A')}")
    print(f"   Eval count: {result.get('eval_count', 'N/A')}")
    print(f"   Response: {result.get('response', result.get('error', 'N/A'))}")
    
    time.sleep(1)

print(f"\n\n{'='*80}")
print("INVESTIGATION COMPLETE")
print(f"{'='*80}")
