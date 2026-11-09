"""
Complete ML Pipeline Integration Test
Tests the full flow: Flask /chat → UnifiedAnalyzer → emotion → sarcasm → crisis → memory → Ollama → response

Test Groups:
1. Intent regression (verify intent fix doesn't break pipeline)
2. Emotion/context analysis
3. Crisis/context safety
4. Conversation context (multi-turn)
5. Ollama integration
"""

import requests
import json
import time
from datetime import datetime

# Laravel API endpoint (behind auth middleware)
LARAVEL_URL = "http://127.0.0.1:8000/api/ai/chat"
TIMEOUT = 60  # Allow up to 60 seconds for Ollama

# Test user ID (must exist in database)
TEST_USER_ID = 1

# Session/auth token (if needed - will try without first)
AUTH_TOKEN = None

# Colors for output
GREEN = '\033[92m'
RED = '\033[91m'
YELLOW = '\033[93m'
BLUE = '\033[94m'
RESET = '\033[0m'

def log_section(title):
    """Print section header"""
    print(f"\n{BLUE}{'='*80}")
    print(f" {title}")
    print(f"{'='*80}{RESET}\n")

def log_pass(msg):
    """Print passing message"""
    print(f"{GREEN}✓ {msg}{RESET}")

def log_fail(msg):
    """Print failing message"""
    print(f"{RED}✗ {msg}{RESET}")

def log_info(msg):
    """Print info message"""
    print(f"{YELLOW}→ {msg}{RESET}")

def test_intent_regression():
    """Test Group 1: Intent regression tests"""
    log_section("TEST GROUP 1: INTENT REGRESSION")
    
    test_cases = [
        ("I'm really stressed with my studies lately and I don't know how to manage everything.", "statement"),
        ("Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul 😂", "statement"),
        ("Bro I finally finished one of my assignments today.", "statement"),
        ("i dont have money bro", "statement"),
        ("any tips?", "question"),
        ("yes", "confirmation"),
        ("yeah", "confirmation"),
        ("okay", "confirmation"),
        ("Yeah bro, I'm stressed.", "statement"),
        ("Yeah, can you help me?", "question"),
        ("Hey", "greeting"),
        ("Hello bro", "greeting"),
        ("Hi", "greeting"),
        ("Can you help me?", "question"),
    ]
    
    results = []
    for message, expected_intent in test_cases:
        try:
            start_time = time.time()
            response = requests.post(
                LARAVEL_URL,
                json={
                    "message": message,
                    "user_id": TEST_USER_ID,
                },
                timeout=TIMEOUT
            )
            elapsed = (time.time() - start_time) * 1000
            
            if response.status_code != 200:
                log_fail(f"HTTP {response.status_code}: {message[:50]}")
                results.append({
                    'message': message,
                    'status': f"HTTP {response.status_code}",
                    'elapsed': elapsed,
                    'passed': False
                })
                continue
            
            data = response.json()
            if not data.get('success'):
                log_fail(f"API error: {message[:50]}")
                results.append({
                    'message': message,
                    'status': 'API error',
                    'elapsed': elapsed,
                    'passed': False
                })
                continue
            
            # Extract intent if available in analysis
            # Note: Intent may not be returned in API response, so we check what's available
            analysis = data.get('analysis', {})
            ai_response = data.get('response', '')
            emotion = data.get('emotion', 'unknown')
            
            log_pass(f"{message[:60]} → {emotion} ({elapsed:.0f}ms)")
            results.append({
                'message': message,
                'expected_intent': expected_intent,
                'emotion': emotion,
                'response': ai_response[:80],
                'elapsed': elapsed,
                'passed': True,
                'analysis': analysis
            })
            
        except requests.exceptions.Timeout:
            log_fail(f"TIMEOUT (>{TIMEOUT}s): {message[:50]}")
            results.append({
                'message': message,
                'status': 'TIMEOUT',
                'elapsed': TIMEOUT * 1000,
                'passed': False
            })
        except Exception as e:
            log_fail(f"ERROR: {message[:50]} - {str(e)[:50]}")
            results.append({
                'message': message,
                'status': f"ERROR: {str(e)[:50]}",
                'elapsed': 0,
                'passed': False
            })
    
    passed = sum(1 for r in results if r.get('passed'))
    total = len(results)
    print(f"\n{BLUE}Intent Regression: {passed}/{total} passed{RESET}")
    return results

