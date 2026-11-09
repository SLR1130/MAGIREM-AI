#!/usr/bin/env python3
"""
TASK 3: Performance Diagnosis - Ollama Bottleneck Analysis
Diagnostic only - NO code changes
"""

import requests
import time
import json
import psutil
import os
from datetime import datetime

# Configuration
OLLAMA_API = "http://localhost:11434/api/generate"
MODELS = ["orca-mini:3b", "phi:latest", "mistral:latest", "neural-chat:latest"]
PRODUCTION_MODEL = "orca-mini:3b"

# Color codes for output
GREEN = "\033[92m"
CYAN = "\033[96m"
YELLOW = "\033[93m"
RED = "\033[91m"
RESET = "\033[0m"
BOLD = "\033[1m"

def get_system_metrics():
    """Get current system resource usage"""
    metrics = {
        "cpu_percent": psutil.cpu_percent(interval=0.1),
        "virtual_memory_percent": psutil.virtual_memory().percent,
        "available_memory_mb": psutil.virtual_memory().available / (1024**2),
        "swap_percent": psutil.swap_memory().percent,
    }
    
    # Get Ollama process memory
    try:
        ollama_process = None
        for proc in psutil.process_iter(['pid', 'name', 'memory_percent']):
            if 'ollama' in proc.name().lower():
                ollama_process = proc
                break
        
        if ollama_process:
            metrics["ollama_memory_mb"] = ollama_process.memory_info().rss / (1024**2)
            metrics["ollama_cpu_percent"] = ollama_process.cpu_percent(interval=0.1)
    except:
        pass
    
    return metrics

def print_header(title):
    """Print a formatted header"""
    print(f"\n{BOLD}{GREEN}{'='*70}{RESET}")
    print(f"{BOLD}{GREEN}{title:^70}{RESET}")
    print(f"{BOLD}{GREEN}{'='*70}{RESET}")

def print_section(title):
    """Print a section header"""
    print(f"\n{CYAN}{title}{RESET}")
    print(f"{CYAN}{'-'*70}{RESET}")

def test_minimal_ollama():
    """Step 2: Test raw Ollama performance with minimal prompt"""
    print_section("STEP 2: RAW OLLAMA PERFORMANCE TEST")
    
    prompt = "Hello"
    print(f"Prompt: '{prompt}' ({len(prompt)} chars)")
    print(f"Model: {PRODUCTION_MODEL}")
    print(f"Stream: false")
    
    try:
        before_metrics = get_system_metrics()
        start_time = time.time()
        
        response = requests.post(
            OLLAMA_API,
            json={
                "model": PRODUCTION_MODEL,
                "prompt": prompt,
                "stream": False,
            },
            timeout=60
        )
        
        end_time = time.time()
        after_metrics = get_system_metrics()
        duration = end_time - start_time
        
        if response.status_code == 200:
            data = response.json()
            generated_text = data.get("response", "")
            
            print(f"\n{GREEN}✓ SUCCESS{RESET}")
            print(f"  Total time: {duration:.2f}s")
            print(f"  Response length: {len(generated_text)} chars")
            print(f"  Response text: {generated_text[:100]}...")
            
            print(f"\n{CYAN}System Metrics Before:{RESET}")
            print(f"  CPU: {before_metrics['cpu_percent']:.1f}%")
            print(f"  Memory: {before_metrics['virtual_memory_percent']:.1f}% ({before_metrics['available_memory_mb']:.0f}MB avail)")
            print(f"  Swap: {before_metrics['swap_percent']:.1f}%")
            
            print(f"\n{CYAN}System Metrics After:{RESET}")
            print(f"  CPU: {after_metrics['cpu_percent']:.1f}%")
            print(f"  Memory: {after_metrics['virtual_memory_percent']:.1f}% ({after_metrics['available_memory_mb']:.0f}MB avail)")
            print(f"  Swap: {after_metrics['swap_percent']:.1f}%")
            
            if "ollama_memory_mb" in after_metrics:
                print(f"  Ollama process: {after_metrics.get('ollama_memory_mb', 0):.0f}MB RAM, {after_metrics.get('ollama_cpu_percent', 0):.1f}% CPU")
            
            return {
                "success": True,
                "duration": duration,
                "response_length": len(generated_text),
                "response_text": generated_text[:100]
            }
        else:
            print(f"\n{RED}✗ ERROR: HTTP {response.status_code}{RESET}")
            return {"success": False, "error": f"HTTP {response.status_code}"}
    except Exception as e:
        print(f"\n{RED}✗ ERROR: {str(e)}{RESET}")
        return {"success": False, "error": str(e)}

