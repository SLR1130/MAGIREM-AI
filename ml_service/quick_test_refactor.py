#!/usr/bin/env python3
import requests
import time

start = time.time()
response = requests.post(
    'http://127.0.0.1:5000/chat',
    json={
        'message': 'I studied for weeks and still failed my exam.',
        'user_id': 1,
        'conversation_history': [],
    },
    timeout=60
)
elapsed = time.time() - start

if response.status_code == 200:
    data = response.json()
    print(f'✓ Success in {elapsed:.2f}s')
    print(f'  Emotion: {data.get("emotion")}')
    print(f'  Response: {data.get("response")[:100]}...')
    print(f'  ML System: {data.get("ml_system", "unknown")}')
else:
    print(f'✗ Error: {response.status_code}')
    print(response.text[:200])