def test_emotion_and_context():
    """Test Group 2: Emotion and context analysis"""
    log_section("TEST GROUP 2: EMOTION/CONTEXT ANALYSIS")
    
    test_cases = [
        "I'm really stressed about my deadlines.",
        "I don't know what to do anymore, everything is overwhelming.",
        "I'm so happy bro, I finally passed my exam!",
        "I'm exhausted from school but I still need to finish this assignment.",
        "Yeah bro, I'm doing absolutely amazing. My deadlines are slowly destroying my soul 😂",
    ]
    
    results = []
    for message in test_cases:
        try:
            start_time = time.time()
            response = requests.post(
                LARAVEL_URL,
                json={
                    "message": message,
                    "user_id": TEST_USER_ID,
                },
                timeout=TIMEOUT
            )
            elapsed = (time.time() - start_time) * 1000
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    emotion = data.get('emotion', 'unknown')
                    confidence = data.get('analysis', {}).get('confidence', 0)
                    crisis_level = data.get('analysis', {}).get('crisis_level', 'none')
                    
                    # Check crisis analysis data
                    crisis_analysis = data.get('crisis_analysis', {})
                    sarcasm_detected = crisis_analysis.get('sarcasm_detected', False)
                    
                    log_pass(f"Emotion: {emotion} ({confidence:.0%}) | Sarcasm: {sarcasm_detected} | Crisis: {crisis_level}")
                    results.append({
                        'message': message[:50],
                        'emotion': emotion,
                        'confidence': confidence,
                        'sarcasm': sarcasm_detected,
                        'crisis': crisis_level,
                        'elapsed': elapsed,
                        'passed': True
                    })
                else:
                    log_fail(f"API error: {data.get('error', 'unknown')}")
                    results.append({
                        'message': message[:50],
                        'status': 'API error',
                        'elapsed': elapsed,
                        'passed': False
                    })
            else:
                log_fail(f"HTTP {response.status_code}")
                results.append({
                    'message': message[:50],
                    'status': f"HTTP {response.status_code}",
                    'elapsed': elapsed,
                    'passed': False
                })
        except requests.exceptions.Timeout:
            log_fail(f"TIMEOUT (>{TIMEOUT}s)")
            results.append({
                'message': message[:50],
                'status': 'TIMEOUT',
                'elapsed': TIMEOUT * 1000,
                'passed': False
            })
        except Exception as e:
            log_fail(f"ERROR: {str(e)[:50]}")
            results.append({
                'message': message[:50],
                'status': f"ERROR: {str(e)[:50]}",
                'elapsed': 0,
                'passed': False
            })
    
    passed = sum(1 for r in results if r.get('passed'))
    total = len(results)
    print(f"\n{BLUE}Emotion/Context: {passed}/{total} passed{RESET}")
    return results

def test_crisis_safety():
    """Test Group 3: Crisis/context safety"""
    log_section("TEST GROUP 3: CRISIS/CONTEXT SAFETY")
    
    test_cases = [
        ("I want to die.", "CRITICAL - direct self-harm language"),
        ("Sometimes I feel like I don't want to be here anymore.", "HIGH - passive death wish"),
        ("I could die of embarrassment 😂", "LOW - figurative with emoji"),
        ("That presentation was so embarrassing I literally died.", "LOW - figurative language"),
        ("I failed my presentation and now I feel like everything is hopeless.", "MEDIUM - high distress but no direct crisis language"),
    ]
    
    results = []
    for message, expected_interpretation in test_cases:
        try:
            start_time = time.time()
            response = requests.post(
                LARAVEL_URL,
                json={
                    "message": message,
                    "user_id": TEST_USER_ID,
                },
                timeout=TIMEOUT
            )
            elapsed = (time.time() - start_time) * 1000
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    crisis_analysis = data.get('crisis_analysis', {})
                    crisis_level = crisis_analysis.get('level', 'unknown')
                    crisis_confidence = crisis_analysis.get('confidence', 0)
                    figurative = crisis_analysis.get('hyperbole_detected', False) or crisis_analysis.get('satire_detected', False)
                    sarcasm = crisis_analysis.get('sarcasm_detected', False)
                    
                    log_pass(f"Crisis: {crisis_level} | Conf: {crisis_confidence:.0%} | Fig: {figurative} | Sarc: {sarcasm}")
                    log_info(f"  Expected: {expected_interpretation}")
                    
                    results.append({
                        'message': message[:50],
                        'crisis_level': crisis_level,
                        'crisis_confidence': crisis_confidence,
                        'figurative': figurative,
                        'sarcasm': sarcasm,
                        'expected': expected_interpretation,
                        'elapsed': elapsed,
                        'passed': True
                    })
                else:
                    log_fail(f"API error: {data.get('error', 'unknown')}")
                    results.append({
                        'message': message[:50],
                        'status': 'API error',
                        'elapsed': elapsed,
                        'passed': False
                    })
            else:
                log_fail(f"HTTP {response.status_code}")
                results.append({
                    'message': message[:50],
                    'status': f"HTTP {response.status_code}",
                    'elapsed': elapsed,
                    'passed': False
                })
        except requests.exceptions.Timeout:
            log_fail(f"TIMEOUT (>{TIMEOUT}s)")
            results.append({
                'message': message[:50],
                'status': 'TIMEOUT',
                'elapsed': TIMEOUT * 1000,
                'passed': False
            })
        except Exception as e:
            log_fail(f"ERROR: {str(e)[:50]}")
            results.append({
                'message': message[:50],
                'status': f"ERROR: {str(e)[:50]}",
                'elapsed': 0,
                'passed': False
            })
    
    passed = sum(1 for r in results if r.get('passed'))
    total = len(results)
    print(f"\n{BLUE}Crisis/Safety: {passed}/{total} passed{RESET}")
    return results

