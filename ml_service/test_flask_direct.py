"""
PHASE 3 Extended: Test Flask API /chat endpoint directly
"""
import json
import requests
import time

print("="*80)
print("PHASE 3 EXTENDED: Flask API /chat Endpoint Test")
print("="*80)

# Start Flask API
import subprocess
import sys
import threading

def run_flask():
    """Run Flask in background"""
    import os
    env = os.environ.copy()
    subprocess.run([sys.executable, "unified_ml_api_with_model.py"], env=env, cwd=".")

# Start Flask in thread
print("\n[STARTUP] Starting Flask API...")
flask_thread = threading.Thread(target=run_flask, daemon=True)
flask_thread.start()

# Wait for Flask to start
time.sleep(10)

print("[STARTUP] Flask API started")

test_messages = [
    "I'm a little sad kasi hindi ako nakapasa sa exam today",
    "hindi ako nakapasa sa exam",
    "Kinakabihan ako ksi may exam ako bukas",
    "Masaya ako kasi pasado ako sa exam",
    "Galit ako ksi unfair yung grade ko sa exam",
    "May exam ako bukas",
    "Pakiramdam ko wala nang pag-asa at gusto ko nang mawala",
    "hey"
]

print("\n" + "="*80)
print("TESTING /chat ENDPOINT")
print("="*80)

results = []

for i, message in enumerate(test_messages, 1):
    print(f"\n[TEST {i}] Sending: {message}")
    
    try:
        response = requests.post(
            "http://127.0.0.1:5000/chat",
            json={
                "message": message,
                "user_id": 1,
                "conversation_history": []
            },
            timeout=30
        )
        
        if response.status_code == 200:
            data = response.json()
            emotion = data.get('emotion')
            emotion_source = data.get('emotion_source')
            confidence = data.get('confidence')
            probabilities = data.get('analysis', {}).get('probabilities', {})
            
            print(f"  Status: {response.status_code}")
            print(f"  Emotion: {emotion}")
            print(f"  Source: {emotion_source}")
            print(f"  Confidence: {confidence:.2%}")
            print(f"  Probabilities: {probabilities}")
            
            results.append({
                'message': message,
                'emotion': emotion,
                'source': emotion_source,
                'confidence': confidence,
                'probabilities': probabilities
            })
        else:
            print(f"  ✗ Error: {response.status_code}")
            print(f"  {response.text}")
            
    except Exception as e:
        print(f"  ✗ Exception: {e}")

print("\n" + "="*80)
print("SUMMARY")
print("="*80)

for i, result in enumerate(results, 1):
    print(f"{i}. {result['emotion']} ({result['confidence']:.2%}) from {result['source']}")
