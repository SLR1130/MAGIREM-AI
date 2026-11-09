#!/usr/bin/env python3
"""
FINAL CORRECTION PASS - Apply only clearly supported corrections
Create emotion_data_multilingual_final/ frozen dataset
"""

import json
import unicodedata
from pathlib import Path
from collections import Counter, defaultdict
from sklearn.model_selection import train_test_split

print("[FINAL CORRECTION PASS - BUILD MULTILINGUAL FINAL DATASET]")
print("=" * 80)

def normalize_text_full(text):
    """Full normalization for deduplication: Unicode + casefold + whitespace collapse + punctuation normalization"""
    text = unicodedata.normalize('NFKD', text)
    text = text.strip()
    text = ' '.join(text.split())
    text = text.casefold()
    # Normalize punctuation spacing (no space before punctuation)
    text = text.replace(' ?', '?').replace(' !', '!').replace(' .', '.')
    text = text.replace(' ,', ',').replace(' ;', ';')
    return text

# ============================================================================
# STEP 1: Load baseline with corrections
# ============================================================================

print("[STEP 1] Load baseline and apply corrections")

baseline_all = []
for file_path in ['emotion_data_clean_final/train.jsonl',
                  'emotion_data_clean_final/validation.jsonl',
                  'emotion_data_clean_final/test.jsonl']:
    with open(file_path, 'r', encoding='utf-8') as f:
        for line in f:
            obj = json.loads(line)
            baseline_all.append({
                'text': obj['text'],
                'emotion': obj['emotion'],
                'file': file_path.split('/')[-1].replace('.jsonl', '')
            })

print(f"Baseline loaded: {len(baseline_all)} examples")

# Apply corrections
corrections_applied = 0
corrections_excluded = 0

corrected_baseline = []
for row in baseline_all:
    text = row['text']
    emotion = row['emotion']
    file_name = row['file']
    
    # CORRECTION 1: Clear negation errors (joy → neutral)
    if text == "this doesn't make me angry" and emotion == 'joy':
        emotion = 'neutral'
        corrections_applied += 1
        print(f"✓ Corrected: \"this doesn't make me angry\" joy→neutral")
    elif text == "i'm not sad" and emotion == 'joy':
        emotion = 'neutral'
        corrections_applied += 1
        print(f"✓ Corrected: \"i'm not sad\" joy→neutral")
    
    # CORRECTION 2: Historical suicidal ideation + current recovery = EXCLUDE
    elif "years ago i wanted to die" in text.lower() and "i'm okay now" in text.lower():
        corrections_excluded += 1
        print(f"✓ Excluded: Historical suicidal with recovery (ambiguous/historical)")
        continue  # Skip this row
    
    # CORRECTION 3: Pet death → grief
    elif "ok my cat die yesterday" in text.lower() and emotion == 'neutral':
        emotion = 'grief'
        corrections_applied += 1
        print(f"✓ Corrected: \"ok my cat die yesterday\" neutral→grief")
    
    # CORRECTION 4: Remove near-duplicate punctuation variant
    # Keep only the version in train.jsonl, skip validation variant
    elif "medyo mahirap ang sitwasyon ko sa pera" in text and file_name == 'validation':
        corrections_excluded += 1
        print(f"✓ Excluded: Near-duplicate from validation (keeping train version)")
        continue  # Skip validation version
    
    corrected_baseline.append({
        'text': normalize_text_full(text),
        'emotion': emotion,
        'source': 'baseline'
    })

print(f"Baseline corrections applied: {corrections_applied}")
print(f"Baseline rows excluded: {corrections_excluded}")
print(f"Baseline rows retained: {len(corrected_baseline)}")

# ============================================================================
# STEP 2: Load and validate Filipino expansion
# ============================================================================

print("\n[STEP 2] Load Filipino expansion and validate REVIEW examples")

# Re-evaluate the 6 REVIEW examples from previous audit
review_decisions = {
    # TAGALOG: Place loss != bereavement
    "ang childhood place namin ay demolished na at parang part ng identity ay nawala": "EXCLUDE",
    
    # TAGLISH: Death of parent = GRIEF (not sadness)
    "napakawala ko ng mom last year at sometimes i still expect to see her sa kitchen": "RECLASSIFY_GRIEF",
    
    # TAGLISH: Cognitive decline/anticipatory grief
    "ang lolo ko ay nag-deteriorate mentally at hindi siya nag-recognize ng name ko last visit": "RECLASSIFY_GRIEF",
}

