<?php

namespace App\Services;

class ConversationalAIService
{
    /**
     * Extract personal details from conversation history
     * E.g., "my dog died" -> extract "dog" as the subject
     */
    public function extractPersonalDetails(array $conversationHistory): array
    {
        $details = [
            'subjects' => [],
            'events' => [],
            'emotions_expressed' => []
        ];

        foreach ($conversationHistory as $exchange) {
            $text = strtolower($exchange['user'] ?? '');
            
            // Extract subject (dog, cat, friend, parent, boyfriend, etc.)
            $subjects = ['dog', 'cat', 'bird', 'pet', 'friend', 'parent', 'mom', 'dad', 'mother', 'father', 
                        'sister', 'brother', 'sibling', 'boyfriend', 'girlfriend', 'husband', 'wife', 
                        'grandpa', 'grandma', 'grandparent', 'boss', 'coworker', 'colleague', 'best friend'];
            
            foreach ($subjects as $subject) {
                if (strpos($text, $subject) !== false) {
                    $details['subjects'][] = $subject;
                }
            }
            
            // Extract events (death, breakup, loss, accident, etc.)
            $events = ['die', 'died', 'death', 'breakup', 'broke up', 'lose', 'lost', 'accident', 
                      'hospital', 'sick', 'cancer', 'diagnosed', 'fired', 'quit', 'graduated'];
            
            foreach ($events as $event) {
                if (strpos($text, $event) !== false) {
                    $details['events'][] = $event;
                }
            }
        }

        // Remove duplicates and keep unique details
        $details['subjects'] = array_unique($details['subjects']);
        $details['events'] = array_unique($details['events']);

        return $details;
    }

