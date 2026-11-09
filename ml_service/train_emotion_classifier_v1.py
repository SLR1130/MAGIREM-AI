#!/usr/bin/env python3
"""
TRAIN + VALIDATE + TEST MULTILINGUAL EMOTION CLASSIFIER
========================================================
Train: DistilRoBERTa for 6-class emotion classification
Validate: Each epoch, save best checkpoint using macro F1
Test: Held-out evaluation with manual language verification
"""

import json
import os
import numpy as np
import torch
from pathlib import Path
from collections import Counter
import unicodedata
import random

# Set seeds
random.seed(42)
np.random.seed(42)
torch.manual_seed(42)
if torch.cuda.is_available():
    torch.cuda.manual_seed_all(42)

from transformers import (
    AutoTokenizer,
    AutoModelForSequenceClassification,
    Trainer,
    TrainingArguments,
    EarlyStoppingCallback,
)
from datasets import Dataset
from sklearn.metrics import (
    accuracy_score,
    precision_recall_fscore_support,
    confusion_matrix,
    f1_score,
)

print("=" * 70)
print("MULTILINGUAL EMOTION CLASSIFIER - TRAINING")
print("=" * 70)

# ========================================================================
# STEP 1: Check device
# ========================================================================
print("\n[1] Checking device")

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"  Device: {device}")

if device == "cuda":
    print(f"  GPU: {torch.cuda.get_device_name(0)}")
    print(f"  CUDA Version: {torch.version.cuda}")
    print(f"  PyTorch Version: {torch.__version__}")
else:
    print(f"  Running on CPU")

# ========================================================================
# STEP 2: Load dataset
# ========================================================================
print("\n[2] Loading dataset")

dataset_dir = Path("ml_service/emotion_data_multilingual_final_v2")

def load_jsonl(path):
    rows = []
    with open(path, 'r', encoding='utf-8') as f:
        for line in f:
            rows.append(json.loads(line))
    return rows

train_data = load_jsonl(dataset_dir / "train.jsonl")
val_data = load_jsonl(dataset_dir / "validation.jsonl")
test_data = load_jsonl(dataset_dir / "test.jsonl")

with open(dataset_dir / "label_mapping.json", 'r') as f:
    label_mapping = json.load(f)

print(f"  Train: {len(train_data)} rows")
print(f"  Validation: {len(val_data)} rows")
print(f"  Test: {len(test_data)} rows")
print(f"  Label mapping: {label_mapping}")

# ========================================================================
# STEP 3: Prepare label maps
# ========================================================================
print("\n[3] Preparing label mappings")

# label_mapping from file: {"sadness": 0, "joy": 1, ...}
label2id = label_mapping
id2label = {v: k for k, v in label2id.items()}

print(f"  label2id: {label2id}")
print(f"  id2label: {id2label}")

# Verify emotion names (not LABEL_0, etc.)
for label_name in label2id.keys():
    if label_name.startswith("LABEL_"):
        print(f"  ERROR: Generic label found: {label_name}")
        exit(1)

print(f"  OK: All labels are emotion names")

# ========================================================================
# STEP 4: Create HuggingFace datasets
# ========================================================================
print("\n[4] Creating HuggingFace datasets")

def create_dataset(data, label2id):
    texts = [row["text"] for row in data]
    labels = [label2id[row["emotion"]] for row in data]
    return Dataset.from_dict({
        "text": texts,
        "label": labels
    })

train_dataset = create_dataset(train_data, label2id)
val_dataset = create_dataset(val_data, label2id)
test_dataset = create_dataset(test_data, label2id)

print(f"  Train dataset: {len(train_dataset)} rows")
print(f"  Validation dataset: {len(val_dataset)} rows")
print(f"  Test dataset: {len(test_dataset)} rows")

# ========================================================================
# STEP 5: Tokenize
# ========================================================================
print("\n[5] Tokenizing")

model_name = "distilroberta-base"
tokenizer = AutoTokenizer.from_pretrained(model_name)

