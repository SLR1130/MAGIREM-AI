"""
Response formatter - adds light formatting and emotion-aware emoji to AI responses
WITHOUT making a second LLM call. This is lightweight post-processing only.
"""

import re


def is_crisis_or_serious(emotion: str, message: str) -> bool:
    """
    Determine if this is a crisis/serious situation where emoji would be inappropriate.
    
    Args:
        emotion: Detected emotion (e.g. 'sad', 'anxious', 'crisis')
        message: Original user message for additional context
    
    Returns:
        True if this is a serious/crisis situation, False otherwise
    """
    crisis_emotions = ['crisis', 'severe_anxiety', 'severe_depression']
    
    if emotion in crisis_emotions:
        return True
    
    # Check for crisis keywords in message
    crisis_keywords = [
        'suicide', 'kill myself', 'die', 'end it all',
        'harm myself', 'hurt myself', 'cut myself',
        'overdose', 'pills', 'hospital', 'emergency'
    ]
    
    message_lower = message.lower()
    for keyword in crisis_keywords:
        if keyword in message_lower:
            return True
    
    return False


def _explicit_emotion_conflict(emotion: str, message: str) -> bool:
    """Avoid decorating a response from a contradictory classifier label.

    This is a presentation guard only. It does not rewrite or persist emotion
    values, and it deliberately leaves mixed/crisis wording undecided.
    """
    text = str(message or '').lower()
    positive = bool(re.search(
        r"\b(?:i'm|i am|i feel|i've been feeling)\s+"
        r"(?:really\s+|so\s+|very\s+)?(?:happy|glad|excited|proud|joyful|thrilled|great|good)\b",
        text,
    )) or bool(re.search(r"\b(?:masaya|masayang-masaya)\b", text))
    positive_negated = bool(re.search(
        r"\b(?:not|never)\s+(?:really\s+)?(?:happy|glad|excited|proud|joyful|great|good)\b"
        r"|\bhindi\s+(?:ako\s+)?masaya\b",
        text,
    ))
    concern = bool(re.search(
        r"\b(?:i'm|i am|i feel|i've been feeling)\s+"
        r"(?:really\s+|so\s+|very\s+|completely\s+|deeply\s+)?"
        r"(?:sad|stressed|overwhelmed|worried|anxious|angry|frustrated|grieving|burned\s*out)\b"
        r"|\b(?:nai-stress|na-stress|malungkot|nahihirapan|pagod na pagod)\b",
        text,
    ))
    if positive_negated:
        positive = False
        concern = True
    if positive and concern:
        return True
    normalized = str(emotion or '').lower()
    return (positive and normalized in {'sad', 'sadness', 'grief', 'anxiety', 'anxious', 'anger', 'angry'}) \
        or (concern and normalized in {'happy', 'happiness', 'joy', 'hopeful'})

def should_add_emoji(emotion: str, confidence: float, response: str, 
                    message: str) -> bool:
    """
    Determine if an emoji is appropriate for this response.
    
    Rules:
    - Confidence must be > 0.5 (reasonably confident)
    - Not during crisis/serious situations
    - Not if response already contains emoji
    - Only for certain emotions
    
    Args:
        emotion: Detected emotion
        confidence: Confidence score (0-1)
        response: The AI response text
        message: Original user message
    
    Returns:
        True if emoji should be added, False otherwise
    """
    # Minimum confidence threshold
    if confidence < 0.5:
        return False

    # An emotion label alone is not a reason to decorate an ordinary factual or
    # practical answer. Require explicit emotional wording from the student so
    # budgets, identity questions, lists, and other routine requests stay clean.
    message_lower = str(message or '').lower()
    if not re.search(
        r"\b(?:i(?:'m| am| feel| felt| was| have been)|feeling|masaya|malungkot|"
        r"nahihirapan|pagod|stressed|anxious|worried|excited|happy|sad|proud)\b",
        message_lower,
    ):
        return False

    # Do not add an emotion emoji when the student's explicit wording conflicts
    # with the model label; the label remains available as AI-estimated metadata.
    if _explicit_emotion_conflict(emotion, message):
        return False
    
    # Don't emoji during crisis
    if is_crisis_or_serious(emotion, message):
        return False
    
    # Don't add emoji if response already has one
    emoji_pattern = r'[\U0001F600-\U0001F64F]|[\U0001F300-\U0001F5FF]|[\U0001F680-\U0001F6FF]|[\u263C-\u2764]|[\u2600-\u27BF]'
    if re.search(emoji_pattern, response):
        return False
    
    # Only add emoji for certain emotions where it makes sense
    emoji_friendly_emotions = ['happy', 'joy', 'happiness', 'sad', 'sadness', 
                               'anxious', 'anxiety', 'stressed', 'stress', 'hopeful']
    
    return emotion.lower() in emoji_friendly_emotions


