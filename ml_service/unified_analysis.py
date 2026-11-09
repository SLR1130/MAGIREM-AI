"""
UNIFIED ANALYSIS LAYER - Clean Pipeline for Emotion + Context
Built for Debugging Pass 3

This module consolidates all analysis signals into ONE structured result:
- VADER sentiment
- Semantic emotion scores
- RoBERTa supporting classification (if available)
- Sarcasm/figurative detection
- Crisis safety analysis
- Intent classification
- Language/context detection

The result is designed for:
1. Ollama to understand context clearly
2. Logging/debugging to be transparent
3. Safety systems to function independently
4. Frontend to receive rich emotion information
"""

from typing import Dict, List, Optional, Tuple
import re
import json

class UnifiedAnalysisResult:
    """
    Structured result containing all analysis signals
    This is THE authoritative analysis for a user message
    """
    
    def __init__(self):
        self.language = {
            'primary': 'english',
            'detected_taglish': False,
            'detected_tagalog': False,
            'confidence': 1.0
        }
        
        self.sentiment = {
            'compound': 0.0,          # VADER: -1 to +1
            'positive': 0.0,          # VADER: positive score
            'neutral': 0.0,           # VADER: neutral score
            'negative': 0.0           # VADER: negative score
        }
        
        self.emotion = {
            'primary': 'neutral',
            'secondary': [],
            'confidence': 0.0,
            'scores': {
                'happiness': 0.0,
                'sadness': 0.0,
                'anxiety': 0.0,
                'anger': 0.0,
                'neutral': 0.0
            },
            'source': 'unknown'  # 'semantic', 'roberta', 'keyword', etc
        }
        
        self.intent = {
            'primary': 'unknown',     # 'statement', 'question', 'request', 'greeting', etc
            'confidence': 0.0
        }
        
        self.sarcasm = {
            'detected': False,
            'confidence': 0.0,
            'underlying_emotion': None,
            'signals': {}  # Individual sarcasm signal scores
        }
        
        self.figurative = {
            'detected': False,
            'type': None,             # 'hyperbole', 'idiom', 'death_idiom', etc
            'confidence': 0.0
        }
        
        self.crisis = {
            'safety_level': 'low',     # 'low', 'medium', 'high', 'critical'
            'direct_self_harm': False,
            'passive_death_wish': False,
            'figurative_language': False,
            'sarcasm_detected': False,
            'emotional_distress_level': 0.0,  # 0-1 scale
            'crisis_keywords': [],
            'reasoning': '',
            'roberta_score': None  # If RoBERTa was consulted
        }
        
        self.context = {
            'academic': False,
            'financial': False,
            'relationship': False,
            'work': False,
            'family': False,
            'social': False,
            'health': False,
            'loneliness': False,
            'primary_domain': None
        }
        
        self.negation = {
            'detected': False,
            'type': None,             # 'simple' (not), 'complex' (don't know what to do)
            'negates': None           # What is being negated ('happiness', 'emotion', 'uncertainty', etc)
        }
        
        self.roberta_support = {
            'available': False,
            'scores': {},            # If available: {'anxiety': 0.7, 'sadness': 0.2, ...}
            'confidence': None,
            'used': False            # Whether it was actually used in final decision
        }
        
        self.fusion_log = []        # Human-readable reasoning
    
    def to_dict(self) -> Dict:
        """Convert to dictionary for JSON serialization"""
        return {
            'language': self.language,
            'sentiment': self.sentiment,
            'emotion': self.emotion,
            'intent': self.intent,
            'sarcasm': self.sarcasm,
            'figurative': self.figurative,
            'crisis': self.crisis,
            'context': self.context,
            'negation': self.negation,
            'roberta_support': self.roberta_support
        }
    
    def log(self, message: str):
        """Add entry to fusion log"""
        self.fusion_log.append(message)


