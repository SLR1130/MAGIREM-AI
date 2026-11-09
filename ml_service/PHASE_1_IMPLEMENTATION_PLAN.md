# PHASE 1 IMPLEMENTATION PLAN - Ollama Understanding Path

## OBJECTIVE
Refactor `/chat` endpoint so Ollama receives the actual user message and can determine its situation, topic, intent, emotional state, and other contextual understanding BEFORE generating responses.

## EXACT CHANGES REQUIRED

### 1. ADD `ollama_understand_message()` function to `unified_ml_api_with_model.py`
**Location:** Before `/chat` endpoint, around line 290
**Function signature:**
```python
def ollama_understand_message(message: str, memory_context: str = None) -> Dict:
    """
    Use Ollama to understand the raw message without emotion labels.
    Returns structured interpretation that will inform response generation.
    
    Returns Dict with keys:
    - situation: What is happening in this message
    - topic: What is being discussed
    - intent: What does user want/need (seeking_support, asking_advice, venting, etc)
    - emotional_meaning: What is the actual emotional content
    - important_details: Key facts from the message
    - sarcasm_or_figurative: Is user being sarcastic/hyperbolic
    - is_question: Is user asking a question
    - severity_indicator: low/medium/high based on content
    """
```

### 2. ADD `minimal_semantic_check()` function to `semantic_analysis.py`
**Location:** At end of file, before `analyze_semantic_layer()` function (around line 605)
**Purpose:** Lightweight safety check to verify understanding is reasonable
**Size:** ~20 lines

### 3. MODIFY `/chat` endpoint in `unified_ml_api_with_model.py`
**Location:** Lines 358-416 (after crisis detection, memory retrieval, context classification)
**Changes:**
- Keep crisis detection (lines 358-365) - UNCHANGED
- Keep memory retrieval (lines 367-387) - UNCHANGED  
- Keep context classification (lines 389-416) - UNCHANGED
- **ADD AFTER line 416:** Call `ollama_understand_message(message, memory_context)`
- Pass understanding result to `generate_hybrid_response()` call

### 4. MODIFY `_build_prompt()` in `hybrid_response_engine.py`
**Location:** Lines 214-440 (entire emotion distribution section)
**Changes:**
- Replace emotion-distribution section with understanding section
- Add: Situation, Topic, Intent, Important Details from Ollama
- Remove: secondary emotions, tone richness sections
- Update response instructions to reference understanding instead of emotion

---

## IMPLEMENTATION ORDER (STRICT)

### STEP 1: Create `ollama_understand_message()` function
→ Calls Ollama with prompt to analyze message structure
→ Parses response to JSON
→ Returns structured understanding

### STEP 2: Add `minimal_semantic_check()` function
→ Validates understanding JSON has required keys
→ ~20 lines, minimal logic

### STEP 3: Modify `/chat` endpoint
→ Call ollama_understand_message after line 416
→ Pass result to generate_hybrid_response()

### STEP 4: Modify `_build_prompt()` in hybrid_response_engine.py
→ Replace emotion-centric prompt building
→ Use Ollama understanding instead

---

## TEST CASE

**Input Message:** `"Yeah about that, my friend said that my gf is cheating on me."`

**Expected Understanding Output:**
```json
{
  "situation": "Friend told user their girlfriend may be cheating",
  "topic": "relationship/trust",
  "intent": "seeking_support",
  "emotional_meaning": "User is concerned/confused/anxious about relationship",
  "important_details": ["friend made the claim", "claim is unverified", "about girlfriend"],
  "sarcasm_or_figurative": false,
  "is_question": false,
  "severity_indicator": "medium"
}
```

**Expected Response:** Should address the actual situation (friend's claim about cheating, uncertainty, relationship concern), NOT say "It sounds like you're feeling anxious"

---

## VERIFICATION CHECKLIST

- [ ] ollama_understand_message() returns valid JSON
- [ ] minimal_semantic_check() validates structure
- [ ] /chat endpoint calls ollama_understand_message() and passes result
- [ ] _build_prompt() uses understanding instead of emotion distribution
- [ ] Test case produces response addressing the actual situation
- [ ] Existing crisis detection still works
- [ ] Memory system still works
- [ ] Context classification still works
- [ ] Run existing tests - no breakage

---

## BACKWARD COMPATIBILITY

Must NOT break:
- ✅ detect_crisis() function - EXACTLY as is
- ✅ Memory retrieval system - EXACTLY as is
- ✅ Context classification - EXACTLY as is
- ✅ All existing test scripts should still run
- ✅ Emotion detection still happens (just not used for main response basis)
- ✅ Safety checks still run

---

## MINIMAL CHANGES PRINCIPLE

- Do NOT delete any existing Python modules
- Do NOT delete natural_conversation.py or other modules yet
- Only ADD new understanding path
- Only MODIFY prompt building to use understanding
- Keep fallback logic for Ollama failures
- No template-based responses as normal path
