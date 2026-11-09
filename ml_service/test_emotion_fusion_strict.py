#!/usr/bin/env python3
"""
TASK 9 - STRICT BEHAVIORAL VALIDATION
Independent validator for EmotionFusionService using REAL emotion signals from MindCare pipeline

CRITICAL: This test suite is FROZEN after initial SHA256. Expected labels and assertions
MUST NOT be weakened, deleted, or changed to pass. Failures are reported, not hidden.

NO fabricated signals. Uses real semantic_analysis, unified_analysis, trained model, VADER.
"""

import sys
import json
from pathlib import Path
from typing import Dict, Any

sys.path.insert(0, str(Path(__file__).parent))

# Real signal sources from MindCare pipeline
from semantic_analysis import analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer
from emotion_fusion_service import EmotionFusionService


# FROZEN TEST CASES - DO NOT MODIFY
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

# FROZEN MANDATORY CATEGORIES - DO NOT CHANGE EXPECTED VALUES
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


class StrictValidator:
    """Frozen behavioral validation - no exceptions for failures"""

    def __init__(self):
        self.service = EmotionFusionService()
        self.vader = SentimentIntensityAnalyzer()
        self.shadow_classifier = ShadowModeEmotionClassifier(enabled=True, deployable_path=None)
        self.results = {}
        self.passed = 0
        self.failed = 0
        self.category_stats = {k: {'passed': 0, 'failed': 0, 'total': len(v['ids'])} 
                              for k, v in MANDATORY_CATEGORIES.items()}

    def collect_real_signals(self, message: str) -> Dict[str, Any]:
        """Collect REAL signals from MindCare pipeline - NO mocks"""
        signals = {}
        
        try:
            # Signal 1: Semantic Analysis
            semantic_result = analyze_semantic_layer(message, None)
            signals['semantic'] = {
                'raw_emotion': semantic_result.get('emotion', {}).get('dominant'),
                'normalized_emotion': self._normalize_emotion(
                    semantic_result.get('emotion', {}).get('dominant'), 'semantic'
                ),
                'confidence': semantic_result.get('emotion', {}).get('confidence', 0),
            }
        except Exception as e:
            signals['semantic'] = {'error': str(e)}

        try:
            # Signal 2: Unified Analysis
            unified_result = analyze_unified(message, None, semantic_result=semantic_result)
            signals['unified'] = {
                'raw_emotion': unified_result.emotion.get('primary'),
                'normalized_emotion': self._normalize_emotion(
                    unified_result.emotion.get('primary'), 'unified'
                ),
                'confidence': unified_result.emotion.get('confidence', 0),
                'language': unified_result.language.get('primary'),
                'negation_detected': unified_result.negation.get('detected', False),
                'sarcasm_detected': unified_result.sarcasm.get('detected', False),
            }
        except Exception as e:
            signals['unified'] = {'error': str(e)}

        try:
            # Signal 3: Trained Shadow Model
            shadow_pred = self.shadow_classifier.predict(message)
            if shadow_pred:
                signals['trained'] = {
                    'emotion': shadow_pred.get('emotion'),
                    'confidence': shadow_pred.get('confidence', 0),
                    'top_3_probabilities': self._get_top_3_probs(shadow_pred.get('probabilities', {})),
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

    def _normalize_emotion(self, emotion: str, source: str) -> str:
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
        
        # Already 6-class
        canonical = ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']
        if emotion_lower in canonical:
            return emotion_lower
        
        return 'neutral'

    def _get_top_3_probs(self, probs: Dict) -> Dict:
        """Get top 3 probabilities from classifier output"""
        if not probs:
            return {}
        sorted_probs = sorted(probs.items(), key=lambda x: x[1], reverse=True)
        return dict(sorted_probs[:3])

    def validate_all_messages(self):
        """Run validation on all 18 frozen messages - STRICT"""
        print("\n" + "="*80)
        print("TASK 9 - STRICT BEHAVIORAL VALIDATION")
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
        
        # Report
        status = 'PASS' if passed else 'FAIL'
        print(f"  Expected: {expected_emotion}")
        print(f"  Got: {final_emotion} (confidence={confidence:.2f}, uncertain={uncertain_flag})")
        print(f"  Status: {status}")
        print(f"  Reason: {reason[:100]}...")
        
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
        """Print COMPLETE validation summary - NO hiding failures"""
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
            
            # List failures for mandatory categories
            if stats['failed'] > 0:
                ids = MANDATORY_CATEGORIES[category]['ids']
                failed_ids = [id for id in ids if self.results.get(id, {}).get('status') == 'FAIL']
                if failed_ids:
                    print(f"    FAILED: {', '.join(failed_ids)}")
        
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

    def save_baseline_report(self, filepath: str):
        """Save full baseline report to JSON"""
        report = {
            'validation_type': 'strict_behavioral',
            'status': 'BASELINE',
            'passed': self.passed,
            'failed': self.failed,
            'total': self.passed + self.failed,
            'results': self.results,
            'category_stats': self.category_stats,
        }
        
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(report, f, indent=2, ensure_ascii=False)
        
        print(f"\nBaseline report saved to: {filepath}")


def main():
    """Run strict validation"""
    validator = StrictValidator()
    validator.validate_all_messages()
    validator.print_summary()
    
    # Save baseline
    report_path = Path(__file__).parent / 'strict_validation_baseline.json'
    validator.save_baseline_report(str(report_path))


if __name__ == "__main__":
    main()
