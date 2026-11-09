"""
Semantic Analysis Layer for Enhanced Crisis Detection
Complements supervised emotion model with:
- Emotion probability distributions (all classes)
- Emotion intensity calculation (0-1 scale)
- Sarcasm/satire/hyperbole detection
- Crisis semantic indicators (IPMA)
- Negation understanding
- Context-aware emotion analysis
- Confidence scoring

This layer ENHANCES the supervised model, doesn't replace it
Works upstream of Laravel safety layer for improved coordination
"""

import re
from typing import Dict, List, Tuple
import json

class SemanticAnalyzer:
    """
    Analyze text semantically to understand nuance, tone, and crisis indicators
    Returns structured data for both Laravel and response generation
    """
    
    def __init__(self):
        """Initialize semantic analyzer with pattern definitions"""
        
        # PHASE 2: Slang/intensifier normalization mapping
        # Maps slang to their normalized forms for pattern matching
        self.slang_normalization = {
            'asf': 'as fuck',  # Very strongly
            'af': 'as fuck',   # Very strongly
            'fr': 'for real',  # Seriously/Really
            'ngl': 'not gonna lie',  # Seriously (intensifier)
            'lowkey': 'somewhat',  # Moderately
            'highkey': 'very',  # Strongly
        }
        
        # Emotion vocabulary organized by emotion class
        self.emotion_vocabulary = {
            'sadness': {
                'keywords': [
                    'sad', 'unhappy', 'down', 'depressed', 'lonely', 'heartbroken',
                    'crying', 'tearful', 'miserable', 'sorrowful', 'devastated',
                    'lost', 'grief', 'bereaved', 'not good', 'terrible', 'awful',
                    'awful', 'hopeless', 'empty', 'numb', 'broken', 'shattered',
                    'dejected', 'despondent', 'melancholic', 'gloomy', 'dreary',
                    'failed', 'failure', 'disappointed', 'disappointment', 'let down',
                    'can\'t handle', 'overwhelmed', 'drowning', 'sinking',
                    # FIX 2: Added common soft negatives that were missing (Sept 27, 2026)
                    'bad', 'rough', 'tough', 'rough day', 'tough day', 'struggling',
                    'struggling with', 'hard', 'hard time', 'difficult', 'tough time',
                    # TASK 9: Filipino/Tagalog sadness keywords (Oct 9, 2026)
                    'malungkot', 'lungkot', 'nawalan', 'wala ang gana',
                    # TASK 9 PHASE 3: Anhedonia indicators (Oct 9, 2026)
                    # Low motivation, lack of energy, general unwillingness to engage
                    'wala akong gana', 'walang gana', 'no energy', 'no motivation',
                    'don\'t feel like', 'don\'t want to', 'i don\'t want to do anything',
                    'can\'t be bothered', 'ayoko', 'wala ng gana',
                    # TASK 9 PHASE 5: Crisis-related sadness keywords (Oct 15, 2026)
                    # Hopelessness indicators - must be detected for crisis escalation
                    'no hope', 'lose hope', 'losing hope', 'pag-asa', 'walang pag-asa',
                    'wala nang pag-asa', 'wala pag-asa', 'disappeared hope',
                    'want to disappear', 'want to go away', 'don\'t want to be here',
                    'gusto ko nang mawala', 'gusto ko nang lumisan'
                ],
                'indicators': {
                    'death': ['died', 'death', 'dead', 'lost someone', 'passing', 'funeral'],
                    'loss': ['lost', 'loss', 'left me', 'broke up', 'breakup', 'divorce'],
                    'isolation': ['alone', 'lonely', 'isolated', 'by myself', 'no one'],
                    'failure': ['failed', 'failed at', 'didn\'t make it', 'couldn\'t pass'],
                    # PHASE 3: Financial hardship + self-state patterns (NOT just "broke")
                    # IMPORTANT: Only match first-person emotional framing to distinguish from neutral cases
                    'financial_self_state': [
                        'i\'m broke', 'i am broke', 'i\'m so broke', 'i\'m really broke',
                        'i\'m broke asf', 'i\'m broke af', 'i\'m broke fr', 'i\'m broke ngl',
                        'i\'m lowkey broke', 'i\'m highkey broke', 'i\'m going broke',
                        'i\'m somewhat broke',  # lowkey expansion
                        'i\'m very broke',  # highkey expansion
                        'i\'m as fuck broke',  # asf/af expansion
                        'i\'m for real broke',  # fr expansion
                        'i\'m not gonna lie broke',  # ngl expansion
                        'i\'m struggling financially', 'i\'m stressed about money', 'i\'m worried about money',
                        'can\'t afford my tuition', 'can\'t afford school', 'can\'t afford my school expenses',
                        'can\'t afford my expenses', 'money is stressing me out', 'i don\'t have enough money',
                    ],
                    'hardship_context': [
                        'struggling financially', 'struggling with money', 'financial hardship',
                        'financial stress', 'money problems', 'financial troubles', 'financial difficulty',
                        'worried about money', 'anxious about money', 'stressed about money',
                    ]
                }
            },
            'anxiety': {
                'keywords': [
                    'anxious', 'worried', 'nervous', 'stressed', 'panic', 'panicking',
                    'afraid', 'scared', 'frightened', 'terrified', 'overwhelming',
                    'worry', 'dread', 'uneasy', 'tense', 'jittery', 'on edge',
                    'anxious', 'apprehensive', 'restless', 'disturbed',
                    'deadline', 'pressure', 'rushed', 'hurried', 'urgent',
                    'unprepared', 'unfinished', 'incomplete', 'behind', 'failing',
                    # TASK 9: Filipino/Tagalog anxiety keywords (Oct 9, 2026)
                    # Note: both spellings included for variations
                    'kinakabahan', 'kinakabihan', 'kabado', 'nag-aalala', 'alalahanin'
                ],
                'indicators': {
                    'physical': ['racing heart', 'chest pain', 'cant breathe', 'shaking', 'sweating'],
                    'future': ['what if', 'what happens', 'worried about'],
                    'panic': ['panic attack', 'panicking', 'cant calm down'],
                    'workload': ['too much', 'pile up', 'stacking', 'drowning in work', 'swamped'],
                    'academic': ['exam', 'test', 'project due', 'assignment', 'study'],
                    # FIX 3: Enhanced academic/workload context detection
                    'academic_workload': [
                        'project due', 'projects due', 'assignment due', 'assignments due',
                        'coursework due', 'presentation due', 'exam due',
                        'haven\'t finished', 'unfinished projects', 'unfinished assignments',
                        'multiple projects', 'multiple assignments', 'multiple deadlines',
                        'three projects', 'four projects', 'five projects',
                        'three assignments', 'three deadlines',
                        'group project', 'group work',
                        'presentation tomorrow', 'exam tomorrow', 'test tomorrow'
                    ],
                    'study_stress': [
                        'studying for', 'prep for exam', 'preparing for', 'weeks of',
                        'all day', 'still haven\'t', 'nothing working', 'not working',
                        'can\'t finish', 'won\'t finish', 'getting done'
                    ],
                    # PHASE 3: Financial stress + freaking out patterns
                    'freaking_out': [
                        'i\'m freaking out', 'i am freaking out', 'i\'m freaking out about money',
                        'i\'m freaking out about tuition', 'everything\'s expensive and i\'m freaking out',
                        'everything is expensive and i\'m freaking out', 'i\'m freaking out about expenses',
                        'i\'m freaking out about my expenses',
                    ],
                    'financial_stress': [
                        'stressed about money', 'stressed about tuition', 'stressed about expenses',
                        'worried about money', 'worried about tuition', 'worried about expenses',
                        'anxious about money', 'anxious about tuition', 'anxious about finances',
                    ]
                }
            },
            'joy': {
                'keywords': [
                    'happy', 'happy', 'great', 'wonderful', 'amazing', 'excellent',
                    'excited', 'love', 'delighted', 'fantastic', 'thrilled', 'joyful',
                    'blessed', 'grateful', 'grateful', 'excited', 'optimistic', 'hopeful',
                    'cheerful', 'elated', 'ecstatic', 'over the moon',
                    'surprised', 'surprised me', 'unexpected good', 'pleasant surprise',
                    # TASK 9: Filipino/Tagalog joy keywords (Oct 9, 2026)
                    'masaya', 'saya',
                    # TASK 9 PHASE 3: Positive expressions (Oct 9, 2026)
                    # Add soft positive indicators when combined with positive VADER + wording context
                    'doing well', 'doing pretty well', 'pretty good', 'feeling pretty good',
                    'having a good day', 'doing good', 'really well', 'doing really well'
                ],
                'indicators': {
                    'achievement': ['got', 'passed', 'made', 'succeeded', 'won', 'accomplished'],
                    # FIX 5: Add positive social/event indicators
                    'positive_event': ['made my day', 'whole day', 'made my whole day', 'friends surprised', 'surprise party'],
                    'connection': ['with friends', 'with family', 'love them', 'care', 'spending time'],
                }
            },
            'anger': {
                'keywords': [
                    'angry', 'mad', 'furious', 'rage', 'furious', 'infuriated',
                    'irritated', 'annoyed', 'frustrated', 'upset', 'livid', 'enraged',
                    'pissed', 'hate', 'disgusted', 'bitter', 'hostile', 'aggressive',
                    # TASK 9: Filipino/Tagalog anger keywords (Oct 9, 2026)
                    'galit', 'galit na galit', 'nakakainis'
                ],
                'indicators': {
                    'injustice': ['not fair', 'unfair', 'wrong', 'treated badly'],
                    'conflict': ['fighting', 'arguing', 'argument', 'confrontation'],
                }
            },
            'neutral': {
                'keywords': [
                    'ok', 'okay', 'fine', 'alright', 'so-so', 'normal', 'regular',
                    'nothing special', 'unremarkable', 'typical'
                ],
                'indicators': {}
            },
            'crisis': {
                'keywords': [
                    'suicide', 'kill myself', 'end my life', 'want to die',
                    'self harm', 'hurt myself', 'cut myself', 'harm myself'
                ],
                'indicators': {
                    'intent': ['i think i', 'i want to', 'i\'m going to', 'i will'],
                    'planning': ['planned', 'figured out how', 'know exactly how'],
                    'method': ['gun', 'knife', 'pills', 'overdose', 'rope', 'poison'],
                    'access': ['i have', 'can get', 'have access', 'available'],
                    'temporal': ['tonight', 'right now', 'immediately', 'now'],
                }
            }
        }
        
        # Negation patterns that flip emotion
        self.negation_patterns = [
            r'\b(not|no|never|dont|don\'t|cannot|can\'t|isn\'t|aren\'t)\s+',
            r'\b(barely|hardly|scarcely|rarely)\s+',
            r'\b(without)\s+',
            # TASK 9 PHASE 3: Tagalog negation patterns (Oct 9, 2026)
            r'\b(hindi|wala|di)\b',  # Tagalog negation particles
            r'\b(hindi naman|di naman)\b',  # Common Tagalog negation phrases
        ]
        
        # Sarcasm patterns
        self.sarcasm_patterns = [
            r'\b(yeah sure|oh sure|right|yep|sure sure|oh yeah)\b',
            r'\b(like i care|who cares|yeah right|oh please)\b',
            r'(lol|lmao|haha)\s+',
            r'\b(oh gee|oh boy|wonderful|fantastic|great)\b.*\b(not|never)\b',
            r'\b(not|never)\b.*\b(oh gee|oh boy|wonderful|fantastic|great)\b',
        ]
        
        # Hyperbole patterns
        self.hyperbole_patterns = [
            r'\b(dying|gonna die|killing me|kill me|dead)\s+(of|from|laugh|boredom)',
            r'\b(to death|to pieces)\b',
            r'\b(literally|absolutely)\s+(dying|killing me)',
            r'\b(so|such|really|extremely)\s+(dying|killing me|dead)\b',
        ]
        
        # Rhetorical question patterns
        self.rhetorical_question_patterns = [
            r'(why|how)\s+\w+.*\?',  # Rhetorical question
            r'(can\'t you see|don\'t you understand)\?',
        ]
    
    def _normalize_slang(self, text: str) -> str:
        """
        PHASE 2: Normalize slang/intensifiers for better pattern matching
        
        Examples:
        - "I'm broke asf" → "I'm broke as fuck"  (for pattern matching)
        - "I'm lowkey stressed" → "I'm somewhat stressed"
        - "I'm freaking out fr" → "I'm freaking out for real"
        
        Returns normalized text for emotion classification
        IMPORTANT: Original text is preserved separately for response generation
        """
        normalized = text.lower()
        
        # Replace slang with normalized forms (for pattern matching only)
        for slang, replacement in self.slang_normalization.items():
            # Use word boundary to avoid substring matches
            normalized = re.sub(r'\b' + re.escape(slang) + r'\b', replacement, normalized, flags=re.IGNORECASE)
        
        return normalized
    
    def analyze(self, text: str, conversation_history: List[Dict] = None) -> Dict:
        """
        Perform complete semantic analysis
        
        Returns structured JSON with all analyses
        """
        
        # PHASE 2: Preprocess slang/intensifiers
        # IMPORTANT: Detect negation BEFORE normalizing slang to avoid false positives
        # "ngl" normalizes to "not gonna lie" which contains "not" -> false negation detection
        negation_info = self.detect_negation(text)
        
        normalized_text = self._normalize_slang(text)
        
        # Core analyses - use normalized text for emotion detection
        emotion_probs = self.calculate_emotion_probabilities(normalized_text)
        emotion_intensity = self.calculate_emotion_intensity(text)
        tone_analysis = self.analyze_tone(text)
        crisis_semantics = self.analyze_crisis_semantics(text)
        context_relevance = self.analyze_context_relevance(text, conversation_history)
        
        # Get dominant emotion
        dominant_emotion = self.get_dominant_emotion(emotion_probs)
        confidence = emotion_probs[dominant_emotion]
        
        # DIAGNOSTIC LOGGING: Print final emotion decision
        print(f"[SEMANTIC_FINAL] emotion={dominant_emotion}, confidence={confidence:.0%}, "
              f"intensity={emotion_intensity:.0%}")
        
        # NEW: Get VADER scores for comparison
        vader_result = self.analyze_with_vader(text)
        print(f"[VADER_COMPARE] compound={vader_result['compound']:.3f} pos={vader_result['positive']:.3f} neu={vader_result['neutral']:.3f} neg={vader_result['negative']:.3f}")
        
        result = {
            'emotion': {
                'dominant': dominant_emotion,
                'confidence': confidence,
                'intensity': emotion_intensity,
                'scores': emotion_probs
            },
            'tone': tone_analysis,
            'crisis_semantic_analysis': crisis_semantics,
            'negation_detected': negation_info['negation_detected'],
            'context_relevance': context_relevance,
            'ml_available': True,
            'analysis_timestamp': self.get_timestamp(),
        }
        
        return result
    
    def calculate_emotion_probabilities(self, text: str) -> Dict[str, float]:
        """
        Calculate probability distribution across all emotion classes
        Not just dominant, but all classes
        
        TASK 9 FIX: Explicit emotion has higher priority than context indicators
        Returns dict with probability for each emotion
        """
        import re  # FIX 5A: Import at method level for word boundary matching
        
        text_lower = text.lower()
        scores = {
            'happiness': 0.0,  # Corresponds to 'joy' in emotion_vocabulary
            'sadness': 0.0,
            'anxiety': 0.0,
            'anger': 0.0,
            'neutral': 0.0,
        }
        
        # Track matched keywords for diagnostic logging
        matched_keywords = {
            'happiness': [],
            'sadness': [],
            'anxiety': [],
            'anger': [],
            'neutral': [],
        }
        
        # Track source of emotion detection (for confidence adjustment)
        emotion_sources = {
            'happiness': [],
            'sadness': [],
            'anxiety': [],
            'anger': [],
            'neutral': [],
        }
        
        # Check for negation patterns (flips positive to negative)
        has_negation = any(re.search(pattern, text, re.IGNORECASE) for pattern in self.negation_patterns)
        
        # TASK 9 PHASE 5: Semantic phrase matching for common failure/disappointment patterns
        # These patterns indicate sadness/disappointment, not anxiety
        # Examples: "didn't pass", "hindi nakapasa", "failed exam", "can't make it"
        failure_patterns = [
            r'(didn\'t|did not|couldn\'t|could not)\s+(pass|make it|succeed|go)',
            r'\b(hindi|di)\s+(ako\s+)?(nakapasa|nakakuha|nakamit)',  # Tagalog: didn't pass/get/achieve
            r'\b(failed|failed to|failure)\b',
            r'(can\'t|cannot|couldn\'t|could not)\s+(do it|make it|pass)',
        ]
        
        has_failure_pattern = any(re.search(pattern, text, re.IGNORECASE) for pattern in failure_patterns)
        
        # Score each emotion based on vocabulary - using direct accumulation without harmful normalization
        for emotion, vocab in self.emotion_vocabulary.items():
            if emotion == 'crisis':
                continue
            
            # FIX 5A: Map emotion names between vocabulary keys and scores dict
            # Vocabulary uses 'joy', but scores dict uses 'happiness'
            score_key = 'happiness' if emotion == 'joy' else emotion
            
            score = 0.0
            
            # TASK 9 PHASE 2: Explicit emotion keywords have HIGHER weight than context indicators
            # Priority: Explicit emotion > Sentence semantics > Tone > Context indicators
            # Check keywords with word boundaries to avoid substring matches
            # "mad" should not match "my"
            for keyword in vocab['keywords']:
                # Use word boundary regex for most keywords, simple contains for multi-word phrases
                if ' ' in keyword:
                    # Multi-word phrase: use simple contains
                    if keyword in text_lower:
                        score += 0.40  # TASK 9: INCREASED from 0.15 to 0.40 - explicit emotion is strongest
                        matched_keywords[score_key].append(f"keyword:{keyword}")
                        emotion_sources[score_key].append(f"explicit_keyword:{keyword}")
                else:
                    # Single word: use word boundary regex
                    if re.search(r'\b' + re.escape(keyword) + r'\b', text_lower):
                        score += 0.40  # TASK 9: INCREASED from 0.15 to 0.40
                        matched_keywords[score_key].append(f"keyword:{keyword}")
                        emotion_sources[score_key].append(f"explicit_keyword:{keyword}")
            
            # TASK 9 PHASE 5: Special handling for sadness when failure pattern detected
            # If user says "didn't pass", that's SADNESS, not anxiety, even without explicit emotion
            if score_key == 'sadness' and has_failure_pattern:
                score += 0.35  # Strong semantic indicator of disappointment/sadness
                emotion_sources[score_key].append("semantic_failure_pattern")
                matched_keywords[score_key].append("semantic_pattern:failure")
            
            # Check indicators with word boundaries and context-aware logic
            for category, indicators in vocab['indicators'].items():
                for indicator in indicators:
                    matched = False
                    
                    # Use word boundary regex for single words, contains for phrases
                    if ' ' in indicator:
                        # Multi-word phrase: use simple contains
                        matched = indicator in text_lower
                    else:
                        # Single word: use word boundary regex
                        matched = bool(re.search(r'\b' + re.escape(indicator) + r'\b', text_lower))
                    
                    if matched:
                        # PHASE 3: Context-aware constraint for "broke" and financial phrases
                        # Distinguish: "I'm broke" (sadness) vs "My phone broke" (neutral)
                        
                        skip_match = False
                        
                        if indicator == 'broke' or 'broke' in indicator:
                            # Check if "broke" is describing an object (equipment failure)
                            # Pattern: (my|the|a) + [noun] + broke
                            if re.search(r'\b(my|the|his|her|its|a)\s+\w+\s+broke\b', text_lower):
                                # Skip this match - it's equipment failure, not financial hardship
                                skip_match = True
                        
                        # Also check for "can't afford X object" (neutral) vs "can't afford my school expenses" (sadness)
                        if 'can\'t afford' in indicator or 'cannot afford' in indicator:
                            # Check if it's describing a personal necessity (school, expenses, tuition)
                            # vs just an object (laptop, phone, etc.)
                            necessity_words = ['tuition', 'school', 'expenses', 'education', 'books']
                            if not any(word in text_lower for word in necessity_words):
                                # Check if it's just an object
                                object_pattern = r'can\'t afford (a|the|my)?\s+\w+\s*(laptop|phone|car|house|ticket|meal)\b'
                                if re.search(object_pattern, text_lower):
                                    skip_match = True
                        
                        if not skip_match:
                            # TASK 9 PHASE 2: Context/topic indicators get MUCH lower weight
                            # Distinguish between strong indicators and weak context indicators
                            if category in ['academic', 'academic_workload', 'study_stress']:
                                # TASK 9: REDUCED from 0.25 to 0.05 - context indicators are weak
                                # "exam" alone should NOT push emotion to 100%
                                score += 0.05
                                emotion_sources[score_key].append(f"context_indicator:{indicator}")
                            else:
                                # Other indicators (death, loss, physical panic) are stronger
                                # TASK 9: Reduced from 0.25 to 0.15
                                score += 0.15
                                emotion_sources[score_key].append(f"semantic_indicator:{indicator}")
                            
                            matched_keywords[score_key].append(f"indicator:{indicator}")
            
            # FIX 1: Remove harmful word_count damping
            # Old logic: score = score / max(1, word_count / 10) heavily penalized short texts
            # New logic: Cap score at 1.0 but don't penalize based on message length
            if score_key in scores:
                scores[score_key] = min(1.0, score)
        
        # DIAGNOSTIC LOGGING: Print raw scores before normalization and negation handling
        # Don't print the message itself (may contain emoji that causes cp1252 encoding error)
        print(f"\n[SEMANTIC_RAW_SCORES] msg_len={len(text)}")
        print(f"[SEMANTIC_RAW_SCORES] happiness={scores['happiness']:.2f}, sadness={scores['sadness']:.2f}, "
              f"anxiety={scores['anxiety']:.2f}, anger={scores['anger']:.2f}, neutral={scores['neutral']:.2f}")
        for emotion, keywords in matched_keywords.items():
            if keywords:
                print(f"[SEMANTIC_MATCHED] {emotion}: {keywords}")
        
        # NEGATION HANDLING (FIX: DEBUGGING PASS 2)
        # OLD BUGGY LOGIC: If negation detected, set neutral to 60% regardless of context
        # This caused: "I'm stressed and I don't know..." → neutral 80% (wrong!)
        # NEW LOGIC: Only apply negation if it directly negates the detected emotion
        if has_negation:
            print(f"[SEMANTIC_NEGATION] Negation detected")
            # ONLY flip positive emotion to negative if happiness is high
            # Example: "I'm not happy" -> sad emotion is appropriate
            if scores['happiness'] > 0.2:
                # "not happy" -> sadness
                scores['sadness'] = max(scores['sadness'], scores['happiness'] * 0.9)
                scores['happiness'] = scores['happiness'] * 0.3  # Reduce happiness
                print(f"[SEMANTIC_NEGATION] Flipped happiness->sadness")
            # REMOVED: scores['neutral'] = max(scores['neutral'], 0.6)
            # This was causing neutral to override all other emotions
            # If user says "I don't know how to manage" but "stressed" was detected,
            # the negation is contextual, not a global negation of emotion
        
        # FIX 1: Smarter fallback when total score is low (vocabulary miss)
        # Old logic: Default to uniform distribution (all 20% for 5 emotions) - WRONG
        # New logic: If we have NO matches, use mostly neutral but keep some distribution
        total = sum(scores.values())
        
        # TASK 9 PHASE 4: Fix confidence calculation for weak evidence
        # OLD BUGGY BEHAVIOR: Automatic normalization to 100%
        # Example: anxiety=0.25 (from 1 context indicator)
        #          normalization: 0.25 / 0.25 = 1.0 = 100% ✓ (mathematically correct)
        # PROBLEM: Conceptually wrong - weak evidence should NOT be 100% confident
        
        if total > 0:
            # Normal case: normalize to probability distribution
            scores = {k: v / total for k, v in scores.items()}
            
            # TASK 9 PHASE 5: Apply stronger confidence cap if evidence is VERY weak
            # Context indicators alone (0.05 per match) should NOT determine emotion
            # Only context indicator matches (no explicit emotion keywords):
            has_only_context_indicators = (
                total <= 0.1 and  # Very weak total score
                all(total - s < 0.06 for s in scores.values() if s > 0)  # All matches are 0.05 contributions
            )
            
            if has_only_context_indicators:
                # TASK 9: When ONLY weak context indicators match (like just "exam"),
                # boost neutral significantly but cap confidence at 60%
                # This prevents "just mention exam" from creating anxiety
                print(f"[SEMANTIC_CONTEXT_ONLY] Only weak context indicators detected, boosting neutral with confidence cap")
                scores['neutral'] = 0.6  # Neutral gets 60% when only context indicators
                # Distribute remaining to other emotions equally
                remaining = 0.4
                other_scores_sum = sum(s for e, s in scores.items() if e != 'neutral' and s > 0)
                if other_scores_sum > 0:
                    for emotion in ['happiness', 'sadness', 'anxiety', 'anger']:
                        scores[emotion] = scores[emotion] * (remaining / other_scores_sum)


            
            elif total < 0.4:
                # Weak evidence detected (not just context-only)
                max_confidence = 0.65  # Cap at 65% if weak evidence
                for emotion in scores:
                    scores[emotion] = scores[emotion] * max_confidence
                    # Re-normalize after applying cap
                    
                # Boost neutral since we're uncertain
                scores['neutral'] = 1.0 - sum(scores.values())
                print(f"[SEMANTIC_CONFIDENCE_CAP] Weak evidence (total={total:.2f}), capping max confidence at {max_confidence:.0%}")
        else:
            # Fallback case: No keywords matched - use neutral-biased distribution instead of uniform
            # This prevents accidental wrong emotion selection
            scores = {
                'happiness': 0.1,
                'sadness': 0.1,
                'anxiety': 0.1,
                'anger': 0.1,
                'neutral': 0.6,  # Neutral gets majority when we're uncertain
            }
            print(f"[SEMANTIC_NO_MATCH] No emotion evidence found, using neutral-biased default")
        
        # Final normalization to ensure probabilities sum to 1.0
        final_total = sum(scores.values())
        if final_total > 0:
            scores = {k: v / final_total for k, v in scores.items()}
        
        # DIAGNOSTIC LOGGING: Print normalized scores
        print(f"[SEMANTIC_NORMALIZED] happiness={scores['happiness']:.0%}, sadness={scores['sadness']:.0%}, "
              f"anxiety={scores['anxiety']:.0%}, anger={scores['anger']:.0%}, neutral={scores['neutral']:.0%}")
        
        return scores
    
    def calculate_emotion_intensity(self, text: str) -> float:
        """
        Calculate intensity of the dominant emotion (0-1 scale)
        How STRONG is the emotion?
        """
        text_lower = text.lower()
        
        # Intensity intensifiers
        intensifiers = [
            ('very', 0.3), ('really', 0.25), ('so', 0.2),
            ('extremely', 0.35), ('absolutely', 0.4),
            ('literally', 0.3), ('just', 0.1), ('always', 0.25),
            ('never', 0.25), ('ever', 0.2), ('all', 0.2),
        ]
        
        intensity = 0.5  # baseline
        
        for intensifier, boost in intensifiers:
            if intensifier in text_lower:
                intensity = min(1.0, intensity + boost)
        
        # Caps lock increases intensity
        caps_ratio = sum(1 for c in text if c.isupper()) / max(1, len(text))
        if caps_ratio > 0.3:
            intensity = min(1.0, intensity + 0.2)
        
        # Multiple exclamation marks
        if text.count('!') > 1:
            intensity = min(1.0, intensity + 0.15)
        
        # Repeated words (e.g., "so so so sad")
        words = text_lower.split()
        if len(words) != len(set(words)):
            intensity = min(1.0, intensity + 0.1)
        
        return min(1.0, max(0.0, intensity))
    
    def analyze_tone(self, text: str) -> Dict:
        """
        Analyze tone: sarcasm, satire, hyperbole, humor
        Returns scores 0-1 for each
        """
        text_lower = text.lower()
        
        # Sarcasm detection
        sarcasm_score = 0.0
        sarcasm_patterns_matched = []
        for pattern in self.sarcasm_patterns:
            if re.search(pattern, text, re.IGNORECASE):
                sarcasm_score += 0.3
                sarcasm_patterns_matched.append(pattern[:50])
        
        # Check for emoji sarcasm
        sarcasm_emojis = ['🙄', '😒', '😏', '😑', '🤨', '🙃']
        if any(emoji in text for emoji in sarcasm_emojis):
            sarcasm_score += 0.4
            sarcasm_patterns_matched.append("emoji")
        
        # Satire detection (joking about serious topics)
        joke_markers = ['lol', 'lmao', 'haha', '😂', '😆', '🤣']
        crisis_keywords = ['kill', 'suicide', 'die', 'hurt', 'harm']
        has_joke = any(marker in text_lower for marker in joke_markers)
        has_crisis = any(kw in text_lower for kw in crisis_keywords)
        satire_score = 0.7 if (has_joke and has_crisis) else 0.0
        
        # Hyperbole detection
        hyperbole_score = 0.0
        for pattern in self.hyperbole_patterns:
            if re.search(pattern, text, re.IGNORECASE):
                hyperbole_score += 0.4
        
        # Humor score (combination)
        humor_score = 0.0
        if has_joke:
            humor_score += 0.5
        if satire_score > 0:
            humor_score += 0.3
        
        result = {
            'humor_score': min(1.0, humor_score),
            'sarcasm_score': min(1.0, sarcasm_score),
            'satire_score': min(1.0, satire_score),
            'hyperbole_score': min(1.0, hyperbole_score),
        }
        
        # DIAGNOSTIC LOGGING
        print(f"[SEMANTIC_TONE] sarcasm={result['sarcasm_score']:.0%}, "
              f"satire={result['satire_score']:.0%}, "
              f"hyperbole={result['hyperbole_score']:.0%}, "
              f"humor={result['humor_score']:.0%}")
        if sarcasm_patterns_matched:
            print(f"[SEMANTIC_TONE_SARCASM_PATTERNS] {sarcasm_patterns_matched}")
        
        return result
    
    def analyze_crisis_semantics(self, text: str) -> Dict:
        """
        Analyze semantic indicators of crisis (IPMA: Intent/Plan/Method/Access)
        Also detects distress, ideation, recurrence, temporal urgency
        
        Returns scores 0-1 for each indicator
        """
        text_lower = text.lower()
        
        crisis_vocab = self.emotion_vocabulary.get('crisis', {})
        
        scores = {
            'distress': 0.0,
            'ideation': 0.0,
            'intent': 0.0,
            'planning': 0.0,
            'temporal_urgency': 0.0,
            'recurrence': 0.0,
        }
        
        # Check if text contains any crisis keywords
        has_crisis_keyword = any(kw in text_lower for kw in crisis_vocab['keywords'])
        
        # Distress: general suffering
        distress_keywords = ['suffering', 'pain', 'cant take it', 'too much', 'struggling', 'stressed', 'hurting']
        for kw in distress_keywords:
            if kw in text_lower:
                scores['distress'] = min(1.0, scores['distress'] + 0.25)
        
        # Ideation: thinking about harm (combined score)
        ideation_keywords = ['want to', 'thinking about', 'thought of', 'considered', 'contemplating']
        if has_crisis_keyword:
            for kw in ideation_keywords:
                if kw in text_lower:
                    scores['ideation'] = 0.6  # Strong ideation if crisis + thinking keywords
                    break
            
            # If just crisis keyword alone, still some ideation
            if scores['ideation'] == 0:
                scores['ideation'] = 0.35
        
        # Intent: stated intention (stronger scoring)
        intent_indicators = crisis_vocab['indicators'].get('intent', [])
        if has_crisis_keyword:
            for indicator in intent_indicators:
                if indicator in text_lower:
                    scores['intent'] = 0.65  # Strong intent if using explicit language
                    break
            
            # Even without explicit intent phrases, crisis keyword shows some intent
            if scores['intent'] == 0 and 'want to' in text_lower or 'going to' in text_lower:
                scores['intent'] = 0.45
        
        # Planning: evidence of planning (strongest indicator)
        planning_indicators = crisis_vocab['indicators'].get('planning', [])
        for indicator in planning_indicators:
            if indicator in text_lower:
                scores['planning'] = 0.75  # Very high if explicit planning language
                break
        
        # Also check for method + access as proxy for planning
        method_keywords = ['gun', 'knife', 'pills', 'overdose', 'rope', 'poison', 'razor']
        access_keywords = ['i have', 'can get', 'have access', 'available', 'in my room', 'my dad\'s']
        
        has_method = any(kw in text_lower for kw in method_keywords)
        has_access = any(kw in text_lower for kw in access_keywords)
        
        if has_method and has_access:
            scores['planning'] = 0.80  # Very high if both method and access mentioned
        elif has_method:
            scores['planning'] = 0.60  # Moderate if method mentioned
        
        # Temporal urgency (scaled properly) - FIX: ONLY trigger with crisis keywords
        temporal_indicators = crisis_vocab['indicators'].get('temporal', [])
        for indicator in temporal_indicators:
            if indicator in text_lower:
                # FIX 4: Only set temporal urgency if there's actual crisis content
                # Don't flag "today" just because it appears in "My friends surprised me today"
                if has_crisis_keyword:  # ← KEY FIX: Only if crisis keyword present
                    if indicator in ['right now', 'now', 'immediately']:
                        scores['temporal_urgency'] = 0.85  # Very high urgency for immediate language
                    else:
                        scores['temporal_urgency'] = 0.60  # Moderate for other temporal language
                # FIX 4A: Explicit logging to prevent false positives
                # If temporal indicator found but NO crisis keyword, log it for debugging
                else:
                    import sys
                    sys.stderr.write(f"[CRISIS_TEMPORAL_DEBUG] Temporal indicator '{indicator}' found but NO crisis keyword present - temporal_urgency NOT set\n")
                    sys.stderr.flush()
                break
        
        # Recurrence: returning struggle
        recurrence_keywords = ['again', 'still', 'coming back', 'back to', 'relapse', 'returning']
        for kw in recurrence_keywords:
            if kw in text_lower:
                # Check if it's combined with sadness/struggle
                struggle_words = ['sad', 'depressed', 'thoughts', 'feelings', 'struggling', 'struggling', 'pain']
                for word in struggle_words:
                    if word in text_lower:
                        scores['recurrence'] = 0.65
                        break
                
                if scores['recurrence'] == 0:
                    scores['recurrence'] = 0.40  # Moderate even without struggle words
                break
        
        return scores
    
    def detect_negation(self, text: str) -> Dict:
        """
        Detect negation patterns that flip emotion
        Important for handling "I'm not happy" vs "I'm happy"
        """
        negation_found = False
        negation_matches = []
        
        for pattern in self.negation_patterns:
            matches = re.finditer(pattern, text, re.IGNORECASE)
            for match in matches:
                negation_found = True
                # Get surrounding words for context
                start = max(0, match.start() - 20)
                end = min(len(text), match.end() + 50)
                context = text[start:end].strip()
                negation_matches.append(context)
        
        return {
            'negation_detected': negation_found,
            'negation_count': len(negation_matches),
            'negation_contexts': negation_matches[:3],  # First 3 examples
        }
    
    def analyze_context_relevance(self, text: str, conversation_history: List[Dict] = None) -> float:
        """
        How much does this message relate to the conversation context?
        0-1 score for relevance
        """
        if not conversation_history or len(conversation_history) == 0:
            return 0.5  # No history = neutral relevance
        
        # Get recent messages (last 2-3 exchanges)
        recent_count = min(3, len(conversation_history))
        recent_messages = conversation_history[-recent_count:]
        
        # Build recent context
        recent_text = ' '.join([msg.get('user', '') + ' ' + msg.get('ai', '') 
                                for msg in recent_messages])
        
        if not recent_text or len(recent_text.strip()) < 5:
            return 0.5
        
        # Get significant words (> 4 chars, excluding common words)
        common_words = {'what', 'that', 'this', 'from', 'with', 'have', 'been', 'were', 'your', 'just', 'more', 'only', 'very', 'some', 'many'}
        
        def get_keywords(text):
            words = set()
            for w in text.lower().split():
                # Remove punctuation
                w = ''.join(c for c in w if c.isalpha())
                if len(w) > 4 and w not in common_words:
                    words.add(w)
            return words
        
        text_keywords = get_keywords(text)
        recent_keywords = get_keywords(recent_text)
        
        if not text_keywords or not recent_keywords:
            return 0.4  # Limited keywords = lower relevance
        
        # Calculate overlap
        overlap = len(text_keywords & recent_keywords)
        
        # Relevance score based on overlap ratio
        max_keywords = max(len(text_keywords), len(recent_keywords))
        relevance = min(1.0, overlap / max(1, max_keywords * 0.5))  # Generous threshold
        
        return max(0.0, min(1.0, relevance))
    
    def get_dominant_emotion(self, emotion_scores: Dict[str, float]) -> str:
        """Get emotion with highest probability"""
        if not emotion_scores:
            return 'neutral'
        return max(emotion_scores, key=emotion_scores.get)
    
    def get_timestamp(self) -> str:
        """Get ISO timestamp"""
        from datetime import datetime
        return datetime.now().isoformat()

    def analyze_with_vader(self, text: str) -> Dict:
        """
        PARALLEL sentiment analysis using VADER (Valence Aware Dictionary and sEntiment Reasoner)
        Returns raw VADER scores for comparison with keyword system.
        NOT YET used for actual emotion detection - observational only.
        """
        try:
            from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer
            analyzer = SentimentIntensityAnalyzer()
            vader_scores = analyzer.polarity_scores(text)
            
            # VADER returns: positive, negative, neutral, compound (-1 to +1)
            # compound = normalized score combining pos/neg/neu
            return {
                'positive': vader_scores['pos'],
                'negative': vader_scores['neg'],
                'neutral': vader_scores['neu'],
                'compound': vader_scores['compound']
            }
        except ImportError as e:
            # VADER not available - return safe defaults
            print(f"[VADER_UNAVAILABLE] Import error: {e} - returning safe defaults")
            return {
                'positive': 0.0,
                'negative': 0.0,
                'neutral': 1.0,
                'compound': 0.0
            }


