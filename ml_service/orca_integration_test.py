#!/usr/bin/env python3
"""
ORCA-MINI:3B Integration Test
Tests all 8 benchmark prompts through the actual capstone application pipeline
Records: response time, emotion, context understanding, naturalness, etc.
"""

import requests
import json
import time
from datetime import datetime

# Test prompts (exact same 8 as benchmark)
TEST_PROMPTS = [
    {
        "id": "english_happiness",
        "category": "English - Positive",
        "message": "My friends surprised me today and it made my whole day.",
    },
    {
        "id": "english_sadness",
        "category": "English - Negative",
        "message": "I studied for weeks and still failed my exam.",
    },
    {
        "id": "english_anxiety",
        "category": "English - Anxiety",
        "message": "I have three projects due tomorrow and I haven't finished any of them.",
    },
    {
        "id": "english_sarcasm",
        "category": "English - Sarcasm",
        "message": "My professor just gave us three deadlines for tomorrow. What a wonderful surprise 😂",
    },
    {
        "id": "relationship",
        "category": "Relationship Issue",
        "message": "Yeah about that, my friend said that my girlfriend is cheating on me.",
    },
    {
        "id": "tagalog",
        "category": "Tagalog",
        "message": "Grabe naman, ang saya talaga. Tatlong deadline bukas 😂",
    },
    {
        "id": "taglish",
        "category": "Taglish (Mixed)",
        "message": "Bro sobrang stressed ako, three deadlines tomorrow tapos wala pa akong natatapos.",
    },
    {
        "id": "direct_question",
        "category": "Direct Question",
        "message": "How should I prepare for my exam tomorrow?",
    }
]

class OrcaIntegrationTester:
    def __init__(self, api_url="http://localhost:5000"):
        self.api_url = api_url
        self.results = []
        
    def run_all_tests(self):
        """Run all 8 test prompts"""
        print("=" * 80)
        print("ORCA-MINI:3B INTEGRATION TEST")
        print("=" * 80)
        print(f"\nStarting {len(TEST_PROMPTS)} end-to-end tests\n")
        
        for i, test in enumerate(TEST_PROMPTS, 1):
            print(f"\n[{i}/{len(TEST_PROMPTS)}] {test['category']}")
            print(f"    Message: {test['message'][:60]}...")
            
            result = self.test_prompt(test)
            self.results.append(result)
            
            # Print immediate feedback
            if result['success']:
                print(f"    ✓ Response time: {result['response_time']:.2f}s")
                print(f"    ✓ Emotion: {result['emotion']} ({result['confidence']:.0%})")
                print(f"    ✓ Response: {result['ai_response'][:80]}...")
            else:
                print(f"    ✗ Error: {result['error']}")
        
        # Print summary
        self.print_summary()
        
    def test_prompt(self, test):
        """Test a single prompt through the /chat endpoint"""
        try:
            start = time.time()
            
            response = requests.post(
                f"{self.api_url}/chat",
                json={
                    "message": test['message'],
                    "user_id": "test_user",
                    "session_id": "test_session",
                },
                timeout=60,  # Give orca time to respond
            )
            
            elapsed = time.time() - start
            
            if response.status_code == 200:
                data = response.json()
                
                return {
                    'success': True,
                    'test_id': test['id'],
                    'category': test['category'],
                    'message': test['message'],
                    'response_time': elapsed,
                    'http_status': response.status_code,
                    'emotion': data.get('emotion', 'unknown'),
                    'confidence': data.get('confidence', 0),
                    'ai_response': data.get('response', '(empty)'),
                    'analysis': data.get('analysis', {}),
                    'model_type': data.get('analysis', {}).get('model_type', 'unknown'),
                    'used_fallback': data.get('analysis', {}).get('model_type') == 'fallback',
                    'error': None,
                }
            else:
                return {
                    'success': False,
                    'test_id': test['id'],
                    'category': test['category'],
                    'message': test['message'],
                    'response_time': elapsed,
                    'http_status': response.status_code,
                    'error': f"HTTP {response.status_code}: {response.text[:100]}",
                }
                
        except requests.exceptions.Timeout:
            return {
                'success': False,
                'test_id': test['id'],
                'category': test['category'],
                'message': test['message'],
                'response_time': 60.0,
                'http_status': None,
                'error': 'Timeout (60 seconds)',
            }
        except Exception as e:
            return {
                'success': False,
                'test_id': test['id'],
                'category': test['category'],
                'message': test['message'],
                'response_time': 0,
                'http_status': None,
                'error': str(e)[:100],
            }
    
    def print_summary(self):
        """Print test summary"""
        print("\n" + "=" * 80)
        print("TEST RESULTS SUMMARY")
        print("=" * 80 + "\n")
        
        successful = [r for r in self.results if r['success']]
        failed = [r for r in self.results if not r['success']]
        
        print(f"Successful: {len(successful)}/{len(self.results)}")
        print(f"Failed: {len(failed)}/{len(self.results)}")
        
        if successful:
            times = [r['response_time'] for r in successful]
            print(f"\nResponse Time Statistics:")
            print(f"  Average: {sum(times)/len(times):.2f}s")
            print(f"  Min: {min(times):.2f}s")
            print(f"  Max: {max(times):.2f}s")
        
        if failed:
            print(f"\n❌ Failed Tests:")
            for r in failed:
                print(f"  - {r['category']}: {r['error']}")
        
        print("\n" + "-" * 80)
        print("RESULTS TABLE")
        print("-" * 80)
        print(f"{'Category':<25} | {'Time':<7} | {'Emotion':<12} | {'Conf':<5} | {'Fallback':<8} | {'Status':<8}")
        print("-" * 80)
        
        for result in self.results:
            if result['success']:
                status = "✓"
                fallback = "Yes" if result['used_fallback'] else "No"
                conf = f"{result['confidence']:.0%}"
            else:
                status = "✗"
                fallback = "N/A"
                conf = "N/A"
            
            print(f"{result['category']:<25} | {result.get('response_time', 0):<7.2f} | {result.get('emotion', 'N/A'):<12} | {conf:<5} | {fallback:<8} | {status:<8}")
        
        print("-" * 80)
        
        # Save detailed results
        self.save_results()
    
    def save_results(self):
        """Save results to JSON file"""
        output = {
            'timestamp': datetime.now().isoformat(),
            'test_type': 'orca-mini-3b-integration',
            'total_tests': len(self.results),
            'successful': len([r for r in self.results if r['success']]),
            'failed': len([r for r in self.results if not r['success']]),
            'results': self.results,
        }
        
        with open('orca_integration_results.json', 'w') as f:
            json.dump(output, f, indent=2)
        
        print(f"\n✓ Results saved to orca_integration_results.json")

if __name__ == "__main__":
    tester = OrcaIntegrationTester()
    tester.run_all_tests()