expansion_all = []
review_count = 0
for file_path in ['emotion_data_fil_expansion/tagalog.jsonl',
                  'emotion_data_fil_expansion/taglish.jsonl']:
    with open(file_path, 'r', encoding='utf-8') as f:
        for line in f:
            obj = json.loads(line)
            text = obj['text']
            emotion = obj['emotion']
            
            # Check if this is a REVIEW example
            norm_text = normalize_text_full(text)
            is_review = False
            for review_text, decision in review_decisions.items():
                if normalize_text_full(review_text) == norm_text:
                    is_review = True
                    if decision == "EXCLUDE":
                        review_count += 1
                        print(f"✓ Review decision - EXCLUDE: \"{text[:60]}...\"")
                        continue  # Skip this row
                    elif decision == "RECLASSIFY_GRIEF":
                        emotion = 'grief'
                        review_count += 1
                        print(f"✓ Review decision - RECLASSIFY: \"{text[:60]}...\" sadness→grief")
                    break
            
            if not is_review or decision != "EXCLUDE":
                expansion_all.append({
                    'text': norm_text,
                    'emotion': emotion,
                    'source': 'expansion'
                })

print(f"Filipino expansion review decisions applied: {review_count}")
print(f"Filipino expansion rows loaded: {len(expansion_all)}")

# ============================================================================
# STEP 3: Combine and deduplicate
# ============================================================================

print("\n[STEP 3] Combine baseline + expansion and check for cross-dataset duplicates")

combined = corrected_baseline + expansion_all
print(f"Combined pool before deduplication: {len(combined)}")

# Track which come from baseline vs expansion for leakage check
unique_examples = {}
exact_dups_within = 0
exact_dups_cross = 0

for row in combined:
    text = row['text']
    emotion = row['emotion']
    source = row['source']
    
    if text not in unique_examples:
        unique_examples[text] = (emotion, source)
    else:
        existing_emotion, existing_source = unique_examples[text]
        if existing_emotion == emotion:
            exact_dups_within += 1
            print(f"Exact duplicate (same emotion): \"{text[:60]}...\"")
        else:
            exact_dups_cross += 1
            print(f"⚠️ Conflicting labels for same text: \"{text[:60]}...\"")

deduplicated = [{'text': text, 'emotion': emotion} 
                for text, (emotion, _) in unique_examples.items()]
duplicates_removed = len(combined) - len(deduplicated)

print(f"Exact duplicates removed: {exact_dups_within}")
print(f"Conflicting-label duplicates: {exact_dups_cross}")
print(f"Total deduplicated: {len(deduplicated)}")

# ============================================================================
# STEP 4: Class distribution
# ============================================================================

print("\n[STEP 4] Class distribution after corrections")

emotion_dist = Counter(ex['emotion'] for ex in deduplicated)
print(f"\nEmotion | Count")
print(f"────────┼──────")
for emotion in ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']:
    count = emotion_dist[emotion]
    print(f"{emotion:7s} | {count:5d}")

print(f"TOTAL   | {len(deduplicated):5d}")

# ============================================================================
# STEP 5: Create 70/15/15 split
# ============================================================================

print("\n[STEP 5] Create 70/15/15 split with stratification")

texts = [ex['text'] for ex in deduplicated]
emotions = [ex['emotion'] for ex in deduplicated]
indices = list(range(len(texts)))

train_indices, remaining_indices = train_test_split(
    indices,
    test_size=0.30,
    random_state=42,
    stratify=emotions
)

remaining_emotions = [emotions[i] for i in remaining_indices]
val_indices, test_indices = train_test_split(
    remaining_indices,
    test_size=0.50,
    random_state=42,
    stratify=remaining_emotions
)

train_set = [deduplicated[i] for i in train_indices]
val_set = [deduplicated[i] for i in val_indices]
test_set = [deduplicated[i] for i in test_indices]

print(f"Train: {len(train_set)}")
print(f"Validation: {len(val_set)}")
print(f"Test: {len(test_set)}")
print(f"Total: {len(train_set) + len(val_set) + len(test_set)}")

