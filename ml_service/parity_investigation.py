#!/usr/bin/env python3
"""
PARITY INVESTIGATION: Determine where inference diverges from training
Systematically test: Tokenization → Model Loading → Inference → Evaluation
"""

import json
import hashlib
import numpy as np
import torch
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification, Trainer, TrainingArguments
from datasets import Dataset
from sklearn.metrics import accuracy_score, precision_recall_fscore_support

print("=" * 90)
print("PARITY INVESTIGATION: CHECKPOINT vs INFERENCE vs EVALUATOR")
print("=" * 90)

# Six fixed diagnostic texts (diverse emotions and languages)
SIX_TEXTS = {
    1: ("i don't know how to move forward", "grief", "ENGLISH"),
    2: ("i'm so happy today!", "joy", "ENGLISH"),
    3: ("i can't stop worrying about this", "anxiety", "ENGLISH"),
    4: ("i'm absolutely furious!", "anger", "ENGLISH"),
    5: ("ang palengke ay malapit sa bahay.", "neutral", "TAGALOG"),
    6: ("ang pagkamatay ng lola ay sobrang sakit.", "grief", "TAGLISH"),
}

device = "cuda" if torch.cuda.is_available() else "cpu"
checkpoint_path = Path("ml_service/emotion_model_multilingual_candidate/checkpoint-130")

print(f"\n[SETUP]")
print(f"  Device: {device}")
print(f"  Checkpoint: {checkpoint_path}")
print(f"  Checkpoint exists: {checkpoint_path.exists()}")

# ==============================================================================
# SECTION 1: VERIFY CHECKPOINT FILES
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 1: CHECKPOINT STRUCTURE")
print("=" * 90)

if checkpoint_path.exists():
    files = sorted([f.name for f in checkpoint_path.glob('*')])
    print(f"\nCheckpoint files ({len(files)}):")
    for f in files:
        print(f"  - {f}")

# ==============================================================================
# SECTION 2: LOAD MODEL & TOKENIZER - PATH A (Direct from checkpoint)
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 2: PATH A - LOAD DIRECTLY FROM CHECKPOINT")
print("=" * 90)

print("\n[Loading tokenizer from checkpoint...]")
tokenizer_a = AutoTokenizer.from_pretrained(str(checkpoint_path))
print(f"  Tokenizer type: {type(tokenizer_a)}")
print(f"  Vocab size: {len(tokenizer_a.vocab)}")

print("\n[Loading model from checkpoint...]")
model_a = AutoModelForSequenceClassification.from_pretrained(str(checkpoint_path))
model_a.to(device)
model_a.eval()
print(f"  Model class: {type(model_a)}")
print(f"  Num labels: {model_a.num_labels}")
print(f"  ID2Label: {model_a.config.id2label}")

# ==============================================================================
# SECTION 3: TEST TOKENIZATION - SIX DIVERSE TEXTS
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 3: TOKENIZATION TEST (SIX DIVERSE TEXTS)")
print("=" * 90)

token_data = {}

for idx, (text, true_emotion, language) in SIX_TEXTS.items():
    encoded = tokenizer_a(text, truncation=True, max_length=128, padding="max_length", return_tensors="pt")
    
    input_ids = encoded['input_ids'][0].cpu().numpy()
    attention_mask = encoded['attention_mask'][0].cpu().numpy()
    
    token_hash = hashlib.md5(str(input_ids).encode()).hexdigest()[:12]
    
    decoded = tokenizer_a.decode(input_ids)
    
    token_data[idx] = {
        'text': text,
        'true_emotion': true_emotion,
        'language': language,
        'input_ids': input_ids,
        'attention_mask': attention_mask,
        'hash': token_hash,
        'decoded': decoded,
        'length': int(attention_mask.sum()),
    }
    
    print(f"\n[Sample {idx}] {language} - {true_emotion}")
    print(f"  Text: {text[:60]}")
    print(f"  Input IDs (first 20): {list(input_ids[:20])}")
    print(f"  Attention mask sum: {int(attention_mask.sum())} / 128")
    print(f"  Token hash: {token_hash}")
    print(f"  Decoded: {decoded[:60]}...")

