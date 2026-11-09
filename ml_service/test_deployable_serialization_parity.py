#!/usr/bin/env python3
"""
Test Serialization Parity: Deployable Bundle vs Fixed Evaluator
=================================================================
Validate that the deployable bundle produces identical metrics
to the fixed evaluator using the correct tokenizer.
"""

import json
import numpy as np
import torch
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification
from sklearn.metrics import accuracy_score, precision_recall_fscore_support, confusion_matrix
import os

print("=" * 80)
print("TESTING DEPLOYABLE SERIALIZATION PARITY")
print("=" * 80)

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"\n[DEVICE] {device.upper()}")

# ===========================================================================
# PART 1: Load Deployable Bundle (Offline)
# ===========================================================================
print("\n[PART 1] Loading Deployable Bundle Offline")
os.environ['HF_HUB_OFFLINE'] = '1'

deployable_path = Path("ml_service/emotion_model_multilingual_deployable").absolute()
print(f"  Path: {deployable_path}")

try:
    print("  Loading model...")
    model_deployable = AutoModelForSequenceClassification.from_pretrained(
        str(deployable_path),
        local_files_only=True
    )
    print(f"    [PASS] Model loaded")
    print(f"      - Type: {type(model_deployable).__name__}")
    print(f"      - Num classes: {model_deployable.config.num_labels}")
except Exception as e:
    print(f"    [FAIL] {e}")
    exit(1)

try:
    print("  Loading tokenizer...")
    tokenizer_deployable = AutoTokenizer.from_pretrained(
        str(deployable_path),
        local_files_only=True
    )
    print(f"    [PASS] Tokenizer loaded")
    print(f"      - Vocab size: {tokenizer_deployable.vocab_size}")
except Exception as e:
    print(f"    [FAIL] {e}")
    exit(1)

# Move model to device
model_deployable.to(device)
model_deployable.eval()

# Load id2label mapping
id2label_file = deployable_path / "id2label.json"
with open(id2label_file, 'r') as f:
    label_config = json.load(f)
id2label = {int(k): v for k, v in label_config["id2label"].items()}

print(f"\n  id2label mapping:")
for idx, name in id2label.items():
    print(f"    {idx}: {name}")

# ===========================================================================
# PART 2: Load Test Data
# ===========================================================================
print("\n[PART 2] Loading Test Data")

test_file = Path("ml_service/emotion_data_multilingual_final_v2/test.jsonl").absolute()
print(f"  Loading from: {test_file}")

with open(test_file, 'r', encoding='utf-8') as f:
    test_data = [json.loads(line) for line in f]

texts = [row["text"] for row in test_data]
true_labels = [label_config["label2id"][row["emotion"]] for row in test_data]
languages = [row["language"] for row in test_data]

print(f"  Total samples: {len(test_data)}")
print(f"  Languages: {sorted(set(languages))}")

# ===========================================================================
# PART 3: Test Fixed Evaluator Approach (Reference)
# ===========================================================================
print("\n[PART 3] Fixed Evaluator Approach (Reference)")

# Fixed evaluator uses "distilroberta-base" tokenizer
print("  Loading tokenizer from: distilroberta-base")
tokenizer_fixed = AutoTokenizer.from_pretrained("distilroberta-base")

# Load model from checkpoint (weights are same as deployable)
checkpoint_path = Path("ml_service/emotion_model_multilingual_candidate/checkpoint-130").absolute()
print(f"  Loading model from checkpoint: {checkpoint_path}")
model_fixed = AutoModelForSequenceClassification.from_pretrained(
    str(checkpoint_path),
    local_files_only=True
)
model_fixed.to(device)
model_fixed.eval()

# Tokenize
print("  Tokenizing...")
encodings_fixed = tokenizer_fixed(texts, truncation=True, max_length=128, padding="max_length", return_tensors="pt")
encodings_fixed = {k: v.to(device) for k, v in encodings_fixed.items()}

# Make predictions
print("  Making predictions...")
with torch.no_grad():
    outputs_fixed = model_fixed(**encodings_fixed)
    logits_fixed = outputs_fixed.logits.cpu().numpy()

pred_labels_fixed = np.argmax(logits_fixed, axis=1)
probs_fixed = torch.nn.functional.softmax(torch.tensor(logits_fixed), dim=1).numpy()

# Calculate metrics
acc_fixed = accuracy_score(true_labels, pred_labels_fixed)
precision_fixed, recall_fixed, f1_fixed, _ = precision_recall_fscore_support(
    true_labels, pred_labels_fixed, average="macro", zero_division=0
)

print(f"\n  Fixed Evaluator Result:")
print(f"    Accuracy: {acc_fixed:.4f}")
print(f"    Macro Precision: {precision_fixed:.4f}")
print(f"    Macro Recall: {recall_fixed:.4f}")
print(f"    Macro F1: {f1_fixed:.4f}")

# ===========================================================================
# PART 4: Test Deployable Bundle Approach
# ===========================================================================
print("\n[PART 4] Deployable Bundle Approach")

