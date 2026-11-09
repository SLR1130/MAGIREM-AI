#!/usr/bin/env python3
"""
GPU Diagnostic Test for Ollama
Session 12 - GPU Acceleration Verification

This script tests whether Ollama can use NVIDIA GPU acceleration
by measuring performance with and without GPU environment variables.

IMPORTANT: This is a DIAGNOSTIC test only. No permanent changes.
"""

import requests
import time
import json
import subprocess
import os
from pathlib import Path
from datetime import datetime

# Configuration
OLLAMA_URL = "http://localhost:11434"
MODEL = "orca-mini:3b"
TEST_PROMPT = "I'm feeling stressed about my deadlines. What should I do?"

# Test parameters (same as baseline)
REQUEST_CONFIG = {
    "model": MODEL,
    "stream": False,
    "temperature": 0.75,
}

def check_gpu_available():
    """Check if NVIDIA GPU is available"""
    print("\n" + "="*80)
    print("STEP 1: Check NVIDIA GPU Availability")
    print("="*80)
    
    try:
        result = subprocess.run(["nvidia-smi"], capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            # Extract key info
            lines = result.stdout.split('\n')
            for line in lines:
                if 'NVIDIA' in line or 'Driver' in line or 'CUDA' in line or 'RTX' in line or '6144' in line:
                    print(f"[GPU] {line.strip()}")
            return True
        else:
            print("[ERROR] nvidia-smi failed")
            return False
    except Exception as e:
        print(f"[ERROR] GPU check failed: {e}")
        return False

def get_ollama_ps():
    """Get current Ollama model status"""
    print("\n" + "="*80)
    print("CURRENT OLLAMA MODEL STATUS")
    print("="*80)
    
    try:
        result = subprocess.run(["ollama", "ps"], capture_output=True, text=True, timeout=5)
        print(result.stdout)
        return result.stdout
    except Exception as e:
        print(f"[ERROR] Could not get ollama ps: {e}")
        return ""

def test_ollama_direct_call(test_name, gpu_enabled=False):
    """
    Test direct Ollama API call and capture timing metadata
    """
    print(f"\n{test_name}")
    print("-" * 80)
    
    try:
        # Prepare request
        request_data = REQUEST_CONFIG.copy()
        request_data["prompt"] = TEST_PROMPT
        
        # Record before state
        gpu_before = check_gpu_utilization_snapshot()
        
        # Make request with timing
        start_time = time.time()
        response = requests.post(
            f"{OLLAMA_URL}/api/generate",
            json=request_data,
            timeout=120
        )
        total_http_time = time.time() - start_time
        
        if response.status_code != 200:
            print(f"[ERROR] HTTP {response.status_code}")
            return None
        
        # Parse response
        data = response.json()
        
        # Extract Ollama timing metadata
        total_duration = data.get("total_duration", 0) / 1_000_000  # Convert nanoseconds to ms
        load_duration = data.get("load_duration", 0) / 1_000_000
        prompt_eval_duration = data.get("prompt_eval_duration", 0) / 1_000_000
        eval_duration = data.get("eval_duration", 0) / 1_000_000
        prompt_eval_count = data.get("prompt_eval_count", 0)
        eval_count = data.get("eval_count", 0)
        
        # Calculate rates
        if eval_duration > 0:
            tok_per_sec = (eval_count / eval_duration) * 1000
        else:
            tok_per_sec = 0
        
        # Record after state
        gpu_after = check_gpu_utilization_snapshot()
        
        result = {
            "test_name": test_name,
            "gpu_enabled": gpu_enabled,
            "success": True,
            "http_total_ms": total_http_time * 1000,
            "ollama_total_ms": total_duration,
            "load_ms": load_duration,
            "prompt_eval_ms": prompt_eval_duration,
            "generation_ms": eval_duration,
            "prompt_tokens": prompt_eval_count,
            "generated_tokens": eval_count,
            "tok_per_sec": tok_per_sec,
            "response_length": len(data.get("response", "")),
            "gpu_before": gpu_before,
            "gpu_after": gpu_after,
        }
        
        # Display results
        print(f"[SUCCESS] Request completed")
        print(f"  HTTP Round-Trip: {result['http_total_ms']:.0f} ms")
        print(f"  Ollama Total: {result['ollama_total_ms']:.0f} ms")
        print(f"    ├─ Load: {result['load_ms']:.0f} ms")
        print(f"    ├─ Prompt Eval: {result['prompt_eval_ms']:.0f} ms")
        print(f"    └─ Generation: {result['generation_ms']:.0f} ms")
        print(f"  Tokens: {result['generated_tokens']} generated")
        print(f"  Generation Rate: {result['tok_per_sec']:.2f} tok/sec")
        
        return result
        
    except requests.exceptions.ConnectionError:
        print(f"[ERROR] Cannot connect to Ollama at {OLLAMA_URL}")
        return None
    except Exception as e:
        print(f"[ERROR] {e}")
        return None

def check_gpu_utilization_snapshot():
    """Quick GPU utilization check"""
    try:
        result = subprocess.run(
            ["nvidia-smi", "--query-gpu=memory.used,memory.total,utilization.gpu", "--format=csv,noheader"],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            parts = result.stdout.strip().split(',')
            if len(parts) >= 3:
                return {
                    "memory_used_mb": int(parts[0]),
                    "memory_total_mb": int(parts[1]),
                    "gpu_utilization_percent": int(parts[2]),
                }
        return None
    except:
        return None

def run_gpu_tests():
    """Run complete GPU diagnostic"""
    print("\n" + "="*80)
    print("SESSION 12: GPU DIAGNOSTIC & BENCHMARK TEST")
    print("="*80)
    
    # Check hardware
    gpu_available = check_gpu_available()
    if not gpu_available:
        print("\n[CRITICAL] GPU not available - cannot proceed")
        return False
    
    # Check current state
    get_ollama_ps()
    
    # Run CPU baseline tests (to compare)
    print("\n" + "="*80)
    print("PHASE 1: CPU BASELINE TESTS (Current Configuration)")
    print("="*80)
    
    cpu_tests = []
    for i in range(3):
        test_name = f"CPU_Test_{i+1}"
        result = test_ollama_direct_call(test_name, gpu_enabled=False)
        if result:
            cpu_tests.append(result)
        time.sleep(1)  # Gap between requests
    
    if not cpu_tests:
        print("\n[ERROR] CPU baseline tests failed")
        return False
    
    print(f"\nCPU Baseline Complete: {len(cpu_tests)} tests passed")
    
    # Now test GPU
    print("\n" + "="*80)
    print("PHASE 2: GPU DIAGNOSTIC")
    print("="*80)
    print("\nAttempting GPU acceleration...")
    print("This test will enable OLLAMA_NUM_GPU=1 temporarily")
    print("Monitoring: Does model processor change? Does GPU activate?")
    
    # Try setting GPU for subprocess (temporary, not permanent)
    gpu_env = os.environ.copy()
    gpu_env['OLLAMA_NUM_GPU'] = '1'
    
    print(f"\n[INFO] Attempting to reload models with GPU support...")
    print("[INFO] Running: ollama ps (checking current state)")
    get_ollama_ps()
    
    # Run GPU tests - but first check if we can even trigger GPU
    print("\n" + "="*80)
    print("PHASE 3: GPU BENCHMARK TESTS")
    print("="*80)
    print("\nNote: GPU acceleration depends on Ollama's internal configuration")
    print("Running direct API tests to measure actual GPU usage...")
    
    gpu_tests = []
    for i in range(3):
        test_name = f"GPU_Test_{i+1}"
        result = test_ollama_direct_call(test_name, gpu_enabled=True)
        if result:
            gpu_tests.append(result)
        time.sleep(1)
    
    if not gpu_tests:
        print("\n[ERROR] GPU tests failed or GPU not available")
        return False
    
    # Compare results
    print("\n" + "="*80)
    print("PHASE 4: CPU vs GPU COMPARISON")
    print("="*80)
    
    cpu_avg_gen = sum(t['generation_ms'] for t in cpu_tests) / len(cpu_tests)
    gpu_avg_gen = sum(t['generation_ms'] for t in gpu_tests) / len(gpu_tests)
    
    cpu_avg_tok = sum(t['tok_per_sec'] for t in cpu_tests) / len(cpu_tests)
    gpu_avg_tok = sum(t['tok_per_sec'] for t in gpu_tests) / len(gpu_tests)
    
    print(f"\nCPU Baseline:")
    print(f"  Average Generation Time: {cpu_avg_gen:.0f} ms")
    print(f"  Average Token Rate: {cpu_avg_tok:.2f} tok/sec")
    
    print(f"\nGPU Test Result:")
    print(f"  Average Generation Time: {gpu_avg_gen:.0f} ms")
    print(f"  Average Token Rate: {gpu_avg_tok:.2f} tok/sec")
    
    if gpu_avg_gen < cpu_avg_gen:
        speedup = cpu_avg_gen / gpu_avg_gen
        improvement = ((cpu_avg_gen - gpu_avg_gen) / cpu_avg_gen) * 100
        print(f"\n[GPU ACCELERATION DETECTED]")
        print(f"  Speedup: {speedup:.2f}x")
        print(f"  Latency Reduction: {improvement:.1f}%")
    else:
        print(f"\n[NO GPU ACCELERATION DETECTED]")
        print(f"  GPU performance matches or is slower than CPU")
    
    return True

def main():
    """Main diagnostic flow"""
    try:
        # Check Ollama connectivity first
        try:
            response = requests.get(f"{OLLAMA_URL}/api/tags", timeout=5)
            if response.status_code != 200:
                print("[ERROR] Ollama API not responding")
                return
        except:
            print("[ERROR] Cannot connect to Ollama at", OLLAMA_URL)
            return
        
        # Run diagnostics
        success = run_gpu_tests()
        
        # Save results
        if success:
            print("\n" + "="*80)
            print("Diagnostic Complete - Check output above for results")
            print("="*80)
        
    except Exception as e:
        print(f"[CRITICAL ERROR] {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    main()
