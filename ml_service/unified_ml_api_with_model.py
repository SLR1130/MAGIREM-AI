"""
MAGIREM AI - Unified ML API with Trained Emotion Model + Long-Term Memory
This version uses a custom-trained transformer model instead of keyword matching
This solves the negation problem by learning from real conversation data
Now with long-term memory for continuous conversational context
"""

import os
import json
import random
import time
import threading
from copy import deepcopy
import requests
from flask import Flask, request, jsonify
import traceback
from pathlib import Path
import torch
import sys
from non_diagnostic_safety import enforce_non_diagnostic_output
from internal_auth import InternalAuthError, verify_request
from diagnostics import emit_diagnostic
from emotion_taxonomy import load_taxonomy, response_metadata as emotion_taxonomy_metadata, taxonomy_version
from emotion_interpreter_schema import validated_response_context


def _load_local_inference_environment():
    """Load only inference settings from the project .env for a direct Flask run.

    Laravel's .env is not inherited automatically when this module is launched
    from a terminal. Keeping this tiny loader local avoids a dotenv dependency,
    does not replace explicit process environment values, and deliberately
    refuses unrelated application/database settings.
    """
    env_path = Path(__file__).resolve().parents[1] / '.env'
    try:
        for raw_line in env_path.read_text(encoding='utf-8').splitlines():
            line = raw_line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, value = line.split('=', 1)
            key = key.strip()
            if not key.startswith(('MINDCARE_', 'OLLAMA_')):
                continue
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in {'"', "'"}:
                value = value[1:-1]
            if value:
                os.environ.setdefault(key, value)
    except OSError:
        # Explicit process environment remains supported when .env is absent.
        pass


_load_local_inference_environment()

# CAPTURE: Import wrapper to log actual Ollama POST calls
try:
    from capture_ollama_wrapper import logged_post
    import requests
    requests.post = logged_post
except:
    pass  # If wrapper fails, continue without it

# CRITICAL FIX: Force UTF-8 encoding for stdout/stderr
# This prevents UnicodeEncodeError when logging messages with emojis
# Must be done BEFORE any prints with potential Unicode content
try:
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')
except Exception:
    pass  # Python 3.6 doesn't support reconfigure

app = Flask(__name__)

# A short-lived completion ledger lets a reconnect reuse a completed inference.
# MySQL remains the durable record; entries expire quickly from process memory.
_CHAT_IDEMPOTENCY_CACHE = {}
_CHAT_IDEMPOTENCY_LOCK = threading.Lock()
_CHAT_IDEMPOTENCY_TTL_SECONDS = max(60, min(3600, int(os.getenv('MINDCARE_CHAT_IDEMPOTENCY_TTL_SECONDS', '900'))))

def _chat_idempotency_key(payload):
    request_id = str(payload.get('request_id', '')).strip()
    user_id = str(payload.get('user_id', '')).strip()
    if not request_id or len(request_id) > 80 or not user_id:
        return None
    if not all(char.isalnum() or char in '_.:-' for char in request_id):
        return None
    return f'{user_id}:{request_id}'

def _cached_chat_response(key):
    if not key:
        return None
    now = time.monotonic()
    with _CHAT_IDEMPOTENCY_LOCK:
        expired = [k for k, (expires_at, _) in _CHAT_IDEMPOTENCY_CACHE.items() if expires_at <= now]
        for expired_key in expired:
            _CHAT_IDEMPOTENCY_CACHE.pop(expired_key, None)
        record = _CHAT_IDEMPOTENCY_CACHE.get(key)
        return deepcopy(record[1]) if record else None

def _remember_chat_response(key, response):
    if key and isinstance(response, dict):
        with _CHAT_IDEMPOTENCY_LOCK:
            _CHAT_IDEMPOTENCY_CACHE[key] = (time.monotonic() + _CHAT_IDEMPOTENCY_TTL_SECONDS, deepcopy(response))


def _validated_emotion_response_context(payload, user_id):
    """Return a strictly bounded local-pilot cue, or no cue at all.

    Laravel supplies this only after the consent-gated shadow interpreter has
    validated it. Flask validates it again and requires an explicit synthetic
    account allow-list, so a browser cannot opt itself into the pilot or inject
    an unsupported label. The normal six-class/crisis paths never consult this
    helper when the feature flags are disabled.
    """
    if os.getenv('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_ENABLED', 'false').lower() != 'true':
        return None
    if os.getenv('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_APPROVED', 'false').lower() != 'true':
        return None
    allowed_account = os.getenv('MINDCARE_DETAILED_EMOTION_RESPONSE_PILOT_ACCOUNT_ID', '').strip()
    if not allowed_account or str(user_id).strip() != allowed_account:
        return None
    if not isinstance(payload, dict):
        return None
    taxonomy = load_taxonomy()
    index = {item.get('key'): item for item in taxonomy.get('emotions', []) if item.get('key')}
    return validated_response_context(
        payload,
        index,
        taxonomy_version=str(taxonomy.get('version') or taxonomy_version()),
    )

# CRITICAL FIX: Configure Flask to use UTF-8 for all JSON responses
# This prevents UnicodeEncodeError when returning responses with emojis
app.config['JSON_ENSURE_ASCII'] = False  # Allow non-ASCII characters in JSON

# For older Flask versions, also set ensure_ascii on the app
if hasattr(app, 'json'):
    app.json.ensure_ascii = False
elif hasattr(app, 'json_encoder'):
    app.config['JSON_SORT_KEYS'] = False

_PHASE4A_OPERATIONS = {
    '/chat': 'memory.chat',
    '/api/initial-greeting': 'memory.initial_greeting',
}

@app.before_request
def _phase4a_direct_auth_boundary():
    operation = _PHASE4A_OPERATIONS.get(request.path)
    if operation is None:
        return None
    if os.getenv('MINDCARE_PHASE4A_AUTH_ENABLED', 'false').lower() != 'true':
        return None
    try:
        context = verify_request(request, operation)
        payload = request.get_json(silent=True) or {}
        if not isinstance(payload, dict) or not payload.get('user_id'):
            return jsonify({'success': False, 'error': 'user_id_required'}), 400
        from flask import g
        g.mindcare_auth = context
    except InternalAuthError as exc:
        return jsonify({'success': False, 'error': 'internal_authorization_failed', 'code': exc.code}), exc.status
    return None

# Memory system
_memory_manager = None

# Try to load trained model first, fall back to V2
_emotion_classifier = None
_smart_gen = None

# Shadow mode for diagnostics (disabled by default)
_shadow_classifier = None

# Fusion shadow mode for diagnostics (disabled by default)
_fusion_shadow_enabled = False
_fusion_service = None

def load_shadow_mode_classifier():
    """Load trained classifier in shadow mode for diagnostics"""
    global _shadow_classifier
    
    shadow_enabled = os.getenv("EMOTION_MODEL_SHADOW_ENABLED", "false").lower() == "true"
    
    if not shadow_enabled:
        print("[SHADOW_MODE] Disabled (EMOTION_MODEL_SHADOW_ENABLED=false)")
        return False
    
    try:
        from shadow_mode_loader import ShadowModeEmotionClassifier
        _shadow_classifier = ShadowModeEmotionClassifier(
            enabled=True,
            deployable_path="emotion_model_multilingual_deployable"
        )
        print("[SHADOW_MODE] Initialized and ready")
        return True
    except Exception as e:
        print(f"[SHADOW_MODE] Failed to initialize: {e}")
        return False

def load_fusion_shadow_service():
    """Load fusion shadow service for diagnostic emotion analysis"""
    global _fusion_shadow_enabled, _fusion_service
    
    fusion_enabled = os.getenv("FUSION_SHADOW_ENABLED", "false").lower() == "true"
    
    if not fusion_enabled:
        print("[FUSION_SHADOW] Disabled (FUSION_SHADOW_ENABLED=false)")
        return False
    
    try:
        from emotion_fusion_service import EmotionFusionService
        _fusion_service = EmotionFusionService()
        _fusion_shadow_enabled = True
        print("[FUSION_SHADOW] Enabled and ready for diagnostic shadow analysis")
        return True
    except Exception as e:
        print(f"[FUSION_SHADOW] Failed to initialize: {e}")
        _fusion_shadow_enabled = False
        return False

def load_memory_system():
    """Initialize long-term memory system"""
    global _memory_manager
    if os.getenv('MINDCARE_PHASE4A_AUTH_ENABLED', 'false').lower() != 'true':
        print('[MEMORY] Persistent memory disabled until Phase 4A auth is enabled')
        return False
    try:
        from memory_manager import MemoryManager
        from memory_config import resolve_memory_db_path

        memory_db_path = resolve_memory_db_path()
        _memory_manager = MemoryManager(str(memory_db_path))
        if os.getenv('MINDCARE_PHASE4B_CONSENT_DB'):
            from consent_store import ConsentAwareMemoryManager, ConsentStore
            _memory_manager = ConsentAwareMemoryManager(_memory_manager, ConsentStore())
            print('[MEMORY] Phase 4B consent enforcement enabled')
        print("[MEMORY] Long-term memory system initialized")
        print(memory_db_path)
        return True
    except Exception as e:
        print(f"[MEMORY_ERROR] Failed to initialize memory system: {e}")
        return False

def load_trained_model():
    """Load the trained emotion detection model - NOW ACTIVATED"""
    global _emotion_classifier
    
    # ============================================================================
    # TASK 10 STATUS: Multilingual Emotion Classifier - ACTIVATED FOR PRODUCTION
    # Date: October 15, 2026
    # Status: NOW PRIMARY EMOTION CLASSIFIER
    # 
    # Training Results:
    #   - Validation F1: 0.8034 (excellent)
    #   - Test Accuracy: 73.96% (validated on held-out set)
    #   - Languages: English, Tagalog, Taglish (multilingual)
    #   - Classes: sadness, joy, anxiety, anger, neutral, grief
    # 
    # Model Location:
    #   ml_service/emotion_model_multilingual_candidate/checkpoint-130/
    # 
    # Tokenizer: distilroberta-base (verified during validation)
    # 
    # Current Status: ACTIVE - PRIMARY EMOTION SOURCE
    # ============================================================================
    
    try:
        from trained_emotion_classifier import get_classifier
        
        print("[TRAINED_MODEL] Loading validated multilingual classifier...")
        classifier = get_classifier()
        
        if classifier.enabled:
            _emotion_classifier = classifier
            print("[TRAINED_MODEL] ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“ PRIMARY CLASSIFIER ACTIVATED")
            print("[TRAINED_MODEL] Emotion classes: sadness, joy, anxiety, anger, neutral, grief")
            print("[TRAINED_MODEL] Architecture: RobertaBased | Tokenizer: distilroberta-base")
            print("[TRAINED_MODEL] Validation F1: 0.8034 | Test Accuracy: 73.96%")
            return True
        else:
            print("[TRAINED_MODEL] ÃƒÂ¢Ã…â€œÃ¢â‚¬â€ Classifier failed to load")
            _emotion_classifier = None
            return False
            
    except Exception as e:
        print(f"[TRAINED_MODEL] ÃƒÂ¢Ã…â€œÃ¢â‚¬â€ Failed to load classifier: {e}")
        _emotion_classifier = None
        return False

