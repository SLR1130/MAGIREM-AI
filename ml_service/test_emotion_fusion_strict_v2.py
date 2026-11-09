#!/usr/bin/env python3
"""
TASK 9 - STRICT BEHAVIORAL VALIDATION V2 (CORRECTED)
Corrected validator using CORRECT signal adapter for EmotionFusionService

PREVIOUS ISSUE (V1): Used wrong signal keys ('raw_emotion', 'normalized_emotion')
CURRENT FIX (V2): Uses correct signal keys ('emotion') as expected by fuse()

This validator uses the EXACT same 18 test messages and expected labels as the original V1.
The ONLY change is the signal adapter format - removed the defect.

NO fabricated signals. Uses real semantic_analysis, unified_analysis, trained model, VADER.
"""

import sys
import json
from pathlib import Path
from typing import Dict, Any, Optional

sys.path.insert(0, str(Path(__file__).parent))

# Real signal sources from MindCare pipeline
from semantic_analysis import analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer
from emotion_fusion_service import EmotionFusionService


# FROZEN TEST CASES - IDENTICAL TO V1
VALIDATION_MESSAGES = {
    # ENGLISH (E1-E6)
    'E1': {
        'text': "I'm stressed because of school.",
        'expected_emotion': 'anxiety',
        'category': 'anxiety',
    },
    'E2': {
        'text': "I'm not sad, I'm just exhausted.",
        'expected_emotion': 'neutral',
        'category': 'negation',
        'constraint': 'MUST NOT be sadness',
    },
    'E3': {
        'text': "I'm furious about what they did.",
        'expected_emotion': 'anger',
        'category': 'anger',
    },
    'E4': {
        'text': "I feel empty and don't want to do anything today.",
        'expected_emotion': 'sadness',
        'category': 'sadness',
    },
    'E5': {
        'text': "My grandmother passed away and I really miss her.",
        'expected_emotion': 'grief',
        'category': 'grief',
    },
    'E6': {
        'text': "I'm doing pretty good today.",
        'expected_emotion': 'joy',
        'category': 'neutral_positive',
    },
    # TAGALOG (T1-T6)
    'T1': {
        'text': "Medyo pagod at malungkot ako ngayong araw.",
        'expected_emotion': 'sadness',
        'category': 'sadness',
    },
    'T2': {
        'text': "Hindi naman ako malungkot, pagod lang talaga.",
        'expected_emotion': 'neutral',
        'category': 'negation',
        'constraint': 'MUST NOT be sadness',
    },
    'T3': {
        'text': "Galit na galit ako sa ginawa nila.",
        'expected_emotion': 'anger',
        'category': 'anger',
    },
    'T4': {
        'text': "Kinakabahan ako sa mangyayari bukas.",
        'expected_emotion': 'anxiety',
        'category': 'anxiety',
    },
    'T5': {
        'text': "Miss na miss ko ang lola kong namatay.",
        'expected_emotion': 'grief',
        'category': 'grief',
    },
    'T6': {
        'text': "Okay lang naman ako ngayon.",
        'expected_emotion': 'neutral',
        'category': 'neutral_positive',
    },
    # TAGLISH (X1-X6)
    'X1': {
        'text': "Medyo anxious ako sa presentation tomorrow.",
        'expected_emotion': 'anxiety',
        'category': 'anxiety',
    },
    'X2': {
        'text': "I'm not sad naman, sobrang pagod lang.",
        'expected_emotion': 'neutral',
        'category': 'negation',
        'constraint': 'MUST NOT be sadness',
    },
    'X3': {
        'text': "Galit talaga ako because they ignored what I said.",
        'expected_emotion': 'anger',
        'category': 'anger',
    },
    'X4': {
        'text': "Parang wala akong gana gumawa ng anything today.",
        'expected_emotion': 'sadness',
        'category': 'sadness',
    },
    'X5': {
        'text': "Miss ko pa rin siya since namatay siya last year.",
        'expected_emotion': 'grief',
        'category': 'grief',
    },
    'X6': {
        'text': "Pretty okay naman ako today.",
        'expected_emotion': 'neutral',
        'category': 'neutral_positive',
    },
}

