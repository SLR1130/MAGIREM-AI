"""
Advice System - Provides practical suggestions and help
Makes AI act like ChatGPT - understands situation and offers solutions
Now with WEB-BASED ADVICE from free sources!
"""

import random
try:
    from .web_advice_scraper import get_web_advice
except ImportError:
    try:
        from web_advice_scraper import get_web_advice
    except ImportError:
        get_web_advice = None

class AdviceSystem:
    def __init__(self):
        # Advice knowledge base organized by situation
        self.advice_database = {
            'exam_anxiety': {
                # ONLY future exams - NOT past failures!
                'triggers': ['exam today', 'test today', 'quiz today', 'exam tomorrow', 'test tomorrow', 'quiz tomorrow', 'exam next week', 'test next week', 'didnt review', "didn't review", 'havent studied', "haven't studied", 'not prepared', 'scared to fail', 'exam coming up', 'test coming up'],
                'advice': [
                    {
                        'situation': 'Exam today, no review',
                        'suggestions': [
                            "Since the exam is today and you haven't reviewed, here's what might help:",
                            "• Quick review: Focus on main concepts, formulas, or key terms (even 15 minutes helps)",
                            "• Breathing exercises: Try 4-7-8 breathing (inhale 4 sec, hold 7 sec, exhale 8 sec) to calm nerves",
                            "• Positive self-talk: Replace 'I'll fail' with 'I'll do my best with what I know'",
                            "• Arrive early: Get settled, avoid last-minute cramming that increases anxiety",
                            "",
                            "Would you like me to walk you through a quick calming technique?"
                        ]
                    },
                    {
                        'situation': 'General exam stress',
                        'suggestions': [
                            "Exam stress is tough. Here are some strategies that help:",
                            "• Break study time into 25-minute chunks (Pomodoro technique)",
                            "• Practice past exams or sample questions",
                            "• Study with a friend - teaching others helps you learn",
                            "• Get enough sleep the night before (cramming all night backfires)",
                            "• Eat a good breakfast - your brain needs fuel",
                            "",
                            "What subject is the exam on? I might have specific tips!"
                        ]
                    }
                ]
            },
            'academic_failure': {
                'triggers': ['failed', 'failing', 'bad grade', 'flunked', 'didnt pass', "didn't pass"],
                'advice': [
                    {
                        'situation': 'Failed a test/exam',
                        'suggestions': [
                            "Failing a test doesn't mean you're a failure. Here's what you can do:",
                            "• Talk to your professor: Ask about extra credit, retakes, or tutoring resources",
                            "• Identify what went wrong: Was it the material, time management, or test anxiety?",
                            "• Get a tutor: Many schools offer free tutoring services",
                            "• Form a study group: Learning with others can make difficult topics clearer",
                            "• Adjust your study method: Try different techniques (flashcards, practice problems, teaching others)",
                            "",
                            "One bad grade doesn't define you. What subject was it? Let's figure out a plan."
                        ]
                    }
                ]
            },
            'anxiety_general': {
                'triggers': ['anxious', 'panic', 'cant breathe', "can't breathe", 'racing heart', 'overwhelmed', 'stressed'],
                'advice': [
                    {
                        'situation': 'Panic attack or high anxiety',
                        'suggestions': [
                            "When anxiety feels overwhelming, try these grounding techniques:",
                            "• 5-4-3-2-1 method: Name 5 things you see, 4 you can touch, 3 you hear, 2 you smell, 1 you taste",
                            "• Box breathing: Breathe in for 4, hold for 4, out for 4, hold for 4 (repeat)",
                            "• Cold water: Splash your face or hold ice - it activates your calming response",
                            "• Move your body: Walk, stretch, or do jumping jacks to release tension",
                            "• Call someone: Talking to a friend or family member can help",
                            "",
                            "If panic attacks are frequent, please consider talking to a counselor. They can teach you more coping strategies.",
                            "",
                            "Would you like me to guide you through a breathing exercise right now?"
                        ]
                    }
                ]
            },
            'depression_symptoms': {
                'triggers': ['depressed', 'empty', 'numb', 'no motivation', 'lost interest', 'dont care', "don't care", 'nothing matters'],
                'advice': [
                    {
                        'situation': 'Depression symptoms',
                        'suggestions': [
                            "What you're describing sounds like depression, and I want you to know it's treatable. Here's what can help:",
                            "• Talk to a counselor or therapist: They can provide professional support and treatment options",
                            "• Reach out to someone you trust: Don't isolate yourself - connection helps",
                            "• Small steps: Even tiny actions (shower, walk outside, eat a meal) count as wins",
                            "• Routine: Try to maintain a basic schedule - it provides structure when everything feels chaotic",
                            "• Be patient with yourself: Healing takes time, and it's okay to not be okay",
                            "",
                            "Depression is serious and you don't have to face it alone. Would you be open to talking to a counselor? I can help you find resources."
                        ]
                    }
                ]
            },
            'relationship_problems': {
                'triggers': ['breakup', 'broke up', 'relationship', 'boyfriend', 'girlfriend', 'fight', 'argument'],
                'advice': [
                    {
                        'situation': 'Breakup/heartbreak',
                        'suggestions': [
                            "Breakups are painful. Here's how to cope:",
                            "• Allow yourself to grieve: It's okay to cry, be sad, and feel hurt",
                            "• No contact (for now): Give yourself space to heal without constant reminders",
                            "• Lean on friends: Spend time with people who care about you",
                            "• Stay busy: Pick up hobbies, exercise, or activities you enjoy",
                            "• Don't rush: Healing takes time - there's no timeline for getting over someone",
                            "• Avoid social media stalking: It only makes it harder to move on",
                            "",
                            "How recent was the breakup? And do you have friends or family supporting you?"
                        ]
                    },
                    {
                        'situation': 'Relationship conflict',
                        'suggestions': [
                            "Relationship conflicts are tough. Here are some communication tips:",
                            "• Use 'I feel' statements: Instead of 'You always...', try 'I feel hurt when...'",
                            "• Listen actively: Try to understand their perspective before responding",
                            "• Take a break if needed: If emotions are high, pause and come back when calmer",
                            "• Focus on the issue, not the person: Attack the problem, not each other",
                            "• Compromise: Healthy relationships require give and take",
                            "",
                            "What's the main issue you're dealing with? Maybe I can offer more specific advice."
                        ]
                    }
                ]
            },
            'grief_loss': {
                'triggers': ['died', 'death', 'dead', 'passed away', 'lost', 'funeral', 'grief', 'lost my', 'my friend died', 'my dog died', 'my cat died', 'lost my bestfriend'],
                'advice': [
                    {
                        'situation': 'Grief and loss',
                        'suggestions': [
                            "I'm so sorry for your loss. Here are some practical steps that can help:",
                            "• Create a daily routine: Even small habits like eating meals or going for walks can provide structure",
                            "• Express your feelings: Write in a journal, draw, or talk to someone you trust about your memories",
                            "• Take care of your body: Try to eat nutritious food, get some fresh air, and rest when you need it",
                            "• Connect with others: Reach out to friends or family - even a simple text can help",
                            "• Honor their memory: Look at photos, share stories, or create a small memorial",
                            "• Be patient with yourself: Grief comes in waves - some days will be harder than others",
                            "• Small wins count: Even getting out of bed or taking a shower is an achievement",
                            "",
                            "What specific thing feels hardest right now? Let's focus on that."
                        ]
                    },
                    {
                        'situation': 'Losing a close friend',
                        'suggestions': [
                            "Losing a best friend is devastating. Here are some ways to cope:",
                            "• Acknowledge the void: It's normal to feel like something essential is missing",
                            "• Reach out to other friends: They might be grieving too and need support",
                            "• Honor the friendship: Write letters to them, create a memory box, or plant something in their honor",
                            "• Give yourself time: The pain of losing a friend can be as intense as losing family",
                            "• Self-care rituals: Do things that made you happy together, but adapted for now",
                            "• Small routines: Try a short walk, mindful breathing, or a comforting meal each day",
                            "",
                            "If you want, I can also share ideas for grief support resources without making it feel overwhelming.",
                            "",
                            "How long were you friends? What made your friendship special?"
                        ]
                    },
                    {
                        'situation': 'Pet loss grief',
                        'suggestions': [
                            "Losing a pet is heartbreaking. Here are some ways to honor them and cope:",
                            "• Create a memorial: Plant a tree, make a photo album, or get a small tattoo in their memory",
                            "• Honor their routine: Keep some of their habits, like feeding other animals or walking at their favorite time",
                            "• Share memories: Tell stories about them with friends and family",
                            "• Consider a new pet: When you're ready - there's no rush, but companionship can help",
                            "• Grief rituals: Write them a letter, have a small ceremony, or scatter ashes in a meaningful place",
                            "• Self-compassion: It's okay to cry, feel angry, or take time off work/school",
                            "• Support groups: Online pet loss communities can connect you with others who understand",
                            "",
                            "What was their name? What did you love most about them?"
                        ]
                    }
                ]
            },
            'emotional_breakdown': {
                'triggers': ['breakdown', 'falling apart', 'cant take it', "can't take it", 'overwhelmed', 'spiraling', 'losing control', 'breaking down', 'falling into pieces', 'cant cope', "can't cope"],
                'advice': [
                    {
                        'situation': 'Emotional breakdown',
                        'suggestions': [
                            "When everything feels like too much, here are immediate steps to help:",
                            "• Ground yourself: Name 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, 1 you can taste",
                            "• Breathe deeply: Try 4-7-8 breathing - inhale for 4 seconds, hold for 7, exhale for 8",
                            "• Move your body: Go for a walk, stretch, or do jumping jacks to release tension",
                            "• Drink water: Sometimes dehydration makes everything feel worse",
                            "• Step away: If possible, take a 10-minute break from what's overwhelming you",
                            "• Call someone safe: Text or call a friend, family member, or hotline if you need immediate support",
                            "• Small actions: Even washing your face or brushing your teeth can create momentum",
                            "",
                            "What's the most overwhelming thing right now? Let's break it down into smaller pieces."
                        ]
                    },
                    {
                        'situation': "Feeling like you're falling apart",
                        'suggestions': [
                            "It's okay to feel like you're falling apart. Here's how to start rebuilding:",
                            "• One thing at a time: Pick the smallest, most urgent need and focus only on that",
                            "• Create safety: Make your immediate environment as comforting as possible",
                            "• Reach out: Tell someone you trust exactly how you're feeling - you don't have to do this alone",
                            "• Basic self-care: Eat something, drink water, get fresh air, rest if you can",
                            "• Limit decisions: Too many choices can be paralyzing - stick to familiar routines",
                            "• Professional help: If this happens frequently, a therapist can teach you coping skills",
                            "• Emergency resources: If you're in crisis, call a hotline or go to emergency services",
                            "",
                            "What triggered this feeling? And what would help you feel a little safer right now?"
                        ]
                    },
                    {
                        'situation': 'Complete overwhelm',
                        'suggestions': [
                            "When you're completely overwhelmed, try this step-by-step approach:",
                            "• Stop and breathe: Put everything down and take 5 slow, deep breaths",
                            "• Write it down: Make a list of everything on your mind - get it out of your head",
                            "• Prioritize ruthlessly: What absolutely must be done today? What can wait?",
                            "• Break it down: Turn big tasks into tiny, 5-minute steps you can actually do",
                            "• Ask for help: Can someone else handle part of this? Delegate if possible",
                            "• Set boundaries: Say no to new things until you feel more stable",
                            "• Self-compassion: You're doing your best in a hard situation",
                            "",
                            "What's the biggest source of overwhelm? Let's tackle that first."
                        ]
                    }
                ]
            },
            'loneliness': {
                'triggers': ['lonely', 'alone', 'no friends', 'isolated', 'nobody cares'],
                'advice': [
                    {
                        'situation': 'Loneliness and isolation',
                        'suggestions': [
                            "Loneliness is really hard. Here are ways to build connections:",
                            "• Join clubs or groups: Find activities you enjoy (sports, gaming, art, music)",
                            "• Volunteer: Helping others is a great way to meet people and feel purposeful",
                            "• Take a class: Workshops or courses create natural opportunities to connect",
                            "• Online communities: Find forums or Discord servers for your interests",
                            "• Say yes to invitations: Even when you don't feel like it, try to show up",
                            "• Start small: Even one good friend is better than many acquaintances",
                            "",
                            "What are some things you enjoy doing? Let's brainstorm ways to connect with others who share those interests."
                        ]
                    }
                ]
            },
            'stress_overwhelm': {
                'triggers': ['overwhelmed', 'too much', 'cant handle', "can't handle", 'drowning', 'stressed out'],
                'advice': [
                    {
                        'situation': 'Feeling overwhelmed',
                        'suggestions': [
                            "When everything feels like too much, try this approach:",
                            "• Brain dump: Write down EVERYTHING on your mind (tasks, worries, deadlines)",
                            "• Prioritize: What's urgent? What's important? What can wait?",
                            "• Break it down: Turn big tasks into tiny, manageable steps",
                            "• Ask for help: Extensions, tutoring, or just someone to listen",
                            "• Say no: You don't have to do everything - it's okay to set boundaries",
                            "• Self-care: Sleep, eat, and take breaks - you can't pour from an empty cup",
                            "",
                            "What's the most urgent thing stressing you out right now? Let's tackle that first."
                        ]
                    }
                ]
            },
            'sleep_problems': {
                'triggers': ['cant sleep', "can't sleep", 'insomnia', 'tired', 'exhausted', 'no sleep'],
                'advice': [
                    {
                        'situation': 'Sleep problems',
                        'suggestions': [
                            "Sleep issues can make everything harder. Here's what helps:",
                            "• Consistent schedule: Go to bed and wake up at the same time daily",
                            "• No screens 1 hour before bed: Blue light disrupts sleep hormones",
                            "• Cool, dark room: Your body sleeps better in cooler temperatures",
                            "• No caffeine after 2pm: It stays in your system for hours",
                            "• Relaxation routine: Reading, meditation, or gentle stretching before bed",
                            "• If you can't sleep after 20 min: Get up, do something calming, then try again",
                            "",
                            "If sleep problems persist, talk to a doctor - there might be an underlying issue."
                        ]
                    }
                ]
            },
            'gaming_loss': {
                'triggers': ['lost in a tournament', 'lost in tournament', 'lost the game', 'lost my rank', 'ranked down', 'deranked', 'lost a match', 'gaming loss', 'esports loss'],
                'advice': [
                    {
                        'situation': 'Lost a gaming tournament or match',
                        'suggestions': [
                            "Losing a match or tournament is frustrating, especially when you care about gaming. Here's how to move forward:",
                            "• Acknowledge the disappointment: It's okay to feel frustrated after a loss",
                            "• Review what happened: Watch replays to see what you could improve (not to criticize yourself)",
                            "• Take a break: Sometimes stepping away helps you see things more clearly",
                            "• Play for fun: Try casual games with friends instead of ranked for a bit",
                            "• Focus on improvement: Each loss teaches you something for next time",
                            "• Remember your wins: Recall times you've succeeded - they'll come again",
                            "",
                            "One loss doesn't define your skill. The best gamers lose matches too."
                        ]
                    }
                ]
            },
            'gaming_addiction': {
                'triggers': ['gaming all day', 'cant stop playing', "can't stop playing", 'addicted to games', 'playing too much', 'skipping classes gaming', 'gaming instead of studying', 'neglecting everything for gaming'],
                'advice': [
                    {
                        'situation': 'Gaming addiction or excessive gaming',
                        'suggestions': [
                            "If gaming is taking over your life, here are steps to help you regain balance:",
                            "• Set specific gaming times: Decide when you'll play (e.g., 2 hours after homework)",
                            "• Use alarms: Set a timer so you know when to stop",
                            "• Find other activities: Sports, hobbies, or time with friends can fulfill you too",
                            "• Tell someone: Let a friend or family member know you're working on this",
                            "• Identify the pull: Why do you play so much? Escape? Achievement? Social? Address the root",
                            "• Start small: Reduce by 30 min each week rather than quitting cold turkey",
                            "• Celebrate non-gaming wins: Notice when you do other things and feel good",
                            "",
                            "Gaming can be fun and healthy - it's about balance. If you're struggling, talking to a counselor can help."
                        ]
                    }
                ]
            },
            'perfectionism': {
                'triggers': ['perfectionist', 'perfect', 'not good enough', 'everything has to be', 'nothing is good enough', 'i need everything to be perfect', 'cant accept failure'],
                'advice': [
                    {
                        'situation': 'Perfectionism and self-criticism',
                        'suggestions': [
                            "Perfectionism can actually hold you back. Here's how to develop a healthier mindset:",
                            "• Done is better than perfect: Finished work with small flaws beats unfinished perfect work",
                            "• Set realistic standards: Aim for \"good enough\" instead of flawless",
                            "• Celebrate effort, not just outcomes: Notice how hard you tried, not just the result",
                            "• Learn from mistakes: They're feedback, not failures",
                            "• Practice self-compassion: Talk to yourself like you'd talk to a friend",
                            "• Notice what you're getting right: 90% of your work is probably great",
                            "• Identify your fears: What are you afraid will happen if it's not perfect?",
                            "",
                            "Perfect doesn't exist. Growth does. Which one matters to you?"
                        ]
                    }
                ]
            },
            'imposter_syndrome': {
                'triggers': ['imposter', 'dont deserve this', "don't deserve this", 'fake', 'fraud', 'lucky to be here', 'not qualified', 'dont belong here', "don't belong here", 'lucky accident'],
                'advice': [
                    {
                        'situation': 'Imposter syndrome',
                        'suggestions': [
                            "That feeling that you don't belong or don't deserve your success? That's imposter syndrome, and you're not alone. Here's what helps:",
                            "• Remember your achievements: You earned this through real work and ability",
                            "• Document your wins: Keep a list of times you succeeded or helped others",
                            "• Everyone feels this way: Even experts doubt themselves sometimes",
                            "• Compare to your past self: You're better now than you were before",
                            "• Share your feelings: Talking to others often reveals they feel the same way",
                            "• Action over feelings: Do the work even if you don't feel ready - confidence follows action",
                            "• Ask for help: Reaching out is strength, not weakness",
                            "",
                            "You belong here. Your success is real, even if your brain is being mean about it."
                        ]
                    }
                ]
            },
            'self_esteem': {
                'triggers': ['worthless', 'useless', 'stupid', 'dumb', 'im a failure', 'hate myself', 'not good enough', 'dont matter', "don't matter", 'nobody likes me', 'not talented'],
                'advice': [
                    {
                        'situation': 'Low self-esteem',
                        'suggestions': [
                            "The negative voice in your head isn't telling the truth. Here's how to challenge it:",
                            "• Notice your self-talk: Are you saying things to yourself you'd never say to a friend?",
                            "• Replace the narrative: When you think \"I'm stupid\", ask \"Would a friend think that? Would that even be true?\"",
                            "• List your strengths: Even small ones - being kind, making people laugh, trying hard",
                            "• Do things you're good at: Small wins build confidence",
                            "• Accept compliments: When someone says something nice, say \"Thank you\" instead of dismissing it",
                            "• Be patient with yourself: Changing how you feel about yourself takes time",
                            "• Talk to someone: A counselor can help you see yourself more clearly",
                            "",
                            "You have value. Not because of what you do or achieve, but because you exist."
                        ]
                    }
                ]
            },
            'social_anxiety': {
                'triggers': ['social anxiety', 'afraid to talk to people', 'scared of socializing', 'avoid people', 'dont like crowds', "don't like crowds", 'anxious around others', 'terrified of judgment'],
                'advice': [
                    {
                        'situation': 'Social anxiety',
                        'suggestions': [
                            "Social anxiety can make the world feel scary. These strategies can help:",
                            "• Start small: Talk to one person instead of joining a group",
                            "• Prepare what to say: Having a simple opening can calm your nerves",
                            "• Remember most people are thinking about themselves: They're not judging you as harshly as you fear",
                            "• Breathing helps: Slow, deep breaths calm your nervous system",
                            "• Gradual exposure: Each small social step makes the next one easier",
                            "• Find your people: Smaller groups or people with shared interests feel less scary",
                            "• Celebrate attempts: You did the hard thing just by trying",
                            "",
                            "Anxiety lies. It tells you the worst will happen, but usually it doesn't. A therapist can teach you specific techniques."
                        ]
                    }
                ]
            },
            'bullying_harassment': {
                'triggers': ['bullied', 'bullying', 'harassed', 'harassment', 'being mocked', 'making fun', 'excluding me', 'people are mean', 'spreading rumors'],
                'advice': [
                    {
                        'situation': 'Bullying or harassment',
                        'suggestions': [
                            "Being bullied or harassed is not your fault, and you don't deserve it. Here's what you can do:",
                            "• Tell a trusted adult: Teacher, counselor, parent, or someone in authority",
                            "• Document what's happening: Save messages, note dates/times of incidents",
                            "• Find your people: Spend time with people who treat you with respect",
                            "• Don't isolate: Loneliness makes bullying feel worse",
                            "• Stand up for yourself: If safe to do so, a clear \"stop\" sometimes helps",
                            "• Remember their words don't define you: Bullies often project their own pain",
                            "• Get professional support: A counselor can help you process the hurt",
                            "",
                            "You don't deserve this treatment. Getting help is brave, not weak."
                        ]
                    }
                ]
            },
            'family_conflict': {
                'triggers': ['fighting with family', 'family fight', 'parents arguing', 'sibling conflict', 'family problem', 'family drama', 'parents dont understand', "parents don't understand", 'family stress'],
                'advice': [
                    {
                        'situation': 'Family conflict',
                        'suggestions': [
                            "Family conflict is painful because family matters so much. Here's how to navigate it:",
                            "• Take a break if needed: Step away when emotions are high",
                            "• Use \"I\" statements: \"I feel hurt when...\" instead of \"You always...\"",
                            "• Listen to understand: Try to see their perspective, even if you disagree",
                            "• Pick your battles: Not everything needs to be argued about",
                            "• Find common ground: There's usually something you both care about",
                            "• Get space if home feels unsafe: Talk to a trusted adult about your options",
                            "• Remember they're human too: Parents and siblings aren't perfect",
                            "",
                            "Family relationships take work, but they're worth it. A family counselor can help bridge gaps."
                        ]
                    }
                ]
            }
        }
    
    def detect_situation(self, user_message, emotion, conversation_history=None):
        """Detect what situation the user is in - prioritizes current message over history"""
        msg_lower = user_message.lower()
        
        # CRITICAL FIX: Check GAMING scenarios FIRST before general "lost" triggers
        # This prevents "lost in a tournament" from being detected as grief_loss
        gaming_keywords = ['tournament', 'game', 'gaming', 'ranked', 'rank', 'match', 'esports', 'competitive']
        if any(word in msg_lower for word in gaming_keywords):
            # Check gaming-specific triggers
            for situation_type, data in self.advice_database.items():
                if situation_type.startswith('gaming_'):
                    for trigger in data['triggers']:
                        if trigger in msg_lower:
                            print(f"[DEBUG] Matched GAMING trigger '{trigger}' for '{situation_type}'")
                            return [situation_type]
        
        # CRITICAL FIX: Check CURRENT message FIRST for strong topic indicators
        # This prevents "lost in a tournament" being misinterpreted as "pet loss"
        current_msg_matches = []
        for situation_type, data in self.advice_database.items():
            for trigger in data['triggers']:
                if trigger in msg_lower:
                    print(f"[DEBUG] Matched trigger '{trigger}' in CURRENT MESSAGE for '{situation_type}'")
                    current_msg_matches.append(situation_type)
                    break
        
        # If we have a strong match in the current message, prefer those
        if current_msg_matches:
            print(f"[DEBUG] Current message matches: {current_msg_matches}")
            # Prioritize specific topic matches from current message
            if current_msg_matches:
                return current_msg_matches
        
        # Otherwise, check full context (current + history) for broader patterns
        full_context = msg_lower
        if conversation_history:
            for entry in conversation_history[-3:]:  # Last 3 messages
                full_context += " " + entry.get('user', '').lower()
        
        print(f"[DEBUG] detect_situation: full_context = '{full_context}'")
        
        # Find matching situations from full context
        matches = []
        for situation_type, data in self.advice_database.items():
            for trigger in data['triggers']:
                if trigger in full_context:
                    print(f"[DEBUG] Matched trigger '{trigger}' in situation '{situation_type}'")
                    matches.append(situation_type)
                    break
        
        print(f"[DEBUG] All matches: {matches}")
        
        # CRITICAL: Prioritize certain situations over others
        # If both exam_anxiety and academic_failure match, prioritize academic_failure (past failure > future exam)
        if 'academic_failure' in matches and 'exam_anxiety' in matches:
            print(f"[DEBUG] Both exam_anxiety and academic_failure matched - prioritizing academic_failure")
            matches = ['academic_failure'] + [m for m in matches if m != 'academic_failure']
        
        # If both depression_symptoms and general emotional triggers match, prioritize depression_symptoms
        if 'depression_symptoms' in matches and any(m in matches for m in ['sadness_general', 'anxiety_general']):
            print(f"[DEBUG] Depression symptoms detected - prioritizing over general emotions")
            matches = ['depression_symptoms'] + [m for m in matches if m != 'depression_symptoms']
        
        # If both grief_loss and emotional_breakdown match, prioritize emotional_breakdown for immediate coping steps
        if 'grief_loss' in matches and 'emotional_breakdown' in matches:
            print(f"[DEBUG] Both grief_loss and emotional_breakdown matched - prioritizing emotional_breakdown")
            matches = ['emotional_breakdown'] + [m for m in matches if m != 'emotional_breakdown']
        
        # If gaming_loss and grief_loss both match, prioritize gaming_loss if message mentions "game/tournament/rank"
        if 'gaming_loss' in matches and 'grief_loss' in matches:
            if any(word in msg_lower for word in ['game', 'tournament', 'rank', 'match', 'competitive']):
                print(f"[DEBUG] Both gaming_loss and grief_loss matched, but message has gaming keywords - prioritizing gaming_loss")
                matches = ['gaming_loss'] + [m for m in matches if m != 'gaming_loss']
        
        return matches
    
    def should_offer_advice(self, user_message, emotion, conversation_history=None):
        """Determine if AI should offer advice (not just listen)"""
        msg_lower = user_message.lower()
        
        # Don't offer advice on first message - let them share first
        if not conversation_history or len(conversation_history) == 0:
            return False
        
        # CRITICAL: Don't offer advice immediately when user first shares sadness/anxiety
        # Let them elaborate first - AI should LISTEN before suggesting
        if len(conversation_history) == 1:  # Only 1 previous message (greeting)
            # User just shared their problem for the first time
            # AI should show empathy and ask questions, NOT offer advice yet
            return False
        
        # Offer advice if:
        # 1. User has shared their problem (2+ messages)
        # 2. User is asking for help
        # 3. User seems stuck or hopeless
        
        if len(conversation_history) >= 2:  # Changed from >= 1 to >= 2
            # Check if user is asking for help (EXPANDED LIST)
            help_phrases = [
                'what should i do', 'what can i do', 'help me', 'i need help', 
                'any advice', 'what do you think', 'any suggestions', 'any suggestion',
                'i want advice', 'i want your advice', 'i want suggestion', 'i want your suggestion',
                'i want suggestions', 'i want your suggestions', 'i want your advised',
                'give me advice', 'give me suggestion', 'give me suggestions',
                'can you help', 'can you suggest', 'can you advise',
                'what would you suggest', 'what would you recommend', 'what do you recommend',
                'how can i', 'how do i', 'how should i',
                'tell me what to do', 'what to do'
            ]
            if any(phrase in msg_lower for phrase in help_phrases):
                return True
            
            # Check if user shared a specific problem
            situations = self.detect_situation(user_message, emotion, conversation_history)
            if situations:
                return True
        
        return False
    
    def generate_advice(self, user_message, emotion, conversation_history=None):
        """Generate practical advice based on situation - NOW WITH WEB-BASED ADVICE!"""
        situations = self.detect_situation(user_message, emotion, conversation_history)
        
        # CRITICAL FIX: If no situation detected from current message, but user is asking for advice,
        # scan the ENTIRE conversation history to understand their problem
        if not situations and conversation_history:
            print(f"[DEBUG] No situation detected from '{user_message}', scanning conversation history...")
            for entry in reversed(conversation_history):
                entry_text = entry.get('user', '')
                entry_emotion = entry.get('emotion', 'neutral')
                temp_situations = self.detect_situation(entry_text, entry_emotion, None)
                if temp_situations:
                    situations = temp_situations
                    print(f"[DEBUG] Found situation '{situations[0]}' from history: '{entry_text}'")
                    break
        
        if not situations:
            print(f"[DEBUG] Still no situation detected, returning generic advice")
            return None
        
        # Get advice for the first matching situation
        situation_type = situations[0]
        
        # Format the response
        response_parts = []
        
        # Add empathy first
        empathy_phrases = {
            'exam_anxiety': "I understand - exam stress is really tough, especially when you're not prepared.",
            'academic_failure': "I'm sorry you're dealing with this. Failing feels awful, but it's not the end.",
            'anxiety_general': "That sounds really overwhelming. Anxiety can be so intense.",
            'depression_symptoms': "I hear you. What you're describing sounds really difficult.",
            'relationship_problems': "Breakups/relationship issues are so painful. I'm sorry you're going through this.",
            'grief_loss': "I'm so sorry for your loss. Grief is one of the hardest things we go through.",
            'loneliness': "Loneliness is really painful. Feeling isolated is tough.",
            'stress_overwhelm': "That sounds like a lot to handle. Being overwhelmed is exhausting.",
            'sleep_problems': "Sleep problems make everything harder. I get it.",
            'gaming_loss': "Losing a match or tournament you cared about is really frustrating. I understand.",
            'gaming_addiction': "It sounds like gaming has become a big part of your life. That's something worth looking at.",
            'perfectionism': "That sounds exhausting - always trying to be perfect. I hear you.",
            'imposter_syndrome': "That feeling of not belonging, even though you've accomplished so much? I hear that a lot.",
            'self_esteem': "The way you're talking about yourself sounds really harsh. I'm sorry you're feeling this way.",
            'social_anxiety': "Being afraid of social situations is really tough. You're not alone in this.",
            'bullying_harassment': "I'm so sorry people are treating you this way. That's not okay, and it's not your fault.",
            'family_conflict': "Family conflict is so painful because they matter to you. I hear that."
        }
        
        response_parts.append(empathy_phrases.get(situation_type, "I hear you."))
        response_parts.append("")
        
        # DISABLED WEB SCRAPING - Use hardcoded advice which is more contextual
        # Get web-based advice
        # try:
        #     web_advice = get_web_advice(situation_type)
        #     
        #     if web_advice and web_advice.get('tips'):
        #         response_parts.append(f"Here's what might help (based on {web_advice['title']}):")
        #         response_parts.append("")
        #         
        #         # Add tips (limit to 5 for readability)
        #         for i, tip in enumerate(web_advice['tips'][:5], 1):
        #             response_parts.append(f"{i}. {tip['technique']}")
        #             response_parts.append(f"   {tip['description']}")
        #             response_parts.append(f"   Why it works: {tip['why']}")
        #             response_parts.append("")
        #         
        #         # Add sources if available
        #         if web_advice.get('sources'):
        #             response_parts.append("Sources:")
        #             for source in web_advice['sources']:
        #                 response_parts.append(f"• {source['name']}: {source['url']}")
        #             response_parts.append("")
        #         
        #         response_parts.append("Would you like me to explain any of these techniques in more detail?")
        #     else:
        #         # Fallback to hardcoded advice if web scraping fails
        #         advice_data = self.advice_database.get(situation_type)
        #         if advice_data:
        #             advice_entry = random.choice(advice_data['advice'])
        #             response_parts.extend(advice_entry['suggestions'])
        # 
        # except Exception as e:
        #     print(f"Error getting web advice: {e}")
        #     # Fallback to hardcoded advice
        #     advice_data = self.advice_database.get(situation_type)
        #     if advice_data:
        #         advice_entry = random.choice(advice_data['advice'])
        #         response_parts.extend(advice_entry['suggestions'])
        
        # Use hardcoded advice (more contextual and appropriate)
        advice_data = self.advice_database.get(situation_type)
        if advice_data:
            advice_entry = random.choice(advice_data['advice'])
            response_parts.extend(advice_entry['suggestions'])
        
        return "\n".join(response_parts)
    
    def generate_permission_request(self, user_message, emotion, conversation_history=None):
        """Ask permission before giving advice"""
        situations = self.detect_situation(user_message, emotion, conversation_history)
        
        if not situations:
            return None
        
        situation_type = situations[0]
        
        # Permission phrases based on situation
        permission_phrases = {
            'exam_anxiety': "Since the exam is today and you haven't reviewed, would you like me to suggest some quick last-minute study techniques? Or some breathing exercises to calm your nerves?",
            'academic_failure': "Would you like me to suggest some strategies for bouncing back from this? I have some ideas that might help.",
            'anxiety_general': "Would you like me to share some grounding techniques that can help when anxiety feels overwhelming?",
            'depression_symptoms': "Would you be open to hearing about some coping strategies? And maybe talking about counseling resources?",
            'relationship_problems': "Would you like some advice on how to cope with this? I have some suggestions that might help.",
            'grief_loss': "Would you like me to share some concrete grief-coping steps and small routines that can help today?",
            'loneliness': "Would you like some ideas for how to meet people and build connections? I have some suggestions.",
            'stress_overwhelm': "Would you like me to help you break this down into manageable pieces? I can suggest some strategies.",
            'sleep_problems': "Would you like some tips for improving your sleep? I have some evidence-based suggestions.",
            'gaming_loss': "Losing is tough, especially in competitive games. Would you like some strategies to bounce back stronger?",
            'gaming_addiction': "It sounds like gaming has taken over a lot of your time. Would you like some ideas for finding better balance?",
            'perfectionism': "That pressure to be perfect is exhausting. Would you like to explore a healthier way to set goals for yourself?",
            'imposter_syndrome': "That imposter feeling isn't true, even though it feels real. Would you like to explore ways to challenge it?",
            'self_esteem': "The way you're talking about yourself breaks my heart. Would you like some ways to build a kinder relationship with yourself?",
            'social_anxiety': "Social situations feeling scary is more common than you think. Would you like some practical ways to make them feel less overwhelming?",
            'bullying_harassment': "What you're experiencing isn't okay, and you don't deserve it. Would you like to talk about how to get support?",
            'family_conflict': "Family relationships are complicated. Would you like some ways to communicate better or find more peace?"
        }
        
        return permission_phrases.get(situation_type)