def load_ml_response_generator():
    """Load response generator - HYBRID: Trained Model + LLM"""
    global _smart_gen
    
    # PRIMARY: Try hybrid engine (trained model + LLM)
    try:
        from hybrid_response_engine import init_hybrid_engine
        # Try to connect to Ollama
        result = init_hybrid_engine()
        if result:
            print("[OK] HYBRID SYSTEM LOADED")
            print("[INFO] [OK] Your trained emotion model + LLM = Natural conversations")
            print("[INFO] NO TEMPLATES - Your training data powers the responses!")
            _smart_gen = "hybrid"  # Flag to use hybrid in chat endpoint
            return True
        else:
            # Ollama not running yet, but we'll still set hybrid as primary
            # It will try to connect on each request
            print("[WARNING] Ollama not running YET")
            print("[INFO] Starting hybrid mode - will connect to Ollama when available")
            print("[INFO] START OLLAMA with: ollama serve")
            _smart_gen = "hybrid"  # Still use hybrid, will try to connect on chat
            return True
    except Exception as e:
        print(f"[INFO] Hybrid system error: {e}")
        
        # FALLBACK: Try LLM only
        try:
            from llm_response_generator import init_llm_generator
            if init_llm_generator():
                print("[OK] LLM fallback loaded")
                _smart_gen = "llm"
                return True
        except:
            pass
        
        # LAST RESORT: Use templates (not ideal, but fallback)
        try:
            from smart_response_generator_v2 import SmartResponseGeneratorV2
            _smart_gen = SmartResponseGeneratorV2()
            print("[WARNING] Using template system as LAST RESORT")
            print("[WARNING] To enable full hybrid system: install Ollama and run 'ollama serve'")
            print("[WARNING] Download from: https://ollama.ai/")
            return True
        except Exception as e2:
            print(f"[ERROR] Failed to load any response generator: {e2}")
            return False

def detect_crisis(text):
    """Detect crisis/self-harm keywords - this is always keyword-based for safety"""
    text_lower = text.lower()
    crisis_keywords = [
        'want to die', 'suicide', 'suicidal', 'kill myself', 'harm myself',
        'self harm', 'cut myself', 'going to kill', 'hopeless', 'worthless'
    ]
    
    for keyword in crisis_keywords:
        if keyword in text_lower:
            return 'critical'
    return 'none'

def detect_emotion_trained(text):
    """
    Use PRIMARY trained model to detect emotion
    
    Returns trained model prediction with all confidence scores, or None if unavailable
    """
    if _emotion_classifier is None:
        print(f"[TRAINED_MODEL] Classifier not available, emotion will use semantic fallback")
        return None
    
    try:
        result = _emotion_classifier.predict(text)
        if result:
            print(f"[TRAINED_MODEL] Prediction: {result['emotion']} ({result['confidence']:.2%})")
            print(f"[TRAINED_MODEL] Inference: {result['inference_time_ms']:.1f}ms")
            return result
        else:
            print(f"[TRAINED_MODEL] Prediction returned None")
            return None
            
    except Exception as e:
        print(f"[TRAINED_MODEL] Prediction error: {e}")
        return None

def detect_emotion_fallback(text):
    """Use V2 keyword matching as fallback"""
    # If we have V2 (SmartResponseGeneratorV2), use it
    if _smart_gen and hasattr(_smart_gen, 'detect_emotion'):
        try:
            emotion = _smart_gen.detect_emotion(text)
            print(f"[FALLBACK] Using V2 keyword matching: {emotion}")
            return emotion
        except Exception as e:
            print(f"[ERROR] V2 classification failed: {e}")
            return _simple_keyword_emotion(text)
    else:
        # Use simple keyword matching
        return _simple_keyword_emotion(text)

def _simple_keyword_emotion(text):
    """Simple keyword-based emotion detection as final fallback"""
    text_lower = text.lower()
    
    # Crisis first
    if any(kw in text_lower for kw in ['suicide', 'kill myself', 'want to die', 'end my life', 'self harm']):
        return 'critical'
    
    # SADNESS indicators (including loss/grief keywords)
    sadness_keywords = ['sad', 'down', 'depressed', 'lonely', 'heartbroken', 'crying', 'not good', 'not feeling well', 'terrible', 'awful', 'die', 'death', 'died', 'lost', 'loss', 'grief', 'bereaved', 'devastated', 'miserable', 'sorrowful', 'unhappy', 'tearful', 'broken']
    if any(kw in text_lower for kw in sadness_keywords):
        return 'sadness'
    
    # ANXIETY indicators  
    anxiety_keywords = ['anxious', 'worried', 'nervous', 'stressed', 'panic', 'afraid', 'scared', 'overwhelming', 'worry', 'anxiety', 'nervous', 'tense', 'dread', 'uneasy']
    if any(kw in text_lower for kw in anxiety_keywords):
        return 'anxiety'
    
    # ANGER indicators
    anger_keywords = ['angry', 'mad', 'frustrated', 'irritated', 'furious', 'rage', 'angry', 'upset', 'pissed', 'annoyed', 'livid', 'enraged']
    if any(kw in text_lower for kw in anger_keywords):
        return 'anger'
    
    # JOY indicators
    joy_keywords = ['happy', 'great', 'wonderful', 'amazing', 'excited', 'love', 'excellent', 'fantastic', 'thrilled', 'delighted', 'joyful', 'blessed']
    if any(kw in text_lower for kw in joy_keywords):
        return 'joy'
    
    return 'neutral'

@app.route('/health', methods=['GET'])
def health():
    """Health check"""
    try:
        from gpu_inference_queue import queue as gpu_inference_queue
        queue_status = gpu_inference_queue.status()
    except Exception:
        queue_status = {'active': None, 'waiting': None, 'capacity': None, 'queue_limit': None}
    return jsonify({
        'status': 'ok',
        'server_running': True,
        'ml_available': True,
        'model_type': 'trained' if _emotion_classifier else 'fallback',
        'persistent_memory_enabled': _memory_manager is not None,
        'gpu_queue': queue_status,
    })

@app.route('/api/initial-greeting', methods=['POST'])
def initial_greeting():
    """
    Generate initial AI greeting for new conversation
    This is the FIRST message - AI starts the conversation
    """
    try:
        data = request.get_json()
        user_id = data.get('user_id', 'anonymous')
        user_name = data.get('user_name')
        has_previous_conversations = data.get('has_previous_conversations', False)
        
        print(f"\n[GREETING] Generating initial greeting for user {user_id} ({user_name})")
        print(f"[GREETING] Previous conversations: {has_previous_conversations}")
        
        # Get recent memories if available
        recent_memories = None
        if _memory_manager:
            try:
                memories = _memory_manager.store.get_user_memories(user_id, status='active')
                if memories:
                    # Limit to 2 most recent memories
                    recent_memories = [m['content'][:50] for m in memories[:2]]
                    print(f"[GREETING] Retrieved {len(memories)} recent memories")
            except Exception as e:
                print(f"[GREETING_MEMORY_ERROR] Could not retrieve memories: {e}")
        
        # Generate greeting using AI greeting initiator
        try:
            from ai_greeting_initiator import generate_initial_greeting
            
            result = generate_initial_greeting(
                user_id=user_id,
                user_name=user_name,
                recent_memories=recent_memories,
                has_previous_conversations=has_previous_conversations
            )

            # Keep generated greetings on the same deterministic
            # non-diagnostic boundary as normal responses. Laravel remains
            # authoritative for crisis assessment and safety routing.
            if result.get('greeting'):
                guarded_greeting, guard_reason = enforce_non_diagnostic_output(
                    result['greeting'], 'en', 'none'
                )
                result['greeting'] = guarded_greeting
                if guard_reason != 'accepted':
                    result['guard_reason'] = guard_reason
                    result['response_source'] = 'non_diagnostic_guard'
            
            print(f"[GREETING_SUCCESS] Generated greeting ({result['method']})")
            
            return jsonify(result)
        
        except Exception as e:
            print(f"[GREETING_ERROR] Failed to generate greeting: {e}")
            import traceback
            traceback.print_exc()
            
            # Fallback greeting if all else fails
            fallback = {
                'success': True,
                'greeting': "Hi! I'm MAGI, your wellness companion." if not has_previous_conversations
                           else "Hey! Good to see you. What's on your mind?",
                'method': 'fallback',
                'type': 'returning_user' if has_previous_conversations else 'new_user'
            }
            fallback['greeting'], fallback_reason = enforce_non_diagnostic_output(
                fallback['greeting'], 'en', 'none'
            )
            if fallback_reason != 'accepted':
                fallback['guard_reason'] = fallback_reason
                fallback['response_source'] = 'non_diagnostic_guard'
            return jsonify(fallback)
    
    except Exception as e:
        print(f"[GREETING_FATAL] Unhandled error: {e}")
        import traceback
        traceback.print_exc()
        
        return jsonify({
            'success': False,
            'error': str(e),
            'greeting': "Hi there! What would you like to talk about?"
        }), 500

