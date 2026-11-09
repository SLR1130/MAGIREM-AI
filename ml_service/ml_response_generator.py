"""
ML-Based Response Generator
Replaces hardcoded templates with learned responses from DistilGPT-2
"""

import torch
import json
from pathlib import Path
from transformers import GPT2Tokenizer, GPT2LMHeadModel
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class MLResponseGenerator:
    def __init__(self):
        self.model_path = Path("./response_model/final")
        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        
        # Load model and tokenizer
        self.load_model()
        
    def load_model(self):
        """Load the trained model"""
        try:
            logger.info(f"Loading model from {self.model_path}")
            self.tokenizer = GPT2Tokenizer.from_pretrained(str(self.model_path))
            self.model = GPT2LMHeadModel.from_pretrained(str(self.model_path))
            self.model.to(self.device)
            self.model.eval()
            logger.info("✅ Model loaded successfully")
        except Exception as e:
            logger.error(f"Failed to load model: {e}")
            self.model = None
            self.tokenizer = None
    
    def generate_response(self, user_message, emotion_detected="neutral", context="", max_length=256):
        """
        Generate a response using the trained model
        
        Args:
            user_message: The user's message
            emotion_detected: Detected emotion (sadness, anxiety, etc.)
            context: Previous conversation context
            max_length: Maximum tokens to generate
            
        Returns:
            Generated response string
        """
        
        if self.model is None:
            logger.error("Model not loaded")
            return self._fallback_response(user_message, emotion_detected)
        
        try:
            # Use a simple, meaningful prompt that guides the model
            prompt = f"Emotion: {emotion_detected}\nResponse: "
            
            # Tokenize the prompt
            input_ids = self.tokenizer.encode(prompt, return_tensors="pt").to(self.device)
            prompt_length = input_ids.shape[1]
            
            # Generate response tokens - aim for 80-120 tokens for good length
            max_new_tokens = 80
            
            with torch.no_grad():
                output_ids = self.model.generate(
                    input_ids,
                    max_new_tokens=max_new_tokens,
                    temperature=0.75,
                    top_p=0.85,
                    do_sample=True,
                    num_beams=1,
                    pad_token_id=self.tokenizer.eos_token_id,
                )
            
            # Decode output
            full_text = self.tokenizer.decode(output_ids[0], skip_special_tokens=True)
            # Show raw bytes to detect issues
            logger.info(f"[RAW] Generated text: {full_text[:150]}")
            logger.info(f"[RAW_REPR] {repr(full_text[:150])}")
            
            # Extract the response part (everything after "Response: ")
            if "Response: " in full_text:
                response = full_text.split("Response: ", 1)[1].strip()
            else:
                response = full_text.strip()
            
            logger.info(f"[AFTER_SPLIT] Response: {response[:80]}")
            
            # Remove common artifacts and special tokens FIRST
            response = response.replace("<|endoftext|>", "").strip()
            response = response.replace("[MASK]", "").strip()
            response = response.replace("[PAD]", "").strip()
            
            # Remove any "Emotion:" prefix or mixed-in content
            if "Emotion:" in response:
                response = response.split("Emotion:")[0].strip()
            
            # Stop at first newline
            if '\n' in response:
                response = response.split('\n')[0].strip()
            
            logger.info(f"[AFTER_CLEANUP] Response: {response[:80]}")
            
            # Clean non-ASCII and extreme garbage characters CAREFULLY
            # Keep: letters, digits, punctuation, common symbols, spaces
            cleaned = ""
            for char in response:
                code = ord(char)
                # Accept: ASCII letters/digits/punct (32-126) and common whitespace
                if (32 <= code <= 126) or code in [9, 10, 13]:  # tab, newline, carriage return
                    cleaned += char
                elif code == 160:  # Non-breaking space - convert to regular space
                    cleaned += ' '
                # Skip everything else
            response = cleaned.strip()
            
            # Normalize whitespace (but preserve sentence structure)
            response = ' '.join(response.split())
            
            # Remove only extreme garbage: repeated special chars
            import re
            response = re.sub(r'([~_\-=\*])\1{2,}', '', response)  # Remove 3+ repeated chars
            response = response.strip()
            
            logger.info(f"[AFTER_ASCII_CLEAN] Response: {response[:80]}")
            
            # Extract complete sentences
            # Try to get up to 2-3 sentences
            import re
            raw_sentences = re.split(r'([.!?])', response)  # Split on punctuation but keep it
            
            sentences = []
            current = ""
            for part in raw_sentences:
                current += part
                if part in '.!?':
                    if len(current.strip()) > 5:  # Lower threshold - accept shorter sentences
                        sentences.append(current.strip())
                    current = ""
                    if len(sentences) >= 3:  # Get up to 3 sentences
                        break
            
            # Add any remaining partial sentence if it's substantial
            if current.strip() and len(current.strip()) > 8 and len(sentences) < 3:
                # Add period if missing
                if current.strip()[-1] not in '.!?':
                    current += '.'
                sentences.append(current.strip())
            
            if sentences:
                response = ' '.join(sentences)
            else:
                # If no complete sentences, use the whole response if long enough
                if len(response) > 8:
                    if response[-1] not in '.!?':
                        response += '.'
                # Otherwise response stays as-is (might be a fragment but at least something)
            
            response = response.strip()
            
            # Final validation - be more lenient
            # Accept responses of 10+ chars that have some substance
            if not response or len(response.strip()) < 10:
                logger.warning(f"Generated response too short or empty: '{response}'")
                return self._fallback_response(user_message, emotion_detected)
            
            # Check if response is just repeated characters or gibberish
            unique_chars = len(set(response.lower()))
            if unique_chars < 3 and len(response) > 5:  # Only 2-3 unique characters = garbage
                logger.warning(f"Generated response is gibberish (only {unique_chars} unique chars): '{response}'")
                return self._fallback_response(user_message, emotion_detected)
            
            logger.info(f"✓ Generated ({len(response)} chars): {response[:70]}...")
            return response
            
        except Exception as e:
            logger.error(f"Generation error: {e}")
            import traceback
            traceback.print_exc()
            return self._fallback_response(user_message, emotion_detected)
    
    def _fallback_response(self, user_message, emotion_detected):
        """Fallback empathetic responses"""
        
        fallbacks = {
            "sadness": [
                "I hear that you're going through a difficult time. That sounds really tough. How are you coping with this?",
                "I'm sorry you're feeling this way. Would you like to talk more about what's bothering you?",
                "It's okay to feel sad sometimes. You're not alone in this. What would help you feel better right now?"
            ],
            "anxiety": [
                "It sounds like you're feeling anxious. Take a deep breath. What's worrying you most right now?",
                "Anxiety can be overwhelming. Let's break this down together. What's on your mind?",
                "Your feelings are valid. Sometimes writing them down or talking helps. What's troubling you?"
            ],
            "anger": [
                "I can sense you're frustrated. It's okay to feel angry. What happened?",
                "Strong emotions are normal. Let's talk about what's making you feel this way.",
                "I'm here to listen. What's upset you?"
            ],
            "neutral": [
                "I'm here to listen and support you. How are you doing today?",
                "What's on your mind? I'm here to help.",
                "Tell me more about that. I'm listening."
            ]
        }
        
        import random
        emotion_responses = fallbacks.get(emotion_detected, fallbacks["neutral"])
        return random.choice(emotion_responses)

def test_generator():
    """Test the generator"""
    print("Testing ML Response Generator...")
    
    gen = MLResponseGenerator()
    
    test_cases = [
        ("I'm feeling really sad and alone", "sadness"),
        ("I'm so anxious about my future", "anxiety"),
        ("Everything is so frustrating right now", "anger"),
        ("Just checking in, how are things?", "neutral"),
    ]
    
    for message, emotion in test_cases:
        response = gen.generate_response(message, emotion)
        print(f"\n[{emotion.upper()}]")
        print(f"User: {message}")
        print(f"Bot: {response}")
    
    print("\n✅ Generator test complete")

if __name__ == "__main__":
    test_generator()