def test_production_request():
    """Step 3: Test production request with actual prompt"""
    print_section("STEP 3: PRODUCTION REQUEST TEST")
    
    # This is a realistic production prompt based on hybrid_response_engine.py
    production_prompt = """You are a supportive AI counselor for mental health. Respond with empathy and care.

================================================================================
MESSAGE UNDERSTANDING & CONTEXT
================================================================================
✓ INFERRED UNDERSTANDING (from message analysis):
  Topic: general
  User intent: seeking_support
  
DETECTED EMOTION (context for response):
✓ Emotion: SADNESS
✓ Confidence: 85% (HIGH)
✓ Intensity: 75% (MODERATE)
Response tone: Be empathetic and understanding. Validate their feelings.

================================================================================
WHAT THE USER JUST SAID (PRIMARY FOCUS)
================================================================================
"Hey, I've actually been having a pretty rough week..."

================================================================================
YOUR RESPONSE
================================================================================
Be empathetic and understanding. Validate their feelings.

IMPORTANT INSTRUCTIONS:
1. Respond to the USER'S CURRENT MESSAGE, not just their emotion label.
2. Be SPECIFIC to what they just said - not generic support phrases.
3. Use detected emotion as CONTEXT, not the entire meaning.
4. Understand what is HAPPENING in their situation before responding.
5. Reference concrete details from their message when natural.
6. Ask specific rather than generic follow-up questions.
7. Don't diagnose or provide therapy - just respond naturally.
8. Match their sentiment and tone exactly.

Generate a brief, empathetic response (2-3 sentences)."""
    
    print(f"Prompt length: {len(production_prompt)} chars")
    print(f"Model: {PRODUCTION_MODEL}")
    print(f"Stream: false")
    
    try:
        before_metrics = get_system_metrics()
        start_time = time.time()
        
        response = requests.post(
            OLLAMA_API,
            json={
                "model": PRODUCTION_MODEL,
                "prompt": production_prompt,
                "stream": False,
                "temperature": 0.75,
                "top_p": 0.9,
                "top_k": 40,
            },
            timeout=60
        )
        
        end_time = time.time()
        after_metrics = get_system_metrics()
        duration = end_time - start_time
        
        if response.status_code == 200:
            data = response.json()
            generated_text = data.get("response", "")
            
            print(f"\n{GREEN}✓ SUCCESS{RESET}")
            print(f"  Total time: {duration:.2f}s")
            print(f"  Response length: {len(generated_text)} chars")
            print(f"  Response text: {generated_text[:150]}...")
            
            print(f"\n{CYAN}System Metrics Before:{RESET}")
            print(f"  CPU: {before_metrics['cpu_percent']:.1f}%")
            print(f"  Memory: {before_metrics['virtual_memory_percent']:.1f}% ({before_metrics['available_memory_mb']:.0f}MB avail)")
            print(f"  Swap: {before_metrics['swap_percent']:.1f}%")
            
            print(f"\n{CYAN}System Metrics After:{RESET}")
            print(f"  CPU: {after_metrics['cpu_percent']:.1f}%")
            print(f"  Memory: {after_metrics['virtual_memory_percent']:.1f}% ({after_metrics['available_memory_mb']:.0f}MB avail)")
            print(f"  Swap: {after_metrics['swap_percent']:.1f}%")
            
            if "ollama_memory_mb" in after_metrics:
                print(f"  Ollama process: {after_metrics.get('ollama_memory_mb', 0):.0f}MB RAM, {after_metrics.get('ollama_cpu_percent', 0):.1f}% CPU")
            
            return {
                "success": True,
                "duration": duration,
                "response_length": len(generated_text),
                "response_text": generated_text[:150]
            }
        else:
            print(f"\n{RED}✗ ERROR: HTTP {response.status_code}{RESET}")
            return {"success": False, "error": f"HTTP {response.status_code}"}
    except Exception as e:
        print(f"\n{RED}✗ ERROR: {str(e)}{RESET}")
        return {"success": False, "error": str(e)}

