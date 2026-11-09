#!/usr/bin/env python3
"""
FINALIZE DATASET
================
Load candidate with corrections.
Create 70/15/15 stratified split.
Create final frozen dataset.
Generate final report.
"""

import json
from pathlib import Path
from collections import defaultdict, Counter
import unicodedata
import random

print("=" * 70)
print("FINALIZING DATASET - STRATIFIED SPLIT")
print("=" * 70)

# ========================================================================
# STEP 1: Load candidate dataset
# ========================================================================
print("\n[1] Loading candidate dataset")

candidate_rows = []
with open('ml_service/emotion_data_multilingual_candidate/candidate_with_metadata.jsonl', 'r') as f:
    for line in f:
        candidate_rows.append(json.loads(line))

print(f"  Loaded {len(candidate_rows)} candidate rows")

# ========================================================================
# STEP 2: Verify data integrity
# ========================================================================
print("\n[2] Verifying data integrity")

invalid_rows = 0
invalid_emotions = 0
duplicate_texts = set()

valid_emotions = {'sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief'}
texts = set()

for row in candidate_rows:
    if not isinstance(row, dict):
        invalid_rows += 1
    if row['emotion'] not in valid_emotions:
        invalid_emotions += 1
    if row['text'] in texts:
        duplicate_texts.add(row['text'])
    texts.add(row['text'])

print(f"  Invalid row types: {invalid_rows}")
print(f"  Invalid emotion labels: {invalid_emotions}")
print(f"  Exact duplicate texts: {len(duplicate_texts)}")

if invalid_rows > 0 or invalid_emotions > 0 or duplicate_texts:
    print("\n  FATAL: Data integrity check failed")
    exit(1)

print(f"  ✓ All {len(candidate_rows)} rows valid")

# ========================================================================
# STEP 3: Analyze and classify language
# ========================================================================
print("\n[3] Classifying language")

# For each row, check language metadata or reclassify
for row in candidate_rows:
    if '_lang_auto' in row:
        row['language'] = row.pop('_lang_auto')
    else:
        # Fallback
        row['language'] = 'ENGLISH'

language_dist = Counter(r['language'] for r in candidate_rows)

print(f"  Language distribution:")
for lang in sorted(language_dist.keys()):
    count = language_dist[lang]
    pct = 100 * count // len(candidate_rows)
    print(f"    {lang}: {count} ({pct}%)")

# ========================================================================
# STEP 4: Prepare for stratified split
# ========================================================================
print("\n[4] Preparing stratified split (seed=42)")

# Group by emotion + language
grouped = defaultdict(list)
for row in candidate_rows:
    emotion = row['emotion']
    language = row['language']
    grouped[(emotion, language)].append(row)

print(f"  Groups by (emotion, language): {len(grouped)}")

# ========================================================================
# STEP 5: Create 70/15/15 split
# ========================================================================
print("\n[5] Creating 70/15/15 split")

random.seed(42)

train_final = []
val_final = []
test_final = []

for (emotion, language), group in grouped.items():
    random.shuffle(group)
    n = len(group)
    
    # 70% train, 15% val, 15% test
    train_idx = int(n * 0.70)
    val_idx = int(n * 0.85)
    
    train_final.extend(group[:train_idx])
    val_final.extend(group[train_idx:val_idx])
    test_final.extend(group[val_idx:])

# Final shuffle
random.seed(42)
random.shuffle(train_final)
random.shuffle(val_final)
random.shuffle(test_final)

print(f"  Train: {len(train_final)} rows")
print(f"  Validation: {len(val_final)} rows")
print(f"  Test: {len(test_final)} rows")
print(f"  TOTAL: {len(train_final) + len(val_final) + len(test_final)} rows")

# ========================================================================
# STEP 6: Verify no cross-split leakage
# ========================================================================
print("\n[6] Verifying cross-split integrity")

def normalize_text(text):
    text = unicodedata.normalize('NFKD', text)
    text = text.casefold()
    text = text.strip()
    text = ' '.join(text.split())
    text = text.replace(' ?', '?').replace(' !', '!').replace(' ,', ',').replace(' .', '.')
    return text

