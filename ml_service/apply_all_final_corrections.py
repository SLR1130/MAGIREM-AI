#!/usr/bin/env python3
"""
FINAL DATASET CORRECTION PASS
==============================
Apply all identified corrections from TASK 6 audit:
- 4 baseline critical emotion errors
- 1 near-duplicate removal
- 3 language misclassifications (add Tagalog tag)
- 4 expansion grief/sadness boundary corrections
- Rebuild clean frozen dataset: emotion_data_multilingual_final/

STATUS: READY FOR EXECUTION
"""

import json
import os
from pathlib import Path
from collections import defaultdict, Counter
import unicodedata

def normalize_text(text):
    """Normalize text for duplicate detection."""
    # Unicode normalization
    text = unicodedata.normalize('NFKD', text)
    # Case folding
    text = text.casefold()
    # Trim whitespace
    text = text.strip()
    # Collapse multiple spaces
    text = ' '.join(text.split())
    # Normalize punctuation
    text = text.replace(' ?', '?').replace(' !', '!').replace(' ,', ',').replace(' .', '.')
    return text

def load_jsonl(filepath):
    """Load JSONL file."""
    rows = []
    with open(filepath, 'r', encoding='utf-8') as f:
        for line in f:
            rows.append(json.loads(line.strip()))
    return rows

def save_jsonl(rows, filepath, create_dir=True):
    """Save JSONL file."""
    if create_dir:
        filepath.parent.mkdir(parents=True, exist_ok=True)
    with open(filepath, 'w', encoding='utf-8') as f:
        for row in rows:
            f.write(json.dumps(row) + '\n')

def correct_baseline_train():
    """Correct train.jsonl - 6 corrections."""
    print("\n[BASELINE TRAIN CORRECTIONS]")
    rows = load_jsonl(Path('ml_service/emotion_data_clean_final/train.jsonl'))
    print(f"  DEBUG: Loaded {len(rows)} rows, first row type: {type(rows[0])}")
    
    corrections = {
        "years ago i wanted to die, but i'm okay now.": ('neutral', 'sadness', 'Suicidal ideation reference'),
        "ok my cat die yesterday": ('neutral', 'sadness', 'Pet death reference'),
        "i'm not struggling": ('joy', 'neutral', 'Negation ≠ joy'),
        "i'm not worried": ('joy', 'neutral', 'Negation ≠ joy'),
        "i'm not upset": ('joy', 'neutral', 'Negation ≠ joy'),
    }
    
    # Tagalog additions (keep emotion, add language)
    tagalog_additions = {
        "baklla si lei": 'Tagalog',
        "may tililing kb": 'Tagalog',
    }
    
    corrected = []
    emotion_count = 0
    lang_count = 0
    
    for row in rows:
        text = row['text']
        
        # Check for emotion corrections
        if text in corrections:
            old_emotion, new_emotion, reason = corrections[text]
            if row['emotion'] == old_emotion:
                row['emotion'] = new_emotion
                print(f"  ✓ Corrected: '{text[:50]}...' → {old_emotion} to {new_emotion}")
                emotion_count += 1
        
        # Check for language additions
        if text in tagalog_additions:
            if 'language' not in row:
                row['language'] = tagalog_additions[text]
                print(f"  ✓ Added language: '{text[:50]}...' → {row['language']}")
                lang_count += 1
        
        corrected.append(row)
    
    save_jsonl(corrected, Path('ml_service/emotion_data_clean_final/train.jsonl'))
    return emotion_count, lang_count

def correct_baseline_validation():
    """Correct validation.jsonl - 3 corrections + 1 deletion."""
    print("\n[BASELINE VALIDATION CORRECTIONS]")
    rows = load_jsonl(Path('ml_service/emotion_data_clean_final/validation.jsonl'))
    
    corrections = {
        "i'm not sad": ('joy', 'neutral', 'Negation ≠ joy'),
        "this doesn't make me angry": ('joy', 'neutral', 'Negation ≠ joy'),
    }
    
    # Tagalog additions
    tagalog_additions = {
        "bakla san galing yan": 'Tagalog',
    }
    
    # Duplicates to remove (keep first occurrence normalized)
    duplicate_to_remove = normalize_text("okay, medyo mahirap ang sitwasyon ko sa pera, ano ba ang pwedi kong gawin? any tips")
    
    corrected = []
    removed = 0
    
    for row in rows:
        text = row['text']
        normalized = normalize_text(text)
        
        # Check for duplicate removal
        if normalized == duplicate_to_remove:
            print(f"  ✓ Removed duplicate: '{text[:50]}...' (punctuation variant)")
            removed += 1
            continue
        
        # Check for emotion corrections
        if text in corrections:
            old_emotion, new_emotion, reason = corrections[text]
            if row['emotion'] == old_emotion:
                row['emotion'] = new_emotion
                print(f"  ✓ Corrected: '{text[:50]}...' → {old_emotion} to {new_emotion}")
        
        # Check for language additions
        if text in tagalog_additions:
            if 'language' not in row:
                row['language'] = tagalog_additions[text]
                print(f"  ✓ Added language: '{text[:50]}...' → {row['language']}")
        
        corrected.append(row)
    
    save_jsonl(corrected, Path('ml_service/emotion_data_clean_final/validation.jsonl'))
    return len(corrections), len(tagalog_additions), removed

