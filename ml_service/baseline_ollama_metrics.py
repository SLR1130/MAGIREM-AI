#!/usr/bin/env python3
"""
OLLAMA METRICS EXTRACTION - Session 11
Extracts detailed timing metadata from Ollama responses

Ollama provides timing information in responses:
- total_duration: Total time (ns)
- load_duration: Model load time (ns)
- prompt_eval_duration: Time to evaluate prompt (ns)
- eval_duration: Time to generate tokens (ns)
- prompt_eval_count: Number of prompt tokens
- eval_count: Number of generated tokens

This script captures these metrics to separate:
1. Model loading from generation
2. Prompt evaluation from token generation
3. Actual token generation rate (tokens/sec)

Instructions:
1. Start Ollama: ollama serve
2. Run this script: python baseline_ollama_metrics.py
3. Review OLLAMA_METRICS_RESULTS.txt
"""

import requests
import json
import time
from pathlib import Path
from datetime import datetime

# ============================================================================
# CONFIGURATION
# ============================================================================

OLLAMA_API = "http://localhost:11434/api"
NEURAL_CHAT_MODEL = "neural-chat:latest"
ORCA_MINI_MODEL = "orca-mini:3b"

# ============================================================================
# DIRECT OLLAMA API CALLS (not through Flask)
# ============================================================================

def direct_ollama_call(model, prompt, num_ctx=None, num_predict=None):
    """Call Ollama API directly and capture all metadata"""
    
    print(f"\n[OLLAMA_DIRECT_CALL] Model: {model}")
    print(f"[PROMPT_SIZE] {len(prompt)} characters")
    
    try:
        # Build request
        payload = {
            "model": model,
            "prompt": prompt,
            "stream": False,
            "temperature": 0.7
        }
        
        if num_ctx:
            payload["num_ctx"] = num_ctx
        if num_predict:
            payload["num_predict"] = num_predict
        
        # Send to Ollama
        start_time = time.time()
        response = requests.post(
            f"{OLLAMA_API}/generate",
            json=payload,
            timeout=120
        )
        end_time = time.time()
        
        if response.status_code != 200:
            print(f"[ERROR] HTTP {response.status_code}")
            return None
        
        # Parse response
        data = response.json()
        
        # Extract timing metadata (all in nanoseconds)
        result = {
            'model': model,
            'http_time_ms': (end_time - start_time) * 1000,
            'prompt_chars': len(prompt),
            'response_chars': len(data.get('response', '')),
            'response_text': data.get('response', '')[:100],
            
            # Timing metadata from Ollama
            'total_duration_ns': data.get('total_duration', 0),
            'load_duration_ns': data.get('load_duration', 0),
            'prompt_eval_duration_ns': data.get('prompt_eval_duration', 0),
            'eval_duration_ns': data.get('eval_duration', 0),
            
            # Token counts
            'prompt_eval_count': data.get('prompt_eval_count', 0),
            'eval_count': data.get('eval_count', 0),
            
            # Raw response for verification
            'raw_response': data
        }
        
        # Convert ns to ms for readability
        result['total_duration_ms'] = result['total_duration_ns'] / 1_000_000
        result['load_duration_ms'] = result['load_duration_ns'] / 1_000_000
        result['prompt_eval_duration_ms'] = result['prompt_eval_duration_ns'] / 1_000_000
        result['eval_duration_ms'] = result['eval_duration_ns'] / 1_000_000
        
        # Calculate token generation rate
        if result['eval_duration_ms'] > 0 and result['eval_count'] > 0:
            result['tokens_per_sec'] = result['eval_count'] / (result['eval_duration_ms'] / 1000)
        else:
            result['tokens_per_sec'] = 0
        
        print(f"[SUCCESS] Got response")
        
        return result
    
    except Exception as e:
        print(f"[EXCEPTION] {e}")
        return None


# ============================================================================
# TEST 1: NEURAL-CHAT METRICS
# ============================================================================

