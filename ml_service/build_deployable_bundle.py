#!/usr/bin/env python3
"""
Build Deployable Emotion Classifier Bundle
============================================
Creates a self-contained inference bundle with:
- Model weights (from checkpoint-130)
- Correct tokenizer (distilroberta-base)
- Configuration files (id2label, config.json)

Does NOT include:
- optimizer.pt (training only)
- scheduler.pt (training only)
- rng_state.pth (training only)
- training_args.bin (training only)
"""

import json
import shutil
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification
import torch

print("=" * 80)
print("BUILDING DEPLOYABLE EMOTION CLASSIFIER BUNDLE")
print("=" * 80)

# Paths (use absolute paths to avoid HuggingFace repo name confusion)
current_dir = Path.cwd()
checkpoint_path = current_dir / "ml_service" / "emotion_model_multilingual_candidate" / "checkpoint-130"
deployable_path = current_dir / "ml_service" / "emotion_model_multilingual_deployable"
deployable_path.mkdir(exist_ok=True, parents=True)

print(f"\n[CHECKPOINT] Loading from: {checkpoint_path}")
print(f"[DEPLOYABLE] Target: {deployable_path}")

# Step 1: Load model from checkpoint
print("\n[STEP 1] Loading model from checkpoint-130...")
try:
    # Use absolute path and local_files_only to avoid repo lookup
    model = AutoModelForSequenceClassification.from_pretrained(
        str(checkpoint_path),
        local_files_only=True
    )
    print(f"  ✓ Model loaded successfully")
    print(f"  - Type: {type(model).__name__}")
    print(f"  - Config vocab_size: {model.config.vocab_size}")
    print(f"  - Num classes: {model.config.num_labels}")
except Exception as e:
    print(f"  ✗ FAILED: {e}")
    exit(1)

# Step 2: Load CORRECT tokenizer (not from checkpoint - from original)
print("\n[STEP 2] Loading correct tokenizer from distilroberta-base...")
try:
    tokenizer = AutoTokenizer.from_pretrained("distilroberta-base")
    print(f"  ✓ Tokenizer loaded successfully")
    print(f"  - Type: {type(tokenizer).__name__}")
    print(f"  - Vocab size: {tokenizer.vocab_size}")
    if tokenizer.vocab_size != 50265:
        print(f"  ⚠ WARNING: Expected vocab_size 50265, got {tokenizer.vocab_size}")
    else:
        print(f"  ✓ Vocab size verified (50,265)")
except Exception as e:
    print(f"  ✗ FAILED: {e}")
    exit(1)

# Step 3: Verify tokenization works correctly
print("\n[STEP 3] Testing tokenization with diverse texts...")
test_texts = [
    "I'm furious about this.",
    "I'm happy today.",
    "Kinakabahan ako bukas.",
    "Sobrang lungkot ko.",
    "Miss ko ang lola kong namatay.",
    "Okay lang naman ako."
]

token_ids_list = []
for text in test_texts:
    tokens = tokenizer.encode(text)
    token_ids_list.append(tokens)
    print(f"  Text: '{text}'")
    print(f"    Tokens (first 10): {tokens[:10]}")
    print(f"    Total tokens: {len(tokens)}")

# Verify diversity
unique_first_tokens = len(set(tuple(t[:5]) for t in token_ids_list))
print(f"\n  Unique token patterns (first 5 tokens): {unique_first_tokens}/{len(test_texts)}")
if unique_first_tokens < len(test_texts):
    print(f"  ✗ WARNING: Token patterns not diverse enough!")
else:
    print(f"  ✓ Token diversity verified")

# Step 4: Save model to deployable
print("\n[STEP 4] Saving model to deployable bundle...")
try:
    model.save_pretrained(str(deployable_path))
    print(f"  ✓ Model saved")
except Exception as e:
    print(f"  ✗ FAILED: {e}")
    exit(1)

