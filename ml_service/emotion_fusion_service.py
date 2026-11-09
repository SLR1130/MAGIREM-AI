#!/usr/bin/env python3
"""
TASK 9 - Emotion Fusion Service
Implements 10-case decision policy for fusing multiple emotion signals
Designed to run in shadow mode initially, with future authority transition

Author: Emotion Fusion Architecture (Task 9)
Date: October 9, 2026
Status: Production-Ready (Shadow Mode)
"""

from dataclasses import dataclass, field, asdict
from typing import Dict, List, Optional, Set
from enum import Enum
import logging

# Configure logging
logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG)


class EmotionLabel(str, Enum):
    """Canonical 6-class emotion taxonomy"""
    SADNESS = "sadness"
    JOY = "joy"
    ANXIETY = "anxiety"
    ANGER = "anger"
    NEUTRAL = "neutral"
    GRIEF = "grief"


class AgreementPattern(str, Enum):
    """Signal agreement patterns"""
    FULL_AGREEMENT = "full_agreement"
    PARTIAL = "partial"
    CONFLICT = "conflict"


@dataclass
class SignalContribution:
    """How a single signal contributed to fusion"""
    emotion: str
    confidence: float
    used: bool  # Whether this signal was used in final decision
    weight: float  # 0.0-1.0 influence weight in fusion


@dataclass
class FusionResult:
    """Complete fusion result with diagnostics"""
    # Primary output
    final_emotion: str
    confidence: float
    reason: str
    
    # Metadata
    uncertain_flag: bool = False
    
    # Source contributions (how each signal influenced)
    source_contributions: Dict[str, Dict] = field(default_factory=dict)
    
    # Signal agreement
    agreement_pattern: str = AgreementPattern.FULL_AGREEMENT
    agreements: List[str] = field(default_factory=list)
    disagreements: List[str] = field(default_factory=list)
    
    # Contextual metadata
    language: str = ""
    negation_detected: bool = False
    negation_type: Optional[str] = None  # simple, temporal, implicit
    sarcasm_detected: bool = False
    sarcasm_confidence: float = 0.0
    intent: str = ""
    
    # For shadow mode (added to response if enabled)
    fusion_shadow_emotion: str = ""
    fusion_shadow_confidence: float = 0.0
    fusion_shadow_reason: str = ""
    
    # Full diagnostics
    diagnostic_metadata: Dict = field(default_factory=dict)