def benchmark_all_models():
    """Step 6: Compare all installed models"""
    print_section("STEP 6: LIGHTWEIGHT MODEL BENCHMARK")
    
    test_prompt = "What is mindfulness and how can it help with anxiety? Give a brief answer."
    
    print(f"Test prompt: '{test_prompt}' ({len(test_prompt)} chars)")
    print(f"Testing each model with SAME prompt, one at a time\n")
    
    results = {}
    
    for model in MODELS:
        print(f"Testing {BOLD}{model}{RESET}...", end=" ", flush=True)
        
        try:
            start_time = time.time()
            
            response = requests.post(
                OLLAMA_API,
                json={
                    "model": model,
                    "prompt": test_prompt,
                    "stream": False,
                },
                timeout=60
            )
            
            duration = time.time() - start_time
            
            if response.status_code == 200:
                data = response.json()
                generated_text = data.get("response", "")
                results[model] = {
                    "success": True,
                    "duration": duration,
                    "response_length": len(generated_text),
                    "response_text": generated_text[:80]
                }
                print(f"{GREEN}✓ {duration:.2f}s (response: {len(generated_text)} chars){RESET}")
            else:
                results[model] = {"success": False, "error": f"HTTP {response.status_code}"}
                print(f"{RED}✗ HTTP {response.status_code}{RESET}")
        except Exception as e:
            results[model] = {"success": False, "error": str(e)}
            print(f"{RED}✗ {str(e)}{RESET}")
    
    # Summary table
    print(f"\n{CYAN}BENCHMARK SUMMARY:{RESET}")
    print(f"{'Model':<25} {'Time':<10} {'Response':<15} {'Status'}")
    print(f"{'-'*70}")
    for model, result in results.items():
        if result.get("success"):
            print(f"{model:<25} {result['duration']:.2f}s {result['response_length']:>6} chars  ✓")
        else:
            print(f"{model:<25} {'ERROR':<10} {result.get('error', 'Unknown')}")
    
    return results

