"""
Proactive Greeting Generator - Creates natural, LLM-based check-ins
NO templates - pure learned responses
Uses memory context to inform greeting
"""

import requests
from typing import Dict, List, Optional
import json

class ProactiveGreetingGenerator:
    """
    Generates natural proactive greetings using LLM
    Not hardcoded templates - each greeting is generated fresh
    """
    
    def __init__(self, ollama_url: str = "http://localhost:11434", model: str = "orca-mini:3b"):
        """Initialize generator"""
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
    
    def generate_proactive_greeting(self,
                                   memory: Dict,
                                   greeting_style: str = 'topic_checkin',
                                   recent_exchange: Optional[Dict] = None) -> Dict:
        """
        Generate a natural proactive greeting based on memory
        
        Args:
            memory: Memory to check in about
            greeting_style: 'project_checkin', 'emotional_support', 'topic_checkin'
            recent_exchange: The most recent exchange (for context)
        
        Returns:
            {
                'success': bool,
                'greeting': str,  # The generated greeting
                'memory_id': int,
                'style': str,
                'method': str  # 'llm_generated' or 'fallback'
            }
        """
        
        # Try LLM generation first
        if self.test_connection():
            return self._generate_with_llm(memory, greeting_style)
        else:
            # Fallback if LLM unavailable
            return self._generate_fallback(memory, greeting_style)
    
    def _generate_with_llm(self, memory: Dict, greeting_style: str) -> Dict:
        """Generate greeting using LLM"""
        try:
            # Build prompt for LLM
            prompt = self._build_greeting_prompt(memory, greeting_style)
            
            # Call LLM
            response = requests.post(
                self.api_endpoint,
                json={
                    "model": self.model,
                    "prompt": prompt,
                    "stream": False,
                    "temperature": 0.8,  # Slightly higher for variety
                    "top_p": 0.9,
                    "top_k": 40,
                },
                timeout=(5, 20)  # Connection 5s, read 20s (increased from 15s for greeting generation)
            )
            
            if response.status_code == 200:
                result = response.json()
                greeting = result.get("response", "").strip()
                
                # Clean and validate response
                greeting = self._clean_greeting(greeting)
                
                if greeting and len(greeting) > 3:
                    return {
                        'success': True,
                        'greeting': greeting,
                        'memory_id': memory.get('id'),
                        'style': greeting_style,
                        'method': 'llm_generated'
                    }
            
            # LLM failed, use fallback
            return self._generate_fallback(memory, greeting_style)
            
        except Exception as e:
            print(f"[GREETING_ERROR] LLM generation failed: {e}")
            return self._generate_fallback(memory, greeting_style)
    
    def _build_greeting_prompt(self, memory: Dict, greeting_style: str) -> str:
        """Build prompt for LLM to generate greeting"""
        
        memory_content = memory.get('content', '')
        age_days = memory.get('age_days', 1)
        importance = memory.get('importance', 0.5)
        
        prompt = """You are MAGI, the wellness companion inside MAGIREM AI, a warm and natural conversation partner.

Your task: Generate a brief, natural greeting that checks in on something the user mentioned before.

CRITICAL RULES - NO TEMPLATES:
- Be conversational and genuine, like a friend checking in
- Don't sound robotic or use templates
- Short and natural (1-2 sentences max)
- Reference the specific thing they mentioned, not generic
- If it was about a project, ask how it's going
- If it was emotional, show you care but don't be dramatic
- Use casual tone (like "bro", "dude" if user uses it)

MEMORY TO CHECK IN ABOUT:
"""
        
        prompt += f"Topic: {memory_content}\n"
        prompt += f"Style: {greeting_style}\n"
        
        if greeting_style == 'project_checkin':
            prompt += "\nGenerate a brief, natural greeting asking how their project is going. "
            prompt += "Make it sound like you genuinely remember and care about their project.\n"
        elif greeting_style == 'emotional_support':
            prompt += "\nGenerate a brief, warm greeting showing you remember they were struggling with something. "
            prompt += "Make it feel like genuine concern from a friend.\n"
        else:  # topic_checkin
            prompt += "\nGenerate a brief, natural greeting that recalls something they mentioned. "
            prompt += "Make it conversational, not forced.\n"
        
        prompt += "\nGenerate ONLY the greeting, nothing else. No meta-commentary."
        prompt += "\nGreeting:"
        
        return prompt
    
    def _clean_greeting(self, text: str) -> str:
        """Clean and validate generated greeting"""
        
        # Remove AI: or greeting: prefixes
        if text.lower().startswith('greeting:'):
            text = text[9:].strip()
        if text.lower().startswith('ai:'):
            text = text[3:].strip()
        
        # Try JSON parsing if quoted
        try:
            if text.startswith('"') and text.endswith('"'):
                text = json.loads(text)
        except:
            pass
        
        # Remove extra quotes
        text = text.strip('"\'')
        
        # Remove extra whitespace
        text = " ".join(text.split())
        
        # Limit to reasonable length (2-3 sentences)
        sentences = text.split('.')
        if len(sentences) > 3:
            text = '.'.join(sentences[:3]).strip() + '.'
        
        return text
    
    def _generate_fallback(self, memory: Dict, greeting_style: str) -> Dict:
        """Generate greeting without LLM (fallback)"""
        
        memory_content = memory.get('content', '')
        mem_type = memory.get('memory_type', 'semantic')
        
        # Extract key term from memory
        key_term = self._extract_key_term(memory_content)
        
        if greeting_style == 'project_checkin':
            # Project check-in pattern
            fallback_greetings = [
                f"Hey bro, how's {key_term} going?",
                f"Yo, any progress on {key_term}?",
                f"Bro, what's the status on {key_term}? 👀",
                f"How's that {key_term} project coming along?",
            ]
        elif greeting_style == 'emotional_support':
            # Emotional check-in pattern
            fallback_greetings = [
                f"Hey, how are you doing now? Thinking about what you mentioned.",
                f"Bro, how have you been? Want to talk about it?",
                f"Just wanted to check in - how are things now?",
                f"Hey, wanted to see how you're holding up.",
            ]
        else:  # topic_checkin
            # Generic topic check-in
            fallback_greetings = [
                f"Hey, remember when you mentioned {key_term}? How's that going?",
                f"Bro, what's up with {key_term}?",
                f"Yo, whatever happened with {key_term}?",
                f"How's the {key_term} situation now?",
            ]
        
        import random
        greeting = random.choice(fallback_greetings)
        
        return {
            'success': True,
            'greeting': greeting,
            'memory_id': memory.get('id'),
            'style': greeting_style,
            'method': 'fallback'
        }
    
    def _extract_key_term(self, text: str) -> str:
        """Extract a key term from memory content for fallback greetings"""
        import re
        
        # Look for capitalized terms (likely names/entities)
        capitalized = re.findall(r'\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b', text)
        
        if capitalized:
            # Return first significant term
            for term in capitalized:
                if len(term) > 3 and term not in ['The', 'User', 'I']:
                    return term.lower()
        
        # Look for quoted strings
        quoted = re.findall(r'["\']([^"\']+)["\']', text)
        if quoted:
            return quoted[0][:20].lower()
        
        # Extract first significant word
        words = re.findall(r'\b[a-z]{4,}\b', text.lower())
        if words:
            return words[0]
        
        # Last resort
        return "that"


# Global instance
_greeting_generator = None

def init_proactive_greeting_generator(ollama_url: str = "http://localhost:11434",
                                     model: str = "orca-mini:3b"):
    """Initialize proactive greeting generator"""
    global _greeting_generator
    _greeting_generator = ProactiveGreetingGenerator(ollama_url, model)
    print("[GREETING] Proactive greeting generator initialized")
    return _greeting_generator

def get_proactive_greeting_generator() -> Optional[ProactiveGreetingGenerator]:
    """Get greeting generator instance"""
    global _greeting_generator
    return _greeting_generator

def generate_proactive_greeting(memory: Dict,
                               greeting_style: str = 'topic_checkin',
                               recent_exchange: Optional[Dict] = None) -> Dict:
    """Generate a proactive greeting"""
    global _greeting_generator
    
    if _greeting_generator is None:
        init_proactive_greeting_generator()
    
    if _greeting_generator is None:
        return {
            'success': False,
            'error': 'Greeting generator not initialized'
        }
    
    return _greeting_generator.generate_proactive_greeting(memory, greeting_style, recent_exchange)
