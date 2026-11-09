#!/usr/bin/env python3
import json
from pathlib import Path
from collections import defaultdict, Counter
import unicodedata
import random

def normalize_text(text):
    text = unicodedata.normalize('NFKD', text)
    text = text.casefold()
    text = text.strip()
    text = ' '.join(text.split())
    text = text.replace(' ?', '?').replace(' !', '!').replace(' ,', ',').replace(' .', '.')
    return text

print("=" * 70)
print("FINAL DATASET CORRECTION PASS")
print("=" * 70)

# STEP 1: Load data
print("\n[STEP 1] Loading datasets...")
train_rows = []
with open('ml_service/emotion_data_clean_final/train.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            train_rows.append(json.loads(line.strip()))

val_rows = []
with open('ml_service/emotion_data_clean_final/validation.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            val_rows.append(json.loads(line.strip()))

test_rows = []
with open('ml_service/emotion_data_clean_final/test.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            test_rows.append(json.loads(line.strip()))

tag_rows = []
with open('ml_service/emotion_data_fil_expansion/tagalog.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            tag_rows.append(json.loads(line.strip()))

tagl_rows = []
with open('ml_service/emotion_data_fil_expansion/taglish.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if line.strip():
            tagl_rows.append(json.loads(line.strip()))

print(f"  Train: {len(train_rows)}")
print(f"  Validation: {len(val_rows)}")
print(f"  Test: {len(test_rows)}")
print(f"  Tagalog expansion: {len(tag_rows)}")
print(f"  Taglish expansion: {len(tagl_rows)}")

# STEP 2: Apply corrections to train
print("\n[STEP 2] Correcting train.jsonl...")
print(f"  DEBUG: train_rows type = {type(train_rows)}, len = {len(train_rows)}")
if train_rows:
    print(f"  DEBUG: train_rows[0] type = {type(train_rows[0])}")

corrections_train = {
    "years ago i wanted to die, but i'm okay now.": ('neutral', 'sadness'),
    "ok my cat die yesterday": ('neutral', 'sadness'),
    "i'm not struggling": ('joy', 'neutral'),
    "i'm not worried": ('joy', 'neutral'),
    "i'm not upset": ('joy', 'neutral'),
}

lang_train = {
    "baklla si lei": 'Tagalog',
    "may tililing kb": 'Tagalog',
}

emotion_fixed_train = 0
for i, row in enumerate(train_rows):
    if i == 0:
        print(f"  DEBUG first iteration: row type = {type(row)}, row = {row}")
    if not isinstance(row, dict):
        print(f"  ERROR: row {i} is not a dict, type = {type(row)}")
        continue
    if row['text'] in corrections_train:
        old_emo, new_emo = corrections_train[row['text']]
        if row['emotion'] == old_emo:
            row['emotion'] = new_emo
            print(f"  ✓ {row['text'][:50]}... : {old_emo} → {new_emo}")
            emotion_fixed_train += 1
    
    if row['text'] in lang_train:
        row['language'] = lang_train[row['text']]
        print(f"  ✓ {row['text'][:50]}... : added Tagalog")

print(f"  Total: {emotion_fixed_train} emotions fixed")

# STEP 3: Apply corrections to validation
print("\n[STEP 3] Correcting validation.jsonl...")
corrections_val = {
    "i'm not sad": ('joy', 'neutral'),
    "this doesn't make me angry": ('joy', 'neutral'),
}

lang_val = {
    "bakla san galing yan": 'Tagalog',
}

dup_text_norm = normalize_text("okay, medyo mahirap ang sitwasyon ko sa pera, ano ba ang pwedi kong gawin? any tips")

emotion_fixed_val = 0
removed_val = 0
val_rows_filtered = []

for row in val_rows:
    # Check for duplicate
    normalized = normalize_text(row['text'])
    if normalized == dup_text_norm:
        print(f"  ✗ Removed duplicate: {row['text'][:50]}...")
        removed_val += 1
        continue
    
    # Fix emotions
    if row['text'] in corrections_val:
        old_emo, new_emo = corrections_val[row['text']]
        if row['emotion'] == old_emo:
            row['emotion'] = new_emo
            print(f"  ✓ {row['text'][:50]}... : {old_emo} → {new_emo}")
            emotion_fixed_val += 1
    
    # Add language
    if row['text'] in lang_val:
        row['language'] = lang_val[row['text']]
        print(f"  ✓ {row['text'][:50]}... : added Tagalog")
    
    val_rows_filtered.append(row)

print(f"  Total: {emotion_fixed_val} emotions fixed, {removed_val} duplicates removed")

# STEP 4: Apply corrections to test
print("\n[STEP 4] Correcting test.jsonl...")
corrections_test = {
    "this doesn't make me angry": ('joy', 'neutral'),
}

emotion_fixed_test = 0
for row in test_rows:
    if row['text'] in corrections_test:
        old_emo, new_emo = corrections_test[row['text']]
        if row['emotion'] == old_emo:
            row['emotion'] = new_emo
            print(f"  ✓ {row['text'][:50]}... : {old_emo} → {new_emo}")
            emotion_fixed_test += 1

print(f"  Total: {emotion_fixed_test} emotions fixed")

# STEP 5: Apply corrections to taglish
print("\n[STEP 5] Correcting taglish.jsonl...")
corrections_tagl = {
    "napakawala ko ng mom last year at sometimes i still expect to see her sa kitchen.": ('sadness', 'grief'),
    "ang lolo ko ay nag-deteriorate mentally at hindi siya nag-recognize ng name ko last visit.": ('sadness', 'grief'),
}

emotion_fixed_tagl = 0
for row in tagl_rows:
    if row['text'] in corrections_tagl:
        old_emo, new_emo = corrections_tagl[row['text']]
        if row['emotion'] == old_emo:
            row['emotion'] = new_emo
            print(f"  ✓ {row['text'][:50]}... : {old_emo} → {new_emo}")
            emotion_fixed_tagl += 1

print(f"  Total: {emotion_fixed_tagl} emotions fixed")

# STEP 6: Save corrected datasets
print("\n[STEP 6] Saving corrected datasets...")
Path('ml_service/emotion_data_clean_final').mkdir(parents=True, exist_ok=True)
with open('ml_service/emotion_data_clean_final/train.jsonl', 'w', encoding='utf-8') as f:
    for row in train_rows:
        f.write(json.dumps(row) + '\n')
print("  ✓ train.jsonl saved")

with open('ml_service/emotion_data_clean_final/validation.jsonl', 'w', encoding='utf-8') as f:
    for row in val_rows_filtered:
        f.write(json.dumps(row) + '\n')
print("  ✓ validation.jsonl saved")

with open('ml_service/emotion_data_clean_final/test.jsonl', 'w', encoding='utf-8') as f:
    for row in test_rows:
        f.write(json.dumps(row) + '\n')
print("  ✓ test.jsonl saved")

with open('ml_service/emotion_data_fil_expansion/tagalog.jsonl', 'w', encoding='utf-8') as f:
    for row in tag_rows:
        f.write(json.dumps(row) + '\n')
print("  ✓ tagalog.jsonl saved")

with open('ml_service/emotion_data_fil_expansion/taglish.jsonl', 'w', encoding='utf-8') as f:
    for row in tagl_rows:
        f.write(json.dumps(row) + '\n')
print("  ✓ taglish.jsonl saved")

# STEP 7: Rebuild final dataset
print("\n[STEP 7] Rebuilding final frozen dataset...")
all_data = train_rows + val_rows_filtered + test_rows + tag_rows + tagl_rows
print(f"  Total examples combined: {len(all_data)}")

# Deduplicate by normalized text
seen = {}
unique_data = []
conflicts = 0

for row in all_data:
    normalized = normalize_text(row['text'])
    if normalized not in seen:
        seen[normalized] = row
        unique_data.append(row)
    else:
        if seen[normalized]['emotion'] != row['emotion']:
            conflicts += 1
            print(f"    ⚠ CONFLICT: {row['text'][:50]}...")

print(f"  Unique examples: {len(unique_data)}")
print(f"  Duplicates removed: {len(all_data) - len(unique_data)}")
print(f"  Conflicting labels: {conflicts}")

# Analyze emotion distribution
emotion_dist = Counter(row['emotion'] for row in unique_data)
language_dist = Counter(row.get('language', 'English') for row in unique_data)

print(f"\n  Emotion Distribution:")
for emotion in sorted(emotion_dist.keys()):
    count = emotion_dist[emotion]
    pct = 100 * count // len(unique_data)
    print(f"    {emotion}: {count} ({pct}%)")

print(f"\n  Language Distribution:")
for lang in sorted(language_dist.keys()):
    count = language_dist[lang]
    pct = 100 * count // len(unique_data)
    print(f"    {lang}: {count} ({pct}%)")

# Stratified 70/15/15 split
print(f"\n  Creating stratified 70/15/15 split...")
grouped = defaultdict(list)
for row in unique_data:
    emotion = row['emotion']
    language = row.get('language', 'English')
    grouped[(emotion, language)].append(row)

random.seed(42)
train_final = []
val_final = []
test_final = []

for (emotion, language), group in grouped.items():
    random.shuffle(group)
    n = len(group)
    split_train = int(n * 0.70)
    split_val = int(n * 0.85)
    
    train_final.extend(group[:split_train])
    val_final.extend(group[split_train:split_val])
    test_final.extend(group[split_val:])

random.shuffle(train_final)
random.shuffle(val_final)
random.shuffle(test_final)

print(f"    Train: {len(train_final)}")
print(f"    Validation: {len(val_final)}")
print(f"    Test: {len(test_final)}")

# Verify no cross-split leakage
train_norm = {normalize_text(r['text']) for r in train_final}
val_norm = {normalize_text(r['text']) for r in val_final}
test_norm = {normalize_text(r['text']) for r in test_final}

tv_overlap = len(train_norm & val_norm)
tt_overlap = len(train_norm & test_norm)
vt_overlap = len(val_norm & test_norm)

print(f"\n  Cross-split leakage check:")
print(f"    train ∩ validation: {tv_overlap}")
print(f"    train ∩ test: {tt_overlap}")
print(f"    validation ∩ test: {vt_overlap}")

# STEP 8: Save final dataset
print("\n[STEP 8] Saving final frozen dataset...")
output_dir = Path('ml_service/emotion_data_multilingual_final')
output_dir.mkdir(parents=True, exist_ok=True)

with open(output_dir / 'train.jsonl', 'w', encoding='utf-8') as f:
    for row in train_final:
        f.write(json.dumps(row) + '\n')
print("  ✓ train.jsonl")

with open(output_dir / 'validation.jsonl', 'w', encoding='utf-8') as f:
    for row in val_final:
        f.write(json.dumps(row) + '\n')
print("  ✓ validation.jsonl")

with open(output_dir / 'test.jsonl', 'w', encoding='utf-8') as f:
    for row in test_final:
        f.write(json.dumps(row) + '\n')
print("  ✓ test.jsonl")

label_mapping = {
    "sadness": 0,
    "joy": 1,
    "anxiety": 2,
    "anger": 3,
    "neutral": 4,
    "grief": 5
}

with open(output_dir / 'label_mapping.json', 'w', encoding='utf-8') as f:
    json.dump(label_mapping, f, indent=2)
print("  ✓ label_mapping.json")

# Final summary
print("\n" + "=" * 70)
print("FINAL DATASET STATUS - READY FOR TRAINING")
print("=" * 70)
print(f"\n✓ Baseline Corrections Applied:")
print(f"  - Train: {emotion_fixed_train} emotions")
print(f"  - Validation: {emotion_fixed_val} emotions, {removed_val} duplicates removed")
print(f"  - Test: {emotion_fixed_test} emotions")
print(f"\n✓ Expansion Corrections Applied:")
print(f"  - Taglish: {emotion_fixed_tagl} emotions")
print(f"\n✓ Final Dataset Metrics:")
print(f"  - Total unique examples: {len(unique_data)}")
print(f"  - Exact duplicates removed: {len(all_data) - len(unique_data)}")
print(f"  - Conflicting labels: {conflicts}")
print(f"  - Cross-split leakage: {max(tv_overlap, tt_overlap, vt_overlap)}")
print(f"\n✓ Final Train/Val/Test Split:")
print(f"  - Train: {len(train_final)} (70%)")
print(f"  - Validation: {len(val_final)} (15%)")
print(f"  - Test: {len(test_final)} (15%)")
print(f"  - TOTAL: {len(train_final) + len(val_final) + len(test_final)}")
print(f"\n✓ Output Directory: ml_service/emotion_data_multilingual_final/")
print(f"\n" + "=" * 70)
print("STATUS: READY FOR MULTILINGUAL EMOTION MODEL TRAINING")
print("=" * 70)
