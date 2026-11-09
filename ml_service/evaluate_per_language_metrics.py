#!/usr/bin/env python3
import json
import numpy as np
import torch
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification
from sklearn.metrics import accuracy_score, precision_recall_fscore_support, confusion_matrix

print("=" * 70)
print("PER-LANGUAGE HELD-OUT TEST EVALUATION")
print("=" * 70)

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"\n[DEVICE] {device.upper()}")

# Load best model
model_path = Path("ml_service/emotion_model_multilingual_candidate/checkpoint-130")
print(f"\n[LOADING BEST MODEL]")
print(f"  Checkpoint: {model_path}")

tokenizer = AutoTokenizer.from_pretrained("distilroberta-base")
model = AutoModelForSequenceClassification.from_pretrained(str(model_path))
model.to(device)
model.eval()

# Load label mapping
with open("ml_service/emotion_data_multilingual_final_v2/label_mapping.json", 'r') as f:
    label2id = json.load(f)
id2label = {v: k for k, v in label2id.items()}

# Load test data
with open("ml_service/emotion_data_multilingual_final_v2/test.jsonl", 'r', encoding='utf-8') as f:
    test_data = [json.loads(line) for line in f]

print(f"\n[TEST DATA LOADED]")
print(f"  Total samples: {len(test_data)}")

# Prepare test set
texts = [row["text"] for row in test_data]
true_labels = [label2id[row["emotion"]] for row in test_data]
languages = [row["language"] for row in test_data]

# Tokenize
print(f"\n[TOKENIZING]")
encodings = tokenizer(texts, truncation=True, max_length=128, padding="max_length", return_tensors="pt")
encodings = {k: v.to(device) for k, v in encodings.items()}

# Make predictions
print(f"\n[MAKING PREDICTIONS]")
with torch.no_grad():
    outputs = model(**encodings)
    logits = outputs.logits.cpu().numpy()

pred_labels = np.argmax(logits, axis=1)

# Compute confidences
probs = torch.nn.functional.softmax(torch.tensor(logits), dim=1).numpy()
max_probs = np.max(probs, axis=1)

print(f"\n[OVERALL TEST METRICS]")
overall_acc = accuracy_score(true_labels, pred_labels)
overall_precision, overall_recall, overall_f1, _ = precision_recall_fscore_support(
    true_labels, pred_labels, average="macro", zero_division=0
)
print(f"  Accuracy: {overall_acc:.4f}")
print(f"  Macro Precision: {overall_precision:.4f}")
print(f"  Macro Recall: {overall_recall:.4f}")
print(f"  Macro F1: {overall_f1:.4f}")

# Per-language evaluation
print(f"\n[PER-LANGUAGE EVALUATION]")
language_stats = {}

for lang in ['ENGLISH', 'TAGALOG', 'TAGLISH']:
    lang_mask = np.array(languages) == lang
    lang_true = np.array(true_labels)[lang_mask]
    lang_pred = pred_labels[lang_mask]
    lang_probs = max_probs[lang_mask]
    
    lang_acc = accuracy_score(lang_true, lang_pred)
    lang_precision, lang_recall, lang_f1, _ = precision_recall_fscore_support(
        lang_true, lang_pred, average="macro", zero_division=0
    )
    
    language_stats[lang] = {
        'count': int(np.sum(lang_mask)),
        'accuracy': lang_acc,
        'precision': lang_precision,
        'recall': lang_recall,
        'f1': lang_f1,
        'mean_conf_correct': float(np.mean(lang_probs[lang_true == lang_pred])) if np.any(lang_true == lang_pred) else 0.0,
        'mean_conf_wrong': float(np.mean(lang_probs[lang_true != lang_pred])) if np.any(lang_true != lang_pred) else 0.0,
    }
    
    print(f"\n  {lang}:")
    print(f"    Samples: {language_stats[lang]['count']}")
    print(f"    Accuracy: {lang_acc:.4f}")
    print(f"    Macro F1: {lang_f1:.4f}")
    print(f"    Mean confidence (correct): {language_stats[lang]['mean_conf_correct']:.4f}")
    print(f"    Mean confidence (wrong): {language_stats[lang]['mean_conf_wrong']:.4f}")

