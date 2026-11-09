"""
Context Classifier for Therapy AI
Understands conversation context to distinguish between CASUAL/PLAYFUL vs EMOTIONAL/SUPPORT_SEEKING vs CRISIS
Uses conversation history and linguistic patterns, not just individual messages
"""

import re
from typing import Dict, List, Optional, Tuple

class ContextClassifier:
    """
    Classifies the interaction type based on:
    - Current message content
    - Conversation history
    - Linguistic markers (jokes, corrections, emotional intensity)
    - User explicit denials/corrections
    
    Output: interaction_type with confidence score
    """
    
    def __init__(self):
        """Initialize classifier with linguistic patterns"""
        
        # Patterns indicating PLAYFUL/JOKING correction
        self.joking_patterns = [
            r"kidding",
            r"just.*kid",
            r"jest",
            r"jok",
            r"mess",  # covers "messing", "mess", "missing" (typo)
            r"with\s*you",
            r"teas",
            r"troll",
            r"play",
            r"pull.*leg",
            r"fuck",
            r"haha",
            r"lol",
            r"lmao",
            r"rofl",
            r"don[\'t]?\s*take.*seri",
            r"around",
            r"nah.*kid",
            r"bro.*kid",
            r"dude.*kid",
        ]
        
        # Patterns indicating emotional intensity/seriousness
        self.emotional_markers = [
            r"stressed",
            r"stressed.*about",
            r"falling.*apart",
            r"everything.*falling",
            r"suicidal",
            r"want.*die",
            r"kill.*myself",
            r"can.*anymore",
            r"i can't.*anymore",
            r"pretending",
            r"faking",
            r"masking",
            r"struggling.*badly",
            r"struggling",
            r"depressed",
            r"feeling.*depressed",
            r"feeling.*anxious",
            r"feeling.*stressed",
            r"hopeless",
            r"worthless",
            r"deeply.*sad",
            r"genuinely",
            r"destroy.*soul",  # Catches "destroying my soul"
            r"deadline.*soul",  # Catches "deadlines... soul"
            r"slowly.*destroy",  # Catches "slowly destroying"
            r"my.*soul",  # General soul references indicating depth
            r"dying.*inside",  # Catches "dying inside"
            r"not.*dying",  # Catches "not dying inside" (sarcasm marker)
            # FIX 2: Add missing emotional markers for academic/failure context
            r"fail",  # Catches "failed", "failing", "fail"
            r"exam",  # Academic stress
            r"test.*stres",  # Test-related stress
            r"project.*due",  # Project deadline stress
            r"deadline",  # General deadline stress
            r"work.*stres",  # Work-related stress
            r"unfinished",  # Incomplete work stress
            r"incomplete",  # Incomplete work stress
            r"behind.*schedul",  # Behind schedule
            r"haven't.*finish",  # Haven't finished (indication of failure/incomplete)
            r"didn't.*finish",  # Didn't finish
            r"weeks.*prepar",  # Studied for weeks = effort indicator
            r"studying.*weeks",  # Studying for weeks
        ]
        
        # Patterns indicating casual/mundane conversation
        self.casual_markers = [
            r"^yo",
            r"^hey",
            r"^hi",
            r"^hello",
            r"^sup",
            r"what[\'s]?\s*up",
            r"do\s*you\s*know",
            r"have\s*you\s*heard",
        ]
        
        # Crisis keywords - ALWAYS high priority
        self.crisis_keywords = [
            'suicide', 'suicidal', 'want to die', 'kill myself',
            'self harm', 'cut myself', 'self-harm', 'end my life'
        ]
        
        # Support-seeking explicit patterns
        self.support_seeking_patterns = [
            r"can\s*i\s*talk",
            r"can\s*i\s*tell",
            r"can\s*i\s*share",
            r"can\s*i\s*discuss",
            r"can\s*you\s*help",
            r"can\s*you\s*listen",
            r"i\s*need\s*help",
            r"i\s*need\s*support",
            r"need.*talk",
            r"want.*discuss",
            r"something.*bothering",
        ]
    
    def classify(self, 
                current_message: str,
                conversation_history: List[Dict] = None,
                emotion_from_model: str = None,
                emotion_confidence: float = 0.0) -> Dict:
        """
        Classify the interaction type
        
        Args:
            current_message: The user's current message
            conversation_history: List of previous exchanges
            emotion_from_model: Emotion classification from trained model
            emotion_confidence: Confidence from trained model
        
        Returns:
            {
                'interaction_type': 'CASUAL' | 'PLAYFUL' | 'EMOTIONAL' | 'SUPPORT_SEEKING' | 'CRISIS',
                'confidence': float (0.0-1.0),
                'reasoning': str,
                'context_shift': bool,  # Did context change from previous message?
                'user_correction': bool,  # Is user correcting previous interpretation?
                'should_override_emotion': bool,  # Should we ignore trained model emotion?
                'recommended_emotion': str,  # What emotion should be used instead
                'recommended_response_style': str,  # How should response be generated
            }
        """
        
        # Check for crisis first (always overrides everything)
        if self._is_crisis(current_message):
            return {
                'interaction_type': 'CRISIS',
                'confidence': 1.0,
                'reasoning': 'Crisis keywords detected - prioritize safety',
                'context_shift': False,
                'user_correction': False,
                'should_override_emotion': True,
                'recommended_emotion': 'crisis',
                'recommended_response_style': 'crisis_immediate_help',
            }
        
        # Check if user is correcting a previous interpretation
        user_correction = self._is_user_correction(current_message)
        
        # Check conversation context
        context_data = self._analyze_context(current_message, conversation_history)
        
        # Classify current message
        classification = self._classify_message(current_message)
        
        # If user is correcting, validate that the previous classification was wrong
        if user_correction and conversation_history and len(conversation_history) > 0:
            # Look at what the previous message was
            prev_message = conversation_history[-1].get('user', '')
            prev_classification = self._classify_message(prev_message)
            
            # If previous was CASUAL/PLAYFUL and user is now saying "I'm joking",
            # then classify current as PLAYFUL correction, and mark that the previous
            # interpretation was wrong
            if prev_classification['type'] in ['CASUAL', 'PLAYFUL']:
                return {
                    'interaction_type': 'PLAYFUL',
                    'confidence': 0.95,
                    'reasoning': f"User correcting interpretation: '{current_message[:40]}...' - previous was casual, not emotional",
                    'context_shift': False,
                    'user_correction': True,
                    'should_override_emotion': True,
                    'recommended_emotion': 'neutral',  # Don't force emotion interpretation
                    'recommended_response_style': 'conversational',
                }
        
        # Check for mixed signals (e.g., "I'm joking around but actually I'm struggling")
        if self._has_mixed_signals(current_message):
            return {
                'interaction_type': 'EMOTIONAL',
                'confidence': 0.9,
                'reasoning': 'Mixed signal: Joking language but actual emotional content detected',
                'context_shift': context_data['context_shift'],
                'user_correction': user_correction,
                'should_override_emotion': True,
                'recommended_emotion': 'sadness',  # Or whatever emotional keyword is present
                'recommended_response_style': 'therapeutic_support',
            }
        
        # Check for explicit support-seeking
        if self._is_support_seeking(current_message):
            return {
                'interaction_type': 'SUPPORT_SEEKING',
                'confidence': 0.95,
                'reasoning': 'Explicit support-seeking request detected',
                'context_shift': context_data['context_shift'],
                'user_correction': user_correction,
                'should_override_emotion': False,
                'recommended_emotion': emotion_from_model or 'neutral',
                'recommended_response_style': 'supportive_listening',
            }
        
        # Use the classification from message analysis
        interaction_type = classification['type']
        confidence = classification['confidence']
        reasoning = classification['reasoning']
        
        # Adjust based on emotion confidence from model
        # If emotion_confidence is low, default to CASUAL
        if emotion_confidence < 0.4 and interaction_type == 'EMOTIONAL':
            interaction_type = 'CASUAL'
            confidence = max(confidence, 0.7)
            reasoning = f"Emotion confidence too low ({emotion_confidence:.0%}) - treating as casual conversation"
        
        # Determine response style based on context
        # NOTE: Context and emotion are SEPARATE concepts
        # A message can be CASUAL but still have valid emotions
        # Do NOT override emotion based on context type
        should_override = False
        recommended_emotion = emotion_from_model or 'neutral'
        
        if interaction_type == 'PLAYFUL':
            # PLAYFUL means conversational tone, but don't erase detected emotion
            # User can be playful AND have real feelings
            should_override = False
            recommended_emotion = emotion_from_model or 'neutral'
            response_style = 'conversational'
        elif interaction_type == 'CASUAL':
            # CASUAL means everyday conversation, but emotion is still valid
            # "I've been feeling really happy today" is CASUAL context + HAPPINESS emotion
            should_override = False
            recommended_emotion = emotion_from_model or 'neutral'
            response_style = 'conversational'
        elif interaction_type == 'EMOTIONAL':
            should_override = False
            response_style = 'therapeutic_support'
        elif interaction_type == 'SUPPORT_SEEKING':
            should_override = False
            response_style = 'supportive_listening'
        else:
            response_style = 'conversational'
        
        return {
            'interaction_type': interaction_type,
            'confidence': confidence,
            'reasoning': reasoning,
            'context_shift': context_data['context_shift'],
            'user_correction': user_correction,
            'should_override_emotion': should_override,
            'recommended_emotion': recommended_emotion,
            'recommended_response_style': response_style,
        }
    
    def _is_crisis(self, message: str) -> bool:
        """Check if message contains crisis indicators"""
        msg_lower = message.lower()
        return any(kw in msg_lower for kw in self.crisis_keywords)
    
    def _is_user_correction(self, message: str) -> bool:
        """Check if user is correcting a previous interpretation"""
        for pattern in self.joking_patterns:
            if re.search(pattern, message, re.IGNORECASE):
                return True
        return False
    
    def _is_support_seeking(self, message: str) -> bool:
        """Check if user is explicitly asking for support"""
        for pattern in self.support_seeking_patterns:
            if re.search(pattern, message, re.IGNORECASE):
                return True
        return False
    
    def _has_mixed_signals(self, message: str) -> bool:
        """Check for messages that combine playful language with emotional content"""
        has_joking = any(re.search(pattern, message, re.IGNORECASE) 
                        for pattern in self.joking_patterns)
        has_emotional = any(re.search(pattern, message, re.IGNORECASE)
                           for pattern in self.emotional_markers)
        
        # Also check for explicit "but" transitions
        if 'but' in message.lower() and 'actually' in message.lower():
            if any(re.search(pattern, message, re.IGNORECASE) for pattern in self.emotional_markers):
                return True
        
        # Check for SARCASM patterns (positive words with negative context)
        # e.g., "I'm doing absolutely amazing" + "deadlines are destroying my soul"
        sarcasm_patterns = [
            r'(?:absolutely|totally|so|definitely)?\s+(?:amazing|great|wonderful|fantastic|perfect)\s*[.,!]*\s*.*(?:destroy|struggling|sarcasm|dying|kill|suicide|hell)',
            r'(?:yeah|sure|right)\s*[.,!]*\s*.*(?:stress|deadline|soul|killing|destroy)',
            r'(?:doing|feeling)\s+(?:amazing|great|wonderful)\s*.*(?:destroying|killing|dying|hell|nightmare)',
        ]
        
        has_sarcasm = any(re.search(pattern, message, re.IGNORECASE) for pattern in sarcasm_patterns)
        
        # If sarcasm detected with emotional content, it's mixed signals
        if has_sarcasm and has_emotional:
            return True
        
        return has_joking and has_emotional
    
    def _classify_message(self, message: str) -> Dict:
        """Classify single message"""
        
        # Special case: if message says "I'm fine" or similar, override to CASUAL
        # User is explicitly denying emotional distress
        if any(phrase in message.lower() for phrase in ['i\'m fine', 'im fine', 'i am fine', 'i\'m okay', 'im okay', 'i am okay']):
            return {
                'type': 'CASUAL',
                'confidence': 0.95,
                'reasoning': 'User explicitly states they\'re fine - treating as denial/casual'
            }
        
        # Check emotional markers first (highest priority)
        emotional_score = sum(1 for pattern in self.emotional_markers
                            if re.search(pattern, message, re.IGNORECASE))
        if emotional_score > 0:
            return {
                'type': 'EMOTIONAL',
                'confidence': min(0.5 + (emotional_score * 0.15), 0.95),
                'reasoning': f'Found {emotional_score} emotional markers'
            }
        
        # Check playful markers
        playful_score = sum(1 for pattern in self.joking_patterns
                           if re.search(pattern, message, re.IGNORECASE))
        if playful_score > 0:
            return {
                'type': 'PLAYFUL',
                'confidence': min(0.6 + (playful_score * 0.15), 0.95),
                'reasoning': f'Found {playful_score} playful/joking markers'
            }
        
        # Check casual markers
        casual_score = sum(1 for pattern in self.casual_markers
                          if re.search(pattern, message, re.IGNORECASE))
        if casual_score > 0:
            return {
                'type': 'CASUAL',
                'confidence': min(0.5 + (casual_score * 0.15), 0.9),
                'reasoning': f'Found {casual_score} casual markers'
            }
        
        # Default to casual if nothing else matches
        return {
            'type': 'CASUAL',
            'confidence': 0.4,
            'reasoning': 'No strong emotional/playful indicators - treating as casual'
        }
    
    def _analyze_context(self, current_message: str, 
                        conversation_history: List[Dict] = None) -> Dict:
        """Analyze conversation context"""
        context_shift = False
        
        if not conversation_history or len(conversation_history) == 0:
            return {'context_shift': False}
        
        # Check if there's a major shift from previous message
        prev_message = conversation_history[-1].get('user', '')
        
        prev_type = self._classify_message(prev_message)['type']
        curr_type = self._classify_message(current_message)['type']
        
        # Context shift if going from CASUAL to EMOTIONAL or vice versa
        if (prev_type in ['CASUAL', 'PLAYFUL'] and 
            curr_type in ['EMOTIONAL', 'SUPPORT_SEEKING']):
            context_shift = True
        
        return {'context_shift': context_shift}


# Global instance
_classifier = None

def get_classifier():
    """Get or create the context classifier"""
    global _classifier
    if _classifier is None:
        _classifier = ContextClassifier()
    return _classifier


def classify_interaction(message: str,
                        conversation_history: List[Dict] = None,
                        emotion_from_model: str = None,
                        emotion_confidence: float = 0.0) -> Dict:
    """
    Main interface: Classify interaction context
    
    Args:
        message: Current user message
        conversation_history: Previous conversation exchanges
        emotion_from_model: Emotion classified by trained model
        emotion_confidence: Confidence of emotion classification
    
    Returns:
        Classification dict with interaction_type and recommendations
    """
    classifier = get_classifier()
    return classifier.classify(message, conversation_history, 
                              emotion_from_model, emotion_confidence)