def minimal_semantic_check(understanding: dict) -> bool:
    """
    Validate that Ollama understanding JSON has required keys and reasonable values.
    Used by ollama_understand_message() to validate response structure.
    
    Args:
        understanding: Dict from Ollama understanding
    
    Returns:
        True if valid, False otherwise
    """
    required_keys = ['situation', 'topic', 'intent', 'emotional_meaning', 
                    'important_details', 'sarcasm_or_figurative', 'is_question', 
                    'severity_indicator']
    
    # Check all required keys exist
    for key in required_keys:
        if key not in understanding:
            print(f"[MINIMAL_SEMANTIC_CHECK] Missing key: {key}")
            return False
    
    # Basic type checking
    if not isinstance(understanding.get('situation'), str):
        return False
    if not isinstance(understanding.get('topic'), str):
        return False
    if not isinstance(understanding.get('intent'), str):
        return False
    if not isinstance(understanding.get('emotional_meaning'), str):
        return False
    if not isinstance(understanding.get('important_details'), list):
        return False
    if not isinstance(understanding.get('sarcasm_or_figurative'), bool):
        return False
    if not isinstance(understanding.get('is_question'), bool):
        return False
    if not isinstance(understanding.get('severity_indicator'), str):
        return False
    
    return True


def analyze_semantic_layer(text: str, conversation_history: List[Dict] = None) -> Dict:
    """
    Convenience function to run semantic analysis
    Returns structured JSON ready for Laravel integration
    """
    analyzer = SemanticAnalyzer()
    return analyzer.analyze(text, conversation_history)