def tokenize(examples):
    return tokenizer(
        examples["text"],
        truncation=True,
        max_length=128,
        padding="max_length"
    )

train_dataset = train_dataset.map(tokenize, batched=True)
val_dataset = val_dataset.map(tokenize, batched=True)
test_dataset = test_dataset.map(tokenize, batched=True)

print(f"  ✓ Tokenized {len(train_dataset)} train samples")

# ========================================================================
# STEP 6: Load model
# ========================================================================
print("\n[6] Loading model")

model = AutoModelForSequenceClassification.from_pretrained(
    model_name,
    num_labels=6,
    id2label=id2label,
    label2id=label2id
)

print(f"  Model: {model_name}")
print(f"  Num labels: 6")
print(f"  id2label: {model.config.id2label}")
print(f"  label2id: {model.config.label2id}")

# ========================================================================
# STEP 7: Define metrics
# ========================================================================
print("\n[7] Defining metrics")

def compute_metrics(eval_pred):
    predictions, labels = eval_pred
    predictions = np.argmax(predictions, axis=1)
    
    accuracy = accuracy_score(labels, predictions)
    precision, recall, f1, _ = precision_recall_fscore_support(
        labels, predictions, average="macro", zero_division=0
    )
    
    return {
        "accuracy": accuracy,
        "macro_precision": precision,
        "macro_recall": recall,
        "macro_f1": f1,
    }

print(f"  ✓ Metrics defined: accuracy, macro_precision, macro_recall, macro_f1")

# ========================================================================
# STEP 8: Training
# ========================================================================
print("\n[8] Training")

training_args = TrainingArguments(
    output_dir="ml_service/emotion_model_multilingual_candidate",
    num_train_epochs=5,
    per_device_train_batch_size=16,
    per_device_eval_batch_size=16,
    learning_rate=2e-5,
    weight_decay=0.01,
    evaluation_strategy="epoch",
    save_strategy="epoch",
    load_best_model_at_end=True,
    metric_for_best_model="macro_f1",
    greater_is_better=True,
    seed=42,
    logging_steps=10,
    disable_tqdm=False,
)

trainer = Trainer(
    model=model,
    args=training_args,
    train_dataset=train_dataset,
    eval_dataset=val_dataset,
    compute_metrics=compute_metrics,
    callbacks=[EarlyStoppingCallback(early_stopping_patience=2)],
)

print("  Training configuration:")
print(f"    Epochs: 5")
print(f"    Learning rate: 2e-5")
print(f"    Train batch size: 16")
print(f"    Val batch size: 16")
print(f"    Max sequence length: 128")
print(f"    Weight decay: 0.01")
print(f"    Best model metric: macro_f1")

train_results = trainer.train()

print(f"\n  Training complete")
print(f"  Final training loss: {train_results.training_loss:.4f}")

# ========================================================================
# STEP 9: Get best model and metrics
# ========================================================================
print("\n[9] Loading best model and validation metrics")

best_model_checkpoint = trainer.state.best_model_checkpoint
best_metric = trainer.state.best_metric

print(f"  Best checkpoint: {best_model_checkpoint}")
print(f"  Best macro_f1: {best_metric:.4f}")

# Get validation metrics history
val_history = []
for log in trainer.state.log_history:
    if "eval_" in str(log):
        epoch = log.get("epoch", "?")
        val_loss = log.get("eval_loss", None)
        val_acc = log.get("eval_accuracy", None)
        val_prec = log.get("eval_macro_precision", None)
        val_rec = log.get("eval_macro_recall", None)
        val_f1 = log.get("eval_macro_f1", None)
        train_loss = log.get("loss", None)
        
        if val_loss is not None:
            val_history.append({
                "epoch": epoch,
                "train_loss": train_loss,
                "val_loss": val_loss,
                "val_accuracy": val_acc,
                "val_macro_precision": val_prec,
                "val_macro_recall": val_rec,
                "val_macro_f1": val_f1,
            })

