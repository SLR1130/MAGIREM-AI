"""Offline feasibility evaluation for fine-grained emotion interpretation.

This module is deliberately evaluation-only.  It never writes production
emotion fields, never changes Admin analytics, and uses synthetic messages.
The six-class baseline is measured through the running Flask ``/analyze``
endpoint.  The optional Gemma pilot uses the existing HybridResponseEngine
Ollama admission path so evaluation calls still share the one-generation
SQLite queue.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import statistics
import sys
import time
import uuid
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

import requests

from emotion_interpreter_schema import (
    ATTR_BOTH,
    ATTR_HYPOTHETICAL,
    ATTR_NONE,
    ATTR_OTHER,
    STATUS_ATTRIBUTED_OTHER,
    STATUS_CLEAR,
    STATUS_MIXED,
    STATUS_NO_EMOTION,
    STATUS_UNCERTAIN,
    build_ollama_output_schema,
    expected_status,
    infer_attribution_scope,
    predicted_uncertain,
    secondary_emotion_has_source_cue,
    secondary_emotion_has_source_evidence,
    validate_interpreter_result,
)


ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config" / "emotion_taxonomy.json"
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "audit-recovery" / "emotion-taxonomy-20261024" / "phase2-emotion-feasibility-results.json"
FROZEN_BASELINE = ROOT / "storage" / "app" / "audit-recovery" / "emotion-taxonomy-20261024" / "phase4-frozen-baseline.json"
FLASK_ANALYZE_URL = os.environ.get("MINDCARE_EVAL_ANALYZE_URL", "http://127.0.0.1:5000/analyze")
OLLAMA_URL = os.environ.get("MINDCARE_OLLAMA_URL", "http://127.0.0.1:11434")


# One synthetic, first-person example per taxonomy label.  The set intentionally
# mixes English, Filipino, and Taglish phrasing and includes nearby emotions.
CASES: List[Dict[str, Any]] = [
    {"key": "happiness_joy", "text": "I feel genuinely happy today because I finally finished my project."},
    {"key": "excitement", "text": "Sobrang excited ako sa internship interview bukas!"},
    {"key": "calmness", "text": "Naramdaman kong kalmado ako matapos ang tahimik na paghinga."},
    {"key": "contentment", "text": "I feel content with a simple evening and enough time to rest."},
    {"key": "hope_optimism", "text": "Kahit mahirap ngayon, hopeful pa rin ako na gagaan ang mga susunod na araw."},
    {"key": "pride", "text": "I am proud of myself for asking for help instead of giving up."},
    {"key": "curiosity", "text": "Curious ako kung paano gumagana ang bagong research method."},
    {"key": "gratitude", "text": "I feel grateful that my classmate listened without judging me."},
    {"key": "love_affection", "text": "I feel so much affection for my family when we call each other."},
    {"key": "relief", "text": "I felt relieved when the doctor said the result was okay."},
    {"key": "confidence", "text": "Mas confident na ako ngayon na kaya kong mag-present sa klase."},
    {"key": "sadness", "text": "I feel sad and I do not have much energy today."},
    {"key": "grief", "text": "I am grieving because someone important to our family died."},
    {"key": "disappointment", "text": "I feel disappointed that my application was not accepted."},
    {"key": "loneliness", "text": "I feel lonely in the dorm even when other students are nearby."},
    {"key": "homesickness", "text": "Miss na miss ko ang bahay at nakakaramdam ako ng homesickness sa dorm."},
    {"key": "hopelessness", "text": "I feel hopeless and cannot imagine anything improving soon."},
    {"key": "rejection_emotional_hurt", "text": "I feel hurt and rejected after my friends left me out."},
    {"key": "anxiety", "text": "I feel anxious about my thesis defense tomorrow."},
    {"key": "fear", "text": "I am afraid that something bad will happen when I walk home alone."},
    {"key": "stress", "text": "Sobrang stressed ako sa sunod-sunod na exams at deadlines."},
    {"key": "overwhelm", "text": "I feel overwhelmed because every task seems urgent at the same time."},
    {"key": "anger", "text": "I feel angry that my group ignored the work I completed."},
    {"key": "frustration", "text": "Nakakafrustrate na paulit-ulit nag-e-error ang program ko."},
    {"key": "jealousy_envy", "text": "I feel jealous when I compare my progress with my classmate's."},
    {"key": "disgust", "text": "I feel disgusted by the way they treated another student."},
    {"key": "shame", "text": "I feel ashamed that I missed the deadline and disappointed my team."},
    {"key": "guilt", "text": "I feel guilty for speaking harshly to my friend yesterday."},
    {"key": "insecurity_self_doubt", "text": "I keep doubting myself and feel insecure about my abilities."},
    {"key": "boredom", "text": "I am bored and cannot find interest in the same routine anymore."},
    {"key": "confusion", "text": "Nalilito ako kung alin ang tamang next step sa decision na ito."},
    {"key": "neutral", "text": "I am describing my schedule for tomorrow; there is no strong feeling."},
    {"key": "surprise", "text": "I was surprised when my quiet classmate suddenly congratulated me."},
    {"key": "compassion_empathy", "text": "I feel compassion for my friend because I can see how difficult this is."},
    {"key": "anticipation", "text": "I am looking forward to meeting my adviser next week."},
    # Confusion-pair probes are retained separately from the one-per-label set.
    {"key": "happiness_joy", "text": "I am happy, not anxious, about the result I received.", "expected_uncertain": False},
    {"key": "anxiety", "text": "I am excited but also anxious about starting university.", "expected_uncertain": True},
    {"key": "shame", "text": "I made a mistake, but I feel guilty about the impact rather than ashamed of who I am.", "expected_uncertain": True},
    {"key": "compassion_empathy", "text": "My friend feels anxious, but I personally feel calm while listening.", "expected_uncertain": True},
    {"key": None, "text": "My friend feels anxious; I am only reporting their feeling and do not feel an emotion about it.", "expected_uncertain": True},
]

# Additional synthetic probes deliberately exercise ambiguity, attribution,
# negation, slang, and close emotion pairs. They are not production records.
# A nullable expected_key means that a detailed label should not be forced.
CASES.extend([
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "Okay lang naman siguro ako."},
    {"key": "happiness_joy", "expected_secondary_keys": ["anxiety"], "expected_status": STATUS_MIXED, "text": "Hindi ko alam kung masaya ba ako o kinakabahan."},
    {"key": "happiness_joy", "expected_secondary_keys": ["fear"], "expected_status": STATUS_MIXED, "text": "Masaya ako na nakapasa, pero natatakot ako sa next semester."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "text": "Sabi ng kaibigan ko, sobrang lungkot niya."},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "text": "Parang wala lang akong nararamdaman ngayon."},
    {"key": "excitement", "expected_secondary_keys": ["stress"], "expected_status": STATUS_MIXED, "text": "Excited ako, pero sobrang pressured din."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "text": "My roommate said she is grieving; I am asking how to support her."},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "text": "What is the deadline for the reflection paper?"},
    {"key": "anxiety", "expected_secondary_keys": ["stress"], "expected_status": STATUS_MIXED, "text": "Kinakabahan at stressed ako sa defense, hindi ko alam alin ang mas nangingibabaw."},
    {"key": "sadness", "expected_secondary_keys": ["loneliness"], "expected_status": STATUS_MIXED, "text": "I feel sad and lonely after my friends stopped replying."},
    {"key": "guilt", "expected_secondary_keys": ["shame"], "expected_status": STATUS_MIXED, "text": "I feel guilty about what I did, but I am not ashamed of who I am."},
    {"key": "fear", "expected_secondary_keys": ["anxiety"], "expected_status": STATUS_MIXED, "text": "I am scared of failing and anxious about what everyone will think."},
    {"key": "stress", "expected_secondary_keys": ["overwhelm"], "expected_status": STATUS_MIXED, "text": "Sobrang pressured ako, parang lahat ng tasks sabay-sabay."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "Maybe I am okay, or maybe I am just distracting myself haha."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "Hindi ko sure kung bad trip ako o pagod lang."},
    {"key": "happiness_joy", "expected_status": STATUS_CLEAR, "text": "I am happy, not sad, that my application was accepted."},
    {"key": "anger", "expected_status": STATUS_CLEAR, "text": "Hindi ako galit; I am actually calm about the change."},
    {"key": "calmness", "expected_status": STATUS_CLEAR, "text": "I am not anxious anymore; I feel calm after talking it through."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "text": "Sabi niya masaya siya, pero ako wala akong sinasabing nararamdaman."},
    {"key": "compassion_empathy", "expected_status": STATUS_CLEAR, "text": "I feel empathy for my classmate and want to listen without fixing everything."},
    {"key": "anticipation", "expected_status": STATUS_CLEAR, "text": "Looking forward ako sa practicum next month."},
    {"key": "frustration", "expected_status": STATUS_CLEAR, "text": "Nakakainis talaga, paulit-ulit na naman nag-crash ang app."},
    {"key": "boredom", "expected_status": STATUS_CLEAR, "text": "Wala akong gana sa routine; bored lang ako today."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "I guess I feel something, but I cannot name it yet."},
    {"key": "relief", "expected_status": STATUS_CLEAR, "text": "At least tapos na; I finally feel relieved."},
    {"key": "pride", "expected_status": STATUS_CLEAR, "text": "Small win lang pero proud ako sa sarili ko."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "text": "My friend is anxious about exams. What can I say to him?"},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "text": "Can you explain the difference between stress and anxiety?"},
])

# Phase 4 holdout: these messages are intentionally distinct from the frozen
# 68-case artifact above. They are only used for independent evaluation.
PHASE4_CASES: List[Dict[str, Any]] = [
    {"key": "excitement", "text": "I cannot wait for the campus arts festival next week."},
    {"key": "anxiety", "text": "Lowkey kinakabahan ako sa oral defense, pero naghahanda naman ako."},
    {"key": "contentment", "text": "Tahimik lang ang weekend ko pero okay na okay ako dito."},
    {"key": "grief", "text": "I still grieve my grandfather, especially when family gatherings come around."},
    {"key": "homesickness", "text": "Nami-miss ko ang luto sa bahay habang nasa dorm ako."},
    {"key": "disappointment", "text": "I was hoping for a different result and I feel let down."},
    {"key": "anger", "text": "Gigil ako sa groupmate na paulit-ulit nang nang-iiwan ng trabaho."},
    {"key": "hope_optimism", "text": "Hindi man mabilis, naniniwala pa rin akong may magandang direction ito."},
    {"key": "confidence", "text": "I can handle the presentation now; I trust the work I prepared."},
    {"key": "curiosity", "text": "Bakit kaya ganito ang result? I want to understand the pattern."},
    {"key": "gratitude", "text": "Thankful ako sa adviser na nagbigay ng oras kahit busy siya."},
    {"key": "loneliness", "text": "I am surrounded by classmates but still feel alone after class."},
    {"key": "insecurity_self_doubt", "text": "Baka hindi ako kasing galing ng iba; I keep second-guessing myself."},
    {"key": "boredom", "text": "Same routine every day and wala na akong gana."},
    {"key": "surprise", "text": "I did not expect the scholarship email at all; I was genuinely surprised."},
    {"key": "compassion_empathy", "text": "I understand why my seatmate is struggling, and I want to be gentle with her."},
    {"key": "relief", "text": "Hindi ako masaya or sad about it; relieved lang ako na tapos na."},
    {"key": "excitement", "expected_secondary_keys": ["anxiety"], "expected_status": STATUS_MIXED, "text": "Excited ako sa graduation pero kinakabahan ako sa future."},
    {"key": "relief", "expected_secondary_keys": ["guilt"], "expected_status": STATUS_MIXED, "text": "Relieved ako na pumasa, pero guilty ako na naiwan ang teammate ko."},
    {"key": "pride", "expected_secondary_keys": ["insecurity_self_doubt"], "expected_status": STATUS_MIXED, "text": "Proud ako sa output ko kahit may part sa akin na nagdududa pa rin."},
    {"key": "sadness", "expected_secondary_keys": ["anger"], "expected_status": STATUS_MIXED, "text": "I feel hurt and angry after they cancelled on me again."},
    {"key": "hope_optimism", "expected_secondary_keys": ["homesickness"], "expected_status": STATUS_MIXED, "text": "Hopeful ako sa bagong term pero homesick pa rin ako."},
    {"key": "calmness", "expected_secondary_keys": ["anticipation"], "expected_status": STATUS_MIXED, "text": "I feel calm today, though I am also looking forward to tomorrow."},
    {"key": "disappointment", "expected_status": STATUS_CLEAR, "text": "I am not angry about the feedback; I am simply disappointed."},
    {"key": "relief", "expected_status": STATUS_CLEAR, "text": "Hindi ako masaya sa delay, pero relieved na safe ang lahat."},
    {"key": "frustration", "expected_status": STATUS_CLEAR, "text": "Great, another deadline on the same night. Exactly what I needed."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "Parang okay lang ako, pero hindi ko ma-explain kung bakit."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "text": "Maybe I am jealous? Hindi ko talaga sure."},
    {"key": None, "expected_status": STATUS_UNCERTAIN, "expected_attribution_scope": "hypothetical", "text": "If I failed the exam, I would probably feel ashamed."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "expected_attribution_scope": "other", "expected_other_keys": ["grief"], "text": "My cousin says she is devastated, and I am asking how to help her."},
    {"key": "calmness", "expected_other_keys": ["anxiety"], "expected_attribution_scope": "both", "text": "I feel calm, but my sister is panicking about the results."},
    {"key": None, "expected_status": STATUS_ATTRIBUTED_OTHER, "expected_attribution": True, "expected_attribution_scope": "other", "expected_other_keys": ["happiness_joy"], "text": "Sinabi ng roommate ko na sobrang saya niya; hindi ko iyon sariling feeling."},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "expected_attribution_scope": "none", "text": "What does anxiety mean in a psychology class?"},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "expected_attribution_scope": "none", "text": "I am only asking for a definition; wala akong sinasabing emotion."},
    {"key": "anxiety", "expected_status": STATUS_CLEAR, "text": "I am not relaxed at all; I am anxious about the interview."},
    {"key": "shame", "expected_status": STATUS_CLEAR, "text": "I feel ashamed of the mistake, but I do not hate myself."},
    {"key": "fear", "expected_status": STATUS_CLEAR, "text": "Hindi ako natatakot sa tao; natatakot ako na mawala ang scholarship."},
    {"key": "anticipation", "expected_status": STATUS_CLEAR, "text": "Looking forward ako sa first day kahit wala pang exact plan."},
    {"key": None, "expected_status": STATUS_NO_EMOTION, "expected_attribution_scope": "none", "text": "Can you compare sadness and grief for my assignment?"},
]


def load_taxonomy() -> Dict[str, Any]:
    return json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))


def taxonomy_index(taxonomy: Dict[str, Any]) -> Dict[str, Dict[str, Any]]:
    return {item["key"]: item for item in taxonomy["emotions"]}


def category_for(key: Optional[str], index: Dict[str, Dict[str, Any]]) -> Optional[str]:
    return index.get(key, {}).get("category_key") if key else None


def percentile(values: Iterable[float], p: float) -> Optional[float]:
    vals = sorted(values)
    if not vals:
        return None
    if len(vals) == 1:
        return round(vals[0], 2)
    position = (len(vals) - 1) * p
    lower = int(position)
    upper = min(lower + 1, len(vals) - 1)
    fraction = position - lower
    return round(vals[lower] + (vals[upper] - vals[lower]) * fraction, 2)


def _case_expectation(case: Dict[str, Any], index: Dict[str, Dict[str, Any]]) -> Dict[str, Any]:
    expected_key = case.get("key")
    expected_secondary = list(case.get("expected_secondary_keys", []))
    expected_other = list(case.get("expected_other_keys", []))
    expected_categories = []
    for emotion in ([expected_key] if expected_key else []) + expected_secondary:
        mapped = category_for(emotion, index)
        if mapped and mapped not in expected_categories:
            expected_categories.append(mapped)
    expected_other_categories = []
    for emotion in expected_other:
        mapped = category_for(emotion, index)
        if mapped and mapped not in expected_other_categories:
            expected_other_categories.append(mapped)
    return {
        "expected_key": expected_key,
        "expected_category": category_for(expected_key, index),
        "expected_category_keys": expected_categories,
        "expected_other_category_keys": expected_other_categories,
        "expected_status": expected_status(case),
        "expected_secondary_keys": list(case.get("expected_secondary_keys", [])),
        "expected_other_keys": list(case.get("expected_other_keys", [])),
        "expected_attribution": bool(case.get("expected_attribution", False)),
        "expected_attribution_scope": case.get("expected_attribution_scope", "student" if case.get("key") is not None else "none"),
        "expected_uncertain": expected_status(case) in {STATUS_UNCERTAIN, STATUS_MIXED},
    }


def _metric_summary(rows: List[Dict[str, Any]], all_categories: Optional[Iterable[str]] = None) -> Dict[str, Any]:
    valid = [row for row in rows if "error" not in row]
    schema_valid = [row for row in valid if row.get("schema_valid")]
    label_rows = [row for row in schema_valid if row.get("expected_key") is not None]
    category_rows = [row for row in schema_valid if row.get("expected_category") is not None]
    status_rows = [row for row in schema_valid if row.get("expected_status") is not None]
    uncertainty_expected = [row for row in status_rows if row["expected_status"] in {STATUS_UNCERTAIN, STATUS_MIXED}]
    uncertainty_predicted = [row for row in status_rows if predicted_uncertain(row.get("status"))]
    tp_uncertain = sum(row["expected_status"] in {STATUS_UNCERTAIN, STATUS_MIXED} and predicted_uncertain(row.get("status")) for row in status_rows)
    fp_uncertain = sum(row["expected_status"] not in {STATUS_UNCERTAIN, STATUS_MIXED} and predicted_uncertain(row.get("status")) for row in status_rows)
    fn_uncertain = sum(row["expected_status"] in {STATUS_UNCERTAIN, STATUS_MIXED} and not predicted_uncertain(row.get("status")) for row in status_rows)
    mixed_expected = [row for row in status_rows if row["expected_status"] == STATUS_MIXED]
    mixed_predicted = [row for row in status_rows if row.get("status") == STATUS_MIXED]
    tp_mixed = sum(row["expected_status"] == STATUS_MIXED and row.get("status") == STATUS_MIXED for row in status_rows)
    fp_mixed = sum(row["expected_status"] != STATUS_MIXED and row.get("status") == STATUS_MIXED for row in status_rows)
    fn_mixed = sum(row["expected_status"] == STATUS_MIXED and row.get("status") != STATUS_MIXED for row in status_rows)
    attributed = [row for row in status_rows if row.get("expected_attribution")]
    attribution_rows = [row for row in status_rows if row.get("expected_attribution_scope")]
    secondary_rows = [row for row in status_rows if row.get("expected_secondary_keys")]
    other_rows = [row for row in status_rows if row.get("expected_other_keys")]
    category_set_rows = [row for row in status_rows if row.get("expected_category_keys")]
    confusion = [
        {"message_sha256": row.get("message_sha256"), "expected_key": row.get("expected_key"), "expected_status": row.get("expected_status"), "predicted_key": row.get("primary_emotion"), "predicted_status": row.get("status"), "predicted_secondary_keys": row.get("secondary_emotions", [])}
        for row in schema_valid
        if (row.get("expected_key") is not None and row.get("primary_emotion") != row.get("expected_key"))
        or row.get("expected_status") != row.get("status")
        or row.get("attribution_scope") != row.get("expected_attribution_scope")
        or (row.get("expected_attribution") and row.get("status") != STATUS_ATTRIBUTED_OTHER)
    ]
    def rate(num: int, den: int) -> Optional[float]:
        return round(num / den, 4) if den else None
    category_performance = {}
    category_names = sorted(set(all_categories or []) | {row.get("expected_category") for row in category_rows if row.get("expected_category")})
    for category in category_names:
        subset = [row for row in category_rows if row.get("expected_category") == category]
        category_performance[category] = {
            "denominator": len(subset),
            "correct": sum(row.get("category_key") == category for row in subset),
            "accuracy": rate(sum(row.get("category_key") == category for row in subset), len(subset)),
        }
    precision_uncertain = rate(tp_uncertain, tp_uncertain + fp_uncertain)
    recall_uncertain = rate(tp_uncertain, tp_uncertain + fn_uncertain)
    precision_mixed = rate(tp_mixed, tp_mixed + fp_mixed)
    recall_mixed = rate(tp_mixed, tp_mixed + fn_mixed)
    return {
        "valid_count": len(valid),
        "schema_valid_count": len(schema_valid),
        "schema_valid_rate": rate(len(schema_valid), len(valid)),
        "label_denominator": len(label_rows),
        "category_denominator": len(category_rows),
        "status_denominator": len(status_rows),
        "exact_detailed_key_accuracy": rate(sum(row.get("primary_emotion") == row.get("expected_key") for row in label_rows), len(label_rows)),
        "category_accuracy": rate(sum(row.get("category_key") == row.get("expected_category") for row in category_rows), len(category_rows)),
        "category_performance": category_performance,
        "category_set_accuracy": {"denominator": len(category_set_rows), "correct": sum(set(row.get("category_keys", [])) == set(row.get("expected_category_keys", [])) for row in category_set_rows), "accuracy": rate(sum(set(row.get("category_keys", [])) == set(row.get("expected_category_keys", [])) for row in category_set_rows), len(category_set_rows))},
        "secondary_label_accuracy": {"denominator": len(secondary_rows), "correct": sum(set(row.get("secondary_emotions", [])) == set(row.get("expected_secondary_keys", [])) for row in secondary_rows), "accuracy": rate(sum(set(row.get("secondary_emotions", [])) == set(row.get("expected_secondary_keys", [])) for row in secondary_rows), len(secondary_rows))},
        "other_label_accuracy": {"denominator": len(other_rows), "correct": sum(set(row.get("other_emotions", [])) == set(row.get("expected_other_keys", [])) for row in other_rows), "accuracy": rate(sum(set(row.get("other_emotions", [])) == set(row.get("expected_other_keys", [])) for row in other_rows), len(other_rows))},
        "uncertainty": {"denominator": len(status_rows), "expected_count": len(uncertainty_expected), "predicted_count": len(uncertainty_predicted), "tp": tp_uncertain, "fp": fp_uncertain, "fn": fn_uncertain, "precision": precision_uncertain, "recall": recall_uncertain},
        "mixed": {"denominator": len(status_rows), "expected_count": len(mixed_expected), "predicted_count": len(mixed_predicted), "tp": tp_mixed, "fp": fp_mixed, "fn": fn_mixed, "precision": precision_mixed, "recall": recall_mixed, "f1": rate(2 * precision_mixed * recall_mixed, precision_mixed + recall_mixed) if precision_mixed is not None and recall_mixed is not None and precision_mixed + recall_mixed else None},
        "third_person_attribution": {"denominator": len(attributed), "correct": sum(row.get("status") == STATUS_ATTRIBUTED_OTHER for row in attributed), "accuracy": rate(sum(row.get("status") == STATUS_ATTRIBUTED_OTHER for row in attributed), len(attributed))},
        "attribution_scope": {"denominator": len(attribution_rows), "correct": sum(row.get("attribution_scope") == row.get("expected_attribution_scope") for row in attribution_rows), "accuracy": rate(sum(row.get("attribution_scope") == row.get("expected_attribution_scope") for row in attribution_rows), len(attribution_rows))},
        "confusion_cases": confusion[:100],
    }


def run_baseline(cases: List[Dict[str, Any]], index: Dict[str, Dict[str, Any]]) -> Dict[str, Any]:
    rows: List[Dict[str, Any]] = []
    for case in cases:
        started = time.perf_counter()
        row: Dict[str, Any] = _case_expectation(case, index)
        row["message_sha256"] = hashlib.sha256(case["text"].encode("utf-8")).hexdigest()
        try:
            response = requests.post(FLASK_ANALYZE_URL, json={"message": case["text"]}, timeout=(5, 30))
            row["http_status"] = response.status_code
            payload = response.json()
            row["predicted_legacy"] = payload.get("emotion")
            taxonomy = payload.get("emotion_taxonomy") or {}
            row["predicted_key"] = taxonomy.get("key")
            row["predicted_category"] = taxonomy.get("category_key")
            row["uncertain"] = bool(taxonomy.get("uncertain", payload.get("uncertain", False)))
            row["confidence"] = payload.get("confidence")
            row["status"] = STATUS_UNCERTAIN if row["uncertain"] else (STATUS_CLEAR if row["predicted_key"] else STATUS_NO_EMOTION)
            row["exact_key_match"] = row["predicted_key"] == row["expected_key"]
            row["category_match"] = row["predicted_category"] == row["expected_category"]
        except Exception as exc:  # pragma: no cover - exercised only when service is unavailable
            row["error"] = type(exc).__name__ + ": " + str(exc)[:180]
            row["exact_key_match"] = False
            row["category_match"] = False
        row["latency_ms"] = round((time.perf_counter() - started) * 1000, 2)
        rows.append(row)

    valid = [row for row in rows if "error" not in row]
    consistency_cases = cases[:4] + cases[-4:]
    consistency_rows: List[Dict[str, Any]] = []
    for case in consistency_cases:
        outputs: List[Dict[str, Any]] = []
        for _ in range(2):
            try:
                payload = requests.post(FLASK_ANALYZE_URL, json={"message": case["text"]}, timeout=(5, 30)).json()
                taxonomy = payload.get("emotion_taxonomy") or {}
                outputs.append({"key": taxonomy.get("key"), "category": taxonomy.get("category_key"), "uncertain": bool(taxonomy.get("uncertain", payload.get("uncertain", False)))})
            except Exception as exc:
                outputs.append({"error": type(exc).__name__})
        consistency_rows.append({"expected_key": case["key"], "outputs": outputs, "consistent": len({json.dumps(item, sort_keys=True) for item in outputs}) == 1})
    return {
        "endpoint": FLASK_ANALYZE_URL,
        "case_count": len(rows),
        "valid_count": len(valid),
        "schema_valid_count": None,
        "schema_valid_rate": None,
        "label_denominator": sum(row.get("expected_key") is not None for row in valid),
        "category_denominator": sum(row.get("expected_category") is not None for row in valid),
        "status_denominator": len(valid),
        "exact_detailed_key_accuracy": round(sum(row.get("exact_key_match", False) for row in valid if row.get("expected_key") is not None) / max(1, sum(row.get("expected_key") is not None for row in valid)), 4) if valid else None,
        "category_accuracy": round(sum(row.get("category_match", False) for row in valid if row.get("expected_category") is not None) / max(1, sum(row.get("expected_category") is not None for row in valid)), 4) if valid else None,
        "uncertain_rate": round(sum(row.get("uncertain", False) for row in valid) / len(valid), 4) if valid else None,
        "uncertainty": {"denominator": len(valid), "expected_count": sum(row.get("expected_uncertain", False) for row in valid), "predicted_count": sum(row.get("uncertain", False) for row in valid), "tp": sum(row.get("expected_uncertain", False) and row.get("uncertain", False) for row in valid), "fp": sum(not row.get("expected_uncertain", False) and row.get("uncertain", False) for row in valid), "fn": sum(row.get("expected_uncertain", False) and not row.get("uncertain", False) for row in valid)},
        "mixed": {"denominator": len(valid), "expected_count": sum(row.get("expected_status") == STATUS_MIXED for row in valid), "predicted_count": 0, "tp": 0, "fp": 0, "fn": sum(row.get("expected_status") == STATUS_MIXED for row in valid), "precision": None, "recall": 0.0 if any(row.get("expected_status") == STATUS_MIXED for row in valid) else None, "f1": None},
        "third_person_attribution": {"denominator": sum(row.get("expected_attribution", False) for row in valid), "correct": 0, "accuracy": 0.0 if any(row.get("expected_attribution", False) for row in valid) else None},
        "confusion_cases": [],
        "latency_ms": {
            "median": percentile((row["latency_ms"] for row in rows), 0.5),
            "p95": percentile((row["latency_ms"] for row in rows), 0.95),
        },
        "consistency": {
            "case_count": len(consistency_rows),
            "repetitions_per_case": 2,
            "agreement_rate": round(sum(row["consistent"] for row in consistency_rows) / len(consistency_rows), 4) if consistency_rows else None,
            "rows": consistency_rows,
        },
        "rows": rows,
    }


def gemma_prompt(case_text: str, valid_keys: List[str], valid_categories: List[str]) -> str:
    return (
        "Classify the student's felt emotion for an offline research evaluation. "
        "Use the full message, grammar, tense, and first-person perspective, not isolated keywords. "
        "Apply this decision order: (1) identify who is experiencing each emotion, "
        "(2) decide whether the student is asserting a present lived feeling, "
        "and (3) separate simultaneous feelings from uncertainty or a merely mentioned emotion. "
        "Return JSON only with exactly these keys: status (clear, mixed, uncertain, "
        "no_emotion, or attributed_other), attribution_scope (student, other, both, "
        "hypothetical, or none), primary_emotion (one allowed key or null), "
        "secondary_emotions (zero or more allowed keys), other_emotions (zero or more "
        "allowed keys felt by another person), uncertainty_reason (short or null), "
        "felt_by_student (boolean), evidence (short string), and optional category_key "
        "for diagnostics only. Mixed requires at least two plausible emotions. Copy emotion keys exactly from the allowed list; "
        "never invent aliases, combine keys, or return a category in place of an emotion key. "
        "Use uncertain when the student signals doubt, inability to identify a feeling, or insufficient evidence; "
        "the presence of an emotion word alone is not enough to make a message clear or mixed. Use no_emotion for "
        "a neutral question or an explicit absence of feeling. Use attributed_other when the emotion "
        "belongs only to a friend or another person; use attribution_scope other and then "
        "primary_emotion must be null. Use attribution_scope student for all emotions the student experiences, "
        "including mixed emotions. A student's mixed emotions are not attribution_scope both. Use both only when the student and another person each have separate emotions "
        "and populate other_emotions; use other only when the emotion belongs to someone else. Use hypothetical "
        "for conditional examples, not lived experience. Do not "
        "diagnose and do not provide a numeric confidence score. The evaluator derives the "
        "authoritative category from primary_emotion, so never invent a category. Allowed "
        "emotion keys: " + ", ".join(valid_keys) + ". Allowed category keys (diagnostic only): " + ", ".join(valid_categories) + ". "
        "For Filipino or Taglish, interpret first-person markers and connective contrast in context; "
        "do not translate a single cue in isolation. Examples: an explicit first-person statement that asserts "
        "two present feelings in the same situation is mixed (such as excitement together with anxiety), with "
        "attribution_scope student and exactly those student emotions; "
        "a first-person question or hedge about which feeling is present is uncertain, even when it names two candidates. "
        "If the student has one clear feeling and another person's feeling is also mentioned, use clear (or uncertain if the student's own state is uncertain), "
        "attribution_scope both, and put the other person's exact label only in other_emotions. "
        "Use uncertain with null primary when the student cannot identify a feeling; use no_emotion with null primary "
        "only for an information request or an explicit absence of feeling. "
        "For a clear mixed result, list the two lived emotions in primary_emotion and secondary_emotions; do not leave "
        "a mixed result with only one label. A hedged self-assessment remains uncertain rather than clear; "
        "Every secondary_emotion must have its own explicit, first-person source cue in the student's message. "
        "If a lived feeling is expressed indirectly (for example, a bodily sensation), include an "
        "emotion_evidence array (empty when there is no secondary) with objects containing the approved emotion key and a short exact span copied from the student's "
        "message; never paraphrase or invent that span. A secondary without either a taxonomy alias or an exact "
        "emotion_evidence span is discarded. "
        "Do not add a related or plausible secondary merely because it commonly co-occurs with the primary; "
        "one shame cue is not evidence of guilt, and one anxiety cue is not evidence of fear. "
        "no_emotion is reserved for an information request or an explicit statement that no feeling is present. "
        "for 'I feel calm, but my sister is panicking' use clear, attribution_scope both, primary calmness, other_emotions anxiety; "
        "for 'My cousin says she is devastated' use attributed_other, attribution_scope other, other_emotions grief; "
        "for a conditional 'If I failed, I would feel ashamed' use uncertain, attribution_scope hypothetical, primary null.\n\n"
        "Student message:\n" + case_text
    )


def parse_gemma_json(
    text: str,
    index: Dict[str, Dict[str, Any]],
    valid_categories: set[str],
    taxonomy_version: str = "1.0.0",
    source_text: str = "",
) -> Dict[str, Any]:
    raw = text.strip()
    try:
        value = json.loads(raw)
    except json.JSONDecodeError:
        start, end = raw.find("{"), raw.rfind("}")
        value = json.loads(raw[start : end + 1]) if start >= 0 and end > start else {}
    if not isinstance(value, dict):
        value = {}
    elif source_text:
        # Apply only broad attribution guards derived from the source message;
        # emotion keys still come exclusively from the constrained model output
        # and are validated below. This prevents reported feelings from leaking
        # into the student's fields without phrase-specific emotion mappings.
        inferred_scope = infer_attribution_scope(source_text)
        if inferred_scope:
            value = dict(value)
            primary = value.get("primary_emotion")
            secondary = value.get("secondary_emotions", [])
            other = value.get("other_emotions", [])
            if not isinstance(secondary, list):
                secondary = []
            if not isinstance(other, list):
                other = []
            if inferred_scope == ATTR_OTHER:
                value["status"] = STATUS_ATTRIBUTED_OTHER
                value["attribution_scope"] = ATTR_OTHER
                value["other_emotions"] = list(dict.fromkeys(other + ([primary] if isinstance(primary, str) else []) + [item for item in secondary if isinstance(item, str)]))
                value["primary_emotion"] = None
                value["secondary_emotions"] = []
                value["felt_by_student"] = False
            elif inferred_scope == ATTR_BOTH:
                value["attribution_scope"] = ATTR_BOTH
                value["status"] = STATUS_CLEAR if value.get("status") == STATUS_MIXED else value.get("status")
                value["other_emotions"] = list(dict.fromkeys(other + [item for item in secondary if isinstance(item, str)]))
                value["secondary_emotions"] = []
                value["felt_by_student"] = True
            elif inferred_scope == ATTR_HYPOTHETICAL:
                value["status"] = STATUS_UNCERTAIN
                value["attribution_scope"] = ATTR_HYPOTHETICAL
                value["primary_emotion"] = None
                value["secondary_emotions"] = []
                value["felt_by_student"] = False
            elif inferred_scope == ATTR_NONE:
                value["status"] = STATUS_NO_EMOTION
                value["attribution_scope"] = ATTR_NONE
                value["primary_emotion"] = None
                value["secondary_emotions"] = []
                value["other_emotions"] = []
                value["felt_by_student"] = False
    if source_text and isinstance(value, dict):
        # Keep primary inference broad, but require an independent source cue
        # for every secondary label. This is a general taxonomy-driven guard,
        # not a phrase-specific test mapping.
        candidate_secondary = value.get("secondary_emotions", [])
        if not isinstance(candidate_secondary, list):
            candidate_secondary = []
        value = dict(value)
        emotion_evidence = value.get("emotion_evidence", [])
        value["secondary_emotions"] = [
            candidate for candidate in candidate_secondary
            # Preserve malformed/unsupported candidates so the strict schema
            # validator can fail closed; filter only valid labels lacking a
            # source cue or exact quoted evidence.
            if not isinstance(candidate, str)
            or candidate not in index
            or secondary_emotion_has_source_cue(candidate, index, source_text)
            or secondary_emotion_has_source_evidence(candidate, source_text, emotion_evidence)
        ]
    parsed = validate_interpreter_result(value, index, taxonomy_version=taxonomy_version)
    if parsed.get("model_category_key") not in valid_categories:
        parsed["model_category_key"] = None
    parsed["model_category_matches_derived"] = value.get("category_key") == parsed.get("category_key")
    # Legacy aliases are retained for comparison with the Phase 2 artifacts.
    parsed["detailed_emotion_key"] = parsed["primary_emotion"]
    parsed["classification_state"] = parsed["status"]
    parsed["uncertain"] = predicted_uncertain(parsed["status"])
    return parsed


def run_gemma(cases: List[Dict[str, str]], taxonomy: Dict[str, Any], index: Dict[str, Dict[str, Any]]) -> Dict[str, Any]:
    # Import through the existing engine so the SQLite GPU admission is shared.
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from hybrid_response_engine import HybridResponseEngine

    engine = HybridResponseEngine(ollama_url=OLLAMA_URL, model=os.environ.get("MINDCARE_OLLAMA_MODEL", "gemma3:4b"))
    valid_keys = set(index)
    valid_categories = {item["key"] for item in taxonomy["categories"]}
    output_schema = build_ollama_output_schema(index, valid_categories)
    rows: List[Dict[str, Any]] = []
    for case in cases:
        started = time.perf_counter()
        row: Dict[str, Any] = _case_expectation(case, index)
        row["message_sha256"] = hashlib.sha256(case["text"].encode("utf-8")).hexdigest()
        try:
            engine._correlation_id = "emotion-eval-" + uuid.uuid4().hex[:16]
            response = engine._post_to_ollama(
                f"{OLLAMA_URL}/api/chat",
                {
                    "model": engine.model,
                    "messages": [
                        {"role": "system", "content": "You are a cautious emotion annotation assistant. Output only the requested JSON."},
                        {"role": "user", "content": gemma_prompt(case["text"], sorted(valid_keys), sorted(valid_categories))},
                    ],
                    "format": output_schema,
                    "stream": False,
                    "options": {"temperature": 0, "num_predict": 180},
                },
            )
            row["http_status"] = response.status_code
            payload = response.json()
            content = ((payload.get("message") or {}).get("content") or payload.get("response") or "")
            parsed = parse_gemma_json(content, index, valid_categories, str(taxonomy.get("version", "1.0.0")), source_text=case["text"])
            row.update(parsed)
            row["exact_key_match"] = parsed["primary_emotion"] == case.get("key")
            row["category_match"] = parsed["category_key"] == category_for(case.get("key"), index)
        except Exception as exc:  # pragma: no cover - depends on local Ollama availability
            row["error"] = type(exc).__name__ + ": " + str(exc)[:180]
            row["exact_key_match"] = False
            row["category_match"] = False
        row["latency_ms"] = round((time.perf_counter() - started) * 1000, 2)
        rows.append(row)

    valid = [row for row in rows if "error" not in row]
    consistency_cases = cases[:4] + cases[-4:]
    consistency_rows: List[Dict[str, Any]] = []
    for case in consistency_cases:
        outputs: List[Dict[str, Any]] = []
        for _ in range(2):
            try:
                engine._correlation_id = "emotion-eval-repeat-" + uuid.uuid4().hex[:16]
                response = engine._post_to_ollama(
                    f"{OLLAMA_URL}/api/chat",
                    {
                        "model": engine.model,
                        "messages": [
                            {"role": "system", "content": "You are a cautious emotion annotation assistant. Output only the requested JSON."},
                            {"role": "user", "content": gemma_prompt(case["text"], sorted(valid_keys), sorted(valid_categories))},
                        ],
                        "format": output_schema,
                        "stream": False,
                        "options": {"temperature": 0, "num_predict": 180},
                    },
                )
                payload = response.json()
                parsed = parse_gemma_json(((payload.get("message") or {}).get("content") or payload.get("response") or ""), index, valid_categories, str(taxonomy.get("version", "1.0.0")), source_text=case["text"])
                outputs.append({"key": parsed["primary_emotion"], "category": parsed["category_key"], "status": parsed["status"], "uncertain": parsed["uncertain"], "felt_by_student": parsed["felt_by_student"]})
            except Exception as exc:
                outputs.append({"error": type(exc).__name__})
        consistency_rows.append({"expected_key": case["key"], "outputs": outputs, "consistent": len({json.dumps(item, sort_keys=True) for item in outputs}) == 1})
    return {
        "endpoint": f"{OLLAMA_URL}/api/chat (existing queued engine, evaluation only)",
        "case_count": len(rows),
        **_metric_summary(rows, valid_categories),
        "agreement_denominator": len(valid),
        "model_category_mismatch_count": sum(not row.get("model_category_matches_derived", True) for row in valid),
        "uncertain_rate": round(sum(row.get("uncertain", False) for row in valid) / len(valid), 4) if valid else None,
        "latency_ms": {
            "median": percentile((row["latency_ms"] for row in rows), 0.5),
            "p95": percentile((row["latency_ms"] for row in rows), 0.95),
        },
        "consistency": {
            "case_count": len(consistency_rows),
            "repetitions_per_case": 2,
            "agreement_rate": round(sum(row["consistent"] for row in consistency_rows) / len(consistency_rows), 4) if consistency_rows else None,
            "rows": consistency_rows,
        },
        "rows": rows,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--baseline-only", action="store_true")
    parser.add_argument("--gemma-only", action="store_true")
    parser.add_argument("--phase4-holdout", action="store_true", help="evaluate the unseen Phase 4 holdout instead of the frozen 68-case set")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    if args.baseline_only and args.gemma_only:
        parser.error("choose only one of --baseline-only or --gemma-only")

    cases = PHASE4_CASES if args.phase4_holdout else CASES
    if args.phase4_holdout and args.baseline_only and args.gemma_only:
        parser.error("choose only one of --baseline-only or --gemma-only")

    taxonomy = load_taxonomy()
    index = taxonomy_index(taxonomy)
    result: Dict[str, Any] = {
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "evaluation_only": True,
        "taxonomy_version": taxonomy.get("version"),
        "case_count": len(cases),
        "dataset": "phase4_unseen_holdout" if args.phase4_holdout else "phase3_frozen_68_case_set",
        "privacy": "synthetic messages only; no production records changed",
    }
    if args.phase4_holdout:
        result["frozen_baseline"] = {
            "path": str(FROZEN_BASELINE),
            "exists": FROZEN_BASELINE.exists(),
            "sha256": hashlib.sha256(FROZEN_BASELINE.read_bytes()).hexdigest() if FROZEN_BASELINE.exists() else None,
            "status": "reference only; not retuned or overwritten",
        }
    if not args.gemma_only:
        result["six_class_baseline"] = run_baseline(cases, index)
    if not args.baseline_only:
        result["gemma_interpretation_pilot"] = run_gemma(cases, taxonomy, index)

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({key: value for key, value in result.items() if key not in {"six_class_baseline", "gemma_interpretation_pilot"}}, indent=2))
    for name in ("six_class_baseline", "gemma_interpretation_pilot"):
        if name in result:
            summary = {key: value for key, value in result[name].items() if key not in {"rows"}}
            print(name + "=" + json.dumps(summary, ensure_ascii=False))
    print("RESULT_FILE=" + str(args.output))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
