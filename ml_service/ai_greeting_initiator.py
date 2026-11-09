"""
AI Greeting Initiator - AI starts conversations when chat is created
NOT proactive check-ins (those happen later in the conversation)
This is: User opens chat -> AI says hello/greeting immediately
"""

import requests
import uuid
from gpu_inference_queue import queue as gpu_inference_queue
from typing import Dict, Optional
from datetime import datetime

class AIGreetingInitiator:
    """
    Generates initial AI greeting when user starts a new chat session
    This is the FIRST message - AI initiates
    """
    
    def __init__(self, ollama_url: str = "http://localhost:11434", model: str = "neural-chat"):
        """Initialize greeting initiator"""
        self.ollama_url = ollama_url
        self.model = model
        self.api_endpoint = f"{ollama_url}/api/generate"
    
    def test_connection(self) -> bool:
        """Test if Ollama is available"""
        try:
            response = requests.get(f"{self.ollama_url}/api/tags", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def generate_initial_greeting(self,
                                 user_id: str,
                                 user_name: str = None,
                                 recent_memories: list = None,
                                 has_previous_conversations: bool = False) -> Dict:
        """
        Generate initial AI greeting for new conversation
        
        Args:
            user_id: User identifier
            user_name: User's name (optional)
            recent_memories: Recent memories to reference (optional)
            has_previous_conversations: Whether user has chatted before
        
        Returns:
            {
                'success': bool,
                'greeting': str,  # The AI's opening message
                'method': str,    # 'llm_generated' or 'fallback'
                'type': str,      # 'returning_user' or 'new_user'
            }
        """
        
        # Try LLM first
        if self.test_connection():
            return self._generate_with_llm(user_name, recent_memories, has_previous_conversations)
        else:
            return self._generate_fallback(user_name, has_previous_conversations)
    
    def _generate_with_llm(self,
                          user_name: str,
                          recent_memories: list,
                          has_previous_conversations: bool) -> Dict:
        """Generate greeting using LLM"""
        try:
            prompt = self._build_greeting_prompt(user_name, recent_memories, has_previous_conversations)
            
            request_id = f"greeting-{uuid.uuid4().hex}"
            with gpu_inference_queue.acquire(request_id):
                response = requests.post(
                    self.api_endpoint,
                json={
                    "model": self.model,
                    "prompt": prompt,
                    "stream": False,
                    "temperature": 0.7,
                    "top_p": 0.9,
                    "top_k": 40,
                },
                    timeout=(5, 20)  # Connection 5s, read 20s (increased from 15s for greeting generation)
                )
            
            if response.status_code == 200:
                result = response.json()
                greeting = result.get("response", "").strip()
                greeting = self._clean_greeting(greeting)
                
                if greeting and len(greeting) > 3:
                    return {
                        'success': True,
                        'greeting': greeting,
                        'method': 'llm_generated',
                        'type': 'returning_user' if has_previous_conversations else 'new_user'
                    }
            
            return self._generate_fallback(user_name, has_previous_conversations)
            
        except Exception as e:
            print(f"[GREETING_ERROR] LLM generation failed: {e}")
            return self._generate_fallback(user_name, has_previous_conversations)
    
    def _build_greeting_prompt(self,
                              user_name: str,
                              recent_memories: list,
                              has_previous_conversations: bool) -> str:
        """Build prompt for initial greeting"""
        
        prompt = """You are MAGI, the wellness companion inside MAGIREM AI, a warm and empathetic student support companion.

Your task: Generate a warm, welcoming OPENING message to start a conversation.

CRITICAL RULES - This is the FIRST message:
- Be genuine and warm, not robotic
- Keep it SHORT (1-2 sentences max)
- Ask something open-ended to invite sharing
- If returning user, acknowledge their return
- If new user, welcome them and encourage them to share
- Be conversational, like talking to a friend
- NO templates or formulas
- NO "I'm here to help you with..." - too formal
- YES "Hey, how's it going?" - natural and warm

"""
        
        if user_name:
            prompt += f"User's name: {user_name}\n"
        
        if has_previous_conversations:
            prompt += "This is a RETURNING user (they've chatted before)\n"
            prompt += "Keep greeting warm but brief - they know you\n"
        else:
            prompt += "This is a NEW user (first time chatting)\n"
            prompt += "Make them feel welcome and safe to share\n"
        
        if recent_memories:
            prompt += f"\nRecent topics they care about: {recent_memories[:2]}\n"
            prompt += "You MAY gently reference these if natural\n"
        
        prompt += "\nGenerate ONLY the greeting (1-2 sentences), nothing else.\n"
        prompt += "Opening message:"
        
        return prompt
    
    def _clean_greeting(self, text: str) -> str:
        """Clean generated greeting"""
        # Remove AI: or greeting: prefixes
        if text.lower().startswith('greeting:'):
            text = text[9:].strip()
        if text.lower().startswith('ai:'):
            text = text[3:].strip()
        if text.lower().startswith('message:'):
            text = text[8:].strip()
        
        # Remove extra whitespace
        text = " ".join(text.split())
        
        return text
    
    def _generate_fallback(self,
                          user_name: str,
                          has_previous_conversations: bool) -> Dict:
        """Fallback greetings when LLM unavailable"""
        
        if has_previous_conversations:
            # Returning user
            fallback_greetings = [
                "Hey bro, what's up? How are you doing today?",
                "Yo, good to see you again. What's on your mind?",
                "Hey! Welcome back. How's everything going?",
                "What's up bro? Ready to chat?",
                "Hey there, how have you been?",
            ]
        else:
            # New user
            fallback_greetings = [
                "Hey! Welcome. I'm here to listen. What's going on?",
                "Hi there! How are you doing today? What's on your mind?",
                "Hi! I'm MAGI, your wellness companion.",
                "Welcome! I'm here if you need to talk. How are you feeling?",
                "Hi! Good to meet you. What brings you here today?",
            ]
        
        import random
        greeting = random.choice(fallback_greetings)
        
        return {
            'success': True,
            'greeting': greeting,
            'method': 'fallback',
            'type': 'returning_user' if has_previous_conversations else 'new_user'
        }


# Global instance
_greeting_initiator = None

def init_greeting_initiator(ollama_url: str = "http://localhost:11434",
                           model: str = "neural-chat"):
    """Initialize greeting initiator"""
    global _greeting_initiator
    _greeting_initiator = AIGreetingInitiator(ollama_url, model)
    print("[GREETING_INITIATOR] AI greeting initiator initialized")
    return _greeting_initiator

def get_greeting_initiator() -> Optional[AIGreetingInitiator]:
    """Get initiator instance"""
    global _greeting_initiator
    return _greeting_initiator

def generate_initial_greeting(user_id: str,
                            user_name: str = None,
                            recent_memories: list = None,
                            has_previous_conversations: bool = False) -> Dict:
    """Generate initial greeting for new chat"""
    global _greeting_initiator
    
    if _greeting_initiator is None:
        init_greeting_initiator()
    
    if _greeting_initiator is None:
        return {
            'success': False,
            'error': 'Greeting initiator not initialized'
        }
    
    return _greeting_initiator.generate_initial_greeting(
        user_id,
        user_name,
        recent_memories,
        has_previous_conversations
    )
