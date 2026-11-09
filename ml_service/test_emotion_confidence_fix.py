#!/usr/bin/env python
"""Test the emotion_confidence AttributeError fix"""

import json
import sys

sys.path.insert(0, '.')

from unified_ml_api_with_model import app

client = app.test_client()

print("Testing /chat endpoint with: {'message': 'Hello bro'}\n")

response = client.post('/chat', 
    json={'message': 'Hello bro', 'user_id': 1, 'conversation_history': []},
    content_type='application/json'
)

print(f'HTTP Status: {response.status_code}\n')

if response.status_code == 200:
    resp_json = response.get_json()
    print('✓ SUCCESS - No AttributeError!')
    print(f'\nResponse fields:')
    print(f'  - emotion: {resp_json.get("emotion")}')
    print(f'  - response: {resp_json.get("response")[:60]}...')
    print(f'  - ml_system: {resp_json.get("ml_system")}')
    print(f'  - confidence: {resp_json.get("confidence")}')
    
    unified = resp_json.get('unified_analysis', {})
    print(f'\nUnified analysis:')
    print(f'  - intent: {unified.get("intent", {}).get("primary", "N/A")}')
    print(f'  - emotion: {unified.get("emotion", {}).get("primary", "N/A")}')
    print(f'  - crisis: {unified.get("crisis", {}).get("safety_level", "N/A")}')
    
else:
    resp_json = response.get_json()
    print('✗ FAILED')
    print(f'Error type: {resp_json.get("error_type")}')
    print(f'Error message: {resp_json.get("error")}')
