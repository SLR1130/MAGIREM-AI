"""
REBUILT SmartResponseGenerator - Simple, Fast, Reliable
Focuses on empathetic responses without complex AI overhead
Uses keyword matching + templates instead of expensive ML operations
"""

import json
import os
import random
from typing import Dict, List, Optional

class SmartResponseGeneratorV2:
    """Simple, fast response generator focused on empathy"""
    
    def __init__(self):
        """Initialize with templates (NO heavy ML loading)"""
        self.response_templates = self._create_templates()
        self.crisis_keywords = self._create_crisis_keywords()
        self.topic_keywords = self._create_topic_keywords()
        
    def _create_templates(self) -> Dict:
        """Empathetic response templates by topic"""
        return {
            'grief': [
                "I'm so sorry for your loss. Losing someone you care about is incredibly painful. There's no timeline for grief - it's okay to feel whatever you're feeling. Do you want to talk about them?",
                "That sounds like a devastating loss. Grief is one of the hardest emotions we experience. Remember that what you're feeling is valid. Would it help to share a memory or talk about what they meant to you?",
                "I can hear how much they meant to you. Grief takes time, and there's no 'right' way to feel. You're not alone in this. What's been the hardest part?",
                "Losing them must feel overwhelming. The pain you're feeling shows how much you loved them. That's something real and precious, even though it hurts right now. How are you holding up?",
            ],
            'anxiety': [
                "I hear that you're worried about this. Anxiety can feel really overwhelming and make everything seem urgent. Let's slow down together. What's the main thing you're anxious about?",
                "That racing mind feeling - I know it's tough. When anxiety takes over, it's hard to think clearly. You're safe right now. What would help you feel a bit calmer?",
                "Anxiety is exhausting, especially when you're not sure why you're feeling it. What's going through your mind right now? Sometimes talking it out helps.",
                "The worry you're carrying sounds heavy. Anxiety makes things feel bigger and scarier than they are. What's the one thing you're most worried about?",
            ],
            'sadness': [
                "That weight you're describing - I really hear it. Sadness can feel so isolating. But you're not alone in this. I'm here listening. What's been making you feel this way?",
                "It sounds like you're going through something difficult right now. Your feelings are completely valid. I'm here to listen without judgment. What would help you feel a bit better today?",
                "Sadness like this is heavy to carry. It's okay to feel this way. You don't have to figure everything out right now. What do you need - do you want to talk, or just have someone listen?",
                "I can sense the sadness in what you're sharing. That's real and it matters. You're not alone. What's one thing that would bring you even a tiny bit of comfort right now?",
            ],
            'anger': [
                "I can sense the frustration in what you're saying. Something real is bothering you, and that's valid. Your anger is telling you that a boundary matters. What's at the core of this?",
                "You sound upset, and you have every right to be. Anger often masks something deeper - maybe hurt or feeling disrespected. What triggered this? What happened?",
                "That frustration you're expressing - it's trying to tell you something important. Something isn't sitting right with you. What's the boundary that's being crossed?",
                "Anger can be intense and confusing. But it's also telling you that something matters to you. What's really bothering you beneath the anger?",
            ],
            'joy': [
                "That's wonderful! I'm genuinely happy to hear something positive from you! Tell me more about what's going well. That's fantastic news!",
                "I love the positive energy you're sharing! That's amazing! Congratulations! Tell me all about this achievement!",
                "It's so great to hear you feeling happy! That's a beautiful thing. Tell me about this wonderful news!",
                "You sound genuinely happy and excited, and that's fantastic! I'm celebrating this with you! Tell me what happened!",
            ],
            'neutral': [
                "Thank you for sharing that with me. I appreciate you opening up. Help me understand a bit more - what brought you here today? What's on your mind?",
                "I hear you. I'm genuinely listening. What would you like to talk about? I'm here without judgment.",
                "That's important. I want to understand better. What's really going on for you right now? What matters most to you?",
                "I'm here for you. What's something that's been on your mind? Something you've wanted to talk about?",
            ],
        }
    
    def _create_crisis_keywords(self) -> List[str]:
        """Keywords that indicate crisis (requires immediate resources)"""
        return [
            'i want to die',
            'i want to kill myself',
            'i\'m suicidal',
            'going to kill myself',
            'going to hurt myself',
            'want to end it',
            'can\'t take this anymore',
            'want to die',
            'suicidal',
        ]
    
    def _create_topic_keywords(self) -> Dict[str, List[str]]:
        """Keywords for detecting conversation topics"""
        return {
            'grief': ['died', 'death', 'passed away', 'lost', 'pet', 'dog', 'cat', 'funeral', 'mourning', 'mom', 'dad', 'father', 'mother', 'best friend'],
            'anxiety': ['anxious', 'worried', 'nervous', 'stressed', 'panic', 'afraid', 'scared', 'nervous'],
            'sadness': ['sad', 'down', 'depressed', 'lonely', 'heartbroken', 'tears', 'crying'],
            'anger': ['angry', 'furious', 'frustrated', 'mad', 'rage', 'irritated'],
            'academic': ['exam', 'test', 'failed', 'grade', 'school', 'college', 'homework', 'assignment'],
            'relationship': ['breakup', 'broke up', 'boyfriend', 'girlfriend', 'friend', 'relationship', 'ex', 'dumped'],
        }
    
    def detect_emotion(self, message: str) -> str:
        """Detect emotion from message - SIMPLE keyword matching with context"""
        text_lower = message.lower()
        
        # Check for crisis FIRST
        for keyword in self.crisis_keywords:
            if keyword in text_lower:
                return 'crisis'  # Special emotion
        
        # Check for explicit negative emotional states (sadness)
        sadness_words = ['sad', 'down', 'not good', 'depressed', 'lonely', 'heartbroken', 'tears', 'crying', 'terrible', 'awful', 'miserable', 'struggling', 'not okay', 'still not']
        if any(word in text_lower for word in sadness_words):
            return 'sadness'
        
        # Check for anxiety/worry
        anxiety_words = ['anxious', 'worried', 'nervous', 'stressed', 'panic', 'afraid', 'scared', 'anxiety', 'nervous']
        if any(word in text_lower for word in anxiety_words):
            return 'anxiety'
        
        # Check for positive indicators (joy)
        positive_words = ['happy', 'great', 'wonderful', 'amazing', 'love', 'excited', 'fantastic', 'awesome', 'good', 'won', 'medal', 'competition', 'achievement', 'success']
        if any(word in text_lower for word in positive_words):
            return 'joy'
        
        # Check for explicit emotional keywords (grief, anger, etc)
        emotions = ['grief', 'anger', 'academic', 'relationship']
        for emotion in emotions:
            keywords = self.topic_keywords.get(emotion, [])
            if any(kw in text_lower for kw in keywords):
                return emotion
        
        return 'neutral'
    
    def generate_response(self, user_text: str, emotion: str = None, contexts: Dict = None, 
                         conversation_history: List = None, laravel_crisis_level: str = 'none') -> str:
        """Generate empathetic response - FAST and RELIABLE
        
        Args:
            user_text: The user's message
            emotion: Detected emotion (None = auto-detect)
            contexts: Additional context dict
            conversation_history: Previous messages
            laravel_crisis_level: Crisis level from Laravel's Gemma assessment ('none', 'moderate', 'critical')
                                 If set to 'none' or 'moderate', this generator must NOT return crisis hotline template
        """
        import sys
        
        # DEBUG: Log what emotion we received
        sys.stderr.write(f"[V2] generate_response called with emotion='{emotion}', laravel_crisis_level='{laravel_crisis_level}' (type: {type(laravel_crisis_level).__name__})\n")
        sys.stderr.flush()
        
        if not emotion:
            sys.stderr.write(f"[V2] Emotion is None/empty, doing own detection\n")
            sys.stderr.flush()
            emotion = self.detect_emotion(user_text)
            sys.stderr.write(f"[V2] Detected emotion: {emotion}\n")
            sys.stderr.flush()
        else:
            sys.stderr.write(f"[V2] Using provided emotion: {emotion}\n")
            sys.stderr.flush()
        
        # TASK 6 FIX: Do NOT return crisis response based on keyword matching
        # If Laravel passed laravel_crisis_level='none' or 'moderate', we must NOT override it with keyword matching
        # This prevents "I used to want to die but I'm doing better now" from returning hotlines
        if laravel_crisis_level and laravel_crisis_level in ['none', 'moderate']:
            sys.stderr.write(f"[V2] Laravel says crisis_level={laravel_crisis_level} - FORCING emotion away from crisis\n")
            sys.stderr.flush()
            # Force emotion to neutral or sadness to get a supportive non-crisis response
            if emotion == 'crisis' or emotion is None:
                emotion = 'sadness'  # Treat crisis emotion as sadness if Laravel says safe/moderate
                sys.stderr.write(f"[V2] Overriding emotion to 'sadness' because Laravel says {laravel_crisis_level}\n")
                sys.stderr.flush()
        elif not laravel_crisis_level or laravel_crisis_level == '':
            # If laravel_crisis_level is empty/None, default to safe behavior
            sys.stderr.write(f"[V2] No laravel_crisis_level provided (value: {repr(laravel_crisis_level)}), defaulting to safe behavior\n")
            sys.stderr.flush()
            if emotion == 'crisis':
                emotion = 'sadness'
        
        # Get appropriate template
        sys.stderr.write(f"[V2] Getting templates for emotion: {emotion}\n")
        sys.stderr.flush()
        templates = self.response_templates.get(emotion, self.response_templates['neutral'])
        sys.stderr.write(f"[V2] Got {len(templates)} templates for emotion '{emotion}'\n")
        sys.stderr.flush()
        response = random.choice(templates)
        sys.stderr.write(f"[V2] Selected response: {response[:50]}...\n")
        sys.stderr.flush()
        
        # Add context-specific follow-up if we have history
        if conversation_history and len(conversation_history) > 0:
            followup = self._generate_followup(user_text, emotion, conversation_history)
            if followup:
                response = f"{response}\n\n{followup}"
        
        return response
    
    def _get_crisis_response(self) -> str:
        """Crisis response with emergency hotlines
        
        DEPRECATED: This should NOT be called directly anymore.
        Flask/Laravel now handles crisis responses at the controller level.
        This method is kept for backwards compatibility only.
        """
        # TASK 6: NEVER return crisis response from generator
        # Laravel is the authority on crisis decisions
        # If Flask generator is called, it should return supportive non-crisis content
        return self.response_templates['sadness'][0]  # Return sadness template instead
    
    def _generate_followup(self, user_text: str, emotion: str, history: List) -> Optional[str]:
        """Generate context-aware follow-up question"""
        text_lower = user_text.lower()
        
        # Direct follow-ups for specific mentions
        if any(word in text_lower for word in ['won', 'medal', 'achievement', 'competition', 'success']):
            return "That's amazing! Tell me more about this achievement - how does it feel?"
        
        if any(word in text_lower for word in ['died', 'death', 'passed', 'loss', 'lose', 'lost someone']):
            return "I'm so sorry. Tell me about them - what do you miss most?"
        
        # Detect topic from conversation
        topic = None
        for topic_name, keywords in self.topic_keywords.items():
            if any(kw in text_lower for kw in keywords):
                topic = topic_name
                break
        
        # Generate topic-specific follow-up
        if topic == 'grief':
            return "Tell me about them - what do you miss most?"
        elif topic == 'anxiety':
            return "What's the main thing you're worried about? Let's talk about it."
        elif topic == 'academic':
            return "That sounds frustrating. Do you have support from your teachers or advisors?"
        elif topic == 'relationship':
            return "How are you holding up with this? Do you have support?"
        elif emotion == 'sadness':
            return "What would help you feel a little better right now? Just for today?"
        elif emotion == 'joy':
            return "Keep sharing - I want to hear more about this!"
        elif emotion == 'anxiety':
            return "What's the main concern? We can work through it together."
        
        return None


# Test the new generator
if __name__ == '__main__':
    gen = SmartResponseGeneratorV2()
    
    # Test cases
    tests = [
        ("My dog died", "Should show grief response"),
        ("I want to die", "Should show crisis response"),
        ("I'm so anxious about exams", "Should show anxiety + academic response"),
        ("I'm feeling really happy!", "Should show joy response"),
    ]
    
    for message, expected in tests:
        emotion = gen.detect_emotion(message)
        response = gen.generate_response(message, emotion)
        print(f"\nTest: {message}")
        print(f"Emotion: {emotion}")
        print(f"Response: {response[:100]}...")
        print(f"Expected: {expected}")
