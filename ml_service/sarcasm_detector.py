"""
Multi-signal Sarcasm Detector for Mental Health Conversations
Combines multiple signals to detect sarcasm more accurately than keyword matching alone
"""

import re
from typing import Dict, Tuple

class SarcasmDetector:
    """
    Detect sarcasm using multiple signals:
    - Positive/negative contradiction
    - Exaggeration patterns
    - Emoji context
    - Conversational context
    - Negation patterns
    """
    
    def __init__(self):
        """Initialize sarcasm detection patterns"""
        
        # SIGNAL 1: Positive words that are often used sarcastically
        self.positive_words = {
            'amazing', 'wonderful', 'fantastic', 'great', 'perfect', 'love', 'fine',
            'good', 'best', 'excellent', 'super', 'awesome', 'brilliant', 'terrific',
            'outstanding', 'magnificent', 'splendid', 'marvelous', 'divine',
            'doing great', 'feeling great', 'so happy', 'absolutely thriving'
        }
        
        # SIGNAL 2: Negative/stress context words
        self.negative_context = {
            'destroy', 'destroying', 'destroyed', 'kill', 'killing', 'die', 'dying', 'dead',
            'soul', 'hell', 'nightmare', 'suffer', 'suffering', 'struggling', 'struggle',
            'stress', 'stressed', 'anxious', 'anxiety', 'worry', 'worried', 'depressed',
            'sad', 'miserable', 'awful', 'terrible', 'horrible', 'bad', 'worse', 'worst',
            'fail', 'failed', 'failing', 'collapse', 'crumble', 'fall apart', 'breaking',
            'exhausted', 'drained', 'worn', 'beaten', 'overwhelm', 'drowning', 'sinking',
            'barely', 'barely surviving', 'can\'t handle', 'can\'t cope', 'can\'t manage',
            'hate', 'disgusting', 'gross', 'yuck', 'ugh', 'ugh', 'ew', 'nasty',
            'deadline', 'deadlines', 'exam', 'exams', 'test', 'tests', 'assignment'
        }
        
        # SIGNAL 3: Exaggeration patterns
        self.exaggeration_patterns = [
            r'\b(?:absolutely|so|totally|completely|extremely|utterly|incredibly)\s+(?:' + '|'.join(self.positive_words) + r')\b',
            r'\b(?:best|greatest|most|only|ever)\s+(?:day|time|experience)\b',
            r'everything\s+is\s+(?:perfect|fine|good|amazing|wonderful)',
            r'life\s+is\s+(?:perfect|great|wonderful|amazing)',
        ]
        
        # SIGNAL 4: Sarcasm emojis
        self.sarcasm_emojis = {
            '🙄': 0.7,    # eye roll
            '😒': 0.7,    # unimpressed
            '😏': 0.6,    # smirk
            '😑': 0.5,    # unimpressed face
            '🤨': 0.6,    # raised eyebrow
            '🙃': 0.5,    # upside down smile (can be sarcastic)
            '💀': 0.4,    # dying/dead (often used sarcastically)
            '😅': 0.3,    # nervous laugh
            '😭': 0.2,    # crying (context-dependent)
            '😂': 0.2,    # laughing (context-dependent)
        }
        
        # SIGNAL 5: Explicit sarcasm markers
        self.sarcasm_markers = {
            'yeah sure', 'oh sure', 'yeah right', 'oh yeah', 'right', 'sure sure',
            'oh please', 'like i care', 'who cares', 'as if', 'give me a break',
            'pull the other one', 'tell me another', 'sure thing', 'whatever',
            'yeah ok', 'sure buddy', 'ok buddy', 'great thanks', 'thanks a lot'
        }
    
    def detect_sarcasm(self, message: str, prior_context: str = None, 
                      semantic_result: Dict = None) -> Dict:
        """
        Detect sarcasm using multiple signals
        
        Args:
            message: The message to analyze
            prior_context: Previous AI/user messages for context
            semantic_result: Result from semantic analysis (for emotion/crisis data)
        
        Returns:
            Dict with:
            - is_sarcastic: bool
            - confidence: float 0-1
            - score: float 0-1 (alias for confidence)
            - signals: dict of individual signal scores
            - reason: str explaining detection
        """
        
        msg_lower = message.lower()
        signals = {}
        
        # SIGNAL 1: Positive/Negative Contradiction
        signals['contradiction'] = self._detect_contradiction(msg_lower)
        
        # SIGNAL 2: Exaggeration
        signals['exaggeration'] = self._detect_exaggeration(msg_lower)
        
        # SIGNAL 3: Emoji indicators
        signals['emoji'] = self._detect_emoji_sarcasm(message)
        
        # SIGNAL 4: Explicit sarcasm markers
        signals['explicit_markers'] = self._detect_explicit_markers(msg_lower)
        
        # SIGNAL 5: Negation + positive words
        signals['negation'] = self._detect_negation_pattern(msg_lower)
        
        # SIGNAL 6: Context from conversation
        signals['context'] = self._detect_contextual_sarcasm(msg_lower, prior_context)
        
        # SIGNAL 7: VADER sentiment if available
        if semantic_result and semantic_result.get('tone', {}).get('sarcasm_score'):
            signals['vader_sarcasm'] = semantic_result['tone']['sarcasm_score']
        
        # Combine signals with weighted voting
        combined_score = self._combine_signals(signals)
        
        # Generate explanation
        reason = self._generate_reason(signals, combined_score)
        
        # Keep development logs metadata-only; the message itself may contain private content.
        message = ''
        # Print debug info
        print(f"[SARCASM_DETECT] msg='{message[:50]}...'")
        print(f"  signals: {signals}")
        print(f"  combined_score: {combined_score:.2f}")
        print(f"  reason: {reason}")
        
        return {
            'is_sarcastic': combined_score > 0.15,  # Lowered threshold from 0.20 to 0.15
            'confidence': min(1.0, combined_score),
            'score': min(1.0, combined_score),
            'signals': signals,
            'reason': reason
        }
    
    def _detect_contradiction(self, msg_lower: str) -> float:
        """
        Detect contradiction between positive and negative content
        E.g., "I'm amazing. My deadlines are destroying me."
        """
        score = 0.0
        
        # Split into sentences
        sentences = [s.strip() for s in re.split(r'[.!?]+', msg_lower) if s.strip()]
        
        if len(sentences) < 2:
            return 0.0
        
        # Check each sentence pair for contradiction
        for i in range(len(sentences) - 1):
            current = sentences[i]
            next_sent = sentences[i + 1]
            
            # Check if one has positive words and next has negative
            current_positive = any(word in current for word in self.positive_words)
            current_negative = any(word in current for word in self.negative_context)
            
            next_positive = any(word in next_sent for word in self.positive_words)
            next_negative = any(word in next_sent for word in self.negative_context)
            
            # Contradiction: positive followed by negative
            if (current_positive and not current_negative) and (next_negative and not next_positive):
                score += 0.6  # Increased from 0.5
            # Or negative followed by positive dismissal
            elif (current_negative and not current_positive) and (next_positive and not next_negative):
                score += 0.4  # Increased from 0.3
        
        return min(1.0, score)
    
    def _detect_exaggeration(self, msg_lower: str) -> float:
        """
        Detect exaggeration that may indicate sarcasm
        E.g., "I'm absolutely thriving" after describing failure
        """
        score = 0.0
        
        for pattern in self.exaggeration_patterns:
            if re.search(pattern, msg_lower, re.IGNORECASE):
                score += 0.3
        
        # Check for "absolutely/completely/so [positive]" patterns
        if re.search(r'\b(?:absolutely|completely|totally|so)\s+(?:amazing|wonderful|fine|good|great|perfect)\b', msg_lower):
            score += 0.2
        
        return min(1.0, score)
    
    def _detect_emoji_sarcasm(self, message: str) -> float:
        """
        Detect emojis commonly associated with sarcasm
        """
        score = 0.0
        
        for emoji, emoji_score in self.sarcasm_emojis.items():
            if emoji in message:
                score += emoji_score
        
        # Context: 😂 or 😭 after negative content = higher sarcasm
        if '😂' in message or '😭' in message:
            # Check if message has negative words
            msg_lower = message.lower()
            if any(word in msg_lower for word in self.negative_context):
                score += 0.2
        
        return min(1.0, score)
    
    def _detect_explicit_markers(self, msg_lower: str) -> float:
        """
        Detect explicit sarcasm markers
        """
        score = 0.0
        
        for marker in self.sarcasm_markers:
            if marker in msg_lower:
                score += 0.4
                break  # One explicit marker is enough
        
        return min(1.0, score)
    
    def _detect_negation_pattern(self, msg_lower: str) -> float:
        """
        Detect negation followed by positive words
        E.g., "I'm NOT doing well" vs. earlier "I'm NOT happy"
        """
        score = 0.0
        
        # Pattern: "not" or "never" followed by positive words
        if re.search(r'\b(?:not|never|can\'t|couldn\'t|isn\'t|aren\'t|wasn\'t|weren\'t)\s+(?:' + '|'.join(self.positive_words) + r')\b', msg_lower):
            score += 0.3
        
        return score
    
    def _detect_contextual_sarcasm(self, msg_lower: str, prior_context: str = None) -> float:
        """
        Detect sarcasm based on conversation context
        If prior messages mentioned stress/deadlines, then "amazing" is more likely sarcastic
        """
        score = 0.0
        
        if not prior_context:
            return 0.0
        
        prior_lower = prior_context.lower()
        
        # If prior context has stress indicators
        has_stress_context = any(word in prior_lower for word in [
            'stress', 'deadline', 'exam', 'anxiety', 'struggle', 'difficult', 'hard'
        ])
        
        # And current message has positive words
        has_positive = any(word in msg_lower for word in self.positive_words)
        
        if has_stress_context and has_positive:
            score += 0.3
        
        return min(1.0, score)
    
    def _combine_signals(self, signals: Dict) -> float:
        """
        Combine multiple signals with weighted voting
        """
        # Weights for each signal
        weights = {
            'contradiction': 0.3,      # Strong indicator
            'exaggeration': 0.15,
            'emoji': 0.15,
            'explicit_markers': 0.25,  # Very strong
            'negation': 0.1,
            'context': 0.15,
            'vader_sarcasm': 0.2,      # If available
        }
        
        weighted_sum = 0.0
        weight_total = 0.0
        
        for signal_name, signal_score in signals.items():
            weight = weights.get(signal_name, 0.1)
            weighted_sum += signal_score * weight
            weight_total += weight
        
        if weight_total == 0:
            return 0.0
        
        return weighted_sum / weight_total
    
    def _generate_reason(self, signals: Dict, combined_score: float) -> str:
        """
        Generate human-readable explanation of sarcasm detection
        """
        strong_signals = []
        
        if signals.get('contradiction', 0) > 0.3:
            strong_signals.append('positive/negative contradiction')
        if signals.get('exaggeration', 0) > 0.2:
            strong_signals.append('exaggeration pattern')
        if signals.get('emoji', 0) > 0.3:
            strong_signals.append('sarcasm emoji')
        if signals.get('explicit_markers', 0) > 0.2:
            strong_signals.append('explicit sarcasm marker')
        if signals.get('context', 0) > 0.2:
            strong_signals.append('contextual contradiction')
        
        if not strong_signals:
            return "No strong sarcasm indicators"
        
        return f"Detected: {', '.join(strong_signals)}"


# Utility function for use in other modules
def detect_sarcasm(message: str, prior_context: str = None, 
                  semantic_result: Dict = None) -> Dict:
    """Convenience function"""
    detector = SarcasmDetector()
    return detector.detect_sarcasm(message, prior_context, semantic_result)