class EmotionFusionService:
    """
    Fuses multiple emotion signals into unified final emotion using 10-case policy
    
    Signals fused:
    1. Semantic Analysis (5 classes: sadness, happiness, anxiety, anger, neutral)
    2. Unified Analysis (6 classes + context metadata)
    3. Trained Shadow Model (6 classes with softmax probabilities)
    4. VADER Sentiment (independent polarity analysis)
    5. Negation Detection (boolean + type)
    6. Sarcasm/Tone (scores for sarcasm, satire, humor, hyperbole)
    7. Language Detection (english, tagalog, taglish)
    8. Intent Classification (statement, question, suggestion, command)
    
    Decision Policy: 10 cases covering signal agreement patterns
    """
    
    # Signal reliability configuration
    SIGNAL_WEIGHTS = {
        'semantic': 1.0,
        'unified': 1.2,       # Slightly higher: consolidation layer
        'trained': 1.0,
        'vader': 0.8,         # Lower: observational only
    }
    
    # Known weak subsets (from Task 7 metrics)
    WEAK_SUBSETS = {
        'trained': {
            'english_anger': 0.0,      # F1 = 0.0
            'taglish_sadness': 0.40,   # F1 = 0.40
            'tagalog_neutral': 0.44,   # F1 = 0.44
        }
    }
    
    # Language-specific keyword normalization
    NORMALIZATION_KEYWORDS = {
        'english': {
            'anxiety': ['stressed', 'worried', 'anxious', 'nervous', 'afraid', 'scared', 'uncertain'],
            'sadness': ['sad', 'down', 'depressed', 'lonely', 'miserable', 'unhappy', 'discouraged'],
            'grief': ['died', 'passed', 'death', 'grandmother', 'father', 'mother', 'miss', 'funeral'],
            'anger': ['furious', 'angry', 'mad', 'frustrated', 'enraged', 'livid', 'irritated'],
            'joy': ['happy', 'great', 'wonderful', 'amazing', 'excited', 'love', 'excellent'],
        },
        'tagalog': {
            'anxiety': ['kinakabahan', 'alalahanin', 'gulat', 'takot', 'nag-aalala'],
            'sadness': ['malungkot', 'nawalan', 'wala ang gana', 'lungkot', 'downhearted'],
            'grief': ['namatay', 'patay', 'kamatayan', 'pumanaw', 'miss', 'loss'],
            'anger': ['galit', 'nakakainis', 'galit na galit', 'frustrated'],
            'joy': ['masaya', 'saya', 'happy', 'excellent'],
        },
        'taglish': {
            'anxiety': ['anxious', 'stressed', 'worried', 'kinakabahan', 'alalahanin'],
            'sadness': ['sad', 'malungkot', 'exhausted', 'pagod', 'tired', 'wala ang gana'],
            'grief': ['passed away', 'namatay', 'died', 'miss', 'loss'],
            'anger': ['angry', 'furious', 'galit', 'mad', 'frustrated'],
            'joy': ['happy', 'masaya', 'great', 'wonderful'],
        }
    }
    
    def __init__(self):
        """Initialize fusion service"""
        logger.info("Emotion Fusion Service initialized")
    
    def fuse(self,
             message: str,
             signals: Dict,
             conversation_history: Optional[List[Dict]] = None) -> FusionResult:
        """
        Fuse multiple emotion signals into final emotion using decision policy
        
        Args:
            message: User's current message
            signals: Dict containing all signals {
                'semantic': {...emotion, confidence, tone, negation},
                'unified': {...emotion, confidence, sarcasm, negation, intent, language},
                'trained': {...emotion, confidence, probabilities},
                'vader': {...compound, pos, neg, neu},
            }
            conversation_history: Previous messages for context
        
        Returns:
            FusionResult with final emotion, confidence, reasoning, and diagnostics
        """
        
        logger.debug(f"Starting fusion for message: {message[:50]}...")
        
        # Extract signal information
        semantic_signal = self._extract_signal(signals, 'semantic')
        unified_signal = self._extract_signal(signals, 'unified')
        trained_signal = self._extract_signal(signals, 'trained')
        vader_signal = self._extract_signal(signals, 'vader')
        
        # Normalize emotions to canonical 6-class taxonomy
        semantic_emotion = self._normalize_emotion(semantic_signal.get('emotion'), 'semantic')
        unified_emotion = self._normalize_emotion(unified_signal.get('emotion'), 'unified')
        trained_emotion = self._normalize_emotion(trained_signal.get('emotion'), 'trained')
        
        # Extract contextual metadata
        language = unified_signal.get('language', 'english')
        negation_detected = unified_signal.get('negation_detected', False)
        negation_type = unified_signal.get('negation_type')
        sarcasm_detected = unified_signal.get('sarcasm_detected', False)
        sarcasm_confidence = unified_signal.get('sarcasm_confidence', 0.0)
        intent = unified_signal.get('intent', 'statement')
        
        # Classify signal reliability
        signal_reliability = self._classify_reliability(
            message, language, semantic_signal, unified_signal, trained_signal, vader_signal
        )
        
        # Apply decision policy based on agreement pattern
        final_emotion, confidence, reason, agreement_pattern, agreements, disagreements = \
            self._apply_fusion_rules(
                message, language,
                semantic_emotion, semantic_signal.get('confidence', 0.0),
                unified_emotion, unified_signal.get('confidence', 0.0),
                trained_emotion, trained_signal.get('confidence', 0.0),
                vader_signal,
                signal_reliability,
                negation_detected, negation_type,
                sarcasm_detected, conversation_history
            )
        
        # Track signal contributions
        source_contributions = {
            'semantic': {
                'emotion': semantic_emotion,
                'confidence': semantic_signal.get('confidence', 0.0),
                'used': semantic_emotion in [final_emotion, 'neutral'],
                'weight': signal_reliability.get('semantic_weight', 0.0)
            },
            'unified': {
                'emotion': unified_emotion,
                'confidence': unified_signal.get('confidence', 0.0),
                'used': unified_emotion in [final_emotion, 'neutral'],
                'weight': signal_reliability.get('unified_weight', 0.0)
            },
            'trained': {
                'emotion': trained_emotion,
                'confidence': trained_signal.get('confidence', 0.0),
                'used': trained_emotion in [final_emotion, 'neutral'],
                'weight': signal_reliability.get('trained_weight', 0.0)
            },
            'vader': {
                'emotion': self._vader_to_emotion(vader_signal),
                'confidence': abs(vader_signal.get('compound', 0.0)),
                'used': False,  # VADER is observational, not primary
                'weight': signal_reliability.get('vader_weight', 0.0)
            }
        }
        
        # Determine if uncertain
        uncertain_flag = agreement_pattern == AgreementPattern.CONFLICT or confidence < 0.60
        
        # Build diagnostic metadata
        diagnostic_metadata = {
            'signals': signals,
            'signal_reliability': signal_reliability,
            'agreement_analysis': {
                'pattern': agreement_pattern,
                'agreements': agreements,
                'disagreements': disagreements,
            }
        }
        
        # Create fusion result
        result = FusionResult(
            final_emotion=final_emotion,
            confidence=confidence,
            reason=reason,
            uncertain_flag=uncertain_flag,
            source_contributions=source_contributions,
            agreement_pattern=agreement_pattern,
            agreements=agreements,
            disagreements=disagreements,
            language=language,
            negation_detected=negation_detected,
            negation_type=negation_type,
            sarcasm_detected=sarcasm_detected,
            sarcasm_confidence=sarcasm_confidence,
            intent=intent,
            fusion_shadow_emotion=final_emotion,
            fusion_shadow_confidence=confidence,
            fusion_shadow_reason=reason,
            diagnostic_metadata=diagnostic_metadata,
        )
        
        logger.debug(f"Fusion complete: {final_emotion} ({confidence:.1%}) - {reason}")
        
        return result
    
    def _extract_signal(self, signals: Dict, signal_name: str) -> Dict:
        """Safely extract signal from signals dict"""
        signal = signals.get(signal_name, {})
        if signal.get('error'):
            logger.warning(f"Signal {signal_name} had error: {signal.get('error')}")
            return {}
        return signal
    
    def _normalize_emotion(self, emotion: Optional[str], source: str) -> str:
        """Normalize emotion from source to canonical 6-class taxonomy"""
        if not emotion:
            return EmotionLabel.NEUTRAL.value
        
        emotion_lower = emotion.lower().strip()
        
        # Semantic analysis uses 5 classes: sadness, happiness, anxiety, anger, neutral
        if source == 'semantic':
            mapping = {
                'happiness': EmotionLabel.JOY.value,
                'sadness': EmotionLabel.SADNESS.value,
                'anxiety': EmotionLabel.ANXIETY.value,
                'anger': EmotionLabel.ANGER.value,
                'neutral': EmotionLabel.NEUTRAL.value,
            }
            return mapping.get(emotion_lower, EmotionLabel.NEUTRAL.value)
        
        # Unified and Trained already use 6 classes
        if emotion_lower in [e.value for e in EmotionLabel]:
            return emotion_lower
        
        return EmotionLabel.NEUTRAL.value
    
    def _classify_reliability(self, message: str, language: str,
                             semantic_signal: Dict, unified_signal: Dict,
                             trained_signal: Dict, vader_signal: Dict) -> Dict:
        """
        Classify reliability of each signal for this message
        Returns weights to apply (0.0-1.0)
        """
        
        reliability = {
            'semantic_weight': 1.0,
            'unified_weight': 1.0,
            'trained_weight': 1.0,
            'vader_weight': 1.0,
            'semantic_quality': 'moderate',
            'unified_quality': 'moderate',
            'trained_quality': 'moderate',
            'vader_quality': 'low',
        }
        
        # Semantic reliability depends on language
        # Tagalog/Taglish are non-English languages
        is_non_english = language.lower() not in ['english']
        
        if is_non_english:
            # Non-English: semantic is weak due to language gap
            reliability['semantic_weight'] = 0.6
            reliability['semantic_quality'] = 'weak'
            logger.debug(f"Semantic downweighted for language: {language}")
        else:
            reliability['semantic_quality'] = 'strong'
        
        # Unified consolidates semantic, typically reliable
        unified_conf = unified_signal.get('confidence', 0.0)
        if unified_conf > 0.8:
            reliability['unified_quality'] = 'strong'
        elif unified_conf < 0.5:
            reliability['unified_weight'] = 0.7
            reliability['unified_quality'] = 'weak'
        
        # Trained model reliability depends on subset
        trained_emotion = trained_signal.get('emotion', '').lower()
        trained_conf = trained_signal.get('confidence', 0.0)
        
        # Check for known weak subsets
        weak_key = f"{language.lower()}_{trained_emotion}"
        if weak_key in self.WEAK_SUBSETS['trained']:
            weak_f1 = self.WEAK_SUBSETS['trained'][weak_key]
            if weak_f1 < 0.5:
                reliability['trained_weight'] = 0.6
                reliability['trained_quality'] = 'weak'
                logger.debug(f"Trained downweighted for weak subset: {weak_key} (F1={weak_f1})")
        
        if trained_conf < 0.4:
            reliability['trained_weight'] = 0.7
            reliability['trained_quality'] = 'weak'
        
        # VADER is always observational
        vader_compound = vader_signal.get('compound', 0.0)
        if abs(vader_compound) > 0.7:
            # Strong VADER signal
            reliability['vader_weight'] = 0.8
            reliability['vader_quality'] = 'moderate'
        elif abs(vader_compound) > 0.3:
            reliability['vader_weight'] = 0.6
            reliability['vader_quality'] = 'low'
        else:
            reliability['vader_weight'] = 0.3
            reliability['vader_quality'] = 'inconclusive'
        
        return reliability
    
    def _apply_fusion_rules(self,
                           message: str, language: str,
                           semantic_emotion: str, semantic_conf: float,
                           unified_emotion: str, unified_conf: float,
                           trained_emotion: str, trained_conf: float,
                           vader_signal: Dict,
                           signal_reliability: Dict,
                           negation_detected: bool, negation_type: Optional[str],
                           sarcasm_detected: bool,
                           conversation_history: Optional[List[Dict]] = None) \
            -> tuple:
        """
        Apply 10-case fusion decision policy
        
        Returns: (final_emotion, confidence, reason, agreement_pattern, agreements, disagreements)
        """
        
        # Case E: Negation - Handle early, before other cases
        # Note: negation_type may not always be provided, so check negation_detected alone
        # Only apply negation if it actually negates the emotional predicate (not just an action)
        if negation_detected:  # Removed: and negation_type == 'simple'
            final_emotion, confidence, reason = self._handle_negation_simple(
                message, semantic_emotion, unified_emotion, trained_emotion,
                semantic_conf, unified_conf, trained_conf
            )
            # Check if negation handler found that negation applies to the emotion
            if final_emotion is not None:  # Returns None if negation doesn't apply to emotion
                return final_emotion, confidence, reason, AgreementPattern.CONFLICT, [], ['negation_applied']
        
        # EARLY CHECK: Case G - Sadness vs Grief (check before agreement analysis)
        # This ensures death keywords trigger grief upgrade even if signals disagree
        sadness_grief_emotions = {EmotionLabel.SADNESS.value, EmotionLabel.GRIEF.value}
        if set([semantic_emotion, unified_emotion, trained_emotion]) & sadness_grief_emotions:
            final_emotion, confidence, reason = self._distinguish_grief_sadness(
                message, semantic_emotion, unified_emotion, trained_emotion,
                semantic_conf, unified_conf, trained_conf
            )
            if final_emotion and final_emotion != EmotionLabel.NEUTRAL.value:
                # Return early with agreement pattern as-is
                agreements = []
                if semantic_emotion == unified_emotion:
                    agreements.append('semantic_unified')
                agreement_pattern = AgreementPattern.FULL_AGREEMENT if len(agreements) > 0 else AgreementPattern.PARTIAL
                return final_emotion, confidence, reason, agreement_pattern, agreements, []
        
        # Analyze signal agreement
        agreements = []
        disagreements = []
        
        if semantic_emotion == unified_emotion:
            agreements.append('semantic_unified')
        else:
            disagreements.append('semantic_unified')
        
        if semantic_emotion == trained_emotion:
            agreements.append('semantic_trained')
        else:
            disagreements.append('semantic_trained')
        
        if unified_emotion == trained_emotion:
            agreements.append('unified_trained')
        else:
            disagreements.append('unified_trained')
        
        # Determine agreement pattern
        if len(agreements) >= 2:
            agreement_pattern = AgreementPattern.FULL_AGREEMENT
        elif len(agreements) == 1:
            agreement_pattern = AgreementPattern.PARTIAL
        else:
            agreement_pattern = AgreementPattern.CONFLICT
        
        # Case A: Full Agreement (all 3 primary signals agree)
        if agreement_pattern == AgreementPattern.FULL_AGREEMENT and len(agreements) >= 2:
            final_emotion = semantic_emotion
            confidence = min(0.95, (semantic_conf + unified_conf + trained_conf) / 3 + 0.15)
            reason = f"Case A: Full agreement. All signals agree on {final_emotion}"
            return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case B: Semantic + Unified Agree, Trained Differs
        if 'semantic_unified' in agreements and 'unified_trained' not in agreements:
            trained_weight = signal_reliability.get('trained_weight', 1.0)
            
            # Special case: If semantic/unified are neutral but language is non-English,
            # AND trained has emotional signal, might be language gap
            language_gap_neutral = (
                semantic_emotion == EmotionLabel.NEUTRAL.value and 
                unified_emotion == EmotionLabel.NEUTRAL.value and 
                trained_emotion != EmotionLabel.NEUTRAL.value and
                language.lower() not in ['english']
            )
            
            if trained_weight < 0.7 or language_gap_neutral:
                # Sub-case B1: Trained weak on this subset, or language gap suggests neutral is wrong
                final_emotion = unified_emotion
                confidence = min(0.90, (unified_conf + semantic_conf) / 2 + 0.10)
                reason = f"Case B1: Trained weak or language gap. Trust unified/semantic: {final_emotion}"
            else:
                # Sub-case B2: Trained strong, but differs from semantic/unified
                # Investigate negation/sarcasm
                if sarcasm_detected:
                    final_emotion = trained_emotion
                    confidence = 0.65
                    reason = f"Case B2: Sarcasm detected with disagreement. Trust trained (with caution): {final_emotion}"
                else:
                    final_emotion = unified_emotion
                    confidence = 0.70
                    reason = f"Case B2: Trained differs from semantic/unified. Trust unified: {final_emotion}"
            
            return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case C: Trained Strong, Semantic/Unified Weak
        if ('semantic_trained' in agreements or 'unified_trained' in agreements) and \
           signal_reliability.get('semantic_weight', 1.0) < 0.8 and \
           signal_reliability.get('trained_weight', 1.0) >= 0.8:
            final_emotion = trained_emotion
            confidence = min(0.90, trained_conf + 0.15)
            reason = f"Case C: Trained strong, semantic/unified weak (language gap). Trust trained: {final_emotion}"
            return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case C Alternative: One signal strong, others very weak/neutral (language gap scenario)
        if signal_reliability.get('semantic_weight', 1.0) < 0.7 and \
           trained_emotion != EmotionLabel.NEUTRAL.value and \
           trained_conf >= 0.40:
            final_emotion = trained_emotion
            confidence = 0.70
            reason = f"Case C alt: Semantic weak (language gap), trained emotional. Trust trained: {final_emotion}"
            return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case H: Sadness vs Anxiety Distinction
        sadness_anxiety_emotions = {EmotionLabel.SADNESS.value, EmotionLabel.ANXIETY.value}
        if set([semantic_emotion, unified_emotion, trained_emotion]) & sadness_anxiety_emotions:
            final_emotion, confidence, reason = self._distinguish_sadness_anxiety(
                message, semantic_emotion, unified_emotion, trained_emotion,
                semantic_conf, unified_conf, trained_conf
            )
            if final_emotion and final_emotion != EmotionLabel.NEUTRAL.value:
                return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case F: Neutral vs Emotional Disagreement
        if EmotionLabel.NEUTRAL.value in [semantic_emotion, unified_emotion, trained_emotion]:
            final_emotion, confidence, reason = self._handle_neutral_conflict(
                message, semantic_emotion, unified_emotion, trained_emotion,
                semantic_conf, unified_conf, trained_conf, vader_signal, signal_reliability
            )
            if final_emotion and final_emotion != EmotionLabel.NEUTRAL.value:
                return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Case D: All Signals Disagree Significantly
        if agreement_pattern == AgreementPattern.CONFLICT:
            final_emotion = EmotionLabel.NEUTRAL.value
            confidence = 0.45
            reason = f"Case D: All signals conflict. Defaulting to neutral (uncertain)"
            return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
        
        # Default: Use unified emotion as final authority
        final_emotion = unified_emotion
        confidence = unified_conf
        reason = f"Default: Using unified analysis: {final_emotion}"
        
        return final_emotion, confidence, reason, agreement_pattern, agreements, disagreements
    
    def _handle_negation_simple(self, message: str,
                                semantic_emotion: str, unified_emotion: str, trained_emotion: str,
                                semantic_conf: float, unified_conf: float, trained_conf: float) -> tuple:
        """
        Handle simple negation: "I'm not X"
        Negation denies the emotional predicate.
        
        Rules:
        - "I'm not sad" → neutral (sadness denied)
        - "I'm not sad, I'm just exhausted" → neutral (sadness denied, exhaustion is physical state not emotion)
        - "I'm not worried" → neutral (anxiety denied)
        - "I don't want to go because I'm angry" → anger still present (negation applies to action, not emotion)
        - "I don't want to do anything" → Check if sadness is core message (don't apply negation)
        
        Returns: (final_emotion, confidence, reason)
        """
        
        # IMPORTANT: Only apply negation if it directly negates an emotional predicate
        # NOT if negation applies to an action/consequence
        
        message_lower = message.lower()
        
        # Check if the sadness/emotion is the PRIMARY message, not the negated part
        # Example: "I don't want to do anything" - sadness is primary (anhedonia)
        # vs "I'm not sad" - negation IS negating the emotion
        
        # Heuristic: If emotional predicate appears BEFORE negation in sentence, 
        # the emotion is primary and negation doesn't negate it
        # "I feel empty and don't want..." → sadness first, negation of action
        # "I'm not sad, I'm exhausted" → negation first, then alternative emotion
        
        sad_words = ['sad', 'empty', 'down', 'depressed', 'malungkot', 'lungkot']
        anxiety_words = ['anxious', 'worried', 'stressed', 'kinakabahan', 'kabado', 'alalahanin']
        anger_words = ['angry', 'furious', 'galit', 'nakakainis']
        joy_words = ['happy', 'joyful', 'masaya']
        
        # Find position of first emotional word
        first_emotion_pos = float('inf')
        emotion_type = None
        
        for word in sad_words:
            pos = message_lower.find(word)
            if pos >= 0 and pos < first_emotion_pos:
                first_emotion_pos = pos
                emotion_type = 'sadness'
        
        for word in anxiety_words:
            pos = message_lower.find(word)
            if pos >= 0 and pos < first_emotion_pos:
                first_emotion_pos = pos
                emotion_type = 'anxiety'
        
        for word in anger_words:
            pos = message_lower.find(word)
            if pos >= 0 and pos < first_emotion_pos:
                first_emotion_pos = pos
                emotion_type = 'anger'
        
        for word in joy_words:
            pos = message_lower.find(word)
            if pos >= 0 and pos < first_emotion_pos:
                first_emotion_pos = pos
                emotion_type = 'joy'
        
        # Find position of first negation word
        negation_words = ['not', "don't", "don't", 'never', 'no ', 'cannot', "can't", "isn't", "aren't",
                         'hindi', 'hindi naman', 'di', 'di naman', 'wala']  # Tagalog negation particles
        first_negation_pos = float('inf')
        
        for word in negation_words:
            pos = message_lower.find(word)
            if pos >= 0 and pos < first_negation_pos:
                first_negation_pos = pos
        
        # If emotion word appears BEFORE negation, negation likely modifies action, not emotion
        # Example: "I feel empty and don't want to do anything"
        #   "feel" at pos ~2, "don't" at pos ~24 → emotion first, negation of action
        if first_emotion_pos < first_negation_pos:
            # Emotion is mentioned first - likely primary, negation modifies action
            # Don't apply emotional negation
            return None, 0, ""
        
        # If negation appears BEFORE or near emotion word, negation likely negates the emotion
        # Example: "I'm not sad, I'm just exhausted"
        #   "not" at pos ~7, "sad" at pos ~10 → negation first/near, negates emotion
        
        # Apply negation only if it directly negates the detected emotion
        if semantic_emotion == EmotionLabel.SADNESS.value or unified_emotion == EmotionLabel.SADNESS.value:
            return EmotionLabel.NEUTRAL.value, 0.60, "Negation: sadness denied -> neutral"
        
        if semantic_emotion == EmotionLabel.ANXIETY.value or unified_emotion == EmotionLabel.ANXIETY.value:
            return EmotionLabel.NEUTRAL.value, 0.60, "Negation: anxiety denied -> neutral"
        
        if semantic_emotion == EmotionLabel.ANGER.value or unified_emotion == EmotionLabel.ANGER.value:
            return EmotionLabel.NEUTRAL.value, 0.60, "Negation: anger denied -> neutral"
        
        if semantic_emotion == EmotionLabel.JOY.value or unified_emotion == EmotionLabel.JOY.value:
            return EmotionLabel.NEUTRAL.value, 0.60, "Negation: joy denied -> neutral"
        
        # Default: return None to indicate negation shouldn't apply
        return None, 0, ""
    
    def _distinguish_grief_sadness(self, message: str,
                                   semantic_emotion: str, unified_emotion: str, trained_emotion: str,
                                   semantic_conf: float, unified_conf: float, trained_conf: float) -> tuple:
        """
        Distinguish between grief (bereavement) and sadness
        Death keywords: died, passed, death, grandmother, father, mother, funeral, loss
        
        Returns: (final_emotion, confidence, reason) or (None, 0, "")
        """
        
        death_keywords = ['died', 'passed', 'death', 'grandmother', 'grandfather', 
                         'father', 'mother', 'parent', 'funeral', 'bereavement', 'miss',
                         'namatay', 'patay', 'kamatayan', 'pumanaw', 'miss']
        
        has_death_context = any(kw in message.lower() for kw in death_keywords)
        
        if has_death_context:
            # Death context present
            if trained_emotion == EmotionLabel.GRIEF.value:
                return EmotionLabel.GRIEF.value, 0.85, "Death context + trained grief signal → grief (0.85)"
            elif semantic_emotion == EmotionLabel.SADNESS.value or unified_emotion == EmotionLabel.SADNESS.value:
                # Upgrade sadness to grief in death context
                return EmotionLabel.GRIEF.value, 0.70, "Death context + sadness signal → upgrade to grief (0.70)"
            else:
                # Other signals, but death context clear
                return EmotionLabel.GRIEF.value, 0.60, "Death context detected → grief (uncertain signals)"
        
        # No death context: keep as sadness
        if semantic_emotion == EmotionLabel.SADNESS.value or unified_emotion == EmotionLabel.SADNESS.value:
            return EmotionLabel.SADNESS.value, max(semantic_conf, unified_conf), \
                   f"No death context → keep sadness"
        
        return None, 0, ""
    
    def _distinguish_sadness_anxiety(self, message: str,
                                     semantic_emotion: str, unified_emotion: str, trained_emotion: str,
                                     semantic_conf: float, unified_conf: float, trained_conf: float) -> tuple:
        """
        Distinguish between sadness (low mood) and anxiety (worry/stress)
        
        Discriminators:
        - "stressed", "worried", "uncertain", "afraid" → anxiety
        - "sad", "down", "empty", "lonely", "hopeless" → sadness
        - "exhausted", "tired", "drained" → sadness (anhedonia indicator)
        
        Returns: (final_emotion, confidence, reason) or (None, 0, "")
        """
        
        message_lower = message.lower()
        
        anxiety_keywords = ['stressed', 'worried', 'anxious', 'nervous', 'afraid', 
                           'scared', 'uncertain', 'fear', 'kinakabahan', 'alalahanin']
        sadness_keywords = ['sad', 'down', 'empty', 'lonely', 'hopeless', 'discouraged',
                           'malungkot', 'nawalan', 'wala ang gana']
        exhaustion_keywords = ['exhausted', 'tired', 'drained', 'pagod', 'burned out', 'burnt out']
        
        has_anxiety = any(kw in message_lower for kw in anxiety_keywords)
        has_sadness = any(kw in message_lower for kw in sadness_keywords)
        has_exhaustion = any(kw in message_lower for kw in exhaustion_keywords)
        
        if has_anxiety and not has_sadness:
            return EmotionLabel.ANXIETY.value, 0.75, "Anxiety keywords present → anxiety"
        
        if has_sadness and not has_anxiety:
            return EmotionLabel.SADNESS.value, 0.75, "Sadness keywords present → sadness"
        
        if has_exhaustion:
            # Exhaustion typically maps to sadness (anhedonia), not anxiety
            return EmotionLabel.SADNESS.value, 0.70, "Exhaustion/fatigue keywords → sadness (anhedonia)"
        
        if has_anxiety and has_sadness:
            # Both present: check signal agreement
            if trained_emotion in [EmotionLabel.ANXIETY.value, EmotionLabel.SADNESS.value]:
                return trained_emotion, 0.65, "Both keywords present, using trained signal"
            return semantic_emotion, 0.60, "Both keywords present, ambiguous"
        
        return None, 0, ""
    
    def _handle_neutral_conflict(self, message: str,
                                 semantic_emotion: str, unified_emotion: str, trained_emotion: str,
                                 semantic_conf: float, unified_conf: float, trained_conf: float,
                                 vader_signal: Dict, signal_reliability: Dict) -> tuple:
        """
        Handle neutral vs emotional disagreement
        Investigate: Is genuine neutral, or is semantic/trained missing language?
        
        Returns: (final_emotion, confidence, reason) or (None, 0, "")
        """
        
        # Count neutral vs emotional signals
        emotional_signals = []
        neutral_signals = []
        
        if semantic_emotion == EmotionLabel.NEUTRAL.value:
            neutral_signals.append(('semantic', semantic_conf))
        else:
            emotional_signals.append(('semantic', semantic_emotion, semantic_conf))
        
        if unified_emotion == EmotionLabel.NEUTRAL.value:
            neutral_signals.append(('unified', unified_conf))
        else:
            emotional_signals.append(('unified', unified_emotion, unified_conf))
        
        if trained_emotion == EmotionLabel.NEUTRAL.value:
            neutral_signals.append(('trained', trained_conf))
        else:
            emotional_signals.append(('trained', trained_emotion, trained_conf))
        
        # If trained + VADER both emotional → Trust them (language gap scenario)
        vader_emotion = self._vader_to_emotion(vader_signal)
        if len(emotional_signals) >= 2 and vader_emotion != EmotionLabel.NEUTRAL.value:
            # Multiple signals emotional, likely language gap
            primary_emotion = emotional_signals[0][1] if emotional_signals else EmotionLabel.NEUTRAL.value
            confidence = 0.65
            reason = f"Language gap: semantic neutral, but trained + VADER emotional → {primary_emotion}"
            return primary_emotion, confidence, reason
        
        # Default: Genuinely neutral or indeterminate
        return None, 0, ""
    
    def _vader_to_emotion(self, vader_signal: Dict) -> str:
        """Map VADER compound score to emotion"""
        compound = vader_signal.get('compound', 0.0)
        
        if compound >= 0.5:
            return EmotionLabel.JOY.value
        elif compound <= -0.5:
            return EmotionLabel.SADNESS.value  # Conservative: negative to sadness, not anger
        else:
            return EmotionLabel.NEUTRAL.value
    
    def to_dict(self, result: FusionResult) -> Dict:
        """Convert FusionResult to dictionary for JSON serialization"""
        return asdict(result)


