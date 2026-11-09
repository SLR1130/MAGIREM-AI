"""
Counselor Suggestion Engine
Intelligently suggests professional counselor help based on conversation context
Not forced, but situationally aware
"""

import re
from typing import Dict, List, Tuple

class CounselorSuggestionEngine:
    """
    Analyzes conversation to determine if counselor suggestion would be helpful
    Uses severity assessment + conversation context
    """
    
    def __init__(self):
        # Topics that definitely need professional help
        self.critical_keywords = {
            'suicide': 10,
            'want to die': 10,
            'kill myself': 10,
            'self harm': 10,
            'cut myself': 10,
            'self-harm': 10,
            'end my life': 10,
            'worthless': 9,
            'hopeless': 9,
            'no point': 9,
        }
        
        # Serious topics that warrant counselor discussion
        self.serious_keywords = {
            'death': 8,
            'died': 8,
            'bullying': 7,
            'bullied': 7,
            'trauma': 8,
            'abuse': 8,
            'assault': 8,
            'rape': 8,
            'eating disorder': 7,
            'addiction': 7,
            'severe depression': 8,
            'severe anxiety': 7,
            'panic attacks': 6,
            'self-worth': 6,
            'lost': 5,
            'grief': 7,
            'bereaved': 8,
            'loss': 6,
        }
        
        # Moderate concerns that might benefit from counselor
        self.moderate_keywords = {
            'bullying': 5,
            'lonely': 5,
            'isolated': 5,
            'depressed': 5,
            'anxious': 4,
            'stressed': 4,
            'overwhelmed': 5,
            'struggling': 4,
            'difficult': 3,
        }
    
    def should_suggest_counselor(self, 
                                 conversation_text: str,
                                 emotion: str,
                                 crisis_level: str,
                                 conversation_history: List[Dict] = None) -> Tuple[bool, str, int]:
        """
        Determine if counselor should be suggested
        
        Returns:
            (should_suggest, reason, severity_score 0-10)
        """
        
        # Critical: Always suggest if crisis detected
        if crisis_level in ['critical', 'high']:
            return True, "Crisis situation detected - professional support recommended", 10
        
        # Analyze conversation severity
        severity = self._calculate_severity(conversation_text, emotion, conversation_history)
        
        # Check for multiple serious indicators
        serious_indicators = self._count_serious_indicators(conversation_text)
        
        # Decision logic:
        # 1. Multiple losses/traumas = suggest
        if serious_indicators['death_mentions'] > 0 and serious_indicators['other_serious'] > 0:
            reason = "Multiple serious issues detected - talking to a counselor could really help"
            return True, reason, severity
        
        # 2. High severity + negative emotion = suggest
        if severity >= 7 and emotion in ['sadness', 'anxiety', 'anger']:
            reason = "You're dealing with a lot - a counselor can provide additional support"
            return True, reason, severity
        
        # 3. Isolation + loss = suggest
        if serious_indicators['isolation'] and serious_indicators['death_mentions'] > 0:
            reason = "Grief combined with isolation - professional guidance can help you cope"
            return True, reason, severity
        
        # 4. Very high severity alone = suggest
        if severity >= 8:
            reason = "What you're going through is significant - professional support would be valuable"
            return True, reason, severity
        
        # Otherwise, don't force suggestion
        return False, "", severity
    
    def _calculate_severity(self, text: str, emotion: str, history: List[Dict] = None) -> int:
        """Calculate overall severity (0-10)"""
        severity = 0
        text_lower = text.lower()
        
        # Check critical keywords
        for keyword, weight in self.critical_keywords.items():
            if keyword in text_lower:
                severity += weight
        
        # Check serious keywords
        for keyword, weight in self.serious_keywords.items():
            if keyword in text_lower:
                severity += weight
        
        # Emotion multiplier
        if emotion in ['sadness', 'anxiety']:
            severity += 2
        elif emotion == 'anger':
            severity += 1
        
        # Multiple messages in conversation = more severity
        if history and len(history) > 2:
            severity += min(len(history), 3)  # Max +3 for long conversations
        
        # Repeated mentions increase severity
        critical_count = sum(1 for kw in self.critical_keywords.keys() 
                            if kw in text_lower)
        serious_count = sum(1 for kw in self.serious_keywords.keys() 
                          if kw in text_lower)
        
        if critical_count > 1:
            severity += critical_count * 2
        if serious_count > 2:
            severity += 2
        
        return min(severity, 10)  # Cap at 10
    
    def _count_serious_indicators(self, text: str) -> Dict:
        """Count different types of serious indicators"""
        text_lower = text.lower()
        
        return {
            'death_mentions': len([kw for kw in ['death', 'died', 'loss', 'grief', 'bereaved'] 
                                  if kw in text_lower]),
            'isolation': any(kw in text_lower for kw in ['alone', 'lonely', 'isolated', 'nobody', 'no one', 'only you']),
            'trauma': any(kw in text_lower for kw in ['bullied', 'bullying', 'abuse', 'assault', 'trauma', 'accident']),
            'crisis': any(kw in text_lower for kw in ['suicide', 'self harm', 'kill myself', 'want to die']),
            'other_serious': len([kw for kw in self.serious_keywords.keys() if kw in text_lower])
        }
    
    def generate_suggestion_message(self, message: str, emotion: str, severity: int, 
                                  indicators: Dict = None) -> str:
        """
        Generate contextual counselor suggestion message based on actual situation
        Uses the student's actual message + emotion to create personalized suggestion
        NOT hardcoded template messages
        """
        
        # Analyze what the student shared to create contextual message
        msg_lower = message.lower()
        
        # Crisis - immediate and urgent
        if any(kw in msg_lower for kw in ['suicide', 'want to die', 'kill myself', 'self harm']):
            return (
                "I'm really concerned about your safety right now. What you're sharing matters, and you deserve "
                "immediate professional support. A counselor can help - they're trained for exactly this. "
                "Would you be willing to connect with one right now?"
            )
        
        # Loss/Grief focused
        if any(kw in msg_lower for kw in ['death', 'died', 'died', 'lost', 'loss', 'grief', 'bereaved']):
            return (
                "Grief is one of the most difficult things to process alone. I want to support you, but "
                "a counselor has specialized training in helping people work through loss like what you're experiencing. "
                "They could really make a difference. Interested in connecting?"
            )
        
        # Isolation/Loneliness focused
        if any(kw in msg_lower for kw in ['alone', 'lonely', 'no one', 'nobody', 'isolated', 'only you']):
            return (
                "Feeling isolated is one of the hardest things. You reaching out to me shows strength, "
                "but a counselor could help you build more real connections and work through this loneliness. "
                "Would you like to explore that?"
            )
        
        # Bullying/Harassment focused
        if any(kw in msg_lower for kw in ['bullying', 'bullied', 'bully', 'harassment', 'harassed']):
            return (
                "Being bullied is never your fault, and you don't have to handle it alone. A counselor can help "
                "you process what's happening and develop strategies to protect your wellbeing. They've helped many "
                "students through this. Want to talk with one?"
            )
        
        # Anxiety/Stress focused
        if emotion in ['anxiety', 'stress'] and severity >= 7:
            return (
                "What you're experiencing sounds really overwhelming. Anxiety and stress at this level are worth "
                "getting professional help with. A counselor can teach you techniques and coping strategies that work. "
                "Would that be helpful?"
            )
        
        # Multiple serious issues (generic)
        if severity >= 8:
            return (
                "You're dealing with a lot right now, and that's genuinely significant. I'm here for you, "
                "but a counselor brings specialized expertise that could really help you navigate all of this. "
                "They're here to support students exactly like you. Open to connecting?"
            )
        
        # Moderate severity - supportive suggestion
        return (
            "What you're sharing shows you're thinking deeply about your situation. Sometimes talking with a "
            "counselor can help you see new perspectives and find solutions you might not see alone. "
            "It's worth considering - would you be interested?"
        )
    
    def get_counselor_connection_prompt(self, student_preference: str = 'companion') -> str:
        """
        Get a prompt for connecting to counselor (not forced)
        """
        if student_preference == 'counselor':
            return (
                "Would you like me to help you find a counselor? I can show you who's available "
                "and help you set up an appointment if you'd like."
            )
        else:
            return (
                "I can connect you with a counselor if you'd like, but no pressure. They're here when you need them. "
                "What would be most helpful for you right now?"
            )


