import json
import unittest
from pathlib import Path

from phase2_emotion_feasibility_eval import parse_gemma_json
from emotion_interpreter_schema import (
    ATTR_BOTH,
    ATTR_HYPOTHETICAL,
    ATTR_NONE,
    STATUS_ATTRIBUTED_OTHER,
    STATUS_CLEAR,
    STATUS_MIXED,
    STATUS_NO_EMOTION,
    STATUS_UNCERTAIN,
    build_ollama_output_schema,
    infer_attribution_scope,
    validated_response_context,
    validate_interpreter_result,
)


ROOT = Path(__file__).resolve().parents[1]
TAXONOMY = json.loads((ROOT / "config" / "emotion_taxonomy.json").read_text(encoding="utf-8"))
INDEX = {item["key"]: item for item in TAXONOMY["emotions"]}


class EmotionInterpreterSchemaTests(unittest.TestCase):
    def test_ollama_schema_uses_only_approved_taxonomy_keys(self):
        schema = build_ollama_output_schema(INDEX, {item["key"] for item in TAXONOMY["categories"]})
        self.assertFalse(schema["additionalProperties"])
        self.assertEqual(set(schema["properties"]["primary_emotion"]["anyOf"][0]["enum"]), set(INDEX))
        self.assertEqual(set(schema["properties"]["secondary_emotions"]["items"]["enum"]), set(INDEX))
        self.assertIn({"type": "null"}, schema["properties"]["primary_emotion"]["anyOf"])

    def test_source_attribution_guard_is_broad_and_non_emotional(self):
        self.assertEqual(infer_attribution_scope("A friend said she feels anxious; I want to support her."), "other")
        self.assertEqual(infer_attribution_scope("I feel hopeful, while my sister is panicking."), "both")
        self.assertEqual(infer_attribution_scope("If the interview goes badly, I might feel embarrassed."), "hypothetical")
        self.assertEqual(infer_attribution_scope("Can you explain what stress means?"), ATTR_NONE)
        self.assertNotEqual(infer_attribution_scope("Wala akong makausap pagkatapos ng klase."), ATTR_NONE)
        self.assertEqual(infer_attribution_scope("Wala akong nararamdaman ngayon."), ATTR_NONE)

    def test_every_approved_label_derives_one_authoritative_category(self):
        for key, item in INDEX.items():
            result = validate_interpreter_result(
                {"status": "clear", "primary_emotion": key, "secondary_emotions": [], "felt_by_student": True, "category_key": "not_authoritative"},
                INDEX,
                taxonomy_version=TAXONOMY["version"],
            )
            self.assertTrue(result["schema_valid"], key)
            self.assertEqual(result["primary_emotion"], key)
            self.assertEqual(result["category_key"], item["category_key"])
            self.assertNotEqual(result["category_key"], "not_authoritative")
            self.assertNotIn("confidence", result)

    def test_invalid_labels_and_secondary_values_are_rejected(self):
        invalid = validate_interpreter_result(
            {"status": "clear", "primary_emotion": "invented_emotion", "secondary_emotions": ["not_a_label"], "felt_by_student": True},
            INDEX,
            taxonomy_version="1.0.0",
        )
        self.assertFalse(invalid["schema_valid"])
        self.assertIsNone(invalid["primary_emotion"])
        self.assertIsNone(invalid["category_key"])

    def test_mixed_and_uncertain_have_stable_statuses(self):
        mixed = validate_interpreter_result(
            {"status": "mixed", "primary_emotion": "happiness_joy", "secondary_emotions": ["anxiety"], "felt_by_student": True}, INDEX, taxonomy_version="1.0.0"
        )
        uncertain = validate_interpreter_result(
            {"status": "uncertain", "primary_emotion": None, "secondary_emotions": [], "felt_by_student": True, "uncertainty_reason": "ambiguous wording"}, INDEX, taxonomy_version="1.0.0"
        )
        self.assertEqual(mixed["status"], STATUS_MIXED)
        self.assertEqual(mixed["category_key"], "positive_wellbeing")
        self.assertEqual(uncertain["status"], STATUS_UNCERTAIN)
        self.assertIsNone(uncertain["category_key"])

    def test_clear_with_two_student_labels_is_canonicalized_to_mixed(self):
        result = validate_interpreter_result(
            {"status": "clear", "attribution_scope": "student", "primary_emotion": "gratitude", "secondary_emotions": ["loneliness"], "felt_by_student": True},
            INDEX,
            taxonomy_version="1.0.0",
        )
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], STATUS_MIXED)

    def test_attribution_and_no_emotion_never_become_neutral(self):
        attributed = validate_interpreter_result(
            {"status": "attributed_other", "primary_emotion": "sadness", "secondary_emotions": [], "felt_by_student": True}, INDEX, taxonomy_version="1.0.0"
        )
        no_emotion = validate_interpreter_result(
            {"status": "no_emotion", "primary_emotion": "neutral", "secondary_emotions": [], "felt_by_student": True}, INDEX, taxonomy_version="1.0.0"
        )
        self.assertEqual(attributed["status"], STATUS_ATTRIBUTED_OTHER)
        self.assertFalse(attributed["felt_by_student"])
        self.assertIsNone(attributed["primary_emotion"])
        self.assertEqual(no_emotion["status"], STATUS_NO_EMOTION)
        self.assertIsNone(no_emotion["category_key"])

    def test_both_and_hypothetical_attribution_are_explicit(self):
        both = validate_interpreter_result(
            {
                "status": "mixed",
                "attribution_scope": ATTR_BOTH,
                "primary_emotion": "excitement",
                "secondary_emotions": ["anxiety"],
                "other_emotions": ["sadness"],
                "felt_by_student": True,
            }, INDEX, taxonomy_version="1.0.0"
        )
        hypothetical = validate_interpreter_result(
            {
                "status": "clear",
                "attribution_scope": ATTR_HYPOTHETICAL,
                "primary_emotion": "fear",
                "secondary_emotions": [],
                "felt_by_student": False,
            }, INDEX, taxonomy_version="1.0.0"
        )
        self.assertTrue(both["schema_valid"])
        self.assertEqual(both["attribution_scope"], ATTR_BOTH)
        self.assertEqual(both["category_keys"], ["positive_wellbeing", "anxiety_stress"])
        self.assertEqual(both["other_category_keys"], ["sadness_disconnection"])
        self.assertTrue(hypothetical["schema_valid"])
        self.assertEqual(hypothetical["status"], STATUS_UNCERTAIN)
        self.assertIsNone(hypothetical["primary_emotion"])


    def test_unsupported_secondary_is_removed_but_primary_is_preserved(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "shame",
            "secondary_emotions": ["guilt"],
            "other_emotions": [],
            "felt_by_student": True,
            "evidence": "nahihiya ako kasi nagkamali ako",
        })
        result = parse_gemma_json(
            raw,
            INDEX,
            {item["key"] for item in TAXONOMY["categories"]},
            taxonomy_version=TAXONOMY["version"],
            source_text="Sobrang nahihiya ako kasi nagkamali ako sa presentation.",
        )
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], "clear")
        self.assertEqual(result["primary_emotion"], "shame")
        self.assertEqual(result["secondary_emotions"], [])


    def test_invalid_secondary_remains_fail_closed_with_source_text(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "shame",
            "secondary_emotions": ["invented_emotion"],
            "other_emotions": [],
            "felt_by_student": True,
        })
        result = parse_gemma_json(
            raw,
            INDEX,
            {item["key"] for item in TAXONOMY["categories"]},
            taxonomy_version=TAXONOMY["version"],
            source_text="I feel ashamed about the mistake.",
        )
        self.assertFalse(result["schema_valid"])
        self.assertEqual(result["primary_emotion"], "shame")
        self.assertEqual(result["secondary_emotions"], [])
    def test_explicit_taglish_mixed_emotions_are_preserved(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "excitement",
            "secondary_emotions": ["anxiety"],
            "other_emotions": [],
            "felt_by_student": True,
            "evidence": "excited ako pero kinakabahan ako",
        })
        result = parse_gemma_json(
            raw,
            INDEX,
            {item["key"] for item in TAXONOMY["categories"]},
            taxonomy_version=TAXONOMY["version"],
            source_text="Excited ako sa graduation pero kinakabahan ako sa future.",
        )
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], STATUS_MIXED)
        self.assertEqual(result["secondary_emotions"], ["anxiety"])
    def test_response_context_only_uses_student_attributed_validated_emotions(self):
        mixed = validate_interpreter_result(
            {
                "status": "mixed",
                "attribution_scope": ATTR_BOTH,
                "primary_emotion": "excitement",
                "secondary_emotions": ["anxiety"],
                "other_emotions": ["sadness"],
                "felt_by_student": True,
            }, INDEX, taxonomy_version=TAXONOMY["version"]
        )
        mixed["classification_source"] = "gemma_shadow"
        mixed["taxonomy_version"] = TAXONOMY["version"]
        context = validated_response_context(mixed, INDEX, taxonomy_version=TAXONOMY["version"])
        self.assertEqual(context["emotions"], ["excitement", "anxiety"])
        self.assertNotIn("sadness", context["emotions"])

        attributed = validate_interpreter_result(
            {
                "status": "attributed_other",
                "attribution_scope": "other",
                "primary_emotion": None,
                "secondary_emotions": [],
                "felt_by_student": False,
            }, INDEX, taxonomy_version=TAXONOMY["version"]
        )
        self.assertIsNone(validated_response_context(attributed, INDEX, taxonomy_version=TAXONOMY["version"]))

        invalid = dict(mixed)
        invalid["schema_valid"] = False
        self.assertIsNone(validated_response_context(invalid, INDEX, taxonomy_version=TAXONOMY["version"]))



    def test_schema_requires_exact_per_emotion_evidence_array(self):
        schema = build_ollama_output_schema(INDEX, {item["key"] for item in TAXONOMY["categories"]})
        evidence = schema["properties"]["emotion_evidence"]
        self.assertEqual(evidence["items"]["properties"]["emotion"]["enum"], sorted(INDEX))
        self.assertEqual(evidence["items"]["required"], ["emotion", "evidence"])
        self.assertIn("emotion_evidence", schema["required"])

    def test_implicit_mixed_emotion_survives_only_with_exact_source_span(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "excitement",
            "secondary_emotions": ["anxiety"],
            "other_emotions": [],
            "felt_by_student": True,
            "emotion_evidence": [{"emotion": "anxiety", "evidence": "my stomach feels tight"}],
        })
        source = "I passed the exam and feel excited, but my stomach feels tight thinking about next term."
        result = parse_gemma_json(raw, INDEX, {item["key"] for item in TAXONOMY["categories"]}, taxonomy_version=TAXONOMY["version"], source_text=source)
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], STATUS_MIXED)
        self.assertEqual(result["secondary_emotions"], ["anxiety"])

    def test_taglish_implicit_source_span_supports_non_taxonomy_wording(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "happiness_joy",
            "secondary_emotions": ["anxiety"],
            "other_emotions": [],
            "felt_by_student": True,
            "emotion_evidence": [{"emotion": "anxiety", "evidence": "nanlalamig ang kamay ko"}],
        })
        source = "Masaya ako sa resulta, pero nanlalamig ang kamay ko kapag iniisip ang susunod na term."
        result = parse_gemma_json(raw, INDEX, {item["key"] for item in TAXONOMY["categories"]}, taxonomy_version=TAXONOMY["version"], source_text=source)
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], STATUS_MIXED)
        self.assertEqual(result["secondary_emotions"], ["anxiety"])
    def test_implicit_secondary_without_exact_source_span_is_removed(self):
        raw = json.dumps({
            "status": "mixed",
            "attribution_scope": "student",
            "primary_emotion": "shame",
            "secondary_emotions": ["guilt"],
            "other_emotions": [],
            "felt_by_student": True,
            "emotion_evidence": [{"emotion": "guilt", "evidence": "I regret what I did"}],
        })
        source = "Nahihiya ako sa presentation, pero hindi ko maipaliwanag kung bakit."
        result = parse_gemma_json(raw, INDEX, {item["key"] for item in TAXONOMY["categories"]}, taxonomy_version=TAXONOMY["version"], source_text=source)
        self.assertTrue(result["schema_valid"])
        self.assertEqual(result["status"], STATUS_CLEAR)
        self.assertEqual(result["primary_emotion"], "shame")
        self.assertEqual(result["secondary_emotions"], [])

if __name__ == "__main__":
    unittest.main()