# FROZEN MANDATORY CATEGORIES - IDENTICAL TO V1
MANDATORY_CATEGORIES = {
    'negation': {
        'ids': ['E2', 'T2', 'X2'],
        'required': 'MUST NOT output sadness. Expected: neutral',
    },
    'grief': {
        'ids': ['E5', 'T5', 'X5'],
        'required': 'MUST output grief',
    },
    'anger': {
        'ids': ['E3', 'T3', 'X3'],
        'required': 'MUST output anger',
    },
    'anxiety': {
        'ids': ['E1', 'T4', 'X1'],
        'required': 'MUST output anxiety',
    },
    'sadness': {
        'ids': ['E4', 'T1', 'X4'],
        'required': 'MUST output sadness',
    },
    'neutral_positive': {
        'ids': ['E6', 'T6', 'X6'],
        'required': 'MUST output neutral or joy',
    },
}


class StrictValidatorV2:
    """Corrected behavioral validation - proper signal adapter"""

    def __init__(self):
        self.service = EmotionFusionService()
        self.vader = SentimentIntensityAnalyzer()
        self.shadow_classifier = ShadowModeEmotionClassifier(enabled=True, deployable_path=None)
        self.results = {}
        self.passed = 0
        self.failed = 0
        self.category_stats = {k: {'passed': 0, 'failed': 0, 'total': len(v['ids'])} 
                              for k, v in MANDATORY_CATEGORIES.items()}
        self.confidence_high = 0  # confidence == 1.0
        self.confidence_disagreement = 0  # confidence >= 0.90 with signal disagreement
        self.explicit_neutral_cases = []
        self.fallback_neutral_cases = []
        self.tagalog_unrecognized = {}  # Track unrecognized Tagalog keywords

    def collect_real_signals(self, message: str) -> Dict[str, Any]:
        """Collect REAL signals from MindCare pipeline - NO mocks, CORRECT adapter format"""
        signals = {}
        
        try:
            # Signal 1: Semantic Analysis
            semantic_result = analyze_semantic_layer(message, None)
            semantic_emotion_raw = semantic_result.get('emotion', {}).get('dominant')
            semantic_emotion_norm = self._normalize_emotion(semantic_emotion_raw, 'semantic')
            semantic_confidence = semantic_result.get('emotion', {}).get('confidence', 0)
            
            # CORRECTED ADAPTER: Use 'emotion' key (not 'raw_emotion' or 'normalized_emotion')
            signals['semantic'] = {
                'emotion': semantic_emotion_norm,  # ← CORRECT KEY
                'confidence': semantic_confidence,
            }
            
            # Track whether this is explicit neutral or fallback neutral
            if semantic_emotion_norm == 'neutral':
                matched_keywords = semantic_result.get('matched_keywords', [])
                if not matched_keywords or len(matched_keywords) == 0:
                    self.fallback_neutral_cases.append((message[:50], 'semantic'))
                else:
                    self.explicit_neutral_cases.append((message[:50], 'semantic'))
        except Exception as e:
            signals['semantic'] = {'error': str(e)}

        try:
            # Signal 2: Unified Analysis
            unified_result = analyze_unified(message, None, semantic_result=semantic_result)
            unified_emotion_raw = unified_result.emotion.get('primary')
            unified_emotion_norm = self._normalize_emotion(unified_emotion_raw, 'unified')
            unified_confidence = unified_result.emotion.get('confidence', 0)
            language = unified_result.language.get('primary', 'english')
            negation_detected = unified_result.negation.get('detected', False)
            sarcasm_detected = unified_result.sarcasm.get('detected', False)
            
            # CORRECTED ADAPTER: Use 'emotion' key
            signals['unified'] = {
                'emotion': unified_emotion_norm,  # ← CORRECT KEY
                'confidence': unified_confidence,
                'language': language,
                'negation_detected': negation_detected,
                'sarcasm_detected': sarcasm_detected,
            }
        except Exception as e:
            signals['unified'] = {'error': str(e)}

        try:
            # Signal 3: Trained Shadow Model
            shadow_pred = self.shadow_classifier.predict(message)
            if shadow_pred:
                trained_emotion_raw = shadow_pred.get('emotion')
                trained_emotion_norm = self._normalize_emotion(trained_emotion_raw, 'trained')
                trained_confidence = shadow_pred.get('confidence', 0)
                probs = shadow_pred.get('probabilities', {})
                
                signals['trained'] = {
                    'emotion': trained_emotion_norm,
                    'confidence': trained_confidence,
                    'probabilities': probs,
                }
            else:
                signals['trained'] = {'error': 'Prediction returned None'}
        except Exception as e:
            signals['trained'] = {'error': str(e)}

        try:
            # Signal 4: VADER
            vader_scores = self.vader.polarity_scores(message)
            signals['vader'] = {
                'compound': vader_scores.get('compound', 0),
                'pos': vader_scores.get('pos', 0),
                'neg': vader_scores.get('neg', 0),
                'neu': vader_scores.get('neu', 0),
            }
        except Exception as e:
            signals['vader'] = {'error': str(e)}

        return signals

    def _normalize_emotion(self, emotion: Optional[str], source: str) -> str:
        """Normalize to canonical 6-class"""
        if not emotion:
            return 'neutral'
        
        emotion_lower = emotion.lower().strip()
        
        if source == 'semantic':
            mapping = {
                'happiness': 'joy',
                'sadness': 'sadness',
                'anxiety': 'anxiety',
                'anger': 'anger',
                'neutral': 'neutral',
            }
            return mapping.get(emotion_lower, 'neutral')
        
        # Already 6-class for unified and trained
        canonical = ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']
        if emotion_lower in canonical:
            return emotion_lower
        
        return 'neutral'

    def validate_all_messages(self):
        """Run validation on all 18 frozen messages"""
        print("\n" + "="*80)
        print("TASK 9 - STRICT BEHAVIORAL VALIDATION V2 (CORRECTED)")
        print("="*80)
        
        for msg_id, test_case in VALIDATION_MESSAGES.items():
            self._validate_single_message(msg_id, test_case)

    def _validate_single_message(self, msg_id: str, test_case: Dict):
        """Validate single message with REAL signals"""
        message = test_case['text']
        expected_emotion = test_case['expected_emotion']
        category = test_case['category']
        constraint = test_case.get('constraint', '')
        
        print(f"\n[{msg_id}] {message[:60]}...")
        
        # Collect REAL signals
        signals = self.collect_real_signals(message)
        
        # Call fusion
        try:
            fusion_result = self.service.fuse(message, signals)
        except Exception as e:
            print(f"  ERROR: Fusion crashed: {e}")
            self.results[msg_id] = {
                'status': 'ERROR',
                'error': str(e),
                'expected': expected_emotion,
            }
            self.failed += 1
            self.category_stats[category]['failed'] += 1
            return
        
        # Check result
        final_emotion = fusion_result.final_emotion
        confidence = fusion_result.confidence
        uncertain_flag = fusion_result.uncertain_flag
        reason = fusion_result.reason
        
        # Validate
        passed = final_emotion == expected_emotion
        
        # Additional constraint check
        if constraint and 'MUST NOT be sadness' in constraint:
            if final_emotion == 'sadness':
                passed = False
                print(f"  CONSTRAINT VIOLATION: {constraint}")
        
        # Track high confidence cases
        if confidence >= 0.90 and confidence < 1.0:
            if fusion_result.agreements != ['semantic_unified', 'semantic_trained', 'unified_trained']:
                self.confidence_disagreement += 1
        elif confidence == 1.0:
            self.confidence_high += 1
        
        # Report
        status = 'PASS' if passed else 'FAIL'
        print(f"  Expected: {expected_emotion}")
        print(f"  Got: {final_emotion} (confidence={confidence:.2f}, uncertain={uncertain_flag})")
        print(f"  Status: {status}")
        
        # Record
        self.results[msg_id] = {
            'status': status,
            'expected': expected_emotion,
            'fusion_emotion': final_emotion,
            'confidence': confidence,
            'uncertain_flag': uncertain_flag,
            'reason': reason,
            'signals': {
                'semantic': signals.get('semantic', {}),
                'unified': signals.get('unified', {}),
                'trained': signals.get('trained', {}),
                'vader': signals.get('vader', {}),
            },
        }
        
        if passed:
            self.passed += 1
            self.category_stats[category]['passed'] += 1
        else:
            self.failed += 1
            self.category_stats[category]['failed'] += 1

    def print_summary(self):
        """Print COMPLETE validation summary"""
        print("\n" + "="*80)
        print("VALIDATION SUMMARY")
        print("="*80)
        
        total = self.passed + self.failed
        print(f"\nOverall: {self.passed}/{total} passed ({self.passed/total*100:.1f}%)")
        print(f"English: {self._count_category('E')}/6 passed")
        print(f"Tagalog: {self._count_category('T')}/6 passed")
        print(f"Taglish: {self._count_category('X')}/6 passed")
        
        print("\nMandatory Category Results:")
        for category, stats in self.category_stats.items():
            result = f"{stats['passed']}/{stats['total']}"
            status = "PASS" if stats['passed'] == stats['total'] else "FAIL"
            print(f"  {category}: {result} [{status}]")
            
            if stats['failed'] > 0:
                ids = MANDATORY_CATEGORIES[category]['ids']
                failed_ids = [id for id in ids if self.results.get(id, {}).get('status') == 'FAIL']
                if failed_ids:
                    print(f"    FAILED: {', '.join(failed_ids)}")
        
        print(f"\nConfidence Analysis:")
        print(f"  confidence == 1.0: {self.confidence_high} cases")
        print(f"  confidence >= 0.90 with signal disagreement: {self.confidence_disagreement} cases")
        
        print(f"\nExplicit vs Fallback Neutral:")
        print(f"  Explicit neutral (matched keywords): {len(self.explicit_neutral_cases)}")
        print(f"  Fallback neutral (no keywords matched): {len(self.fallback_neutral_cases)}")
        
        print("\nDetailed Results:")
        for msg_id in sorted(self.results.keys()):
            result = self.results[msg_id]
            status = result['status']
            if status == 'FAIL':
                expected = result.get('expected', '?')
                got = result.get('fusion_emotion', '?')
                print(f"  [{msg_id}] FAIL: expected {expected}, got {got}")
        
        print("\n" + "="*80)

    def _count_category(self, prefix: str) -> int:
        """Count passed for language prefix"""
        count = 0
        for i in range(1, 7):
            msg_id = f"{prefix}{i}"
            if self.results.get(msg_id, {}).get('status') == 'PASS':
                count += 1
        return count

    def save_results(self, filepath: str):
        """Save validation results to JSON"""
        report = {
            'validator_version': 'v2_corrected',
            'adapter_schema': 'CORRECT',
            'passed': self.passed,
            'failed': self.failed,
            'total': self.passed + self.failed,
            'results': self.results,
            'category_stats': self.category_stats,
        }
        
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(report, f, indent=2, ensure_ascii=False)
        
        print(f"\nResults saved to: {filepath}")


def main():
    """Run strict validation V2"""
    validator = StrictValidatorV2()
    validator.validate_all_messages()
    validator.print_summary()
    
    # Save results
    report_path = Path(__file__).parent / 'strict_validation_v2_results.json'
    validator.save_results(str(report_path))


if __name__ == "__main__":
    main()