class UnifiedAnalyzer:
    """
    Main analyzer that consolidates all signals into UnifiedAnalysisResult
    
    Pipeline:
    1. Language detection
    2. VADER sentiment analysis
    3. Semantic emotion analysis
    4. RoBERTa query (if available)
    5. Sarcasm/figurative detection
    6. Crisis safety analysis
    7. Intent classification
    8. FUSION: Combine all signals
    """
    
    def __init__(self):
        """Initialize unified analyzer"""
        self.crisis_keywords = [
            'want to die', 'suicide', 'suicidal', 'kill myself', 'kill myself now',
            'harm myself', 'self harm', 'cut myself', 'going to kill', 'hopeless',
            'worthless', 'no point', 'end it', 'end it all', 'going to end it',
            'going to harm', 'overdose', 'jump', 'hang myself'
        ]
        
        self.figurative_death_idioms = [
            "could die of",     # "could die of embarrassment"
            "dying inside",     # "dying inside"
            "kill me",         # "kill me that's funny"
            "dying of",        # "dying of laughter"
            "dead inside",     # "dead inside (emotionally)"
            "killed me",       # "killed me (made me laugh)"
            "dying",           # "I'm dying (laughing)" with emoji
            "die",             # "I'll die" (in figurative context)
        ]
    
    def analyze(self, message: str, conversation_history: List[Dict] = None,
                semantic_result: Dict = None, roberta_scores: Dict = None) -> UnifiedAnalysisResult:
        """
        Complete analysis pipeline
        
        Args:
            message: User's message
            conversation_history: Previous messages for context
            semantic_result: Output from semantic_analysis.py (if available)
            roberta_scores: Output from RoBERTa classifier (if available, currently disabled)
        
        Returns:
            UnifiedAnalysisResult with all signals consolidated
        """
        
        result = UnifiedAnalysisResult()
        
        # STEP 1: Language Detection
        self._detect_language(message, result)
        result.log(f"Language: {result.language['primary']}")
        
        # STEP 2: VADER Sentiment (always available)
        self._analyze_vader(message, result)
        result.log(f"VADER compound: {result.sentiment['compound']:.2f}")
        
        # STEP 3: Use semantic analysis output
        if semantic_result:
            self._apply_semantic_result(semantic_result, result)
            result.emotion['source'] = 'semantic'
            result.log(f"Emotion (semantic): {result.emotion['primary']} ({result.emotion['confidence']:.0%})")
        else:
            result.log("Semantic analysis not available")
        
        # STEP 4: RoBERTa support (if available - currently disabled)
        if roberta_scores:
            self._apply_roberta_scores(roberta_scores, result)
            result.log(f"RoBERTa consulted (disabled in production)")
        
        # STEP 5: Detect sarcasm/figurative language
        self._detect_figurative_language(message, result)
        if result.sarcasm['detected']:
            result.log(f"Sarcasm detected ({result.sarcasm['confidence']:.0%})")
            if result.sarcasm['underlying_emotion']:
                result.log(f"Underlying emotion: {result.sarcasm['underlying_emotion']}")
        
        # STEP 6: Crisis safety analysis (deterministic, application-controlled)
        self._analyze_crisis(message, result)
        result.log(f"Safety level: {result.crisis['safety_level']}")
        
        # STEP 7: Intent classification
        self._classify_intent(message, conversation_history, result)
        result.log(f"Intent: {result.intent['primary']}")
        
        # STEP 8: Negation analysis
        self._analyze_negation(message, result)
        if result.negation['detected']:
            result.log(f"Negation detected ({result.negation['type']})")
        
        # STEP 9: Context detection
        self._detect_context(message, conversation_history, result)
        if result.context['primary_domain']:
            result.log(f"Context: {result.context['primary_domain']}")
        
        # STEP 10: FUSION - Apply contextual corrections
        self._apply_fusion_rules(message, result)
        
        # Final emotion determination
        result.log(f"FINAL EMOTION: {result.emotion['primary']} ({result.emotion['confidence']:.0%})")
        
        return result
    
    def _detect_language(self, message: str, result: UnifiedAnalysisResult):
        """Detect if message is in English, Tagalog, or Taglish"""
        import re
        msg_lower = message.lower()
        
        # Tagalog keywords (with word boundaries to avoid false positives)
        # Particles, pronouns, prepositions, common verbs, and content words
        tagalog_keywords = ['ay', 'ng', 'na', 'naman', 'pa', 'din', 'rin', 'kami', 'tayo',
                           'ako', 'kita', 'yong', 'dito', 'kasi', 'dahil', 'pero', 'daw',
                           'dapat', 'gabi', 'araw', 'bago', 'walang', 'ko', 'mo', 'nyo',
                           'wala', 'tapos', 'grabe', 'sobrang', 'pagod', 'yung', 'ung',
                           'ayoko', 'ayos', 'sana', 'sabihin', 'aso', 'kamatayan', 'matay',
                           'namatay', 'mamatay', 'alaala', 'miss', 'masaya', 'malungkot']
        
        # Use word boundary matching to avoid substring false positives
        # (e.g., "din" in "died", "na" in "mean")
        tagalog_count = sum(1 for kw in tagalog_keywords if re.search(r'\b' + re.escape(kw) + r'\b', msg_lower))
        
        if tagalog_count > 2:
            result.language['detected_taglish'] = True
            result.language['detected_tagalog'] = True
            result.language['primary'] = 'taglish'
        elif tagalog_count > 0:
            result.language['detected_taglish'] = True
            result.language['primary'] = 'english'  # Mostly English with Tagalog words
        else:
            result.language['primary'] = 'english'
    
    def _analyze_vader(self, message: str, result: UnifiedAnalysisResult):
        """Analyze VADER sentiment"""
        try:
            from nltk.sentiment import SentimentIntensityAnalyzer
            analyzer = SentimentIntensityAnalyzer()
            scores = analyzer.polarity_scores(message)
            
            result.sentiment['compound'] = scores.get('compound', 0.0)
            result.sentiment['positive'] = scores.get('pos', 0.0)
            result.sentiment['neutral'] = scores.get('neu', 0.0)
            result.sentiment['negative'] = scores.get('neg', 0.0)
        except Exception as e:
            print(f"[VADER_ERROR] {e}")
            result.sentiment = {'compound': 0.0, 'positive': 0.0, 'neutral': 0.0, 'negative': 0.0}
    
    def _apply_semantic_result(self, semantic_result: Dict, result: UnifiedAnalysisResult):
        """Extract and apply semantic analysis results"""
        if not semantic_result:
            return
        
        emotion_data = semantic_result.get('emotion', {})
        primary_emotion = emotion_data.get('dominant', 'neutral')
        
        # Normalize to canonical classes: map 'happiness' -> 'joy'
        if primary_emotion == 'happiness':
            primary_emotion = 'joy'
        
        result.emotion['primary'] = primary_emotion
        result.emotion['secondary'] = emotion_data.get('secondary', [])
        result.emotion['confidence'] = emotion_data.get('confidence', 0.0)
        result.emotion['scores'] = emotion_data.get('scores', {})
    
    def _apply_roberta_scores(self, roberta_scores: Dict, result: UnifiedAnalysisResult):
        """If RoBERTa model available (currently disabled), apply as supporting signal"""
        result.roberta_support['available'] = True
        result.roberta_support['scores'] = roberta_scores.get('scores', {})
        result.roberta_support['confidence'] = roberta_scores.get('confidence', 0.0)
        # Don't override emotion yet - use in fusion
    
    def _detect_figurative_language(self, message: str, result: UnifiedAnalysisResult):
        """Detect sarcasm, hyperbole, idioms
        
        CRITICAL: Emoji is a SIGNAL, not automatic proof of figurative language.
        Example: "I could die of embarrassment 😂" is likely figurative.
        But: "I want to die 😂" should still be treated carefully (signal + context).
        """
        msg_lower = message.lower()
        
        # Check for figurative death idioms
        # BUT: Don't automatically assume figurative just because emoji exists
        for idiom in self.figurative_death_idioms:
            if idiom in msg_lower:
                # Emoji is ONE signal, not the deciding factor
                has_emoji = any(em in message for em in ['😂', '😆', 'haha', 'lol', 'lmao', 'rofl'])
                
                # Also check for explicit non-crisis context
                non_crisis_context = any(ctx in msg_lower for ctx in [
                    'embarrassment', 'laugh', 'funny', 'joke', 'presentation', 'homework'
                ])
                
                if has_emoji and non_crisis_context:
                    # Strong evidence of figurative
                    result.figurative['detected'] = True
                    result.figurative['type'] = 'death_idiom_figurative'
                    result.figurative['confidence'] = 0.85
                    result.sarcasm['detected'] = False
                    return
                elif has_emoji:
                    # Moderate evidence - emoji alone isn't enough
                    result.figurative['detected'] = True
                    result.figurative['type'] = 'death_idiom_possibly_figurative'
                    result.figurative['confidence'] = 0.50  # LOWER confidence
                    return
                else:
                    # Death-related idiom WITHOUT emoji indicators - treat more carefully
                    result.figurative['detected'] = False
                    result.figurative['confidence'] = 0.2
                    return
        
        # Check for sarcasm patterns (positive words + negative context)
        sarcasm_patterns = [
            (r'(?:absolutely|totally|so|definitely|really)?\s+(?:amazing|great|wonderful|fantastic|perfect|awesome)\s*[.,!]*\s*.*(?:destroy|dying|soul|kill|hell|nightmare|stress|deadline)',
             'positive_then_negative'),
            (r'(?:yeah|sure|right)\s*[.,!]*\s*.*(?:stress|deadline|soul|destroying|dying)',
             'agreement_then_negative'),
            (r'(?:doing|feeling)\s+(?:amazing|great|wonderful|fine|good)\s*.*(?:inside|soul|stressed)',
             'feeling_positive_but_context_negative'),
        ]
        
        for pattern, sarcasm_type in sarcasm_patterns:
            if re.search(pattern, msg_lower):
                result.sarcasm['detected'] = True
                result.sarcasm['confidence'] = 0.75
                result.sarcasm['signals']['text_contradiction'] = 0.75
                
                # Infer underlying emotion from negative context ONLY if specific evidence
                stress_words = ['deadline', 'exam', 'test', 'project']
                if any(w in msg_lower for w in stress_words):
                    result.sarcasm['underlying_emotion'] = 'anxiety'
                
                return
    
    def _analyze_crisis(self, message: str, result: UnifiedAnalysisResult):
        """
        Deterministic crisis analysis - application always in control
        
        Distinguish between:
        - Direct self-harm/death language
        - Passive death wishes
        - Figurative language
        - Sarcasm with death words
        - Emotional distress (not crisis)
        """
        msg_lower = message.lower()
        
        # Check for direct keywords
        direct_crisis_keywords = [
            'want to die', 'suicide', 'suicidal', 'kill myself', 
            'self harm', 'cut myself', 'overdose', 'jump'
        ]
        
        for kw in direct_crisis_keywords:
            if kw in msg_lower:
                result.crisis['safety_level'] = 'critical'
                result.crisis['direct_self_harm'] = True
                result.crisis['crisis_keywords'].append(kw)
                result.crisis['reasoning'] = f"Direct self-harm keyword detected: {kw}"
                return
        
        # Check for passive death wishes: "want to disappear", "want to end it", etc
        if 'want to' in msg_lower or 'wanna' in msg_lower:
            if any(phrase in msg_lower for phrase in ['disappear', 'vanish', 'die', 'end', 'end it', 'end it all', 'be gone']):
                result.crisis['safety_level'] = 'critical'
                result.crisis['passive_death_wish'] = True
                result.crisis['crisis_keywords'].append('passive_death_wish')
                result.crisis['reasoning'] = "Passive death wish detected"
                return
        
        # Check if combined with context that might indicate figurative
        if 'die' in msg_lower or 'dying' in msg_lower:
            # Check for figurative markers
            if any(em in message for em in ['😂', '😆', 'haha', 'lol']):
                result.crisis['safety_level'] = 'low'
                result.crisis['figurative_language'] = True
                result.crisis['reasoning'] = "Death-related words detected but figurative markers present"
                return
        
        # No crisis indicators
        result.crisis['safety_level'] = 'low'
        result.crisis['reasoning'] = "No acute crisis indicators detected"
    
    def _classify_intent(self, message: str, conversation_history: List[Dict], result: UnifiedAnalysisResult):
        """
        Classify the user's primary interaction intent.

        Priority:
        1. Explicit questions and factual/informational requests
        2. Practical/general requests
        3. Greetings
        4. Short standalone confirmations
        5. Emotional statements/venting

        Important:
        - Uses word boundaries for greetings/confirmations.
        - Does not treat words such as "with" as containing "hi".
        - Recognizes imperative factual requests such as "Calculate..." as questions.
        - Recognizes ordinary action requests such as "Tell me a joke." as requests.
        """
        msg_lower = message.lower().strip()

        # STEP 1: Explicit question mark.
        # Keep this first so "Can you help me?" remains a question.
        if '?' in message:
            result.intent['primary'] = 'question'
            result.intent['confidence'] = 0.95
            return

        # STEP 2: Factual / informational requests without '?'.
        # These are grammatically imperatives but function as information questions.
        factual_request_patterns = [
            r'^\s*calculate\b',
            r'^\s*compute\b',
            r'^\s*solve\b',
            r'^\s*find\b.*\b(square root|sqrt|result|answer)\b',
            r'^\s*what is\b',
            r'^\s*what are\b',
            r'^\s*who is\b',
            r'^\s*who was\b',
            r'^\s*where is\b',
            r'^\s*when is\b',
            r'^\s*when was\b',
            r'^\s*why is\b',
            r'^\s*why are\b',
            r'^\s*how much\b',
            r'^\s*how many\b',
            r'^\s*how old\b',
            r'^\s*translate\b',
            r'^\s*give me the translation\b',
            r'^\s*translation of\b',
            r'^\s*define\b',
            r'^\s*what does\b',
            r'^\s*meaning of\b',
            r'^\s*convert\b',
        ]

        if any(re.search(pattern, msg_lower) for pattern in factual_request_patterns):
            result.intent['primary'] = 'question'
            result.intent['confidence'] = 0.90
            return

        # STEP 3: Explicit general/action requests.
        request_patterns = [
            r'^\s*tell me\b',
            r'^\s*give me\b',
            r'^\s*show me\b',
            r'^\s*write\b',
            r'^\s*make\b',
            r'^\s*create\b',
            r'^\s*list\b',
            r'^\s*provide\b',
            r'^\s*recommend\b',
            r'^\s*suggest\b',
        ]

        if any(re.search(pattern, msg_lower) for pattern in request_patterns):
            result.intent['primary'] = 'request'
            result.intent['confidence'] = 0.90
            return

        # STEP 4: Existing practical-help request detection.
        if any(
            re.search(r'\b' + re.escape(word) + r'\b', msg_lower)
            for word in ['tips', 'advice', 'help', 'support', 'suggestions']
        ) or re.search(r'\bcan you\b', msg_lower):
            result.intent['primary'] = 'request'
            result.intent['confidence'] = 0.90
            return

        # STEP 5: Greeting detection using standalone word boundaries.
        greeting_pattern = r'\b(hi|hey|hello|sup|yo|howdy)\b'
        if re.search(greeting_pattern, msg_lower):
            words = msg_lower.split()

            # Only short greeting-like messages are greetings.
            if len(words) <= 3 or (len(words) <= 5 and msg_lower.count('?') == 0):
                result.intent['primary'] = 'greeting'
                result.intent['confidence'] = 0.95
                return

        # STEP 6: Confirmation detection ONLY for short standalone confirmations.
        confirmation_words = [
            'yes', 'yeah', 'yep', 'yup', 'ok', 'okay', 'sure', 'alright'
        ]

        confirmation_pattern = (
            r'\b(' + '|'.join(re.escape(w) for w in confirmation_words) + r')\b'
        )

        if re.search(confirmation_pattern, msg_lower):
            non_confirmation_words = [
                w for w in msg_lower.split()
                if not re.fullmatch(confirmation_pattern, w)
            ]

            # Standalone confirmation or very short confirmation.
            if len(message.strip()) < 15 or len(non_confirmation_words) == 0:
                result.intent['primary'] = 'confirmation'
                result.intent['confidence'] = 0.85
                return

            # Longer message containing "yeah/yes/okay" is a statement.
            result.intent['primary'] = 'statement'
            result.intent['confidence'] = 0.70
            return

        # STEP 7: Default to emotional/general statement.
        result.intent['primary'] = 'statement'
        result.intent['confidence'] = 0.60

    def _analyze_negation(self, message: str, result: UnifiedAnalysisResult):
        """Detect and understand negation"""
        msg_lower = message.lower()
        
        # Check for compound sadness phrases that contain 'wala' but are NOT negations
        # These should NOT trigger negation detection
        sadness_phrases_with_wala = ['wala akong gana', 'walang gana', 'wala ng gana', 'wala akong energy']
        if any(phrase in msg_lower for phrase in sadness_phrases_with_wala):
            # This is a sadness indicator, not negation
            # Don't detect negation for this case
            return
        
        # English and Tagalog negation words
        # Note: "wala" is excluded because it's checked above as part of sadness phrases
        negation_words = ["don't", "don't", "not", "no", "can't", "cannot", "never", "nor", "neither",
                         # Tagalog negation particles (exclude standalone "wala" to avoid conflict with sadness phrases)
                         "hindi", "hindi naman", "di", "di naman"]
        
        for neg in negation_words:
            if neg in msg_lower:
                result.negation['detected'] = True
                result.negation['type'] = 'simple'
                
                # What is being negated?
                if any(h in msg_lower for h in ['happy', 'glad', 'good', 'great', 'amazing', 'masaya']):
                    result.negation['negates'] = 'positive_emotion'
                elif any(s in msg_lower for s in ['stressed', 'worried', 'anxious', 'scared', 'kinakabahan', 'alalahanin']):
                    result.negation['negates'] = 'negative_emotion'
                elif any(sad in msg_lower for sad in ['sad', 'down', 'depressed', 'malungkot', 'lungkot']):
                    result.negation['negates'] = 'sadness_emotion'
                else:
                    result.negation['negates'] = 'unknown'
                
                return
    
    def _detect_context(self, message: str, conversation_history: List[Dict], result: UnifiedAnalysisResult):
        """Detect contextual domains"""
        msg_lower = message.lower()
        
        # Academic
        if any(w in msg_lower for w in ['exam', 'test', 'assignment', 'project', 'homework', 'school', 'class', 'study', 'deadline', 'coursework']):
            result.context['academic'] = True
            result.context['primary_domain'] = 'academic'
        
        # Financial
        elif any(w in msg_lower for w in ['money', 'cash', 'broke', 'debt', 'loan', 'financial', 'afford', 'pay', 'cost', 'expensive']):
            result.context['financial'] = True
            result.context['primary_domain'] = 'financial'
        
        # Work
        elif any(w in msg_lower for w in ['work', 'job', 'boss', 'coworker', 'colleague', 'work', 'office', 'meeting', 'project']):
            result.context['work'] = True
            result.context['primary_domain'] = 'work'
        
        # Relationship
        elif any(w in msg_lower for w in ['girlfriend', 'boyfriend', 'partner', 'spouse', 'lover', 'crush', 'ex', 'dating', 'relationship', 'broke up']):
            result.context['relationship'] = True
            result.context['primary_domain'] = 'relationship'
        
        # Family
        elif any(w in msg_lower for w in ['mom', 'dad', 'parent', 'brother', 'sister', 'family', 'sibling', 'kid', 'child']):
            result.context['family'] = True
            result.context['primary_domain'] = 'family'
        
        # Loneliness
        elif any(w in msg_lower for w in ['lonely', 'alone', 'no one', 'isolated', 'left out', 'excluded']):
            result.context['loneliness'] = True
            result.context['primary_domain'] = 'loneliness'
    
    def _apply_fusion_rules(self, message: str, result: UnifiedAnalysisResult):
        """
        Apply contextual fusion rules to finalize emotion classification
        
        CRITICAL RULES (per Debugging Pass 3 corrections):
        1. Do NOT automatically force sarcasm → anxiety
        2. Only apply underlying emotion if it was explicitly inferred from SPECIFIC context
        3. Preserve surface vs underlying emotion distinction
        4. Prefer uncertainty over invented emotions
        5. Crisis safety-first but not based on emoji alone
        """
        
        # Rule 1: Sarcasm application - ONLY if underlying emotion was explicitly inferred
        if result.sarcasm['detected'] and result.sarcasm['underlying_emotion']:
            # underlying_emotion is ONLY set if specific context evidence exists (deadline, failure, etc)
            original = result.emotion['primary']
            result.emotion['primary'] = result.sarcasm['underlying_emotion']
            result.emotion['confidence'] *= 0.80  # Lower confidence for inferred emotion
            result.sarcasm['signals']['contextual_inferred'] = True
            result.log(f"[FUSION] Sarcasm + context: {original} → {result.sarcasm['underlying_emotion']} (inferred)")
        
        # Rule 2: VADER mismatch - only if strong clear signal
        if result.sentiment['compound'] < -0.3 and result.emotion['primary'] in ['joy', 'neutral']:
            # Only change if semantic scores clearly support anxiety
            if result.emotion['scores'].get('anxiety', 0) > 0.2:
                result.emotion['primary'] = 'anxiety'
                result.log(f"[FUSION] VADER negative (-{abs(result.sentiment['compound']):.2f}) + semantic anxiety: adjusted")
        
        # Rule 3: Crisis handling - preserve safety-first
        if result.crisis['safety_level'] in ['critical', 'high'] and not result.crisis['figurative_language']:
            result.emotion['primary'] = 'crisis'
            result.emotion['confidence'] = 1.0
            result.log(f"[FUSION] Crisis safety_level={result.crisis['safety_level']}: emotion=crisis")
        
        # Rule 4: Figurative language - emoji is signal, not automatic proof
        if result.figurative['detected'] and result.figurative['type'] == 'death_idiom_figurative':
            result.crisis['safety_level'] = 'low'
            result.crisis['figurative'] = True
            result.log(f"[FUSION] Figurative death idiom detected + emoji: safety lowered to low")


# Global analyzer instance
_unified_analyzer = None

def get_unified_analyzer() -> UnifiedAnalyzer:
    """Get or create unified analyzer"""
    global _unified_analyzer
    if _unified_analyzer is None:
        _unified_analyzer = UnifiedAnalyzer()
    return _unified_analyzer


def analyze_unified(message: str, conversation_history: List[Dict] = None,
                   semantic_result: Dict = None, roberta_scores: Dict = None) -> UnifiedAnalysisResult:
    """
    Convenience function for unified analysis
    
    Args:
        message: User's message
        conversation_history: Previous messages
        semantic_result: From semantic_analysis.py
        roberta_scores: From RoBERTa (currently disabled)
    
    Returns:
        UnifiedAnalysisResult
    """
    analyzer = get_unified_analyzer()
    return analyzer.analyze(message, conversation_history, semantic_result, roberta_scores)