def test_neural_chat_metrics():
    """Direct Ollama calls to Neural-chat to measure timing"""
    
    print("\n" + "="*80)
    print("TEST 1: NEURAL-CHAT METRICS (Direct Ollama)")
    print("="*80)
    
    # Test prompt for greeting
    prompt = """You are MinsuanScore AI, a warm, empathetic mental health support companion.

Your task: Generate a warm, welcoming OPENING message to start a conversation.

User's name: TestUser
This is a RETURNING user (they've chatted before).

Recent topics they care about: working on a school project, feeling stressed about deadlines

Generate ONLY the greeting (1-2 sentences), nothing else.
Opening message:"""
    
    results = []
    
    # Test A: Cold start attempt
    print("\n[TEST_A] Neural-chat Cold Start")
    print("-"*80)
    
    # Try to unload first
    try:
        requests.post(f"{OLLAMA_API}/delete", json={"model": NEURAL_CHAT_MODEL}, timeout=10)
        time.sleep(2)
    except:
        pass
    
    result_a = direct_ollama_call(NEURAL_CHAT_MODEL, prompt)
    if result_a:
        results.append(("Neural-chat_A_Cold", result_a))
    
    time.sleep(2)
    
    # Test B: Warm start
    print("\n[TEST_B] Neural-chat Warm Start")
    print("-"*80)
    result_b = direct_ollama_call(NEURAL_CHAT_MODEL, prompt)
    if result_b:
        results.append(("Neural-chat_B_Warm", result_b))
    
    time.sleep(2)
    
    # Test C: Another warm call
    print("\n[TEST_C] Neural-chat Warm Start (repeat)")
    print("-"*80)
    result_c = direct_ollama_call(NEURAL_CHAT_MODEL, prompt)
    if result_c:
        results.append(("Neural-chat_C_Warm_Repeat", result_c))
    
    return results


# ============================================================================
# TEST 2: ORCA-MINI METRICS
# ============================================================================

def test_orca_mini_metrics():
    """Direct Ollama calls to Orca-mini to measure timing"""
    
    print("\n" + "="*80)
    print("TEST 2: ORCA-MINI METRICS (Direct Ollama)")
    print("="*80)
    
    # Test prompts
    prompts = [
        ("Test_1", "I'm feeling stressed about my deadlines."),
        ("Test_2", "What should I do about this?"),
        ("Test_3", "That's helpful, thanks."),
    ]
    
    results = []
    
    # Format as chat to match production (using /api/generate with system prompt)
    system_prompt = """You are MinsuanScore AI, a warm, empathetic mental health support companion.
Your role is to listen, validate feelings, and offer supportive guidance.
Be natural, conversational, and authentic."""
    
    for test_name, message in prompts:
        print(f"\n[{test_name}] {message[:50]}...")
        print("-"*80)
        
        # Build prompt in conversation format
        full_prompt = f"{system_prompt}\n\nUser: {message}\n\nAI:"
        
        result = direct_ollama_call(ORCA_MINI_MODEL, full_prompt)
        if result:
            results.append((f"Orca-mini_{test_name}", result))
        
        time.sleep(1)
    
    return results


# ============================================================================
# REPORT GENERATION
# ============================================================================

def format_metrics(test_name, metrics):
    """Format metrics for report"""
    
    lines = []
    lines.append(f"\n{test_name}")
    lines.append("-" * 80)
    
    if metrics is None:
        lines.append("ERROR: No metrics captured")
        return lines
    
    lines.append(f"Model: {metrics['model']}")
    lines.append(f"Prompt: {metrics['prompt_chars']} chars")
    lines.append(f"Response: {metrics['response_chars']} chars")
    lines.append(f"Response preview: {metrics['response_text']}...")
    
    lines.append(f"\nTiming (from Ollama metadata):")
    lines.append(f"  Total Duration: {metrics['total_duration_ms']:.0f}ms")
    lines.append(f"  Model Load: {metrics['load_duration_ms']:.0f}ms")
    lines.append(f"  Prompt Evaluation: {metrics['prompt_eval_duration_ms']:.0f}ms")
    lines.append(f"  Token Generation: {metrics['eval_duration_ms']:.0f}ms")
    
    lines.append(f"\nToken Counts:")
    lines.append(f"  Prompt Tokens: {metrics['prompt_eval_count']}")
    lines.append(f"  Generated Tokens: {metrics['eval_count']}")
    
    if metrics['tokens_per_sec'] > 0:
        lines.append(f"  Generation Rate: {metrics['tokens_per_sec']:.2f} tokens/sec")
    
    lines.append(f"\nHTTP Round Trip: {metrics['http_time_ms']:.0f}ms")
    
    return lines