@app.route('/analyze', methods=['POST'])
def analyze():
    """
    Enhanced semantic analysis endpoint
    Returns structured ML analysis with emotion probabilities, tone analysis, crisis indicators
    This feeds into Laravel's EnhancedCrisisAnalysisService for final decision
    """
    try:
        data = request.get_json()
        message = data.get('message', data.get('text', '')).strip()
        conversation_history = data.get('conversation_history', [])
        
        if not message:
            return jsonify({'error': 'Message is required'}), 400
        
        print(f"\n[ANALYZE] Semantic analysis request length={len(message)}")
        
        # SEMANTIC ANALYSIS: Get emotion probabilities, tone, crisis indicators
        from semantic_analysis import analyze_semantic_layer
        semantic_result = analyze_semantic_layer(message, conversation_history)
        
        print(f"[SEMANTIC] Dominant emotion: {semantic_result['emotion']['dominant']} "
              f"({semantic_result['emotion']['confidence']:.2%})")
        
        trained_result = detect_emotion_trained(message)
        if trained_result:
            emotion = trained_result['emotion']
            confidence = float(trained_result['confidence'])
            probabilities = trained_result.get('probabilities', {})
            method = 'ml_server_trained'
            inference_time_ms = trained_result.get('inference_time_ms')
        else:
            emotion = semantic_result['emotion']['dominant']
            confidence = float(semantic_result['emotion']['confidence'])
            probabilities = semantic_result['emotion'].get('scores', {})
            method = 'ml_server_semantic_fallback'
            inference_time_ms = None

        crisis = semantic_result.get('crisis_semantic_analysis', {})
        if crisis.get('planning', 0) >= 0.75 or (
            crisis.get('intent', 0) >= 0.65 and crisis.get('temporal_urgency', 0) >= 0.6
        ):
            crisis_level = 'critical'
        elif crisis.get('ideation', 0) >= 0.6 or crisis.get('planning', 0) >= 0.6:
            crisis_level = 'severe'
        elif crisis.get('distress', 0) >= 0.5:
            crisis_level = 'moderate'
        else:
            crisis_level = 'none'

        taxonomy_metadata = emotion_taxonomy_metadata(emotion)

        return jsonify({
            'success': True,
            'emotion': emotion,
            'confidence': confidence * 100,
            'method': method,
            'confidence_type': 'model_softmax_max' if method == 'ml_server_trained' else 'semantic_heuristic_score',
            'confidence_is_calibrated': False,
            'uncertain': method != 'ml_server_trained' or confidence < 0.5,
            'raw_label': trained_result.get('raw_label') if trained_result else None,
            'all_scores': probabilities,
            'intensity': semantic_result['emotion'].get('intensity', 0.5),
            'crisis_level': crisis_level,
            'crisis_confidence': max(crisis.values()) if crisis else 0.0,
            'needs_counselor': crisis_level in ('severe', 'critical'),
            'crisis_category': 'semantic_safety_signal' if crisis_level != 'none' else None,
            'contexts': [],
            'semantic_analysis': semantic_result,
            'processing_time_ms': inference_time_ms,
            # Additive reporting metadata; the original label remains unchanged.
            'emotion_taxonomy': taxonomy_metadata,
        })
        
    except Exception as e:
        print(f"[ANALYZE_ERROR] {e}")
        import traceback
        traceback.print_exc()
        
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

def ollama_understand_message(message: str, memory_context: str = None) -> dict:
    """
    Use Ollama to understand the raw message without emotion labels.
    Returns structured interpretation that will inform response generation.
    
    This is Phase 1 of the refactor: Let Ollama read the message first
    and understand its situation, topic, intent before generating responses.
    
    Args:
        message: Raw user message
        memory_context: Optional long-term memory context
    
    Returns:
        Dict with keys:
        - situation: What is happening in this message
        - topic: What is being discussed  
        - intent: What does user want (seeking_support, asking_advice, venting, etc)
        - emotional_meaning: What is the actual emotional content
        - important_details: List of key facts from the message
        - sarcasm_or_figurative: Boolean - is user being sarcastic/hyperbolic
        - is_question: Boolean - is user asking a question
        - severity_indicator: low/medium/high based on content
    """
    import requests
    import json
    
    try:
        # Build understanding prompt for Ollama
        understanding_prompt = """You are analyzing a message to understand its structure and meaning.
        
User's message:
"{message}"

Analyze this message and provide a structured JSON response with:
- situation: What is happening in this message (1-2 sentences)
- topic: What is being discussed (e.g., relationship, work, school, family)
- intent: What does the user need/want (seeking_support, asking_advice, venting, asking_question, sharing_news)
- emotional_meaning: What is the actual emotional content (don't label as emotion, describe the meaning)
- important_details: Key facts or context from the message (as a list)
- sarcasm_or_figurative: True if user is being sarcastic, ironic, or using hyperbole
- is_question: True if user is asking a question
- severity_indicator: low/medium/high based on the content

Respond ONLY with valid JSON, no other text. Example format:
{{
  "situation": "User is worried about an upcoming test",
  "topic": "academics",
  "intent": "seeking_support",
  "emotional_meaning": "anxious about performance and feeling unprepared",
  "important_details": ["test is tomorrow", "hasn't studied much", "worried about grade"],
  "sarcasm_or_figurative": false,
  "is_question": false,
  "severity_indicator": "low"
}}

Now analyze this message and respond with ONLY the JSON:""".format(message=message)
        
        # Call Ollama to understand the message
        response = requests.post(
            "http://localhost:11434/api/generate",
            json={
                "model": "orca-mini:3b",
                "prompt": understanding_prompt,
                "stream": False,  # Get complete response
                "temperature": 0.3,  # Lower temperature for consistent structure
                "top_p": 0.9,
            },
            timeout=(5, 15),
        )
        
        if response.status_code == 200:
            response_data = response.json()
            response_text = response_data.get("response", "").strip()
            
            # Extract JSON from response
            try:
                # Try to parse the response as JSON
                understanding = json.loads(response_text)
                
                # Validate it has required keys
                required_keys = ['situation', 'topic', 'intent', 'emotional_meaning', 
                                'important_details', 'sarcasm_or_figurative', 'is_question', 
                                'severity_indicator']
                
                if minimal_semantic_check(understanding):
                    print(f"[OLLAMA_UNDERSTAND] Successfully understood message")
                    print(f"[OLLAMA_UNDERSTAND] Topic: {understanding.get('topic')}, Intent: {understanding.get('intent')}")
                    return understanding
                else:
                    print(f"[OLLAMA_UNDERSTAND] Understanding validation failed")
                    return _fallback_understanding(message)
                    
            except json.JSONDecodeError:
                response_text = ''
                print(f"[OLLAMA_UNDERSTAND_ERROR] Could not parse JSON response: {response_text[:100]}")
                return _fallback_understanding(message)
        else:
            print(f"[OLLAMA_UNDERSTAND_ERROR] Ollama returned {response.status_code}")
            return _fallback_understanding(message)
            
    except Exception as e:
        print(f"[OLLAMA_UNDERSTAND_ERROR] {e}")
        return _fallback_understanding(message)

def _fallback_understanding(message: str) -> dict:
    """
    Fallback understanding when Ollama fails.
    Uses simple heuristics instead of LLM.
    """
    # Simple checks
    is_question = message.strip().endswith('?')
    has_sarcasm = any(marker in message.lower() for marker in ['sure', 'yeah right', 'oh sure', 'lol'])
    
    # Topic detection
    topics_keywords = {
        'relationship': ['boyfriend', 'girlfriend', 'partner', 'spouse', 'dating', 'crush', 'cheating', 'breakup', 'broke up'],
        'work': ['work', 'job', 'boss', 'coworker', 'project', 'deadline', 'meeting', 'presentation'],
        'school': ['school', 'class', 'exam', 'test', 'assignment', 'homework', 'grade', 'professor', 'university'],
        'family': ['mom', 'dad', 'mother', 'father', 'parent', 'sibling', 'brother', 'sister', 'family'],
        'health': ['sick', 'illness', 'doctor', 'hospital', 'pain', 'health', 'medical'],
        'mental': ['sad', 'depressed', 'anxiety', 'anxious', 'stressed', 'overwhelmed', 'struggling'],
    }
    
    topic = 'general'
    msg_lower = message.lower()
    for potential_topic, keywords in topics_keywords.items():
        if any(kw in msg_lower for kw in keywords):
            topic = potential_topic
            break
    
    return {
        'situation': message[:100],  # Truncate if too long
        'topic': topic,
        'intent': 'seeking_support' if not is_question else 'asking_question',
        'emotional_meaning': 'User is reaching out',
        'important_details': [],
        'sarcasm_or_figurative': has_sarcasm,
        'is_question': is_question,
        'severity_indicator': 'low',
    }

def minimal_semantic_check(understanding: dict) -> bool:
    """
    Validate that understanding JSON has required keys and reasonable values.
    Added to semantic_analysis.py as well.
    """
    required_keys = ['situation', 'topic', 'intent', 'emotional_meaning', 
                    'important_details', 'sarcasm_or_figurative', 'is_question', 
                    'severity_indicator']
    
    # Check all required keys exist
    for key in required_keys:
        if key not in understanding:
            print(f"[MINIMAL_SEMANTIC_CHECK] Missing key: {key}")
            return False
    
    # Basic type checking
    if not isinstance(understanding.get('situation'), str):
        return False
    if not isinstance(understanding.get('topic'), str):
        return False
    if not isinstance(understanding.get('intent'), str):
        return False
    if not isinstance(understanding.get('emotional_meaning'), str):
        return False
    if not isinstance(understanding.get('important_details'), list):
        return False
    if not isinstance(understanding.get('sarcasm_or_figurative'), bool):
        return False
    if not isinstance(understanding.get('is_question'), bool):
        return False
    if not isinstance(understanding.get('severity_indicator'), str):
        return False
    
    return True