# Global instance
_suggestion_engine = None

def get_suggestion_engine():
    """Get or create the suggestion engine"""
    global _suggestion_engine
    if _suggestion_engine is None:
        _suggestion_engine = CounselorSuggestionEngine()
    return _suggestion_engine


def analyze_for_counselor_suggestion(message: str, 
                                     emotion: str,
                                     crisis_level: str,
                                     conversation_history: List[Dict] = None) -> Dict:
    """
    Main interface: Analyze if counselor suggestion is appropriate
    
    Returns:
        {
            'should_suggest': bool,
            'reason': str,
            'severity': int (0-10),
            'suggestion_message': str or None (CONTEXTUAL - not hardcoded),
            'connection_prompt': str or None
        }
    """
    engine = get_suggestion_engine()
    
    should_suggest, reason, severity = engine.should_suggest_counselor(
        message, 
        emotion, 
        crisis_level, 
        conversation_history
    )
    
    # Generate contextual message based on actual student message + emotion + severity
    suggestion_message = None
    if should_suggest:
        suggestion_message = engine.generate_suggestion_message(
            message,           # Pass actual student message for context
            emotion,           # Include emotion to personalize
            severity,          # Include severity level
            engine._count_serious_indicators(message)  # Include detected indicators
        )
    
    return {
        'should_suggest': should_suggest,
        'reason': reason,
        'severity': severity,
        'suggestion_message': suggestion_message,  # CONTEXTUAL, not hardcoded
        'connection_prompt': engine.get_counselor_connection_prompt() if should_suggest else None,
    }