def correct_baseline_test():
    """Correct test.jsonl - 1 correction."""
    print("\n[BASELINE TEST CORRECTIONS]")
    rows = load_jsonl(Path('ml_service/emotion_data_clean_final/test.jsonl'))
    
    corrections = {
        "this doesn't make me angry": ('joy', 'neutral', 'Negation ≠ joy'),
    }
    
    corrected = []
    for row in rows:
        text = row['text']
        if text in corrections:
            old_emotion, new_emotion, reason = corrections[text]
            if row['emotion'] == old_emotion:
                row['emotion'] = new_emotion
                print(f"  ✓ Corrected: '{text[:50]}...' → {old_emotion} to {new_emotion}")
        corrected.append(row)
    
    save_jsonl(corrected, Path('ml_service/emotion_data_clean_final/test.jsonl'))
    return len(corrections)

def correct_expansion_tagalog():
    """Correct tagalog.jsonl - 1 correction."""
    print("\n[EXPANSION TAGALOG CORRECTIONS]")
    rows = load_jsonl(Path('ml_service/emotion_data_fil_expansion/tagalog.jsonl'))
    
    corrections = {
        "ang childhood place namin ay demolished na at parang part ng identity ay nawala": ('grief', 'sadness', 'Place loss ≠ bereavement'),
    }
    
    corrected = []
    for row in rows:
        text = row['text']
        if text in corrections:
            old_emotion, new_emotion, reason = corrections[text]
            if row['emotion'] == old_emotion:
                row['emotion'] = new_emotion
                print(f"  ✓ Corrected: '{text[:50]}...' → {old_emotion} to {new_emotion}")
        corrected.append(row)
    
    save_jsonl(corrected, Path('ml_service/emotion_data_fil_expansion/tagalog.jsonl'))
    return len(corrections)

def correct_expansion_taglish():
    """Correct taglish.jsonl - 2 corrections."""
    print("\n[EXPANSION TAGLISH CORRECTIONS]")
    rows = load_jsonl(Path('ml_service/emotion_data_fil_expansion/taglish.jsonl'))
    
    corrections = {
        "napakawala ko ng mom last year at sometimes i still expect to see her sa kitchen.": ('sadness', 'grief', 'Death of parent = bereavement'),
        "ang lolo ko ay nag-deteriorate mentally at hindi siya nag-recognize ng name ko last visit.": ('sadness', 'grief', 'Anticipatory grief - deterioration toward death'),
    }
    
    corrected = []
    for row in rows:
        text = row['text']
        if text in corrections:
            old_emotion, new_emotion, reason = corrections[text]
            if row['emotion'] == old_emotion:
                row['emotion'] = new_emotion
                print(f"  ✓ Corrected: '{text[:50]}...' → {old_emotion} to {new_emotion}")
        corrected.append(row)
    
    save_jsonl(corrected, Path('ml_service/emotion_data_fil_expansion/taglish.jsonl'))
    return len(corrections)