@app.route('/chat', methods=['POST'])
def chat():
    """Main chat endpoint"""
    import sys
    import uuid
    
    request_started = time.perf_counter()
    header_correlation = request.headers.get('X-MindCare-Correlation-ID', '').strip()
    valid_correlation = lambda value: 8 <= len(value) <= 80 and all(char.isalnum() or char in '-_.:' for char in value)
    CORRELATION_ID = header_correlation if valid_correlation(header_correlation) else str(uuid.uuid4())[:8]
    stage_timings = {}
    history_turns_received = 0
    
    sys.stderr.write(f"[FLASK_CHAT_START] correlation_id={CORRELATION_ID}\n")
    sys.stderr.flush()
    
    try:
        # DIAGNOSTIC: Mark that we entered the try block - use stderr
        import sys
        sys.stderr.write('DIAGNOSTIC_MARKER_ENTERED_TRY_BLOCK\n')
        sys.stderr.flush()
        
        data = request.get_json() or {}
        if not isinstance(data, dict):
            return jsonify({'success': False, 'error': 'Invalid request payload.'}), 400
        idempotency_key = _chat_idempotency_key(data)
        cached_response = _cached_chat_response(idempotency_key)
        if cached_response is not None:
            print('[CHAT_IDEMPOTENT_REPLAY] request_id={}'.format(str(data.get('request_id', ''))[:80]))
            return jsonify(cached_response)
        payload_correlation = str(data.get('correlation_id', '')).strip()
        if not header_correlation and valid_correlation(payload_correlation):
            CORRELATION_ID = payload_correlation
        message = data.get('message', '').strip()
        user_id = data.get('user_id', 'anonymous')
        emotion_response_context = _validated_emotion_response_context(
            data.get('emotion_response_context'), user_id
        )
        if emotion_response_context:
            # Safe trace marker only: never log message text or emotion labels.
            print('[EMOTION_RESPONSE_PILOT] cue_accepted=true')
        elif isinstance(data.get('emotion_response_context'), dict):
            # A supplied but rejected cue is observable only as a boolean.
            print('[EMOTION_RESPONSE_PILOT] cue_accepted=false')
        conversation_history = data.get('conversation_history', [])
        history_turns_received = len(conversation_history) if isinstance(conversation_history, list) else 0
        # Laravel creates this only from the authenticated session's completed
        # records. It is bounded and session-scoped, not optional cross-session
        # memory. Reject malformed values rather than letting an oversized body
        # expand the Ollama prompt.
        session_context = data.get('session_context')
        if not isinstance(session_context, str):
            session_context = ''
        session_context = session_context.strip()[:2400]
        counselor_context = data.get('counselor_context', {})
        if not isinstance(counselor_context, dict):
            counselor_context = {}
        
        # TASK 6 FIX: Accept Laravel's crisis assessment and respect it
        # This prevents Flask from over-escalating with its own keyword matching
        laravel_crisis_assessment = data.get('laravel_crisis_assessment', {})
        laravel_crisis_level = laravel_crisis_assessment.get('crisis_level', 'none')
        laravel_emotion_analysis = data.get('laravel_emotion_analysis', {})
        
        if not message:
            return jsonify({'error': 'Message is required'}), 400
        
        sys.stderr.write(f"[FLASK_CHAT] correlation_id={CORRELATION_ID}, msg_len={len(message)}, laravel_crisis={laravel_crisis_level}\n")
        sys.stderr.flush()
        
        print(f"\n[CHAT] New message length={len(message)} (Laravel crisis: {laravel_crisis_level})")
        
        # SEMANTIC LAYER: Enhanced analysis (ONE PASS ONLY)
        stage_started = time.perf_counter()
        try:
            from semantic_analysis import analyze_semantic_layer
            semantic_result = analyze_semantic_layer(message, conversation_history)
            print(f"[SEMANTIC] Analysis complete - emotion: {semantic_result['emotion']['dominant']}")
        except Exception as e:
            print(f"[SEMANTIC_ERROR] Failed to load semantic analysis: {e}")
            semantic_result = None
        stage_timings['semantic_ms'] = round((time.perf_counter() - stage_started) * 1000, 2)
        
        # UNIFIED ANALYSIS: Consolidate all signals into structured result (PASS semantic_result to avoid re-analysis)
        unified_result = None
        stage_started = time.perf_counter()
        try:
            from unified_analysis import analyze_unified
            unified_result = analyze_unified(
                message,
                conversation_history,
                semantic_result=semantic_result  # ÃƒÂ¢Ã¢â‚¬Â Ã‚Â REUSE existing result, don't re-analyze
            )
            print(f"[UNIFIED] Analysis complete - emotion: {unified_result.emotion}, crisis_level: {unified_result.crisis['safety_level']}")
        except Exception as e:
            print(f"[UNIFIED_ERROR] Failed to load unified analysis: {e}")
            unified_result = None
        stage_timings['unified_ms'] = round((time.perf_counter() - stage_started) * 1000, 2)
        
        # LONG-TERM MEMORY: Retrieve relevant context from previous conversations
        memory_context = None
        if _memory_manager:
            try:
                memory_context = _memory_manager.retrieve_context(
                    user_id=user_id,
                    current_message=message,
                    conversation_history=conversation_history,
                    max_memories=5,
                    max_tokens=300
                )
                
                if memory_context['memories'] or memory_context['projects']:
                    print(f"[MEMORY] Retrieved {len(memory_context['memories'])} relevant memories")
                    if memory_context['projects']:
                        print(f"[MEMORY] User has {len(memory_context['projects'])} active projects")
            except Exception as e:
                print(f"[MEMORY_ERROR] Failed to retrieve context: {e}")
                memory_context = None
        
        # CONTEXT CLASSIFICATION: Understand interaction type FIRST
        # This informs everything downstream
        try:
            from context_classifier import classify_interaction
            
            # FIX 2: Pass semantic analysis data to context classifier
            # Extract emotion_confidence from semantic_result if available
            emotion_for_context = None
            emotion_confidence_for_context = 0.0
            
            if semantic_result:
                emotion_for_context = semantic_result.get('emotion', {}).get('dominant')
                emotion_confidence_for_context = semantic_result.get('emotion', {}).get('confidence', 0.0)
                print(f"[FIX2_DEBUG] Extracted from semantic_result: emotion={emotion_for_context}, confidence={emotion_confidence_for_context:.2%}")
            else:
                print(f"[FIX2_DEBUG] semantic_result is None")
            
            context_result = classify_interaction(
                message,
                conversation_history,
                emotion_from_model=emotion_for_context,
                emotion_confidence=emotion_confidence_for_context
            )
            print(f"[CONTEXT] Classification: {context_result['interaction_type']} "
                  f"(confidence: {context_result['confidence']:.0%})")
            print(f"[CONTEXT] Reasoning: {context_result['reasoning']}")
            if context_result['user_correction']:
                print(f"[CONTEXT] User is correcting previous interpretation")
        except Exception as e:
            print(f"[CONTEXT_ERROR] Classification failed: {e}")
            context_result = None
        
        # REFACTORED: Removed sequential Ollama understanding call
        # All context information will be passed directly to generation prompt
        # This eliminates the ~15s timeout bottleneck
        print(f"[REFACTOR] Skipping separate understanding call - consolidating into single generation call")
        
        # TASK 6 FIX: Detect crisis ONLY using unified analysis, NOT simple keyword matching
        # This prevents over-escalation for messages like "I used to want to die but I'm better now"
        # Simple keyword matching can't handle negation or temporal context
        if unified_result and unified_result.crisis['safety_level'] in ['critical', 'severe']:
            crisis_level = 'critical'
            print(f"[CRISIS] Detected via unified analysis: {unified_result.crisis['safety_level']}")
        else:
            # Don't use keyword_based detection - it causes false positives
            # Let Gemma handle crisis assessment in Laravel instead
            crisis_level = 'none'
            print(f"[NO_CRISIS] Using unified analysis - no crisis detected or unclear")
        
        # TASK 6 FIX: Flask as SECONDARY safety signal, NOT primary authority
        # If Flask detects possible crisis: return metadata for Laravel to decide
        # Do NOT directly inject crisis response when Laravel might already have context
        secondary_safety_signal = {
            'detected': False,
            'level': 'none',  # 'none', 'possible', 'high'
            'source': 'unified_analysis'
        }
        
        if crisis_level == 'critical':
            # Flask detected crisis - but Laravel is the authority
            # Return this as a secondary signal, not a direct response override
            secondary_safety_signal = {
                'detected': True,
                'level': 'high',
                'source': 'unified_analysis',
                'reason': 'Flask unified analysis detected a potential crisis signal.'
            }
            # DO NOT return crisis template here
            # Let Laravel handle the crisis response
            # For now, continue to normal generation and include the signal
            print(f"[SECONDARY_SIGNAL] Flask detected crisis, returning as metadata")
        
        # Continue to normal emotion detection and response generation
        # Flask will NOT directly inject crisis template
        # (Crisis response was being wrongly injected at old code line 646)
        
        # CONFIDENCE-AWARE ROUTING: PRIMARY TRAINED CLASSIFIER WITH SEMANTIC FALLBACK
        emotion_stage_started = time.perf_counter()
        emotion = None
        emotion_confidence = 0.7
        emotion_source = None
        emotion_probabilities = {}
        emotion_inference_time = None
        trained_emotion = None
        trained_confidence = None
        
        # PHASE 2: CONFIDENCE ROUTING THRESHOLD
        CONFIDENCE_THRESHOLD = 0.50  # Use trained if confidence >= 0.50
        GRIEF_PRESERVATION_THRESHOLD = 0.35  # Preserve only stronger low-confidence grief predictions; fallback has no grief class.
        GRIEF_PRESERVATION_MARGIN = 0.10  # Exclude ambiguous grief-vs-other top-class ties.
        
        # PHASE 3: ROUTING DECISION LOG
        import sys
        
        # TRACE: Log that we're about to call detect_emotion_trained()
        sys.stderr.write(f"[TRACE_TRAINED_START] Calling trained classifier for message_length={len(message)}\n")
        sys.stderr.flush()
        
        # Reuse Laravel's trained /analyze result when available; otherwise classify here.
        laravel_emotion_method = str(laravel_emotion_analysis.get('method', ''))
        if laravel_emotion_method == 'ml_server_trained' and laravel_emotion_analysis.get('emotion'):
            trained_result = {
                'emotion': laravel_emotion_analysis.get('emotion', 'neutral'),
                'confidence': float(laravel_emotion_analysis.get('confidence', 0.0)),
                'probabilities': laravel_emotion_analysis.get('all_scores', {}),
                'inference_time_ms': laravel_emotion_analysis.get('processing_time_ms'),
            }
            sys.stderr.write('[TRACE_TRAINED_REUSE] Reused Laravel trained classifier result\n')
        else:
            trained_result = detect_emotion_trained(message)
        
        sys.stderr.write(f"[TRACE_TRAINED_RESULT] Result from detect_emotion_trained(): {trained_result}\n")
        sys.stderr.flush()
        
        if trained_result:
            trained_emotion = trained_result['emotion']
            trained_confidence = float(trained_result['confidence'])
            if trained_confidence > 1:
                trained_confidence /= 100.0
            emotion_inference_time = trained_result.get('inference_time_ms')
            emotion_probabilities = trained_result.get('probabilities', {})
            sys.stderr.write(f"[TRACE_TRAINED_EXTRACTED] trained_emotion={trained_emotion}, trained_confidence={trained_confidence}\n")
            sys.stderr.flush()
            
            # LOG THE ROUTING DECISION
            sys.stderr.write(f"[EMOTION_ROUTING] trained_emotion={trained_emotion}, trained_confidence={trained_confidence:.4f}, threshold={CONFIDENCE_THRESHOLD}, ")

            trained_scores = trained_result.get('probabilities', {}) or {}
            competing_scores = [
                float(score) for label, score in trained_scores.items()
                if label != trained_emotion
            ]
            grief_margin = trained_confidence - max(competing_scores, default=0.0)
            
            # MAKE THE ROUTING DECISION
            if trained_confidence >= CONFIDENCE_THRESHOLD:
                # Confidence is sufficient - use trained model
                emotion = trained_emotion
                emotion_confidence = trained_confidence
                emotion_source = 'trained_model'
                sys.stderr.write(f"decision=trained_model\n")
                print(f"[EMOTION_ROUTING] trained_emotion={trained_emotion}, trained_confidence={trained_confidence:.4f}, threshold={CONFIDENCE_THRESHOLD}, decision=trained_model")
                sys.stderr.flush()
            elif (trained_emotion == 'grief'
                  and trained_confidence >= GRIEF_PRESERVATION_THRESHOLD
                  and grief_margin >= GRIEF_PRESERVATION_MARGIN):
                # The semantic fallback has no grief class. Preserve only a
                # bounded, top-class grief signal and mark it uncertain; do
                # not preserve the weakest or ambiguous grief predictions.
                emotion = trained_emotion
                emotion_confidence = trained_confidence
                emotion_source = 'trained_model_low_confidence_grief'
                sys.stderr.write(f"decision=trained_model_low_confidence_grief, preservation_threshold={GRIEF_PRESERVATION_THRESHOLD}, margin={grief_margin:.4f}\n")
                print(f"[EMOTION_ROUTING] trained_emotion={trained_emotion}, trained_confidence={trained_confidence:.4f}, threshold={GRIEF_PRESERVATION_THRESHOLD}, margin={grief_margin:.4f}, decision=trained_model_low_confidence_grief")
                sys.stderr.flush()
            else:
                # Only explicit semantic evidence can override an uncertain ML
                # prediction. Normalized heuristic scores alone are not proof.
                semantic_emotion = (semantic_result or {}).get('emotion', {})
                supported = semantic_emotion.get('supported_labels', [])
                candidate = (unified_result.emotion.get('primary')
                             if unified_result and unified_result.emotion
                             else semantic_emotion.get('dominant'))
                candidate_key = 'happiness' if candidate == 'joy' else candidate
                if candidate_key not in supported:
                    emotion = trained_emotion
                    emotion_confidence = trained_confidence
                    emotion_source = 'trained_model_low_confidence'
                sys.stderr.write(f"decision={emotion_source or 'semantic_fallback'}\n")
                sys.stderr.flush()
        
        # If trained model was unavailable OR confidence was too low, use semantic analysis
        if emotion is None:
            # Fallback to UNIFIED ANALYSIS
            if unified_result and unified_result.emotion:
                emotion = unified_result.emotion['primary']
                emotion_confidence = unified_result.emotion['confidence']
                emotion_source = 'semantic_fallback'
                print(f"[EMOTION_FALLBACK] Using unified analysis: {emotion} ({emotion_confidence:.0%})")
                sys.stderr.write(f"[EMOTION_FALLBACK] Using unified analysis: {emotion} ({emotion_confidence:.0%})\n")
                sys.stderr.flush()
            
            # Fallback to SEMANTIC ANALYSIS if unified not available
            elif semantic_result:
                emotion = semantic_result.get('emotion', {}).get('dominant')
                emotion_confidence = semantic_result.get('emotion', {}).get('confidence', 0.7)
                emotion_source = 'semantic_fallback'
                print(f"[EMOTION_FALLBACK] Using semantic analysis: {emotion} ({emotion_confidence:.0%})")
                sys.stderr.write(f"[EMOTION_FALLBACK] Using semantic analysis: {emotion} ({emotion_confidence:.0%})\n")
                sys.stderr.flush()
            
            # Fallback to KEYWORD matching if all else fails
            elif not emotion:
                emotion = detect_emotion_fallback(message)
                emotion_source = 'keyword_fallback'
                print(f"[EMOTION_FALLBACK] Using keyword fallback: {emotion}")
                sys.stderr.write(f"[EMOTION_FALLBACK] Using keyword fallback: {emotion}\n")
                sys.stderr.flush()
        
        # FINAL EMOTION LOG with source
        emotion = str(emotion)
        sys.stderr.write(f"[FINAL_EMOTION] emotion={emotion}, confidence={emotion_confidence:.4f}, source={emotion_source}\n")
        sys.stderr.flush()
        print(f"[EMOTION_FINAL] {emotion} ({emotion_confidence:.0%}) from {emotion_source}")
        
        # Ensure confidence is between 0 and 1
        if emotion_confidence > 1:
            emotion_confidence = emotion_confidence / 100.0
        
        print(f"[EMOTION_BEFORE_CONTEXT] {emotion} at {emotion_confidence:.2%}")
        
        # APPLY CONTEXT-AWARE CLASSIFICATION
        # This provides additional context but should NOT override emotion
        if context_result:
            print(f"[CONTEXT_APPLICATION] Interaction type: {context_result['interaction_type']}")
            print(f"[CONTEXT_NOTE] Context provides conversational style, emotion is separate")
            if context_result['user_correction']:
                print(f"[USER_CORRECTION] User corrected interpretation - treating as playful clarification")
                # User is just correcting tone, don't force neutral emotion
                # Let the emotion from semantic analysis stand
        
        print(f"[EMOTION_FINAL] {emotion} at {emotion_confidence:.2%}")
        
        stage_timings['emotion_ms'] = round((time.perf_counter() - emotion_stage_started) * 1000, 2)
        # Any heuristic route is inherently uncertain, even when normalized
        # keyword scores happen to sum to 1.0. That number is not a calibrated
        # model probability and must never be presented as certainty.
        is_trained_emotion_source = emotion_source.startswith('trained_model')
        emotion_uncertain = emotion_source != 'trained_model'
        confidence_type = 'model_softmax_max' if is_trained_emotion_source else (
            'keyword_heuristic_score' if emotion_source == 'keyword_fallback' else 'semantic_heuristic_score'
        )

        # Initialize ml_system default value
        ml_system = 'unknown'
        response_source = 'unknown'
        model_used = None
        fallback_reason = None
        
        # TRACE: Log _smart_gen runtime value and type
        sys.stderr.write(f"[_SMART_GEN] value={repr(_smart_gen)}, type={type(_smart_gen).__name__}\n")
        sys.stderr.flush()
        
        # TRACE: Get HybridResponseEngine instance ID if available
        try:
            from hybrid_response_engine import _hybrid_engine
            if _hybrid_engine:
                sys.stderr.write(f"[HYBRID_ENGINE] Instance ID: {id(_hybrid_engine)}\n")
                sys.stderr.flush()
        except:
            pass
        
        generation_stage_started = time.perf_counter()
        # Generate response using HYBRID system (Trained Model + LLM + Memory)
        if _smart_gen == "hybrid":
            # Using hybrid: trained emotion model + LLM response generation + long-term memory
            print(f"[GENERATE] Using HYBRID SYSTEM (Trained Model + LLM + Memory)")
            
            try:
                from hybrid_response_engine import generate_hybrid_response, _hybrid_engine
                
                # Prepare memory context for LLM
                memory_context_text = None
                if memory_context:
                    memory_context_text = memory_context.get('context_text', None)
                if session_context:
                    session_layer = (
                        '[CURRENT SESSION CONTINUITY - bounded excerpts; not verified facts]\n'
                        + session_context
                    )
                    memory_context_text = '\n\n'.join(
                        part for part in [memory_context_text, session_layer] if part
                    )
                
                # Pass semantic_result and context_result to hybrid engine
                # These provide better context for response generation
                # NOTE: ollama_understanding is now None (refactored into single call)
                # Set correlation ID on hybrid engine for use in Ollama call
                if _hybrid_engine:
                    _hybrid_engine._correlation_id = CORRELATION_ID
                
                hybrid_result = generate_hybrid_response(
                    message,
                    emotion,
                    emotion_confidence,
                    conversation_history,
                    memory_context_text,
                    semantic_result=semantic_result,    # ÃƒÂ¢Ã¢â‚¬Â Ã‚Â Full semantic analysis
                    context_result=context_result,      # ÃƒÂ¢Ã¢â‚¬Â Ã‚Â Context classification
                    unified_result=unified_result,      # ÃƒÂ¢Ã¢â‚¬Â Ã‚Â NEW: Unified analysis consolidation
                    ollama_understanding=None,           # REFACTORED: Now None (single call only)
                    counselor_context=counselor_context,
                    emotion_response_context=emotion_response_context,
                )
                
                queue_code = hybrid_result.get('error_code')
                if queue_code in {'GPU_QUEUE_BUSY', 'GPU_REQUEST_IN_PROGRESS'}:
                    status = 409 if queue_code == 'GPU_REQUEST_IN_PROGRESS' else 429
                    return jsonify({
                        'success': False,
                        'code': queue_code,
                        'error': hybrid_result.get('error', 'The local AI is busy. Please retry shortly.'),
                        'retry_after_seconds': hybrid_result.get('retry_after_seconds', 5),
                    }), status

                # Check if hybrid actually succeeded or fell back
                ml_system = hybrid_result.get('ml_system', 'unknown')
                response_source = hybrid_result.get('response_source')
                if not response_source:
                    response_source = 'gemma' if ml_system == 'hybrid_active' else 'fallback'
                model_used = hybrid_result.get('model_used') if ml_system == 'hybrid_active' else None
                if ml_system == 'hybrid_active' and not model_used:
                    model_used = 'gemma3:4b'
                fallback_reason = getattr(_hybrid_engine, '_last_fallback_reason', None)
                
                if ml_system == 'hybrid_active':
                    response = hybrid_result.get('response', 'I am here to listen.')
                    analysis = hybrid_result.get('analysis', {})
                    emotion_confidence = hybrid_result.get('confidence', emotion_confidence)
                    print(f"[HYBRID_SUCCESS] Generated using trained model + LLM + memory context")
                elif ml_system == 'hybrid_fallback':
                    response = hybrid_result.get('response', 'I am here to listen.')
                    analysis = hybrid_result.get('analysis', {})
                    emotion_confidence = hybrid_result.get('confidence', emotion_confidence)
                    print(f"[DEBUG5] Using TEMPLATE FALLBACK (Ollama timeout or unavailable)")
                else:
                    # Fallback if hybrid fails completely
                    response = "I'm here to listen and support you."
                    analysis = {'emotion': emotion, 'crisis_level': 'none', 'model_type': 'hybrid_fallback'}
                    print(f"[HYBRID_FALLBACK] Hybrid failed, using fallback")
            except Exception as e:
                print(f"[HYBRID_ERROR] {e}")
                response = f"I'm here to listen and support you."
                analysis = {'emotion': emotion, 'crisis_level': 'none', 'model_type': 'error_fallback'}
                response_source = 'error_fallback'
                fallback_reason = 'hybrid_exception'
        elif _smart_gen == "llm":
            # LLM only fallback
            print(f"[GENERATE] Using LLM only (no trained model)")
            ml_system = 'llm'
            response_source = 'llm'
            model_used = 'ollama'
            try:
                from llm_response_generator import generate_llm_response
                llm_result = generate_llm_response(message, conversation_history, emotion)
                response = llm_result.get('response', 'I am here to listen.')
                analysis = llm_result.get('analysis', {})
            except Exception as e:
                print(f"[LLM_ERROR] {e}")
                response = f"I'm here to listen and support you."
                analysis = {'emotion': emotion, 'crisis_level': 'none', 'model_type': 'fallback'}
                response_source = 'llm_fallback'
                fallback_reason = 'llm_exception'
        elif _smart_gen:
            # Template-based fallback (not ideal but better than nothing)
            print(f"[GENERATE] Using template-based fallback")
            ml_system = 'template'
            response_source = 'template_fallback'
            fallback_reason = 'template_generator'
            response = _smart_gen.generate_response(
                user_text=message,
                emotion=emotion,
                contexts={},
                conversation_history=[],
                laravel_crisis_level=laravel_crisis_level  # TASK 6: Pass Laravel's assessment to prevent crisis override
            )
            analysis = {'emotion': emotion, 'crisis_level': 'none', 'model_type': 'template_fallback'}
        else:
            print(f"[GENERATE] No generator available")
            ml_system = 'fallback'
            response_source = 'fallback'
            fallback_reason = 'no_generator'
            response = f"Thank you for sharing. I'm here to listen."
            analysis = {'emotion': emotion, 'crisis_level': 'none', 'model_type': 'no_generator'}
        
        print(f"[RESPONSE] Generated response ({len(response)} chars)")
        print(f"[DEBUG3] Final emotion being returned in JSON: {emotion}")
        print(f"[DEBUG4] Response generator type: {type(_smart_gen).__name__} (system={_smart_gen})")
        
        # FIX: Log based on actual ml_system value, not generator type
        if ml_system == 'hybrid_active':
            print(f"[DEBUG5] Using HYBRID SYSTEM (Ollama-generated response)")
        elif ml_system == 'hybrid_fallback':
            print(f"[DEBUG5] Using FALLBACK RESPONSE (Ollama unavailable or timeout)")
        else:
            print(f"[DEBUG5] Using UNKNOWN SYSTEM ({ml_system})")
        
        # Gate 3: validate every generated path, including template/LLM fallbacks.
        # This is output-only; Laravel remains authoritative for crisis decisions.
        language_hint = 'en'
        try:
            if _smart_gen == 'hybrid':
                from hybrid_response_engine import _hybrid_engine
                if _hybrid_engine:
                    language_hint = _hybrid_engine.detect_message_language(message).get('detected_language', 'en')
        except Exception:
            language_hint = 'en'
        response, guard_reason = enforce_non_diagnostic_output(response, language_hint, crisis_level)
        if guard_reason != 'accepted':
            print(f'[NON_DIAGNOSTIC_GUARD] Replaced generated output: {guard_reason}')

        stage_timings['generation_ms'] = round((time.perf_counter() - generation_stage_started) * 1000, 2)

        # Check if counselor suggestion is appropriate
        try:
            from counselor_suggestion_engine import analyze_for_counselor_suggestion
            suggestion_data = analyze_for_counselor_suggestion(
                message,
                emotion,
                crisis_level,
                conversation_history
            )
        except Exception as e:
            print(f"[WARNING] Counselor suggestion engine error: {e}")
            suggestion_data = {
                'should_suggest': False,
                'reason': '',
                'severity': 0,
                'suggestion_message': None,
                'connection_prompt': None,
            }
        
        # TASK 9.1: FUSION SHADOW DIAGNOSTIC ANALYSIS
        # Reuse all existing signals - do NOT make redundant predictions
        fusion_shadow_result = None
        if _fusion_shadow_enabled and _fusion_service:
            try:
                fusion_start = time.time()
                
                # Get VADER scores (already computed in semantic layer)
                vader_scores = None
                if semantic_result:
                    vader_scores = semantic_result.get('vader_scores', {})
                
                if not vader_scores:
                    # Fallback: compute VADER only if not already available
                    try:
                        from nltk.sentiment import SentimentIntensityAnalyzer
                        sia = SentimentIntensityAnalyzer()
                        vader_scores = sia.polarity_scores(message)
                    except:
                        vader_scores = {'compound': 0.0, 'pos': 0.0, 'neg': 0.0, 'neu': 1.0}
                
                # Reuse the primary trained prediction; do not run the classifier again.
                trained_prediction = trained_result
                
                # Build signals dict reusing existing results
                signals_for_fusion = {
                    'semantic': {
                        'emotion': semantic_result.get('emotion', {}).get('dominant', 'neutral') if semantic_result else 'neutral',
                        'confidence': semantic_result.get('emotion', {}).get('confidence', 0.0) if semantic_result else 0.0,
                        'negation_detected': semantic_result.get('negation_detected', False) if semantic_result else False,
                    },
                    'unified': {
                        'emotion': unified_result.emotion.get('primary', 'neutral') if unified_result else 'neutral',
                        'confidence': unified_result.emotion.get('confidence', 0.0) if unified_result else 0.0,
                        'language': unified_result.language.get('primary', 'english') if unified_result else 'english',
                        'negation_detected': unified_result.negation.get('detected', False) if unified_result else False,
                        'sarcasm_detected': unified_result.sarcasm.get('detected', False) if unified_result else False,
                    },
                    'trained': {
                        'emotion': trained_prediction.get('emotion', 'neutral') if trained_prediction else 'neutral',
                        'confidence': trained_prediction.get('confidence', 0.0) if trained_prediction else 0.0,
                        'probabilities': trained_prediction.get('probabilities', {}) if trained_prediction else {},
                    },
                    'vader': vader_scores,
                }
                
                # Fuse signals using the validated fusion service
                fusion_result = _fusion_service.fuse(message, signals_for_fusion, conversation_history)
                
                fusion_time = (time.time() - fusion_start) * 1000  # milliseconds
                
                # Build shadow result with diagnostic fields
                fusion_shadow_result = {
                    'fusion_shadow_emotion': fusion_result.final_emotion,
                    'fusion_shadow_confidence': float(fusion_result.confidence),
                    'fusion_shadow_reason': fusion_result.reason if hasattr(fusion_result, 'reason') else '',
                    'fusion_shadow_uncertain': fusion_result.uncertain_flag if hasattr(fusion_result, 'uncertain_flag') else False,
                    'fusion_shadow_agreement': fusion_result.agreement_pattern if hasattr(fusion_result, 'agreement_pattern') else 'unknown',
                    # Compact source summary
                    'fusion_shadow_sources': {
                        'semantic': signals_for_fusion['semantic'].get('emotion', 'unknown'),
                        'unified': signals_for_fusion['unified'].get('emotion', 'unknown'),
                        'trained': signals_for_fusion['trained'].get('emotion', 'unknown'),
                    },
                    'fusion_shadow_computation_ms': float(fusion_time),
                }
                
                print(f"[FUSION_SHADOW] Analysis complete: {fusion_result.final_emotion} "
                      f"(confidence={fusion_result.confidence:.2f}, time={fusion_time:.1f}ms)")
                
            except Exception as e:
                # Fusion shadow is non-blocking diagnostic - don't fail the user request
                print(f"[FUSION_SHADOW] Error (non-fatal): {e}")
                fusion_shadow_result = {
                    'fusion_shadow_error': str(e),
                    'fusion_shadow_emotion': None,
                }
        
        taxonomy_metadata = emotion_taxonomy_metadata(emotion)

        response_dict = {
            'success': True,
            'emotion': emotion,
            'response': response,
            'ml_system': ml_system,  # Track which system generated the response
            'response_source': response_source,
            'confidence': float(emotion_confidence) if emotion_confidence else 0.7,
            'emotion_source': emotion_source,  # NEW: Track where emotion came from
            'confidence_type': confidence_type,
            'confidence_is_calibrated': False,
            'uncertain': emotion_uncertain,
            # Additive reporting metadata; unknown labels remain unclassified.
            'emotion_taxonomy': taxonomy_metadata,
            'analysis': {
                'emotion': emotion,
                'confidence': float(emotion_confidence) if emotion_confidence else 0.7,
                'crisis_level': crisis_level,
                'method': emotion_source,  # NEW: Primary source of emotion classification
                'confidence_type': confidence_type,
                'confidence_is_calibrated': False,
                'severity': suggestion_data['severity'],  # Severity score 0-10
                # NEW: Include trained model probabilities if available
                'probabilities': emotion_probabilities if emotion_probabilities else {},
                'inference_time_ms': emotion_inference_time if emotion_inference_time else None,
                'uncertain': emotion_uncertain,
                'emotion_taxonomy': taxonomy_metadata,
                'generation_mode': analysis.get('generation_mode'),
                'structured_validated': analysis.get('structured_validated', False),
                'structured_step_count': analysis.get('structured_step_count'),
                # Operational queue telemetry only; it contains no prompt,
                # response, user, or persistent-memory content.
                'gpu_queue_wait_ms': analysis.get('gpu_queue_wait_ms'),
                'gpu_queue_position': analysis.get('gpu_queue_position'),
                'quality_recovery_attempted': analysis.get('quality_recovery_attempted', False),
                'quality_recovery_applied': analysis.get('quality_recovery_applied', False),
                'quality_recovery_reason': analysis.get('quality_recovery_reason'),
                'response_origin': analysis.get('response_origin', response_source),
            },
            'context_analysis': {
                'interaction_type': context_result['interaction_type'] if context_result else 'UNKNOWN',
                'context_confidence': context_result['confidence'] if context_result else 0.0,
                'context_reasoning': context_result['reasoning'] if context_result else '',
                'user_correction': context_result['user_correction'] if context_result else False,
            },
            'counselor_suggestion': {
                'should_suggest': suggestion_data['should_suggest'],
                'reason': suggestion_data['reason'],
                'message': suggestion_data['suggestion_message'],
                'connection_prompt': suggestion_data['connection_prompt'],
            },
            'counselor_lookup_outcome': counselor_context.get('lookup_outcome', 'not_requested'),
            # TASK 6: Add secondary safety signal - Flask observations without overriding Laravel
            'secondary_safety_signal': secondary_safety_signal,
            'semantic_analysis': semantic_result if semantic_result else {},
            'unified_analysis': unified_result.to_dict() if unified_result else {}
        }
        
        # TASK 9.1: Add fusion shadow diagnostic fields if enabled
        if fusion_shadow_result:
            response_dict.update(fusion_shadow_result)
        
        # TRACE END: Log final summary with correlation ID
        import sys
        print(f"\n[TRACE_END] correlation_id={CORRELATION_ID} total_ollama_calls=1 total_duration_ms={__import__('time').time()*1000:.0f}")
        sys.stderr.write(f"[TRACE_END] correlation_id={CORRELATION_ID} total_ollama_calls=1\n")
        sys.stderr.flush()
        
        # LONG-TERM MEMORY: Store this conversation for future reference
        if _memory_manager:
            try:
                extraction_result = _memory_manager.handle_message_pair(
                    user_id=user_id,
                    user_message=message,
                    ai_response=response,
                    emotion=emotion,
                    conversation_history=conversation_history
                )
                
                # Add memory info to response for transparency
                response_dict['memory'] = {
                    'memories_created': extraction_result['count'],
                    'timestamp': str(__import__('datetime').datetime.now())
                }
            except Exception as e:
                print(f"[MEMORY_STORE_ERROR] Failed to store memories: {e}")
                response_dict['memory'] = {'error': str(e)}
        
        # SHADOW MODE: Optional trained classifier diagnostics (if enabled)
        if _shadow_classifier and _shadow_classifier.enabled and trained_result:
            response_dict['trained_emotion_shadow'] = trained_result['emotion']
            response_dict['trained_emotion_confidence'] = trained_result['confidence']
            response_dict['trained_emotion_probabilities'] = trained_result.get('probabilities', {})
        
        # PHASE 3-4: Include trained model results in response for confidence routing transparency
        # This shows what the trained model predicted and why routing decision was made
        sys.stderr.write(f"[TRACE_RESPONSE_ASSEMBLY] trained_emotion={trained_emotion}, trained_confidence={trained_confidence}\n")
        sys.stderr.flush()
        
        if trained_emotion is not None and trained_confidence is not None:
            sys.stderr.write(f"[TRACE_RESPONSE_ASSEMBLY] Adding trained_emotion_primary to response\n")
            sys.stderr.flush()
            response_dict['trained_emotion_primary'] = trained_emotion
            response_dict['trained_emotion_confidence_primary'] = trained_confidence
            response_dict['trained_emotion_probabilities_primary'] = emotion_probabilities if emotion_probabilities else {}
            response_dict['confidence_threshold'] = 0.50  # Document the threshold used
            response_dict['grief_preservation_threshold'] = 0.35
            response_dict['grief_preservation_margin'] = 0.10
            response_dict['routing_threshold_check'] = {
                'trained_confidence': trained_confidence,
                'threshold': 0.50,
                'grief_preservation_threshold': 0.35,
                'grief_preservation_margin': 0.10,
                'meets_threshold': trained_confidence >= 0.50,
                'routed_to': (
                    'trained_model' if emotion_source == 'trained_model' and trained_confidence >= 0.50
                    else 'trained_model_low_confidence_grief' if emotion_source == 'trained_model_low_confidence_grief'
                    else 'trained_model_low_confidence' if emotion_source == 'trained_model_low_confidence'
                    else 'semantic_fallback'
                )
            }
        else:
            sys.stderr.write(f"[TRACE_RESPONSE_ASSEMBLY] NOT adding trained_emotion_primary because trained_emotion={trained_emotion}, trained_confidence={trained_confidence}\n")
            sys.stderr.flush()
        
        emit_diagnostic(
            'flask',
            'chat_complete',
            CORRELATION_ID,
            duration_ms=round((time.perf_counter() - request_started) * 1000, 2),
            stage_timings=stage_timings,
            history_turns_received=history_turns_received,
            history_turns_sent=int(analysis.get('history_turns_used', min(12, history_turns_received)) or 0),
            session_context_chars=len(session_context),
            response_source=response_source,
            ml_system=ml_system,
            model_used=model_used,
            fallback_reason=fallback_reason,
            emotion=emotion,
            emotion_confidence=round(float(emotion_confidence or 0), 3),
            emotion_source=emotion_source,
            emotion_confidence_type=confidence_type,
            emotion_uncertain=emotion_uncertain,
            crisis_level=crisis_level,
            generation_mode=analysis.get('generation_mode'),
            structured_validated=analysis.get('structured_validated', False),
            structured_step_count=analysis.get('structured_step_count'),
        )
        _remember_chat_response(idempotency_key, response_dict)
        return jsonify(response_dict)
    except Exception as e:
        import sys
        error_str = str(e)
        print(f"\n[ERROR] Chat error: {error_str[:100]}")
        print(f"[ERROR_TRACEBACK] Full trace:")
        traceback.print_exc()
        
        # Write error to file instead of stderr to avoid encoding issues
        try:
            with open('error.log', 'a', encoding='utf-8') as elog:
                elog.write(f"[ERROR] {error_str}\n")
        except:
            pass
        
        sys.stderr.flush()
        
        # Build error response with safe encoding
        error_response = {
            'success': False,
            'error': error_str,
            'error_type': type(e).__name__,
            'debug_note': 'Check server logs for [ERROR] messages'
        }
        
        return jsonify(error_response), 500

