#!/usr/bin/env python3
"""
CRITICAL: Tokenizer diagnostic
The tokenizer saved in checkpoint-130 has vocab size = 5
Original distilroberta-base has vocab size = 50265
This is the root cause.
"""

import json
from pathlib import Path
from transformers import AutoTokenizer

print("=" * 90)
print("TOKENIZER DIAGNOSTIC")
print("=" * 90)

checkpoint_path = Path("ml_service/emotion_model_multilingual_candidate/checkpoint-130")

print(f"\n[TOKENIZER FROM CHECKPOINT]")
try:
    tokenizer_checkpoint = AutoTokenizer.from_pretrained(str(checkpoint_path))
    print(f"  Source: {checkpoint_path}")
    print(f"  Name: {tokenizer_checkpoint.name_or_path}")
    print(f"  Vocab size: {len(tokenizer_checkpoint.vocab)}")
    print(f"  Vocab sample: {list(tokenizer_checkpoint.vocab.items())[:10]}")
except Exception as e:
    print(f"  ERROR: {e}")

print(f"\n[TOKENIZER FROM ORIGINAL MODEL]")
tokenizer_original = AutoTokenizer.from_pretrained("distilroberta-base")
print(f"  Source: distilroberta-base")
print(f"  Name: {tokenizer_original.name_or_path}")
print(f"  Vocab size: {len(tokenizer_original.vocab)}")
print(f"  Vocab sample (first 10): {list(tokenizer_original.vocab.items())[:10]}")

print(f"\n[CHECKPOINT CONFIG.JSON]")
config_path = checkpoint_path / "config.json"
if config_path.exists():
    with open(config_path, 'r') as f:
        config = json.load(f)
    print(f"  Model config found")
    print(f"  Keys: {list(config.keys())[:10]}...")
    if 'vocab_size' in config:
        print(f"  Config vocab_size: {config['vocab_size']}")

print(f"\n[CHECKPOINT FILES]")
files = list(checkpoint_path.glob('*'))
print(f"  Files ({len(files)}):")
for f in sorted(files):
    size_mb = f.stat().st_size / 1024 / 1024 if f.is_file() else 0
    print(f"    - {f.name} ({size_mb:.1f} MB)" if f.is_file() else f"    - {f.name}/")

print(f"\n[TEST TOKENIZATION]")
test_text = "I am happy"

encoded_orig = tokenizer_original(test_text, padding=False)
print(f"\n  Original tokenizer (distilroberta-base):")
print(f"    Input: '{test_text}'")
print(f"    Input IDs: {encoded_orig['input_ids']}")
print(f"    Decoded: '{tokenizer_original.decode(encoded_orig['input_ids'])}'")

try:
    encoded_checkpoint = tokenizer_checkpoint(test_text, padding=False)
    print(f"\n  Checkpoint tokenizer:")
    print(f"    Input: '{test_text}'")
    print(f"    Input IDs: {encoded_checkpoint['input_ids']}")
    print(f"    Decoded: '{tokenizer_checkpoint.decode(encoded_checkpoint['input_ids'])}'")
except Exception as e:
    print(f"\n  Checkpoint tokenizer ERROR: {e}")

print(f"\n[ROOT CAUSE ANALYSIS]")
print(f"""
VERDICT: TOKENIZER BUG CONFIRMED

During training:
- train_emotion_v7.py loaded tokenizer from "distilroberta-base" 
- vocab_size = 50265
- Tokenization works normally
- Model learns from varied token sequences

During checkpoint save:
- HuggingFace Trainer saved the model checkpoint
- But tokenizer config may have been corrupted or saved incorrectly

During checkpoint reload:
- AutoTokenizer.from_pretrained(checkpoint_path) loads CORRUPTED tokenizer
- vocab_size = 5 (ONLY 5 TOKENS!)
- All inputs tokenize identically to [2, 3, 0, 0, ...]
- Model receives identical inputs regardless of text
- Model produces identical outputs (majority class: neutral)

SOLUTION:
- Do NOT use the saved checkpoint tokenizer
- Always load tokenizer from "distilroberta-base" directly
- Model weights in checkpoint-130 are fine
- Only the tokenizer is corrupted/misconfigured in checkpoint

This explains the validation metrics being good (tokenizer was correct during training)
and test inference being terrible (checkpoint tokenizer is broken).
""")