def rebuild_final_dataset():
    """Rebuild final frozen dataset with 70/15/15 split."""
    print("\n[REBUILDING FINAL FROZEN DATASET]")
    
    # Load corrected data
    train = load_jsonl(Path('ml_service/emotion_data_clean_final/train.jsonl'))
    val = load_jsonl(Path('ml_service/emotion_data_clean_final/validation.jsonl'))
    test = load_jsonl(Path('ml_service/emotion_data_clean_final/test.jsonl'))
    
    tagalog_exp = load_jsonl(Path('ml_service/emotion_data_fil_expansion/tagalog.jsonl'))
    taglish_exp = load_jsonl(Path('ml_service/emotion_data_fil_expansion/taglish.jsonl'))
    
    # Combine all data
    all_data = train + val + test + tagalog_exp + taglish_exp
    
    # Deduplicate by normalized text
    seen = {}
    unique_data = []
    duplicates = 0
    
    for row in all_data:
        normalized = normalize_text(row['text'])
        if normalized not in seen:
            seen[normalized] = row
            unique_data.append(row)
        else:
            # Check for conflicting labels
            if seen[normalized]['emotion'] != row['emotion']:
                print(f"  ⚠ CONFLICT: '{row['text'][:50]}...' has labels: {seen[normalized]['emotion']} vs {row['emotion']}")
            duplicates += 1
    
    print(f"  Total before dedup: {len(all_data)}")
    print(f"  Total after dedup: {len(unique_data)}")
    print(f"  Exact duplicates removed: {duplicates}")
    
    # Analyze distribution
    emotion_dist = Counter(row['emotion'] for row in unique_data)
    language_dist = Counter(row.get('language', 'English') for row in unique_data)
    
    print(f"\n  Emotion Distribution:")
    for emotion, count in sorted(emotion_dist.items()):
        print(f"    {emotion}: {count}")
    
    print(f"\n  Language Distribution:")
    for lang, count in sorted(language_dist.items()):
        print(f"    {lang}: {count}")
    
    # Group by emotion and language for stratified splitting
    grouped = defaultdict(list)
    for row in unique_data:
        emotion = row['emotion']
        language = row.get('language', 'English')
        grouped[(emotion, language)].append(row)
    
    # Split 70/15/15
    import random
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
    
    # Shuffle final splits
    random.shuffle(train_final)
    random.shuffle(val_final)
    random.shuffle(test_final)
    
    # Verify no cross-split leakage
    train_normalized = {normalize_text(r['text']): r['emotion'] for r in train_final}
    val_normalized = {normalize_text(r['text']): r['emotion'] for r in val_final}
    test_normalized = {normalize_text(r['text']): r['emotion'] for r in test_final}
    
    train_val_overlap = len(set(train_normalized.keys()) & set(val_normalized.keys()))
    train_test_overlap = len(set(train_normalized.keys()) & set(test_normalized.keys()))
    val_test_overlap = len(set(val_normalized.keys()) & set(test_normalized.keys()))
    
    print(f"\n  Cross-split overlap verification:")
    print(f"    train ∩ validation: {train_val_overlap}")
    print(f"    train ∩ test: {train_test_overlap}")
    print(f"    validation ∩ test: {val_test_overlap}")
    
    # Save final dataset
    output_dir = Path('ml_service/emotion_data_multilingual_final')
    save_jsonl(train_final, output_dir / 'train.jsonl')
    save_jsonl(val_final, output_dir / 'validation.jsonl')
    save_jsonl(test_final, output_dir / 'test.jsonl')
    
    print(f"\n  Final counts:")
    print(f"    Train: {len(train_final)}")
    print(f"    Validation: {len(val_final)}")
    print(f"    Test: {len(test_final)}")
    print(f"    TOTAL: {len(train_final) + len(val_final) + len(test_final)}")
    
    # Create label mapping
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
    
    return {
        'total': len(unique_data),
        'train': len(train_final),
        'validation': len(val_final),
        'test': len(test_final),
        'emotion_dist': dict(emotion_dist),
        'language_dist': dict(language_dist),
        'duplicates': duplicates,
        'cross_split_leakage': max(train_val_overlap, train_test_overlap, val_test_overlap),
    }

def main():
    print("=" * 70)
    print("FINAL DATASET CORRECTION PASS")
    print("=" * 70)
    
    # Apply baseline corrections
    train_corr, train_lang = correct_baseline_train()
    val_corr, val_lang, val_removed = correct_baseline_validation()
    test_corr = correct_baseline_test()
    
    # Apply expansion corrections
    tag_corr = correct_expansion_tagalog()
    tagl_corr = correct_expansion_taglish()
    
    # Rebuild final dataset
    stats = rebuild_final_dataset()
    
    # Print summary
    print("\n" + "=" * 70)
    print("CORRECTION SUMMARY")
    print("=" * 70)
    print(f"\nBaseline Corrections:")
    print(f"  Train emotions: {train_corr}, languages: {train_lang}")
    print(f"  Validation emotions: {val_corr}, languages: {val_lang}, removed: {val_removed}")
    print(f"  Test emotions: {test_corr}")
    
    print(f"\nExpansion Corrections:")
    print(f"  Tagalog: {tag_corr}")
    print(f"  Taglish: {tagl_corr}")
    
    print(f"\nFinal Dataset Statistics:")
    print(f"  Total unique examples: {stats['total']}")
    print(f"  Exact duplicates removed: {stats['duplicates']}")
    print(f"  Cross-split leakage: {stats['cross_split_leakage']}")
    
    print(f"\nFinal Split Distribution:")
    print(f"  Train: {stats['train']} ({100*stats['train']//stats['total']}%)")
    print(f"  Validation: {stats['validation']} ({100*stats['validation']//stats['total']}%)")
    print(f"  Test: {stats['test']} ({100*stats['test']//stats['total']}%)")
    
    print(f"\nFinal Emotion Distribution:")
    for emotion, count in sorted(stats['emotion_dist'].items()):
        pct = 100 * count // stats['total']
        print(f"  {emotion}: {count} ({pct}%)")
    
    print(f"\nFinal Language Distribution:")
    for lang, count in sorted(stats['language_dist'].items()):
        pct = 100 * count // stats['total']
        print(f"  {lang}: {count} ({pct}%)")
    
    print(f"\n✓ Final dataset saved to: ml_service/emotion_data_multilingual_final/")
    print(f"✓ Status: READY FOR MULTILINGUAL EMOTION MODEL TRAINING")
    print("=" * 70)

if __name__ == '__main__':
    main()
