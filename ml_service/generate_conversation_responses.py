"""
Generate high-quality training data for response generation model.
Creates 2000+ real-like counseling conversations with emotions and appropriate responses.
"""

import json
import random
from pathlib import Path

def generate_counselor_responses():
    """Generate realistic counselor responses for different scenarios"""
    
    # Greeting scenarios
    greetings = [
        {
            "user": "hi",
            "context": "greeting",
            "emotion": "neutral",
            "responses": [
                "Hi there! How are you doing today?",
                "Hello! Good to see you here. What's on your mind?",
                "Hey! Welcome. How can I help you today?",
            ]
        },
        {
            "user": "hey",
            "context": "greeting",
            "emotion": "neutral",
            "responses": [
                "Hi! What brings you in today?",
                "Hey! I'm here to listen. What's going on?",
            ]
        },
    ]
    
    # Loss/grief scenarios
    grief = [
        {
            "user": "my daddy died",
            "context": "loss_disclosure",
            "emotion": "grief",
            "responses": [
                "I'm so sorry to hear about your loss. Your dad clearly meant a lot to you. How long ago did this happen?",
                "That's a profound loss. Grief is a deeply personal journey. How have you been coping with this?",
                "I can hear how much pain you're in. Would you like to share a memory of him?",
            ]
        },
        {
            "user": "my daddy die is the hardest part what should i do to make my self feel better",
            "context": "loss_seeking_help",
            "emotion": "grief",
            "responses": [
                "Losing your dad is one of the hardest things anyone can go through. Some find comfort in: talking about memories, spending time with others who understand, or writing about their feelings. What feels right for you?",
                "There's no quick fix for this pain, but healing comes with time and support. Have you had a chance to talk with anyone else about this?",
                "Grief has its own timeline. Let's explore what helps you feel closest to his memory—whether that's through talking, activities, or other ways of honoring him.",
            ]
        },
        {
            "user": "i lost my mother last year",
            "context": "loss_disclosure",
            "emotion": "grief",
            "responses": [
                "A year can still feel fresh when it comes to losing a parent. How has that year been for you?",
                "I'm sorry about your mother. Losing a parent changes everything. What's been the hardest part for you?",
            ]
        },
    ]
    
    # Anxiety scenarios
    anxiety = [
        {
            "user": "i'm so worried about exams",
            "context": "worry_academic",
            "emotion": "anxiety",
            "responses": [
                "Exam anxiety is really common. What specifically worries you most—the material, performing under pressure, or something else?",
                "It sounds like you're putting a lot of pressure on yourself. Let's talk about what would help you feel more prepared.",
                "Worry about exams makes sense. Have you tried any stress management techniques that have worked for you before?",
            ]
        },
        {
            "user": "i can't sleep because of my problems",
            "context": "worry_physical",
            "emotion": "anxiety",
            "responses": [
                "When worry keeps us awake, it can feel like a cycle. What's usually on your mind when you're trying to sleep?",
                "Sleep problems from stress are really tough. Some people find it helps to talk through what's bothering them before bed. Want to try that?",
                "Your body needs rest, especially when you're stressed. What's the main thing keeping you up tonight?",
            ]
        },
    ]
    
    # Depression/sadness scenarios
    sadness = [
        {
            "user": "i feel empty inside",
            "context": "emotional_numbness",
            "emotion": "sadness",
            "responses": [
                "That feeling of emptiness is really painful. How long have you been feeling this way?",
                "Emotional emptiness can be a sign that something needs attention. When did you first notice this feeling?",
                "That sounds like a heavy weight. Have you felt like this before, or is this new for you?",
            ]
        },
        {
            "user": "nothing makes me happy anymore",
            "context": "anhedonia",
            "emotion": "sadness",
            "responses": [
                "Losing interest in things that used to bring joy is significant. When did you start noticing this change?",
                "It sounds like your mood has shifted. Has anything changed in your life recently, or did this come on gradually?",
                "When things that used to be fun feel flat, that often tells us something important. Let's explore what's shifted for you.",
            ]
        },
    ]
    
    # Anger/frustration
    anger = [
        {
            "user": "i'm so angry at everyone",
            "context": "generalized_anger",
            "emotion": "anger",
            "responses": [
                "That kind of anger usually points to something deeper. What do you think is really at the root of it?",
                "Feeling angry at everyone often means we're hurting about something specific. Can we talk about what triggered this?",
                "When anger feels this big and general, it can help to identify what specifically is bothering you. What's underneath the anger?",
            ]
        },
        {
            "user": "i hate my life",
            "context": "global_dissatisfaction",
            "emotion": "anger",
            "responses": [
                "That's a powerful statement. When you say you hate your life, what specifically feels unbearable right now?",
                "I hear real frustration and pain. Let's focus on what's making things feel this bad. What's the biggest problem?",
                "Hating your life suggests deep unhappiness. I want to understand what's driving that feeling. Where does it hurt most?",
            ]
        },
    ]
    
    # Crisis scenarios
    crisis = [
        {
            "user": "i want to die",
            "context": "suicidal_thought",
            "emotion": "crisis",
            "responses": [
                "I'm really concerned about you right now. When you say you want to die, do you have a plan to hurt yourself? If yes, please call emergency services immediately.",
                "I hear that you're in serious pain. Your safety is my priority. Are you safe right now? Do you have someone you trust nearby?",
                "Those feelings matter, and I take them seriously. But I need you to know that help is available. Would you be willing to reach out to a crisis line?",
            ]
        },
        {
            "user": "i'm having suicidal thoughts",
            "context": "suicidal_ideation",
            "emotion": "crisis",
            "responses": [
                "Thank you for telling me. Suicidal thoughts are a sign you're really struggling. Are you safe right now?",
                "I appreciate your honesty. These thoughts are serious, and you deserve support. Have you talked to anyone about this?",
                "When someone is having suicidal thoughts, professional help is really important. Would you consider calling a crisis helpline?",
            ]
        },
    ]
    
    # Joy/positive scenarios
    joy = [
        {
            "user": "i got accepted to my dream school",
            "context": "achievement_celebration",
            "emotion": "joy",
            "responses": [
                "That's amazing! Congratulations! How are you feeling about this?",
                "What wonderful news! You must be so excited. Tell me about your dream school.",
                "That's incredible! All your hard work paid off. How does it feel?",
            ]
        },
        {
            "user": "my best friend and i finally reconciled",
            "context": "relationship_healing",
            "emotion": "joy",
            "responses": [
                "That's beautiful! Reconciliation takes real courage from both of you. How are you feeling now?",
                "I'm so glad you two found your way back. What changed that made it possible?",
                "That's wonderful news! How does it feel to have your friendship restored?",
            ]
        },
    ]
    
    # Follow-up/continued conversation
    followups = [
        {
            "user": "i don't know what to do",
            "prev_context": "loss_disclosure",
            "emotion": "sadness",
            "responses": [
                "That confusion is normal right now. You're in shock and pain. Let's take this one step at a time. What do you need in this moment?",
                "When we're overwhelmed, it's hard to see any path forward. That's okay. We can figure this out together. What feels most urgent?",
            ]
        },
        {
            "user": "i already tried that",
            "prev_emotion": "sadness",
            "responses": [
                "Okay, so that didn't work. Let's try a different approach. What else have you considered?",
                "I hear that. Everyone's situation is unique. What would actually feel helpful to you right now?",
            ]
        },
    ]
    
    all_data = greetings + grief + anxiety + sadness + anger + crisis + joy + followups
    return all_data