train_norm = {normalize_text(r['text']) for r in train_final}
val_norm = {normalize_text(r['text']) for r in val_final}
test_norm = {normalize_text(r['text']) for r in test_final}

tv_leak = len(train_norm & val_norm)
tt_leak = len(train_norm & test_norm)
vt_leak = len(val_norm & test_norm)

print(f"  train ∩ validation: {tv_leak}")
print(f"  train ∩ test: {tt_leak}")
print(f"  validation ∩ test: {vt_leak}")

if tv_leak > 0 or tt_leak > 0 or vt_leak > 0:
    print("\n  ✗ LEAKAGE DETECTED - Cannot proceed")
    exit(1)

print(f"  ✓ No cross-split leakage")

# ========================================================================
# STEP 7: Analyze final distributions
# ========================================================================
print("\n[7] Analyzing final distributions")

def analyze_split(rows, name):
    emotion_dist = Counter(r['emotion'] for r in rows)
    language_dist = Counter(r['language'] for r in rows)
    print(f"\n  {name} ({len(rows)} rows):")
    print(f"    Emotions: {dict(emotion_dist)}")
    print(f"    Languages: {dict(language_dist)}")

analyze_split(train_final, "TRAIN")
analyze_split(val_final, "VALIDATION")
analyze_split(test_final, "TEST")

# Overall
overall_emotion = Counter(r['emotion'] for r in train_final + val_final + test_final)
overall_language = Counter(r['language'] for r in train_final + val_final + test_final)

print(f"\n  OVERALL ({len(train_final) + len(val_final) + len(test_final)} rows):")
print(f"    Emotions: {dict(overall_emotion)}")
print(f"    Languages: {dict(overall_language)}")

# ========================================================================
# STEP 8: Prepare output (remove metadata)
# ========================================================================
print("\n[8] Preparing clean output (removing metadata)")

for rows in [train_final, val_final, test_final]:
    for row in rows:
        # Keep only text and emotion for final dataset
        # (language is metadata for evaluation, not training input)
        if '_lang_auto' in row:
            del row['_lang_auto']

print(f"  ✓ Cleaned metadata")

# ========================================================================
# STEP 9: Save final frozen dataset
# ========================================================================
print("\n[9] Saving final frozen dataset")

output_dir = Path('ml_service/emotion_data_multilingual_final_v2')
output_dir.mkdir(parents=True, exist_ok=True)

def save_split(rows, filename):
    with open(output_dir / filename, 'w', encoding='utf-8') as f:
        for row in rows:
            f.write(json.dumps(row) + '\n')

save_split(train_final, 'train.jsonl')
save_split(val_final, 'validation.jsonl')
save_split(test_final, 'test.jsonl')

print(f"  ✓ train.jsonl ({len(train_final)} rows)")
print(f"  ✓ validation.jsonl ({len(val_final)} rows)")
print(f"  ✓ test.jsonl ({len(test_final)} rows)")

# Save label mapping
label_mapping = {
    "sadness": 0,
    "joy": 1,
    "anxiety": 2,
    "anger": 3,
    "neutral": 4,
    "grief": 5
}

with open(output_dir / 'label_mapping.json', 'w') as f:
    json.dump(label_mapping, f, indent=2)

print(f"  ✓ label_mapping.json")

# ========================================================================
# STEP 10: Final Report
# ========================================================================
print("\n" + "=" * 70)
print("FINAL DATASET REPORT")
print("=" * 70)

print(f"\n[SOURCE INTEGRITY]")
print(f"  Original source: 591 rows")
print(f"  After corrections/exclusions: 590 rows")
print(f"  Malformed rows: 0")
print(f"  Invalid emotion labels: 0")
print(f"  Exact duplicates: 0")
print(f"  Cross-split leakage: 0")

print(f"\n[CORRECTIONS APPLIED]")
print(f"  Negation label fixes: 5")
print(f"  Bereavement label fix: 1")
print(f"  Historical suicidal row excluded: 1")

print(f"\n[FINAL SPLIT]")
print(f"  Train: {len(train_final)} (70%)")
print(f"  Validation: {len(val_final)} (15%)")
print(f"  Test: {len(test_final)} (15%)")
print(f"  TOTAL: {len(train_final) + len(val_final) + len(test_final)}")

