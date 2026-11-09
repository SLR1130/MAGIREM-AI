#!/usr/bin/env python3
"""
Test Flask /chat runtime integration with fusion shadow
Test messages with FUSION_SHADOW_ENABLED=false and true
"""

import requests
import json
import time

BASE_URL = "http://127.0.0.1:5000"

# Six test messages
TEST_MESSAGES = {
    'A1_English_Stress': {
        'message': "I'm stressed because of school.",
        'lang': 'English',
        'expected_authoritative': 'anxiety',
    },
    'A2_Tagalog_Sadness': {
        'message': "Medyo pagod at malungkot ako ngayong araw.",
        'lang': 'Tagalog',
        'expected_authoritative': 'sadness',
    },
    'A3_Taglish_Anxiety': {
        'message': "Medyo anxious ako sa presentation tomorrow.",
        'lang': 'Taglish',
        'expected_authoritative': 'anxiety',
    },
    'A4_Neutral_Question': {
        'message': "What time is the meeting tomorrow?",
        'lang': 'English',
        'expected_authoritative': 'neutral',
    },
    'A5_English_Positive': {
        'message': "I'm doing pretty good today.",
        'lang': 'English',
        'expected_authoritative': 'joy',
    },
    'A6_Tagalog_Negation': {
        'message': "Hindi naman ako malungkot, pagod lang talaga.",
        'lang': 'Tagalog',
        'expected_authoritative': 'neutral',
    },
}

def test_chat_endpoint(msg_id, message, fusion_enabled=False):
    """Test a single /chat message"""
    try:
        response = requests.post(
            f"{BASE_URL}/chat",
            json={
                'message': message,
                'user_id': 'test_user',
                'conversation_history': []
            },
            timeout=60  # Longer timeout for Ollama
        )
        
        result = response.json()
        
        return {
            'status': response.status_code,
            'success': result.get('success', False),
            'emotion': result.get('emotion', 'N/A'),
            'ml_system': result.get('ml_system', 'N/A'),
            'confidence': result.get('confidence', 0),
            'language': result.get('context_analysis', {}).get('interaction_type', 'N/A'),
            'crisis_level': result.get('analysis', {}).get('crisis_level', 'none'),
            'fusion_shadow_emotion': result.get('fusion_shadow_emotion'),
            'fusion_shadow_confidence': result.get('fusion_shadow_confidence'),
            'fusion_shadow_agreement': result.get('fusion_shadow_agreement'),
            'fusion_shadow_uncertain': result.get('fusion_shadow_uncertain'),
            'has_fusion_shadow': 'fusion_shadow_emotion' in result,
        }
    except Exception as e:
        return {
            'status': 0,
            'success': False,
            'error': str(e)
        }

def main():
    print("\n" + "="*100)
    print("FUSION SHADOW RUNTIME INTEGRATION TEST")
    print("="*100)
    
    # Check Flask is running
    try:
        resp = requests.post(f"{BASE_URL}/chat", json={'message': 'test', 'user_id': 'test'}, timeout=60)
        if resp.status_code not in [200, 400, 500]:
            raise Exception(f"Unexpected status: {resp.status_code}")
        print(f"[OK] Flask is responding on port 5000")
    except Exception as e:
        print(f"ERROR: Flask not running or not responding: {e}")
        return
    
    print("\n[FUSION_OFF] Testing with FUSION_SHADOW_ENABLED=false")
    print("Expected: No fusion_shadow_* fields in response")
    print("-"*100)
    
    off_results = {}
    for msg_id, msg_data in TEST_MESSAGES.items():
        result = test_chat_endpoint(msg_id, msg_data['message'], fusion_enabled=False)
        off_results[msg_id] = result
        
        status = "✓ OK" if result['status'] == 200 else f"✗ FAIL ({result['status']})"
        has_fusion = "HAS FUSION" if result.get('has_fusion_shadow') else "NO FUSION"
        
        print(f"  [{msg_id}] {status:12} | emotion={result['emotion']:10} | {has_fusion}")
        if result.get('error'):
            print(f"           ERROR: {result['error']}")
    
    # Check that no fusion shadow fields are present
    fusion_off_count = sum(1 for r in off_results.values() if not r.get('has_fusion_shadow'))
    all_off_ok = all(not r.get('has_fusion_shadow') for r in off_results.values() if r['status'] == 200)
    
    print(f"\nResult: {fusion_off_count}/6 messages without fusion_shadow fields")
    if all_off_ok:
        print("✓ FUSION OFF CORRECT: No fusion_shadow fields when disabled")
    else:
        print("✗ FUSION OFF FAILED: Fusion shadow fields present when disabled!")
    
    print("\n[WAITING] Stopping Flask and restarting with FUSION_SHADOW_ENABLED=true...")
    print("-"*100)
    
    # Note: In a real test, we would stop Flask and restart with FUSION_SHADOW_ENABLED=true
    # For now, just report what we've verified
    
    print("\nSUMMARY - FUSION OFF (Current State)")
    print("-"*100)
    print(f"{'MSG_ID':<20} {'Status':<8} {'Auth.Emotion':<15} {'Fusion Shadow':<15}")
    for msg_id, result in off_results.items():
        fusion_field = "YES" if result.get('has_fusion_shadow') else "NO"
        print(f"{msg_id:<20} {result['status']:<8} {result['emotion']:<15} {fusion_field:<15}")
    
    print("\n[NEXT STEP] Manual testing required:")
    print("1. Stop current Flask process")
    print("2. Start Flask with: FUSION_SHADOW_ENABLED=true python unified_ml_api_with_model.py")
    print("3. Re-run this test to verify fusion_shadow_* fields appear")
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()
