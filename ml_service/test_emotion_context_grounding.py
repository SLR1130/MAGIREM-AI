import pathlib
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

from semantic_analysis import SemanticAnalyzer
from hybrid_response_engine import HybridResponseEngine


class EmotionContextGroundingTests(unittest.TestCase):
    def setUp(self):
        self.semantic = SemanticAnalyzer()
        self.engine = HybridResponseEngine(ollama_url='http://127.0.0.1:9')

    def test_generic_verbs_are_not_joy_evidence(self):
        for text in ('My dog got sick today', 'My phone got broken', 'My dog passed away', 'I made a mistake'):
            result = self.semantic.analyze(text)
            self.assertNotIn('happiness', result['emotion']['supported_labels'], text)
            self.assertNotEqual(result['emotion']['dominant'], 'happiness', text)

    def test_explicit_emotion_still_provides_evidence(self):
        for text, label in [('I feel happy today', 'happiness'), ('I am sad', 'sadness')]:
            self.assertIn(label, self.semantic.analyze(text)['emotion']['supported_labels'])

    def test_illness_does_not_establish_death(self):
        for message in ('My dog is sick today', 'May sakit ang aso ko', 'My cat got injured'):
            self.assertEqual(self.engine._quality_retry_reason(message, 'Losing a pet is painful.'),
                             'unsupported_loss_assumption')

    def test_prior_assistant_guess_is_not_death_evidence(self):
        history = [{'user': 'My dog is sick', 'ai': 'I am sorry your dog died.'}]
        self.assertEqual(self.engine._quality_retry_reason('Still worried', 'Losing a pet is hard.', history=history),
                         'unsupported_loss_assumption')

    def test_confirmed_loss_remains_supported(self):
        history = [{'user': 'My dog was sick and died yesterday', 'ai': 'I am sorry.'}]
        self.assertIsNone(self.engine._quality_retry_reason('I miss him.', 'Losing a pet is hard.', history=history))

    def test_later_confirmed_loss_after_earlier_fear_is_supported(self):
        history = [{'user': 'My dog is sick and I fear he might die.', 'ai': 'I hope he recovers.'},
                   {'user': 'He died yesterday.', 'ai': 'I am sorry.'}]
        self.assertIsNone(self.engine._quality_retry_reason('I miss him.', 'Losing a pet is hard.', history=history))

    def test_assumed_temporary_companion_loss_is_rejected(self):
        self.assertEqual(self.engine._quality_retry_reason('My dog is sick today',
                         'Losing a companion, even temporarily, is painful.'), 'unsupported_loss_assumption')

    def test_speculative_loss_does_not_establish_death(self):
        self.assertEqual(self.engine._quality_retry_reason('My dog is sick and I am afraid he might die.',
                         'Losing a pet is hard.'), 'unsupported_loss_assumption')

    def test_failed_grounding_retry_never_returns_unsupported_loss(self):
        class Response:
            status_code = 200
            def json(self):
                return {'message': {'content': 'Losing a pet is really painful.'}}

        with patch.object(self.engine, '_post_to_ollama', return_value=Response()) as post:
            result = self.engine.generate('My dog is sick today', 'sadness', .36, [])
        self.assertEqual(post.call_count, 2)
        self.assertNotIn('losing', result['response'].lower())
        self.assertEqual(result['analysis']['quality_recovery_reason'], 'unsupported_loss_assumption')


if __name__ == '__main__':
    unittest.main()