def generate_report(neural_chat_results, orca_results):
    """Generate comprehensive metrics report"""
    
    report = []
    report.append("="*80)
    report.append("OLLAMA METRICS ANALYSIS")
    report.append("MinsuanScore Mental Health AI System")
    report.append(f"Generated: {datetime.now()}")
    report.append("="*80)
    
    # ========================================================================
    # NEURAL-CHAT SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("SECTION A: NEURAL-CHAT METRICS")
    report.append("="*80)
    
    for test_name, metrics in neural_chat_results:
        report.extend(format_metrics(test_name, metrics))
    
    # Summary for neural-chat
    if neural_chat_results:
        successful = [m for _, m in neural_chat_results if m is not None]
        if successful:
            total_times = [m['total_duration_ms'] for m in successful]
            load_times = [m['load_duration_ms'] for m in successful]
            eval_times = [m['eval_duration_ms'] for m in successful]
            gen_rates = [m['tokens_per_sec'] for m in successful if m['tokens_per_sec'] > 0]
            
            report.append("\n" + "-"*80)
            report.append("Neural-chat Summary (successful tests)")
            report.append("-"*80)
            report.append(f"Average Total Time: {sum(total_times)/len(total_times):.0f}ms")
            report.append(f"Average Load Time: {sum(load_times)/len(load_times):.0f}ms")
            report.append(f"Average Generation Time: {sum(eval_times)/len(eval_times):.0f}ms")
            if gen_rates:
                report.append(f"Average Generation Rate: {sum(gen_rates)/len(gen_rates):.2f} tokens/sec")
            report.append(f"Min Total: {min(total_times):.0f}ms | Max Total: {max(total_times):.0f}ms")
    
    # ========================================================================
    # ORCA-MINI SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("SECTION B: ORCA-MINI METRICS")
    report.append("="*80)
    
    for test_name, metrics in orca_results:
        report.extend(format_metrics(test_name, metrics))
    
    # Summary for Orca
    if orca_results:
        successful = [m for _, m in orca_results if m is not None]
        if successful:
            total_times = [m['total_duration_ms'] for m in successful]
            load_times = [m['load_duration_ms'] for m in successful]
            eval_times = [m['eval_duration_ms'] for m in successful]
            gen_rates = [m['tokens_per_sec'] for m in successful if m['tokens_per_sec'] > 0]
            
            report.append("\n" + "-"*80)
            report.append("Orca-mini Summary (successful tests)")
            report.append("-"*80)
            report.append(f"Average Total Time: {sum(total_times)/len(total_times):.0f}ms")
            report.append(f"Average Load Time: {sum(load_times)/len(load_times):.0f}ms")
            report.append(f"Average Generation Time: {sum(eval_times)/len(eval_times):.0f}ms")
            if gen_rates:
                report.append(f"Average Generation Rate: {sum(gen_rates)/len(gen_rates):.2f} tokens/sec")
            report.append(f"Min Total: {min(total_times):.0f}ms | Max Total: {max(total_times):.0f}ms")
    
    # ========================================================================
    # ANALYSIS
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("SECTION C: ANALYSIS")
    report.append("="*80)
    
    report.append("""
The metrics above are extracted directly from Ollama API responses.

Key measurements:
1. Load Duration: Time to load model from disk (only if cold start)
2. Prompt Evaluation: Time to process input tokens (tokenization + setup)
3. Token Generation: Time to generate output tokens (actual inference)
4. Generation Rate: Tokens per second (eval_count / eval_duration)

Interpretation:
- If load_duration >> 0: Model was not in memory, had to load
- If load_duration ≈ 0: Model was already resident in memory
- Generation rate determines how long responses take

Example:
  Total: 15,000ms
  = Load (model already there): 50ms
  + Prompt eval: 400ms
  + Generation (140 tokens @ 10 tok/s): 14,000ms
  + Overhead: 550ms
""")
    
    return "\n".join(report)


# ============================================================================
# MAIN
# ============================================================================

def main():
    """Run all Ollama metrics tests"""
    
    print("\nOllama Metrics Extraction")
    print("Capturing timing metadata from direct Ollama API calls")
    print()
    
    # Test Ollama connection
    try:
        requests.get(f"{OLLAMA_API}/tags", timeout=5)
        print("[OK] Ollama is accessible")
    except:
        print("[ERROR] Cannot connect to Ollama")
        print("Please start Ollama with: ollama serve")
        return
    
    # Run tests
    neural_chat_metrics = test_neural_chat_metrics()
    orca_metrics = test_orca_mini_metrics()
    
    # Generate report
    report = generate_report(neural_chat_metrics, orca_metrics)
    
    # Print
    print("\n" + report)
    
    # Save
    report_file = Path(__file__).parent / "OLLAMA_METRICS_RESULTS.txt"
    with open(report_file, 'w', encoding='utf-8') as f:
        f.write(report)
    
    print(f"\n[SAVED] Report saved to: {report_file}")
    
    # Save raw JSON
    json_data = {
        'neural_chat': [{'name': n, 'metrics': m} for n, m in neural_chat_metrics],
        'orca_mini': [{'name': n, 'metrics': m} for n, m in orca_metrics]
    }
    
    json_file = Path(__file__).parent / "ollama_metrics_results.json"
    with open(json_file, 'w', encoding='utf-8') as f:
        json.dump(json_data, f, indent=2)
    
    print(f"[SAVED] Raw data saved to: {json_file}")


if __name__ == "__main__":
    main()
