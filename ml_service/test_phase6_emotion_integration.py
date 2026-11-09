import os
import unittest
from pathlib import Path

from detailed_emotion_shadow import DetailedEmotionShadowInterpreter, DetailedShadowUnavailable
from phase6_run_final_evaluation import _load_adjudicated


class Phase6EmotionIntegrationTest(unittest.TestCase):
    def setUp(self):
        self.previous = os.environ.get("MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED")
        self.previous_approved = os.environ.get("MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED")
        os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED"] = "false"

    def tearDown(self):
        if self.previous is None:
            os.environ.pop("MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED", None)
        else:
            os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED"] = self.previous
        if self.previous_approved is None:
            os.environ.pop("MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED", None)
        else:
            os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED"] = self.previous_approved

    def test_shadow_is_disabled_by_default(self):
        os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED"] = "false"
        interpreter = DetailedEmotionShadowInterpreter()
        self.assertFalse(interpreter.enabled)
        with self.assertRaises(DetailedShadowUnavailable) as raised:
            interpreter.assess("I feel okay.", consent_granted=True, request_id="phase6-test-001")
        self.assertEqual(str(raised.exception), "shadow_disabled")

    def test_shadow_requires_explicit_consent_even_when_flagged(self):
        os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED"] = "true"
        os.environ["MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED"] = "true"
        interpreter = DetailedEmotionShadowInterpreter()
        with self.assertRaises(DetailedShadowUnavailable) as raised:
            interpreter.assess("I feel okay.", consent_granted=False, request_id="phase6-test-002")
        self.assertEqual(str(raised.exception), "shadow_consent_required")

    def test_final_runner_rejects_phase5_draft_dataset(self):
        draft = Path(__file__).with_name("phase5_final_unseen_cases.json")
        with self.assertRaises(SystemExit) as raised:
            _load_adjudicated(draft)
        self.assertIn("status=adjudicated", str(raised.exception))


if __name__ == "__main__":
    unittest.main()