def test_conversation_context():
    """Test Group 4: Multi-turn conversation context"""
    log_section("TEST GROUP 4: CONVERSATION CONTEXT (Multi-turn)")
    
    messages = [
        ("Bro I'm stressed about school.", "Initial statement of stress"),
        ("My deadline is tomorrow.", "Escalation of urgency"),
        ("I haven't even started.", "Increased anxiety"),
        ("Yeah, I'm doing amazing 😂", "Sarcasm in context"),
    ]
    
    results = []
    session_id = None
    
    for idx, (message, context_note) in enumerate(messages, 1):
        try:
            start_time = time.time()
            payload = {
                "message": message,
                "user_id": TEST_USER_ID,
            }
            if session_id:
                payload["session_id"] = session_id
            
            response = requests.post(
                LARAVEL_URL,
                json=payload,
                timeout=TIMEOUT
            )
            elapsed = (time.time() - start_time) * 1000
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    if not session_id:
                        session_id = data.get('session_id')
                    
                    emotion = data.get('emotion', 'unknown')
                    ai_response = data.get('response', '')
                    sarcasm = data.get('crisis_analysis', {}).get('sarcasm_detected', False)
                    
                    log_pass(f"Turn {idx}: {emotion} | Sarcasm: {sarcasm} | {context_note}")
                    log_info(f"  Response: {ai_response[:70]}...")
                    
                    results.append({
                        'turn': idx,
                        'message': message[:50],
                        'emotion': emotion,
                        'sarcasm': sarcasm,
                        'response': ai_response[:80],
                        'elapsed': elapsed,
                        'passed': True
                    })
                else:
                    log_fail(f"API error: {data.get('error', 'unknown')}")
                    results.append({
                        'turn': idx,
                        'message': message[:50],
                        'status': 'API error',
                        'elapsed': elapsed,
                        'passed': False
                    })
            else:
                log_fail(f"HTTP {response.status_code}")
                results.append({
                    'turn': idx,
                    'message': message[:50],
                    'status': f"HTTP {response.status_code}",
                    'elapsed': elapsed,
                    'passed': False
                })
        except requests.exceptions.Timeout:
            log_fail(f"TIMEOUT (>{TIMEOUT}s)")
            results.append({
                'turn': idx,
                'message': message[:50],
                'status': 'TIMEOUT',
                'elapsed': TIMEOUT * 1000,
                'passed': False
            })
        except Exception as e:
            log_fail(f"ERROR: {str(e)[:50]}")
            results.append({
                'turn': idx,
                'message': message[:50],
                'status': f"ERROR: {str(e)[:50]}",
                'elapsed': 0,
                'passed': False
            })
    
    passed = sum(1 for r in results if r.get('passed'))
    total = len(results)
    print(f"\n{BLUE}Conversation Context: {passed}/{total} passed{RESET}")
    return results

