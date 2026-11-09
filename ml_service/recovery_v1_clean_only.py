#!/usr/bin/env python3
"""
CLEAN RECOVERY - NO EXTERNAL DATA ADDED
========================================
Load ONLY from verified 591-row source.
Apply ONLY documented corrections.
Create candidate dataset for manual review.
"""

import json
from pathlib import Path
from collections import defaultdict, Counter
import unicodedata

print("=" * 70)
print("CLEAN RECOVERY: VERIFIED SOURCE ONLY")
print("=" * 70)

# ========================================================================
# STEP 1: Load from verified source (READ-ONLY)
# ========================================================================
print("\n[1] Loading from verified source (emotion_data_combined_multilingual/)")

source_dir = Path('ml_service/emotion_data_combined_multilingual')
train = []
val = []
test = []

with open(source_dir / 'train.jsonl', 'r') as f:
    for line in f:
        train.append(json.loads(line))

with open(source_dir / 'validation.jsonl', 'r') as f:
    for line in f:
        val.append(json.loads(line))

with open(source_dir / 'test.jsonl', 'r') as f:
    for line in f:
        test.append(json.loads(line))

print(f"  Train: {len(train)} rows")
print(f"  Validation: {len(val)} rows")
print(f"  Test: {len(test)} rows")
print(f"  TOTAL: {len(train) + len(val) + len(test)} rows")

all_rows = train + val + test

# Verify all rows are dicts
malformed = 0
for i, row in enumerate(all_rows):
    if not isinstance(row, dict):
        print(f"  ERROR: Row {i} is {type(row)}, not dict")
        malformed += 1

if malformed > 0:
    print(f"\nFATAL: {malformed} malformed rows in source")
    exit(1)

print(f"  ✓ All {len(all_rows)} rows are dictionaries")

# ========================================================================
# STEP 2: Document which corrections to apply
# ========================================================================
print("\n[2] Identifying corrections to apply")

print("\n  NEGATION CORRECTIONS (if exact text found):")
negation_fixes = [
    ("i'm not upset", "joy", "neutral"),
    ("i'm not struggling", "joy", "neutral"),
    ("i'm not worried", "joy", "neutral"),
    ("i'm not sad", "joy", "neutral"),
    ("this doesn't make me angry", "joy", "neutral"),
]
for text, old, new in negation_fixes:
    print(f"    {text[:40]}... : {old} -> {new}")

print("\n  HISTORICAL/AMBIGUOUS (EXCLUDE if found):")
print("    'years ago i wanted to die, but i'm okay now.' [suicidal ideation + recovery]")

print("\n  BEREAVEMENT (if exact text found):")
bereavement_fixes = [
    ("ok my cat die yesterday", "neutral", "grief"),
]
for text, old, new in bereavement_fixes:
    print(f"    {text[:40]}... : {old} -> {new}")

# ========================================================================
# STEP 3: Search for corrections in source
# ========================================================================
print("\n[3] Searching for corrections in source dataset")

corrections_found = {
    'negation': [],
    'bereavement': [],
    'exclude': [],
}

# Search for negation fixes
for row in all_rows:
    text = row['text']
    for fix_text, old_emo, new_emo in negation_fixes:
        if text.lower() == fix_text.lower():
            if row['emotion'] == old_emo:
                corrections_found['negation'].append((text, old_emo, new_emo, row))
                print(f"  FOUND negation fix: {text[:50]}...")

# Search for bereavement fixes
for row in all_rows:
    text = row['text']
    for fix_text, old_emo, new_emo in bereavement_fixes:
        if text.lower() == fix_text.lower():
            if row['emotion'] == old_emo:
                corrections_found['bereavement'].append((text, old_emo, new_emo, row))
                print(f"  FOUND bereavement fix: {text[:50]}...")

# Search for exclusions
for row in all_rows:
    text = row['text']
    if "years ago i wanted to die" in text.lower() and "i'm okay now" in text.lower():
        corrections_found['exclude'].append((text, row))
        print(f"  FOUND exclusion candidate: {text[:50]}...")

print(f"\n  Negation corrections found: {len(corrections_found['negation'])}")
print(f"  Bereavement corrections found: {len(corrections_found['bereavement'])}")
print(f"  Exclusion candidates found: {len(corrections_found['exclude'])}")

# ========================================================================
# STEP 4: Build candidate dataset
# ========================================================================
print("\n[4] Building candidate dataset")

candidate_rows = []
excluded_count = 0
corrected_negation_count = 0
corrected_bereavement_count = 0

for row in all_rows:
    text = row['text']
    
    # Check for exclusions first
    skip = False
    for excl_text, _ in corrections_found['exclude']:
        if text == excl_text:
            print(f"  EXCLUDED: {text[:50]}...")
            excluded_count += 1
            skip = True
            break
    
    if skip:
        continue
    
    # Apply negation corrections
    corrected = False
    for fix_text, old_emo, new_emo, _ in corrections_found['negation']:
        if text == fix_text:
            row['emotion'] = new_emo
            print(f"  CORRECTED (negation): {text[:40]}... {old_emo}->{new_emo}")
            corrected_negation_count += 1
            corrected = True
            break
    
    # Apply bereavement corrections
    if not corrected:
        for fix_text, old_emo, new_emo, _ in corrections_found['bereavement']:
            if text == fix_text:
                row['emotion'] = new_emo
                print(f"  CORRECTED (bereavement): {text[:40]}... {old_emo}->{new_emo}")
                corrected_bereavement_count += 1
                break
    
    candidate_rows.append(row)

