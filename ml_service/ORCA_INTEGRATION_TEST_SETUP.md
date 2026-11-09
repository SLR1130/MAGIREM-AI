# ORCA-MINI:3B Integration Test - Setup Report

**Date**: September 27, 2026  
**Objective**: Controlled temporary ORCA model integration test  
**Status**: SETUP COMPLETE - Ready for integration testing

---

## STEP 1: CURRENT MODEL CONFIGURATION INSPECTION

### Configuration Locations Identified

#### Location 1: unified_ml_api_with_model.py
- **File**: `ml_service/unified_ml_api_with_model.py`
- **Function**: `ollama_understand_message()` (line ~351)
- **Model specification**: Line 352
```python
"model": "neural-chat",
```
- **HTTP Endpoint**: `http://localhost:11434/api/generate`
- **Purpose**: First Ollama call - structural understanding of message
- **Parameters**: temperature=0.3, top_p=0.9, stream=False
- **Timeout**: (5, 15) - 5s connection, 15s read

#### Location 2: hybrid_response_engine.py
- **File**: `ml_service/hybrid_response_engine.py`
- **Class**: `HybridResponseEngine` (line ~22)
- **Constructor parameter**: `model="neural-chat"` (line 22)
- **Model usage**: Line 121
```python
"model": self.model,
```
- **Initialization function**: `init_hybrid_engine()` (line ~591)
- **Default parameter**: `model="neural-chat"`
- **HTTP Endpoint**: Uses self.ollama_url and self.model
- **Purpose**: Second Ollama call - response generation
- **Parameters**: temperature varies, stream=False

#### Location 3: proactive_greeting_generator.py
- **File**: `ml_service/proactive_greeting_generator.py`
- **Class**: `ProactiveGreetingGenerator` (line ~17)
- **Constructor parameter**: `model: str = "neural-chat"` (line 17)
- **Usage**: Line 70 - `"model": self.model,`
- **Initialization**: `init_proactive_greeting_generator()` (line ~249)
- **Purpose**: Greeting generation when user starts new session

---

## CURRENT ACTIVE MODEL

**Model**: `neural-chat:latest` (4.1 GB)  
**Locations**: 3 places (2 Python files)  
**Configuration Type**: Hardcoded defaults in function signatures and API calls

---

## HOW APPLICATION CALLS OLLAMA

### Call Path 1: Message Understanding
```
User sends message via /chat endpoint
    ↓
unified_ml_api_with_model.py::chat() function
    ↓
ollama_understand_message(message)
    ↓
HTTP POST to http://localhost:11434/api/generate
    ├─ model: "neural-chat" (HARDCODED)
    ├─ prompt: structured JSON request
    ├─ temperature: 0.3
    └─ timeout: 15 seconds
    ↓
Returns: Structured JSON analysis
```

### Call Path 2: Response Generation
```
Analyzed message + emotion
    ↓
hybrid_response_engine.py::generate_response()
    ↓
HTTP POST to http://localhost:11434/api/generate
    ├─ model: self.model = "neural-chat" (from init)
    ├─ prompt: response context + message
    ├─ temperature: varies
    └─ timeout: 30 seconds
    ↓
Returns: Natural language response
```

### Call Path 3: Initial Greeting
```
New session started
    ↓
proactive_greeting_generator.py::init_proactive_greeting_generator()
    ↓
ProactiveGreetingGenerator initialized with model="neural-chat"
    ↓
HTTP POST to Ollama with self.model
    ↓
Returns: Personalized greeting
```

---

## INTEGRATION STRATEGY

### Option A: Modify Function Signatures (CHOSEN)
Modify default parameters in function definitions:
- `hybrid_response_engine.py::init_hybrid_engine()` - change default
- `proactive_greeting_generator.py::init_proactive_greeting_generator()` - change default
- `unified_ml_api_with_model.py::ollama_understand_message()` - change hardcoded model

**Advantage**: Configurable, can be reverted easily  
**Advantage**: All three call paths use same model  
**Risk**: Low - only defaults changed

### Implementation Plan
1. Temporarily change model name in 3 locations
2. Change: `"neural-chat"` → `"orca-mini:3b"`
3. Run integration tests
4. Revert changes after testing
5. All models remain installed

---

## FILES TO MODIFY (TEMPORARY)

| File | Line | Current | Temporary | Revert To |
|------|------|---------|-----------|-----------|
| unified_ml_api_with_model.py | 352 | `"neural-chat"` | `"orca-mini:3b"` | `"neural-chat"` |
| hybrid_response_engine.py | 22 | `model="neural-chat"` | `model="orca-mini:3b"` | `model="neural-chat"` |
| hybrid_response_engine.py | 591 | `model="neural-chat"` | `model="orca-mini:3b"` | `model="neural-chat"` |
| proactive_greeting_generator.py | 17 | `model: str = "neural-chat"` | `model: str = "orca-mini:3b"` | `model: str = "neural-chat"` |
| proactive_greeting_generator.py | 249 | `model: str = "neural-chat"` | `model: str = "orca-mini:3b"` | `model: str = "neural-chat"` |

**Total changes**: 5 locations in 3 files  
**Total lines modified**: 5 (all model name changes)  
**No logic changes**: Only parameter values

---

## SAFETY VERIFICATION

✓ **Crisis detection**: Unchanged (keyword-based, separate system)  
✓ **Emotion detection**: Unchanged (semantic analysis)  
✓ **Sarcasm detection**: Unchanged (semantic analysis)  
✓ **Memory system**: Unchanged  
✓ **Fallback logic**: Unchanged  
✓ **Architecture**: Unchanged  
✓ **Frontend**: Unchanged  

**All safety systems untouched**: Only model name parameter changed

---

## ROLLBACK PLAN

After testing, revert all 5 changes:
- Replace `"orca-mini:3b"` → `"neural-chat"` in unified_ml_api_with_model.py line 352
- Replace `model="orca-mini:3b"` → `model="neural-chat"` in hybrid_response_engine.py lines 22, 591
- Replace `model: str = "orca-mini:3b"` → `model: str = "neural-chat"` in proactive_greeting_generator.py lines 17, 249

**Time to rollback**: < 2 minutes  
**Reversibility**: 100% - No permanent changes  
**Data safety**: No data will be modified, all models remain installed

---

## READY FOR STEP 2

✓ Current configuration documented  
✓ All model locations identified  
✓ Integration strategy defined  
✓ Safety verified  
✓ Rollback plan ready  

Proceeding to **STEP 2: Temporarily Configure ORCA**