def main():
    """Run full diagnostic suite"""
    print_header("OLLAMA PERFORMANCE DIAGNOSIS - TASK 3")
    print(f"Start time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"Ollama API: {OLLAMA_API}")
    
    # Step 1: Already done - verified models are installed
    print_section("STEP 1: INSTALLED MODELS")
    print(f"Installed models: {', '.join(MODELS)}")
    print(f"Production model: {PRODUCTION_MODEL}")
    print("Current status: No model loaded (checked with ollama ps)")
    
    # Step 2: Raw Ollama performance
    result_minimal = test_minimal_ollama()
    
    # Small delay for system reset
    print(f"\n{YELLOW}Waiting 3s before next test...{RESET}")
    time.sleep(3)
    
    # Step 3: Production request
    result_production = test_production_request()
    
    # Step 4: Already covered in system metrics above
    print_section("STEP 4: CPU/RAM BEHAVIOR")
    print("Monitored during Steps 2 and 3 above")
    print("Note: For heavy concurrent load testing, open Task Manager/Resource Monitor")
    
    # Small delay for system reset
    print(f"\n{YELLOW}Waiting 3s before benchmark...{RESET}")
    time.sleep(3)
    
    # Step 6: Model benchmark
    benchmark_results = benchmark_all_models()
    
    # Generate final diagnosis
    print_header("FINAL DIAGNOSIS (STEP 7)")
    
    print(f"\n{BOLD}A. CURRENT MODEL:{RESET} {PRODUCTION_MODEL}")
    
    print(f"\n{BOLD}B. RAW OLLAMA RESPONSE TIME (Step 2):{RESET}")
    if result_minimal.get("success"):
        print(f"   {result_minimal['duration']:.2f}s for minimal prompt ('Hello')")
    else:
        print(f"   ERROR: {result_minimal.get('error')}")
    
    print(f"\n{BOLD}C. PRODUCTION-PROMPT RESPONSE TIME (Step 3):{RESET}")
    if result_production.get("success"):
        print(f"   {result_production['duration']:.2f}s for full production prompt (1637 chars)")
    else:
        print(f"   ERROR: {result_production.get('error')}")
    
    print(f"\n{BOLD}D. CPU/RAM BEHAVIOR:{RESET}")
    print("   Detailed metrics shown in Steps 2-3 output above")
    print("   Key indicators: Ollama process memory, system swap usage")
    
    print(f"\n{BOLD}E. NUMBER OF OLLAMA REQUESTS PER CHAT:{RESET}")
    print("   Single request per user message (confirmed in code review)")
    print("   Flow: User message → Flask endpoint → single Ollama call")
    
    print(f"\n{BOLD}F. MODEL COMPARISON TIMES:{RESET}")
    for model, result in benchmark_results.items():
        if result.get("success"):
            print(f"   {model:<25} {result['duration']:>6.2f}s")
        else:
            print(f"   {model:<25} ERROR")
    
    print(f"\n{BOLD}G. EXACT BOTTLENECK:{RESET}")
    if result_production.get("success") and result_minimal.get("success"):
        prompt_overhead = result_production['duration'] - result_minimal['duration']
        print(f"   Minimal test: {result_minimal['duration']:.2f}s")
        print(f"   Production test: {result_production['duration']:.2f}s")
        print(f"   Prompt overhead: {prompt_overhead:.2f}s")
        
        if prompt_overhead > 5:
            print(f"   {YELLOW}⚠ Larger prompt (+{len(production_prompt)-5} chars) adds {prompt_overhead:.2f}s overhead{RESET}")
        
        if result_production['duration'] > 30:
            print(f"   {RED}⚠ CRITICAL: Production response exceeds 30s target{RESET}")
            print(f"   Current: {result_production['duration']:.2f}s (45s timeout configured)")
        
        # Check if orca-mini is slower than other models
        faster_models = [m for m, r in benchmark_results.items() if r.get("success") and r['duration'] < result_minimal['duration'] and m != PRODUCTION_MODEL]
        if faster_models:
            print(f"   {YELLOW}ℹ Some models are faster: {', '.join(faster_models)}{RESET}")
    
    print(f"\n{BOLD}H. RECOMMENDED SMALLEST CHANGE:{RESET}")
    if result_production.get("success"):
        if result_production['duration'] > 25:
            print(f"   1. Consider using smaller model (phi:latest or orca-mini:3b benchmarks)")
            print(f"   2. Reduce prompt context - current: ~1500 chars (history + memory + instructions)")
            print(f"   3. Monitor concurrent load - 45s timeout handles multiple simultaneous requests")
            print(f"   4. Current duration: {result_production['duration']:.2f}s - acceptable with 45s timeout")
        else:
            print(f"   Response time is acceptable ({result_production['duration']:.2f}s < 45s timeout)")
            print(f"   No immediate changes recommended")
    
    print(f"\n{YELLOW}Note: This is diagnostic only. No code changes were made.{RESET}")
    print(f"End time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

if __name__ == "__main__":
    main()