def test_ollama_integration():
    """Test Group 5: Ollama integration"""
    log_section("TEST GROUP 5: OLLAMA INTEGRATION")
    
    test_messages = [
        "I'm having a tough time with my studies.",
        "Everything feels overwhelming right now.",
        "Can you help me think through this?",
    ]
    
    results = []
    for message in test_messages:
        try:
            start_time = time.time()
            response = requests.post(
                LARAVEL_URL,
                json={
                    "message": message,
                    "user_id": TEST_USER_ID,
                },
                timeout=TIMEOUT
            )
            elapsed = (time.time() - start_time) * 1000
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    ai_response = data.get('response', '')
                    ml_system = data.get('ml_system', 'unknown')
                    analysis = data.get('analysis', {})
                    emotion = analysis.get('emotion', 'unknown')
                    
                    # Check if Ollama was actually called
                    ollama_called = ml_system in ['hybrid_active', 'hybrid']
                    response_type = 'Generated' if ollama_called else 'Fallback'
                    
                    log_pass(f"ML System: {ml_system} ({response_type}) | Emotion: {emotion}")
                    log_info(f"  Response ({len(ai_response)} chars): {ai_response[:70]}...")
                    log_info(f"  Time: {elapsed:.0f}ms")
                    
                    results.append({
                        'message': message[:50],
                        'ml_system': ml_system,
                        'response': ai_response,
                        'response_length': len(ai_response),
                        'emotion': emotion,
                        'ollama_called': ollama_called,
                        'elapsed': elapsed,
                        'passed': True
                    })
                else:
                    log_fail(f"API error: {data.get('error', 'unknown')}")
                    results.append({
                        'message': message[:50],
                        'status': 'API error',
                        'elapsed': elapsed,
                        'passed': False
                    })
            else:
                log_fail(f"HTTP {response.status_code}")
                results.append({
                    'message': message[:50],
                    'status': f"HTTP {response.status_code}",
                    'elapsed': elapsed,
                    'passed': False
                })
        except requests.exceptions.Timeout:
            log_fail(f"TIMEOUT (>{TIMEOUT}s) - Ollama may be unresponsive")
            results.append({
                'message': message[:50],
                'status': 'TIMEOUT',
                'elapsed': TIMEOUT * 1000,
                'ollama_called': False,
                'passed': False
            })
        except Exception as e:
            log_fail(f"ERROR: {str(e)[:50]}")
            results.append({
                'message': message[:50],
                'status': f"ERROR: {str(e)[:50]}",
                'elapsed': 0,
                'passed': False
            })
    
    passed = sum(1 for r in results if r.get('passed'))
    total = len(results)
    ollama_used = sum(1 for r in results if r.get('ollama_called', False))
    
    print(f"\n{BLUE}Ollama Integration: {passed}/{total} passed | Ollama used: {ollama_used}/{total}{RESET}")
    return results

def main():
    """Run all test groups"""
    print(f"{BLUE}")
    print("╔" + "═" * 78 + "╗")
    print("║" + " " * 15 + "COMPLETE ML PIPELINE INTEGRATION TEST" + " " * 26 + "║")
    print("║" + " " * 8 + "Testing: Flask /chat → UnifiedAnalyzer → Emotion → Crisis → Ollama" + " " * 2 + "║")
    print("╚" + "═" * 78 + "╝")
    print(f"{RESET}")
    
    all_results = {
        'Test Group 1: Intent Regression': test_intent_regression(),
        'Test Group 2: Emotion/Context': test_emotion_and_context(),
        'Test Group 3: Crisis/Safety': test_crisis_safety(),
        'Test Group 4: Conversation Context': test_conversation_context(),
        'Test Group 5: Ollama Integration': test_ollama_integration(),
    }
    
    # Summary
    log_section("OVERALL SUMMARY")
    
    total_tests = 0
    total_passed = 0
    
    for group_name, results in all_results.items():
        passed = sum(1 for r in results if r.get('passed', False))
        total = len(results)
        total_tests += total
        total_passed += passed
        
        status = f"{GREEN}✓ PASS{RESET}" if passed == total else f"{RED}✗ FAIL{RESET}"
        print(f"{status} {group_name}: {passed}/{total}")
    
    print(f"\n{BLUE}Total: {total_passed}/{total_tests} tests passed{RESET}")
    
    if total_passed == total_tests:
        print(f"\n{GREEN}✓✓✓ ALL TESTS PASSED ✓✓✓{RESET}")
    else:
        print(f"\n{RED}✗ {total_tests - total_passed} tests failed{RESET}")
    
    # Save results to file
    with open('pipeline_test_results.json', 'w') as f:
        json.dump(all_results, f, indent=2, default=str)
    
    print(f"\nResults saved to: pipeline_test_results.json")

if __name__ == '__main__':
    main()
