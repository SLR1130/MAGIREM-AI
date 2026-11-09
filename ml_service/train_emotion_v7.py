#!/usr/bin/env python3
import json, os, numpy as np, torch, random
from pathlib import Path
from collections import Counter
from transformers import AutoTokenizer, AutoModelForSequenceClassification, Trainer, TrainingArguments, EarlyStoppingCallback
from datasets import Dataset
from sklearn.metrics import accuracy_score, precision_recall_fscore_support, confusion_matrix, f1_score

random.seed(42)
np.random.seed(42)
torch.manual_seed(42)
if torch.cuda.is_available():
    torch.cuda.manual_seed_all(42)

print("=" * 70)
print("MULTILINGUAL EMOTION CLASSIFIER TRAINING")
print("=" * 70)

device = "cuda" if torch.cuda.is_available() else "cpu"
print("\n[DEVICE]")
print(f"  Device: {device.upper()}")
if device == "cuda":
    print(f"  GPU: {torch.cuda.get_device_name(0)}")

print("\n[LOADING DATA]")
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

print(f"  Train: {len(train_data)}")
print(f"  Validation: {len(val_data)}")
print(f"  Test: {len(test_data)}")

label2id = label_mapping
id2label = {v: k for k, v in label2id.items()}

print(f"\n[LABELS]")
for lid, lname in id2label.items():
    print(f"  {lid}: {lname}")

print("\n[PREPARING DATA]")

def create_dataset(data, label2id):
    texts = [row["text"] for row in data]
    labels = [label2id[row["emotion"]] for row in data]
    return Dataset.from_dict({"text": texts, "label": labels})

train_dataset = create_dataset(train_data, label2id)
val_dataset = create_dataset(val_data, label2id)
test_dataset = create_dataset(test_data, label2id)

print(f"  Train dataset: {len(train_dataset)}")
print(f"  Validation dataset: {len(val_dataset)}")
print(f"  Test dataset: {len(test_dataset)}")

print("\n[TOKENIZING]")
model_name = "distilroberta-base"
tokenizer = AutoTokenizer.from_pretrained(model_name)

def tokenize(examples):
    return tokenizer(examples["text"], truncation=True, max_length=128, padding="max_length")

train_dataset = train_dataset.map(tokenize, batched=True)
val_dataset = val_dataset.map(tokenize, batched=True)
test_dataset = test_dataset.map(tokenize, batched=True)

print(f"  Tokenized {len(train_dataset)} train samples")

print("\n[LOADING MODEL]")
model = AutoModelForSequenceClassification.from_pretrained(
    model_name, num_labels=6, id2label=id2label, label2id=label2id
)
print(f"  Model: {model_name}")
print(f"  Num labels: 6")

print("\n[DEFINING METRICS]")
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

print("  Metrics: accuracy, macro_precision, macro_recall, macro_f1")

print("\n[TRAINING CONFIG]")
training_args = TrainingArguments(
    output_dir="ml_service/emotion_model_multilingual_candidate",
    num_train_epochs=5,
    per_device_train_batch_size=16,
    per_device_eval_batch_size=16,
    learning_rate=2e-5,
    weight_decay=0.01,
    eval_strategy="epoch",
    save_strategy="epoch",
    load_best_model_at_end=True,
    metric_for_best_model="macro_f1",
    greater_is_better=True,
    seed=42,
    logging_steps=10,
    disable_tqdm=False,
)

print("  Epochs: 5")
print("  Learning rate: 2e-5")
print("  Train batch size: 16")
print("  Weight decay: 0.01")
print("  Best model metric: macro_f1")

print("\n[TRAINING]")
trainer = Trainer(
    model=model,
    args=training_args,
    train_dataset=train_dataset,
    eval_dataset=val_dataset,
    compute_metrics=compute_metrics,
    callbacks=[EarlyStoppingCallback(early_stopping_patience=2)],
)

train_results = trainer.train()
print(f"  Training complete. Final training loss: {train_results.training_loss:.4f}")

print("\n[BEST CHECKPOINT]")
best_model_checkpoint = trainer.state.best_model_checkpoint
best_metric = trainer.state.best_metric
print(f"  Checkpoint: {best_model_checkpoint}")
print(f"  Best validation macro_f1: {best_metric:.4f}")

print("\n[VALIDATION METRICS BY EPOCH]")
for log in trainer.state.log_history:
    if "eval_loss" in log:
        epoch = log.get("epoch", "?")
        train_loss = log.get("loss", "N/A")
        val_loss = log.get("eval_loss", "N/A")
        val_acc = log.get("eval_accuracy", "N/A")
        val_f1 = log.get("eval_macro_f1", "N/A")
        if isinstance(train_loss, float):
            print(f"  Epoch {epoch}: train_loss={train_loss:.4f}, val_loss={val_loss:.4f}, val_acc={val_acc:.4f}, val_f1={val_f1:.4f}")