# ============================================================================
# STEP 6: Verify zero leakage
# ============================================================================

print("\n[STEP 6] Verify zero leakage across splits")

train_texts = set(ex['text'] for ex in train_set)
val_texts = set(ex['text'] for ex in val_set)
test_texts = set(ex['text'] for ex in test_set)

train_val = len(train_texts & val_texts)
train_test = len(train_texts & test_texts)
val_test = len(val_texts & test_texts)

print(f"Train ∩ Validation: {train_val}")
print(f"Train ∩ Test: {train_test}")
print(f"Validation ∩ Test: {val_test}")

if train_val == 0 and train_test == 0 and val_test == 0:
    print(f"✓ LEAKAGE = 0")
else:
    print(f"✗ LEAKAGE DETECTED - ABORT")
    exit(1)

# ============================================================================
# STEP 7: Emotion × Language breakdown
# ============================================================================

print("\n[STEP 7] Final emotion × language matrix (all data)")

# Simple language classification from text content
def classify_language(text):
    """Classify text as ENGLISH / TAGALOG / TAGLISH based on content markers"""
    tagalog_markers = {'ang', 'sa', 'ay', 'na', 'ng', 'ko', 'mo', 'niya', 'kami', 'namin', 'sila', 'medyo', 'mahirap', 'pwedi', 'bakla', 'may', 'para'}
    words = set(text.lower().split())
    tagalog_matches = len(words & tagalog_markers)
    
    if tagalog_matches >= 2:
        return 'TAGLISH'  # Code-switching
    elif tagalog_matches == 1 and len(text.split()) > 3:
        return 'TAGLISH'
    elif 'medyo' in text or 'mahirap' in text or 'pwedi' in text:
        return 'TAGLISH'
    else:
        return 'ENGLISH'

# Classify all examples
for ex in deduplicated:
    ex['language'] = classify_language(ex['text'])

# Build matrices
final_matrix = defaultdict(lambda: {'ENGLISH': 0, 'TAGALOG': 0, 'TAGLISH': 0})
train_matrix = defaultdict(lambda: {'ENGLISH': 0, 'TAGALOG': 0, 'TAGLISH': 0})
val_matrix = defaultdict(lambda: {'ENGLISH': 0, 'TAGALOG': 0, 'TAGLISH': 0})
test_matrix = defaultdict(lambda: {'ENGLISH': 0, 'TAGALOG': 0, 'TAGLISH': 0})

for ex in deduplicated:
    emotion = ex['emotion']
    language = ex['language']
    final_matrix[emotion][language] += 1

for ex in train_set:
    emotion = ex['emotion']
    language = ex['language']
    train_matrix[emotion][language] += 1

for ex in val_set:
    emotion = ex['emotion']
    language = ex['language']
    val_matrix[emotion][language] += 1

for ex in test_set:
    emotion = ex['emotion']
    language = ex['language']
    test_matrix[emotion][language] += 1

print("\nFINAL EMOTION × LANGUAGE MATRIX (All Data):")
print("Emotion    | English | Tagalog | Taglish | Total")
print("───────────┼─────────┼─────────┼─────────┼──────")
grand_total = 0
for emotion in ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']:
    en = final_matrix[emotion]['ENGLISH']
    tl = final_matrix[emotion]['TAGALOG']
    tg = final_matrix[emotion]['TAGLISH']
    total = en + tl + tg
    grand_total += total
    print(f"{emotion:9s} | {en:7d} | {tl:7d} | {tg:7d} | {total:4d}")

print("───────────┼─────────┼─────────┼─────────┼──────")
total_en = sum(final_matrix[e]['ENGLISH'] for e in final_matrix)
total_tl = sum(final_matrix[e]['TAGALOG'] for e in final_matrix)
total_tg = sum(final_matrix[e]['TAGLISH'] for e in final_matrix)
print(f"{'TOTAL':9s} | {total_en:7d} | {total_tl:7d} | {total_tg:7d} | {grand_total:4d}")

# ============================================================================
# STEP 8: Train/Val/Test matrices
# ============================================================================

print("\n[TRAIN SET] Emotion × Language:")
print("Emotion    | English | Tagalog | Taglish")
for emotion in ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']:
    en = train_matrix[emotion]['ENGLISH']
    tl = train_matrix[emotion]['TAGALOG']
    tg = train_matrix[emotion]['TAGLISH']
    print(f"{emotion:9s} | {en:7d} | {tl:7d} | {tg:7d}")

