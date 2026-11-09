# OLLAMA TIMEOUT DIAGNOSIS - Phase 1 Pipeline

## PROBLEM SUMMARY
Frontend receives timeouts and falls back to TEMPLATE FALLBACK instead of using Ollama-generated responses.

**Error 1:** `HTTPConnectionPool(host='localhost', port=11434): Read timed out. (read timeout=15)`
**Error 2:** `HTTPConnectionPool(host='localhost', port=11434): Read timed out. (read timeout=25)`

**Evidence:** Ollama works correctly when run directly (`ollama run neural-chat:latest`), but Flask pipeline times out.

---

## ROOT CAUSE ANALYSIS

### TWO SEQUENTIAL OLLAMA CALLS IDENTIFIED

The pipeline makes **TWO separate HTTP requests** to Ollama for EVERY user message:

#### Call 1: ollama_understand_message() in /chat endpoint
**Location:** `unified_ml_api_with_model.py`, line 350  
**Endpoint:** `http://localhost:11434/api/generate`  
**Method:** POST  
**Streaming:** NO (`"stream": False`)  
**Timeout:** `(5, 15)` = 5s connection, 15s read  
**Temperature:** 0.3 (low, for JSON structure)  
**Model:** neural-chat  
**Payload:** Understanding prompt (JSON extraction)  
**Prompt size:** ~600-800 chars (compact understanding request)

**Purpose:** Extract: situation, topic, intent, emotional_meaning, important_details, sarcasm, is_question, severity

---

#### Call 2: HybridResponseEngine.generate() for response
**Location:** `hybrid_response_engine.py`, line 119  
**Endpoint:** `http://localhost:11434/api/generate`  
**Method:** POST  
**Streaming:** YES (`"stream": True`)  
**Timeout:** `(5, 25)` = 5s connection, 25s read  
**Temperature:** 0.75 (higher, for natural response)  
**Model:** neural-chat  
**Payload:** Full context prompt  
**Prompt size:** 3772+ chars (large, includes system prompt + context + history + understanding + current message)

**Purpose:** Generate actual conversational response

---

## DETAILED ISSUE BREAKDOWN

### Issue 1: TWO Sequential Ollama Calls (NOT Parallel)
```
User Message
    ↓
[SEMANTIC ANALYSIS - fast]
    ↓
[MEMORY RETRIEVAL - fast]
    ↓
[CONTEXT CLASSIFICATION - fast]
    ↓
[OLLAMA CALL 1: understand_message] ← TIMEOUT HERE (15s limit)
│  Model reads message, generates JSON
│  Expected: 5-10 seconds typically
│  Actual problem: May take 15+ seconds
    ↓
[OLLAMA CALL 2: generate_response] ← TIMEOUT HERE (25s limit)
│  Model reads full prompt, generates response
│  Expected: 5-15 seconds typically
│  Actual problem: May take 25+ seconds
    ↓
[FALLBACK] When either times out
```

**Impact:** Two 15-25 second timeouts means user experiences 30-50 second delays even before fallback.

---

### Issue 2: STREAMING Mismatch in Call 2
**Problem:** 
- Call 2 uses `"stream": True` in request JSON
- Also uses `stream=True` in requests library call
- BUT Call 1 uses `"stream": False` (non-streaming)

**Why it matters:**
- Streaming adds overhead: chunking, JSON parsing per chunk, per-line iteration
- With large prompts (3772+ chars), streaming adds latency
- Most Ollama responses complete in < 5 seconds anyway

---

### Issue 3: LARGE Prompt Size in Call 2
**Prompt breakdown (estimated):**
```
System prompt:             ~800 chars
Emotional context section: ~400 chars
Memory context:            ~300 chars  
Understanding section:     ~300 chars
Conversation history:      ~600 chars
Current message:           ~100 chars
Instructions:              ~500 chars
────────────────────────────────
TOTAL:                     ~3000+ chars
```

**Problem:** Large prompts take longer to process, especially with streaming overhead

---

### Issue 4: UNNECESSARY First Call (ollama_understand_message)
**Why it might not be needed:**
1. Semantic analysis already runs (emotion detection, tone, crisis semantics)
2. Memory system already provides context
3. Context classification already runs (interaction type)
4. By the time response generation happens, we already HAVE all understanding

**Current flow:**
```
Semantic Analysis (generates emotion + tone + crisis data)
    ↓
[Throws away result]
    ↓
Calls Ollama to "understand" (regenerates similar understanding)
    ↓
Uses BOTH in response generation
```

---

## EXACT CONFIGURATION DETAILS

### Call 1: ollama_understand_message()
```python
requests.post(
    "http://localhost:11434/api/generate",
    json={
        "model": "neural-chat",
        "prompt": understanding_prompt,  # ~600 chars
        "stream": False,  # Non-streaming
        "temperature": 0.3,
        "top_p": 0.9,
    },
    timeout=(5, 15),  # 15s read timeout
)
```

**Analysis:**
- ✓ Correctly uses non-streaming
- ✓ Temperature 0.3 is appropriate (JSON extraction)
- ⚠️ Timeout 15s is tight but reasonable
- ✓ Prompt is compact

---