# Per-emotion metrics for each language
print(f"\n[PER-EMOTION BREAKDOWN BY LANGUAGE]")
for lang in ['ENGLISH', 'TAGALOG', 'TAGLISH']:
    lang_mask = np.array(languages) == lang
    lang_true = np.array(true_labels)[lang_mask]
    lang_pred = pred_labels[lang_mask]
    
    print(f"\n  {lang} (n={np.sum(lang_mask)}):")
    precision_per_class, recall_per_class, f1_per_class, support_per_class = (
        precision_recall_fscore_support(lang_true, lang_pred, average=None, zero_division=0)
    )
    
    print(f"    {'Emotion':<12} {'Precision':>10} {'Recall':>10} {'F1':>10} {'Support':>10}")
    for label_id, label_name in id2label.items():
        if support_per_class[label_id] > 0:
            print(f"    {label_name:<12} {precision_per_class[label_id]:>10.4f} {recall_per_class[label_id]:>10.4f} {f1_per_class[label_id]:>10.4f} {int(support_per_class[label_id]):>10}")

# Per-language confusion matrices
print(f"\n[PER-LANGUAGE CONFUSION MATRICES]")
for lang in ['ENGLISH', 'TAGALOG', 'TAGLISH']:
    lang_mask = np.array(languages) == lang
    lang_true = np.array(true_labels)[lang_mask]
    lang_pred = pred_labels[lang_mask]
    
    if np.sum(lang_mask) > 0:
        cm = confusion_matrix(lang_true, lang_pred, labels=list(range(6)))
        
        print(f"\n  {lang} confusion matrix (rows=true, cols=predicted):")
        print(f"        ", end="")
        for lname in id2label.values():
            print(f"{lname[:4]:>6}", end="")
        print()
        
        for true_idx, label_name in id2label.items():
            print(f"    {label_name[:6]:<6}", end="")
            for pred_idx in range(6):
                print(f"{cm[true_idx, pred_idx]:>6}", end="")
            print()

# Major misclassification patterns
print(f"\n[MAJOR MISCLASSIFICATION PATTERNS]")

confusion_pairs = []
for i in range(len(pred_labels)):
    if true_labels[i] != pred_labels[i]:
        true_name = id2label[true_labels[i]]
        pred_name = id2label[pred_labels[i]]
        confusion_pairs.append((true_name, pred_name, languages[i]))

# Count confusion patterns
from collections import Counter
confusion_counter = Counter((cp[0], cp[1]) for cp in confusion_pairs)

print(f"  Top misclassifications:")
for (true_emotion, pred_emotion), count in confusion_counter.most_common(10):
    print(f"    {true_emotion} -> {pred_emotion}: {count} cases")

# Show example misclassifications
print(f"\n[EXAMPLE MISCLASSIFICATIONS]")
shown_pairs = set()
for i, (true_label, pred_label) in enumerate(zip(true_labels, pred_labels)):
    if true_label != pred_label:
        pair = (id2label[true_label], id2label[pred_label])
        if pair not in shown_pairs and len(shown_pairs) < 5:
            shown_pairs.add(pair)
            print(f"  [{id2label[true_label]} -> {id2label[pred_label]}] {languages[i]}")
            print(f"    Text: \"{test_data[i]['text'][:80]}\"")
            print(f"    Confidence: {max_probs[i]:.4f}")

# Confidence analysis by correctness
print(f"\n[CONFIDENCE ANALYSIS]")
correct_mask = np.array(true_labels) == pred_labels
correct_probs = max_probs[correct_mask]
wrong_probs = max_probs[~correct_mask]

print(f"  When correct (n={np.sum(correct_mask)}): mean_conf={np.mean(correct_probs):.4f}, std={np.std(correct_probs):.4f}")
print(f"  When wrong (n={np.sum(~correct_mask)}): mean_conf={np.mean(wrong_probs):.4f}, std={np.std(wrong_probs):.4f}")

high_conf_wrong = np.where((~correct_mask) & (max_probs > 0.80))[0]
print(f"  High confidence (>0.80) but wrong: {len(high_conf_wrong)}")
if len(high_conf_wrong) > 0:
    for idx in high_conf_wrong[:3]:
        print(f"    [{id2label[true_labels[idx]]} -> {id2label[pred_labels[idx]]}] conf={max_probs[idx]:.4f}")
        print(f"      Text: \"{test_data[idx]['text'][:70]}\"")

print("\n" + "=" * 70)
print("PER-LANGUAGE EVALUATION COMPLETE")
print("=" * 70)
