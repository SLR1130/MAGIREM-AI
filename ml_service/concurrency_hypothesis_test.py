#!/usr/bin/env python3
"""
CONCURRENCY HYPOTHESIS TEST - Verify Flask Single-Threaded Request Queueing

This test will:
1. Send 1 request (baseline)
2. Send 2 simultaneous requests (test queueing)
3. Send 3 simultaneous requests (test severe queueing)

Records timing, responses, and determines if requests queue/timeout.

NO PRODUCTION CHANGES - This is a diagnostic test only.
"""

import requests
import time
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime

print("=" * 80)
print("FLASK CONCURRENCY HYPOTHESIS TEST")
print("=" * 80)
print()

# Test messages
TEST_MESSAGES = {
    'A': "My friends surprised me after class today and we went out for food. It was honestly a really good day.",
    'B': "I studied for weeks and still failed my exam.",
    'C': "I have three projects due tomorrow and I haven't finished any of them.",
}

def send_chat_request(request_id, message, user_id=1):
    """Send a single /chat request and record timing"""
    
    print(f"  [REQ-{request_id}] Sending request at {datetime.now().strftime('%H:%M:%S.%f')[:-3]}")
    
    try:
        start_time = time.time()
        response = requests.post(
            "http://127.0.0.1:5000/chat",
            json={
                "message": message,
                "user_id": user_id,
                "conversation_history": [],
            },
            timeout=60
        )
        elapsed = time.time() - start_time
        
        if response.status_code == 200:
            data = response.json()
            return {
                'request_id': request_id,
                'status': 'success',
                'elapsed': elapsed,
                'http_status': 200,
                'emotion': data.get('emotion'),
                'response_len': len(data.get('response', '')),
                'ml_system': data.get('ml_system', 'unknown'),
                'error': None,
            }
        else:
            return {
                'request_id': request_id,
                'status': 'error',
                'elapsed': elapsed,
                'http_status': response.status_code,
                'error': f"HTTP {response.status_code}",
            }
    except requests.Timeout:
        elapsed = time.time() - start_time
        return {
            'request_id': request_id,
            'status': 'timeout',
            'elapsed': elapsed,
            'http_status': None,
            'error': "Request timeout (60s exceeded)",
        }
    except Exception as e:
        elapsed = time.time() - start_time
        return {
            'request_id': request_id,
            'status': 'error',
            'elapsed': elapsed,
            'http_status': None,
            'error': str(e),
        }

# ============================================================================
# TEST 1: Single Request (Baseline)
# ============================================================================
print("[TEST 1] Single Request (Baseline)")
print("-" * 80)

test1_start = time.time()
result_1 = send_chat_request('1A', TEST_MESSAGES['A'])
test1_elapsed = time.time() - test1_start

print(f"  [REQ-1A] Completed in {result_1['elapsed']:.2f}s")
print(f"    Status: {result_1['status']}")
print(f"    Emotion: {result_1.get('emotion', 'N/A')}")
print(f"    Response len: {result_1.get('response_len', 'N/A')}")
print(f"    ML System: {result_1.get('ml_system', 'N/A')}")

print()
print("[TEST 1 RESULT]")
print(f"  Single request: {result_1['elapsed']:.2f}s - Baseline established")
print()

# ============================================================================
# TEST 2: Two Simultaneous Requests
# ============================================================================
print("[TEST 2] Two Simultaneous Requests")
print("-" * 80)
print("Sending both requests at the same time...")
print()

test2_start = time.time()

# Send both requests concurrently using threads
with ThreadPoolExecutor(max_workers=2) as executor:
    futures = {
        executor.submit(send_chat_request, '2A', TEST_MESSAGES['A'], 2): '2A',
        executor.submit(send_chat_request, '2B', TEST_MESSAGES['B'], 3): '2B',
    }
    
    results_2 = {}
    for future in as_completed(futures):
        result = future.result()
        results_2[result['request_id']] = result

test2_elapsed = time.time() - test2_start

# Sort by request_id for reporting
for req_id in ['2A', '2B']:
    if req_id in results_2:
        r = results_2[req_id]
        print(f"  [{req_id}] Completed in {r['elapsed']:.2f}s")
        print(f"    Status: {r['status']}")
        print(f"    Emotion: {r.get('emotion', 'N/A')}")
        print(f"    ML System: {r.get('ml_system', 'N/A')}")
        if r.get('error'):
            print(f"    Error: {r['error']}")

print()
print("[TEST 2 RESULT]")
print(f"  Total time for 2 simultaneous requests: {test2_elapsed:.2f}s")
print(f"  Request 2A: {results_2.get('2A', {}).get('elapsed', 'N/A'):.2f}s")
print(f"  Request 2B: {results_2.get('2B', {}).get('elapsed', 'N/A'):.2f}s")

if results_2.get('2A', {}).get('status') == 'timeout' or results_2.get('2B', {}).get('status') == 'timeout':
    print(f"  ⚠️ One or both requests timed out!")
    
if test2_elapsed > 25:
    print(f"  ⚠️ Total time {test2_elapsed:.2f}s suggests queueing (baseline single: {result_1['elapsed']:.2f}s)")
    print(f"    If Flask is single-threaded, requests would queue: first request (~14s) + second request (~14s) = ~28s")
else:
    print(f"  ✓ Requests completed quickly ({test2_elapsed:.2f}s), no severe queueing evident")

