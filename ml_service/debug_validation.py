#!/usr/bin/env python3
"""Debug why validation is rejecting Ollama responses"""

import re
import sys

# The response from Ollama that the crisis greeting case gets
crisis_response = """Hi there! I'm doing pretty well, thank you for asking. It's lovely to chat with you. 😊 How about you? How's your day going so far? Don't feel pressured to tell me everything if you don't want to, just whatever feels comfortable for you."""

sadness_response = """Oh, my dearest friend, hearing that breaks my heart a little. It sounds like you're carrying a heavy weight, and I want you to know that it's okay to feel down. It's okay to not be okay. There's a profound sadness in acknowledging that, and I want to simply hold that space with you for a moment.

It takes courage to even say those words, and I want to commend you for sharing this with me. I'm here to listen, truly listen, without judgment. Just breathe.

Would you like to tell me a little bit about what's been making you feel this way? You don't have to go into detail if you don't want to, but even just a small piece of it could help. Or, if you'd rather just talk about something completely different, that's perfectly alright too.

I'm sending you all the warmth and gentle support I can muster. You are not alone in this. 💖"""

def validate_response(response: str, emotion: str, confidence: float, message: str) -> tuple:
    """Copy of validation logic from hybrid_response_engine.py"""
    
    # Check 1: Empty response
    if not response or len(response.strip()) == 0:
        return False, "Empty response"
    
    # Check 2: Response is way too short (less than 3 words)
    word_count = len(response.split())
    if word_count < 3:
        return False, f"Too short ({word_count} words)"
    
    # FIX (PHASE 1): DETECT INTERNAL INSTRUCTION LEAKAGE
    response_lower = response.lower()
    
    # Patterns that indicate INTERNAL INSTRUCTION TEXT (should be rejected)
    instruction_leakage_patterns = [
        r'\bvalidate and support\b',
        r'\[routing\]',
        r'\[/routing\]',
        r'\[emotional_statement\]',
        r'\[question\]',
        r'\baacknowledge their feelings\b.*\bfirst\b',
        r'\backnowledge their feelings\b.*\bfirst\b',
        r'\bshow you understand\b',
        r'\bthen offer\b.*\b(support|suggestions|guidance|help)\b',
        r'^1\.\s*(acknowledge|answer|provide|return|respond to|validate)\b',
        r'^1\.\s*[A-Z].*\n2\.\s*[A-Z].*\n3\.\s*[A-Z]',
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
    
    # Check 4: RELAXED Emotional tone mismatch - only reject SEVERE mismatches
    if confidence > 0.90:  # Only check for VERY HIGH confidence
        emotion_lower = emotion.lower()
        
        # Only reject if response is HOSTILE to the emotion
        if emotion_lower in ["happy", "joy", "happiness", "excited"]:
            if response_lower.count("sorry") > 2 or "that's terrible" in response_lower:
                return False, "Hostile tone for positive emotion"
        
        elif emotion_lower in ["sad", "sadness", "depressed", "grief"]:
            dismiss_phrases = ["just relax", "cheer up", "it's not that bad"]
            if any(phrase in response_lower for phrase in dismiss_phrases):
                return False, "Dismissive tone for sad emotion"
    
    return True, "Valid (response passed validation)"

# Test the validation
print("=" * 70)
print("TEST: Validating Ollama responses")
print("=" * 70)

print("\n1. Crisis greeting response (neutral emotion)")
is_valid, reason = validate_response(crisis_response, "neutral", 0.7, "Hi")
print(f"   Valid: {is_valid}")
print(f"   Reason: {reason}")
print(f"   Length: {len(crisis_response)} chars")
print(f"   Word count: {len(crisis_response.split())} words")

print("\n2. Sadness response (sadness emotion, high confidence)")
is_valid, reason = validate_response(sadness_response, "sadness", 0.95, "I'm feeling down")
print(f"   Valid: {is_valid}")
print(f"   Reason: {reason}")
print(f"   Length: {len(sadness_response)} chars")
print(f"   Word count: {len(sadness_response.split())} words")

# Test what would cause rejection
print("\n3. Test different confidence levels for sadness")
for conf in [0.5, 0.7, 0.9, 0.95]:
    is_valid, reason = validate_response(sadness_response, "sadness", conf, "I'm feeling down")
    print(f"   Confidence {conf}: Valid={is_valid}, Reason={reason}")

print("\n" + "=" * 70)
print("CONCLUSION")
print("=" * 70)
print("Validation is PASSING these responses - they should be used!")
print("If they're still being rejected, the issue is elsewhere in the Flask code.")