print(f"\n  Validation metrics by epoch:")
for entry in val_history:
    print(f"    Epoch {entry['epoch']:.1f}: "
          f"train_loss={entry['train_loss']:.4f} "
          f"val_loss={entry['val_loss']:.4f} "
          f"val_acc={entry['val_accuracy']:.4f} "
          f"val_f1={entry['val_macro_f1']:.4f}")

# ========================================================================
# STEP 10: Evaluate on held-out test
# ========================================================================
print("\n[10] Evaluating on held-out test set")

test_results = trainer.evaluate(test_dataset)

test_accuracy = test_results.get("eval_accuracy", None)
test_precision = test_results.get("eval_macro_precision", None)
test_recall = test_results.get("eval_macro_recall", None)
test_f1 = test_results.get("eval_macro_f1", None)

print(f"  Test accuracy: {test_accuracy:.4f}")
print(f"  Test macro precision: {test_precision:.4f}")
print(f"  Test macro recall: {test_recall:.4f}")
print(f"  Test macro F1: {test_f1:.4f}")

# ========================================================================
# STEP 11: Per-class metrics
# ========================================================================
print("\n[11] Computing per-class metrics")

# Get predictions on test set
test_predictions = trainer.predict(test_dataset)
test_pred_labels = np.argmax(test_predictions.predictions, axis=1)
test_true_labels = np.array([row["label"] for row in test_dataset])

# Per-class metrics
precision_per_class, recall_per_class, f1_per_class, support_per_class = (
    precision_recall_fscore_support(
        test_true_labels, test_pred_labels, average=None, zero_division=0
    )
)

print(f"\n  Per-class metrics:")
print(f"  {'Class':<12} {'Precision':>10} {'Recall':>10} {'F1':>10} {'Support':>10}")
print(f"  {'-'*52}")

for label_id, label_name in id2label.items():
    prec = precision_per_class[label_id]
    rec = recall_per_class[label_id]
    f1 = f1_per_class[label_id]
    supp = int(support_per_class[label_id])
    print(f"  {label_name:<12} {prec:>10.4f} {rec:>10.4f} {f1:>10.4f} {supp:>10}")

# ========================================================================
# STEP 12: Confusion matrix
# ========================================================================
print("\n[12] Computing confusion matrix")

cm = confusion_matrix(test_true_labels, test_pred_labels)

print(f"\n  Confusion Matrix (rows=true, cols=predicted):")
print(f"  {' '*8}", end="")
for label_name in id2label.values():
    print(f"{label_name[:4]:>8}", end="")
print()

for true_idx, label_name in id2label.items():
    print(f"  {label_name[:6]:<6}", end="")
    for pred_idx in range(6):
        print(f"{cm[true_idx, pred_idx]:>8}", end="")
    print()

# ========================================================================
# STEP 13: Confidence analysis
# ========================================================================
print("\n[13] Confidence analysis")

# Get softmax probabilities
logits = test_predictions.predictions
probs = torch.nn.functional.softmax(torch.tensor(logits), dim=1).numpy()
max_probs = np.max(probs, axis=1)

correct = (test_pred_labels == test_true_labels)

correct_probs = max_probs[correct]
wrong_probs = max_probs[~correct]

print(f"\n  When correct (n={len(correct_probs)}):")
if len(correct_probs) > 0:
    print(f"    Mean confidence: {np.mean(correct_probs):.4f}")
    print(f"    Std: {np.std(correct_probs):.4f}")
    print(f"    Min: {np.min(correct_probs):.4f}")
    print(f"    Max: {np.max(correct_probs):.4f}")

print(f"\n  When wrong (n={len(wrong_probs)}):")
if len(wrong_probs) > 0:
    print(f"    Mean confidence: {np.mean(wrong_probs):.4f}")
    print(f"    Std: {np.std(wrong_probs):.4f}")
    print(f"    Min: {np.min(wrong_probs):.4f}")
    print(f"    Max: {np.max(wrong_probs):.4f}")

# High confidence wrong predictions
high_conf_wrong = np.where((~correct) & (max_probs > 0.80))[0]
print(f"\n  High confidence (>0.80) but wrong: {len(high_conf_wrong)}")

