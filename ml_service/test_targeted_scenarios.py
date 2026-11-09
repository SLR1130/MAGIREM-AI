#!/usr/bin/env python3
"""
Test targeted scenarios for E6, T2, X4 fixes
Shows current VADER output and semantic understanding
"""

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent))

from semantic_analysis import SemanticAnalyzer, analyze_semantic_layer
from unified_analysis import analyze_unified
from shadow_mode_loader import ShadowModeEmotionClassifier
from nltk.sentiment import SentimentIntensityAnalyzer
from emotion_fusion_service import EmotionFusionService

# Test cases
SCENARIOS = {
    'E6_POSITIVE': {
        'text': "I'm doing pretty good today.",
        'type': 'positive_expression',
        'expected': 'joy',
    },
    'E6_OKAY': {
        'text': "I'm okay.",
        'type': 'positive_expression',
        'expected': 'neutral',
    },
    'E6_WELL': {
        'text': "I'm doing really well.",
        'type': 'positive_expression', 
        'expected': 'joy',
    },
    'T2_NEGATION': {
        'text': "Hindi naman ako malungkot, pagod lang talaga.",
        'type': 'tagalog_negation',
        'expected': 'neutral',
    },
    'T2_SIMPLE': {
        'text': "Hindi ako malungkot.",
        'type': 'tagalog_negation',
        'expected': 'neutral',
    },
    'T2_WITH_SADNESS': {
        'text': "Hindi ko alam pero malungkot ako.",
        'type': 'tagalog_negation',
        'expected': 'sadness',
    },
    'X4_LOW_MOTIVATION': {
        'text': "Parang wala akong gana gumawa ng anything today.",
        'type': 'low_motivation',
        'expected': 'sadness',
    },
    'X4_JUST_TIRED': {
        'text': "Pagod lang ako.",
        'type': 'low_motivation',
        'expected': 'neutral',
    },
    'X4_TIRED_SAD': {
        'text': "Pagod at malungkot ako.",
        'type': 'low_motivation',
        'expected': 'sadness',
    },
}

def test_scenario(scenario_id, scenario):
    """Test a single scenario"""
    text = scenario['text']
    expected = scenario['expected']
    print(f"\n{'='*70}")
    print(f"TEST: {scenario_id} ({scenario['type']})")
    print(f"Message: {text}")
    print(f"Expected: {expected}")
    print(f"{'='*70}")
    
    # Get signals
    semantic = analyze_semantic_layer(text, None)
    unified = analyze_unified(text, None, semantic_result=semantic)
    vader = SentimentIntensityAnalyzer().polarity_scores(text)
    
    # Extract emotions
    semantic_emotion = semantic.get('emotion', {}).get('dominant', 'unknown')
    semantic_conf = semantic.get('emotion', {}).get('confidence', 0)
    negation_detected = semantic.get('negation_detected', False)  # FIX: Top-level key, not nested
    
    unified_emotion = unified.emotion.get('primary', 'unknown')
    unified_conf = unified.emotion.get('confidence', 0)
    
    # Display VADER output
    print(f"\nVADER SENTIMENT:")
    print(f"  compound: {vader['compound']:.3f}")
    print(f"  positive: {vader['pos']:.3f}")
    print(f"  negative: {vader['neg']:.3f}")
    print(f"  neutral: {vader['neu']:.3f}")
    
    print(f"\nSEMANTIC ANALYSIS:")
    print(f"  emotion: {semantic_emotion}")
    print(f"  confidence: {semantic_conf:.2f}")
    print(f"  negation_detected: {negation_detected}")
    
    print(f"\nUNIFIED ANALYSIS:")
    print(f"  emotion: {unified_emotion}")
    print(f"  confidence: {unified_conf:.2f}")
    
    # Show if analysis matches expected
    semantic_match = "PASS" if semantic_emotion == expected else "FAIL"
    unified_match = "PASS" if unified_emotion == expected else "FAIL"
    
    print(f"\nRESULT:")
    print(f"  Semantic matches expected: {semantic_match}")
    print(f"  Unified matches expected: {unified_match}")

def main():
    print("\n" + "="*70)
    print("TARGETED SCENARIO TESTING")
    print("="*70)
    
    for scenario_id, scenario in SCENARIOS.items():
        test_scenario(scenario_id, scenario)

if __name__ == "__main__":
    main()