# Module-level convenience functions for integration

_fusion_service: Optional[EmotionFusionService] = None


def get_fusion_service() -> EmotionFusionService:
    """Get or create singleton fusion service"""
    global _fusion_service
    if _fusion_service is None:
        _fusion_service = EmotionFusionService()
    return _fusion_service


def fuse_emotions(message: str, signals: Dict,
                 conversation_history: Optional[List[Dict]] = None) -> FusionResult:
    """
    Convenience function for emotion fusion
    
    Usage:
        from emotion_fusion_service import fuse_emotions
        
        result = fuse_emotions(message, {
            'semantic': semantic_result,
            'unified': unified_result,
            'trained': trained_pred,
            'vader': vader_scores,
        })
        
        print(f"Final emotion: {result.final_emotion} ({result.confidence:.1%})")
    """
    service = get_fusion_service()
    return service.fuse(message, signals, conversation_history)


if __name__ == "__main__":
    # Quick test
    logging.basicConfig(level=logging.DEBUG)
    
    service = EmotionFusionService()
    
    test_signals = {
        'semantic': {
            'emotion': 'sadness',
            'confidence': 1.0,
        },
        'unified': {
            'emotion': 'sadness',
            'confidence': 1.0,
            'language': 'english',
            'negation_detected': True,
            'negation_type': 'simple',
        },
        'trained': {
            'emotion': 'sadness',
            'confidence': 0.46,
        },
        'vader': {
            'compound': 0.0139,
        }
    }
    
    result = service.fuse("I'm not sad, I'm just exhausted.", test_signals)
    
    print(f"\nFusion Result:")
    print(f"  Final emotion: {result.final_emotion}")
    print(f"  Confidence: {result.confidence:.1%}")
    print(f"  Reason: {result.reason}")
    print(f"  Uncertain: {result.uncertain_flag}")
