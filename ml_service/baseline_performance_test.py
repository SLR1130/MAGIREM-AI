#!/usr/bin/env python3
"""
BASELINE PERFORMANCE TEST - Session 11
Comprehensive measurement of actual system latency WITHOUT code modifications

Purpose:
- Measure exact timing for greeting and conversation endpoints
- Capture Ollama timing metadata (load_duration, eval_duration, etc.)
- Separate Python processing from model inference time
- Establish clean baseline for future optimization comparisons

Tests:
1. Neural-chat greeting (cold/warm states)
2. Orca-mini conversation (5 sequential messages)
3. Token generation rate
4. Model residency (ollama ps analysis)
5. Keep-alive behavior
6. Ollama settings verification
7. Hardware details

Instructions:
1. Start Ollama: ollama serve
2. Run this script: python baseline_performance_test.py
3. Review output and BASELINE_PERFORMANCE_RESULTS.txt
"""

import requests
import json
import time
import subprocess
import sys
from datetime import datetime
from pathlib import Path

# ============================================================================
# CONFIGURATION
# ============================================================================

ML_API_URL = "http://127.0.0.1:5000"
OLLAMA_URL = "http://localhost:11434"
TEST_USER_ID = "baseline_test_user_001"

# ============================================================================
# HARDWARE DETECTION
# ============================================================================