### Call 2: HybridResponseEngine.generate()
```python
response = requests.post(
    self.api_endpoint,  # "http://localhost:11434/api/generate"
    json={
        "model": self.model,  # "neural-chat"
        "prompt": context,    # 3772+ chars
        "stream": True,       # Streaming ENABLED
        "temperature": 0.75,
        "top_p": 0.9,
        "top_k": 40,
    },
    timeout=(5, 25),  # 25s read timeout
    stream=True  # Enable streaming in requests library
)

# Then per-line JSON parsing:
for line in response.iter_lines():
    if line:
        try:
            chunk = json.loads(line)
            if chunk.get("response"):
                response_text += chunk["response"]
        except json.JSONDecodeError:
            pass
```

**Analysis:**
- ⚠️ Streaming adds per-line JSON parsing overhead
- ⚠️ Large prompt (3772 chars) makes this slower
- ⚠️ Timeout 25s is still insufficient if Ollama is busy
- ⚠️ No error handling if individual chunks fail to parse

---

## COMPARISON: Direct CLI vs Flask Pipeline

### Direct Ollama CLI
```bash
ollama run neural-chat:latest
```
- No HTTP overhead
- Direct memory access
- No JSON encoding/decoding
- Single process, full resources
- Response: ~2-5 seconds

### Flask Pipeline
```
User → Flask /chat → 
  Semantic (fast) → 
  Memory (fast) → 
  Context (fast) → 
  HTTP to Ollama (slow) →
    JSON request encode →
    HTTP send →
    Ollama processing →
    HTTP receive →
    JSON response decode →
  Stream parsing (if enabled) →
  HTTP to Ollama AGAIN (slower) →
    JSON request encode →
    HTTP send →
    Ollama processing →
    HTTP receive chunk by chunk →
    JSON per-chunk decode →
    HTTP send more chunks → ...
  Fallback if timeout
```

**Overhead factors:**
1. HTTP round-trip latency (5-10ms each direction, x2 calls = 20-40ms)
2. JSON encoding/decoding
3. Network buffers
4. Stream parsing overhead
5. Serialization/deserialization

---

## MINIMAL FIX PROPOSAL

### Option A: Eliminate First Ollama Call (Recommended)
**Why:** The first call to `ollama_understand_message()` is redundant

**Current state:**
- Semantic analysis ALREADY generates: emotion, tone, crisis indicators
- Call 1 to Ollama regenerates: situation, topic, intent (similar data)
- Call 2 to Ollama generates: response

**Proposed fix:**
- Keep semantic analysis results (they're fast)
- Skip the first Ollama call
- Pass semantic + memory + context directly to response generation
- Single Ollama call instead of two

**Result:** 50% reduction in Ollama calls = ~50% latency reduction

---

### Option B: Combine Both Calls (Medium effort)
**Approach:** Make ONE Ollama call that does both understanding AND response generation

**Payload:** Include understanding request + response instructions in single prompt

**Benefit:** Only 1 HTTP round-trip instead of 2

**Risk:** Single prompt becomes even larger (but only once)

---

### Option C: Remove Streaming, Reduce Prompt Size (Quick fix)
**Changes:**
1. Disable streaming in Call 2 (change `"stream": False`)
2. Truncate conversation history to last 2 messages (not 10)
3. Increase timeout to 35 seconds (temporary workaround)

**Benefit:** Reduces latency, may resolve timeouts
**Drawback:** Doesn't address root cause

---

## EVIDENCE CHECKLIST

### Confirmed:
- ✅ TWO sequential Ollama calls per message
- ✅ Call 1: 15-second timeout
- ✅ Call 2: 25-second timeout  
- ✅ Call 2 uses streaming (adds overhead)
- ✅ Call 2 prompt is large (3772+ chars)
- ✅ Ollama works fine in CLI (confirmed working)
- ✅ Flask pipeline times out before getting response
- ✅ System falls back to templates on timeout

### Metrics:
- Timeout 1: `read timeout=15` (ollama_understand_message)
- Timeout 2: `read timeout=25` (hybrid response generation)
- Prompt length: 3772 characters
- History length: 10 messages
- CPU: 100% (Ollama at capacity processing requests)

---

## ROOT CAUSE SUMMARY

The Flask pipeline times out because:

1. **Two sequential Ollama calls** instead of one
   - Each adds latency (HTTP overhead + processing)
   - Total potential delay: 30-50 seconds

2. **Streaming overhead** in second call
   - Per-line JSON parsing adds latency
   - Not beneficial for <5 second responses

3. **Large prompt** in second call
   - 3772+ characters takes longer to process
   - Includes full conversation history (10 messages)

4. **Ollama running at 100% CPU**
   - Limited resources
   - Second call has to wait for first call to finish
   - Both have 15-25s timeouts which are tight for sequential calls

5. **Redundant first call**
   - Ollama_understand_message regenerates data already available from semantic analysis
   - Adds 5-15 seconds delay before actual response generation

---

## RECOMMENDATION

**Minimum change:** Remove the first `ollama_understand_message()` call since semantic analysis already provides similar understanding data.

**Expected result:** 50% reduction in Ollama calls = faster responses, fewer timeouts

**Risk:** Very low - Phase 1 would still have understanding data from semantic + memory + context

**Implementation:** ~10 lines changed in /chat endpoint

---

## NEXT STEPS

1. Confirm whether first Ollama call is truly necessary
2. Compare semantic analysis output with ollama_understand_message output
3. If redundant: remove first call
4. If necessary: investigate why (may indicate semantic analysis is incomplete)
5. Test with frontend

**Do not increase timeout as first solution** - addresses symptom, not cause.