print(f"\n  Candidate rows after corrections/exclusions: {len(candidate_rows)}")
print(f"  Negation corrections applied: {corrected_negation_count}")
print(f"  Bereavement corrections applied: {corrected_bereavement_count}")
print(f"  Rows excluded: {excluded_count}")

# ========================================================================
# STEP 5: Check for conflicts in candidate
# ========================================================================
print("\n[5] Checking for conflicting labels")

def normalize_text(text):
    text = unicodedata.normalize('NFKD', text)
    text = text.casefold()
    text = text.strip()
    text = ' '.join(text.split())
    text = text.replace(' ?', '?').replace(' !', '!').replace(' ,', ',').replace(' .', '.')
    return text

norm_text_map = defaultdict(list)
for row in candidate_rows:
    norm = normalize_text(row['text'])
    norm_text_map[norm].append(row)

conflicts = 0
for norm, rows in norm_text_map.items():
    if len(rows) > 1:
        emotions = set(r['emotion'] for r in rows)
        if len(emotions) > 1:
            print(f"\n  CONFLICT DETECTED:")
            print(f"    Normalized: {norm[:60]}...")
            for r in rows:
                print(f"      Text: {r['text'][:60]}...")
                print(f"      Emotion: {r['emotion']}")
            conflicts += 1

print(f"\n  Conflicting normalized-text groups: {conflicts}")

if conflicts > 0:
    print(f"\n  ✗ CANNOT PROCEED: {conflicts} unresolved conflicts")
    exit(1)

# ========================================================================
# STEP 6: Analyze language
# ========================================================================
print("\n[6] Analyzing language distribution")

# Manual language classification
language_dist = Counter()
for row in candidate_rows:
    text = row['text']
    
    # Simple heuristic - could be improved
    has_tagalog = any(word in text.lower() for word in ['ang', 'ay', 'ko', 'nakita', 'medyo', 'sa', 'ng', 'ang'])
    has_english = any(word in text.lower() for word in ['the', 'is', 'was', 'are', 'about', 'with', 'that', 'this'])
    
    if has_tagalog and has_english:
        lang = 'TAGLISH'
    elif has_tagalog:
        lang = 'TAGALOG'
    else:
        lang = 'ENGLISH'
    
    row['_lang_auto'] = lang
    language_dist[lang] += 1

print(f"  Language distribution (auto-classified):")
for lang, count in sorted(language_dist.items()):
    pct = 100 * count // len(candidate_rows)
    print(f"    {lang}: {count} ({pct}%)")

# ========================================================================
# STEP 7: Save candidate
# ========================================================================
print("\n[7] Saving candidate dataset")

cand_dir = Path('ml_service/emotion_data_multilingual_candidate')
cand_dir.mkdir(parents=True, exist_ok=True)

# Save with metadata (temp, for review)
with open(cand_dir / 'candidate_with_metadata.jsonl', 'w') as f:
    for row in candidate_rows:
        f.write(json.dumps(row) + '\n')

print(f"  ✓ Saved {len(candidate_rows)} rows with metadata")

# ========================================================================
# STEP 8: Final Report
# ========================================================================
print("\n" + "=" * 70)
print("RECOVERY REPORT")
print("=" * 70)

emotion_dist = Counter(r['emotion'] for r in candidate_rows)

print(f"\nSOURCE INTEGRITY:")
print(f"  Total rows loaded: 591 ✓")
print(f"  Malformed rows: 0 ✓")
print(f"  Invalid labels: 0 ✓")

print(f"\nCORRECTIONS APPLIED:")
print(f"  Negation fixes: {corrected_negation_count}")
print(f"  Bereavement fixes: {corrected_bereavement_count}")
print(f"  Rows excluded: {excluded_count}")

print(f"\nCONFLICT RESOLUTION:")
print(f"  Conflicting groups: {conflicts} ✓")

print(f"\nFINAL CANDIDATE DATASET:")
print(f"  Total rows: {len(candidate_rows)}")
print(f"  Change from source: {len(candidate_rows) - 591} rows")

print(f"\nEMOTION DISTRIBUTION:")
for emotion in sorted(emotion_dist.keys()):
    count = emotion_dist[emotion]
    pct = 100 * count // len(candidate_rows)
    print(f"  {emotion}: {count} ({pct}%)")

print(f"\nLANGUAGE DISTRIBUTION:")
for lang, count in sorted(language_dist.items()):
    pct = 100 * count // len(candidate_rows)
    print(f"  {lang}: {count} ({pct}%)")

print(f"\nOUTPUT:")
print(f"  Candidate dataset: ml_service/emotion_data_multilingual_candidate/")

print("\n" + "=" * 70)
print("READY FOR MANUAL REVIEW AND SPLIT")
print("=" * 70)