# Check for identical tokens
hashes = [d['hash'] for d in token_data.values()]
unique_hashes = set(hashes)

print(f"\n[TOKEN DIVERSITY CHECK]")
print(f"  Total samples: 6")
print(f"  Unique token hashes: {len(unique_hashes)}")
if len(unique_hashes) == 6:
    print(f"  ✓ ALL TOKENS DIFFER (expected)")
else:
    print(f"  ✗ TOKEN COLLISION DETECTED: {6 - len(unique_hashes)} duplicates")

# ==============================================================================
# SECTION 4: PATH A - INFERENCE WITH torch.no_grad()
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 4: PATH A INFERENCE (Direct model inference)")
print("=" * 90)

path_a_results = {}

with torch.no_grad():
    for idx, data in token_data.items():
        text = data['text']
        input_ids = torch.tensor([data['input_ids']], device=device)
        attention_mask = torch.tensor([data['attention_mask']], device=device)
        
        outputs = model_a(input_ids=input_ids, attention_mask=attention_mask)
        logits = outputs.logits[0].cpu().numpy()
        probs = torch.nn.functional.softmax(torch.tensor(logits), dim=0).numpy()
        pred_idx = int(np.argmax(logits))
        
        label2id = {v: int(k) for k, v in model_a.config.id2label.items()}
        id2label = model_a.config.id2label
        
        path_a_results[idx] = {
            'logits': logits,
            'probs': probs,
            'pred_idx': pred_idx,
            'pred_label': id2label[str(pred_idx)],
            'confidence': float(probs[pred_idx]),
        }
        
        print(f"\n[Sample {idx}] Path A")
        print(f"  Text: {text[:60]}")
        print(f"  True: {data['true_emotion']}")
        print(f"  Logits: {logits}")
        print(f"  Probs: {probs}")
        print(f"  Pred: {id2label[str(pred_idx)]} (idx={pred_idx}, conf={float(probs[pred_idx]):.4f})")
        print(f"  Match: {'✓ CORRECT' if id2label[str(pred_idx)] == data['true_emotion'] else '✗ WRONG'}")

# ==============================================================================
# SECTION 5: PATH B - USE TRAINER.PREDICT() AS IN TRAINING
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 5: PATH B INFERENCE (Trainer.predict())")
print("=" * 90)

# Prepare dataset
texts_list = [data['text'] for data in token_data.values()]
dataset_b = Dataset.from_dict({"text": texts_list})

def tokenize_fn(examples):
    return tokenizer_a(examples["text"], truncation=True, max_length=128, padding="max_length")

dataset_b = dataset_b.map(tokenize_fn, batched=True)

# Trainer
training_args = TrainingArguments(
    output_dir="ml_service/emotion_model_multilingual_candidate/temp_eval",
    per_device_eval_batch_size=16,
    remove_unused_columns=False,
)

trainer = Trainer(
    model=model_a,
    args=training_args,
)

# Predict
pred_output = trainer.predict(dataset_b)
logits_all = pred_output.predictions
predictions_idx = np.argmax(logits_all, axis=1)

path_b_results = {}
id2label_str = model_a.config.id2label

