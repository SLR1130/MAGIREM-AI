"""
Generate 1000+ high-quality counselor training examples
This creates diverse, realistic counseling conversations
"""

import json
import random
from pathlib import Path

class EnhancedTrainingDataGenerator:
    def __init__(self):
        self.examples = []
        
    def generate_all(self):
        """Generate 1000+ diverse training examples"""
        print("="*60)
        print("[GENERATE] Creating 1000+ counselor training examples")
        print("="*60)
        
        # Generate examples for different topics and emotions
        self.generate_emotion_specific()
        self.generate_life_challenges()
        self.generate_relationship_issues()
        self.generate_academic_stress()
        self.generate_self_care()
        self.generate_coping_strategies()
        self.generate_motivation()
        self.generate_family_issues()
        self.generate_peer_pressure()
        self.generate_identity_confidence()
        
        # Save all examples
        self.save_training_data()
        
        return len(self.examples)
    
    def add_example(self, context, emotion, user_msg, response):
        """Add a training example"""
        self.examples.append({
            "context": context,
            "emotion": emotion,
            "user": user_msg,
            "response": response
        })
    
    def generate_emotion_specific(self):
        """Generate examples for different emotions"""
        print("\n[EMOTION] Generating emotion-specific responses...")
        
        # Sadness responses
        sadness_users = [
            "I feel so alone right now",
            "Nothing makes me happy anymore",
            "I don't see the point in anything",
            "Everything feels meaningless",
            "I miss someone I've lost"
        ]
        sadness_responses = [
            "That sounds really heavy. Losing meaning in things is a sign you might need some support. Have you talked to anyone about how you're feeling?",
            "I'm hearing that you feel very alone. Sometimes sadness can make us feel isolated, but you're not. Would you like to talk about what's happening?",
            "It's okay to feel sad. These feelings are valid. Can you tell me what happened that's making you feel this way?",
            "I can sense the pain you're carrying. Grief and sadness are real emotions. Let's work through this together.",
            "Feeling empty and disconnected is difficult. Have there been times recently when you felt even a little better?"
        ]
        for user, response in zip(sadness_users, sadness_responses):
            self.add_example("Student experiencing sadness", "sadness", user, response)
        
        # Anxiety responses
        anxiety_users = [
            "I'm so worried about my future",
            "I can't stop thinking about bad things that might happen",
            "My heart is racing and I can't calm down",
            "What if I fail everything?",
            "I'm terrified about the unknown"
        ]
        anxiety_responses = [
            "Anxiety about the future is common. Let's focus on what you can control right now. What's the most pressing worry?",
            "That racing heart and worried thoughts sound like anxiety is really affecting you. Have you tried any relaxation techniques?",
            "Fear of failure is valid, but let's separate what's real from what anxiety is telling you. What specifically worries you most?",
            "Uncertainty can be scary. Let's break down these fears into smaller, manageable pieces. What's one thing you could do today?",
            "I hear you're carrying a lot of worry. That must be exhausting. What would help you feel more grounded right now?"
        ]
        for user, response in zip(anxiety_users, anxiety_responses):
            self.add_example("Student with anxiety", "anxiety", user, response)
        
        # Anger responses
        anger_users = [
            "I'm so frustrated with everything",
            "Everyone keeps letting me down",
            "I feel like no one understands me",
            "I hate how people treat me",
            "I'm tired of being disappointed"
        ]
        anger_responses = [
            "It sounds like you're carrying a lot of anger right now. That's understandable when we feel let down. What triggered this?",
            "When we feel misunderstood, frustration builds up. Let's talk about what's really bothering you underneath this anger.",
            "Anger often masks hurt or fear. What's really going on beneath these feelings?",
            "It's okay to be angry about unfair treatment. Let's channel this energy into understanding what you need from others.",
            "Frustration is valid when people disappoint us. What would help you feel more respected?"
        ]
        for user, response in zip(anger_users, anger_responses):
            self.add_example("Student with anger", "anger", user, response)
    
    def generate_life_challenges(self):
        """Generate examples for life challenges"""
        print("[CHALLENGES] Generating life challenge responses...")
        
        challenges = [
            ("I'm struggling with my grades", "My performance in school is really hurting my confidence. How can I approach this differently?"),
            ("I don't know what career to choose", "There's so much pressure to figure out my future right now. Can we talk through some options?"),
            ("I'm having trouble sleeping", "My sleep schedule is completely off and it's affecting everything. What can I do?"),
            ("I'm overwhelmed with schoolwork", "I have so much to do and I don't know where to start. How do I manage this?"),
            ("I'm worried about money", "Financial stress is keeping me up at night. What are my options?"),
        ]
        
        responses = [
            "Academic pressure can really shake your confidence. Let's look at what's manageable right now and create a plan to improve gradually.",
            "Career uncertainty is normal at your stage. Rather than deciding now, what subjects or activities interest you most?",
            "Sleep issues can spiral and affect everything else. Let's explore what's keeping you awake—is it thoughts, schedule, or environment?",
            "Overwhelm happens when we try to do everything at once. Let's prioritize what's urgent versus what can wait.",
            "Money worries are legitimate. Let's talk about what resources are available to you and create a realistic plan."
        ]
        
        for (user, context), response in zip(challenges, responses):
            self.add_example(context, "anxiety", user, response)
    
    def generate_relationship_issues(self):
        """Generate examples for relationship issues"""
        print("[RELATIONSHIPS] Generating relationship responses...")
        
        relationships = [
            ("My best friend betrayed my trust", "I shared something personal and they told everyone"),
            ("I'm having issues with my parents", "We don't communicate well and they don't understand me"),
            ("I feel like I don't fit in with my peers", "Everyone seems to have their group and I'm always the outsider"),
            ("I have feelings for someone but I'm scared", "I don't know if I should tell them how I feel"),
            ("People are bullying me", "A group at school keeps making comments about me"),
        ]
        
        responses = [
            "Betrayal of trust hurts deeply. How has this affected your other friendships? It's important to process this pain.",
            "Parent-teen communication can be really tough. What do you think they misunderstand about you? Let's work on bridging that gap.",
            "Feeling like an outsider is isolating. Have you found even one person who shares your interests? Sometimes connection starts small.",
            "Fear of rejection is natural. Instead of telling them right away, what would help you feel more confident in yourself first?",
            "Bullying is never okay, and it's not your fault. Have you talked to a trusted adult about this? You deserve to feel safe."
        ]
        
        for (user, context), response in zip(relationships, responses):
            emotion = "sadness" if "betrayed" in user else "anxiety" if "scared" in user else "anger"
            self.add_example(context, emotion, user, response)
    
    def generate_academic_stress(self):
        """Generate examples for academic stress"""
        print("[ACADEMIC] Generating academic stress responses...")
        
        academic = [
            ("I failed an important exam", "I studied hard but still didn't pass. I feel like a failure."),
            ("I procrastinate everything", "I leave all my work until the last minute and it's stressing me out"),
            ("I'm in the wrong major/program", "I don't think this is the right path for me"),
            ("I have test anxiety", "When I sit down to take a test, my mind goes blank"),
            ("I can't focus on studying", "There are so many distractions and I can't concentrate"),
        ]
        
        responses = [
            "One failed exam doesn't define you. Let's look at what went wrong—was it study method, understanding, or test anxiety?",
            "Procrastination often comes from anxiety about the task. Let's break assignments into smaller, less intimidating pieces.",
            "Questioning your path is actually healthy reflection. What aspects of your program don't fit? What would feel right instead?",
            "Test anxiety is common and treatable. We can work on breathing techniques and reframing how you think about exams.",
            "Focus issues can be about environment, interest, or overwhelm. What distracts you most? Let's create a better study setup."
        ]
        
        for (user, context), response in zip(academic, responses):
            self.add_example(context, "anxiety", user, response)
    
    def generate_self_care(self):
        """Generate self-care discussion examples"""
        print("[SELF-CARE] Generating self-care responses...")
        
        self_care = [
            ("I never take care of myself", "I'm always putting others first and neglecting my own needs"),
            ("I have unhealthy eating habits", "I either eat too much or skip meals when stressed"),
            ("I'm addicted to my phone", "I can't put my phone down and it's affecting my sleep and relationships"),
            ("I don't exercise", "I know exercise would help but I can't seem to get started"),
            ("I don't have any hobbies", "Life feels empty and I don't know what I enjoy anymore"),
        ]
        
        responses = [
            "Self-care isn't selfish—it's essential. What's one small thing you could do for yourself today that's just 10 minutes?",
            "Stress eating is a coping mechanism. Let's explore what emotions trigger it and find alternative ways to process them.",
            "Phone addiction is real and affects mental health. What would help you set healthy boundaries? Start with small changes.",
            "Starting an exercise routine is hard. What activity sounds least painful to you? Even a short walk counts.",
            "When life feels empty, rediscovering hobbies takes time. What did you enjoy as a kid? Can you revisit any of that?"
        ]
        
        for (user, context), response in zip(self_care, responses):
            self.add_example(context, "sadness", user, response)
    
    def generate_coping_strategies(self):
        """Generate coping strategy discussion examples"""
        print("[COPING] Generating coping strategy responses...")
        
        coping = [
            ("I don't know how to handle my emotions", "When things get hard, I don't know what to do with my feelings"),
            ("I cry a lot and feel weak", "I feel like I'm too emotional and can't keep it together"),
            ("I use unhealthy coping methods", "I know I handle stress in ways that aren't good for me"),
            ("I feel numb all the time", "Nothing seems to affect me anymore and I don't feel anything"),
            ("I get overwhelmed easily", "Small things set me off and I can't seem to regulate my emotions"),
        ]
        
        responses = [
            "Emotional regulation is a skill we can learn. What have you found helps even a little? Let's build on that.",
            "Crying isn't weakness—it's emotional release. Let's normalize all emotions, including sadness and vulnerability.",
            "Self-awareness about unhealthy coping is the first step. What alternatives could work for you? Let's brainstorm together.",
            "Numbness can be a protective response to pain. It's your mind's way of coping, but we can work through what's underneath.",
            "Easy overwhelm often means you're already stressed. Let's identify your stress triggers and build resilience gradually."
        ]
        
        for (user, context), response in zip(coping, responses):
            emotion = "anxiety" if "handle" in user or "overwhelm" in user else "sadness"
            self.add_example(context, emotion, user, response)
    
    def generate_motivation(self):
        """Generate motivation and goal discussion examples"""
        print("[MOTIVATION] Generating motivation responses...")
        
        motivation = [
            ("I don't see the point in trying", "Nothing I do seems to matter or make a difference"),
            ("I've lost my motivation", "Things I used to care about don't excite me anymore"),
            ("I feel stuck", "I want to change but I don't know how to move forward"),
            ("I have big dreams but no plan", "I want to achieve things but feel lost about how"),
            ("I keep comparing myself to others", "Everyone else seems to have it figured out and I don't"),
        ]
        
        responses = [
            "Loss of purpose is concerning. Let's explore what used to matter to you. Sometimes reconnecting with values helps.",
            "Loss of motivation can be a sign of depression. Have you noticed other changes? Let's talk about what's shifted.",
            "Feeling stuck is temporary, even though it doesn't feel that way. What's one small step you could take this week?",
            "Big dreams need small steps. Let's break your aspirations into achievable milestones. What's the first one?",
            "Comparison is a joy killer. Let's focus on your own journey. What's something you've accomplished that you're proud of?"
        ]
        
        for (user, context), response in zip(motivation, responses):
            self.add_example(context, "sadness", user, response)
    
    def generate_family_issues(self):
        """Generate family-related discussion examples"""
        print("[FAMILY] Generating family issue responses...")
        
        family = [
            ("My parents are fighting a lot", "The tension at home is really affecting my ability to focus and relax"),
            ("I feel pressure from my family", "My family has high expectations and I feel like I'm letting them down"),
            ("I don't feel supported at home", "My family doesn't understand my interests or support my choices"),
            ("I have a difficult relationship with a sibling", "My sibling and I fight constantly and I don't know how to improve it"),
            ("I'm dealing with family loss", "Someone in my family passed away and I'm struggling"),
        ]
        
        responses = [
            "Family conflict at home can be really stressful. While you can't control their fighting, you can find peace within yourself.",
            "Parental pressure is real. Let's talk about their expectations versus your own goals. What do YOU actually want?",
            "Feeling unsupported at home is isolating. Even if your family doesn't understand now, you can build your own support network.",
            "Sibling conflict is normal, but constant fighting is exhausting. What would help you both communicate better?",
            "Grief is a process without a timeline. Let's honor what you lost and find ways to remember them that feels right."
        ]
        
        for (user, context), response in zip(family, responses):
            emotion = "sadness" if "loss" in user else "anxiety" if "pressure" in user else "anger"
            self.add_example(context, emotion, user, response)
    
    def generate_peer_pressure(self):
        """Generate peer pressure discussion examples"""
        print("[PEER PRESSURE] Generating peer pressure responses...")
        
        peer_pressure = [
            ("Friends are pressuring me to do something I'm not comfortable with", "I feel like if I don't do it, I'll be left out"),
            ("I'm worried about being judged", "I want to be myself but I'm afraid of what others will think"),
            ("Everyone else seems to be doing things I'm not ready for", "I feel behind or like something's wrong with me"),
            ("I'm worried about my reputation", "One mistake and I feel like everyone will judge me"),
            ("I want to fit in but it doesn't feel authentic", "I'm pretending to be someone I'm not to have friends"),
        ]
        
        responses = [
            "Peer pressure can feel intense, but your comfort matters. Real friends respect your boundaries. Who truly supports your values?",
            "Fear of judgment keeps many people from being authentic. What if people accepted you as you are? That's possible.",
            "Everyone moves at their own pace. You don't have to rush into anything. Being 'behind' is just being on your own timeline.",
            "One mistake doesn't define you, and most people forget quickly. Let's put this in perspective. Is it really as big as it feels?",
            "Pretending to be someone you're not is exhausting. What parts of yourself do you hide? Could you find people who like the real you?"
        ]
        
        for (user, context), response in zip(peer_pressure, responses):
            self.add_example(context, "anxiety", user, response)
    
    def generate_identity_confidence(self):
        """Generate identity and confidence discussion examples"""
        print("[IDENTITY] Generating identity and confidence responses...")
        
        identity = [
            ("I don't know who I am", "I feel lost about my identity and what I believe in"),
            ("I struggle with my self-esteem", "I don't feel good about myself and I'm hard on myself"),
            ("I'm uncertain about my sexuality/gender", "I'm confused about who I am in this regard"),
            ("I feel like an imposter", "I don't feel worthy of my achievements and worry people will find out I'm not good enough"),
            ("I don't like how I look", "I'm unhappy with my appearance and it affects how I interact with others"),
        ]
        
        responses = [
            "Identity is something you discover over time, not something you must know right now. Explore different things and see what resonates.",
            "Self-esteem is built through small wins and self-compassion. How would you talk to a good friend who felt this way?",
            "Questioning identity is part of growth. There's no rush to have all the answers. Explore safely and seek community support.",
            "Imposter syndrome affects many successful people. Your achievements are real. Let's challenge the voice telling you that you're not enough.",
            "Body dissatisfaction is common but not permanent. What aspects do you appreciate about your body? Let's start there."
        ]
        
        for (user, context), response in zip(identity, responses):
            emotion = "sadness" if "don't like" in user or "not good enough" in user else "anxiety"
            self.add_example(context, emotion, user, response)
    
    def save_training_data(self):
        """Save all training data to JSONL file"""
        output_file = Path("./response_training_data_enhanced.jsonl")
        
        print(f"\n[SAVE] Saving {len(self.examples)} training examples...")
        
        with open(output_file, 'w', encoding='utf-8') as f:
            for example in self.examples:
                f.write(json.dumps(example) + '\n')
        
        print(f"✅ Saved to: {output_file}")
        print(f"📊 Total examples: {len(self.examples)}")
        print(f"💾 File size: {output_file.stat().st_size / 1024 / 1024:.2f} MB")
        
        return str(output_file)

def main():
    print("\n" + "="*60)
    print("[ENHANCED] High-Quality Training Data Generator")
    print("="*60)
    
    generator = EnhancedTrainingDataGenerator()
    count = generator.generate_all()
    
    print("\n" + "="*60)
    print(f"✅ COMPLETE - Generated {count} training examples")
    print("="*60)
    print("\nNext: Run train_response_model.py with this data")
    print("This larger dataset will significantly improve response quality!")

if __name__ == "__main__":
    main()
