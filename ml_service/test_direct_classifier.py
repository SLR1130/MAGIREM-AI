"""
PHASE 3: Direct classifier test without Flask/Gemma
Tests the exact current trained_emotion_classifier.py implementation
"""
import sys
import torch
import numpy as np
from pathlib import Path

# Import the current classifier
from trained_emotion_classifier import get_classifier

print("="*80)
print("PHASE 3: DIRECT TRAINED CLASSIFIER TEST")
print("="*80)

classifier = get_classifier()

if not classifier.enabled:
    print("✗ CLASSIFIER FAILED TO LOAD")
    sys.exit(1)

print(f"✓ Classifier loaded")
print(f"  Model path: {classifier.model}")
print(f"  Tokenizer: {classifier.tokenizer}")
print(f"  Device: {classifier.device}")
print(f"  Label mapping: {classifier.id2label}")

test_messages = [
    "I'm a little sad kasi hindi ako nakapasa sa exam today",
    "hindi ako nakapasa sa exam",
    "Kinakabihan ako ksi may exam ako bukas",
    "Masaya ako kasi pasado ako sa exam",
    "Galit ako ksi unfair yung grade ko sa exam",
    "May exam ako bukas",
    "Pakiramdam ko wala nang pag-asa at gusto ko nang mawala",
    "hey"
]

print("\n" + "="*80)
print("TEST PREDICTIONS")
print("="*80)

predictions = []

for i, message in enumerate(test_messages, 1):
    print(f"\n[MESSAGE {i}] {message}")
    
    # Tokenize
    inputs = classifier.tokenizer(
        message,
        truncation=True,
        max_length=128,
        padding="max_length",
        return_tensors="pt"
    )
    
    # Get token IDs
    token_ids = inputs["input_ids"][0].tolist()
    print(f"  Token IDs ({len(token_ids)} tokens): {token_ids[:15]}...")
    
    # Forward pass
    inputs_device = {k: v.to(classifier.device) for k, v in inputs.items()}
    
    with torch.no_grad():
        outputs = classifier.model(**inputs_device)
        logits = outputs.logits[0].cpu().numpy()
    
    print(f"  Logits (raw model output): {logits}")
    print(f"  Logits shape: {logits.shape}")
    print(f"  Logits min: {logits.min():.6f}, max: {logits.max():.6f}, mean: {logits.mean():.6f}")
    
    # Softmax
    exp_logits = np.exp(logits - logits.max())
    probs = exp_logits / exp_logits.sum()
    
    print(f"  Probabilities: {probs}")
    print(f"  Probs sum: {probs.sum():.6f}")
    
    # Prediction
    pred_idx = logits.argmax()
    pred_label = classifier.id2label[pred_idx]
    pred_conf = probs[pred_idx]
    
    print(f"  Predicted: {pred_label} (index {pred_idx}, confidence {pred_conf:.4f})")
    
    # Store for summary
    predictions.append({
        'message': message,
        'predicted': pred_label,
        'confidence': pred_conf,
        'logits': logits.tolist(),
        'probabilities': probs.tolist()
    })

print("\n" + "="*80)
print("SUMMARY")
print("="*80)

print("\nPredictions:")
for i, pred in enumerate(predictions, 1):
    print(f"  {i}. {pred['predicted']} ({pred['confidence']:.2%})")

print("\nLogits distribution:")
for i, pred in enumerate(predictions, 1):
    print(f"  {i}. min={min(pred['logits']):.4f}, max={max(pred['logits']):.4f}, range={max(pred['logits'])-min(pred['logits']):.4f}")

print("\nProbability distribution:")
for i, pred in enumerate(predictions, 1):
    probs_dict = {classifier.id2label[j]: pred['probabilities'][j] for j in range(len(pred['probabilities']))}
    sorted_probs = sorted(probs_dict.items(), key=lambda x: x[1], reverse=True)
    print(f"  {i}. {' | '.join([f'{label}:{conf:.2%}' for label, conf in sorted_probs])}")

print("\n" + "="*80)
print("ANALYSIS")
print("="*80)

# Check if all predictions are neutral
all_neutral = all(p['predicted'] == 'neutral' for p in predictions)
print(f"All predictions neutral: {all_neutral}")

# Check if probabilities are diverse
uniform_threshold = 0.35  # If one class >85%, consider uniform
for i, pred in enumerate(predictions, 1):
    max_prob = max(pred['probabilities'])
    if max_prob > 0.85:
        print(f"  Message {i}: Heavily skewed ({pred['predicted']}={max_prob:.2%})")
    else:
        print(f"  Message {i}: Diverse probabilities")

print("\n✓ Test complete")