for idx, (sample_idx, local_idx) in enumerate([(k, i) for i, k in enumerate(token_data.keys())]):
    logits = logits_all[local_idx]
    probs = torch.nn.functional.softmax(torch.tensor(logits), dim=0).numpy()
    pred_idx = int(predictions_idx[local_idx])
    
    path_b_results[sample_idx] = {
        'logits': logits,
        'probs': probs,
        'pred_idx': pred_idx,
        'pred_label': id2label_str[str(pred_idx)],
        'confidence': float(probs[pred_idx]),
    }
    
    text = SIX_TEXTS[sample_idx][0]
    true_emotion = SIX_TEXTS[sample_idx][1]
    
    print(f"\n[Sample {sample_idx}] Path B (Trainer)")
    print(f"  Text: {text[:60]}")
    print(f"  True: {true_emotion}")
    print(f"  Logits: {logits}")
    print(f"  Probs: {probs}")
    print(f"  Pred: {id2label_str[str(pred_idx)]} (idx={pred_idx}, conf={float(probs[pred_idx]):.4f})")
    print(f"  Match: {'✓ CORRECT' if id2label_str[str(pred_idx)] == true_emotion else '✗ WRONG'}")

# ==============================================================================
# SECTION 6: PATH C - DIRECT MODEL WITH RAW INPUTS (simulate custom evaluator)
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 6: PATH C INFERENCE (Raw model with encoded inputs)")
print("=" * 90)

path_c_results = {}

with torch.no_grad():
    for idx, data in token_data.items():
        # Recreate inputs
        input_ids = torch.tensor(data['input_ids']).unsqueeze(0).to(device)
        attention_mask = torch.tensor(data['attention_mask']).unsqueeze(0).to(device)
        
        # Forward pass
        outputs = model_a(input_ids=input_ids, attention_mask=attention_mask)
        logits = outputs.logits[0].cpu().numpy()
        probs = torch.nn.functional.softmax(torch.tensor(logits), dim=0).numpy()
        pred_idx = int(np.argmax(logits))
        
        path_c_results[idx] = {
            'logits': logits,
            'probs': probs,
            'pred_idx': pred_idx,
            'pred_label': model_a.config.id2label[str(pred_idx)],
            'confidence': float(probs[pred_idx]),
        }
        
        text = data['text']
        true_emotion = data['true_emotion']
        
        print(f"\n[Sample {idx}] Path C (Raw)")
        print(f"  Text: {text[:60]}")
        print(f"  True: {true_emotion}")
        print(f"  Logits: {logits}")
        print(f"  Probs: {probs}")
        print(f"  Pred: {model_a.config.id2label[str(pred_idx)]} (idx={pred_idx}, conf={float(probs[pred_idx]):.4f})")
        print(f"  Match: {'✓ CORRECT' if model_a.config.id2label[str(pred_idx)] == true_emotion else '✗ WRONG'}")

# ==============================================================================
# SECTION 7: COMPARE PATHS A vs B vs C
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 7: PARITY COMPARISON")
print("=" * 90)

print("\n[COMPARISON TABLE]")
print(f"{'Idx':<4} {'Text':<30} {'True':<10} {'Path A':<15} {'Path B':<15} {'Path C':<15} {'A=B':<6} {'A=C':<6}")
print("-" * 100)

for idx in sorted(token_data.keys()):
    text = token_data[idx]['text'][:27]
    true_emotion = token_data[idx]['true_emotion']
    pred_a = path_a_results[idx]['pred_label']
    pred_b = path_b_results[idx]['pred_label']
    pred_c = path_c_results[idx]['pred_label']
    
    same_ab = "YES" if pred_a == pred_b else "NO"
    same_ac = "YES" if pred_a == pred_c else "NO"
    
    print(f"{idx:<4} {text:<30} {true_emotion:<10} {pred_a:<15} {pred_b:<15} {pred_c:<15} {same_ab:<6} {same_ac:<6}")

# ==============================================================================
# SECTION 8: LOGIT VARIANCE ANALYSIS
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 8: LOGIT VARIANCE ACROSS SAMPLES")
print("=" * 90)

logits_a = np.array([path_a_results[idx]['logits'] for idx in sorted(token_data.keys())])
logits_b = np.array([path_b_results[idx]['logits'] for idx in sorted(token_data.keys())])
logits_c = np.array([path_c_results[idx]['logits'] for idx in sorted(token_data.keys())])

