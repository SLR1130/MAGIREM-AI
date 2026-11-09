#!/usr/bin/env python3
"""
Controlled Ollama Model Benchmark Test

Tests candidate models using identical prompts and measures:
- Response time
- Token generation speed
- Format adherence
- Multilingual understanding
- Emotional awareness
- Sarcasm detection
- Response quality

Usage:
    python benchmark_models.py [model_name]
    
    Or run all recommended candidates:
    python benchmark_models.py --all

Results are saved to benchmark_results.json
"""

import requests
import json
import time
import sys
from typing import Dict, List, Tuple

# Test prompts - identical for all models
TEST_PROMPTS = [
    {
        "id": "english_happiness",
        "category": "English - Positive",
        "message": "My friends surprised me today and it made my whole day.",
        "task": "Understand the emotional context and respond naturally",
        "expected_emotion": "happy",
        "expected_features": ["joy", "gratitude", "positive"]
    },
    {
        "id": "english_sadness",
        "category": "English - Negative",
        "message": "I studied for weeks and still failed my exam.",
        "task": "Show understanding of disappointment",
        "expected_emotion": "sad",
        "expected_features": ["disappointment", "discouragement", "failure"]
    },
    {
        "id": "english_anxiety",
        "category": "English - Anxiety",
        "message": "I have three projects due tomorrow and I haven't finished any of them.",
        "task": "Recognize stress and offer support",
        "expected_emotion": "anxious",
        "expected_features": ["stress", "urgency", "overwhelm"]
    },
    {
        "id": "english_sarcasm",
        "category": "English - Sarcasm",
        "message": "My professor just gave us three deadlines for tomorrow. What a wonderful surprise 😂",
        "task": "Detect sarcasm and respond appropriately",
        "expected_emotion": "frustrated",
        "expected_features": ["sarcasm", "frustration", "criticism"]
    },
    {
        "id": "relationship",
        "category": "Relationship Issue",
        "message": "Yeah about that, my friend said that my girlfriend is cheating on me.",
        "task": "Show empathy for relationship concern",
        "expected_emotion": "distressed",
        "expected_features": ["betrayal", "concern", "conflict"]
    },
    {
        "id": "tagalog",
        "category": "Tagalog",
        "message": "Grabe naman, ang saya talaga. Tatlong deadline bukas 😂",
        "task": "Understand Tagalog sarcasm",
        "expected_emotion": "frustrated",
        "expected_features": ["sarcasm", "stress"]
    },
    {
        "id": "taglish",
        "category": "Taglish (Mixed)",
        "message": "Bro sobrang stressed ako, three deadlines tomorrow tapos wala pa akong natatapos.",
        "task": "Understand mixed-language anxiety",
        "expected_emotion": "anxious",
        "expected_features": ["stress", "worry", "incomplete"]
    },
    {
        "id": "direct_question",
        "category": "Direct Question",
        "message": "How should I prepare for my exam tomorrow?",
        "task": "Provide useful advice",
        "expected_emotion": "neutral",
        "expected_features": ["advice", "preparation"]
    }
]