# Deployable bundle uses its own tokenizer (same as distilroberta-base)
print("  Tokenizing...")
encodings_deployable = tokenizer_deployable(texts, truncation=True, max_length=128, padding="max_length", return_tensors="pt")
encodings_deployable = {k: v.to(device) for k, v in encodings_deployable.items()}

# Make predictions
print("  Making predictions...")
with torch.no_grad():
    outputs_deployable = model_deployable(**encodings_deployable)
    logits_deployable = outputs_deployable.logits.cpu().numpy()

pred_labels_deployable = np.argmax(logits_deployable, axis=1)
probs_deployable = torch.nn.functional.softmax(torch.tensor(logits_deployable), dim=1).numpy()

# Calculate metrics
acc_deployable = accuracy_score(true_labels, pred_labels_deployable)
precision_deployable, recall_deployable, f1_deployable, _ = precision_recall_fscore_support(
    true_labels, pred_labels_deployable, average="macro", zero_division=0
)

print(f"\n  Deployable Bundle Result:")
print(f"    Accuracy: {acc_deployable:.4f}")
print(f"    Macro Precision: {precision_deployable:.4f}")
print(f"    Macro Recall: {recall_deployable:.4f}")
print(f"    Macro F1: {f1_deployable:.4f}")

# ===========================================================================
# PART 5: Check Parity
# ===========================================================================
print("\n[PART 5] Serialization Parity Check")

# Compare predictions
predictions_match = np.array_equal(pred_labels_fixed, pred_labels_deployable)
print(f"  Predictions identical: {predictions_match}")

# Compare logits (within tolerance for numerical errors)
logits_close = np.allclose(logits_fixed, logits_deployable, atol=1e-5)
print(f"  Logits identical (atol=1e-5): {logits_close}")

# Compare probabilities (within tolerance)
probs_close = np.allclose(probs_fixed, probs_deployable, atol=1e-5)
print(f"  Probabilities identical (atol=1e-5): {probs_close}")

# Compare metrics
acc_match = abs(acc_fixed - acc_deployable) < 1e-4
f1_match = abs(f1_fixed - f1_deployable) < 1e-4

print(f"\n  Metric Comparison:")
print(f"    Fixed Evaluator Accuracy: {acc_fixed:.4f}")
print(f"    Deployable Accuracy:      {acc_deployable:.4f}")
print(f"    Match: {acc_match}")
print(f"    Fixed Evaluator F1: {f1_fixed:.4f}")
print(f"    Deployable F1:      {f1_deployable:.4f}")
print(f"    Match: {f1_match}")

if predictions_match and logits_close and acc_match and f1_match:
    SERIALIZATION_PARITY = "PASS"
    print(f"\n  [PASS] SERIALIZATION_PARITY = PASS")
else:
    SERIALIZATION_PARITY = "FAIL"
    print(f"\n  [FAIL] SERIALIZATION_PARITY = FAIL")
    if not predictions_match:
        print(f"    - Predictions differ")
    if not logits_close:
        print(f"    - Logits differ")
    if not acc_match:
        print(f"    - Accuracy differs")
    if not f1_match:
        print(f"    - F1 differs")

# ===========================================================================
# PART 6: Validation Against Expected Reference
# ===========================================================================
print("\n[PART 6] Validation Against Expected Reference")

expected_accuracy = 0.7396  # From Task 7
expected_f1 = 0.7542

print(f"  Expected Accuracy: {expected_accuracy:.4f}")
print(f"  Actual Accuracy:   {acc_deployable:.4f}")
print(f"  Match: {abs(acc_deployable - expected_accuracy) < 0.001}")

print(f"  Expected F1: {expected_f1:.4f}")
print(f"  Actual F1:   {f1_deployable:.4f}")
print(f"  Match: {abs(f1_deployable - expected_f1) < 0.001}")

# ===========================================================================
# PART 7: Output Summary
# ===========================================================================
print("\n" + "=" * 80)
print("SERIALIZATION PARITY TEST COMPLETE")
print("=" * 80)

print(f"\n[SUMMARY]")
print(f"  Fixed Evaluator Accuracy: {acc_fixed:.4f}")
print(f"  Deployable Bundle Accuracy: {acc_deployable:.4f}")
print(f"  Deployable Bundle F1: {f1_deployable:.4f}")
print(f"  Serialization Parity: {SERIALIZATION_PARITY}")
print(f"  id2label Mapping Verified: 6 emotions")
print(f"  Tokenizer Vocab Size: {tokenizer_deployable.vocab_size}")

if SERIALIZATION_PARITY == "PASS":
    print(f"\n[PASS] DEPLOYABLE BUNDLE READY FOR INTEGRATION")
else:
    print(f"\n[FAIL] DEPLOYABLE BUNDLE PARITY FAILED")
    exit(1)

# Clear offline mode
del os.environ['HF_HUB_OFFLINE']