@app.route('/inspect_intent', methods=['POST'])
def inspect_intent():
    """
    TASK 5: Inspection endpoint for intent routing
    Returns actual intent values detected by unified_analysis for debugging
    """
    try:
        data = request.get_json()
        message = data.get('message', '').strip()
        conversation_history = data.get('conversation_history', [])
        
        if not message:
            return jsonify({'error': 'Message is required'}), 400
        
        print(f"\n[INSPECT_INTENT] Analyzing message_length={len(message)}")
        
        # Run unified analysis
        from unified_analysis import analyze_unified
        from semantic_analysis import analyze_semantic_layer
        
        # Get semantic analysis first
        semantic_result = analyze_semantic_layer(message, conversation_history)
        
        # Then unified analysis (uses semantic_result)
        unified_result = analyze_unified(
            message,
            conversation_history,
            semantic_result=semantic_result
        )
        
        # Extract all analysis data
        return jsonify({
            'message': message,
            'intent': {
                'primary': unified_result.intent['primary'],
                'confidence': float(unified_result.intent['confidence'])
            },
            'emotion': {
                'primary': unified_result.emotion['primary'],
                'secondary': unified_result.emotion['secondary'],
                'confidence': float(unified_result.emotion['confidence']),
                'source': unified_result.emotion['source'],
                'scores': unified_result.emotion['scores']
            },
            'sarcasm': {
                'detected': unified_result.sarcasm['detected'],
                'confidence': float(unified_result.sarcasm['confidence']),
                'underlying_emotion': unified_result.sarcasm['underlying_emotion'],
                'signals': unified_result.sarcasm['signals']
            },
            'crisis': {
                'safety_level': unified_result.crisis['safety_level'],
                'direct_self_harm': unified_result.crisis['direct_self_harm'],
                'passive_death_wish': unified_result.crisis['passive_death_wish'],
                'figurative_language': unified_result.crisis['figurative_language'],
                'crisis_keywords': unified_result.crisis['crisis_keywords'],
                'reasoning': unified_result.crisis['reasoning']
            },
            'context': unified_result.context,
            'fusion_log': unified_result.fusion_log
        })
    
    except Exception as e:
        print(f"[INSPECT_INTENT_ERROR] {e}")
        import traceback
        traceback.print_exc()
        
        return jsonify({
            'error': str(e),
            'error_type': type(e).__name__
        }), 500