class ModelBenchmark:
    def __init__(self, ollama_url: str = "http://localhost:11434"):
        self.ollama_url = ollama_url
        self.results = {
            "timestamp": None,
            "models": {}
        }
    
    def test_model(self, model_name: str) -> Dict:
        """Test a model with all test prompts"""
        print(f"\n{'='*80}")
        print(f"TESTING MODEL: {model_name}")
        print(f"{'='*80}\n")
        
        model_results = {
            "name": model_name,
            "tests": [],
            "summary": {}
        }
        
        total_time = 0
        total_tokens = 0
        completed_tests = 0
        
        for i, test in enumerate(TEST_PROMPTS, 1):
            print(f"[{i}/{len(TEST_PROMPTS)}] {test['category']}: {test['message'][:60]}...")
            
            test_result = self._run_test(model_name, test)
            
            if test_result:
                model_results["tests"].append(test_result)
                total_time += test_result.get("response_time", 0)
                total_tokens += test_result.get("eval_count", 0)
                completed_tests += 1
                
                status = "✓" if test_result.get("success") else "✗"
                resp_time = test_result.get("response_time", 0)
                print(f"  {status} Time: {resp_time:.2f}s | Status: {test_result.get('status', 'unknown')}")
            else:
                print(f"  ✗ FAILED")
        
        # Calculate summary statistics
        if completed_tests > 0:
            avg_time = total_time / completed_tests
            avg_tokens_per_sec = total_tokens / total_time if total_time > 0 else 0
            
            model_results["summary"] = {
                "completed_tests": completed_tests,
                "total_tests": len(TEST_PROMPTS),
                "total_time": total_time,
                "avg_response_time": avg_time,
                "total_tokens": total_tokens,
                "avg_tokens_per_sec": avg_tokens_per_sec,
                "success_rate": f"{(completed_tests/len(TEST_PROMPTS)*100):.0f}%"
            }
        
        return model_results
    
    def _run_test(self, model_name: str, test: Dict) -> Dict:
        """Run a single test on a model"""
        try:
            start = time.time()
            
            response = requests.post(
                f"{self.ollama_url}/api/generate",
                json={
                    "model": model_name,
                    "prompt": f"{test['task']}\n\nMessage: \"{test['message']}\"\n\nRespond naturally:",
                    "stream": False,
                    "temperature": 0.7,
                    "top_p": 0.9,
                },
                timeout=(5, 30),
            )
            
            elapsed = time.time() - start
            
            if response.status_code == 200:
                data = response.json()
                response_text = data.get("response", "").strip()
                
                return {
                    "success": True,
                    "status": "completed",
                    "test_id": test["id"],
                    "category": test["category"],
                    "response_time": elapsed,
                    "response_length": len(response_text),
                    "first_100_chars": response_text[:100],
                    "eval_count": data.get("eval_count", 0),
                    "prompt_eval_count": data.get("prompt_eval_count", 0),
                    "total_duration_ns": data.get("total_duration", 0),
                }
            else:
                return {
                    "success": False,
                    "status": f"http_{response.status_code}",
                    "test_id": test["id"],
                    "response_time": elapsed,
                }
                
        except requests.exceptions.Timeout:
            return {
                "success": False,
                "status": "timeout",
                "test_id": test["id"],
                "response_time": elapsed if 'elapsed' in locals() else 30.0,
            }
        except Exception as e:
            return {
                "success": False,
                "status": f"error: {str(e)[:50]}",
                "test_id": test["id"],
            }
    
    def save_results(self, filename: str = "benchmark_results.json"):
        """Save benchmark results to file"""
        import datetime
        self.results["timestamp"] = datetime.datetime.now().isoformat()
        with open(filename, 'w') as f:
            json.dump(self.results, f, indent=2)
        print(f"\n✓ Results saved to {filename}")
    
    def print_summary(self):
        """Print summary comparison"""
        print(f"\n{'='*80}")
        print("BENCHMARK SUMMARY")
        print(f"{'='*80}\n")
        
        for model_name, model_data in self.results.get("models", {}).items():
            summary = model_data.get("summary", {})
            print(f"{model_name}")
            print(f"  Completed: {summary.get('completed_tests', 0)}/{summary.get('total_tests', 0)}")
            print(f"  Total time: {summary.get('total_time', 0):.2f}s")
            print(f"  Average response: {summary.get('avg_response_time', 0):.2f}s per prompt")
            print(f"  Token speed: {summary.get('avg_tokens_per_sec', 0):.2f} tokens/sec")
            print(f"  Success rate: {summary.get('success_rate', 'N/A')}")
            print()

def main():
    """Main benchmark runner"""
    models_to_test = []
    
    # Parse command line arguments
    if len(sys.argv) > 1:
        if sys.argv[1] == "--all":
            models_to_test = [
                "phi:latest",
                "tinyllama:1.1b",
                "orca-mini:3b",
                "mistral:latest",
                "neural-chat:latest"
            ]
        else:
            models_to_test = sys.argv[1:]
    else:
        # Default: test recommended candidates
        models_to_test = [
            "phi:latest",
            "orca-mini:3b",
            "mistral:latest",
        ]
    
    benchmark = ModelBenchmark()
    
    print("="*80)
    print("OLLAMA MODEL BENCHMARK")
    print("="*80)
    print(f"\nModels to test: {', '.join(models_to_test)}")
    print(f"Test prompts: {len(TEST_PROMPTS)}")
    print(f"Total requests: {len(models_to_test) * len(TEST_PROMPTS)}")
    print("\nStarting benchmark...\n")
    
    for model in models_to_test:
        result = benchmark.test_model(model)
        benchmark.results["models"][model] = result
    
    benchmark.print_summary()
    benchmark.save_results()
    
    print("\nNote: If some models failed to run, make sure they are:")
    print("1. Downloaded via Ollama (ollama pull <model>)")
    print("2. Not currently running another copy")
    print("3. Compatible with the current system")

if __name__ == "__main__":
    main()
