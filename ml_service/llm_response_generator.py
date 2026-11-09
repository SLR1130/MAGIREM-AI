"""
LLM-Based Response Generator using Ollama/Local LLM
Replaces template-based responses with real conversational AI
"""

import requests
import uuid
from gpu_inference_queue import queue as gpu_inference_queue
import json
from typing import Optional, Dict

class LLMResponseGenerator:
    """
    Uses a local LLM (Ollama) to generate natural, conversational responses
    Like ChatGPT but running locally
    """
    
    def __init__(self, model="neural-chat", ollama_url="http://localhost:11434"):
        """
        Initialize with local LLM
        
        Args:
            model: Model name (neural-chat, llama2, mistral, etc.)
            ollama_url: URL where Ollama is running
        """
        self.model = model
        self.ollama_url = ollama_url
        self.api_endpoint = f"{ollama_url}/api/generate"
        
        # System prompt to make the AI empathetic and therapeutic
        self.system_prompt = """You are MAGI, the wellness companion inside MAGIREM AI. You're here to listen and help.

TONE:
- Professional but warm, like a caring counselor
- Natural and conversational, not robotic
- Genuine and human, not overly formal
- Responsive to what the user actually said

HOW TO RESPOND:
1. Address what they shared directly
2. Show you understand their specific situation
3. Keep it concise and natural (short paragraphs, not essays)
4. Use their context and words
5. Ask a gentle follow-up question if appropriate

FORMATTING:
- Use short paragraphs (2-3 sentences each)
- Separate thoughts with blank lines
- For advice or tips: just list them naturally, don't force numbered lists
- Use bold for important phrases only when it adds clarity
- Avoid unnecessary formatting or huge headings

EXAMPLES:
Bad: "Here are three strategies you can implement to manage stress more effectively: 1. Prioritize..."
Good: "A few things that help with stress. Try prioritizing your most urgent tasks first instead of trying to handle everything. Take short breaks when you start feeling overwhelmed."

Bad: "I hear you. That sounds really challenging."
Good: "That sounds really tough, especially with exams coming up."

GREETING RESPONSES:
- User says "hey" â†’ "Hey! How are you doing?" (warm, not formal)
- Keep it short and inviting

FOR SHORT MESSAGES:
- User says "ok" â†’ Brief acknowledgment + gentle follow-up
- Don't over-explain

FOR VENTING:
- Validate their feelings
- Show you understand what they said
- Support first, suggestions second

CONVERSATION FLOW:
- Build naturally on what they share
- Don't repeat yourself
- Avoid giving the same advice in multiple ways
- Adapt response length to their message

Remember: Sound like a real supportive friend, not an AI system."""
    
    def test_connection(self) -> bool:
        """Test if Ollama is running and accessible"""
        try:
            response = requests.get(f"{self.ollama_url}/api/tags", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def generate(self, message: str, conversation_history: list = None, emotion: str = "neutral") -> Dict:
        """
        Generate a natural response using local LLM
        
        Args:
            message: User's message
            conversation_history: Previous messages for context
            emotion: Detected emotion (for context)
        
        Returns:
            Dict with response, analysis, etc.
        """
        try:
            # Build conversation context
            context = self._build_context(message, conversation_history, emotion)
            
            # Call local LLM
            request_id = f"fallback-{uuid.uuid4().hex}"
            with gpu_inference_queue.acquire(request_id):
                response = requests.post(
                    self.api_endpoint,
                json={
                    "model": self.model,
                    "prompt": context,
                    "stream": False,
                    "temperature": 0.7,  # More natural responses
                    "top_p": 0.9,
                    "top_k": 40,
                },
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                generated_text = result.get("response", "").strip()
                
                # Clean up response
                generated_text = self._clean_response(generated_text)
                
                return {
                    "success": True,
                    "response": generated_text,
                    "analysis": {
                        "emotion": emotion,
                        "crisis_level": "none",  # Would need crisis detection
                        "model_used": "local_llm"
                    },
                    "ml_system": "llm_active"
                }
            else:
                return self._fallback_response(message, emotion)
                
        except requests.ConnectionError:
            print("[ERROR] Cannot connect to Ollama. Make sure it's running: ollama serve")
            return self._fallback_response(message, emotion)
        except Exception as e:
            print(f"[ERROR] LLM generation failed: {e}")
            return self._fallback_response(message, emotion)
    
    def _build_context(self, message: str, history: list, emotion: str) -> str:
        """Build the prompt context for the LLM"""
        
        # Start with system prompt
        context = self.system_prompt + "\n\n"
        
        # Add conversation history
        if history and len(history) > 0:
            context += "Previous conversation:\n"
            for i, msg_pair in enumerate(history[-5:]):  # Last 5 exchanges
                context += f"User: {msg_pair.get('user', '')}\n"
                context += f"AI: {msg_pair.get('ai', '')}\n"
            context += "\n"
        
        # Add emotion context
        if emotion and emotion != "neutral":
            context += f"[The user seems to be feeling {emotion}]\n"
        
        # Add current message
        context += f"User: {message}\n"
        context += "AI:"
        
        return context
    
    def _clean_response(self, response: str) -> str:
        """Clean up LLM response"""
        # Remove "AI:" prefix if present
        if response.startswith("AI:"):
            response = response[3:].strip()
        
        # Remove extra whitespace
        response = " ".join(response.split())
        
        # Ensure it's a reasonable length
        sentences = response.split(".")
        if len(sentences) > 3:
            response = ".".join(sentences[:3]).strip() + "."
        
        return response
    
    def _fallback_response(self, message: str, emotion: str) -> Dict:
        """Fallback when LLM is unavailable"""
        # Detect if it's a greeting
        greetings = ["hey", "hi", "hello", "hey there", "hiya", "howdy", "what's up", "sup"]
        message_lower = message.lower().strip()
        
        if any(g in message_lower for g in greetings):
            response = "Hey! How are you doing today?"
        elif emotion == "sadness":
            response = "I'm sorry you're feeling this way. What's been happening?"
        elif emotion == "joy":
            response = "That's great to hear! Tell me more about what's making you happy."
        elif emotion == "anxiety":
            response = "It sounds like you might be worried about something. I'm here to listen."
        else:
            response = "I'm here to listen. What's on your mind?"
        
        return {
            "success": True,
            "response": response,
            "analysis": {
                "emotion": emotion,
                "crisis_level": "none",
                "model_used": "fallback_greeting"
            },
            "ml_system": "fallback"
        }


# Global instance
_llm_generator = None

def init_llm_generator(model="neural-chat", ollama_url="http://localhost:11434"):
    """Initialize the LLM generator"""
    global _llm_generator
    _llm_generator = LLMResponseGenerator(model=model, ollama_url=ollama_url)
    
    # Test connection
    if _llm_generator.test_connection():
        print(f"[OK] LLM Generator initialized with {model}")
        print("[INFO] âœ… Using LOCAL LLM for natural conversations")
        return True
    else:
        print(f"[WARNING] Cannot connect to Ollama at {ollama_url}")
        print("[INFO] Make sure Ollama is running: ollama serve")
        return False

def generate_llm_response(message: str, conversation_history: list = None, emotion: str = "neutral") -> Dict:
    """Generate response using LLM"""
    global _llm_generator
    
    if _llm_generator is None:
        init_llm_generator()
    
    if _llm_generator is None:
        return {"success": False, "error": "LLM not initialized"}
    
    return _llm_generator.generate(message, conversation_history, emotion)