@app.route('/inspect_prompt', methods=['POST'])
def inspect_prompt():
    """
    TASK 6: Inspection endpoint for final prompt sent to Ollama
    Returns the sanitized prompt to verify intent routing is working
    """
    try:
        data = request.get_json()
        message = data.get('message', '').strip()
        conversation_history = data.get('conversation_history', [])
        message_type = data.get('message_type', 'unknown')
        
        if not message:
            return jsonify({'error': 'Message is required'}), 400
        
        print(f"\n[INSPECT_PROMPT] Analyzing message_length={len(message)} (type: {message_type})")
        
        # Run full analysis pipeline
        from unified_analysis import analyze_unified
        from semantic_analysis import analyze_semantic_layer
        
        # Get semantic analysis first
        semantic_result = analyze_semantic_layer(message, conversation_history)
        
        # Then unified analysis
        unified_result = analyze_unified(
            message,
            conversation_history,
            semantic_result=semantic_result
        )
        
        # Now call hybrid engine's _build_prompt method to see the actual prompt
        from hybrid_response_engine import HybridResponseEngine
        
        engine = HybridResponseEngine()
        
        # Build the prompt (this is what would be sent to Ollama)
        # We'll capture the prompt by calling _build_prompt directly
        intent_primary = unified_result.intent['primary']
        emotion_primary = unified_result.emotion['primary']
        emotion_confidence = unified_result.emotion['confidence']
        detected_sarcasm = unified_result.sarcasm['detected']
        
        print(f"[INSPECT_PROMPT] Intent: {intent_primary}, Emotion: {emotion_primary}, Sarcasm: {detected_sarcasm}")
        
        # Build the prompt (this is the same code called in generate())
        prompt = engine._build_prompt(
            message=message,
            emotion=emotion_primary,
            confidence=emotion_confidence,
            history=conversation_history,
            memory_context=None,
            detected_sarcasm=detected_sarcasm,
            semantic_result=semantic_result,
            context_result=None,
            unified_result=unified_result,
            ollama_understanding=None
        )
        
        # Sanitize prompt (remove any potential secrets)
        sanitized_prompt = prompt
        # Remove user_id if present
        sanitized_prompt = sanitized_prompt.replace('user_id=', 'user_id=[redacted]')
        sanitized_prompt = sanitized_prompt.replace('user id=', 'user_id=[redacted]')
        
        # Check for key routing instructions in the prompt
        prompt_lower = prompt.lower()
        routing_features = {
            'has_direct_answer_instruction': 'answer' in prompt_lower and 'direct' in prompt_lower,
            'has_validate_instruction': 'validat' in prompt_lower and 'feel' in prompt_lower,
            'has_crisis_path': 'crisis' in prompt_lower or 'safety' in prompt_lower,
            'has_support_first': 'support' in prompt_lower or 'validat' in prompt_lower,
            'mentions_intent': 'intent' in prompt_lower or 'question' in prompt_lower,
            'has_sarcasm_handling': 'sarcasm' in prompt_lower or 'underlying' in prompt_lower,
        }
        
        return jsonify({
            'message': message,
            'message_type': message_type,
            'analysis': {
                'intent': intent_primary,
                'emotion': emotion_primary,
                'sarcasm_detected': detected_sarcasm,
                'underlying_emotion': unified_result.sarcasm.get('underlying_emotion')
            },
            'prompt': sanitized_prompt,
            'prompt_length': len(prompt),
            'routing_features': routing_features,
            'verification': {
                'passes_direct_routing': routing_features['has_direct_answer_instruction'] if 'question' in intent_primary.lower() else True,
                'passes_emotional_routing': routing_features['has_validate_instruction'] if 'statement' in intent_primary.lower() else True,
                'passes_crisis_routing': routing_features['has_crisis_path'] if emotion_primary == 'crisis' else True,
            }
        })
    
    except Exception as e:
        print(f"[INSPECT_PROMPT_ERROR] {e}")
        import traceback
        traceback.print_exc()
        
        return jsonify({
            'error': str(e),
            'error_type': type(e).__name__
        }), 500

