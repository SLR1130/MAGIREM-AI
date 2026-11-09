# PHASE 1 TESTING GUIDE

## QUICK START

Phase 1 implementation is complete and ready to test with the ML server running.

### Prerequisites
- Ollama running on localhost:11434
- Flask ML server running  
- Database configured
- Postman or curl ready

---

## TEST CASE 1: Cheating Girlfriend (Primary Test)

This is the main test case to verify Phase 1 is working correctly.

### Input
```json
{
  "message": "Yeah about that, my friend said that my gf is cheating on me.",
  "user_id": "test_user",
  "conversation_history": []
}
```

### Expected Ollama Understanding
```json
{
  "situation": "Friend told user their girlfriend may be cheating",
  "topic": "relationship",
  "intent": "seeking_support",
  "emotional_meaning": "concerned/anxious about relationship",
  "important_details": ["friend's claim", "unverified", "about girlfriend"],
  "sarcasm_or_figurative": false,
  "is_question": false,
  "severity_indicator": "medium"
}
```

### Expected Response Characteristics
✓ Should address the relationship/trust concern
✓ Should mention uncertainty/unverified claim
✓ Should acknowledge the friend told them
✓ Should NOT say "It sounds like you're feeling anxious"
✓ Should NOT ignore the situation and just comment on emotions

### How to Send (curl)
```bash
curl -X POST http://localhost:5000/chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Yeah about that, my friend said that my gf is cheating on me.",
    "user_id": "test_user",
    "conversation_history": []
  }'
```

### How to Send (Postman)
1. POST to: `http://localhost:5000/chat`
2. Body (JSON):
```json
{
  "message": "Yeah about that, my friend said that my gf is cheating on me.",
  "user_id": "test_user",
  "conversation_history": []
}
```

---

## TEST CASE 2: Sarcasm With Stress (Verify Sarcasm Detection)

### Input
```json
{
  "message": "Yeah sure, wonderful, my three projects are due tomorrow and I haven't started them yet",
  "user_id": "test_user",
  "conversation_history": []
}
```

### Expected Ollama Understanding
```json
{
  "situation": "User has multiple deadlines approaching with no progress made",
  "topic": "academics/work",
  "intent": "venting",
  "emotional_meaning": "stressed/overwhelmed/anxious about workload",
  "important_details": ["three projects", "due tomorrow", "no progress"],
  "sarcasm_or_figurative": true,
  "is_question": false,
  "severity_indicator": "medium"
}
```

### Expected Response
✓ Should acknowledge the stress and overwhelm
✓ Should reference the projects and deadline
✓ Should NOT treat "wonderful" as genuine positive emotion
✓ Should address the actual problem (workload stress)

---

## TEST CASE 3: Question Format (Verify Question Detection)

### Input
```json
{
  "message": "What should I do if my roommate keeps taking my food from the fridge?",
  "user_id": "test_user",
  "conversation_history": []
}
```

### Expected Ollama Understanding
```json
{
  "situation": "Roommate is taking user's food without permission",
  "topic": "relationships/conflict",
  "intent": "asking_advice",
  "emotional_meaning": "frustrated/annoyed about situation",
  "important_details": ["roommate", "taking food", "from fridge", "without permission"],
  "sarcasm_or_figurative": false,
  "is_question": true,
  "severity_indicator": "low"
}
```

### Expected Response
✓ Should recognize this is a question seeking advice
✓ Should provide suggestions for addressing the roommate
✓ Should NOT just validate emotions

---

## VERIFICATION IN SERVER LOGS

### When Ollama Understanding is Called
Look for these log messages in the ML server output:

```
[OLLAMA_UNDERSTANDING] Topic: relationship, Intent: seeking_support, Severity: medium
[OLLAMA_UNDERSTAND] Successfully understood message
[FINAL_PROMPT_DEBUG] About to send to Ollama:
✓ STRUCTURED UNDERSTANDING (from Ollama analysis):
  Situation: Friend told user their girlfriend may be cheating
  Topic: relationship
  User intent: seeking_support
  Emotional meaning: concerned/anxious about relationship
```

### When Ollama Understanding Fails (Fallback)
If Ollama is down, you should see:

```
[OLLAMA_UNDERSTANDING_ERROR] Failed to get understanding: ...
[FINAL_PROMPT_DEBUG] About to send to Ollama:
⚠️ Ollama understanding not available, using emotion context:
```

---

## RESPONSE JSON STRUCTURE

The `/chat` endpoint should return:

```json
{
  "success": true,
  "emotion": "sadness",
  "response": "That's a really tough situation. A friend's claim about cheating is serious, but you mentioned it's unverified. Have you had a chance to talk to your girlfriend directly about what your friend told you?",
  "confidence": 0.85,
  "analysis": {
    "emotion": "sadness",
    "confidence": 0.85,
    "crisis_level": "none",
    "method": "trained_model",
    "severity": 3
  },
  "context_analysis": {
    "interaction_type": "RELATIONSHIP_CONCERN",
    "context_confidence": 0.9,
    "context_reasoning": "User is expressing concern about a relationship issue based on secondhand information",
    "user_correction": false
  },
  "counselor_suggestion": {
    "should_suggest": false,
    "reason": "",
    "message": null,
    "connection_prompt": null
  },
  "semantic_analysis": {
    "emotion": {
      "dominant": "sadness",
      "confidence": 0.75,
      "intensity": 0.45,
      "scores": {
        "happiness": 0.1,
        "sadness": 0.75,
        "anxiety": 0.1,
        "anger": 0.05,
        "neutral": 0.0
      }
    },
    "tone": {
      "humor_score": 0.0,
      "sarcasm_score": 0.0,
      "satire_score": 0.0,
      "hyperbole_score": 0.0
    }
  }
}
```

---

## DEBUGGING TIPS

### 1. Check if Ollama is Running
```bash
curl http://localhost:11434/api/tags
```
Should return model list. If fails, start Ollama:
```bash
ollama serve
```

### 2. Check if ML Server is Running
```bash
curl http://localhost:5000/health
```
Should return: `{"status": "ok"}`

### 3. Enable Debug Logging
In the ML server, search for `[OLLAMA_UNDERSTANDING]` and `[FINAL_PROMPT_DEBUG]` messages in console output.

### 4. Test ollama_understand_message() Directly
Create a test file:
```python
from unified_ml_api_with_model import ollama_understand_message
import json

message = "Yeah about that, my friend said that my gf is cheating on me."
result = ollama_understand_message(message)
print(json.dumps(result, indent=2))
```

### 5. Check Fallback Works
Stop Ollama, then send a message. Should see:
- `[OLLAMA_UNDERSTANDING_ERROR]` in logs
- Response still generated (using fallback)
- Response might be less specific but still valid

---

## WHAT TO CHECK IN RESPONSE

### ✓ CORRECT - Response addresses actual situation
```
"That's really concerning. A friend's claim about cheating is serious, 
but since it's unverified, the best step might be to talk directly 
with your girlfriend about what you heard."
```

### ✗ WRONG - Generic emotion response
```
"It sounds like you're feeling anxious. That's a common emotion when 
dealing with relationship concerns."
```

### ✓ CORRECT - References specific details
```
"Three projects due tomorrow with no progress? That's definitely stressful. 
Let's talk about what you can prioritize tonight."
```

### ✗ WRONG - Misses the actual problem
```
"It sounds like you're having some feelings. Would you like to talk about that?"
```

---

## SUCCESS CRITERIA

Phase 1 is working correctly if:

1. ✓ Server logs show `[OLLAMA_UNDERSTANDING]` messages
2. ✓ Response addresses actual situation, not just emotion
3. ✓ Cheating girlfriend test shows relationship/trust concern
4. ✓ Sarcasm test recognizes stress despite "wonderful" word
5. ✓ Question test recognizes it's seeking advice
6. ✓ When Ollama is down, fallback provides valid JSON
7. ✓ All crisis detection still works
8. ✓ Memory system still works
9. ✓ Existing tests pass

---

## NEXT STEPS AFTER TESTING

Once Phase 1 is verified working:

1. **Document real responses** - Save actual responses from system to show how it improved
2. **Phase 2 Planning** - Reduce semantic analysis usage
3. **Phase 3 Planning** - Eliminate template fallbacks
4. **Performance tuning** - Optimize Ollama understanding calls if needed

---

## COMMON ISSUES & FIXES

### Issue: Timeout connecting to Ollama
**Fix:** Ensure Ollama is running on localhost:11434
```bash
ollama serve
```

### Issue: Empty response
**Fix:** Check if Ollama model is loaded
```bash
ollama list
ollama pull neural-chat
```

### Issue: Generic response instead of situation-specific
**Fix:** Verify `[OLLAMA_UNDERSTANDING]` appears in logs. If not, understanding extraction failed.

### Issue: Crisis detection not working
**Fix:** Crisis detection runs BEFORE understanding, so it's independent. Verify `detect_crisis()` still works.

---

## FILES USED IN PHASE 1

- `ml_service/unified_ml_api_with_model.py` - Main endpoint with ollama_understand_message()
- `ml_service/hybrid_response_engine.py` - Response generation with understanding
- `ml_service/semantic_analysis.py` - Validation function
- `ml_service/PHASE_1_IMPLEMENTATION_COMPLETE.md` - Implementation details

---

Ready to test Phase 1! 🚀
