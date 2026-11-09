#!/usr/bin/env python3
"""
FINAL LANGUAGE VERIFICATION: Single clean test run
Confirms language routing stability after port cleanup
"""
import requests
import time

FLASK_URL = "http://127.0.0.1:5000"

print("\n" + "="*80)
print("FINAL LANGUAGE VERIFICATION TEST")
print("="*80)

# Test 1: Single Kumusta ka?
print("\n[TEST 1] Single Request: Kumusta ka?")
print("-"*80)

r = requests.post(
    f"{FLASK_URL}/chat",
    json={'user_id': 'test_final', 'message': 'Kumusta ka?', 'session_id': 'FINAL-LANG-01'},
    timeout=30
)

if r.status_code == 200:
    data = r.json()
    response_text = data.get('response', '')
    ml_system = data.get('ml_system', 'unknown')
    response_source = data.get('response_source', 'unknown')
    
    print(f"Status: 200")
    print(f"Response: {response_text}")
    print(f"ml_system: {ml_system}")
    print(f"response_source: {response_source}")
    
    # Classify
    if any(w in response_text.lower() for w in ['kumusta', 'mabuti', 'paano', 'kami', 'naman', 'salamat']):
        result = "TAGALOG"
    elif any(w in response_text.lower() for w in ['how', 'okay', 'doing', 'hi', 'hello', 'translates']):
        result = "ENGLISH"
    else:
        result = "OTHER"
    
    print(f"Classification: {result}")
else:
    print(f"ERROR: Status {r.status_code}")

# Test 2-6: Five consecutive "Kumusta ka?"
print("\n[TEST 2-6] Five Consecutive Requests")
print("-"*80)

results = []
for i in range(1, 6):
    r = requests.post(
        f"{FLASK_URL}/chat",
        json={'user_id': 'test_final', 'message': 'Kumusta ka?', 'session_id': f'FINAL-LANG-{i:02d}'},
        timeout=30
    )
    
    if r.status_code == 200:
        data = r.json()
        response_text = data.get('response', '')
        ml_system = data.get('ml_system', 'unknown')
        
        # Classify
        if any(w in response_text.lower() for w in ['kumusta', 'mabuti', 'paano', 'kami', 'naman', 'salamat']):
            lang = "TL"
        elif any(w in response_text.lower() for w in ['how', 'okay', 'doing', 'hi', 'hello', 'translates']):
            lang = "EN"
        else:
            lang = "OTH"
        
        results.append({'run': i, 'lang': lang, 'ml_system': ml_system})
        print(f"  Run {i}: {lang:3s} ({ml_system})")
    else:
        results.append({'run': i, 'lang': 'ERR', 'ml_system': 'error'})
        print(f"  Run {i}: ERROR")
    
    time.sleep(0.3)

# Summary
tl_count = sum(1 for r in results if r['lang'] == 'TL')
en_count = sum(1 for r in results if r['lang'] == 'EN')
err_count = sum(1 for r in results if r['lang'] == 'ERR')

print("\n" + "="*80)
print("SUMMARY")
print("="*80)
print(f"Tagalog:   {tl_count}/5")
print(f"English:   {en_count}/5")
print(f"Errors:    {err_count}/5")

if tl_count == 5 and en_count == 0 and err_count == 0:
    print("\nVERDICT: PASS - Language routing stable")
else:
    print(f"\nVERDICT: MIXED - Inconsistent language responses")

print("="*80 + "\n")
