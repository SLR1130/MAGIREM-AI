"""
Hybrid Response Engine - Trained ML Model + LLM working together
Uses your trained RoBERTa emotion model to inform LLM responses
NO TEMPLATES - pure learned intelligence
"""

import requests
import json
import re
import os
import threading
from typing import Optional, Dict, List
from response_formatter import format_for_display
from non_diagnostic_safety import validate_non_diagnostic_output, safe_non_diagnostic_fallback
from diagnostics import emit_diagnostic, elapsed_ms
from gpu_inference_queue import queue as gpu_inference_queue, InferenceAlreadyQueued, InferenceQueueBusy
from deterministic_math import calculate_budget_context, food_budget_caution_response, numeric_context_for_prompt, numeric_fallback_response, _summary_requested


def _env_int(name: str, default: int, minimum: int, maximum: int) -> int:
    try:
        return max(minimum, min(maximum, int(os.getenv(name, str(default)))))
    except (TypeError, ValueError):
        return default


def _env_float(name: str, default: float, minimum: float, maximum: float) -> float:
    try:
        return max(minimum, min(maximum, float(os.getenv(name, str(default)))))
    except (TypeError, ValueError):
        return default
class HybridResponseEngine:
    @property
    def _correlation_id(self):
        return getattr(self._correlation_local, 'value', None)

    @_correlation_id.setter
    def _correlation_id(self, value):
        self._correlation_local.value = value

    """
    Combines:
    1. Your trained emotion detection (RoBERTa)
    2. LLM response generation (Ollama) - informed by emotion + context
    3. Conversation history for context-awareness
    
    Result: Natural conversations powered by your training data
    """
    
    def __init__(self, ollama_url="http://localhost:11434", model="gemma3:4b"):
        """Initialize hybrid engine with LLM backend"""
        self.ollama_url = ollama_url
        self.model = model
        self.api_endpoint = f"{ollama_url}/api/generate"
        self._correlation_local = threading.local()
        self._correlation_id = None
        # Explicit process-level bounds keep local generation reliable without
        # treating ordinary answers as artificially short.
        self.connect_timeout = _env_float('MINDCARE_OLLAMA_CONNECT_TIMEOUT', 5.0, 1.0, 30.0)
        # Leave room inside Laravel's 180-second request budget for bounded queue waiting.
        self.read_timeout = _env_float('MINDCARE_OLLAMA_READ_TIMEOUT', 150.0, 10.0, 170.0)
        self.keep_alive = os.getenv('MINDCARE_OLLAMA_KEEP_ALIVE', '10m').strip() or '10m'
        self.history_turn_limit = _env_int('MINDCARE_OLLAMA_HISTORY_TURNS', 12, 1, 24)
        self.prompt_history_turns = _env_int('MINDCARE_OLLAMA_PROMPT_HISTORY_TURNS', 6, 1, 12)
        self.history_char_budget = _env_int('MINDCARE_OLLAMA_HISTORY_CHAR_BUDGET', 9000, 1000, 24000)
        self.context_window = _env_int('MINDCARE_OLLAMA_CONTEXT_WINDOW', 4096, 1024, 16384)
        self.default_max_tokens = _env_int('MINDCARE_OLLAMA_MAX_TOKENS', 512, 128, 2048)
        self.detailed_max_tokens = _env_int('MINDCARE_OLLAMA_DETAILED_MAX_TOKENS', 1536, 256, 2048)
        self.practical_max_tokens = _env_int('MINDCARE_OLLAMA_PRACTICAL_MAX_TOKENS', 480, 256, 1024)
        self._last_gpu_queue_wait_ms = 0
        self._last_gpu_queue_position = 0
        
        # Print module info for diagnostics
        print(f"[HYBRID_ENGINE] Initialized from: {__file__}")
        print(f"[HYBRID_ENGINE] Instance ID: {id(self)}")
        
        # System prompt for therapeutic response
        # NOTE: Using only ASCII characters (no en-dashes/em-dashes) for Windows compatibility
        self.system_prompt = """You are MAGI, the AI wellness companion inside MAGIREM AI, a warm and professional student wellness support platform.

CONVERSATIONAL PRINCIPLES:
- Sound like a real person having a real conversation, not like a template or article
- Match the user's established language, tone, and formality without imitating slang or pet names
- Respond to what they actually said - not generic advice they didn't ask for
- Validate before suggesting - acknowledge the feeling first
- Be authentic and genuine - short responses for simple questions, substantive responses for complex problems
- Avoid generic filler and repeated empathy statements
- Do not reuse the same opening or closing wording from the previous assistant turn
- If the user is answering a prior question or accepting an offer, follow through on that context
- Short paragraphs with breathing room (1-3 sentences each)
- Let normal conversation stay normal conversation

UNDERSTANDING USER EMOTION:
- Sentiment analysis (VADER) provides numerical signals: compound score (-1 to +1), positive/neutral/negative
- These are SUPPORTING SIGNALS, not constraints for your response
- A message with negative compound score (-0.401) usually indicates stress or concern
- Look at ACTUAL CONTENT and CONTEXT first - don't override with numbers
- Example: "I'm really stressed and I don't know how to manage" = user is stressed, even if algorithm seems confused

SARCASM & IRONY:
- If user surface emotion seems positive but context indicates struggle:
  * A cheerful-sounding sentence can still contain stress; follow the surrounding facts instead of copying slang
  * "I'm fine, everything is great, I'm not falling apart" = user may be overwhelmed
- Respond to the UNDERLYING EMOTION (stress, frustration, overwhelm), not surface words
- Don't use generic apologies like "I'm sorry to hear that" for sarcasm - acknowledge the irony directly
- Example GOOD response to sarcasm: "I hear the irony - those deadlines are really crushing it. Which one's worst?"
- Example BAD response: "I'm sorry to hear that" (misses the actual emotional content)

WHEN TO USE STRUCTURE:
- Lists: Only when giving step-by-step instructions or comparing 3+ distinct options
- Numbered: Only for sequence/priority (1 first, 2 second, etc)
- Bullets: Only for unordered options or multiple similar items
- Default: Natural conversation without forced lists
- IMPORTANT: Never force a specific number of items (don't force "3 tips" if user didn't ask for 3)
- If user asks "any tips?", provide tips naturally - could be 2, 3, 4, or conversational flow, NOT forced "3"
ADAPTIVE RESPONSE DEPTH:
- Casual greeting or small talk: one short, natural paragraph.
- Emotional disclosure: acknowledge the specific situation, then offer one useful next step; ask a question only if it genuinely helps.
- Practical advice: give the first actionable answer immediately and explain briefly why each step helps.
- Detailed explanation, planning, or a request for a stated number of steps: give the requested depth and use clear headings, numbered steps, or bullets when they make the answer easier to follow. Put each paragraph, heading, and list item on its own line, with a blank line between sections; never run list items or their explanations together. If the user explicitly asks for a heading, include at least one Markdown heading line beginning with `## `; do not add headings to ordinary emotional conversation.
- Do not use a vague clarification question when the user has already supplied enough context to begin helping.
- Preserve the user's established language and response style.

TONE & STYLE:
- Warm and present, not corporate or distant
- Real concern, not canned empathy
- Professional but conversational - the way friends actually talk
- Match their energy level without being fake
- Light humor okay IF the situation permits
- Serious topics stay serious (no forced positivity during crisis)

EMOJI:
- Zero to one max per response
- Only if it adds genuine warmth (not forced)
- Never during crisis, trauma, or severe distress
- Skip if the tone is heavy

NEVER DO THIS:
- Don't start with "I understand how you feel" (show it, don't say it)
- Don't generate numbered lists unless they're actually steps or priorities
- Don't ask generic follow-ups like "How can I help?" (just help)
- Don't repeat their situation back to them
- Don't give advice they didn't ask for
- Don't invent details (school, work, relationships they didn't mention)
- Don't be overly cheerful when they're struggling
- Don't force a specific number of suggestions or tips
- Do not diagnose depression, anxiety, or any other mental-health condition
- Never present an emotion, wellbeing, risk, or confidence score as a diagnosis
- When context is ambiguous, say what is uncertain and ask one clarifying question
- For financial or academic stress, name the concrete issue the user mentioned before offering options
- Recommend a counselor or other professional as an optional next step when useful; do not claim that anyone was contacted or assigned
- Keep crisis safety handling independent from ordinary emotion labels and do not claim that an alert or appointment was created

EXAMPLE - Bad Response (template):
"I'm sorry to hear that. I understand how you feel. Here are some tips for managing money:
1. Create a budget
2. Cut unnecessary expenses
3. Build an emergency fund
I hope this helps!"

EXAMPLE - Good Response (conversational, contextual):
"Yeah, that's rough. Money stress messes with everything else. Let's start simple - where is your money actually going? Usually there's a few things we can trim that don't feel too painful."

See the difference? Conversational, real, not a template."""
    
    def _post_to_ollama(self, endpoint: str, payload: Dict):
        gemini_key = os.getenv('GEMINI_API_KEY')
        if gemini_key:
            try:
                from gemini_bridge import GeminiBridge, GeminiMockResponse
                bridge = GeminiBridge(api_key=gemini_key)
                if bridge.is_available():
                    messages = payload.get('messages', [])
                    opts = payload.get('options', {})
                    temp = opts.get('temperature', 0.7)
                    max_tokens = opts.get('num_predict', 800)
                    schema = payload.get('format')
                    res = bridge.chat(messages, temperature=temp, max_tokens=max_tokens, response_schema=schema)
                    self._last_gpu_queue_wait_ms = 0
                    self._last_gpu_queue_position = 0
                    return GeminiMockResponse(res, status_code=200)
            except Exception as e:
                import sys
                sys.stderr.write(f"[GEMINI_FALLBACK_TO_OLLAMA] {e}\n")
                sys.stderr.flush()

        # The SQLite lease ledger serializes Gemma across Flask worker processes.
        request_id = str(self._correlation_id or f'engine-{id(self)}')
        with gpu_inference_queue.acquire(request_id) as admission:
            self._last_gpu_queue_wait_ms = admission.wait_ms
            self._last_gpu_queue_position = admission.queue_position
            return requests.post(
                endpoint,
                json=payload,
                timeout=(self.connect_timeout, self.read_timeout),
                stream=False,
            )

    def test_connection(self) -> bool:
        """Test if Ollama or Gemini is running"""
        if os.getenv('GEMINI_API_KEY'):
            return True
        try:
            response = requests.get(f"{self.ollama_url}/api/tags", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def _bounded_history(self, history: Optional[List[Dict]]) -> List[Dict]:
        """Preserve ordered session context without allowing an unbounded prompt."""
        if not isinstance(history, list):
            return []
        selected = history[-self.history_turn_limit:]
        kept_reversed: List[Dict] = []
        remaining = self.history_char_budget
        for exchange in reversed(selected):
            if not isinstance(exchange, dict) or remaining <= 0:
                continue
            user = str(exchange.get('user', '') or '')[:1200]
            assistant = str(exchange.get('ai', '') or '')[:1600]
            cost = len(user) + len(assistant)
            if cost > remaining:
                user = user[:min(len(user), remaining // 2)]
                assistant = assistant[:max(0, remaining - len(user))]
                cost = len(user) + len(assistant)
            if not user and not assistant:
                continue
            kept_reversed.append({
                'user': user,
                'ai': assistant,
                'emotion': str(exchange.get('emotion', 'neutral') or 'neutral'),
                'timestamp': exchange.get('timestamp', ''),
            })
            remaining -= cost
        return list(reversed(kept_reversed))

    def _response_token_budget(self, message: str, structured: bool, mode: str = '') -> int:
        """Use a compact budget unless the request genuinely needs long output."""
        text = str(message or '')
        short_request = bool(re.search(r'\b(?:short|make\s+it\s+short(?:er)?|shorten|brief(?:ly)?|summari[sz]e)\b', text, re.IGNORECASE))
        expanded_request = bool(re.search(
            r'\b(?:detail(?:ed)?|explain|plan|guide|comparison|steps?|budget|expenses?|'
            r'list|solutions?|options?|time[- ]management|schedule|manage|organize|'
            r'letter|poem|story|song|journal|write|create|scientific|rights|support\s+options?|'
            r'recommend|suggest|tips?)\b',
            text,
            re.IGNORECASE,
        ))
        if structured:
            return self.detailed_max_tokens
        if short_request:
            return min(self.default_max_tokens, 320)
        if mode == 'creative':
            return min(self.detailed_max_tokens, self.practical_max_tokens)
        if mode in {'practical', 'knowledge', 'interest_activity', 'recommendation'} or expanded_request:
            return min(self.detailed_max_tokens, self.practical_max_tokens)
        return self.default_max_tokens

    @staticmethod
    def _needs_application_identity(message: str) -> bool:
        """Include long project provenance only for questions that need it."""
        text = re.sub(r'\s+', ' ', str(message or '').strip().lower())
        return bool(re.search(
            r'\b(?:who\s+(?:made|developed|built|maintains?)|who\s+are\s+you|'
            r'what\s+is\s+(?:magirem\s+ai|minsuanscore)|who\s+created\s+(?:this|you)|'
            r'(?:google|magirem\s+ai|minsuanscore)\s+(?:developer|team|app|model))\b',
            text,
        ))
    def _request_mode(self, message: str, conversation_history: List[Dict] = None) -> str:
        """Classify the current task for response routing, without changing safety.

        This is intentionally a small lexical router. Emotion labels remain
        tone signals only, and Laravel/Flask crisis assessment remains the
        authoritative safety path.
        """
        text = re.sub(r'\s+', ' ', str(message or '').strip().lower())
        has_history = bool(conversation_history)
        # A short confirmation is relational rather than a new standalone
        # topic. Keep it attached to the immediately preceding exchange so a
        # practical task (budgeting, academics, planning, etc.) is continued
        # instead of being routed through generic emotional conversation.
        if has_history and re.fullmatch(
            r'(?:yes(?:\s+(?:that(?:\s+is|\'s|s)\s+(?:it|right)))?|yeah|yep|yup|ok|okay|sure|'
            r'alright|correct|that(?:\s+is|\'s|s)\s+(?:it|right)|go\s+ahead|continue|yes\s+please)'
            r'(?:[.!?\s]*)',
            text,
            re.IGNORECASE,
        ):
            return 'confirmation'
        if has_history and re.search(
            r'\b(?:explain\s+(?:that|this)\s+more|make\s+(?:it|that|this)\s+short(?:er)?|shorten\s+that|'
            r'give\s+me\s+more\s+solutions?|more\s+(?:ideas|options)|list\s+(?:them|those|the\s+expenses))\b',
            text,
        ):
            return 'follow_up'
        if re.search(r'\b(?:write|draft|compose|create)\b.*\b(?:letter|poem|story|song|message|journal)', text) \
                or re.search(r'\b(?:poem|short\s+story|motivational\s+letter|tula|liham|sanaysay)\b', text):
            return 'creative'
        # A student can explicitly ask to be heard without asking for advice.
        # Keep this separate from generic emotional language so the model does
        # not reflexively turn a vent into a checklist.
        if re.search(
            r'\b(?:just\s+(?:want|need)\s+to\s+(?:vent|rant|talk)|want\s+to\s+vent|need\s+someone\s+to\s+listen|'
            r'gusto\s+ko\s+lang\s+(?:mag-?rant|magkwento|maglabas|magsalita)|'
            r'kailangan\s+ko\s+lang\s+(?:ng\s+)?makikinig)\b',
            text,
        ):
            return 'venting'
        if re.search(r'\b(?:recommend|suggest|reading)\b.*\bbooks?\b|\bbooks?\b.*\b(?:recommend|suggest)\b', text):
            return 'recommendation'
        # Educational questions in Filipino and Taglish are common in the
        # student flow. They should be answered as questions, not treated as
        # generic emotional conversation merely because a classifier label exists.
        if re.search(
            r'\b(?:ano\s+(?:ang|ba\s+ang)|paano\s+(?:gumagana|nagwo-?work|nangyayari)|'
            r'ipaliwanag|explain|what\s+is|how\s+does|why\s+does|define|scientific(?:ally)?)\b',
            text,
        ):
            return 'knowledge'
        # Keep a stated hobby in view when the student asks for an activity;
        # this is a request for tailored ideas, never an assertion that a
        # preference is remembered outside the provided conversation context.
        if re.search(r'\b(?:mahilig\s+ako|hobby|hobbies|gusto\s+kong\s+(?:mag-?drawing|mag-?basa|mag-?sulat)|i\s+(?:like|love)\s+(?:drawing|reading|writing|music|gaming))\b', text) \
                and re.search(r'\b(?:anong|ano(?:ng)?\s+pwede|what|how|relax|pampakalma|pang-?relax|suggest|recommend)\b', text):
            return 'interest_activity'
        if re.search(
            r'\b(?:budget|expenses?|time[- ]management|schedule|organize|prioriti[sz]e|'
            r'plan|steps?|solutions?|options?|tips?|manage|what\s+can\s+i\s+do|how\s+can\s+i|'
            r'paano\s+(?:ko|natin|kami|ako)|pagkasyahin|review(?:hin)?|aralin|thesis|capstone|'
            r'requirements?|deadline|exam(?:s)?|pag-?aral)\b',
            text,
        ):
            return 'practical'
        if re.search(r'\b(?:sad|lonely|overwhelmed|stressed|anxious|pagod|malungkot|nahihirapan|nakakadrain|badtrip|kabado)\b', text) \
                and not re.search(r'\b(?:what|how|can|paano|ano|pwede|puwede)\b', text):
            return 'emotional'
        return 'conversation'

    def _request_mode_guidance(self, mode: str) -> str:
        """Return bounded, model-facing instructions for the selected mode."""
        return {
            'practical': (
                'REQUEST MODE: PRACTICAL ASSISTANCE. Give a usable first-pass plan or list immediately '
                'from the facts provided. State assumptions (for example, a sample allocation) as hypothetical; '
                'anchor any deadline plan to the exact timeframe stated; do not invent extra days, available hours, or numerical study blocks unless the student supplies them; '
                'prefer two to four concise steps with no generic preamble; put any optional clarifying question after the useful answer.'
            ),
            'knowledge': (
                'REQUEST MODE: KNOWLEDGE SUPPORT. Answer the question directly, explain the reasoning in plain '
                'language, distinguish general information from facts that require local verification, and explain '
                'scientific mechanisms conservatively in two short paragraphs or up to three short bullets unless the student asks for detail. Do not attribute a downstream hormone or body response directly '
                'to a brain structure when it coordinates or signals that response. Do not '
                'invent laws, services, citations, or institutional policies.'
            ),
            'recommendation': (
                'REQUEST MODE: FACTUAL RECOMMENDATION. Give no more than three concise recommendations. '
                'Never invent a title, author, availability, publication detail, or description. For study-habit books, '
                'use only these verified references when relevant: Atomic Habits by James Clear (habit formation); '
                'How to Become a Straight-A Student by Cal Newport (study strategies); A Mind for Numbers by Barbara Oakley '
                '(learning strategies). For a request outside verified context, give selection criteria and say that exact '
                'titles should be checked before recommending them.'
            ),
            'creative': (
                'REQUEST MODE: CREATIVE WRITING. Produce the requested artifact first, in the requested form and '
                'language. Avoid a generic empathy preamble or commentary about how you generated it.'
            ),
            'follow_up': (
                "REQUEST MODE: CONTEXTUAL FOLLOW-UP. Resolve references such as 'that', 'them', or 'more' from "
                'the most recent relevant exchange. Expand, shorten, or extend that answer as requested; do not '
                'restart the conversation or ask what topic the user means when the history makes it clear.'
            ),
            'confirmation': (
                'REQUEST MODE: CONTEXTUAL CONFIRMATION. Treat the current short reply as confirmation of the '
                'immediately preceding exchange. Acknowledge it and continue that same task using the stated facts; '
                'do not start a new topic, restart the conversation, or switch to mental-health/diagnostic language '
                'unless the current message itself contains a safety concern. For a practical task, give the next '
                'useful step now and label assumptions instead of inventing missing facts.'
            ),
            'emotional': (
                'REQUEST MODE: EMOTIONAL SUPPORT. Acknowledge the specific feeling or situation briefly, then '
                'offer one realistic next step. Do not turn the response into a generic checklist.'
            ),
            'venting': (
                'REQUEST MODE: VENTING / ACTIVE LISTENING. The student primarily wants to be heard. Briefly reflect '
                'the concrete situation, do not rush into a list of fixes, and invite one relevant detail only if it helps. '
                'Do not use a generic empathy opener or state the same feeling twice. '
                'Offer practical steps only when asked or when the student asks what to do next.'
            ),
            'interest_activity': (
                'REQUEST MODE: INTEREST-BASED ACTIVITY. Suggest a few specific low-pressure activities connected to '
                'the interest the student stated in this conversation. Do not claim to remember that interest beyond '
                'the provided context, and avoid generic activity lists.'
            ),
            'conversation': (
                'REQUEST MODE: NATURAL CONVERSATION. Answer the current message directly and keep the depth '
                'proportional to what was asked.'
            ),
        }.get(mode, 'REQUEST MODE: NATURAL CONVERSATION. Answer the current message directly.')

    @staticmethod
    def _verified_feature_guidance(message: str) -> str:
        """Return only route-verified, contextually relevant in-app hints.

        This is deliberately local and deterministic: it neither reads nor
        writes student data, and it never implies a booking, saved entry, or
        external availability. The named features map to existing student
        routes audited in Laravel (Journal, Coping Toolkit, Wellness Resources).
        """
        text = re.sub(r'\s+', ' ', str(message or '').strip().lower())
        if re.search(r'\b(?:journal|isulat|sulat(?:in)?|write\s+(?:down|my\s+thoughts)|organize\s+my\s+thoughts|reflection)\b', text):
            return (
                'VERIFIED IN-APP OPTION: MAGIREM AI has a private Journal. Mention it only as an optional place '
                'to write or organize thoughts; do not claim anything was saved or shared.'
            )
        if re.search(r'\b(?:grounding|breath(?:ing|e)|reset|calm(?:\s+down)?|pampakalma|overwhelmed|nakakadrain|stress(?:ed)?|pagod)\b', text):
            return (
                'VERIFIED IN-APP OPTION: MAGIREM AI has a Coping Toolkit. Mention it only if a concrete calming or '
                'reset activity would be useful; do not imply it is treatment or that the student used it.'
            )
        if re.search(r'\b(?:wellness\s+resources?|resource(?:s)?|learn\s+more|reading\s+material|materials?)\b', text):
            return (
                'VERIFIED IN-APP OPTION: MAGIREM AI has Wellness Resources. Mention it only as an optional source '
                'of general information; do not claim a particular item or external source is available.'
            )
        return ''

    def detect_message_language(self, message: str, conversation_history: List[Dict] = None) -> Dict:
        """
        Detect the language of the current message.
        
        Returns dict with:
        - detected_language: 'tl' (Tagalog), 'en' (English), 'taglish' (mixed)
        - confidence: 0.0-1.0
        - tagalog_indicators: count of Tagalog keywords
        - english_indicators: count of English keywords
        """
        text_lower = message.lower()

        # Explicit language requests take precedence over keyword heuristics.
        requested_language = None
        if re.search(r'\b(?:reply|respond|answer|speak|write)\s+(?:in\s+)?(?:tagalog|filipino)\b', text_lower) \
                or re.search(r'\b(?:sa\s+)?tagalog\b', text_lower):
            requested_language = 'tl'
        elif re.search(r'\b(?:reply|respond|answer|speak|write)\s+(?:in\s+)?english\b', text_lower):
            requested_language = 'en'
        elif re.search(r'\b(?:reply|respond|answer|speak|write)\s+in\s+taglish\b', text_lower):
            requested_language = 'taglish'
        
        # Comprehensive Tagalog keyword list
        tagalog_keywords = {
            'kumusta', 'salamat', 'medyo', 'ako', 'mahirap', 'paano', 'pwede', 'ano', 'ngayon',
            'naman', 'kasi', 'pero', 'talaga', 'nakaktulog', 'gutom', 'naapiktuhan', 'gawin',
            'maaari', 'tayo', 'kami', 'sila', 'mo', 'natin', 'nyo', 'kami', 'sayo',
            'gabi', 'umaga', 'hapon', 'kinabukasan', 'kahapon', 'ikaw', 'siya',
            'dahil', 'kung', 'para', 'yung', 'yan', 'ito', 'iyan', 'ayaw', 'gusto',
            'hindi', 'nag', 'nag-', 'noong', 'ba', 'ne', 'pa', 'na', 'man',
            'problema', 'hirap', 'kalakasan', 'sigurado', 'kaya', 'makakaya',
            'tulong', 'tumutulong', 'magtulungan', 'puwede', 'maari', 'maaaring',
            'masaya', 'masaya', 'dimalubhasa', 'espesyalista', 'kakaiba',
            'bagay', 'malaki', 'maliit', 'mahusay', 'maayos', 'mabuti', 'grabe', 'awit', 'sobrang',
            'umuwi', 'umalis', 'pumunta', 'dumating', 'nangyari', 'nangyari'
        }
        
        # Comprehensive English keyword list
        english_keywords = {
            'how', 'what', 'when', 'where', 'why', 'help', 'tips', 'manage', 'money',
            'do', 'better', 'thank', 'you', 'stress', 'struggling', 'trouble', 'problem',
            'advice', 'suggestions', 'can', 'should', 'would', 'could', 'will',
            'the', 'is', 'are', 'be', 'have', 'has', 'do', 'does', 'did',
            'my', 'your', 'their', 'his', 'her', 'our', 'its',
            'and', 'or', 'but', 'because', 'if', 'that', 'which', 'who',
            'about', 'after', 'before', 'during', 'through', 'until', 'over',
            'feeling', 'think', 'feel', 'know', 'want', 'need', 'like',
            'good', 'bad', 'happy', 'sad', 'anxious', 'angry', 'tired', 'bro', 'hassle', 'groupmates',
            'school', 'work', 'money', 'finance', 'budget', 'expense',
            'advice', 'help', 'support', 'guidance', 'suggest', 'option'
        }
        
        # Count whole-word indicators. Substring matching made English words
        # such as management look like Tagalog because they contain man.
        tokens = set(re.findall(r'[A-Za-z-]+', text_lower))
        tl_count = sum(1 for kw in tagalog_keywords if kw in tokens)
        en_count = sum(1 for kw in english_keywords if kw in tokens)
        
        # Also check for Tagalog-specific morphology patterns
        tagalog_patterns = [
            # Keep morphology conservative. Broad suffix matching classified
            # ordinary English words such as "can" and "plan" as Tagalog.
            r'\b(?:nag|mag|isa|naka|paka)-?\w+',
            r'\bka[aeiou]\w+',
        ]
        
        tl_pattern_matches = sum(1 for pattern in tagalog_patterns if re.search(pattern, text_lower))
        tl_count += tl_pattern_matches
        
        # Determine language. When the current turn is short or ambiguous,
        # retain the established language of the current conversation.
        if requested_language:
            detected = requested_language
            confidence = 0.98
        elif tl_count > 0 and en_count > 0:
            detected = 'taglish'
            confidence = min(0.95, 0.5 + (max(tl_count, en_count) / 10.0))
        elif en_count > tl_count and en_count > 0:
            detected = 'en'
            confidence = min(0.95, 0.5 + (en_count / 10.0))
        elif tl_count > 0:
            detected = 'tl'
            confidence = min(0.95, 0.5 + (tl_count / 10.0))
        else:
            previous_languages = []
            for exchange in (conversation_history or [])[-3:]:
                previous_user = exchange.get('user') if isinstance(exchange, dict) else None
                if previous_user and previous_user != message:
                    previous_languages.append(self.detect_message_language(previous_user, []).get('detected_language'))
            established = next((lang for lang in reversed(previous_languages) if lang in {'tl', 'taglish', 'en'}), None)
            detected = established or 'en'
            confidence = 0.65 if established else 0.3
        
        print(f"[LANG_DETECT] message='...' detected={detected} confidence={confidence:.0%} (tl={tl_count}, en={en_count})")
        
        return {
            'detected_language': detected,
            'confidence': confidence,
            'tagalog_indicators': tl_count,
            'english_indicators': en_count,
            'requested_language': requested_language,
            'continuity_applied': bool(not requested_language and not tl_count and not en_count and conversation_history),
        }

    def get_system_prompt_for_language(self, detected_language: str, emotion: str, confidence: float, intent_type: str = 'general', tone_emotion: str = None) -> str:
        """
        Build language-aware system prompt based on detected language.
        CRITICAL: Use language code and explicit instruction for Ollama to respond in the correct language.
        
        Args:
            detected_language: 'tl' (Tagalog), 'en' (English), or 'taglish' (mixed)
            emotion: Detected emotion
            confidence: Emotion confidence
            intent_type: Type of user intent (question, help_request, venting, greeting, etc.)
        
        Returns:
            System prompt in appropriate language
        """
        # The classifier label is retained for provenance, but it is not allowed to dictate conversational tone when the student's words provide clearer evidence.
        tone_emotion = str(tone_emotion or emotion or 'neutral')

        # BASE INSTRUCTION - Language specification MUST come first and be very explicit
        if detected_language == 'tl':
            language_instruction = "RESPOND IN TAGALOG ONLY. Your entire response must be in Tagalog. Do not use any English words unless absolutely necessary (proper nouns). Even for technical terms, use Tagalog equivalents. TAGALOG ONLY."
            
            intent_routing_map = {
                'question': "Sumagot ng direkta at tukoy. Sagot muna, tapos empathy kung may emosyon.",
                'statement': "Kilalanin ang damdamin. Address ang sitwasyon. Mag-alok ng suporta.",
                'venting': "Kilalanin. Ipakita na nauunawaan mo. Tulong o praktikal na direksyon.",
                'help_request': "Konkretong payo. 2-4 options o hakbang. Praktikal at malinaw.",
                'greeting': "Natural na pagbati. Maikli. Taglish pwede kung natural.",
            }
            
            intent_routing = intent_routing_map.get(
                intent_type,
                "Sumagot sa sinabi ng user. Specific at natural."
            )
            
            tone_map = {
                'anxiety': "Kalmado, reassuring. Praktikal na tulong.",
                'sadness': "Empathetic, nakakaintindi. Kilalanin ang damdamin.",
                'anger': "Validating. Intindihin ang frustration.",
                'joy': "Masaya, positibo. Ibahagi ang kaligayahan.",
            }
            
            tone = tone_map.get(tone_emotion.lower(), "Natural at makipag-usap.")
            
            return f"""{language_instruction}

Ikaw si MAGI, ang wellness companion sa loob ng MAGIREM AI.
Makinig ng may empatya. Sumagot lang sa sinabi ng user.
Emosyon: {emotion} ({confidence:.0%})
IMPORTANT: Ang detected na emosyon ay auxiliary signal lamang - maaaring hindi perpekto.
Prioritize ang actual words ng user at conversation context. Huwag mag-assume ng severe distress base sa emotion label lang.

{intent_routing}
Tone: {tone}

Kung oo/okay/kaya ang user, bigyan na ng content - walang tanong pa.
Iangkop ang haba sa hinihinging detalye: maikli para sa casual na usapan, ngunit malinaw at mas kumpleto para sa praktikal o detalyadong request."""
        
        elif detected_language == 'taglish':
            language_instruction = "Use natural Taglish when the student materially mixes Tagalog and English; do not switch entirely to English. Do not add filler slang, pet names, or theatrical reactions."
            
            intent_routing_map = {
                'question': "Answer directly. Sagot muna, tapos empathy kung may emosyon.",
                'statement': "Kilalanin ang feelings. Address ang situation. Mag-offer ng support.",
                'venting': "Kilalanin. Show na naiintindihan mo. Tulong o practical guidance.",
                'help_request': "Concrete tips. 2-4 specific options or steps. Praktical at clear.",
                'greeting': "Natural greeting. Brief. Mirror the student's amount of Taglish without adding slang.",
            }
            
            intent_routing = intent_routing_map.get(
                intent_type,
                "Respond sa sinabi nila. Natural at specific."
            )
            
            tone_map = {
                'anxiety': "Calm, reassuring. Practical na support.",
                'sadness': "Empathetic, nakakaintindi. Kilalanin ang feelings.",
                'anger': "Validating. Acknowledge the frustration.",
                'joy': "Upbeat, positive. Share sa happiness.",
            }
            
            tone = tone_map.get(tone_emotion.lower(), "Natural conversation.")
            
            return f"""{language_instruction}

You are MAGI, the wellness companion inside MAGIREM AI.
Listen with empathy. Respond to what they said - no invented details.
Emotion: {emotion} ({confidence:.0%})
IMPORTANT: Ang detected na emosyon ay auxiliary signal - maaaring hindi perpekto.
Prioritize ang actual words at conversation context. Don't infer severe distress from emotion label alone.

{intent_routing}
Tone: {tone}

If they say yes/okay/kaya, give content now - don't ask pa.
Keep casual conversation natural and brief, but give a developed, structured answer when the user asks for practical or detailed help."""
        
        else:  # English (default)
            language_instruction = "RESPOND IN ENGLISH ONLY. Your entire response must be in English. Do not use Tagalog or other languages."
            
            intent_routing_map = {
                'question': "Answer DIRECTLY and specifically. Give the factual answer first.",
                'statement': "Validate their feelings. Address their situation. Offer support.",
                'venting': "Validate their feelings. Show you understand. Offer support.",
                'help_request': "Give concrete, actionable advice. Provide 2-4 specific options.",
                'greeting': "Return a natural, brief greeting. Match their tone.",
            }
            
            intent_routing = intent_routing_map.get(
                intent_type,
                "Respond to what the user actually said. Be specific and natural."
            )
            
            tone_map = {
                'anxiety': "Be calm and reassuring. Offer practical support.",
                'sadness': "Be empathetic and understanding. Validate their feelings.",
                'anger': "Be validating. Don't dismiss their frustration.",
                'joy': "Be upbeat and positive. Share in their joy.",
            }
            
            tone = tone_map.get(tone_emotion.lower(), "Be conversational and natural.")
            
            return f"""{language_instruction}

You are MAGI, the wellness companion inside MAGIREM AI.
Listen with empathy. Respond to what the user actually said - don't invent details.
Emotion: {emotion} (confidence: {confidence:.0%})
IMPORTANT: The detected emotion is an auxiliary signal and may be imperfect.
Prioritize the user's actual words and conversation context. Do not infer severe distress solely from the emotion label.

{intent_routing}
Tone: {tone}

If user confirms your offer (yes/ok/sure), give the content now - don't ask them to supply it.
Reference only details in the conversation. Be specific. Keep casual replies concise, and give a developed, structured answer when the request is practical or detailed."""

    # Ollama supports passing a JSON schema in the /api/chat ``format`` field.
    # Use it only for explicit structured-content requests; ordinary emotional
    # conversation continues through the natural prose path.
    STRUCTURED_RESPONSE_SCHEMA = {
        "type": "object",
        "properties": {
            "title": {"type": "string"},
            "introduction": {"type": "string"},
            "steps": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "heading": {"type": "string"},
                        "explanation": {"type": "string"},
                    },
                    "required": ["heading", "explanation"],
                    "additionalProperties": False,
                },
            },
            "closing": {"type": "string"},
        },
        "required": ["introduction", "steps"],
        "additionalProperties": False,
    }

    def _explicit_message_signal(self, message: str) -> Dict:
        """Extract strong first-person wording for generation guidance only.

        This is deliberately not a second emotion classifier. It is a narrow
        guard against a contradictory model label (for example, a student saying
        ``I am happy`` while RoBERTa returns ``sadness``). Crisis routing remains
        authoritative outside this helper and mixed wording is left unresolved.
        """
        text = re.sub(r"\s+", " ", str(message or "").strip().lower())
        if not text:
            return {'kind': 'none', 'tone_emotion': None, 'instruction': ''}

        positive = bool(re.search(
            r"\b(?:i'm|i am|i feel|i've been feeling|i was)\s+"
            r"(?:really\s+|so\s+|very\s+|genuinely\s+|quite\s+)?"
            r"(?:happy|glad|excited|proud|joyful|thrilled|grateful|great|good)\b",
            text,
        )) or bool(re.search(r"\b(?:masaya|masayang-masaya)\b", text))
        positive_negated = bool(re.search(
            r"\b(?:not|never)\s+(?:really\s+)?(?:happy|glad|excited|proud|joyful|great|good)\b"
            r"|\bhindi\s+(?:ako\s+)?masaya\b",
            text,
        ))
        concern = bool(re.search(
            r"\b(?:i'm|i am|i feel|i've been feeling|i was)\s+"
            r"(?:really\s+|so\s+|very\s+|completely\s+|deeply\s+)?"
            r"(?:sad|stressed|overwhelmed|worried|anxious|angry|frustrated|grieving|burned\s*out)\b"
            r"|\b(?:nai-stress|na-stress|malungkot|nahihirapan|pagod na pagod)\b"
            r"|\b(?:sad|stressed|overwhelmed|worried|anxious|angry|frustrated|grieving)\b",
            text,
        ))

        if positive_negated:
            positive = False
            concern = True
        if positive and concern:
            return {
                'kind': 'mixed',
                'tone_emotion': 'neutral',
                'instruction': 'The current message contains both positive and difficult cues. Do not force one emotion; answer the explicit request and acknowledge only the facts stated.',
            }
        if positive:
            return {
                'kind': 'positive',
                'tone_emotion': 'joy',
                'instruction': 'The student explicitly describes a positive experience or feeling. Treat that current wording as higher priority than a conflicting model label; do not give sadness-based reassurance or mention the classifier.',
            }
        if concern:
            return {
                'kind': 'concern',
                'tone_emotion': 'neutral',
                'instruction': 'The student explicitly describes a difficulty or concern. Address the concrete issue and any requested plan before optional follow-up; do not infer a diagnosis or stronger distress than the words support.',
            }
        return {'kind': 'none', 'tone_emotion': None, 'instruction': ''}

    def _generation_guidance(self, message: str, emotion: str, unified_result=None) -> Dict:
        """Return a bounded tone hint without changing authoritative safety/emotion."""
        signal = self._explicit_message_signal(message)
        crisis_level = None
        if unified_result is not None:
            try:
                crisis_level = unified_result.crisis.get('safety_level')
            except AttributeError:
                crisis_level = None
        # Never apply a positive/neutral tone override to a critical safety path.
        if crisis_level in {'critical', 'severe'}:
            signal = {'kind': 'none', 'tone_emotion': None, 'instruction': ''}
        return signal
    def _structured_request_spec(self, message: str) -> Optional[Dict]:
        """Return a structured-generation request only for explicit requests."""
        text = (message or '').lower()
        if not text:
            return None
        number_words = {
            'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5,
            'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
        }
        match = re.search(r'\b(\d{1,2}|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:detailed\s+)?(?:practical\s+)?steps?\b', text)
        requested = None
        if match:
            token = match.group(1)
            requested = int(token) if token.isdigit() else number_words[token]
        explicit = bool(
            match
            or re.search(r'\b(numbered\s+(?:guide|steps?)|organized\s+explanation|structured\s+(?:plan|explanation)|comparison)\b', text)
        )
        if not explicit:
            return None
        require_title = bool(re.search(r'\bheadings?\b', text))
        schema = json.loads(json.dumps(self.STRUCTURED_RESPONSE_SCHEMA))
        if requested is not None:
            schema['properties']['steps']['minItems'] = requested
            schema['properties']['steps']['maxItems'] = requested
        if require_title and 'title' not in schema['required']:
            schema['required'] = list(schema['required']) + ['title']
        return {'requested_steps': requested, 'require_title': require_title, 'schema': schema}

    def _validate_structured_response(self, content: str, requested_steps: Optional[int], require_title: bool = False) -> Optional[Dict]:
        """Parse and validate model JSON without exposing malformed JSON."""
        try:
            parsed = json.loads(content or '')
        except (TypeError, ValueError):
            return None
        if not isinstance(parsed, dict):
            return None
        introduction = parsed.get('introduction')
        steps = parsed.get('steps')
        if not isinstance(introduction, str) or not introduction.strip() or not isinstance(steps, list):
            return None
        if require_title and not str(parsed.get('title') or '').strip():
            return None
        if requested_steps is not None and len(steps) != requested_steps:
            return None
        if not 1 <= len(steps) <= 10:
            return None
        clean_steps = []
        for step in steps:
            if not isinstance(step, dict):
                return None
            heading = step.get('heading')
            explanation = step.get('explanation')
            if not isinstance(heading, str) or not heading.strip() or not isinstance(explanation, str) or not explanation.strip():
                return None
            clean_steps.append({'heading': heading.strip(), 'explanation': explanation.strip()})
        return {
            'title': str(parsed.get('title') or '').strip(),
            'introduction': introduction.strip(),
            'steps': clean_steps,
            'closing': str(parsed.get('closing') or '').strip(),
        }

    def _structured_to_markdown(self, structured: Dict) -> str:
        """Create safe Markdown; the existing renderer performs escaping."""
        parts = []
        if structured.get('title'):
            parts.append('## ' + structured['title'])
        parts.append(structured['introduction'])
        items = [f"**{step['heading']}** - {step['explanation']}" for step in structured['steps']]
        parts.append('\n'.join(f'{index}. {item}' for index, item in enumerate(items, 1)))
        if structured.get('closing'):
            parts.append(structured['closing'])
        return '\n\n'.join(parts)

    def _quality_retry_reason(self, message: str, response: str, structured: bool = False, history: List[Dict] = None) -> Optional[str]:
        """Identify two narrowly scoped quality failures that merit one bounded retry.

        This is deliberately a detector, not a response generator: ordinary prose is
        left untouched and no advice is invented by the application.
        """
        if structured:
            return None
        msg = (message or '').lower()
        out = (response or '').strip().lower()

        # An illness is not a death. Only the student's own statements can
        # establish a loss; prior assistant hallucinations cannot establish it.
        user_turns = [str((turn or {}).get('user') or '') for turn in (history or [])
                      if isinstance(turn, dict)]
        user_turns.append(message or '')
        user_context = ' '.join(user_turns).lower()
        illness = re.search(r'\b(?:sick|ill|unwell|injured|hospitali[sz]ed|nagkasakit|maysakit|may sakit|masakit|nasa ospital)\b', user_context)
        death_pattern = r'\b(?:died|passed away|has died|namatay|pumanaw)\b'
        speculative_pattern = r"\b(?:might|may|could|will|if|afraid|worried|fear|not|didn't|hindi|baka|sana)\b.{0,35}\b(?:die|died|pass away|namatay|mamatay|pumanaw)\b"
        asserted_loss = any(re.search(death_pattern, turn, re.I) and not re.search(speculative_pattern, turn, re.I)
                            for turn in user_turns)
        claimed_loss = re.search(r"\b(?:(?:losing|lost|lose) (?:a|your|the|my) (?:pet|dog|cat|companion)|(?:your|the|his|her) (?:pet|dog|cat|mother|father|friend) (?:died|passed away|is dead)|(?:condolences|pagkamatay|namatay ang))\b", out)
        if illness and claimed_loss and not asserted_loss:
            return 'unsupported_loss_assumption'

        # A financial continuation must not be redirected into an unrelated
        # emotion check merely because earlier history contained distress.
        financial_request = bool(re.search(
            r"\b(?:calculate|budget|income|salary|rent|utilities?|transport(?:ation)?|food|meal|expense|expenses|balance|deficit|monthly|weekly|reduce|increase|change|original)\b",
            msg,
        ))
        unrelated_emotion_question = bool(re.search(
            r"\b(?:what are you feeling|how are you feeling|tell me what you(?:'re| are) feeling|"
            r"what(?:'s| is) on your mind|what are you experiencing)\b",
            out,
        ))
        if financial_request and unrelated_emotion_question and self._detect_crisis(message) != 'critical':
            return 'financial_context_override'

        planning_request = bool(re.search(
            r"\b(?:where\s+(?:do\s+i\s+)?(?:begin|start)|don't\s+know\s+where\s+to\s+begin|"
            r"how\s+do\s+i\s+start|what\s+should\s+i\s+priorit(?:ize|y)|"
            r"priorit(?:ize|y)|make\s+(?:a|me\s+a)\s+plan|practical\s+plan)\b",
            msg,
        ))
        if planning_request:
            # A response may end with one useful question, but it must first offer
            # an actionable framework grounded in the stated facts.
            before_question = re.split(r"\?", out, maxsplit=1)[0]
            practical_markers = (
                'priorit', 'essential', 'list', 'track', 'budget',
                'separate', 'next step', 'plan', 'review', 'write', 'categor',
            )
            has_practical_advice = any(marker in before_question for marker in practical_markers)
            asks_for_more_context = bool(re.search(
                r"\b(?:would you like|could you tell me|can you tell me|what specifically)\b",
                out,
            ))
            if asks_for_more_context and not has_practical_advice:
                return 'planning_without_first_pass'

        purchase_request = bool(re.search(r"\b(?:buy|purchase|new phone|upgrade)\b", msg))
        unqualified_purchase = bool(re.search(
            r"\b(?:best\s+to|should|need\s+to|must|recommend|suggest|better\s+to)\b"
            r".{0,80}\b(?:postpone|delay|buy|purchase|upgrade)\b"
            r"|\b(?:might|may|would|could)\b.{0,60}\b(?:not\s+be|be)\b.{0,60}\b(?:best|wise|right|step\s+backward)\b",
            out,
        ))
        conditional_context = bool(re.search(
            r"\b(?:if|whether|necessary|optional|depends|current\s+phone|"
            r"cost|afford|reliable\s+enough)\b",
            out,
        ))
        if purchase_request and unqualified_purchase and not conditional_context:
            return 'unqualified_purchase_advice'

        # A purchase/financial decision should still receive a usable decision
        # framework when the first Gemma pass only reflects the emotion. One
        # bounded recovery call is preferable to inventing a recommendation.
        decision_markers = (
            'current phone', 'necessary', 'optional', 'cost', 'afford',
            'reliable', 'compare', 'budget', 'wait', 'priority', 'essential',
            'transport', 'meals', 'requirements', 'condition',
        )
        if purchase_request and not any(marker in out for marker in decision_markers):
            return 'purchase_without_decision_framework'
        numeric_reason = self._numeric_quality_retry_reason(message, response, history)
        if numeric_reason:
            return numeric_reason
        return None

    def _numeric_quality_retry_reason(self, message: str, response: str, history: List[Dict] = None) -> Optional[str]:
        """Detect category, income, total, and balance contradictions.

        The guard is intentionally read-only: it validates against the bounded
        deterministic facts and never evaluates model-supplied expressions.
        """
        cautious_food = food_budget_caution_response(message)
        if cautious_food:
            out_lower = str(response or '').lower()
            if re.search(r"\b(?:potentially\s+realistic|realistic|sufficient|adequate|your\s+(?:known\s+)?(?:monthly\s+)?income|fixed\s+monthly\s+food\s+expense|let'?s\s+assume|allocation)\b", out_lower):
                return "food_budget_unsupported_claim"
        facts = calculate_budget_context(message, history)
        if not facts or facts.get("balance_before_unknown") is None:
            return None
        out = str(response or "")
        balance = facts["balance_before_unknown"]
        currency_pattern = r"(?:\u20b1|PHP|USD|EUR|GBP)?"

        def amount_value(raw: str) -> float:
            try:
                return float(str(raw).replace(",", ""))
            except (TypeError, ValueError):
                return -1.0

        def normalized(value: object) -> float:
            try:
                return round(float(value), 2)
            except (TypeError, ValueError):
                return -1.0

        expected_total = normalized(facts["known_expenses_total"])
        total_pattern = rf"\b(?:total(?:\s+(?:known|monthly|current|actual|fixed|estimated|baseline))*\s+expenses?|total\s+expenditure|expenses?\s+total)\b.{{0,45}}?{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)"
        total_matches = list(re.finditer(total_pattern, out, re.IGNORECASE))
        for match in total_matches:
            if abs(amount_value(match.group(1)) - expected_total) > 0.01:
                return "numeric_expense_total"
        # Explicit calculation/restoration requests must state the verified
        # total; a response that merely lists categories is incomplete even if
        # its prose happens not to contradict the arithmetic.
        calculation_requested = bool(re.search(
            r"\b(?:calculate|calculation|recalculate|compute|total|remaining|balance|deficit|expenses?)\b",
            str(message or ""),
            re.IGNORECASE,
        ))
        if calculation_requested and not total_matches:
            return "numeric_expense_total"

        # Category-level validation catches internally contradictory output
        # even when the final total happens to be correct.
        label_patterns = {
            "rent": r"rent|boarding|dorm|housing",
            "utilities": r"utilities?|electric(?:ity)?|water|internet|wi[- ]?fi",
            "food": r"food|meal(?:s)?|grocer(?:y|ies)",
            "transportation": r"transport(?:ation)?|commute|fare|jeepney|bus|train|tricycle",
            "tuition": r"tuition|school\s+fees?",
            "phone": r"phone|mobile|load",
        }
        for label, expected_amount, _currency in facts.get("expenses", []):
            pattern = label_patterns.get(label)
            if not pattern:
                continue
            expected = normalized(expected_amount)
            # A transportation response may show both its unit amount and the
            # effective monthly amount; accept either verified component.
            allowed = {expected}
            for original_label, original_amount, original_currency, multiplier in facts.get("original_expenses", []):
                if original_label == label and label == "transportation":
                    allowed.add(normalized(original_amount))
            sentences = re.split(r"(?<=[.!?])\s+|\n+", out)
            label_mentions = [sentence for sentence in sentences if re.search(rf"\b(?:{pattern})\b", sentence, re.IGNORECASE)]
            for sentence in label_mentions:
                values = [amount_value(raw) for raw in re.findall(rf"{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)", sentence)]
                if not values:
                    continue
                contextual = bool(re.search(r"\b(?:original|hypothetical|what\s+if|previous|before|scenario|example)\b", sentence, re.IGNORECASE))
                if not contextual and not any(any(abs(value - item) <= 0.01 for item in allowed) for value in values):
                    return "numeric_category_amount"
            # Also inspect a short label window for compact Markdown/list
            # formatting where sentence splitting is not sufficient.
            for match in re.finditer(rf"\b(?:{pattern})\b", out, re.IGNORECASE):
                window = out[max(0, match.start() - 20):min(len(out), match.end() + 90)]
                values = [amount_value(raw) for raw in re.findall(rf"{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)", window)]
                if values:
                    if not any(any(abs(value - item) <= 0.01 for item in allowed) for value in values):
                        if not re.search(r"\b(?:original|hypothetical|what\s+if|previous|before|scenario|example)\b", window, re.IGNORECASE):
                            return "numeric_category_amount"

        # Validate stated income separately from expenses. A monthly baseline
        # must not be replaced by the last monthly expense amount.
        if facts.get("income_value") is not None and facts.get("income_period"):
            income_expected = normalized(facts["income_value"])
            period = str(facts["income_period"])
            # Use focused searches in both word orders; only reject a stated
            # period amount when it is explicitly present in the response.
            for match in re.finditer(rf"\b{period}\b.{{0,45}}?{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)", out, re.IGNORECASE):
                if abs(amount_value(match.group(1)) - income_expected) > 0.01:
                    return "numeric_income"

        balance_abs = abs(balance)
        amount_text = f"{balance_abs:,.2f}".rstrip("0").rstrip(".")
        amount_plain = amount_text.replace(",", "")
        if balance < 0:
            balance_mentions = bool(re.search(rf"(?:remaining|balance|left|available).{{0,70}}{currency_pattern}\s*{re.escape(amount_text)}\b", out, re.IGNORECASE))
            valid_deficit = bool(re.search(rf"(?:-\s*{currency_pattern}\s*{re.escape(amount_text)}|(?:deficit|shortfall|short)\s+(?:of|by)?\s*{currency_pattern}\s*{re.escape(amount_text)})", out, re.IGNORECASE))
            if re.search(r"\b(?:surplus|excess(?: funds)?|positive balance|within the monthly baseline)\b", out, re.IGNORECASE) and not re.search(r"\b(?:deficit|shortfall|short)\b", out, re.IGNORECASE):
                return "numeric_deficit_sign"
            if balance_mentions and not valid_deficit:
                return "numeric_deficit_sign"
            if re.search(r"\b(?:remaining|balance|left|available)\b", out, re.IGNORECASE):
                listed = re.findall(rf"{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)", out)
                if listed and all(item.replace(",", "") != amount_plain for item in listed[-4:]):
                    return "numeric_balance_mismatch"
        else:
            if re.search(r"\b(?:deficit|shortfall|short|expenses?\s+(?:exceed|are above)|exceed(?:s|ing)? the (?:monthly )?(?:income|baseline))\b", out, re.IGNORECASE):
                return "numeric_positive_balance_reversal"
            if re.search(r"\b(?:no money|nothing left|no amount left|leaves? no|no remaining funds)\b", out, re.IGNORECASE):
                return "numeric_positive_balance_reversal"
            if re.search(r"\b(?:remaining|balance|left|available|funds|surplus)\b", out, re.IGNORECASE):
                listed = re.findall(rf"{currency_pattern}\s*([0-9][0-9,]*(?:\.[0-9]+)?)", out)
                if listed and all(item.replace(",", "") != amount_plain for item in listed[-4:]):
                    return "numeric_balance_mismatch"
        # A summary request must enumerate each verified scenario endpoint.
        # Otherwise Gemma can return a plausible actual total while silently
        # dropping intermediate hypothetical changes from the conversation.
        if _summary_requested(message) and facts.get("hypothetical_scenarios"):
            def amount_present(value: object) -> bool:
                try:
                    decimal_value = float(value)
                except (TypeError, ValueError):
                    return False
                if decimal_value.is_integer():
                    variants = {f"{int(decimal_value):,}", str(int(decimal_value))}
                else:
                    variants = {f"{decimal_value:,.2f}", f"{decimal_value:.2f}"}
                return any(re.search(rf"(?<!\d){re.escape(item)}(?:\.00)?(?!\d)", out) for item in variants)
            missing_targets = [
                scenario.get("to") for scenario in facts.get("hypothetical_scenarios", [])
                if not amount_present(scenario.get("to"))
            ]
            if missing_targets:
                return "financial_summary_incomplete"
        return None

    @staticmethod
    def _strip_creative_preface(response: str) -> str:
        """Remove only stock creative wrappers; keep the requested creative work intact."""
        text = str(response or '').strip()
        artifact = r'(?:poem|story|letter|message|song|tula|liham|sanaysay)'
        descriptor = r'(?:(?:short|brief|hopeful|gentle|simple)\s+)'

        # These patterns require an explicit creative-artifact hand-off, so a
        # meaningful opening that happens to use similar language is preserved.
        text = re.sub(
            r'^(?:(?:okay|ok|sure|of course)\s*[,!:.\-]?\s*)?'
            r'(?:(?:let.?s\s+get\s+(?:this|it)\s+done!?\s*)?'
            r'(?:here(?:.s|\s+is)\s+(?:a|an|the)?\s*' + descriptor + r'{0,3}'
            + artifact + r'(?:\s+for\s+you)?\s*[:!]\s*))',
            '',
            text,
            count=1,
            flags=re.IGNORECASE,
        )
        text = re.sub(
            r'^(?:(?:okay|ok|sure|of course)\s*[,!:.\-]?\s*)?'
            r'(?:(?:let.?s|i.?ll|we.?ll)\s+)?(?:craft|write|create|share)\s+'
            r'(?:a|an|the)?\s*' + descriptor + r'{0,3}' + artifact
            + r'(?:\s+for\s+you)?\s*[!:]\s*',
            '',
            text,
            count=1,
            flags=re.IGNORECASE,
        )
        return re.sub(
            r'\s*(?:---|\*{3})?\s*Would you like (?:me to create something else for you|'
            r'to explore any related ideas or perhaps create a different kind of '
            + artifact + r')\?\s*$',
            '',
            text,
            count=1,
            flags=re.IGNORECASE,
        ).strip()
    def _contextual_quality_retry_reason(self, mode: str, message: str, response: str, history: List[Dict] = None) -> Optional[str]:
        """Detect bounded follow-up/creative misses without altering safety validation."""
        text = str(message or '').lower()
        out = str(response or '').strip()
        if mode == 'follow_up' and re.search(r'\b(?:make\s+(?:it|that|this)\s+short(?:er)?|shorten\s+that)\b', text):
            previous = ''
            for exchange in reversed(history or []):
                if isinstance(exchange, dict) and exchange.get('ai'):
                    previous = str(exchange.get('ai') or '').strip()
                    break
            # Ask the model to rewrite only when the result is plainly longer
            # than the answer the student asked to shorten.
            if previous and len(out) >= max(24, int(len(previous) * 0.9)):
                return 'followup_not_shorter'
        if mode == 'practical':
            immediate_deadline = bool(re.search(r'\b(?:today|tomorrow|bukas|ngayong\s+araw)\b', text))
            stated_calendar = bool(re.search(r'\b(?:mon(?:day)?|tue(?:sday)?|wed(?:nesday)?|thu(?:rsday)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?|lunes|mart(?:es)?|miyerkules|huwebes|biyernes|sabado|linggo|\d{1,2}\s*(?:a\.?m\.?|p\.?m\.?))\b', text, re.IGNORECASE))
            invented_calendar = bool(re.search(r'\b(?:mon(?:day)?|tue(?:sday)?|wed(?:nesday)?|thu(?:rsday)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?|lunes|mart(?:es)?|miyerkules|huwebes|biyernes|sabado|linggo|\d{1,2}\s*(?:a\.?m\.?|p\.?m\.?))\b', out, re.IGNORECASE))
            if immediate_deadline and invented_calendar and not stated_calendar:
                return 'invented_calendar_schedule'
        if mode == 'practical':
            immediate_academic_request = bool(re.search(r'\b(?:exam(?:s)?|review|quiz|aral(?:in)?|pag-?aral)\b', text)) and bool(re.search(r'\b(?:today|tomorrow|bukas|ngayong\s+araw)\b', text))
            first_pass_actions = ('unahin', 'hatiin', 'priorit', 'simulan', 'focus', 'gumawa', 'reviewhin', 'i-review', 'piliin')
            if immediate_academic_request and not any(marker in out.lower() for marker in first_pass_actions):
                return 'academic_plan_without_first_pass'
        if mode == 'creative' and re.match(r'^(?:okay|ok|sure|here(?:\'s| is)|of course)[,:.!]?\s+', out, re.IGNORECASE):
            return 'creative_preface'
        return None

    def generate(self, message: str, emotion: str, confidence: float, 
                conversation_history: List[Dict] = None, memory_context: str = None,
                semantic_result: Dict = None, context_result: Dict = None,
                unified_result = None, ollama_understanding: Dict = None,
                counselor_context: Dict = None,
                emotion_response_context: Dict = None) -> Dict:
        """
        Generate response using trained emotion model + LLM + long-term memory
        
        Args:
            message: User's actual message
            emotion: Detected emotion from trained model
            confidence: Confidence score from trained model
            conversation_history: Previous messages for context (will be capped to prevent timeouts)
            memory_context: Long-term memory context from memory system (SEPARATE from history cap)
            semantic_result: Full semantic analysis (includes sarcasm_detected, probabilities)
            context_result: Context classification (includes interaction_type, reasoning)
            ollama_understanding: PHASE 1 - Structured understanding from Ollama
            context_result: Context classification (includes interaction_type, reasoning)
        
        Returns:
            Response with all analysis included
        """
        import time
        start_time_total = time.time()
        # Development consoles on Windows may use cp1252; keep diagnostics from
        # aborting otherwise valid Tagalog or Taglish requests on Unicode output.
        import sys
        for stream in (sys.stdout, sys.stderr):
            try:
                stream.reconfigure(encoding='utf-8', errors='backslashreplace')
            except (AttributeError, ValueError):
                pass
        
        # LANGUAGE DETECTION: Detect user's language
        lang_result = self.detect_message_language(message, conversation_history)
        detected_language = lang_result['detected_language']
        language_confidence = lang_result['confidence']
        generation_started = time.perf_counter()
        correlation_id = getattr(self, '_correlation_id', None)
        emit_diagnostic(
            'hybrid',
            'generation_start',
            correlation_id,
            model=self.model,
            language=detected_language,
            language_confidence=round(language_confidence, 3),
            history_turns=len(conversation_history or []),
            emotion=emotion,
            emotion_confidence=round(float(confidence or 0), 3),
        )
        
        print(f"[LANG_ROUTING] Detected: {detected_language} (conf: {language_confidence:.0%})")
        
        # Keep one bounded, ordered current-session window. Optional persistent
        # memory remains separate and behind the Phase 4B consent boundary.
        received_history_turns = len(conversation_history) if isinstance(conversation_history, list) else 0
        conversation_history = self._bounded_history(conversation_history)
        if received_history_turns != len(conversation_history):
            print(f"[HISTORY_BOUND] received={received_history_turns} used={len(conversation_history)}")

        history_len = len(conversation_history)
        print(f"\n[HYBRID_GENERATE_START]")
        print(f"[HYBRID_INPUT] emotion={emotion}, confidence={confidence:.0%}, msg_len={len(message)}, history_len={history_len}")
        try:
            print(f"[GENERATE_TRY_START] Entering generate() try block")
            # Detect sarcasm: Check semantic_result FIRST (already computed)
            # Fall back to pattern matching only if semantic_result unavailable
            detected_sarcasm = False
            sarcasm_confidence = 0.0
            sarcasm_reason = ""
            
            # Try to use improved sarcasm detector with multiple signals
            try:
                from sarcasm_detector import detect_sarcasm as detect_sarcasm_ml
                print(f"[SARCASM_DEBUG] Successfully imported detect_sarcasm from sarcasm_detector")
                
                # Build prior context from conversation history
                prior_context = ""
                if conversation_history:
                    for exchange in conversation_history:
                        if exchange.get('user'):
                            prior_context += exchange['user'] + " "
                        if exchange.get('ai'):
                            prior_context += exchange['ai'] + " "
                
                print(f"[SARCASM_DEBUG] Calling detect_sarcasm_ml...")
                sarcasm_result = detect_sarcasm_ml(message, prior_context, semantic_result)
                print(f"[SARCASM_DEBUG] Result: {sarcasm_result}")
                
                detected_sarcasm = sarcasm_result.get('is_sarcastic', False)
                sarcasm_confidence = sarcasm_result.get('confidence', 0.0)
                sarcasm_reason = sarcasm_result.get('reason', '')
                
                print(f"[SARCASM_ML] Detected: {detected_sarcasm}, confidence: {sarcasm_confidence:.0%}")
                print(f"[SARCASM_ML] Reason: {sarcasm_reason}")
                
                # Store for use in _build_prompt
                self._sarcasm_confidence = sarcasm_confidence
                
            except ImportError as ie:
                print(f"[SARCASM_DEBUG] ImportError from sarcasm_detector: {ie}")
                # Fallback: Use semantic_result or simple pattern matching
                # FIX: semantic_result stores sarcasm in ['tone']['sarcasm_score'], NOT ['sarcasm_detected']
                if semantic_result and semantic_result.get('tone', {}).get('sarcasm_score', 0) > 0.5:
                    detected_sarcasm = True
                    sarcasm_confidence = semantic_result.get('tone', {}).get('sarcasm_score', 0)
                    sarcasm_reason = "VADER sentiment analysis detected sarcasm"
                    print(f"[SARCASM] Using semantic analysis result: sarcasm_score={sarcasm_confidence:.2f}")
                else:
                    # Fallback: Use pattern matching
                    detected_sarcasm = self._detect_sarcasm(message)
                    sarcasm_confidence = 0.6 if detected_sarcasm else 0.0
                    sarcasm_reason = "Pattern-based detection"
                    if detected_sarcasm:
                        print(f"[SARCASM] Detected via pattern matching")
                
                # Store for use in _build_prompt
                self._sarcasm_confidence = sarcasm_confidence
            except Exception as ex:
                print(f"[SARCASM_DEBUG] Exception from sarcasm_detector: {ex}")
                import traceback
                traceback.print_exc()
                # Fallback: Use semantic_result or simple pattern matching
                if semantic_result and semantic_result.get('tone', {}).get('sarcasm_score', 0) > 0.5:
                    detected_sarcasm = True
                    sarcasm_confidence = semantic_result.get('tone', {}).get('sarcasm_score', 0)
                    sarcasm_reason = "VADER sentiment analysis detected sarcasm"
                    print(f"[SARCASM] Using semantic analysis result: sarcasm_score={sarcasm_confidence:.2f}")
                else:
                    # Fallback: Use pattern matching
                    detected_sarcasm = self._detect_sarcasm(message)
                    sarcasm_confidence = 0.6 if detected_sarcasm else 0.0
                    sarcasm_reason = "Pattern-based detection"
                    if detected_sarcasm:
                        print(f"[SARCASM] Detected via pattern matching")
                
                # Store for use in _build_prompt
                self._sarcasm_confidence = sarcasm_confidence
            
            # DETERMINE EMOTION FOR TONE (before calling _build_prompt)
            # This will also be used in system_prompt_short
            sarcasm_conf = sarcasm_confidence
            if detected_sarcasm and sarcasm_conf > 0.15:
                # Infer underlying emotion
                msg_lower = message.lower()
                emotion_for_tone = emotion  # default
                
                # Check for stress/deadline indicators
                stress_keywords = ['deadline', 'exam', 'test', 'project', 'assignment', 'homework', 
                                  'work', 'busy', 'overwhelm', 'stressed', 'anxiety', 'anxious']
                if any(kw in msg_lower for kw in stress_keywords):
                    emotion_for_tone = 'anxiety'
                
                # Check for sadness/failure indicators
                sadness_keywords = ['failed', 'fail', 'bad', 'awful', 'terrible', 'sad', 'depressed', 
                                   'lonely', 'broken', 'heartbreak', 'lost']
                if any(kw in msg_lower for kw in sadness_keywords):
                    emotion_for_tone = 'sadness'
                
                # Check for frustration/anger indicators
                anger_keywords = ['frustrated', 'angry', 'mad', 'annoyed', 'irritated', 'fed up', 'pissed']
                if any(kw in msg_lower for kw in anger_keywords):
                    emotion_for_tone = 'anger'
                
                print(f"[GENERATE_LEVEL_OVERRIDE] Using emotion_for_tone={emotion_for_tone} due to sarcasm")
            else:
                emotion_for_tone = emotion

            # Give explicit first-person wording priority for generation tone only.
            # The returned/persisted emotion remains the classifier's provenance.
            generation_guidance = self._generation_guidance(message, emotion, unified_result)
            if generation_guidance.get('tone_emotion') and not detected_sarcasm:
                emotion_for_tone = generation_guidance['tone_emotion']
            print(f"[GENERATION_SIGNAL] kind={generation_guidance.get('kind', 'none')} tone={emotion_for_tone}")

            # Build context with emotion information and long-term memory
            start_time_prompt = time.time()
            context = self._build_prompt(message, emotion, confidence, conversation_history, 
                                        memory_context, detected_sarcasm, semantic_result, context_result,
                                         unified_result, ollama_understanding, emotion_for_tone,
                                         emotion_response_context)
            prompt_build_time = (time.time() - start_time_prompt) * 1000
            try:
                print(f"[AFTER_BUILD_PROMPT] Prompt built successfully, {len(context)} chars, took {prompt_build_time:.0f}ms")
            except:
                print(f"[AFTER_BUILD_PROMPT] Prompt built successfully, took {prompt_build_time:.0f}ms")
            print(f"[POST_BUILD_CHECKPOINT] About to enter Ollama request section")
            
            # Call LLM with appropriate timeout
            # Use separate connection and read timeout:
            # - connection timeout (5s): time to establish connection to Ollama
            # - read timeout (15s): time to wait for response from model
            
            # LOG THE FINAL PROMPT FOR DEBUGGING
            print(f"[PRE_OLLAMA] About to call Ollama with msg_len={len(message)}")
            
            import sys
            sys.stderr.write(f"\n[PRE_OLLAMA] msg_len={len(message)} emotion={emotion}\n")
            sys.stderr.flush()
            
            print(f"[TRACE] About to call Ollama")
            import sys
            sys.stderr.write(f"[TRACE_STDERR] Calling Ollama\n")
            sys.stderr.flush()
            
            # ===== STEP 2: CONVERT TO /api/chat FORMAT (MESSAGES ARRAY) =====
            # Check if user is confirming/answering a prior offer - rewrite as explicit task
            rewritten_msg, was_rewritten = self._detect_and_rewrite_task(message, conversation_history)
            if was_rewritten:
                print(f"[TASK_REWRITE] Confirmation detected, rewritten to explicit task")
                user_message_for_model = rewritten_msg
            else:
                user_message_for_model = message
            
            # Build messages array instead of flat prompt
            # SESSION 14: Intent routing must be included in the actual Ollama system prompt.
            intent_type = ""
            if unified_result and unified_result.intent and unified_result.intent.get('primary'):
                intent_type = unified_result.intent['primary'].lower()

            # LANGUAGE-AWARE SYSTEM PROMPT
            system_prompt_short = self.get_system_prompt_for_language(detected_language, emotion, confidence, intent_type, emotion_for_tone)
            system_prompt_short += (
                '\n\nFACTUAL CONTINUITY: Use the student’s stated facts, not assumptions or '
                'earlier assistant guesses. Illness or injury does not imply death or loss. '
                'Distinguish a fear or possibility from an event that happened; ask if unclear.'
            )
            request_mode = self._request_mode(message, conversation_history)
            # Project provenance is relevant to identity questions, not every
            # ordinary chat turn. Keeping it conditional reduces prompt prefill
            # while retaining verified model/application attribution on demand.
            if self._needs_application_identity(message):
                system_prompt_short += (
                    '\n\nAPPLICATION IDENTITY (authoritative project context): '
                    'The application is MAGIREM AI at Mindoro State University. '
                    'The underlying Gemma 3:4B language model was developed by Google. '
                    'MAGIREM AI is integrated and maintained by the MAGIREM AI development team; '
                    'do not invent individual names, services, availability, or affiliations. '
                    'If asked who made you, distinguish Google as the model developer from the '
                    'MAGIREM AI team as the application integrator.'
                )
            # The active /api/chat path sends this compact routing directive in
            # the system message. The richer _build_prompt analysis is retained
            # for diagnostics, but is not sent as a second user-like prompt.
            system_prompt_short += '\n\n' + self._request_mode_guidance(request_mode)
            feature_guidance = self._verified_feature_guidance(message)
            if feature_guidance:
                system_prompt_short += '\n\n' + feature_guidance
            # Gate 3: keep the model in a transparent, non-diagnostic support role.
            safety_prompt = 'Huwag mag-diagnose ng mental-health condition. Ang scores ay hindi diagnosis. Magtanong ng isang paglilinaw kapag kulang ang context. Huwag sabihing may alert o appointment kung walang aktuwal na event.' if detected_language == 'tl' else 'Do not diagnose mental-health conditions. Scores are not diagnoses. Ask one clarifying question when context is unclear. Never claim an external alert or appointment occurred unless an actual event is provided.'
            system_prompt_short = system_prompt_short + '\n\nNON-DIAGNOSTIC SAFETY:\n' + safety_prompt
            if generation_guidance.get('instruction'):
                system_prompt_short += '\n\nCURRENT-MESSAGE PRIORITY: ' + generation_guidance['instruction']
            if emotion_response_context:
                cue_emotions = emotion_response_context.get('emotions') or []
                cue_status = emotion_response_context.get('status')
                if cue_emotions and cue_status in {'clear', 'mixed'}:
                    emotion_text = ' and '.join(str(item).replace('_', ' ') for item in cue_emotions[:5])
                    system_prompt_short += (
                        '\n\nVALIDATED CONVERSATIONAL CUE (soft, non-diagnostic): '
                        f'The student\'s own message supports {cue_status} feelings involving {emotion_text}. '
                        'Use the student\'s actual words as the source of truth. Reflect more than one feeling '
                        'when relevant rather than reducing mixed feelings to one label. Do not mention internal '
                        'emotion keys, claim certainty, diagnose, or infer facts the student did not state. '
                        'If the message does not call for emotional reflection, answer its practical request normally.'
                    )
                    # Safe internal trace marker: labels and message content are
                    # deliberately excluded from logs.
                    print('[EMOTION_RESPONSE_CUE_APPLIED] applied=true')
            system_prompt_short += (
                '\n\nRESPONSE QUALITY: Match the user\'s language unless they ask to switch. '
                'Use a calm, professional, natural tone. Avoid pet names, slang greetings, theatrical reactions, '
                'and emojis in serious distress. Open with the concrete situation or a relevant question rather than defaulting '
                'to generic empathy phrases. Answer a detailed request directly with concrete steps before asking '
                'a question. Do not repeat reassurance or clarification when the request is already clear. ' +
                'For a prioritization or planning request, give a usable first-pass plan from the stated facts even when an exact allowance or amount is unknown; make any follow-up question optional and place it after the advice. ' +
                'Carry forward concrete details from earlier turns (such as transport, meals, requirements, deadlines, or peer comparison) and explain how they affect the plan. Do not add unrelated essentials or personal circumstances. ' +
                'If the user explicitly requests a heading, include a Markdown heading line beginning with ## ; ' +
                'for detailed or list requests, put each paragraph, heading, and list item on its own line with blank lines between sections; ' +
                'do not force headings into ordinary emotional conversation. ' +
                'Use prior turns only as context: distinguish stated facts from suggestions or assumptions; when the current turn changes topic, answer the current topic. ' +
                 'For money, academic, or university matters, do not invent amounts, savings, services, schedules, availability, scholarships, grants, or aid. Never mention MinSU/MSU resources, counselors, appointments, or services as available unless exact details are present in counselor_context or the current message; otherwise say only that official options can be checked. Label any hypothetical example as hypothetical. For food-budget questions, do not call a range sufficient without knowing the number of meals, time period, location, and what food access already exists. Prioritize adequate meals and essential needs, never recommend skipping meals, and explicitly flag a very low hypothetical range as potentially restrictive rather than presenting it as a complete plan. When reflecting emotion, name the student\'s feelings at most once, then focus on their situation and a useful next step; avoid repeating generic reassurance.'
            )
            # Keep precision-sensitive arithmetic out of Gemma's mental math.
            # This is a bounded, read-only fact block; it does not execute user
            # supplied expressions and remains absent for ordinary conversation.
            numeric_context = numeric_context_for_prompt(message, conversation_history)
            if numeric_context:
                system_prompt_short += '\n\n' + numeric_context
            # Laravel supplies this compact, read-only directory summary. It is
            # trusted factual context, never student data. The model must not
            # fabricate names, schedules, contacts, bookings, or links.
            if counselor_context:
                outcome = counselor_context.get('lookup_outcome', 'unknown')
                eligible = int(counselor_context.get('eligible_counselor_count', 0) or 0)
                available_now = int(counselor_context.get('available_now_count', 0) or 0)
                specialties = counselor_context.get('specializations') or []
                specialty_text = ', '.join(str(item) for item in specialties[:6]) or 'not published'
                system_prompt_short += (
                    '\n\nCOUNSELOR DIRECTORY FACTS (Laravel verified): '
                    f'outcome={outcome}; eligible_count={eligible}; available_now={available_now}; '
                    f'published_specializations={specialty_text}. '
                    'Only mention counseling as an optional next step when context supports it. '
                    'Never invent a counselor name, schedule, contact detail, appointment, alert, or link. '
                    'Laravel alone provides any referral actions.'
                )
                if counselor_context.get('explicit_request'):
                    system_prompt_short += (
                        ' The student explicitly asked for counseling. State plainly that they can use the in-app '
                        'counselor directory or submit an appointment request now; do not delay this with screening questions.'
                    )
            
            # Add language tag to user message itself to reinforce language choice
            # This helps models that don't respect system prompt instructions well
            if detected_language == 'tl':
                user_message_with_lang = f"[TAGALOG] {user_message_for_model}"
                print(f"[LANG_TAG] Added Tagalog tag to user message")
            elif detected_language == 'taglish':
                user_message_with_lang = f"[TAGLISH] {user_message_for_model}"
                print(f"[LANG_TAG] Added Taglish tag to user message")
            else:
                user_message_with_lang = user_message_for_model
            
            messages = [{"role": "system", "content": system_prompt_short}]
            
            # Preserve recent active-session turns in order. This allows a follow-up or referral
            # confirmation to refer to its actual preceding offer; the newest message remains authoritative.
            if conversation_history:
                for exchange in conversation_history:
                    if exchange.get('user'):
                        messages.append({"role": "user", "content": exchange['user']})
                    if exchange.get('ai'):
                        messages.append({"role": "assistant", "content": exchange['ai']})
            
            # Add current message (rewritten if it was a confirmation) with language tag
            messages.append({"role": "user", "content": user_message_with_lang})
            structured_spec = self._structured_request_spec(message)
            if structured_spec:
                print(f"[STRUCTURED_REQUEST] requested_steps={structured_spec.get('requested_steps')} require_title={structured_spec.get('require_title', False)}")
                messages[0]["content"] += (
                    "\n\nSTRUCTURED OUTPUT: Return only the supplied JSON schema. Generate the requested number of meaningful steps. "
                    "Use plain, non-clinical language; do not diagnose or assert anxiety, depression, or another disorder. "
                    "Do not include HTML, URLs, alerts, appointments, or claims that anyone was contacted."
                )
            
            start_time_ollama = time.time()
            
            # Call /api/chat instead of /api/generate
            chat_endpoint = self.api_endpoint.replace("/api/generate", "/api/chat")
            
            # Log Ollama call START with correlation ID
            import sys
            correlation_id = getattr(self, '_correlation_id', 'unknown')
            print(f"[OLLAMA_REQUEST_START] call=1 model=gemma3:4b correlation_id={correlation_id} message_len={len(message)}")
            sys.stderr.write(f"[OLLAMA_REQUEST_START] call=1 model=gemma3:4b correlation_id={correlation_id}\n")
            sys.stderr.flush()
            
            print(f"[OLLAMA_CALL_CHAT] Using /api/chat with {len(messages)} messages...")
            print(f"[OLLAMA_LANGUAGE] System prompt language: {detected_language} (confidence: {language_confidence:.0%})")
            print(f"[OLLAMA_TIMEOUT_CONFIG] connection_timeout={self.connect_timeout}s, read_timeout={self.read_timeout}s")
            
            response_budget = self._response_token_budget(message, bool(structured_spec), request_mode)
            request_payload = {
                "model": self.model,
                "messages": messages,
                "stream": False,
                "keep_alive": self.keep_alive,
                "options": {
                    "temperature": 0.75,
                    "num_predict": response_budget,
                    "num_ctx": self.context_window,
                },
            }
            if structured_spec:
                request_payload["format"] = structured_spec["schema"]
                # Structured output is still model-generated, but constrained to
                # the requested schema and same bounded runtime budget.
                request_payload["options"]["temperature"] = 0.2
            try:
                
                response = self._post_to_ollama(chat_endpoint, request_payload)

                print(f"[TRY_POST_SUCCESS] POST request succeeded")
                


            except requests.Timeout as timeout_error:
                print(f"[OLLAMA_TIMEOUT] Ollama took too long to respond: {timeout_error}")
                return self._fallback_response(message, emotion, confidence, 'timeout')
            print(f"[OLLAMA_RESPONSE] Status: {response.status_code}")
            
            ollama_time = (time.time() - start_time_ollama) * 1000
            emit_diagnostic('hybrid', 'ollama_complete', correlation_id, model=self.model, status_code=response.status_code, duration_ms=round(ollama_time, 2), history_turns_sent=len(conversation_history or []))
            
            # Log Ollama call END with duration and correlation ID
            print(f"[OLLAMA_REQUEST_END] call=1 duration_ms={ollama_time:.0f} correlation_id={correlation_id}")
            sys.stderr.write(f"[OLLAMA_REQUEST_END] call=1 duration_ms={ollama_time:.0f} correlation_id={correlation_id}\n")
            sys.stderr.flush()
            
            print(f"[TRACE] Ollama call complete, status: {response.status_code}")
            sys.stderr.write(f"[TRACE_STDERR] Ollama returned status {response.status_code}\n")
            sys.stderr.flush()
            
            if response.status_code == 200:
                response_text_for_logging = ''
                structured_generation_validated = False
                # Handle /api/chat response
                try:
                    response_data = response.json()
                    response_text = response_data.get("message", {}).get("content", "").strip()

                    if structured_spec:
                        structured_result = self._validate_structured_response(
                            response_text, structured_spec.get('requested_steps'), structured_spec.get('require_title', False)
                        )
                        # Ollama schemas constrain shape, but the model may still
                        # miss the requested count. Allow one bounded repair call.
                        if structured_result is None:
                            retry_messages = list(messages)
                            retry_messages.insert(0, {
                                "role": "system",
                                "content": "Return only valid JSON matching the supplied schema. Include the exact requested number of non-empty steps and a non-empty title when a heading was requested. Do not omit or merge steps.",
                            })
                            retry_payload = dict(request_payload)
                            retry_payload["messages"] = retry_messages
                            try:
                                retry_response = self._post_to_ollama(chat_endpoint, retry_payload)

                                if retry_response.status_code == 200:
                                    retry_data = retry_response.json()
                                    retry_content = retry_data.get("message", {}).get("content", "").strip()
                                    structured_result = self._validate_structured_response(
                                        retry_content, structured_spec.get('requested_steps'), structured_spec.get('require_title', False)
                                    )
                            except (requests.RequestException, ValueError, TypeError):
                                structured_result = None
                        if structured_result is None:
                            fallback = self._fallback_response(message, emotion, confidence, 'malformed_output')
                            fallback.setdefault('analysis', {})['structured_generation'] = 'fallback_invalid_output'
                            return fallback
                        response_text = self._structured_to_markdown(structured_result)
                        structured_generation_validated = True

                    response_length = len(response_text)
                except Exception as e:
                    print(f"[RESPONSE_ERROR] type={type(e).__name__}")
                    response_text = ""
                    response_length = 0

                # Content is never written to runtime diagnostics. Preserve only
                # metadata required to distinguish a malformed result from a slow one.
                print(f"[OLLAMA_RESPONSE_RECEIVED] status=200, length={response_length}")
                response_text = self._clean_response(response_text)
                if request_mode == 'creative':
                    response_text = self._strip_creative_preface(response_text)
                print(f"[OLLAMA_RESPONSE_CLEANED] length={len(response_text)}")
                quality_retry_reason = self._quality_retry_reason(
                    message, response_text, bool(structured_spec), conversation_history
                )
                if not quality_retry_reason:
                    quality_retry_reason = self._contextual_quality_retry_reason(
                        request_mode, message, response_text, conversation_history
                    )
                quality_retry_attempted = False
                quality_retry_applied = False
                response_origin = 'gemma'
                if quality_retry_reason:
                    # One bounded recovery call keeps generation model-driven while
                    # correcting only the measured failure mode. It uses the same
                    # authenticated Ollama endpoint and timeout as the primary call.
                    quality_retry_attempted = True
                    recovery_instruction = (
                        "QUALITY RECOVERY: Answer the current request directly with a grounded, "
                        "non-diagnostic first pass. For a where-to-start, prioritization, or planning "
                        "request, give a short actionable framework using only stated facts before one "
                        "optional question. For a purchase decision, do not tell the student to buy or "
                        "postpone without facts about necessity, the current device, cost, and affordability; "
                        "give conditional criteria and ask one targeted question after the framework. "
                        "Do not invent amounts, services, or circumstances. "
                        "For a shorter follow-up, rewrite only the previous answer's essential point in no more than "
                        "three concise sentences, with no preamble. For creative writing, output the requested "
                        "artifact immediately and omit commentary about the artifact."
                    )
                    if quality_retry_reason and quality_retry_reason.startswith("numeric_"):
                        recovery_instruction += (
                            " NUMERICAL RECOVERY: Use the DETERMINISTIC NUMERICAL CHECK exactly. "
                            "Keep every stated income and expense unchanged. Show the arithmetic. "
                            "A negative balance must be written as a deficit/shortfall with its negative sign; "
                            "do not invent or replace actual expenses with estimates."
                        )
                    if quality_retry_reason == 'academic_plan_without_first_pass':
                        recovery_instruction += (
                            ' ACADEMIC-FIRST-PASS RECOVERY: The student gave an immediate exam deadline. Give two to four ordered review actions now before any optional question. Do not invent calendar details, clock times, or study-hour availability.'
                        )
                    if quality_retry_reason == 'unsupported_loss_assumption':
                        recovery_instruction += (
                            ' FACT RECOVERY: The student described illness or injury, not a confirmed death. '
                            'Do not offer condolences or assume anyone or any animal died. Respond to the stated situation.'
                        )
                    if quality_retry_reason == 'invented_calendar_schedule':
                        recovery_instruction += (
                            ' DEADLINE RECOVERY: Keep the plan tied to the stated immediate deadline. Do not invent a weekday, clock time, or study-hours schedule. Give an ordered review strategy and ask for availability only as an optional follow-up.'
                        )
                    if quality_retry_reason == "financial_context_override":
                        recovery_instruction += (
                            " FINANCIAL-CONTEXT RECOVERY: The current request is an ordinary financial continuation. "
                            "Use the deterministic budget facts and the current actual or hypothetical state; do not ask an unrelated emotion question, "
                            "add a diagnostic disclaimer, or replace the requested arithmetic with generic support language."
                        )
                    if quality_retry_reason == "financial_summary_incomplete":
                        recovery_instruction += (
                            " FINANCIAL-SUMMARY RECOVERY: Separate the confirmed actual budget from hypothetical changes. "
                            "List every deterministic hypothetical scenario endpoint, including each intermediate amount, and label those changes as hypothetical; "
                            "do not present a scenario amount as a confirmed expense."
                        )
                    if quality_retry_reason == "food_budget_unsupported_claim":
                        recovery_instruction += (
                            " FOOD-BUDGET RECOVERY: The student supplied a hypothetical monthly range, not an income or current expense. "
                            "Do not call it potentially realistic or sufficient, do not invent an income, and do not create a made-up allocation. "
                            "Say that the range may be very restrictive for adequate meals, explain what meal-count and food-access facts are needed, "
                            "and never recommend skipping meals."
                        )
                    retry_messages = list(messages)
                    # Keep one authoritative system message so Ollama does not
                    # dilute the recovery instruction between two system roles.
                    if retry_messages and retry_messages[0].get("role") == "system":
                        retry_messages[0] = dict(retry_messages[0])
                        retry_messages[0]["content"] = str(retry_messages[0].get("content") or "") + "\n\n" + recovery_instruction
                    retry_payload = dict(request_payload)
                    retry_payload["messages"] = retry_messages
                    if quality_retry_reason == 'followup_not_shorter':
                        retry_payload["options"] = dict(request_payload.get("options") or {})
                        # An explicit shortening request gets a finite compact
                        # retry budget; ordinary generations keep their adaptive
                        # budget and are never globally truncated.
                        retry_payload["options"]["num_predict"] = min(
                            int(retry_payload["options"].get("num_predict", self.default_max_tokens)), 128
                        )
                    emit_diagnostic(
                        'hybrid', 'quality_recovery_start', correlation_id,
                        reason=quality_retry_reason, model=self.model,
                    )
                    try:
                        retry_response = self._post_to_ollama(chat_endpoint, retry_payload)

                        if retry_response.status_code == 200:
                            retry_data = retry_response.json()
                            retry_text = retry_data.get('message', {}).get('content', '')
                            retry_text = self._clean_response(str(retry_text or ''))
                            retry_quality_reason = self._quality_retry_reason(message, retry_text, False, conversation_history)
                            if not retry_quality_reason:
                                retry_quality_reason = self._numeric_quality_retry_reason(message, retry_text, conversation_history)
                            if not retry_quality_reason:
                                retry_quality_reason = self._contextual_quality_retry_reason(
                                    request_mode, message, retry_text, conversation_history
                                )
                            if retry_text and retry_quality_reason is None:
                                response_text = retry_text
                                quality_retry_applied = True
                    except (requests.RequestException, ValueError, TypeError):
                        pass
                    emit_diagnostic(
                        'hybrid', 'quality_recovery_complete', correlation_id,
                        reason=quality_retry_reason, model=self.model,
                        applied=quality_retry_applied,
                    )
                    if quality_retry_reason == 'unsupported_loss_assumption' and not quality_retry_applied:
                        response_text = ('Ayokong magpalagay tungkol sa nangyari. Kumusta na ngayon?' if detected_language in {'tl', 'taglish'}
                                         else "I don't want to assume what happened. How are things right now?")
                        response_origin = 'grounding_fallback'
                    if ((quality_retry_reason or '').startswith('numeric_') or quality_retry_reason in {'food_budget_unsupported_claim', 'financial_context_override', 'financial_summary_incomplete'}) and not quality_retry_applied:
                        numeric_fallback = numeric_fallback_response(message, conversation_history)
                        if numeric_fallback:
                            response_text = numeric_fallback
                            response_origin = 'deterministic_numeric_fallback'
                    if quality_retry_reason == 'followup_not_shorter' and not quality_retry_applied:
                        # An explicit shortening request must not return a longer
                        # answer merely because the bounded model retry ignored it.
                        previous = ''
                        for exchange in reversed(conversation_history or []):
                            if isinstance(exchange, dict) and exchange.get('ai'):
                                previous = str(exchange.get('ai') or '').strip()
                                break
                        words = previous.split()
                        if len(words) > 12:
                            response_text = ' '.join(words[:12]).rstrip('.,!?') + '.'
                        elif previous and len(previous) < len(response_text):
                            response_text = previous
                # VALIDATE the response - is it good quality?
                is_valid, reason = self._validate_response(response_text, emotion, confidence, message, detected_language)
                
                print(f"[VALIDATION_RESULT] is_valid={is_valid}, reason={reason}")
                print(f"[VALIDATION_DETAIL] msg_len={len(message)}, resp_len={len(response_text)}, emotion={emotion}, conf={confidence:.0%}")
                print(f"[VALIDATION_CHECKPOINT] About to check: if not is_valid={not is_valid}")
                
                if not is_valid:
                    print(f"[VALIDATION_REJECTED] Using fallback due to: {reason}")
                    print(f"[FALLBACK_TRIGGER_REASON] {reason}")
                    if reason in {'unsupported_diagnostic_assertion', 'unsupported_external_action_claim', 'guard_error'}:
                        # For a clearly numerical request, preserve the verified
                        # arithmetic instead of replacing it with an unrelated
                        # mental-health disclaimer.  The deterministic helper
                        # contains only explicitly supplied values; it cannot
                        # invent expenses or execute expressions.
                        numeric_fallback = numeric_fallback_response(message, conversation_history)
                        safe = numeric_fallback or safe_non_diagnostic_fallback(detected_language, self._detect_crisis(message))
                        guarded = self._fallback_response(message, emotion, confidence, 'safety_rejection')
                        guarded['response'] = safe
                        guarded['analysis']['model_type'] = 'deterministic_numeric_guard' if numeric_fallback else 'non_diagnostic_guard'
                        guarded['analysis']['guard_reason'] = reason
                        return guarded
                    return self._fallback_response(message, emotion, confidence, 'safety_rejection')
                
                print(f"[VALIDATION_PASSED] Moving past validation check, response_len={len(response_text)}")
                
                # If response is None or too short/generic, use fallback
                if not response_text:
                    print(f"[FALLBACK_TRIGGER_REASON] Empty response after clean")
                    return self._fallback_response(message, emotion, confidence, 'empty_output')
                
                print(f"[VALIDATION_ACCEPTED] Response approved: {reason}")
                
                # Apply response formatting: preserve paragraphs, add emotion-aware emoji
                print(f"[PRE_FORMAT_DISPLAY] About to call format_for_display with response_len={len(response_text)}")
                try:
                    response_text = format_for_display(response_text, emotion, confidence, message)
                    print(f"[POST_FORMAT_DISPLAY] format_for_display succeeded, len={len(response_text)}")
                except Exception as fmt_ex:
                    print(f"[FORMAT_DISPLAY_ERROR] Exception in format_for_display: {type(fmt_ex).__name__}: {str(fmt_ex)[:100]}")
                    print(f"[FORMAT_DISPLAY_FALLBACK] Using response without format_for_display")
                    pass  # Continue with unformatted response
                
                # ===== STEP 2 CONTINUED: FINAL TIMING SUMMARY (SESSION 8) =====
                total_time = (time.time() - start_time_total) * 1000
                print(f"\n[SESSION_8_STEP_2] REAL RESPONSE TIME MEASUREMENT - BASELINE")
                print(f"{'='*70}")
                print(f"[TIMING_TOTAL] {total_time:.0f}ms end-to-end (prompt build + Ollama call + cleanup)")
                print(f"[TIMING_BREAKDOWN]")
                print(f"  * Prompt build: {prompt_build_time:.0f}ms")
                print(f"  * Ollama call: {ollama_time:.0f}ms")
                print(f"  * Cleanup + validation: {total_time - prompt_build_time - ollama_time:.0f}ms")
                print(f"[OLLAMA_SYSTEM_PROMPT_CHARS] {len(system_prompt_short)} characters sent to Ollama")
                print(f"[RESPONSE_CHARS] {len(response_text)} characters returned")
                print(f"{'='*70}\n")
                
                return {
                    "success": True,
                    "response": response_text,
                    "emotion": emotion,
                    "confidence": confidence,
                    "analysis": {
                        "emotion": emotion,
                        "emotion_confidence": confidence,
                        "crisis_level": self._detect_crisis(message),
                        "model_type": "hybrid_trained_llm",
                        "sarcasm_detected": detected_sarcasm,
                        "generation_mode": "structured" if structured_spec else "natural",
                        "structured_validated": bool(structured_generation_validated),
                        "structured_step_count": (len(structured_result.get("steps", [])) if structured_spec and structured_generation_validated else None),
                        "quality_recovery_attempted": quality_retry_attempted,
                        "quality_recovery_applied": quality_retry_applied,
                        "quality_recovery_reason": quality_retry_reason,
                        "response_origin": response_origin,
                        "history_turns_used": len(conversation_history),
                        "history_char_budget": self.history_char_budget,
                        "gpu_queue_wait_ms": self._last_gpu_queue_wait_ms,
                        "gpu_queue_position": self._last_gpu_queue_position,
                    },
                    "ml_system": "hybrid_active",
                    "response_source": response_origin,
                    "powered_by": f"Trained emotion model ({emotion}, {confidence:.0%}) + {self.model} LLM"
                }
            else:
                raise Exception(f"LLM returned {response.status_code}")
                
        except (InferenceQueueBusy, InferenceAlreadyQueued) as queue_error:
            code = getattr(queue_error, 'code', 'GPU_QUEUE_BUSY')
            print(f"[GPU_QUEUE] code={code} detail={queue_error}")
            return {
                'success': False,
                'error_code': code,
                'error': 'The local AI is busy. Please wait briefly and retry.',
                'retry_after_seconds': 5,
            }
        except requests.ConnectionError:
            print("[ERROR_CAUGHT] requests.ConnectionError - Ollama not running")
            print("[ERROR] Ollama not running")
            return self._fallback_response(message, emotion, confidence, 'ollama_unavailable')
        except UnicodeEncodeError as e:
            # Detailed error info - be careful with printing
            print("[ERROR_CAUGHT] UnicodeEncodeError in generate()")
            # print(f"[ENCODING_ERROR] Can't encode character in prompt to cp1252")
            # print(f"[ENCODING_ERROR_POSITION] Around position {e.start}-{e.end}")
            # Don't try to print the actual problematic bytes
            return self._fallback_response(message, emotion, confidence, 'encoding_error')
        except Exception as e:
            error_type = type(e).__name__
            error_msg = str(e)
            print(f"[ERROR_CAUGHT] {error_type} in generate(): {error_msg[:100]}")
            print(f"[HYBRID_ERROR] {error_type}: (first 80 chars): {error_msg[:80]}")
            import traceback
            # Don't print traceback to avoid encoding issues
            return self._fallback_response(message, emotion, confidence, 'generation_error')
    
    def _detect_and_rewrite_task(self, message: str, conversation_history: List[Dict]) -> tuple:
        """
        Detect confirmation/follow-up messages and potentially clarify intent.
        Returns (message_possibly_enhanced, was_rewritten)
        
        CRITICAL CHANGE (DEBUGGING PASS 2): 
        Don't force "Give 3 tips" structure. Ollama can understand natural requests better.
        This was causing:
        - "any tips ?" -> "Give 3 concrete, specific tips..." -> rigid numbered lists
        - Loss of conversational flow
        
        NEW APPROACH: 
        - Simple confirmations: Return unchanged (yes/ok/sure)
        - Explicit requests: Return unchanged, let Ollama handle naturally
        - Don't rewrite to force structure - Ollama will naturally provide structure if appropriate
        """
        msg_lower = message.lower().strip()
        
        # For now, don't rewrite any messages
        # Ollama is capable of understanding:
        # - "any tips ?" as a request for suggestions
        # - "yes can you do that?" as a confirmation to take action
        # - "can you give me tips in my problems" as a contextual advice request
        # 
        # By passing the message unchanged, Ollama can:
        # 1. Understand the actual user intent
        # 2. Generate appropriate structure (not forced "3 tips")
        # 3. Reference conversation history naturally
        # 4. Provide conversational responses instead of templates
        
        # Make a short confirmation explicit to the model while retaining the
        # original conversation history as the source of facts. This prevents
        # Gemma from treating "Correct" or "Yes please" as a new ambiguous
        # request, without adding a second intent classifier or bypassing safety.
        if self._request_mode(message, conversation_history) == "confirmation":
            return (
                "The student confirms the immediately preceding exchange. Continue that same topic "
                "and give the next useful step now; do not ask what the confirmation refers to, "
                "restart the conversation, or switch topics unless the current message contains a safety concern.",
                True,
            )

        # Return other messages unchanged - no forced structure.
        return message, False

    def _infer_underlying_emotion(self, message: str, surface_emotion: str, 
                                  sarcasm_confidence: float, semantic_result: Dict = None) -> str:
        """
        When sarcasm is detected, infer the likely underlying emotion.
        
        Returns:
        - str: underlying emotion name (or surface_emotion if no sarcasm/unclear)
        
        Logic:
        - If sarcasm is low confidence: return surface_emotion
        - If sarcasm is high AND negative context exists: return inferred underlying emotion
        - Otherwise: use semantic analysis scores if available
        """
        
        # If no sarcasm, trust the surface emotion (LOWERED from 0.35 to 0.15 to catch mid-confidence sarcasm)
        if sarcasm_confidence < 0.15:
            return surface_emotion
        
        # With high sarcasm confidence, look for underlying emotion clues
        msg_lower = message.lower()
        
        # Check for stress/deadline indicators
        stress_keywords = ['deadline', 'exam', 'test', 'project', 'assignment', 'homework', 
                          'work', 'busy', 'overwhelm', 'stressed', 'anxiety', 'anxious']
        if any(kw in msg_lower for kw in stress_keywords):
            return 'anxiety'
        
        # Check for sadness/failure indicators
        sadness_keywords = ['failed', 'fail', 'bad', 'awful', 'terrible', 'sad', 'depressed', 
                           'lonely', 'broken', 'heartbreak', 'lost']
        if any(kw in msg_lower for kw in sadness_keywords):
            return 'sadness'
        
        # Check for frustration/anger indicators
        anger_keywords = ['frustrated', 'angry', 'mad', 'annoyed', 'irritated', 'fed up', 'pissed']
        if any(kw in msg_lower for kw in anger_keywords):
            return 'anger'
        
        # Use semantic result if available
        if semantic_result and semantic_result.get('emotion', {}).get('scores'):
            scores = semantic_result['emotion']['scores']
            
            # Get emotion with highest score
            if scores:
                # Exclude happiness since we're in sarcasm mode
                non_positive_scores = {k: v for k, v in scores.items() if k != 'happiness'}
                if non_positive_scores:
                    underlying = max(non_positive_scores, key=non_positive_scores.get)
                    return underlying
        
        # Fallback: if high sarcasm but no clues, assume anxiety/stress
        if sarcasm_confidence > 0.5:
            return 'anxiety'
        
        return surface_emotion

    def _detect_sarcasm(self, message: str) -> bool:
        """Detect sarcasm patterns: positive words with negative context"""
        msg_lower = message.lower()
        
        # Patterns: positive words followed by negative content
        sarcasm_patterns = [
            r'(?:absolutely|totally|so|definitely)?\s+(?:amazing|great|wonderful|fantastic|perfect)\s*[.,!]*\s*.*(?:destroy|struggling|sarcasm|dying|kill|suicide|hell)',
            r'(?:yeah|sure|right)\s*[.,!]*\s*.*(?:stress|deadline|soul|killing|destroy|dying)',
            r'(?:doing|feeling)\s+(?:amazing|great|wonderful)\s*.*(?:destroying|killing|dying|hell|nightmare)',
            r'(?:im|i[\'m])\s+(?:fine|okay|good).*(?:dying|soul|hell|nightmare|destroying)',
            r'sure.*(?:everything|fine).*(?:dying|inside|struggling)',
        ]
        
        for pattern in sarcasm_patterns:
            if re.search(pattern, msg_lower):
                print(f"[SARCASM_DETECTED] Pattern matched: {pattern[:30]}... in message")
                return True
        
        return False
    
    def _build_prompt(self, message: str, emotion: str, confidence: float, 
                     history: List[Dict], memory_context: str = None,
                     detected_sarcasm: bool = False, semantic_result: Dict = None,
                     context_result: Dict = None, unified_result = None, 
                     ollama_understanding: Dict = None, tone_emotion: str = None,
                     emotion_response_context: Dict = None) -> str:
        """Build LLM prompt with trained emotion context, long-term memory, and message understanding"""
        
        # CRITICAL FIX: When sarcasm is detected, determine underlying emotion
        sarcasm_conf = getattr(self, '_sarcasm_confidence', 0.0)
        print(f"[_BUILD_PROMPT_DEBUG] detected_sarcasm={detected_sarcasm}, sarcasm_conf={sarcasm_conf:.0%}")
        
        if detected_sarcasm and sarcasm_conf > 0.15:  # LOWERED from 0.35 to 0.15 to catch mid-confidence sarcasm
            # Infer the underlying emotion from context clues
            underlying_emotion = self._infer_underlying_emotion(message, emotion, sarcasm_conf, semantic_result)
            print(f"[SARCASM_OVERRIDE] surface_emotion={emotion}, sarcasm_conf={sarcasm_conf:.0%}, "
                  f"underlying_emotion={underlying_emotion}")
            
            # Use underlying emotion for tone instruction
            emotion_for_tone = underlying_emotion
            
            # Log this decision
            print(f"[SARCASM_PIPELINE] Using underlying emotion '{emotion_for_tone}' for tone instruction")
        else:
            emotion_for_tone = tone_emotion or emotion
            print(f"[SARCASM_PIPELINE] No sarcasm override needed (detected={detected_sarcasm}, conf={sarcasm_conf:.0%}), using generation tone: {emotion_for_tone}")
        
        # TONE MAPPING: Map detected emotions to specific response tones
        tone_map = {
            'happy': 'Be upbeat and positive. Share in their joy.',
            'sadness': 'Be empathetic and understanding. Validate their feelings.',
            'sad': 'Be empathetic and understanding. Validate their feelings.',
            'anger': 'Be validating. Don\'t dismiss their frustration.',
            'angry': 'Be validating. Don\'t dismiss their frustration.',
            'anxiety': 'Be calm and reassuring. Offer practical support.',
            'anxious': 'Be calm and reassuring. Offer practical support.',
            'neutral': 'Be conversational and natural. Match their casual tone.',
            'confused': 'Be clarifying and helpful. Help them think through it.',
            'hopeful': 'Be encouraging. Support their positive outlook.',
            'frustrated': 'Be understanding. Acknowledge the difficulty.',
            'joy': 'Be upbeat and positive. Share in their joy.',
        }
        
        print(f"[_BUILD_PROMPT_DEBUG] Looking up tone for emotion_for_tone='{emotion_for_tone}' (lowercase: '{emotion_for_tone.lower()}')")
        tone_instruction = tone_map.get(emotion_for_tone.lower(), f'Match their {emotion_for_tone} emotional state.')
        print(f"[_BUILD_PROMPT_DEBUG] Got tone_instruction: {tone_instruction}")
        
        # TRACK SECTION SIZES FOR DEBUGGING
        section_sizes = {}
        
        # START BUILDING PROMPT
        prompt = self.system_prompt + "\n\n"
        section_sizes['system_prompt'] = len(self.system_prompt)
        current_size = len(prompt)
        
        # INITIALIZE with safe defaults - used below, must be defined regardless of conditions
        inferred_topic = "general"
        inferred_intent = "seeking_support"
        inferred_severity = "low"
        is_question = message.strip().endswith('?')
        
        # Try to get topic from context_result
        if context_result and context_result.get('interaction_type'):
            interaction_type = context_result['interaction_type']
            # Map interaction type to topic (best effort)
            if 'question' in interaction_type.lower():
                inferred_intent = 'asking_question'
                inferred_topic = interaction_type.lower()
            elif 'advice' in interaction_type.lower():
                inferred_intent = 'asking_advice'
            elif 'vent' in interaction_type.lower():
                inferred_intent = 'venting'
        
        # If semantic_result has topic info, use that
        if semantic_result and semantic_result.get('context', {}).get('topic'):
            inferred_topic = semantic_result['context']['topic']
        
        # Determine severity from emotion
        if emotion and emotion.lower() in ['crisis', 'extreme']:
            inferred_severity = 'high'
        elif emotion and emotion.lower() in ['anxiety', 'anxious', 'angry', 'anger']:
            inferred_severity = 'medium'
        
        # SECTION 1: MESSAGE UNDERSTANDING & CONTEXT
        prompt += "=" * 60 + "\n"
        prompt += "MESSAGE UNDERSTANDING & CONTEXT\n"
        prompt += "=" * 60 + "\n"
        
        # REFACTORED: Show ONLY the inferred understanding (condensed)
        prompt += "[OK] INFERRED UNDERSTANDING (from message analysis):\n"
        
        # Extract topic keywords - only to detect which one matches
        topics_keywords = {
            'relationship': ['boyfriend', 'girlfriend', 'partner', 'spouse', 'dating', 'crush', 'cheating', 'breakup', 'jowa', 'kasintahan'],
            'work': ['work', 'job', 'boss', 'coworker', 'project', 'deadline', 'presentation', 'trabaho', 'opisina'],
            'school': ['school', 'class', 'exam', 'test', 'assignment', 'homework', 'grade', 'professor', 'aral', 'eskwela', 'tuition'],
            'family': ['mom', 'dad', 'mother', 'father', 'parent', 'sibling', 'brother', 'sister', 'nanay', 'tatay', 'pamilya'],
            'health': ['sick', 'illness', 'doctor', 'hospital', 'pain', 'health', 'may sakit', 'doktor'],
            'financial': ['money', 'finance', 'financial', 'budget', 'expense', 'utang', 'pera', 'gastusin', 'bayarin', 'pambayad', 'kita'],
        }
        
        msg_lower = message.lower()
        for topic, keywords in topics_keywords.items():
            if any(kw in msg_lower for kw in keywords):
                inferred_topic = topic
                break
        
        # CONDENSED OUTPUT: Only show detected info, not possibilities
        prompt += f"  Topic: {inferred_topic}\n"
        prompt += f"  Intent: {inferred_intent}\n"
        
        # Only show special markers if they apply
        if is_question:
            prompt += f"  Question: Yes\n"
        
        if detected_sarcasm or (semantic_result and semantic_result.get('tone', {}).get('sarcasm_score', 0) > 0.5):
            prompt += f"  Sarcasm/Irony: Detected\n"
        
        if inferred_severity != 'low':
            prompt += f"  Severity: {inferred_severity}\n"
        
        prompt += "\n"
        
        # SECTION 2: DETECTED EMOTION (context for response)
        prompt += "DETECTED EMOTION (context for response):\n"
        
        # Use unified_result if available for comprehensive analysis package
        if unified_result:
            # Compact analysis package from unified result (5-10 lines max)
            prompt += f"[UNIFIED] Emotion: {unified_result.emotion['primary']}\n"
            prompt += f"[UNIFIED] Confidence: {unified_result.emotion['confidence']:.0%}\n"
            
            # Add underlying emotion if detected
            if unified_result.sarcasm.get('underlying_emotion') and unified_result.sarcasm['underlying_emotion'] != unified_result.emotion['primary']:
                prompt += f"[UNIFIED] Underlying: {unified_result.sarcasm['underlying_emotion']}\n"
            
            # Add context tags (financial, academic, relationship, etc.)
            if unified_result.context:
                context_tags = [tag for tag, val in unified_result.context.items() if val]
                if context_tags:
                    prompt += f"[UNIFIED] Context: {', '.join(context_tags)}\n"
            
            # Add intent if detected
            if unified_result.intent and unified_result.intent.get('primary'):
                prompt += f"[UNIFIED] Intent: {unified_result.intent['primary']}\n"
            
            # Add figurative likelihood if applicable
            if unified_result.figurative and unified_result.figurative.get('confidence', 0) > 0.5:
                prompt += f"[UNIFIED] Figurative: {unified_result.figurative['confidence']:.0%}\n"
            
            # Add safety level
            prompt += f"[UNIFIED] Safety: {unified_result.crisis['safety_level']}\n"
            
            prompt += "\n"
        else:
            # Fallback to existing emotion display
            if semantic_result and semantic_result.get('emotion', {}).get('intensity') is not None:
                intensity = semantic_result['emotion']['intensity']
                
                if confidence > 0.8:
                    prompt += f"[OK] Emotion: {emotion.upper()}\n"
                    prompt += f"[OK] Confidence: {confidence:.0%} (HIGH)\n"
                    
                    if intensity > 0.8:
                        prompt += f"[OK] Intensity: {intensity:.0%} (VERY STRONG)\n"
                    elif intensity > 0.5:
                        prompt += f"[OK] Intensity: {intensity:.0%} (MODERATE)\n"
                    else:
                        prompt += f"[OK] Intensity: {intensity:.0%} (MILD)\n"
                elif confidence > 0.6:
                    prompt += f"~ Emotion: {emotion.upper()}\n"
                    prompt += f"~ Confidence: {confidence:.0%} (MODERATE)\n"
                else:
                    prompt += f"? Possible emotion: {emotion.upper()}\n"
                    prompt += f"? Confidence: {confidence:.0%} (LOW)\n"
            else:
                if confidence > 0.8:
                    prompt += f"[OK] Emotion: {emotion.upper()} ({confidence:.0%})\n"
                else:
                    prompt += f"~ Possible emotion: {emotion.upper()} ({confidence:.0%})\n"
        
        # NOTE: Tone instruction removed from here - it's specified in Response Instructions section below
        # This prevents duplication in the prompt (was appearing twice)
        
        # Add sarcasm warning if detected
        if detected_sarcasm:
            sarcasm_conf = getattr(self, '_sarcasm_confidence', 0.6)  # Use instance var if available
            prompt += f"[WARNING] User is using SARCASM or IRONY (confidence: {sarcasm_conf:.0%})\n"
            prompt += "Reason: The user may be expressing a different emotional meaning than literal words suggest.\n"
            prompt += "Examples: Positive words + stress context, exaggeration, sarcastic emojis, explicit markers.\n"
            prompt += "ACTION: Respond to the UNDERLYING emotional concern, not the literal positive wording.\n"
            
            # CRITICAL: Document that tone instruction has been adjusted
            if sarcasm_conf > 0.35:
                underlying_emotion = self._infer_underlying_emotion(message, emotion, sarcasm_conf, semantic_result)
                prompt += f"[OK] Underlying emotion identified: {underlying_emotion.upper()}\n"
                prompt += f"[OK] Response tone adjusted accordingly.\n"


        
        # Add NEGATION CONTEXT if detected
        if semantic_result and semantic_result.get('negation_detected'):
            prompt += "[WARNING] NOTE: Message contains negation (NOT, NEVER, CAN'T, etc.)\n"
            prompt += "This may flip the apparent emotion (e.g., 'NOT happy' = sadness, not happiness)\n"
            prompt += "Interpret emotional tone in context of negation.\n\n"
        # Add CRISIS SEMANTIC ANALYSIS if any indicators present
        if semantic_result and semantic_result.get('crisis_semantic_analysis'):
            crisis_data = semantic_result['crisis_semantic_analysis']
            
            # FIX 5B: Only show MEANINGFUL crisis indicators (higher thresholds to reduce noise)
            # Only include if at least one indicator exceeds threshold
            crisis_indicators = []
            if crisis_data.get('planning', 0) > 0.6:  # Raised from 0.4
                crisis_indicators.append(f"planning ({crisis_data['planning']:.0%})")
            if crisis_data.get('intent', 0) > 0.5:  # Raised from 0.3
                crisis_indicators.append(f"intent ({crisis_data['intent']:.0%})")
            if crisis_data.get('temporal_urgency', 0) > 0.7:  # Raised from 0.4 to filter out "today" false positives
                crisis_indicators.append(f"temporal urgency ({crisis_data['temporal_urgency']:.0%})")
            if crisis_data.get('ideation', 0) > 0.5:  # Raised from 0.4
                crisis_indicators.append(f"ideation ({crisis_data['ideation']:.0%})")
            if crisis_data.get('distress', 0) > 0.8:  # Raised from 0.7
                crisis_indicators.append(f"severe distress ({crisis_data['distress']:.0%})")
            
            # ONLY add crisis section if there are actual indicators
            if crisis_indicators:
                prompt += "[WARNING] CRISIS SEMANTIC INDICATORS:\n"
                prompt += ", ".join(crisis_indicators) + "\n"
                prompt += "Be vigilant for crisis warning signs. Prioritize safety.\n"
                prompt += "Encourage professional support if appropriate.\n\n"
        
        # SECTION 2: LONG-TERM MEMORY (if available) - Prioritized
        if memory_context:
            prompt += "=" * 60 + "\n"
            prompt += "RELEVANT CONTEXT (may be incomplete; current message wins)\n"
            prompt += "=" * 60 + "\n"
            prompt += memory_context + "\n\n"
        
        # SECTION 3: CURRENT-SESSION CONTEXT - bounded for reliable long conversations
        if history and len(history) > 0:
            prompt += "=" * 60 + "\n"
            prompt += "CONVERSATION SO FAR (context only - current message is priority)\n"
            prompt += "=" * 60 + "\n"
            prompt += "[WARNING] NOTE: Previous messages provide context,\n"
            prompt += "but THIS CURRENT MESSAGE is the primary focus.\n"
            prompt += "User's emotions may have SHIFTED from previous messages.\n"
            prompt += "Do NOT assume current emotion matches previous tone.\n"
            prompt += "[OK] If user is responding to YOUR offer/question: FOLLOW THROUGH with what you offered.\n\n"
            
            # Extract emotional tone from recent history
            recent_emotions = []
            if len(history) > 0:
                last_exchange = history[-1]
                if last_exchange.get('emotion'):
                    recent_emotions.append(last_exchange.get('emotion'))
            
            if recent_emotions:
                prompt += f"Previous emotion: {recent_emotions[0]}\n"
                prompt += "(Use for context, but prioritize current message emotion)\n\n"
            
            # Keep current-session context bounded; older session context is supplied separately.
            exchanges_to_show = history[-self.prompt_history_turns:] if len(history) > self.prompt_history_turns else history
            if exchanges_to_show:
                prompt += f"Recent Exchanges ({len(exchanges_to_show)}):\n"
                for i, exchange in enumerate(exchanges_to_show, 1):
                    user_msg = exchange.get('user', '')
                    ai_msg = exchange.get('ai', '')
                    if user_msg or ai_msg:
                        prompt += f"  Exchange {i}:\n"
                        if user_msg:
                            # Truncate long messages but preserve key details
                            truncated_user = (user_msg[:120] + "...") if len(user_msg) > 120 else user_msg
                            prompt += f"    User: {truncated_user}\n"
                        if ai_msg:
                            # Truncate long messages but preserve key details
                            truncated_ai = (ai_msg[:120] + "...") if len(ai_msg) > 120 else ai_msg
                            prompt += f"    AI: {truncated_ai}\n"
            prompt += "\n"
        
        # SECTION 4: CURRENT MESSAGE - Make it IMPOSSIBLE to miss
        prompt += "=" * 60 + "\n"
        prompt += "WHAT THE USER JUST SAID (PRIMARY FOCUS)\n"
        prompt += "=" * 60 + "\n"
        prompt += f"\"{message}\"\n\n"
        
        explicit_signal = self._explicit_message_signal(message)
        if explicit_signal.get('instruction'):
            prompt += "CURRENT-MESSAGE EVIDENCE (higher priority than the model label):\n"
            prompt += explicit_signal['instruction'] + "\n\n"
        # SECTION 5: RESPONSE INSTRUCTIONS - INTERNAL METADATA FORMAT (NOT OUTPUT-LIKE)
        # FIX: Changed from numbered instruction lists to internal metadata to prevent Ollama from copying the format
        prompt += "=" * 60 + "\n"
        prompt += "YOUR RESPONSE\n"
        prompt += "=" * 60 + "\n"
        prompt += f"{tone_instruction}\n\n"
        
        # TASK 3: Use intent to route response behavior - using internal metadata format
        # This format is NOT designed to be copied as output by the LLM
        if unified_result and unified_result.intent and unified_result.intent.get('primary'):
            intent_type = unified_result.intent['primary'].lower()
            
            if intent_type == 'question':
                prompt += "[ROUTING] response_type=direct_answer intent_context=question priority=factual_answer_first tone=professional_but_supportive [/ROUTING]\n"
                prompt += "For factual questions: answer directly and specifically. If user shows distress (from emotion analysis), add supportive framing after the answer.\n"
            elif intent_type == 'statement' or intent_type == 'venting':
                prompt += "[ROUTING] response_type=emotional_support intent_context=venting priority=acknowledge_feeling_then_support tone=empathetic [/ROUTING]\n"
                prompt += "Acknowledge what they're feeling. Show you understand their specific situation. Then offer support if appropriate.\n"
            elif intent_type == 'help_request':
                prompt += "[ROUTING] response_type=concrete_help intent_context=help_request priority=specific_actionable_suggestions tone=practical [/ROUTING]\n"
                prompt += "Provide concrete, actionable help. For advice/tips requests: give 2-4 practical options, brief explanation for each. Ask clarifying questions only if essential.\n"
            elif intent_type == 'greeting':
                prompt += "[ROUTING] response_type=greeting intent_context=greeting priority=natural_warm_brief tone=friendly [/ROUTING]\n"
                prompt += "Return a natural, brief greeting. Match their tone and style.\n"
            else:
                prompt += "[ROUTING] response_type=conversational intent_context=general priority=respond_to_actual_message tone=natural [/ROUTING]\n"
                prompt += "Respond to what they actually said. Be specific and natural. Avoid generic responses.\n"
        else:
            # Detect help request from message content if intent not explicitly detected
            msg_lower = message.lower()
            has_help_keywords = any(kw in msg_lower for kw in ['tips', 'suggestions', 'any suggestions', 'pwede', 'ano', 'paano', 'how to', 'what should', 'what can'])
            is_advice_seeking = any(phrase in msg_lower for phrase in ['can i', 'can i do', 'should i', 'need help', 'having trouble', 'struggling', 'mahirap', 'problema', 'problem', 'difficulty'])
            is_complex_message = len(message) > 80 or message.count('?') > 1
            
            if has_help_keywords or (is_advice_seeking and is_complex_message):
                # Multi-point advice/help request
                prompt += "[ROUTING] response_type=practical_help intent_context=help_request priority=useful_guidance tone=supportive_practical [/ROUTING]\n"
                prompt += "Provide substantive, multi-point guidance. Acknowledge their situation first, then give 2-4 concrete, practical options or steps. Keep each option brief but complete.\n"
            else:
                prompt += "[ROUTING] response_type=conversational intent_context=default priority=respond_to_actual_message tone=natural [/ROUTING]\n"
                prompt += "Respond to what they said. Be specific, natural, and conversational. Avoid generic phrases.\n"
        
        prompt += "\nRespond now:\n"
        
        # DEBUG: Log actual prompt length and section breakdown
        prompt_length = len(prompt)
        memory_len = len(memory_context) if memory_context else 0
        history_len_chars = sum(len(h.get('user', '')) + len(h.get('ai', '')) for h in (history or []))
        
        # Do not write prompts or student/model content to local diagnostic files.
        # Metadata-only prompt sizing is emitted through the development diagnostic
        # event at generation start/completion instead.
        
        # ===== STEP 1: CAPTURE REAL PROMPT SIZE BREAKDOWN (SESSION 8, BASELINE) =====
        # Suppress problematic print statements to avoid encoding errors
        # print(f"\n{'='*70}")
        # print(f"[SESSION_8_STEP_1] REAL PROMPT SIZE BREAKDOWN - BASELINE CAPTURE")
        # print(f"{'='*70}")
        # print(f"[PROMPT_TOTAL] {prompt_length} characters (REAL, NOT ESTIMATED)")
        print(f"\nSection Breakdown:")
        print(f"  * System prompt: {len(self.system_prompt)} chars")
        print(f"  * Message understanding (inferred topic, intent, sarcasm): ~400 chars")
        print(f"  * Emotion detection + crisis analysis: ~300 chars")
        print(f"  * Memory context (long-term background): {memory_len} chars")
        print(f"  * Recent conversation history ({len(history) if history else 0} exchanges): {history_len_chars} chars")
        print(f"  * Response instructions (tone + 12 rules): ~500 chars")
        print(f"  * Current message + formatting/separators: {prompt_length - len(self.system_prompt) - 400 - 300 - memory_len - history_len_chars - 500} chars")
        print(f"\nRatio breakdown:")
        print(f"  * System prompt: {len(self.system_prompt) / prompt_length * 100:.1f}% of total")
        print(f"  * Memory context: {memory_len / prompt_length * 100:.1f}% of total")
        print(f"  * History: {history_len_chars / prompt_length * 100:.1f}% of total")
        print(f"  * Instructions + analysis: {(400 + 300 + 500) / prompt_length * 100:.1f}% of total")
        print(f"\nUser message length: {len(message)} chars")
        print(f"Emotion: {emotion} ({confidence:.0%})")
        print(f"Sarcasm detected: {detected_sarcasm}")
        print(f"\n[SESSION_8_STEP_1_PROMPT_SIZE] Prompt length: {prompt_length} chars")
        # print(f"[FULL_PROMPT_DUMP] Length={prompt_length}")
        # print(f"[FIRST_300_CHARS]\n{prompt[:300]}\n...")
        # print(f"[LAST_300_CHARS]\n...{prompt[-300:]}")
        print(f"{'='*70}\n")
        
        print(f"\n[HYBRID_GENERATE_START] Prompt size: {prompt_length} chars, history: {len(history) if history else 0} exchanges")
        # print(f"\n[PROMPT_BREAKDOWN]")
        # print(f"[PROMPT_TOTAL] {prompt_length} chars")
        print(f"  - System prompt: ~{len(self.system_prompt)} chars")
        print(f"  - Memory context: {memory_len} chars")
        print(f"  - History ({len(history) if history else 0} exchanges): {history_len_chars} chars")
        # print(f"  - Message understanding: ~400 chars")
        # print(f"  - Emotion detection section: ~300 chars")
        # print(f"  - Response instructions: ~500 chars")
        # print(f"  - Other sections (conversation, current message): ~{prompt_length - len(self.system_prompt) - memory_len - history_len_chars - 400 - 300 - 500}")
        #
        # print(f"\n[DEBUG_PROMPT] length={prompt_length} chars | memory_len={memory_len} | history_exchanges={len(history) if history else 0}")
        # print(f"[FIRST_500_CHARS]\n{prompt[:500]}")
        
        return prompt
    
    def _clean_response(self, response: str) -> str:
        """Clean LLM response - remove artifacts and keep only the actual response"""
        import re
        
        # Try to decode JSON-encoded string first
        try:
            if response.startswith('"') and response.endswith('"'):
                response = json.loads(response)
        except:
            pass
        
        response = response.strip()
        
        # Remove common prompt artifacts (exact matches at start)
        artifacts = [
            "Respond now:",
            "YOUR RESPONSE",
            "Respond:",
            "Response:",
            "AI:",
            "Assistant:",
        ]
        
        for artifact in artifacts:
            if response.startswith(artifact):
                response = response[len(artifact):].strip()
        
        # FIX: Remove tone instruction patterns that leak into response
        # Normalize whitespace first for better pattern matching
        # Keep the model's paragraphs and list lines intact for the safe frontend renderer.
        response_normalized = response
        
        # Try specific tone instruction patterns
        tone_patterns = [
            r'^Be\s+calm\s+and\s+reassuring\s*\.\s+',
            r'^Be\s+upbeat\s+and\s+positive\s*\.\s+',
            r'^Be\s+empathetic\s+and\s+understanding\s*\.\s+',
            r'^Be\s+validating\s*\.\s+',
            r'^Be\s+conversational\s+and\s+natural\s*\.\s+',
            r'^Be\s+clarifying\s+and\s+helpful\s*\.\s+',
            r'^Be\s+encouraging\s*\.\s+',
            r'^Be\s+understanding\s*\.\s+',
            r'^Match\s+their\s+[^\.]*\s+emotional\s+state\s*\.\s+',
            r'^Share\s+in\s+their\s+[^\.]*\s*\.\s+',
            r'^Offer\s+practical\s+[^\.]*\s*\.\s+',
            r'^Help\s+them\s+think\s+through\s+it\s*\.\s+',
        ]
        
        for pattern in tone_patterns:
            if re.search(pattern, response_normalized, flags=re.IGNORECASE):
                response_normalized = re.sub(pattern, '', response_normalized, flags=re.IGNORECASE)
                print(f"[CLEANUP_DEBUG] Matched and removed tone pattern from response")
                break
        
        # Generic fallback: Remove any leading "Be [adjective]" phrase followed by period
        # This catches patterns like "Be [word] [word]... . "
        response_normalized = re.sub(r'^Be\s+\w+(?:\s+\w+)*\s*\.\s+', '', response_normalized, flags=re.IGNORECASE)
        
        # Further pattern: "Offer/Match/Share..." instructions
        response_normalized = re.sub(r'^(?:Offer|Match|Share|Help|Acknowledge|Don\'t)\s+[^\.]+\.\s+', '', response_normalized, flags=re.IGNORECASE)
        
        response = response_normalized.strip()
        response = self._strip_bracketed_interjections(response)
        
        # Remove repetitive AI markers
        lines = response.split('\n')
        cleaned_lines = []
        for line in lines:
            if not any(marker in line for marker in ["===", "[EMOTION", "[RESPONSE", "[LONG-TERM"]):
                cleaned_lines.append(line)
        
        response = '\n'.join(cleaned_lines).strip()
        
        # IMPORTANT: Preserve intentional newlines for readability
        # Do NOT collapse all whitespace into a single line
        # The model naturally separates thoughts with newlines - preserve that structure
        # Only normalize excessive blank lines (more than one blank line between thoughts)
        response = re.sub(r'\n\n\n+', '\n\n', response)  # Collapse 3+ newlines into 2
        cleaned_response_for_logging = response
        response = ''
        
        print(f"[CLEANUP_RESULT] final_length={len(response)}")
        response = cleaned_response_for_logging
        return response
    
    @staticmethod
    def _strip_bracketed_interjections(response: str) -> str:
        """Remove model filler brackets without touching meaningful markup."""
        text = str(response or '').strip()
        if not text or '```' in text:
            return text

        leading = re.match(r'^\[([^\]\n]{1,80})\]\s*', text)
        if leading:
            inner = leading.group(1).strip().casefold().rstrip('.!?')
            allowed = {
                'talaga', 'okay', 'ok', 'sige', 'sure', 'of course', 'great',
                'nice', 'wow', 'tama', 'ayos', 'got it', 'understood',
                'salamat', 'thanks',
            }
            if inner in allowed:
                text = text[leading.end():].lstrip()

        trailing = re.search(r'\s*\[([^\]\n]{1,80})\]\s*$', text)
        if trailing:
            inner = trailing.group(1).strip().casefold()
            # Keep this deliberately narrow: only conversational hand-off
            # questions are removed; quoted/code/math brackets are preserved.
            if re.fullmatch(r"okay\s+ba\s+(?:[\u2018\u2019'`]yun|yun|ito|iyon)\?", inner):
                text = text[:trailing.start()].rstrip()

        return text
    def _validate_response(self, response: str, emotion: str, confidence: float,
                          message: str, language: str = 'en') -> tuple:
        """
        Validate if response is appropriate and specific
        Situation-aware responses that address the actual message are preferred
        
        CRITICAL FIX (PHASE 1): Detect and reject obvious internal instruction leakage
        Accept legitimate Ollama-generated responses and numbered lists
        
        Returns:
            (is_valid: bool, reason: str)
        """
        
        # Check 1: Empty response
        if not response or len(response.strip()) == 0:
            return False, "Empty response"
        
        # Deterministic Gate 3 guard for unsupported clinical or external-action claims.
        _, safety_reason = validate_non_diagnostic_output(response)
        if safety_reason != 'accepted':
            return False, safety_reason

        # Check 2: Response is way too short (less than 3 words)
        word_count = len(response.split())
        if word_count < 3:
            return False, f"Too short ({word_count} words)"
        
        # FIX (PHASE 1): DETECT INTERNAL INSTRUCTION LEAKAGE
        # Reject responses that contain CLEAR MARKERS of internal instruction text
        # BUT: Do NOT reject legitimate numbered lists like "1. Take a break. 2. Work on assignments."
        response_lower = response.lower()
        
        # Patterns that indicate INTERNAL INSTRUCTION TEXT (should be rejected)
        # These are patterns that show the LLM is outputting its instructions, not a user-facing response
        instruction_leakage_patterns = [
            r'\bvalidate and support\b',  # Classic internal marker
            r'\[routing\]',  # Internal metadata tags
            r'\[/routing\]',  # Internal metadata closing tags
            r'\[emotional_statement\]',  # Internal category markers
            r'\[question\]',  # Internal category markers
            r'\baacknowledge their feelings\b.*\bfirst\b',  # Clear instruction pattern (with typo check)
            r'\backnowledge their feelings\b.*\bfirst\b',  # Clear instruction pattern
            r'\bshow you understand\b',  # Clear instruction marker (only if alone, not in context)
            r'\bthen offer\b.*\b(support|suggestions|guidance|help)\b',  # Clear instruction pattern
            r'^1\.\s*(acknowledge|answer|provide|return|respond to|validate)\b',  # Numbered instructions at start
            r'^1\.\s*[A-Z].*\n2\.\s*[A-Z].*\n3\.\s*[A-Z]',  # Multi-numbered instruction blocks
        ]
        
        # Count how many instruction leakage patterns are present
        leakage_count = 0
        detected_patterns = []
        
        for pattern in instruction_leakage_patterns:
            if re.search(pattern, response_lower, re.MULTILINE):
                leakage_count += 1
                detected_patterns.append(pattern)
        
        # If 2+ instruction patterns detected, it's definitely leakage
        if leakage_count >= 2:
            return False, f"Detected multiple instruction leakage patterns"
        
        # If exactly 1 leakage pattern, check context to distinguish from legitimate response
        if leakage_count == 1:
            # Allow "show you understand" if it's part of natural speech
            if 'show you understand' in detected_patterns[0]:
                # If this is the ONLY pattern and response is longer, might be legitimate
                if len(response) > 200:
                    # Probably legitimate - ignore this single pattern
                    pass
                else:
                    return False, "Detected instruction leakage: show you understand"
            else:
                return False, f"Detected instruction leakage"
        
        # Check 3: REMOVED - Don't penalize generic responses
        # Reason: Ollama generating generic responses is better than using hardcoded fallback
        
        # Check 4: RELAXED Emotional tone mismatch - only reject SEVERE mismatches
        if confidence > 0.90:  # Only check for VERY HIGH confidence
            emotion_lower = emotion.lower()
            
            # Only reject if response is HOSTILE to the emotion
            if emotion_lower in ["happy", "joy", "happiness", "excited"]:
                # Positive emotion should not be met with dismissal of the positive
                # But can acknowledge challenges
                if response_lower.count("sorry") > 2 or "that's terrible" in response_lower:
                    return False, "Hostile tone for positive emotion"
            
            elif emotion_lower in ["sad", "sadness", "depressed", "grief"]:
                # Sad emotion should not be dismissed
                dismiss_phrases = ["just relax", "cheer up", "it's not that bad"]
                if any(phrase in response_lower for phrase in dismiss_phrases):
                    return False, "Dismissive tone for sad emotion"
        
        return True, "Valid (response passed validation)"
    
    def _detect_crisis(self, message: str) -> str:
        """Simple crisis detection - backup to ML-based detection"""
        crisis_keywords = [
            'want to die', 'suicide', 'kill myself', 'harm myself',
            'cut myself', 'going to kill', 'hopeless', 'worthless'
        ]
        
        msg_lower = message.lower()
        for keyword in crisis_keywords:
            if keyword in msg_lower:
                return 'critical'
        return 'none'
    
    def _fallback_response(self, message: str, emotion: str, confidence: float, reason: str = 'ollama_unavailable') -> Dict:
        """Fallback response when LLM unavailable - context-aware and emotion-matched"""
        # Metadata-only reason code; never include message contents or model output.
        self._last_fallback_reason = reason
        # Still use emotion to inform fallback
        fallback_responses = {
            "sadness": [
                "I can hear the sadness in what you're sharing. I'm here to listen. Can you tell me more?",
                "That sounds really tough. It's okay to feel this way. What's weighing on you most?"
            ],
            "sadness_alt": [  # Alternative names for sadness
                "I can hear the sadness in what you're sharing. I'm here to listen. Can you tell me more?",
                "That sounds really tough. It's okay to feel this way. What's weighing on you most?"
            ],
            "sad": [
                "I can hear the sadness in what you're sharing. I'm here to listen. Can you tell me more?",
                "That sounds really tough. It's okay to feel this way. What's weighing on you most?"
            ],
            "anxiety": [
                "It sounds like something's weighing on you and causing some worry. I'm here to help you work through it.",
                "I can sense some anxiety in what you're saying. Let's talk about what's worrying you.",
            ],
            "anxious": [
                "It sounds like something's weighing on you and causing some worry. I'm here to help you work through it.",
                "I can sense some anxiety in what you're saying. Let's talk about what's worrying you.",
            ],
            "anger": [
                "I can tell you're frustrated about something. That's a valid feeling. What's been getting to you?",
                "It sounds like you're upset, and that's completely understandable. What triggered this?"
            ],
            "angry": [
                "I can tell you're frustrated about something. That's a valid feeling. What's been getting to you?",
                "It sounds like you're upset, and that's completely understandable. What triggered this?"
            ],
            "joy": [
                "That's wonderful to hear! Tell me more about what's making you happy.",
                "It sounds like something good is happening! I'd love to hear more about it."
            ],
            "happy": [
                "That's wonderful to hear! Tell me more about what's making you happy.",
                "It sounds like something good is happening! I'd love to hear more about it."
            ],
            "happiness": [
                "That's wonderful to hear! Tell me more about what's making you happy.",
                "It sounds like something good is happening! I'd love to hear more about it."
            ],
            "neutral": [
                "I'm here to listen and support you. What's on your mind?",
                "Thanks for sharing that. Can you tell me more about it?"
            ]
        }
        
        # Prefer explicit current-message wording for fallback tone as well. This
        # does not alter the authoritative emotion returned to Laravel/history.
        emotion_lower = emotion.lower() if emotion else "neutral"
        explicit_signal = self._explicit_message_signal(message)
        if self._detect_crisis(message) != 'critical' and explicit_signal.get('kind') == 'positive':
            category = 'joy'
        elif self._detect_crisis(message) != 'critical' and explicit_signal.get('kind') == 'concern':
            category = 'anxiety'
        else:
            category = emotion_lower if emotion_lower in fallback_responses else "neutral"
        
        responses = fallback_responses.get(category, fallback_responses["neutral"])
        response = responses[0]  # Use first response as default
        
        return {
            "success": True,
            "response": response,
            "emotion": emotion,
            "confidence": confidence,
            "analysis": {
                "emotion": emotion,
                "emotion_confidence": confidence,
                "crisis_level": self._detect_crisis(message),
                "model_type": "hybrid_fallback"
            },
            "ml_system": "hybrid_fallback",
            "powered_by": f"Trained emotion model ({emotion}, {confidence:.0%}) + fallback response",
            # Internal diagnostics only; controllers must not expose this to students.
            "fallback_reason": reason
        }


