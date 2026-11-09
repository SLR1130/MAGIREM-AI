#!/usr/bin/env python3
import json
import torch
from pathlib import Path
from transformers import AutoModelForSequenceClassification, AutoTokenizer
import numpy as np

print("=" * 70)
print("DEBUGGING MODEL LOADING ISSUE")
print("=" * 70)

# Check best checkpoint
best_checkpoint = Path("ml_service/emotion_model_multilingual_candidate/checkpoint-130")

print("\n[CHECKPOINT STATUS]")
print(f"  Checkpoint exists: {best_checkpoint.exists()}")
if best_checkpoint.exists():
    files = list(best_checkpoint.glob('*'))
    print(f"  Files in checkpoint:")
    for f in sorted(files):
        print(f"    - {f.name}")

# Load model
print("\n[LOADING MODEL]")
try:
    model = AutoModelForSequenceClassification.from_pretrained(str(best_checkpoint))
    tokenizer = AutoTokenizer.from_pretrained(str(best_checkpoint))
    print(f"  Model loaded successfully")
    print(f"  Num labels: {model.num_labels}")
    print(f"  ID2Label: {model.config.id2label}")
    print(f"  Model device: CPU (before move)")
except Exception as e:
    print(f"  ERROR loading model: {e}")
    exit(1)

# Test with sample text
print("\n[TEST INFERENCE]")
model.eval()
device = "cuda" if torch.cuda.is_available() else "cpu"
model.to(device)

test_texts = [
    "i don't know how to move forward",  # grief
    "so this happened...",  # neutral
    "i'm so angry!",  # anger
    "i'm so happy!",  # joy
    "i'm worried about this",  # anxiety
    "ang kapatid ko ay nawalan ng buhay",  # sadness
]

with torch.no_grad():
    for text in test_texts:
        inputs = tokenizer(text, return_tensors="pt", max_length=128, padding="max_length", truncation=True)
        inputs = {k: v.to(device) for k, v in inputs.items()}
        outputs = model(**inputs)
        logits = outputs.logits[0].cpu()
        probs = torch.nn.functional.softmax(logits, dim=0)
        pred = torch.argmax(logits)
        
        print(f"\n  Text: \"{text}\"")
        print(f"  Logits: {logits.numpy()}")
        print(f"  Probs: {probs.numpy()}")
        print(f"  Top class: {model.config.id2label[int(pred)]} ({pred})")
        print(f"  Top prob: {float(probs[pred]):.4f}")

# Check trainer state
print("\n[TRAINER STATE]")
trainer_state_file = best_checkpoint / "trainer_state.json"
if trainer_state_file.exists():
    with open(trainer_state_file, 'r') as f:
        trainer_state = json.load(f)
    print(f"  Best model checkpoint: {trainer_state.get('best_model_checkpoint', 'N/A')}")
    print(f"  Best metric: {trainer_state.get('best_metric', 'N/A')}")
    print(f"  Log history length: {len(trainer_state.get('log_history', []))}")
    
    # Get last few logs
    logs = trainer_state['log_history'][-3:]
    print(f"  Last 3 log entries:")
    for log in logs:
        if 'eval_loss' in log:
            print(f"    Epoch {log.get('epoch', '?')}: loss={log.get('eval_loss', '?'):.4f}, f1={log.get('eval_macro_f1', '?'):.4f}")

print("\n" + "=" * 70)