def _get_crisis_response():
    """Crisis response with emergency hotlines"""
    return (
        "I'm really concerned about your safety. Please reach out for immediate help:\n\n"
        "[EMERGENCY] Emergency Hotlines (Philippines):\n"
        "ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ NCMH Crisis Hotline: 1553 (toll-free, 24/7)\n"
        "ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ In Touch Crisis Line: (02) 8893-7603\n"
        "ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ Emergency: 911\n\n"
        "You are not alone. Help is available now."
    )

# ============================================================================
# INITIALIZATION: Load all systems when module is imported (not just __main__)
# This ensures trained model is available for both direct use and Flask imports
# ============================================================================
print("\n" + "="*60)
print("[STARTUP] MAGIREM AI - Unified ML API with Trained Model + Long-Term Memory")
print("="*60)

# Load trained model if available
has_trained = load_trained_model()

# Load ML-based response generator (replaces hardcoded templates)
load_ml_response_generator()

# Load long-term memory system
has_memory = load_memory_system()

# Load shadow mode for trained classifier (disabled by default)
load_shadow_mode_classifier()

# Load fusion shadow service for diagnostic emotion analysis (disabled by default)
load_fusion_shadow_service()

if has_trained:
    print("[OK] System ready with TRAINED emotion model + ML Response Generator")
    print("[INFO] Both emotion detection AND response generation are learned from data!")
