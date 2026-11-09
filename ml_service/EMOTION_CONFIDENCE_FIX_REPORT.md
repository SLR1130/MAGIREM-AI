# emotion_confidence AttributeError Fix Report

**Date:** September 28, 2026  
**Issue:** `AttributeError: 'UnifiedAnalysisResult' object has no attribute 'emotion_confidence'`  
**Status:** FIXED

---

## Root Cause Analysis

### The Bug
In `hybrid_response_engine.py` line 656:
```python
prompt += f"[UNIFIED] Confidence: {unified_result.emotion_confidence:.0%}\n"
```

The code attempted to access `emotion_confidence` as a direct attribute of `UnifiedAnalysisResult`, but this attribute does not exist.

### Why It Failed
The `UnifiedAnalysisResult` class (in `unified_analysis.py`) structures emotion as a **dictionary**, not as separate scalar attributes:

```python
self.emotion = {
    'primary': 'neutral',       # The emotion name
    'secondary': [],            # Alternative emotions
    'confidence': 0.0,          # <-- THE CORRECT FIELD
    'scores': {...},            # Individual emotion scores
    'source': 'unknown'         # Where this came from
}
```

The correct path is: `result.emotion['confidence']`, not `result.emotion_confidence`.

---

## Exact Code Changes

### Change 1: emotion_confidence attribute (Line 656)

**BEFORE (WRONG):**
```python
prompt += f"[UNIFIED] Emotion: {unified_result.emotion}\n"
prompt += f"[UNIFIED] Confidence: {unified_result.emotion_confidence:.0%}\n"
```

**AFTER (FIXED):**
```python
prompt += f"[UNIFIED] Emotion: {unified_result.emotion['primary']}\n"
prompt += f"[UNIFIED] Confidence: {unified_result.emotion['confidence']:.0%}\n"
```

**Why:** 
- `unified_result.emotion` is a dict, so we need `['primary']` to get the emotion name
- Confidence is inside the emotion dict, accessed as `['confidence']`

---

### Change 2: underlying_emotion comparison (Line 658)

**BEFORE (WRONG):**
```python
if unified_result.underlying_emotion and unified_result.underlying_emotion != unified_result.emotion:
```

**AFTER (FIXED):**
```python
if unified_result.sarcasm.get('underlying_emotion') and unified_result.sarcasm['underlying_emotion'] != unified_result.emotion['primary']:
```

**Why:**
- `underlying_emotion` is not a top-level attribute; it's inside `sarcasm` dict
- We need to compare it with `emotion['primary']` (a string), not the whole emotion dict

---

### Change 3: intent access (Line 669)

**BEFORE (WRONG):**
```python
if unified_result.intent:
    prompt += f"[UNIFIED] Intent: {unified_result.intent}\n"
```

**AFTER (FIXED):**
```python
if unified_result.intent and unified_result.intent.get('primary'):
    prompt += f"[UNIFIED] Intent: {unified_result.intent['primary']}\n"
```

**Why:**
- `intent` is a dict with structure `{'primary': '...', 'confidence': 0.0}`
- We need the `'primary'` field to get the actual intent text

---

### Change 4: figurative_likelihood (Line 672)

**BEFORE (WRONG):**
```python
if unified_result.figurative_likelihood > 0.5:
    prompt += f"[UNIFIED] Figurative: {unified_result.figurative_likelihood:.0%}\n"
```

**AFTER (FIXED):**
```python
if unified_result.figurative and unified_result.figurative.get('confidence', 0) > 0.5:
    prompt += f"[UNIFIED] Figurative: {unified_result.figurative['confidence']:.0%}\n"
```

**Why:**
- `figurative_likelihood` doesn't exist as a scalar attribute
- Figurative is a dict with structure `{'detected': bool, 'type': str, 'confidence': float}`

---

## Canonical UnifiedAnalysisResult Structure

For future reference, the correct way to access UnifiedAnalysisResult fields:

```python
result.emotion = {
    'primary': str,          # emotion name
    'secondary': list,       # alternative emotions
    'confidence': float,     # 0-1 scale
    'scores': dict,          # {'emotion': score, ...}
    'source': str            # where it came from
}

result.sarcasm = {
    'detected': bool,
    'confidence': float,
    'underlying_emotion': str or None,  # <-- Where underlying_emotion lives
    'signals': dict
}

result.intent = {
    'primary': str,          # intention type
    'confidence': float
}

result.figurative = {
    'detected': bool,
    'type': str or None,
    'confidence': float      # <-- Where figurative confidence lives
}
```

---

## Ollama Diagnostic

After the fix, the hybrid response engine should:

1. ✓ Build prompt without AttributeError
2. ✓ Access all UnifiedAnalysisResult fields correctly
3. ✓ Attempt Ollama request (no exception before reaching Ollama)
4. ✓ Either get response from Ollama OR fall back gracefully if Ollama is actually unavailable

---

## Files Modified

- `ml_service/hybrid_response_engine.py` (4 attribute access fixes)

## Files Verified (No Changes Needed)

- `ml_service/unified_analysis.py` (correct structure)
- `ml_service/unified_ml_api_with_model.py` (correct structure)

---

## Verification

**Syntax Check:**
```
✓ ml_service/hybrid_response_engine.py - PASS
✓ ml_service/unified_ml_api_with_model.py - PASS
✓ ml_service/unified_analysis.py - PASS
```

All files compile without syntax errors.

---

## Expected Behavior After Fix

### Test 1: "Hello bro"
- ✓ No AttributeError
- ✓ Prompt builds successfully
- ✓ Ollama called (or falls back if unavailable)
- ✓ Response returned or fallback triggered

### Test 2: "I want to die"
- ✓ Crisis detected
- ✓ Safety protocol takes priority
- ✓ Response is safety-focused (not casual)

### Test 3: "Yeah bro, I'm doing absolutely amazing. My deadlines are destroying my soul 😂"
- ✓ Sarcasm detected
- ✓ Underlying emotion inferred (anxiety)
- ✓ Response addresses actual stress, not surface positivity

---

## Important Note

This fix addresses only the **attribute access bug**. It does NOT indicate that:
- Ollama is unavailable (that's a separate issue to diagnose)
- The fallback should be used instead of the hybrid path
- The system is broken

The AttributeError was masking whether Ollama requests were even being attempted. With this fix, we can properly determine whether Ollama is actually unreachable or whether other issues exist.