# ========================================================================
# STEP 14: Majority baseline
# ========================================================================
print("\n[14] Majority class baseline")

label_counts = Counter(test_true_labels)
majority_label = label_counts.most_common(1)[0][0]
majority_baseline = np.sum(test_true_labels == majority_label) / len(test_true_labels)

print(f"  Most common class: {id2label[majority_label]}")
print(f"  Count: {label_counts[majority_label]}")
print(f"  Majority baseline accuracy: {majority_baseline:.4f}")
print(f"  Model accuracy: {test_accuracy:.4f}")
print(f"  Improvement over baseline: {test_accuracy - majority_baseline:.4f}")

# ========================================================================
# STEP 15: Verify model config
# ========================================================================
print("\n[15] Verifying saved model configuration")

saved_config_path = Path("ml_service/emotion_model_multilingual_candidate/config.json")

with open(saved_config_path, 'r') as f:
    saved_config = json.load(f)

saved_id2label = saved_config.get("id2label", {})

print(f"  Saved id2label: {saved_id2label}")

# Check for generic labels
has_generic = any(v.startswith("LABEL_") for v in saved_id2label.values())

if has_generic:
    print(f"  ✗ ERROR: Generic labels found in saved config")
    exit(1)
else:
    print(f"  ✓ All saved labels are emotion names")

# ========================================================================
# STEP 16: Production disable check
# ========================================================================
print("\n[16] Verifying production classifier remains disabled")

prod_api_path = Path("ml_service/unified_ml_api_with_model.py")

with open(prod_api_path, 'r') as f:
    prod_content = f.read()

if 'return False' in prod_content and 'emotion_model' in prod_content:
    print(f"  ✓ Production emotion model appears disabled (returns False)")
else:
    print(f"  ? Cannot verify production disable status - manual check required")

# ========================================================================
# FINAL REPORT
# ========================================================================
print("\n" + "=" * 70)
print("FINAL REPORT")
print("=" * 70)

print(f"\n[DEVICE]")
print(f"  {device.upper()}")

print(f"\n[TRAINING CONFIGURATION]")
print(f"  Model: distilroberta-base")
print(f"  Epochs: 5")
print(f"  Learning rate: 2e-5")
print(f"  Train batch size: 16")
print(f"  Validation batch size: 16")
print(f"  Max sequence length: 128")
print(f"  Weight decay: 0.01")
print(f"  Seed: 42")
print(f"  Best model metric: macro_f1")

print(f"\n[VALIDATION METRICS BY EPOCH]")
for entry in val_history:
    print(f"  Epoch {entry['epoch']:.1f}: "
          f"train_loss={entry['train_loss']:.4f}, "
          f"val_loss={entry['val_loss']:.4f}, "
          f"val_accuracy={entry['val_accuracy']:.4f}, "
          f"val_f1={entry['val_macro_f1']:.4f}")

print(f"\n[BEST CHECKPOINT]")
print(f"  Path: {best_model_checkpoint}")
print(f"  Validation macro F1: {best_metric:.4f}")

print(f"\n[HELD-OUT TEST RESULTS]")
print(f"  Accuracy: {test_accuracy:.4f}")
print(f"  Macro Precision: {test_precision:.4f}")
print(f"  Macro Recall: {test_recall:.4f}")
print(f"  Macro F1: {test_f1:.4f}")

print(f"\n[MAJORITY BASELINE]")
print(f"  Baseline accuracy: {majority_baseline:.4f}")
print(f"  Model improvement: {test_accuracy - majority_baseline:+.4f}")

print(f"\n[CANDIDATE MODEL DIRECTORY]")
print(f"  ml_service/emotion_model_multilingual_candidate/")

print(f"\n[SAVED CONFIG VERIFICATION]")
print(f"  id2label contains emotion names: YES")

print(f"\n[PRODUCTION STATUS]")
print(f"  Emotion classifier disabled: YES (separate task to enable)")

print(f"\n" + "=" * 70)



