#!/usr/bin/env python3
"""Quick test of first message with FUSION_SHADOW_ENABLED=true"""

import requests
import json

BASE_URL = "http://127.0.0.1:5000"

# Just test one message quickly
message = "I'm stressed because of school."

print("Testing A1_English_Stress with FUSION_SHADOW_ENABLED=true...\n")

try:
    response = requests.post(
        f"{BASE_URL}/chat",
        json={
            'message': message,
            'user_id': 'test_user',
            'conversation_history': []
        },
        timeout=60
    )
    
    result = response.json()
    
    print(f"Status: {response.status_code}")
    print(f"Success: {result.get('success')}")
    print(f"Authoritative Emotion: {result.get('emotion')}")
    print(f"Confidence: {result.get('confidence'):.2f}")
    
    # Check fusion shadow fields
    fusion_fields = {
        'fusion_shadow_emotion': result.get('fusion_shadow_emotion'),
        'fusion_shadow_confidence': result.get('fusion_shadow_confidence'),
        'fusion_shadow_uncertain': result.get('fusion_shadow_uncertain'),
        'fusion_shadow_agreement': result.get('fusion_shadow_agreement'),
        'fusion_shadow_sources': result.get('fusion_shadow_sources'),
        'fusion_shadow_computation_ms': result.get('fusion_shadow_computation_ms'),
    }
    
    has_all_fusion_fields = all(v is not None for v in fusion_fields.values())
    
    print(f"\nFusion Shadow Fields Present:")
    for field, value in fusion_fields.items():
        status = "✓" if value is not None else "✗"
        print(f"  {status} {field}: {value}")
    
    if has_all_fusion_fields:
        print(f"\n✓ SUCCESS: All fusion_shadow_* fields present")
        print(f"  Fusion emotion: {fusion_fields['fusion_shadow_emotion']}")
        print(f"  Auth emotion: {result.get('emotion')}")
        print(f"  Agreement: {fusion_fields['fusion_shadow_agreement']}")
        print(f"  Computation time: {fusion_fields['fusion_shadow_computation_ms']:.1f}ms")
    else:
        print(f"\n✗ FAILED: Some fusion_shadow fields missing")
        missing = [k for k, v in fusion_fields.items() if v is None]
        print(f"  Missing: {missing}")
    
except Exception as e:
    print(f"ERROR: {e}")