else:
    print("[INFO] System ready with keyword matching (fallback)")
    print("[WARN] Keyword matching can't handle negation properly")
    print("[NEXT] Train a model: python train_emotion_model.py")

if has_memory:
    print("[OK] Long-term memory system active")
    print("[INFO] AI will maintain conversational memory across sessions")
else:
    print("[WARNING] Long-term memory system unavailable - running without persistent memory")

print("="*60)

@app.route('/internal/crisis-assessment', methods=['POST'])
def internal_crisis_assessment():
    """Authenticated, loopback-only Gemma crisis classification transport."""
    from inference_bridge_auth import verify_crisis_bridge
    from gpu_inference_queue import queue as gpu_queue, InferenceQueueBusy, InferenceAlreadyQueued, InferenceQueueUnavailable
    allowed, detail, status = verify_crisis_bridge()
    if not allowed:
        return jsonify({'success': False, 'code': detail}), status
    data = request.get_json(silent=True) or {}
    prompt = str(data.get('prompt', ''))
    model = str(data.get('model', 'gemma3:4b'))
    if str(data.get('request_id', '')) != detail:
        return jsonify({'success': False, 'code': 'request_id_mismatch'}), 400
    if not prompt or len(prompt) > 24000 or model != 'gemma3:4b':
        return jsonify({'success': False, 'code': 'invalid_crisis_request'}), 400
    try:
        with gpu_queue.acquire(detail, priority='crisis') as admission:
            response = requests.post('http://127.0.0.1:11434/api/generate', json={
                'model': model, 'prompt': prompt, 'stream': False,
                'options': {'temperature': float(data.get('temperature', 0.3))},
            }, timeout=(5, min(100, max(10, int(os.getenv('MINDCARE_CRISIS_OLLAMA_READ_TIMEOUT', '90'))))))
            if response.status_code != 200:
                return jsonify({'success': False, 'code': 'ollama_unavailable'}), 503
            content = response.json().get('response', '')
            if not isinstance(content, str) or not content.strip():
                return jsonify({'success': False, 'code': 'invalid_model_response'}), 503
            return jsonify({'success': True, 'response': content, 'queue_wait_ms': admission.wait_ms})
    except InferenceAlreadyQueued:
        return jsonify({'success': False, 'code': 'GPU_REQUEST_IN_PROGRESS'}), 409
    except InferenceQueueBusy:
        return jsonify({'success': False, 'code': 'GPU_QUEUE_BUSY'}), 429
    except InferenceQueueUnavailable:
        return jsonify({'success': False, 'code': 'GPU_QUEUE_UNAVAILABLE'}), 503
    except requests.RequestException:
        return jsonify({'success': False, 'code': 'ollama_unavailable'}), 503

@app.route('/internal/magi-resource-recommendations', methods=['POST'])
def internal_magi_resource_recommendations():
    """Authenticated local resource selection; never uses the chat or memory path."""
    from inference_bridge_auth import verify_crisis_bridge
    from gpu_inference_queue import queue as gpu_queue, InferenceQueueBusy, InferenceAlreadyQueued, InferenceQueueUnavailable
    from magi_resource_recommendations import generate, RecommendationError
    allowed, detail, status = verify_crisis_bridge('/internal/magi-resource-recommendations')
    if not allowed:
        return jsonify({'success': False, 'code': detail}), status
    data = request.get_json(silent=True)
    if not isinstance(data, dict) or data.get('request_id') != detail:
        return jsonify({'success': False, 'code': 'invalid_request'}), 400
    try:
        with gpu_queue.acquire(detail, priority='normal'):
            result = generate(data.get('messages'), data.get('resources'))
        return jsonify({'success': True, 'recommendations': result})
    except (InferenceQueueBusy, InferenceAlreadyQueued):
        return jsonify({'success': False, 'code': 'GPU_QUEUE_BUSY'}), 429
    except (InferenceQueueUnavailable, RecommendationError):
        return jsonify({'success': False, 'code': 'recommendations_unavailable'}), 503


@app.route('/internal/detailed-emotion-shadow', methods=['POST'])
def internal_detailed_emotion_shadow():
    """Consent-gated, non-persisting 35-label shadow assessment.

    This route is disabled unless explicitly enabled after an approved final
    evaluation. It never participates in normal chat, crisis routing, or Admin
    analytics and is protected by the same loopback/HMAC bridge as crisis work.
    """
    if (
        os.getenv('MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED', 'false').lower() != 'true'
        or os.getenv('MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED', 'false').lower() != 'true'
    ):
        return jsonify({'success': False, 'code': 'DETAILED_SHADOW_DISABLED'}), 404
    from inference_bridge_auth import verify_crisis_bridge
    from detailed_emotion_shadow import DetailedEmotionShadowInterpreter, DetailedShadowUnavailable
    allowed, detail, status = verify_crisis_bridge('/internal/detailed-emotion-shadow')
    if not allowed:
        return jsonify({'success': False, 'code': detail}), status
    data = request.get_json(silent=True) or {}
    if str(data.get('request_id', '')) != detail:
        return jsonify({'success': False, 'code': 'request_id_mismatch'}), 400
    if data.get('consent_granted') is not True or data.get('consent_purpose') != 'emotion_shadow_evaluation':
        return jsonify({'success': False, 'code': 'shadow_consent_required'}), 403
    try:
        revision = int(data.get('consent_revision', 0))
    except (TypeError, ValueError):
        revision = 0
    if revision < 1 or not str(data.get('user_id', '')).strip():
        return jsonify({'success': False, 'code': 'shadow_consent_metadata_required'}), 403
    try:
        result = DetailedEmotionShadowInterpreter().assess(
            str(data.get('message', '')),
            consent_granted=True,
            request_id=detail,
        )
        return jsonify({'success': True, 'shadow': result, 'production_classifier_unchanged': True})
    except DetailedShadowUnavailable as exc:
        code = str(exc) or 'DETAILED_SHADOW_UNAVAILABLE'
        response_status = 429 if code in {'GPU_QUEUE_BUSY', 'GPU_REQUEST_IN_PROGRESS'} else 503
        return jsonify({'success': False, 'code': code, 'shadow_only': True}), response_status
# Flask development server launcher (if run directly)
if __name__ == '__main__':
    # Keep the direct development entry point aligned with the secure wrapper
    # and Laravel's process-scoped endpoint configuration.  The ordinary
    # development default remains port 5000, while a secure local run can set
    # MINDCARE_PHASE4A_PORT (for example 5018) without silently starting on a
    # different port than Laravel targets.
    flask_port = int(os.getenv('MINDCARE_PHASE4A_PORT', '5000'))
    app.run(host='127.0.0.1', port=flask_port, debug=False, threaded=True)
