"""
PHASE 2: Test which tokenizer is actually being loaded
"""
import sys
from pathlib import Path
from transformers import AutoTokenizer

module_dir = Path(__file__).resolve().parent

print("="*80)
print("PHASE 2: TOKENIZER PARITY TEST")
print("="*80)

# Test 1: Try loading from checkpoint-130 with local_files_only=True
print("\n[TEST 1] Loading tokenizer from checkpoint-130 with local_files_only=True")
checkpoint_path = module_dir / "emotion_model_multilingual_candidate" / "checkpoint-130"
print(f"Path: {checkpoint_path}")
print(f"Exists: {checkpoint_path.exists()}")

try:
    tokenizer_ckpt = AutoTokenizer.from_pretrained(
        str(checkpoint_path),
        local_files_only=True
    )
    print(f"✓ SUCCESS: Loaded tokenizer from checkpoint-130")
    print(f"  Vocab size: {len(tokenizer_ckpt)}")
    print(f"  Class: {tokenizer_ckpt.__class__.__name__}")
except Exception as e:
    print(f"✗ FAILED: {e}")
    tokenizer_ckpt = None

# Test 2: Load from deployable
print("\n[TEST 2] Loading tokenizer from deployable with local_files_only=True")
deployable_path = module_dir / "emotion_model_multilingual_deployable"
print(f"Path: {deployable_path}")
print(f"Exists: {deployable_path.exists()}")

try:
    tokenizer_deploy = AutoTokenizer.from_pretrained(
        str(deployable_path),
        local_files_only=True
    )
    print(f"✓ SUCCESS: Loaded tokenizer from deployable")
    print(f"  Vocab size: {len(tokenizer_deploy)}")
    print(f"  Class: {tokenizer_deploy.__class__.__name__}")
except Exception as e:
    print(f"✗ FAILED: {e}")
    tokenizer_deploy = None

# Test 3: Load the proper distilroberta-base tokenizer
print("\n[TEST 3] Loading AutoTokenizer.from_pretrained('distilroberta-base')")
try:
    tokenizer_hf = AutoTokenizer.from_pretrained("distilroberta-base")
    print(f"✓ SUCCESS: Loaded distilroberta-base tokenizer")
    print(f"  Vocab size: {len(tokenizer_hf)}")
    print(f"  Class: {tokenizer_hf.__class__.__name__}")
except Exception as e:
    print(f"✗ FAILED: {e}")
    tokenizer_hf = None

# Compare tokenizers
print("\n" + "="*80)
print("TOKENIZER COMPARISON")
print("="*80)

test_messages = [
    "I'm a little sad kasi hindi ako nakapasa sa exam today",
    "hindi ako nakapasa sa exam",
    "Kinakabihan ako kasi may exam ako bukas",
    "Masaya ako kasi pasado ako sa exam",
    "Galit ako ksi unfair yung grade ko sa exam",
    "May exam ako bukas",
    "Pakiramdam ko wala nang pag-asa at gusto ko nang mawala",
    "hey"
]

for i, msg in enumerate(test_messages, 1):
    print(f"\n[MESSAGE {i}] {msg}")
    
    if tokenizer_deploy:
        tokens_deploy = tokenizer_deploy.encode(msg)
        print(f"  Deployable: {len(tokens_deploy)} tokens | {tokens_deploy[:10]}...")
    
    if tokenizer_hf:
        tokens_hf = tokenizer_hf.encode(msg)
        print(f"  HuggingFace: {len(tokens_hf)} tokens | {tokens_hf[:10]}...")
    
    if tokenizer_deploy and tokenizer_hf:
        if tokens_deploy == tokens_hf:
            print(f"  ✓ SAME tokenization")
        else:
            print(f"  ✗ DIFFERENT tokenization")

print("\n" + "="*80)
print("SPECIAL TOKENS")
print("="*80)

if tokenizer_deploy:
    print("\nDeployable tokenizer special tokens:")
    print(f"  CLS token ID: {tokenizer_deploy.cls_token_id} ({tokenizer_deploy.cls_token})")
    print(f"  SEP token ID: {tokenizer_deploy.sep_token_id} ({tokenizer_deploy.sep_token})")
    print(f"  PAD token ID: {tokenizer_deploy.pad_token_id} ({tokenizer_deploy.pad_token})")
    print(f"  UNK token ID: {tokenizer_deploy.unk_token_id} ({tokenizer_deploy.unk_token})")

if tokenizer_hf:
    print("\nHuggingFace tokenizer special tokens:")
    print(f"  CLS token ID: {tokenizer_hf.cls_token_id} ({tokenizer_hf.cls_token})")
    print(f"  SEP token ID: {tokenizer_hf.sep_token_id} ({tokenizer_hf.sep_token})")
    print(f"  PAD token ID: {tokenizer_hf.pad_token_id} ({tokenizer_hf.pad_token})")
    print(f"  UNK token ID: {tokenizer_hf.unk_token_id} ({tokenizer_hf.unk_token})")