print()

# ============================================================================
# TEST 3: Three Simultaneous Requests
# ============================================================================
print("[TEST 3] Three Simultaneous Requests")
print("-" * 80)
print("Sending all three requests at the same time...")
print()

test3_start = time.time()

# Send all three requests concurrently using threads
with ThreadPoolExecutor(max_workers=3) as executor:
    futures = {
        executor.submit(send_chat_request, '3A', TEST_MESSAGES['A'], 4): '3A',
        executor.submit(send_chat_request, '3B', TEST_MESSAGES['B'], 5): '3B',
        executor.submit(send_chat_request, '3C', TEST_MESSAGES['C'], 6): '3C',
    }
    
    results_3 = {}
    for future in as_completed(futures):
        result = future.result()
        results_3[result['request_id']] = result

test3_elapsed = time.time() - test3_start

# Sort by request_id for reporting
for req_id in ['3A', '3B', '3C']:
    if req_id in results_3:
        r = results_3[req_id]
        print(f"  [{req_id}] Completed in {r['elapsed']:.2f}s")
        print(f"    Status: {r['status']}")
        print(f"    Emotion: {r.get('emotion', 'N/A')}")
        print(f"    ML System: {r.get('ml_system', 'N/A')}")
        if r.get('error'):
            print(f"    Error: {r['error']}")

print()
print("[TEST 3 RESULT]")
print(f"  Total time for 3 simultaneous requests: {test3_elapsed:.2f}s")
print(f"  Request 3A: {results_3.get('3A', {}).get('elapsed', 'N/A'):.2f}s")
print(f"  Request 3B: {results_3.get('3B', {}).get('elapsed', 'N/A'):.2f}s")
print(f"  Request 3C: {results_3.get('3C', {}).get('elapsed', 'N/A'):.2f}s")

if any(r.get('status') == 'timeout' for r in results_3.values()):
    print(f"  ⚠️ One or more requests timed out!")
    
if test3_elapsed > 35:
    print(f"  ⚠️ Total time {test3_elapsed:.2f}s suggests severe queueing")
    print(f"    If Flask is single-threaded, requests would queue: 3 × ~14s = ~42s")
else:
    print(f"  ✓ Requests completed in {test3_elapsed:.2f}s")

print()

# ============================================================================
# ANALYSIS
# ============================================================================
print("=" * 80)
print("ANALYSIS")
print("=" * 80)
print()

print("BASELINE (Single Request):")
print(f"  Latency: {result_1['elapsed']:.2f}s")
print()

print("TWO REQUESTS:")
print(f"  Total time: {test2_elapsed:.2f}s")
print(f"  vs. Baseline × 2: {result_1['elapsed'] * 2:.2f}s")
if test2_elapsed > result_1['elapsed'] * 1.5:
    print(f"  ⚠️ Significant delay detected (queueing hypothesis supported)")
else:
    print(f"  ✓ No significant delay (requests may be processing in parallel)")
print()

print("THREE REQUESTS:")
print(f"  Total time: {test3_elapsed:.2f}s")
print(f"  vs. Baseline × 3: {result_1['elapsed'] * 3:.2f}s")
if test3_elapsed > result_1['elapsed'] * 2:
    print(f"  ⚠️ Significant delay detected (queueing hypothesis strongly supported)")
else:
    print(f"  ✓ No significant delay (requests may be processing in parallel)")
print()

# ============================================================================
# HYPOTHESIS EVALUATION
# ============================================================================
print("=" * 80)
print("HYPOTHESIS EVALUATION: Flask Single-Threaded Request Queueing")
print("=" * 80)
print()

timeouts_detected = (
    results_2.get('2A', {}).get('status') == 'timeout' or
    results_2.get('2B', {}).get('status') == 'timeout' or
    results_3.get('3A', {}).get('status') == 'timeout' or
    results_3.get('3B', {}).get('status') == 'timeout' or
    results_3.get('3C', {}).get('status') == 'timeout'
)

queueing_detected = test2_elapsed > result_1['elapsed'] * 1.5

print("Evidence for queueing hypothesis:")
print(f"  1. Request timeouts detected: {'YES ✓' if timeouts_detected else 'NO'}")
print(f"  2. Latency increases with concurrent requests: {'YES ✓' if queueing_detected else 'NO'}")
print(f"  3. Flask app.run() has no 'threaded=True': (not checked in this test)")
print()

if timeouts_detected:
    print("✓ STRONG EVIDENCE: Timeouts occurred with concurrent requests")
    print("  This supports the Flask queueing hypothesis")
elif queueing_detected:
    print("✓ MODERATE EVIDENCE: Latency increased with concurrent requests")
    print("  This suggests queueing but not definitive timeout behavior")
else:
    print("✗ NO EVIDENCE: Concurrent requests completed without delays")
    print("  Flask single-threaded queueing may NOT be the issue")
    print("  Root cause may be elsewhere (database, memory, Ollama)")

print()
print("=" * 80)
print("TEST COMPLETE")
print("=" * 80)
print()
print("NOTES:")
print("  • Server logs should show timestamps of [CHAT] entries")
print("  • If queueing exists, [CHAT] entries will NOT overlap in time")
print("  • If threading works, [CHAT] entries WILL overlap")
print("  • Check server output for evidence of sequential vs. parallel processing")
print()