print("\n[PATH A]")
print(f"  Mean logits variance: {np.var(logits_a.mean(axis=0)):.6f}")
print(f"  Max logit range: {np.max(logits_a) - np.min(logits_a):.6f}")
print(f"  Are all logits identical? {np.allclose(logits_a, logits_a[0])}")

print("\n[PATH B]")
print(f"  Mean logits variance: {np.var(logits_b.mean(axis=0)):.6f}")
print(f"  Max logit range: {np.max(logits_b) - np.min(logits_b):.6f}")
print(f"  Are all logits identical? {np.allclose(logits_b, logits_b[0])}")

print("\n[PATH C]")
print(f"  Mean logits variance: {np.var(logits_c.mean(axis=0)):.6f}")
print(f"  Max logit range: {np.max(logits_c) - np.min(logits_c):.6f}")
print(f"  Are all logits identical? {np.allclose(logits_c, logits_c[0])}")

# ==============================================================================
# SECTION 9: CHECKPOINT WEIGHT INSPECTION
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 9: CHECKPOINT WEIGHTS INSPECTION")
print("=" * 90)

state_dict = model_a.state_dict()
classifier_keys = [k for k in state_dict.keys() if 'classifier' in k]

print(f"\n[CLASSIFIER LAYERS]")
for key in sorted(classifier_keys):
    tensor = state_dict[key]
    print(f"\n  {key}")
    print(f"    Shape: {tensor.shape}")
    print(f"    Mean: {float(tensor.mean()):.6f}")
    print(f"    Std: {float(tensor.std()):.6f}")
    print(f"    Min: {float(tensor.min()):.6f}")
    print(f"    Max: {float(tensor.max()):.6f}")
    print(f"    All zeros: {torch.allclose(tensor, torch.zeros_like(tensor))}")
    print(f"    All constant: {torch.allclose(tensor, tensor.view(-1)[0] * torch.ones_like(tensor))}")

# ==============================================================================
# SECTION 10: MODEL LOADING REPORT
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 10: MODEL LOADING REPORT")
print("=" * 90)

print(f"\n[LOADED MODEL STATE]")
print(f"  Model class: {type(model_a)}")
print(f"  Training mode: {model_a.training}")
print(f"  Device: {next(model_a.parameters()).device}")
print(f"  Num parameters: {sum(p.numel() for p in model_a.parameters())}")
print(f"  Num trainable: {sum(p.numel() for p in model_a.parameters() if p.requires_grad)}")

expected_keys = {'roberta.embeddings.word_embeddings.weight', 'classifier.dense.weight', 'classifier.out_proj.weight'}
missing_keys = expected_keys - set(state_dict.keys())
if missing_keys:
    print(f"\n  ✗ MISSING EXPECTED KEYS:")
    for k in sorted(missing_keys):
        print(f"    - {k}")
else:
    print(f"\n  ✓ All expected keys present")

# ==============================================================================
# SECTION 11: TOKENIZER SOURCE VERIFICATION
# ==============================================================================
print("\n" + "=" * 90)
print("SECTION 11: TOKENIZER SOURCES")
print("=" * 90)

print(f"\n[TOKENIZER INFO]")
print(f"  Path A source: {checkpoint_path}")
print(f"  Tokenizer class: {type(tokenizer_a)}")
print(f"  Tokenizer name: {tokenizer_a.name_or_path}")

# Load original model to compare tokenizer
print(f"\n[COMPARING WITH ORIGINAL distilroberta-base]")
tokenizer_original = AutoTokenizer.from_pretrained("distilroberta-base")
print(f"  Original tokenizer: {tokenizer_original.name_or_path}")

# Test if they're the same
test_text = "I am happy"
encoded_a = tokenizer_a(test_text, padding=False)['input_ids']
encoded_orig = tokenizer_original(test_text, padding=False)['input_ids']

same_tokenizer = encoded_a == encoded_orig
print(f"  Same tokenization: {same_tokenizer}")

print("\n" + "=" * 90)
print("INVESTIGATION COMPLETE")
print("=" * 90)