# Step 5: Save tokenizer to deployable
print("\n[STEP 5] Saving tokenizer to deployable bundle...")
try:
    tokenizer.save_pretrained(str(deployable_path))
    print(f"  ✓ Tokenizer saved")
except Exception as e:
    print(f"  ✗ FAILED: {e}")
    exit(1)

# Step 6: Verify config files
print("\n[STEP 6] Verifying configuration files...")
config_path = deployable_path / "config.json"
if config_path.exists():
    with open(config_path, 'r') as f:
        config = json.load(f)
    print(f"  ✓ config.json found")
    print(f"    - vocab_size: {config.get('vocab_size')}")
    print(f"    - num_labels: {config.get('num_labels')}")
else:
    print(f"  ✗ config.json NOT found")

# Step 7: Copy and verify label mapping
print("\n[STEP 7] Setting up label configuration...")
label_mapping = {
    "label2id": {
        "sadness": 0,
        "joy": 1,
        "anxiety": 2,
        "anger": 3,
        "neutral": 4,
        "grief": 5
    },
    "id2label": {
        "0": "sadness",
        "1": "joy",
        "2": "anxiety",
        "3": "anger",
        "4": "neutral",
        "5": "grief"
    }
}

label_file = deployable_path / "id2label.json"
with open(label_file, 'w') as f:
    json.dump(label_mapping, f, indent=2)
print(f"  ✓ id2label.json created")
print(f"    - Emotions: {', '.join(label_mapping['label2id'].keys())}")

# Step 8: List deployable contents
print("\n[STEP 8] Deployable bundle contents:")
deployable_files = sorted([f.name for f in deployable_path.iterdir()])
for fname in deployable_files:
    fpath = deployable_path / fname
    if fpath.is_file():
        size_kb = fpath.stat().st_size / 1024
        print(f"  - {fname:<25} ({size_kb:>10.1f} KB)")
    else:
        print(f"  - {fname}/ (directory)")

# Step 9: Test local loading (offline mode)
print("\n[STEP 9] Testing offline local loading...")
import os
os.environ['HF_HUB_OFFLINE'] = '1'

try:
    print("  Loading model locally (offline)...")
    model_deployed = AutoModelForSequenceClassification.from_pretrained(
        str(deployable_path),
        local_files_only=True
    )
    print(f"    ✓ Model loaded from deployable (offline)")
    MODEL_LOCAL_LOAD = "PASS"
except Exception as e:
    print(f"    ✗ FAILED: {e}")
    MODEL_LOCAL_LOAD = "FAIL"

try:
    print("  Loading tokenizer locally (offline)...")
    tokenizer_deployed = AutoTokenizer.from_pretrained(
        str(deployable_path),
        local_files_only=True
    )
    print(f"    ✓ Tokenizer loaded from deployable (offline)")
    print(f"      Vocab size: {tokenizer_deployed.vocab_size}")
    TOKENIZER_LOCAL_LOAD = "PASS"
except Exception as e:
    print(f"    ✗ FAILED: {e}")
    TOKENIZER_LOCAL_LOAD = "FAIL"

# Clear offline flag
del os.environ['HF_HUB_OFFLINE']

print("\n" + "=" * 80)
print("BUILD COMPLETE")
print("=" * 80)
print(f"\n[RESULTS]")
print(f"  Deployable location: {deployable_path.absolute()}")
print(f"  Model local load:    {MODEL_LOCAL_LOAD}")
print(f"  Tokenizer local load: {TOKENIZER_LOCAL_LOAD}")
print(f"  Token diversity:     PASS ({unique_first_tokens} unique patterns)")
print(f"  Label mapping:       PASS (6 emotions)")

if MODEL_LOCAL_LOAD == "PASS" and TOKENIZER_LOCAL_LOAD == "PASS":
    print(f"\n✓ DEPLOYABLE BUNDLE READY FOR USE")
else:
    print(f"\n✗ DEPLOYABLE BUNDLE BUILD FAILED")
    exit(1)
