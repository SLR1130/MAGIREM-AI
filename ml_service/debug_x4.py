#!/usr/bin/env python3
"""Debug X4 - make sure wala in wala akong gana is NOT treated as negation"""

import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent))

from unified_analysis import analyze_unified
from semantic_analysis import analyze_semantic_layer

message = "Parang wala akong gana gumawa ng anything today."
print(f"Message: {message}\n")

# Get semantic
semantic = analyze_semantic_layer(message, None)
print(f"Semantic emotion: {semantic['emotion'].get('dominant')}")
print(f"Semantic negation_detected: {semantic.get('negation_detected', False)}")
print()

# Get unified
unified = analyze_unified(message, None, semantic_result=semantic)
print(f"Unified emotion: {unified.emotion.get('primary')}")
print(f"Unified negation_detected: {unified.negation.get('detected', False)}")
print(f"Unified negation_type: {unified.negation.get('type', None)}")
print()

if unified.negation.get('detected', False):
    print("ERROR: X4 should NOT have negation detected!")
else:
    print("OK: X4 correctly has NO negation detected")