print("\n[EVALUATING ON TEST]")
test_results = trainer.evaluate(test_dataset)
test_accuracy = test_results.get("eval_accuracy", None)
test_precision = test_results.get("eval_macro_precision", None)
test_recall = test_results.get("eval_macro_recall", None)
test_f1 = test_results.get("eval_macro_f1", None)

print(f"  Test Accuracy: {test_accuracy:.4f}")
print(f"  Test Macro Precision: {test_precision:.4f}")
print(f"  Test Macro Recall: {test_recall:.4f}")
print(f"  Test Macro F1: {test_f1:.4f}")

print("\n[PER-CLASS METRICS]")
test_predictions = trainer.predict(test_dataset)
test_pred_labels = np.argmax(test_predictions.predictions, axis=1)
test_true_labels = np.array([row["label"] for row in test_dataset])

precision_per_class, recall_per_class, f1_per_class, support_per_class = (
    precision_recall_fscore_support(test_true_labels, test_pred_labels, average=None, zero_division=0)
)

print(f"  {'Class':<12} {'Precision':>10} {'Recall':>10} {'F1':>10} {'Support':>10}")
print(f"  {'-'*52}")

for label_id, label_name in id2label.items():
    prec = precision_per_class[label_id]
    rec = recall_per_class[label_id]
    f1 = f1_per_class[label_id]
    supp = int(support_per_class[label_id])
    print(f"  {label_name:<12} {prec:>10.4f} {rec:>10.4f} {f1:>10.4f} {supp:>10}")

print("\n[CONFUSION MATRIX]")
cm = confusion_matrix(test_true_labels, test_pred_labels)
print(f"  Rows=true, Cols=predicted")
print("        ", end="")
for lname in id2label.values():
    print(f"{lname[:4]:>8}", end="")
print()

for true_idx, label_name in id2label.items():
    print(f"  {label_name[:6]:<6}", end="")
    for pred_idx in range(6):
        print(f"{cm[true_idx, pred_idx]:>8}", end="")
    print()

print("\n[CONFIDENCE ANALYSIS]")
logits = test_predictions.predictions
probs = torch.nn.functional.softmax(torch.tensor(logits), dim=1).numpy()
max_probs = np.max(probs, axis=1)
correct = (test_pred_labels == test_true_labels)

correct_probs = max_probs[correct]
wrong_probs = max_probs[~correct]

print(f"  When correct (n={len(correct_probs)}): mean_conf={np.mean(correct_probs):.4f}")
print(f"  When wrong (n={len(wrong_probs)}): mean_conf={np.mean(wrong_probs):.4f}")

high_conf_wrong = np.where((~correct) & (max_probs > 0.80))[0]
print(f"  High confidence (>0.80) but wrong: {len(high_conf_wrong)}")

print("\n[BASELINE COMPARISON]")
label_counts = Counter(test_true_labels)
majority_label = label_counts.most_common(1)[0][0]
majority_baseline = np.sum(test_true_labels == majority_label) / len(test_true_labels)
print(f"  Most common class: {id2label[majority_label]}")
print(f"  Majority baseline accuracy: {majority_baseline:.4f}")
print(f"  Model accuracy: {test_accuracy:.4f}")
print(f"  Improvement: {test_accuracy - majority_baseline:+.4f}")

print("\n[MODEL CONFIG VERIFICATION]")
saved_config_path = Path("ml_service/emotion_model_multilingual_candidate/config.json")
with open(saved_config_path, 'r') as f:
    saved_config = json.load(f)

saved_id2label = saved_config.get("id2label", {})
print(f"  Saved id2label: {saved_id2label}")

has_generic = any(v.startswith("LABEL_") for v in saved_id2label.values())
if has_generic:
    print(f"  ERROR: Generic labels found")
    exit(1)
else:
    print(f"  OK: All labels are emotion names")

print("\n[PRODUCTION STATUS]")
prod_api_path = Path("ml_service/unified_ml_api_with_model.py")
with open(prod_api_path, 'r') as f:
    prod_content = f.read()

if 'return False' in prod_content:
    print(f"  OK: Production emotion model appears disabled")
else:
    print(f"  WARNING: Cannot verify production disable status")

print("\n" + "=" * 70)
print("TRAINING COMPLETE")
print("=" * 70)
print(f"\nCandidate model saved to: ml_service/emotion_model_multilingual_candidate/")
print(f"Status: DO NOT ENABLE - evaluation complete, integration is separate task")

