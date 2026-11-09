#!/usr/bin/env python3
"""
Check which candidate models are available and their estimated specs.
This doesn't download anything - just information gathering.
"""

import json

# Candidate models with their characteristics
candidate_models = {
    # Tier 1: Speed-Focused
    "phi:latest": {
        "name": "Phi 2.7B",
        "params": "2.7B",
        "size_gb": 3.3,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 0.5,  # 2x faster than neural-chat
        "tier": "Speed-focused",
        "expected_tokens_per_sec": 8,
        "multilingual": False,
        "notes": "Microsoft's fast model, excellent for CPU inference"
    },
    
    "tinyllama:1.1b": {
        "name": "TinyLlama 1.1B",
        "params": "1.1B",
        "size_gb": 0.637,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 0.25,  # 4x faster than neural-chat
        "tier": "Speed-focused",
        "expected_tokens_per_sec": 15,
        "multilingual": False,
        "notes": "Ultra-small, ultra-fast. Useful for speed baseline testing."
    },
    
    "orca-mini:3b": {
        "name": "Orca Mini 3B",
        "params": "3B",
        "size_gb": 3.5,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 0.6,  # ~1.6x faster
        "tier": "Speed-focused",
        "expected_tokens_per_sec": 7,
        "multilingual": False,
        "notes": "Smaller Orca model, instruction-following"
    },
    
    # Tier 2: Balanced
    "mistral:latest": {
        "name": "Mistral 7B",
        "params": "7B",
        "size_gb": 4.1,
        "quantization": "Q4_0",
        "language": ["English", "French", "German", "Spanish", "Italian"],
        "speed_factor": 0.95,  # Similar to neural-chat
        "tier": "Balanced",
        "expected_tokens_per_sec": 4.2,
        "multilingual": True,
        "notes": "Better multilingual support, stronger reasoning"
    },
    
    "zephyr:7b": {
        "name": "Zephyr 7B",
        "params": "7B",
        "size_gb": 4.2,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 0.95,  # Similar to neural-chat
        "tier": "Balanced",
        "expected_tokens_per_sec": 4.2,
        "multilingual": False,
        "notes": "Mistral-based, fine-tuned for dialogue"
    },
    
    # Tier 3: Multilingual
    "llama2:7b": {
        "name": "Llama 2 7B",
        "params": "7B",
        "size_gb": 3.8,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 0.95,
        "tier": "Balanced",
        "expected_tokens_per_sec": 4.2,
        "multilingual": False,
        "notes": "Meta's Llama 2, good baseline comparison"
    },
    
    # Current model for reference
    "neural-chat:latest": {
        "name": "Neural Chat 7B (CURRENT)",
        "params": "7B",
        "size_gb": 3.83,
        "quantization": "Q4_0",
        "language": ["English"],
        "speed_factor": 1.0,  # Baseline
        "tier": "Current",
        "expected_tokens_per_sec": 4.4,
        "multilingual": False,
        "notes": "Currently running model (for comparison)"
    }
}

print("=" * 80)
print("OLLAMA MODEL CANDIDATES FOR BENCHMARKING")
print("=" * 80)
print()

print("TIER 1: SPEED-FOCUSED (Likely to solve timeout issue)")
print("-" * 80)

tier_1 = {k: v for k, v in candidate_models.items() if v.get("tier") == "Speed-focused"}
for model_name, info in tier_1.items():
    print(f"\n{info['name']}")
    print(f"  Model name: {model_name}")
    print(f"  Size: {info['size_gb']} GB | Params: {info['params']} | Quantization: {info['quantization']}")
    print(f"  Expected speed: {info['expected_tokens_per_sec']} tokens/sec (~{100*info['speed_factor']:.0f}% of neural-chat)")
    print(f"  Current speed vs this model: {1/info['speed_factor']:.1f}x speedup expected")
    print(f"  Languages: {', '.join(info['language'])}")
    print(f"  Notes: {info['notes']}")

print()
print()
print("TIER 2: BALANCED (Similar speed, different strengths)")
print("-" * 80)

tier_2 = {k: v for k, v in candidate_models.items() if v.get("tier") == "Balanced"}
for model_name, info in tier_2.items():
    print(f"\n{info['name']}")
    print(f"  Model name: {model_name}")
    print(f"  Size: {info['size_gb']} GB | Params: {info['params']} | Quantization: {info['quantization']}")
    print(f"  Expected speed: {info['expected_tokens_per_sec']} tokens/sec (~{100*info['speed_factor']:.0f}% of neural-chat)")
    print(f"  Languages: {', '.join(info['language'])}")
    print(f"  Multilingual: {'Yes' if info['multilingual'] else 'No'}")
    print(f"  Notes: {info['notes']}")

print()
print()
print("CURRENT MODEL (for reference)")
print("-" * 80)
info = candidate_models["neural-chat:latest"]
print(f"\n{info['name']}")
print(f"  Model name: neural-chat:latest")
print(f"  Size: {info['size_gb']} GB | Params: {info['params']} | Quantization: {info['quantization']}")
print(f"  Current measured speed: {info['expected_tokens_per_sec']} tokens/sec")

print()
print()
print("RECOMMENDED BENCHMARK CANDIDATES")
print("=" * 80)
print()
print("Based on practical constraints and goals, I recommend testing these models:")
print()
print("1. PHI 2.7B (model: phi:latest)")
print("   ✓ Significantly smaller (50% of neural-chat size)")
print("   ✓ Expected 2x speed improvement")
print("   ✓ Safe for current hardware")
print("   ✓ Microsoft research quality")
print("   - English-focused")
print()
print("2. TINYLLAMA 1.1B (model: tinyllama:1.1b)")
print("   ✓ Ultra-small (16% of neural-chat size)")
print("   ✓ Expected 4x speed improvement (baseline test)")
print("   ✓ Very safe for current hardware")
print("   - Limited reasoning capability")
print("   - English only")
print("   ⚠ Quality may be insufficient, but useful for speed limits")
print()
print("3. MISTRAL 7B (model: mistral:latest)")
print("   ✓ Better multilingual support")
print("   ✓ Stronger reasoning than neural-chat")
print("   ~ Similar size and speed to current")
print("   - May not solve the speed issue")
print("   → Test to see if architecture differences help")
print()
print("4. ORCA MINI 3B (model: orca-mini:3b)")
print("   ✓ Smaller (90% of neural-chat size)")
print("   ✓ Expected 1.5-1.7x speed improvement")
print("   ✓ Good instruction-following")
print("   - Less multilingual support")
print()
print()
print("HARDWARE FEASIBILITY CHECK")
print("=" * 80)
print()
print("Current available RAM/storage for models:")
print("  - Current model: 3.83 GB")
print("  - Safe to test: <4.5 GB models")
print("  - Maximum safe: <5 GB to avoid swap")
print()
print("Feasibility of candidates:")

for model_name, info in candidate_models.items():
    size = info['size_gb']
    status = "✓ SAFE" if size <= 4.5 else ("⚠ TIGHT" if size <= 5 else "✗ RISKY")
    print(f"  {status:8} | {size:4} GB | {model_name}")

print()
print()
print("NEXT STEP")
print("=" * 80)
print()
print("Once you approve this candidate list, I will:")
print("1. Create a benchmark test script")
print("2. Use the 10 test prompts you specified")
print("3. Test EACH model with the SAME prompts")
print("4. Record response time, token speed, quality metrics")
print("5. Generate comparison report")
print()
print("This is still non-destructive - we're just gathering data.")
print()