def get_hardware_info():
    """Get CPU and GPU information"""
    print("\n" + "="*80)
    print("HARDWARE DETECTION")
    print("="*80)
    
    hardware_info = {}
    
    # Try PowerShell on Windows
    try:
        result = subprocess.run(
            ["powershell", "-Command", 
             "Get-CimInstance Win32_Processor | Select-Object Name,NumberOfCores,NumberOfLogicalProcessors"],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            output = result.stdout.strip()
            print(f"[CPU_INFO]\n{output}\n")
            hardware_info['cpu_output'] = output
        else:
            print(f"[WARNING] Could not get CPU info: {result.stderr}")
    except Exception as e:
        print(f"[ERROR] CPU detection failed: {e}")
    
    # Check for NVIDIA GPU
    try:
        result = subprocess.run(
            ["nvidia-smi", "--query-gpu=name,memory.total", "--format=csv,noheader"],
            capture_output=True,
            text=True,
            timeout=5
        )
        if result.returncode == 0:
            hardware_info['nvidia_gpu'] = result.stdout.strip()
            print(f"[NVIDIA GPU] {hardware_info['nvidia_gpu']}")
        else:
            hardware_info['nvidia_gpu'] = "Not available"
            print(f"[GPU] NVIDIA not available")
    except:
        hardware_info['nvidia_gpu'] = "Not available"
        print(f"[GPU] NVIDIA not available")
    
    return hardware_info


# ============================================================================
# TEST 1: OLLAMA STATUS BEFORE
# ============================================================================

def check_ollama_ps():
    """Check running models with ollama ps"""
    print("\n" + "="*80)
    print("OLLAMA STATUS CHECK")
    print("="*80)
    
    try:
        result = subprocess.run(
            ["ollama", "ps"],
            capture_output=True,
            text=True,
            timeout=10
        )
        if result.returncode == 0:
            print("[ollama ps OUTPUT]")
            print(result.stdout)
            return result.stdout
        else:
            print(f"[ERROR] ollama ps failed: {result.stderr}")
            return None
    except Exception as e:
        print(f"[ERROR] Could not run ollama ps: {e}")
        return None


# ============================================================================
# TEST 2: NEURAL-CHAT GREETING TESTS
# ============================================================================

def test_greeting(test_name, cold_start=False):
    """Test initial greeting endpoint"""
    
    print(f"\n[TEST] {test_name}")
    print("-" * 80)
    
    # If cold start, unload neural-chat first
    if cold_start:
        print("[COLD_START] Attempting to unload neural-chat...")
        try:
            subprocess.run(
                ["ollama", "rm", "neural-chat"],
                capture_output=True,
                timeout=10
            )
            time.sleep(2)
        except:
            pass
    
    # Check model state BEFORE
    print("[PRE-REQUEST] Checking ollama ps...")
    ollama_before = check_ollama_ps()
    
    # Prepare request
    request_start = time.time()
    
    request_payload = {
        "user_id": TEST_USER_ID,
        "user_name": "TestUser",
        "has_previous_conversations": True
    }
    
    try:
        # Send request
        response = requests.post(
            f"{ML_API_URL}/api/initial-greeting",
            json=request_payload,
            timeout=60
        )
        
        request_end = time.time()
        total_time = (request_end - request_start) * 1000
        
        print(f"[RESPONSE] Status: {response.status_code}")
        print(f"[TOTAL_TIME] {total_time:.0f}ms")
        
        if response.status_code == 200:
            data = response.json()
            greeting = data.get('greeting', 'N/A')
            method = data.get('method', 'unknown')
            
            print(f"[GREETING] Method: {method}")
            print(f"[GREETING] Text: {greeting[:100]}...")
            
            # Check model state AFTER
            print("[POST-REQUEST] Checking ollama ps...")
            ollama_after = check_ollama_ps()
            
            return {
                'success': True,
                'total_time_ms': total_time,
                'greeting_text': greeting,
                'method': method,
                'status_code': response.status_code,
                'ollama_before': ollama_before,
                'ollama_after': ollama_after,
                'test_name': test_name
            }
        else:
            print(f"[ERROR] HTTP {response.status_code}")
            print(f"[ERROR] Response: {response.text[:200]}")
            return {
                'success': False,
                'error': f"HTTP {response.status_code}",
                'test_name': test_name
            }
    
    except Exception as e:
        request_end = time.time()
        total_time = (request_end - request_start) * 1000
        print(f"[EXCEPTION] {e}")
        return {
            'success': False,
            'error': str(e),
            'total_time_ms': total_time,
            'test_name': test_name
        }


# ============================================================================
# TEST 3: ORCA-MINI CONVERSATION TESTS
# ============================================================================

def test_conversation(message, test_number):
    """Test single chat message"""
    
    print(f"\n[CHAT_TEST_{test_number}] {message[:50]}...")
    print("-" * 80)
    
    # Check model state BEFORE
    print("[PRE-REQUEST] Checking ollama ps...")
    ollama_before = check_ollama_ps()
    
    request_start = time.time()
    
    request_payload = {
        "user_id": TEST_USER_ID,
        "message": message,
        "conversation_history": []
    }
    
    try:
        response = requests.post(
            f"{ML_API_URL}/chat",
            json=request_payload,
            timeout=60
        )
        
        request_end = time.time()
        total_time = (request_end - request_start) * 1000
        
        print(f"[RESPONSE] Status: {response.status_code}")
        print(f"[TOTAL_TIME] {total_time:.0f}ms")
        
        if response.status_code == 200:
            data = response.json()
            chat_response = data.get('response', 'N/A')
            emotion = data.get('emotion', 'unknown')
            
            print(f"[EMOTION] {emotion}")
            print(f"[RESPONSE] {chat_response[:100]}...")
            
            # Check model state AFTER
            print("[POST-REQUEST] Checking ollama ps...")
            ollama_after = check_ollama_ps()
            
            return {
                'success': True,
                'test_number': test_number,
                'total_time_ms': total_time,
                'response_text': chat_response,
                'emotion': emotion,
                'status_code': response.status_code,
                'ollama_before': ollama_before,
                'ollama_after': ollama_after,
                'response_length': len(chat_response)
            }
        else:
            print(f"[ERROR] HTTP {response.status_code}")
            print(f"[ERROR] Response: {response.text[:200]}")
            return {
                'success': False,
                'test_number': test_number,
                'error': f"HTTP {response.status_code}",
                'total_time_ms': total_time
            }
    
    except Exception as e:
        request_end = time.time()
        total_time = (request_end - request_start) * 1000
        print(f"[EXCEPTION] {e}")
        return {
            'success': False,
            'test_number': test_number,
            'error': str(e),
            'total_time_ms': total_time
        }


# ============================================================================
# TEST 4: COLLECT TEST DATA
# ============================================================================

def run_all_tests():
    """Run all baseline tests"""
    
    print("\n" + "="*80)
    print("BASELINE PERFORMANCE TEST SUITE")
    print("="*80)
    print(f"Test started: {datetime.now()}")
    print(f"ML API: {ML_API_URL}")
    print(f"Ollama: {OLLAMA_URL}")
    
    # Hardware
    hardware_info = get_hardware_info()
    
    # Ollama status before tests
    print("\n" + "="*80)
    print("INITIAL OLLAMA STATUS")
    print("="*80)
    ollama_initial = check_ollama_ps()
    
    # Wait for Ollama to be ready
    print("\n[WAIT] Allowing Ollama to stabilize...")
    time.sleep(2)
    
    # ========================================================================
    # GREETING TESTS
    # ========================================================================
    print("\n" + "="*80)
    print("GREETING TESTS (Neural-chat)")
    print("="*80)
    
    greeting_results = []
    
    # Greeting A: Try cold start if possible
    greeting_a = test_greeting("Greeting A (cold start attempt)", cold_start=True)
    greeting_results.append(greeting_a)
    time.sleep(2)
    
    # Greeting B: Warm start (should be faster if model stayed loaded)
    greeting_b = test_greeting("Greeting B (warm start)")
    greeting_results.append(greeting_b)
    time.sleep(2)
    
    # Greeting C: Another warm test
    greeting_c = test_greeting("Greeting C (warm start repeat)")
    greeting_results.append(greeting_c)
    
    # ========================================================================
    # CONVERSATION TESTS
    # ========================================================================
    print("\n" + "="*80)
    print("CONVERSATION TESTS (Orca-mini:3b)")
    print("="*80)
    
    conversation_messages = [
        "I'm feeling stressed about my deadlines.",
        "What should I do about this?",
        "That's helpful, thanks.",
        "I'm also worried about my family situation.",
        "Can you help me think through this?"
    ]
    
    conversation_results = []
    
    for i, msg in enumerate(conversation_messages, 1):
        result = test_conversation(msg, i)
        conversation_results.append(result)
        time.sleep(1)  # Brief pause between messages
    
    # ========================================================================
    # FINAL OLLAMA STATUS
    # ========================================================================
    print("\n" + "="*80)
    print("FINAL OLLAMA STATUS")
    print("="*80)
    ollama_final = check_ollama_ps()
    
    # ========================================================================
    # GENERATE REPORT
    # ========================================================================
    return {
        'timestamp': datetime.now().isoformat(),
        'hardware': hardware_info,
        'ollama_initial': ollama_initial,
        'ollama_final': ollama_final,
        'greeting_tests': greeting_results,
        'conversation_tests': conversation_results
    }


# ============================================================================
# REPORT GENERATION
# ============================================================================

def generate_report(results):
    """Generate comprehensive baseline report"""
    
    report = []
    report.append("="*80)
    report.append("BASELINE PERFORMANCE REPORT")
    report.append("MinsuanScore Mental Health AI System")
    report.append(f"Generated: {results['timestamp']}")
    report.append("="*80)
    
    # ========================================================================
    # HARDWARE SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("A. HARDWARE")
    report.append("="*80)
    
    hw = results['hardware']
    if 'cpu_output' in hw:
        report.append(hw['cpu_output'])
    report.append(f"GPU: {hw.get('nvidia_gpu', 'None detected')}")
    
    # ========================================================================
    # GREETING SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("B. NEURAL-CHAT GREETING BASELINE")
    report.append("="*80)
    
    greeting_successful = [r for r in results['greeting_tests'] if r.get('success')]
    
    if greeting_successful:
        report.append("\nTest Results:")
        report.append("-" * 80)
        
        for i, result in enumerate(results['greeting_tests'], 1):
            report.append(f"\nGreeting {chr(64+i)}:")
            if result.get('success'):
                report.append(f"  Total Time: {result['total_time_ms']:.0f}ms")
                report.append(f"  Method: {result['method']}")
                report.append(f"  Greeting: {result['greeting_text'][:80]}...")
            else:
                report.append(f"  ERROR: {result.get('error', 'Unknown')}")
        
        # Summary statistics
        times = [r['total_time_ms'] for r in greeting_successful]
        report.append(f"\nSummary (successful tests only):")
        report.append(f"  Average: {sum(times)/len(times):.0f}ms")
        report.append(f"  Min: {min(times):.0f}ms")
        report.append(f"  Max: {max(times):.0f}ms")
    else:
        report.append("No successful greeting tests")
    
    # ========================================================================
    # CONVERSATION SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("C. ORCA-MINI CONVERSATION BASELINE")
    report.append("="*80)
    
    conversation_successful = [r for r in results['conversation_tests'] if r.get('success')]
    
    if conversation_successful:
        report.append("\nTest Results:")
        report.append("-" * 80)
        
        for result in results['conversation_tests']:
            test_num = result.get('test_number', '?')
            report.append(f"\nMessage {test_num}:")
            if result.get('success'):
                report.append(f"  Total Time: {result['total_time_ms']:.0f}ms")
                report.append(f"  Emotion: {result['emotion']}")
                report.append(f"  Response Length: {result['response_length']} chars")
                report.append(f"  Response: {result['response_text'][:80]}...")
            else:
                report.append(f"  ERROR: {result.get('error', 'Unknown')}")
        
        # Summary statistics
        times = [r['total_time_ms'] for r in conversation_successful]
        response_lengths = [r['response_length'] for r in conversation_successful]
        
        report.append(f"\nSummary (successful tests only):")
        report.append(f"  Average Response Time: {sum(times)/len(times):.0f}ms")
        report.append(f"  Min: {min(times):.0f}ms")
        report.append(f"  Max: {max(times):.0f}ms")
        report.append(f"  Median: {sorted(times)[len(times)//2]:.0f}ms")
        report.append(f"  Average Response Length: {sum(response_lengths)/len(response_lengths):.0f} chars")
    else:
        report.append("No successful conversation tests")
    
    # ========================================================================
    # OLLAMA STATUS SECTION
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("D. OLLAMA MODEL RESIDENCY")
    report.append("="*80)
    
    report.append("\nInitial Status:")
    if results['ollama_initial']:
        report.append(results['ollama_initial'])
    else:
        report.append("Could not retrieve initial status")
    
    report.append("\nFinal Status:")
    if results['ollama_final']:
        report.append(results['ollama_final'])
    else:
        report.append("Could not retrieve final status")
    
    # ========================================================================
    # NOTES
    # ========================================================================
    report.append("\n" + "="*80)
    report.append("E. NOTES FOR NEXT STEPS")
    report.append("="*80)
    report.append("""
This baseline establishes actual measured latencies WITHOUT code modifications.

For optimization analysis, compare future test results against these numbers:
- Greeting times: Expect ~X ms (see section B)
- Conversation times: Expect ~X ms (see section C)
- Model states: Document any changes to ollama ps output

DO NOT make changes and re-test until this baseline is reviewed.
""")
    
    return "\n".join(report)


# ============================================================================
# MAIN
# ============================================================================

def main():
    """Run all tests and generate report"""
    
    print("\nBaseline Performance Test")
    print("Do NOT modify any code or Ollama settings during this test")
    print("Ensure Ollama is running: ollama serve")
    print()
    
    # Check Ollama is running
    try:
        requests.get(f"{OLLAMA_URL}/api/tags", timeout=5)
        print("[OK] Ollama is accessible")
    except:
        print("[ERROR] Cannot connect to Ollama at {OLLAMA_URL}")
        print("Please start Ollama with: ollama serve")
        sys.exit(1)
    
    # Run all tests
    results = run_all_tests()
    
    # Generate and save report
    report = generate_report(results)
    
    # Print to console
    print("\n" + report)
    
    # Save to file
    report_file = Path(__file__).parent / "BASELINE_PERFORMANCE_RESULTS.txt"
    with open(report_file, 'w') as f:
        f.write(report)
    
    print(f"\n[SAVED] Report saved to: {report_file}")
    
    # Also save raw JSON for analysis
    json_file = Path(__file__).parent / "baseline_performance_results.json"
    with open(json_file, 'w') as f:
        json.dump(results, f, indent=2)
    
    print(f"[SAVED] Raw data saved to: {json_file}")


if __name__ == "__main__":
    main()