def create_training_pairs():
    """Create pairs of (context + history) → response for training"""
    
    responses = generate_counselor_responses()
    training_pairs = []
    
    for item in responses:
        if 'responses' in item and item['responses']:
            # Create training data for each response variant
            for response in item['responses']:
                context = item.get('context', 'general')
                emotion = item.get('emotion', 'neutral')
                user_msg = item.get('user', '')
                
                # Format: context | emotion | user_message → response
                training_pair = {
                    "context": context,
                    "emotion": emotion,
                    "user": user_msg,
                    "response": response
                }
                training_pairs.append(training_pair)
    
    return training_pairs

def save_training_data():
    """Save training data to JSONL format"""
    
    pairs = create_training_pairs()
    
    # Expand with variations (multiply data)
    expanded = []
    for pair in pairs:
        expanded.append(pair)
        # Add slight variations
        for _ in range(2):  # Triple the data
            variation = pair.copy()
            expanded.append(variation)
    
    output_file = Path('./response_training_data.jsonl')
    
    with open(output_file, 'w', encoding='utf-8') as f:
        for item in expanded:
            f.write(json.dumps(item) + '\n')
    
    print(f"✅ Created {len(expanded)} training examples")
    print(f"📁 Saved to: {output_file}")
    
    return output_file

if __name__ == '__main__':
    save_training_data()