    /**
     * Check if this is a request for more conversation (not to be dismissed)
     * E.g., "can you talk to me more", "i have no one to talk to", "please stay with me"
     */
    public function isRequestForContinuation(string $text): bool
    {
        $continuationPhrases = [
            'talk to me more', 'talk to me', 'can you talk', 'can we talk', 'keep talking',
            'stay with me', 'dont leave me', "don't leave me", 'i have no one', 'no one to talk to',
            'no one cares', 'nobody listens', 'please help', 'just listen', 'listen to me',
            'need someone', 'need you', 'be with me', 'be there', 'help me please',
            'i need you', 'can you help', 'im alone', "i'm alone", 'so alone'
        ];

        $text = strtolower($text);
        foreach ($continuationPhrases as $phrase) {
            if (strpos($text, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is asking a direct question
     */
    public function isDirectQuestion(string $text): bool
    {
        $text = trim($text);
        return strpos($text, '?') !== false && strlen($text) < 200;
    }

    /**
     * Check if this is a simple greeting (not requiring deep therapy)
     */
    public function isSimpleGreeting(string $text): bool
    {
        $text = strtolower(trim($text));
        
        // Very short messages are likely greetings
        if (strlen($text) <= 10) {
            $simpleGreetings = ['hey', 'hi', 'hello', 'yo', 'sup', 'what\'s up', "what's up", 'howdy', 'hey you'];
            foreach ($simpleGreetings as $greeting) {
                if ($text === $greeting) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if the text contains heavy emotional topics
     */
    private function containsHeavyTopic(string $text): bool
    {
        $text = strtolower($text);
        $heavyTopics = [
            'sad', 'depressed', 'depression', 'suicid', 'kill', 'die', 'died', 'death', 'pain', 'hurt',
            'alone', 'lonely', 'nobody', 'no one', 'anxious', 'worried', 'scared', 'afraid', 'angry',
            'hate', 'breakup', 'broke up', 'cheated', 'betray', 'abuse', 'assault',
            'lost', 'losing', 'grief', 'suffering', 'suffer', 'not good', 'bad', 'terrible', 'awful',
            'hard time', 'struggling', 'overwhelm', 'emergency', 'crisis', 'hospital', 'sick',
        ];
        
        foreach ($heavyTopics as $topic) {
            if (strpos($text, $topic) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate a genuinely conversational response
     * Focus on: empathy → active listening → meaningful questions → later advice
     */
    public function generateConversationalResponse(
        string $userText,
        string $emotion,
        array $conversationHistory,
        array $context
    ): string {
        
        // PRIORITY 0: Simple greeting - respond simply, don't over-analyze
        if ($this->isSimpleGreeting($userText)) {
            $greetings = [
                "Hey! How's it going?",
                "Hi! What's on your mind?",
                "Hello! How can I help you today?",
                "Hey there! What's up?",
                "Hi! Good to see you. How are you feeling?",
            ];
            return $greetings[array_rand($greetings)];
        }
        
        // PRIORITY 1: User is asking for more conversation/connection
        if ($this->isRequestForContinuation($userText)) {
            return $this->generateContinuationResponse($userText, $emotion, $conversationHistory);
        }

        // PRIORITY 2: Extract personal details and reference them
        $details = $this->extractPersonalDetails($conversationHistory);
        
        // Get exchange count
        $exchangeCount = count($conversationHistory);

        // PRIORITY 3: Early conversation (1-2 exchanges) - LISTEN, don't advise yet
        if ($exchangeCount <= 2) {
            return $this->generateListeningResponse($userText, $emotion, $details, $conversationHistory);
        }

        // PRIORITY 4: Mid conversation (3-5 exchanges) - Show understanding + ask deeper questions
        if ($exchangeCount <= 5) {
            return $this->generateDeepeningResponse($userText, $emotion, $details, $conversationHistory);
        }

        // PRIORITY 5: Later conversation (6+ exchanges) - Validate + gentle suggestions/resources
        return $this->generateProgressResponse($userText, $emotion, $details, $conversationHistory);
    }

    /**
     * User is explicitly asking to continue the conversation
     * NEVER give advice here - just show you're here and ask meaningful questions
     */
    private function generateContinuationResponse(string $userText, string $emotion, array $conversationHistory): string
    {
        $responses = [
            // For someone who feels alone
            "I'm here, and I'm listening. You're not alone right now - I'm with you. Tell me what's on your mind. What do you need to get off your chest?",
            
            "You reached out to me, and that took courage. I'm genuinely here for you. What's the hardest part of what you're going through right now?",
            
            "I hear you, and I'm not going anywhere. Let's talk through this together. What would help you feel less alone right now?",
            
            "Thank you for trusting me with this. I'm here to listen, really listen. What's something you've been wanting to say but haven't been able to?",
            
            "You matter. Your feelings matter. I'm here, and I'm listening. Tell me more about what's been happening.",
            
            "I can feel how much you need someone to just... listen. I'm here. Talk to me. What do you want me to know about what you're going through?",
        ];

        // Add personalized element if we know what they're dealing with
        $lastExchange = end($conversationHistory);
        $lastUserMessage = $lastExchange['user'] ?? '';
        
        if (stripos($lastUserMessage, 'dog') !== false || stripos($lastUserMessage, 'pet') !== false) {
            $responses[] = "Losing your pet... that's real grief. I want to understand more. Tell me about them. What made them special to you?";
        }
        
        if (stripos($lastUserMessage, 'no one') !== false) {
            $responses[] = "You feel like you're going through this alone. I get that. But I'm here now. Let's talk about what you're really feeling. What's it like for you right now?";
        }

        return $responses[array_rand($responses)];
    }

    /**
     * EARLY CONVERSATION (1-2 exchanges)
     * Goal: Be natural and casual, only get therapeutic if heavy topics appear
     * DO NOT: Give advice, ask "have you tried", offer solutions
     */
    private function generateListeningResponse(string $userText, string $emotion, array $details, array $conversationHistory): string
    {
        $responses = [];
        
        // Check if user is sharing something heavy or just asking to chat
        $isHeavy = $this->containsHeavyTopic($userText);

        // LIGHT/CASUAL - User just asked to share or making small talk
        if (!$isHeavy && $emotion === 'neutral') {
            $responses = [
                "Of course! I'm all ears. What's on your mind?",
                "Yeah, go for it. I'm listening.",
                "Sure! Tell me what you're thinking about.",
                "Absolutely. What do you want to talk about?",
                "I'm here. What's going on?",
            ];
            return $responses[array_rand($responses)];
        }

        // HEAVY TOPICS - Now activate empathetic mode

        // SADNESS
        if ($emotion === 'sad') {
            $responses = [
                "I'm really sorry you're going through this. That sounds really heavy. Can you tell me more about what's making you feel this way?",
                
                "That sounds painful. I want to understand what you're experiencing. What's been the hardest part for you?",
                
                "I hear the sadness in what you're sharing. I'm here, and I'm listening. What happened that brought you to this point?",
                
                "That's a lot to carry. Thank you for sharing that with me. Can you tell me more about what's going on?",
            ];
            
            // Personalized if they mentioned a loss
            if (!empty($details['subjects'])) {
                $subject = $details['subjects'][0];
                $responses[] = "Losing your {$subject}... that's real grief. I'm so sorry. Tell me about what this means to you. Who were they?";
            }
        }

        // ANXIOUS
        elseif ($emotion === 'anxious') {
            $responses = [
                "I can hear the worry in what you're saying. That sounds really stressful. What's making you feel so anxious right now?",
                
                "That sounds overwhelming. Can you walk me through what's worrying you the most?",
                
                "I'm sensing a lot of stress and worry. What's going on that's got you feeling this way?",
                
                "That sounds like a lot to handle. I want to understand better. Can you tell me more about what's making you feel so anxious?",
            ];
        }

        // ANGRY
        elseif ($emotion === 'angry') {
            $responses = [
                "I can tell you're really frustrated about this. That's valid. What happened that made you feel this way?",
                
                "You sound upset, and I want to understand why. Can you tell me what's going on?",
                
                "That sounds infuriating. What's really bothering you about this situation?",
                
                "I hear you - that would upset anyone. Can you tell me more about what happened?",
            ];
        }

        // DEPRESSED
        elseif ($emotion === 'depressed') {
            $responses = [
                "I'm really concerned about what you just shared. That sounds like you're in a dark place right now. Can you tell me how long you've been feeling this way?",
                
                "This sounds serious, and I want you to know I'm taking this seriously too. Can you talk to me about what's been going on?",
                
                "What you're describing... that's depression, and it's real. I want to understand. How long have you been feeling like this?",
                
                "I'm here, and I'm listening. What you're going through sounds really difficult. Can you tell me more?",
            ];
        }

        // DEFAULT FOR HEAVY TOPICS
        else {
            $responses = [
                "That sounds important. Can you tell me more about what's going on?",
                
                "I can tell this matters to you. What's the main thing on your mind?",
                
                "I'm here. What do you need to talk about?",
            ];
        }

        return $responses[array_rand($responses)] ?? $responses[0];
    }

    /**
     * MID CONVERSATION (3-5 exchanges)
     * Goal: Show deeper understanding, ask questions that show you listened, validate
     * Still: Minimize advice, focus on emotional support
     */
    private function generateDeepeningResponse(string $userText, string $emotion, array $details, array $conversationHistory): string
    {
        $responses = [];
        
        // Look at what they've been talking about
        $lastExchange = end($conversationHistory);
        $previousUserMessages = array_column($conversationHistory, 'user');
        $combinedHistory = implode(' ', $previousUserMessages);
        $combinedHistory = strtolower($combinedHistory);

        // DEEPENING RESPONSE FOR LOSS/GRIEF
        if (!empty($details['subjects']) || strpos($combinedHistory, 'die') !== false) {
            $subject = $details['subjects'][0] ?? 'them';
            $responses = [
                "It sounds like your {$subject} meant a lot to you. Losing someone important... that's not something you just get over. How are you managing day to day?",
                
                "I'm really hearing how much pain you're in about losing your {$subject}. That's a real loss. What's been the hardest part since it happened?",
                
                "Losing your {$subject}... I can tell that's weighing on you heavily. When did this happen? How are you holding up?",
            ];
        }

        // DEEPENING RESPONSE FOR LONELINESS  
        elseif (strpos($combinedHistory, 'alone') !== false || strpos($combinedHistory, 'no one') !== false) {
            $responses = [
                "It sounds like you're feeling really isolated right now. Like nobody really understands what you're going through. Is that how it feels?",
                
                "You mentioned feeling like you have no one to talk to. That's such a lonely place to be. How long have you been feeling this disconnected?",
                
                "I'm hearing that you don't have anyone to turn to, and that isolation is making everything harder. Can you tell me - is there anyone at all, even just one person, you feel safe with?",
            ];
        }

        // DEEPENING RESPONSE FOR ONGOING STRUGGLE
        elseif ($emotion === 'sad') {
            $responses = [
                "It sounds like this has been going on for a while now. How long have you been feeling this way? Has anything helped, even a little bit?",
                
                "I'm noticing this is really heavy for you. On a scale of 1-10, how are you really doing right now?",
                
                "You keep coming back to this pain. I want you to know that it's okay to feel this way, and I'm here. What would help you feel even slightly better right now?",
            ];
        }

        // DEEPENING FOR ANXIETY
        elseif ($emotion === 'anxious') {
            $responses = [
                "It sounds like your mind is really spinning right now. What's the main thing that keeps coming back to worry you?",
                
                "You're carrying a lot of worry. What would help you feel even a tiny bit calmer right now?",
                
                "I'm noticing you're really stressed. What's making you feel most out of control about this situation?",
            ];
        }

        // DEFAULT
        else {
            $responses = [
                "I want to really understand what you're going through. What's something you haven't told me yet that I should know?",
                
                "Thank you for being honest with me. This is clearly important. What's the part that's hardest for you?",
                
                "I'm seeing a bigger picture of what you're dealing with. What would actually help right now?",
            ];
        }

        return $responses[array_rand($responses)] ?? $responses[0];
    }

    /**
     * LATER CONVERSATION (6+ exchanges)
     * Goal: Validate → gently suggest perspective shift → offer practical resources/suggestions
     * Now we've EARNED the right to offer advice through genuine listening
     */
    private function generateProgressResponse(string $userText, string $emotion, array $details, array $conversationHistory): string
    {
        $responses = [];

        // Check if they're showing any signs of progress
        $hasProgressMarkers = false;
        $lastFewMessages = array_slice($previousUserMessages = array_column($conversationHistory, 'user'), -3);
        $lastFewCombined = strtolower(implode(' ', $lastFewMessages));
        
        if (strpos($lastFewCombined, 'better') !== false || 
            strpos($lastFewCombined, 'help') !== false ||
            strpos($lastFewCombined, 'try') !== false) {
            $hasProgressMarkers = true;
        }

        // FOR LOSS/GRIEF - Validate the grief journey
        if (!empty($details['subjects']) || strpos($lastFewCombined, 'die') !== false) {
            if ($hasProgressMarkers) {
                $responses = [
                    "I'm really glad you're open to moving forward with this. Grief isn't something you 'get over' - it's something you learn to carry. Here are some things that might help: <br><br>• Allow yourself to feel whatever comes up - sadness, anger, even moments of joy. That's normal.<br>• Consider talking to a counselor who specializes in grief - they can help you process this in a safe way.<br>• Some people find it healing to do something in their memory - plant a tree, donate, create a photo album.<br>• Lean on friends and family when you can, even just for a moment of connection.<br>",
                ];
            } else {
                $responses = [
                    "Grief is a journey, and you're right where you need to be - feeling what you need to feel. There's no timeline for this. What you're experiencing is real and valid.<br><br>When you're ready, talking to someone trained in grief support - like a counselor - can really help. Would you be open to that?",
                ];
            }
        }

        // FOR ONGOING DEPRESSION/SADNESS
        elseif ($emotion === 'sad' || $emotion === 'depressed') {
            $responses = [
                "What you're experiencing is real, and I want you to know that depression isn't weakness - it's a signal that you need support. Have you considered talking to a counselor? They can offer tools and strategies specifically designed for this.<br><br>In the meantime: <br>• Be gentle with yourself<br>• Try to maintain small routines (even just drinking water or going outside)<br>• Tell someone you trust how you're feeling<br><br>You don't have to do this alone.",
                
                "You've been carrying this for a while now, and I can see how much it's affecting you. That tells me it might be time to get professional support - a counselor or therapist who can help you work through this more deeply. Would you be open to chatting with our school counselor?",
            ];
        }

        // FOR ANXIETY
        elseif ($emotion === 'anxious') {
            $responses = [
                "All this worry you're carrying... that's a lot. You've been really brave in talking through it with me. But I think you'd benefit from learning some actual anxiety management techniques - things like breathing exercises, grounding techniques, or talking to a counselor.<br><br>Would you be interested in trying some of these? Or talking to a counselor who can teach you coping strategies?",
            ];
        }

        // DEFAULT
        else {
            $responses = [
                "Thank you for opening up so much. I can see you're really trying to work through this. That's important. What do you think would help you move forward from here?",
            ];
        }

        return $responses[array_rand($responses)] ?? $responses[0];
    }
}