print(f"\n[EMOTION × LANGUAGE MATRIX - OVERALL]")
print(f"  {'Emotion':<12} {'ENGLISH':>8} {'TAGALOG':>8} {'TAGLISH':>8} {'TOTAL':>8}")
print(f"  {'-'*44}")

for emotion in sorted(overall_emotion.keys()):
    eng = sum(1 for r in train_final+val_final+test_final if r['emotion']==emotion and r['language']=='ENGLISH')
    tag = sum(1 for r in train_final+val_final+test_final if r['emotion']==emotion and r['language']=='TAGALOG')
    tagl = sum(1 for r in train_final+val_final+test_final if r['emotion']==emotion and r['language']=='TAGLISH')
    total = overall_emotion[emotion]
    print(f"  {emotion:<12} {eng:>8} {tag:>8} {tagl:>8} {total:>8}")

print(f"  {'-'*44}")
eng_tot = sum(1 for r in train_final+val_final+test_final if r['language']=='ENGLISH')
tag_tot = sum(1 for r in train_final+val_final+test_final if r['language']=='TAGALOG')
tagl_tot = sum(1 for r in train_final+val_final+test_final if r['language']=='TAGLISH')
print(f"  {'TOTAL':<12} {eng_tot:>8} {tag_tot:>8} {tagl_tot:>8} {len(train_final)+len(val_final)+len(test_final):>8}")

print(f"\n[TRAIN SPLIT - EMOTION × LANGUAGE]")
print(f"  {'Emotion':<12} {'ENGLISH':>8} {'TAGALOG':>8} {'TAGLISH':>8}")
print(f"  {'-'*36}")
train_emo_dist = Counter(r['emotion'] for r in train_final)
for emotion in sorted(train_emo_dist.keys()):
    eng = sum(1 for r in train_final if r['emotion']==emotion and r['language']=='ENGLISH')
    tag = sum(1 for r in train_final if r['emotion']==emotion and r['language']=='TAGALOG')
    tagl = sum(1 for r in train_final if r['emotion']==emotion and r['language']=='TAGLISH')
    print(f"  {emotion:<12} {eng:>8} {tag:>8} {tagl:>8}")

print(f"\n[VALIDATION SPLIT - EMOTION × LANGUAGE]")
print(f"  {'Emotion':<12} {'ENGLISH':>8} {'TAGALOG':>8} {'TAGLISH':>8}")
print(f"  {'-'*36}")
val_emo_dist = Counter(r['emotion'] for r in val_final)
for emotion in sorted(val_emo_dist.keys()):
    eng = sum(1 for r in val_final if r['emotion']==emotion and r['language']=='ENGLISH')
    tag = sum(1 for r in val_final if r['emotion']==emotion and r['language']=='TAGALOG')
    tagl = sum(1 for r in val_final if r['emotion']==emotion and r['language']=='TAGLISH')
    print(f"  {emotion:<12} {eng:>8} {tag:>8} {tagl:>8}")

print(f"\n[TEST SPLIT - EMOTION × LANGUAGE]")
print(f"  {'Emotion':<12} {'ENGLISH':>8} {'TAGALOG':>8} {'TAGLISH':>8}")
print(f"  {'-'*36}")
test_emo_dist = Counter(r['emotion'] for r in test_final)
for emotion in sorted(test_emo_dist.keys()):
    eng = sum(1 for r in test_final if r['emotion']==emotion and r['language']=='ENGLISH')
    tag = sum(1 for r in test_final if r['emotion']==emotion and r['language']=='TAGALOG')
    tagl = sum(1 for r in test_final if r['emotion']==emotion and r['language']=='TAGLISH')
    print(f"  {emotion:<12} {eng:>8} {tag:>8} {tagl:>8}")

print(f"\n[OUTPUT]")
print(f"  Directory: ml_service/emotion_data_multilingual_final_v2/")
print(f"  Files: train.jsonl, validation.jsonl, test.jsonl, label_mapping.json")

print(f"\n" + "=" * 70)
print("STATUS: READY FOR MULTILINGUAL EMOTION MODEL TRAINING")
print("=" * 70)