def get_emoji_for_emotion(emotion: str) -> str:
    """
    Get a single, subtle emoji for the detected emotion.
    These are suggestions - one emoji, max.
    
    Args:
        emotion: Detected emotion
    
    Returns:
        Single emoji character or empty string
    """
    emoji_map = {
        # Positive emotions
        'happy': '\U0001F60A',
        'joy': '\u2728',
        'happiness': '\U0001F60A',
        'hopeful': '\u2728',
        'content': '\U0001F642',
        
        # Negative emotions  
        'sad': '\U0001FAC2',
        'sadness': '\U0001FAC2',
        'lonely': '\U0001FAC2',
        'heartbreak': '\U0001F494',
        
        # Anxious/stressed
        'anxious': '\U0001F33F',
        'anxiety': '\U0001F33F',
        'stressed': '\U0001F331',
        'stress': '\U0001F331',
        'nervous': '\U0001F33F',
        'worried': '\U0001F33F',
        
        # Neutral - no emoji
        'neutral': '',
        'confused': '',
        'thinking': '',
        'curious': '',
    }
    
    emotion_lower = emotion.lower()
    
    # Direct match
    if emotion_lower in emoji_map:
        return emoji_map[emotion_lower]
    
    # Partial match (check if emotion contains key word)
    for key, emoji in emoji_map.items():
        if key in emotion_lower:
            return emoji
    
    return ''


def format_for_display(response: str, emotion: str, confidence: float, 
                      message: str) -> str:
    """
    Format response for display: preserve paragraphs, add light emoji if appropriate.
    
    This is LIGHTWEIGHT post-processing:
    - Preserves existing paragraph structure (newlines created by model)
    - Optionally adds ONE subtle emoji at end if emotion-appropriate
    - Does NOT modify the content, only formatting
    
    Args:
        response: The AI response text
        emotion: Detected emotion
        confidence: Confidence score (0-1)
        message: Original user message
    
    Returns:
        Formatted response ready for display
    """
    
    # Response is already formatted by model
    # (Orca naturally uses newlines for paragraphs)
    formatted = response.strip()
    
    # Add emoji if appropriate
    if should_add_emoji(emotion, confidence, formatted, message):
        emoji = get_emoji_for_emotion(emotion)
        if emoji:
            # Add emoji to the end with appropriate spacing
            formatted = f"{formatted} {emoji}"
    
    return formatted


# Simple test
if __name__ == '__main__':
    test_response = """Hey there! I hear you. Stress with schoolwork is really tough, and it's completely normal to feel overwhelmed sometimes.

Here are a few things that might help:

Start with the assignment that feels most urgent. Sometimes breaking it into smaller tasks makes it less intimidating.

Take short breaks when you need to. Even 5-10 minutes can help reset your mind.

Remember that you don't have to solve everything today. One step at a time is enough."""
    
    formatted = format_for_display(test_response, 'stressed', 0.75, 'im so stressed with school')
    print("Original:")
    print(test_response)
    print("\n" + "="*50 + "\n")
    print("Formatted:")
    print(formatted)