print("\n[VALIDATION SET] Emotion × Language:")
print("Emotion    | English | Tagalog | Taglish")
for emotion in ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']:
    en = val_matrix[emotion]['ENGLISH']
    tl = val_matrix[emotion]['TAGALOG']
    tg = val_matrix[emotion]['TAGLISH']
    print(f"{emotion:9s} | {en:7d} | {tl:7d} | {tg:7d}")

print("\n[TEST SET] Emotion × Language:")
print("Emotion    | English | Tagalog | Taglish")
for emotion in ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']:
    en = test_matrix[emotion]['ENGLISH']
    tl = test_matrix[emotion]['TAGALOG']
    tg = test_matrix[emotion]['TAGLISH']
    print(f"{emotion:9s} | {en:7d} | {tl:7d} | {tg:7d}")

# ============================================================================
# STEP 9: Create output files
# ============================================================================

print("\n[STEP 9] Create output files")

output_dir = Path('emotion_data_multilingual_final')
output_dir.mkdir(exist_ok=True)

with open(output_dir / 'train.jsonl', 'w', encoding='utf-8') as f:
    for ex in train_set:
        f.write(json.dumps({'text': ex['text'], 'emotion': ex['emotion']}) + '\n')

with open(output_dir / 'validation.jsonl', 'w', encoding='utf-8') as f:
    for ex in val_set:
        f.write(json.dumps({'text': ex['text'], 'emotion': ex['emotion']}) + '\n')

with open(output_dir / 'test.jsonl', 'w', encoding='utf-8') as f:
    for ex in test_set:
        f.write(json.dumps({'text': ex['text'], 'emotion': ex['emotion']}) + '\n')

label_mapping = {
    "label2id": {
        "sadness": 0,
        "joy": 1,
        "anxiety": 2,
        "anger": 3,
        "neutral": 4,
        "grief": 5
    },
    "id2label": {
        "0": "sadness",
        "1": "joy",
        "2": "anxiety",
        "3": "anger",
        "4": "neutral",
        "5": "grief"
    }
}

with open(output_dir / 'label_mapping.json', 'w', encoding='utf-8') as f:
    json.dump(label_mapping, f, indent=2)

print(f"✓ train.jsonl ({len(train_set)} rows)")
print(f"✓ validation.jsonl ({len(val_set)} rows)")
print(f"✓ test.jsonl ({len(test_set)} rows)")
print(f"✓ label_mapping.json")

# ============================================================================
# FINAL SUMMARY
# ============================================================================

print("\n[FINAL SUMMARY]")
print("=" * 80)

print(f"\nCorrections Applied:")
print(f"  Baseline corrections: {corrections_applied}")
print(f"  Baseline exclusions: {corrections_excluded}")
print(f"  Review decisions: {review_count}")

print(f"\nDataset Composition:")
print(f"  Baseline rows retained: {len(corrected_baseline)}")
print(f"  Filipino expansion retained: {len(expansion_all)}")
print(f"  Exact duplicates removed: {duplicates_removed}")
print(f"  Final unique examples: {len(deduplicated)}")

print(f"\nFinal Split:")
print(f"  Train: {len(train_set)} ({len(train_set)/len(deduplicated)*100:.1f}%)")
print(f"  Validation: {len(val_set)} ({len(val_set)/len(deduplicated)*100:.1f}%)")
print(f"  Test: {len(test_set)} ({len(test_set)/len(deduplicated)*100:.1f}%)")

print(f"\nLanguage Coverage (Final):")
print(f"  English: {total_en} ({total_en/grand_total*100:.1f}%)")
print(f"  Tagalog: {total_tl} ({total_tl/grand_total*100:.1f}%)")
print(f"  Taglish: {total_tg} ({total_tg/grand_total*100:.1f}%)")

print(f"\nData Integrity:")
print(f"  Exact duplicates: {exact_dups_within}")
print(f"  Conflicting-label groups: {exact_dups_cross}")
print(f"  Cross-split leakage: 0 ✓")

print(f"\n✓ FROZEN DATASET READY: emotion_data_multilingual_final/")