# Global instance
_hybrid_engine = None

def init_hybrid_engine(ollama_url="http://localhost:11434", model="gemma3:4b"):
    """Initialize the hybrid engine - PHASE 2: Using gemma3:4b"""
    global _hybrid_engine
    _hybrid_engine = HybridResponseEngine(ollama_url=ollama_url, model=model)
    
    if _hybrid_engine.test_connection():
        print(f"[OK] Hybrid Response Engine initialized")
        print(f"[INFO] [OK] Trained Emotion Model + {model} LLM working together")
        print(f"[INFO] NO TEMPLATES - Pure learned intelligence")
        return True
    else:
        print(f"[WARNING] Ollama not available at {ollama_url}")
        return False

def generate_hybrid_response(message: str, emotion: str, confidence: float,
                            conversation_history: List[Dict] = None,
                            memory_context: str = None,
                            semantic_result: Dict = None,
                            context_result: Dict = None,
                             unified_result = None,
                             ollama_understanding: Dict = None,
                             counselor_context: Dict = None,
                             emotion_response_context: Dict = None) -> Dict:
    """
    Generate response using trained model + LLM hybrid + long-term memory
    
    PHASE 1: Now also uses Ollama understanding to inform response generation
    This is the ACTUAL system you should use instead of templates
    """
    global _hybrid_engine
    
    if _hybrid_engine is None:
        init_hybrid_engine()
    
    if _hybrid_engine is None:
        return {"success": False, "error": "Hybrid engine not initialized"}
    
    result = _hybrid_engine.generate(message, emotion, confidence, conversation_history,
                                       memory_context, semantic_result, context_result,
                                        unified_result, ollama_understanding, counselor_context,
                                        emotion_response_context)
    if result.get('ml_system') == 'hybrid_active':
        result.setdefault('response_source', 'gemma')
        result.setdefault('model_used', _hybrid_engine.model)
    elif result.get('ml_system') == 'hybrid_fallback':
        result.setdefault('response_source', 'fallback')
        result.setdefault('fallback_reason', getattr(_hybrid_engine, '_last_fallback_reason', 'unknown'))
    return result