# Global instance
advice_system = AdviceSystem()

def should_offer_advice(user_message, emotion, conversation_history=None):
    """Check if AI should offer advice"""
    return advice_system.should_offer_advice(user_message, emotion, conversation_history)

def generate_advice(user_message, emotion, conversation_history=None):
    """Generate advice for user's situation"""
    return advice_system.generate_advice(user_message, emotion, conversation_history)

def generate_permission_request(user_message, emotion, conversation_history=None):
    """Ask permission to give advice"""
    return advice_system.generate_permission_request(user_message, emotion, conversation_history)

if __name__ == "__main__":
    # Test cases
    test_cases = [
        {
            'message': 'today actually, i didnt review at all im scared to fail this',
            'emotion': 'anxious',
            'history': [
                {'user': 'its about my exam', 'ai': 'I get it - exams are tough. When is it?', 'emotion': 'anxious'}
            ]
        },
        {
            'message': 'i failed my test this morning',
            'emotion': 'sad',
            'history': [
                {'user': 'im sad', 'ai': 'What\'s going on?', 'emotion': 'sad'}
            ]
        }
    ]
    
    print("Testing Advice System:\n")
    for test in test_cases:
        print(f"User: {test['message']}")
        print(f"Emotion: {test['emotion']}")
        print(f"History: {len(test['history'])} messages")
        print()
        
        should_offer = should_offer_advice(test['message'], test['emotion'], test['history'])
        print(f"Should offer advice: {should_offer}")
        
        if should_offer:
            permission = generate_permission_request(test['message'], test['emotion'], test['history'])
            print(f"\nPermission request: {permission}")
            
            advice = generate_advice(test['message'], test['emotion'], test['history'])
            print(f"\nAdvice:\n{advice}")
        
        print("\n" + "="*80 + "\n")
