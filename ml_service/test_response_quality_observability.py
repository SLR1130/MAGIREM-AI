import io
import os
import pathlib
import sys
import unittest
from unittest.mock import patch
from contextlib import redirect_stderr

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
os.environ['MINDCARE_DIAGNOSTICS_ENABLED'] = 'true'

from diagnostics import emit_diagnostic
from hybrid_response_engine import HybridResponseEngine


class ResponseQualityObservabilityTests(unittest.TestCase):
    def setUp(self):
        self.engine = HybridResponseEngine(ollama_url='http://127.0.0.1:9')

    def test_explicit_tagalog_request_wins(self):
        result = self.engine.detect_message_language(
            'Please reply in Tagalog tungkol sa bayarin ko.', []
        )
        self.assertEqual(result['detected_language'], 'tl')
        self.assertEqual(result['requested_language'], 'tl')

    def test_taglish_stress_is_mixed(self):
        result = self.engine.detect_message_language(
            'Na-stress ako sa tuition and I need some help.', []
        )
        self.assertEqual(result['detected_language'], 'taglish')

    def test_fresh_taglish_casual_message_is_not_forced_into_counseling(self):
        message = 'Awit bro, sobrang hassle ng groupmates ko.'
        self.assertEqual(self.engine.detect_message_language(message, [])['detected_language'], 'taglish')
        self.assertEqual(self.engine._request_mode(message, []), 'conversation')

    def test_explicit_venting_prefers_active_listening(self):
        self.assertEqual(
            self.engine._request_mode('Gusto ko lang mag-rant, ang bigat ng araw ko.', []),
            'venting',
        )
        guidance = self.engine._request_mode_guidance('venting')
        self.assertIn('do not rush into a list of fixes', guidance)

    def test_taglish_and_filipino_practical_and_knowledge_questions_route_directly(self):
        self.assertEqual(
            self.engine._request_mode('Tatlong exams ko bukas. Paano ko pagkakasyahin review ko?', []),
            'practical',
        )
        self.assertEqual(
            self.engine._request_mode('Paano gumagana ang amygdala?', []),
            'knowledge',
        )

    def test_interest_request_and_verified_feature_hint_are_contextual(self):
        self.assertEqual(
            self.engine._request_mode('Mahilig ako mag-drawing. Anong pwede kong gawin para mag-relax?', []),
            'interest_activity',
        )
        journal_hint = self.engine._verified_feature_guidance('Gusto ko sana isulat lahat ng iniisip ko.')
        self.assertIn('private Journal', journal_hint)
        self.assertEqual(self.engine._verified_feature_guidance('What is the amygdala?'), '')
    def test_short_follow_up_keeps_previous_language(self):
        history = [{
            'user': 'Mabigat ang bayarin ko at hindi ko alam ang gagawin.',
            'ai': 'Naiintindihan ko.',
        }]
        result = self.engine.detect_message_language('Okay.', history)
        self.assertEqual(result['detected_language'], 'tl')
        self.assertTrue(result['continuity_applied'])

    def test_cleanup_preserves_paragraphs_and_lists(self):
        response = "Start here.\n\n1. First step\n2. Second step\n\n- Another option\n\n**Important:** Keep going."
        self.assertEqual(self.engine._clean_response(response), response)
    def test_diagnostic_event_contains_metadata_only(self):
        stream = io.StringIO()
        with redirect_stderr(stream):
            emit_diagnostic(
                'test',
                'quality_probe',
                'synthetic-correlation',
                message_length=24,
                response_source='gemma',
            )
        output = stream.getvalue()
        self.assertIn('synthetic-correlation', output)
        self.assertIn('message_length', output)
        self.assertNotIn('student message', output)

    def test_diagnostics_are_disabled_without_explicit_flag(self):
        previous = os.environ.get('MINDCARE_DIAGNOSTICS_ENABLED')
        os.environ['MINDCARE_DIAGNOSTICS_ENABLED'] = 'false'
        try:
            stream = io.StringIO()
            with redirect_stderr(stream):
                emit_diagnostic('test', 'disabled', 'synthetic-correlation')
            self.assertEqual(stream.getvalue(), '')
        finally:
            if previous is None:
                os.environ.pop('MINDCARE_DIAGNOSTICS_ENABLED', None)
            else:
                os.environ['MINDCARE_DIAGNOSTICS_ENABLED'] = previous



    def test_structured_request_detection_is_explicit(self):
        spec = self.engine._structured_request_spec(
            'Give me five detailed practical steps for managing academic and financial stress. Include a heading.'
        )
        self.assertIsNotNone(spec)
        self.assertEqual(spec['requested_steps'], 5)
        self.assertTrue(spec['require_title'])
        self.assertEqual(spec['schema']['properties']['steps']['minItems'], 5)
        self.assertEqual(spec['schema']['properties']['steps']['maxItems'], 5)
        self.assertIn('title', spec['schema']['required'])
        self.assertIsNone(self.engine._structured_request_spec('I feel overwhelmed today.'))

    def test_structured_response_validates_count_and_formats_safely(self):
        content = '{"title":"A plan","introduction":"Start with one small change.","steps":[' \
                  '{"heading":"Prioritize","explanation":"Choose one task."},' \
                  '{"heading":"Schedule","explanation":"Set a short block."}' \
                  '],"closing":"Review the plan tomorrow."}'
        parsed = self.engine._validate_structured_response(content, 2)
        self.assertIsNotNone(parsed)
        markdown = self.engine._structured_to_markdown(parsed)
        self.assertIn('## A plan', markdown)
        self.assertEqual(markdown.count('\n1. '), 1)
        self.assertEqual(markdown.count('\n2. '), 1)
        self.assertIsNone(self.engine._validate_structured_response(content, 5))

    def test_structured_schema_has_required_safe_shape(self):
        schema = self.engine.STRUCTURED_RESPONSE_SCHEMA
        self.assertEqual(schema['type'], 'object')
        self.assertEqual(schema['required'], ['introduction', 'steps'])
        self.assertFalse(schema['additionalProperties'])
        self.assertFalse(schema['properties']['steps']['items']['additionalProperties'])
    def test_recent_session_context_is_sent_in_order_and_capped(self):
        class FakeResponse:
            status_code = 200

            @staticmethod
            def json():
                return {'message': {'content': 'Focus first on the deadlines you named, then make a short plan for the next task.'}}

        history = [
            {'user': f'older user {index}', 'ai': f'older assistant {index}'}
            for index in range(1, 6)
        ]
        with patch('hybrid_response_engine.requests.post', return_value=FakeResponse()) as post:
            result = self.engine.generate('What should I prioritize?', 'anxiety', 0.8, history)

        self.assertTrue(result['success'])
        messages = post.call_args.kwargs['json']['messages']
        sent_history = messages[1:-1]
        self.assertEqual(len(sent_history), 10)
        self.assertEqual(sent_history[0]['content'], 'older user 1')
        self.assertEqual(sent_history[-1]['content'], 'older assistant 5')
        self.assertEqual(messages[-1]['content'], 'What should I prioritize?')

    def test_english_planning_turn_is_not_misclassified_as_taglish(self):
        result = self.engine.detect_message_language(
            'Can you turn that into a practical plan for this week?', []
        )
        self.assertEqual(result['detected_language'], 'en')
    def test_immediate_deadline_guard_rejects_invented_calendar_details(self):
        message = 'Tatlong exams ko bukas. Paano ko pagkakasyahin review ko?'
        response = 'Unahin ang pinakamahirap na subject. Exam 1 ay Sabado 8 AM, tapos Exam 2 ay Sabado 12 NN.'
        self.assertEqual(
            self.engine._contextual_quality_retry_reason('practical', message, response),
            'invented_calendar_schedule',
        )
        stated = 'Tatlong exams ko bukas, Sabado 8 AM ang una. Paano ko pagkakasyahin review ko?'
        self.assertIsNone(
            self.engine._contextual_quality_retry_reason('practical', stated, response)
        )

    def test_immediate_academic_request_requires_an_action_before_a_question(self):
        message = 'Tatlong exams ko bukas. Paano ko pagkakasyahin review ko?'
        question_only = 'Ano ang mga subject ng exams mo? Mayroon ka bang oras para mag-review?'
        self.assertEqual(
            self.engine._contextual_quality_retry_reason('practical', message, question_only),
            'academic_plan_without_first_pass',
        )
        actionable = 'Unahin ang pinakamahirap na subject, hatiin ang topics, at mag-focus sa practice questions.'
        self.assertIsNone(
            self.engine._contextual_quality_retry_reason('practical', message, actionable)
        )
    def test_verified_journal_hint_reaches_the_actual_system_message(self):
        class FakeResponse:
            status_code = 200

            @staticmethod
            def json():
                return {'message': {'content': 'You can use the private Journal if writing it down feels useful.'}}

        with patch('hybrid_response_engine.requests.post', return_value=FakeResponse()) as post:
            result = self.engine.generate('Gusto ko sana isulat lahat ng iniisip ko.', 'neutral', 0.51, [])

        self.assertTrue(result['success'])
        system_message = post.call_args.kwargs['json']['messages'][0]['content']
        self.assertIn('VERIFIED IN-APP OPTION: MAGIREM AI has a private Journal', system_message)
        self.assertNotIn('Coping Toolkit', system_message)
    def test_quality_retry_detects_planning_without_first_pass(self):
        response = (
            "That sounds difficult. Would you like to tell me more about what is stressing you?"
        )
        self.assertEqual(
            self.engine._quality_retry_reason(
                "I don't know where to begin with my school expenses.", response
            ),
            'planning_without_first_pass',
        )

    def test_quality_retry_ignores_echoed_facts_as_advice(self):
        response = (
            "It is understandable to feel overwhelmed with school expenses. Let’s see if we can break "
            "things down. Would you like to talk about what is contributing to these expenses?"
        )
        self.assertEqual(
            self.engine._quality_retry_reason(
                "I don't know where to begin with my school expenses.", response
            ),
            'planning_without_first_pass',
        )
    def test_quality_retry_does_not_treat_generic_first_step_as_advice(self):
        response = (
            "It is okay not to know where to begin. Taking this one step at a time is the first step. "
            "Would you like to tell me what is contributing to the expenses?"
        )
        self.assertEqual(
            self.engine._quality_retry_reason(
                "I don't know where to begin with my school expenses.", response
            ),
            'planning_without_first_pass',
        )
    def test_quality_retry_allows_advice_before_optional_question(self):
        response = (
            "Start with essentials: list transportation, meals, and school requirements. "
            "Would you like to share the rough weekly amounts?"
        )
        self.assertIsNone(
            self.engine._quality_retry_reason('What should I prioritize?', response)
        )

    def test_quality_retry_detects_unqualified_purchase_advice(self):
        response = "Given your situation, it would be best to postpone buying the phone."
        self.assertEqual(
            self.engine._quality_retry_reason("I'm planning to buy a new phone.", response),
            'unqualified_purchase_advice',
        )

    def test_quality_retry_detects_soft_unqualified_purchase_advice(self):
        response = (
            "Given the financial pressures, buying a new phone this week might not be the best approach."
        )
        self.assertEqual(
            self.engine._quality_retry_reason("I'm planning to buy a new phone.", response),
            'unqualified_purchase_advice',
        )
    def test_quality_retry_detects_step_backward_purchase_advice(self):
        response = (
            "Given your current financial challenges, purchasing a new phone this week would likely be a step backward."
        )
        self.assertEqual(
            self.engine._quality_retry_reason("I'm planning to buy a new phone.", response),
            'unqualified_purchase_advice',
        )
    def test_quality_retry_requires_purchase_decision_framework(self):
        response = 'That sounds stressful. Would you like to tell me more?'
        self.assertEqual(
            self.engine._quality_retry_reason('My allowance is limited and I am planning to buy a new phone.', response),
            'purchase_without_decision_framework',
        )
    def test_quality_retry_allows_conditional_purchase_guidance(self):
        response = (
            "Whether to buy depends on whether your current phone is reliable enough and whether "
            "the cost is affordable after essentials."
        )
        self.assertIsNone(
            self.engine._quality_retry_reason("I'm planning to buy a new phone.", response)
        )
    def test_quality_retry_is_one_bounded_model_call(self):
        class FakeResponse:
            status_code = 200

            def __init__(self, content):
                self._content = content

            def json(self):
                return {'message': {'content': self._content}}

        responses = [
            FakeResponse("That sounds difficult. Would you like to tell me more?"),
            FakeResponse(
                "Start with essentials: list transportation, meals, and school requirements. "
                "Then track those costs for the next few days."
            ),
        ]
        with patch('hybrid_response_engine.requests.post', side_effect=responses) as post:
            result = self.engine.generate(
                "I don't know where to begin with my school expenses.",
                'anxiety', 0.8, []
            )
        self.assertTrue(result['success'])
        self.assertEqual(post.call_count, 2)
        self.assertTrue(result['analysis']['quality_recovery_attempted'])
        self.assertTrue(result['analysis']['quality_recovery_applied'])
        self.assertIn('transportation', result['response'].lower())
    def test_taglish_prompt_preserves_student_style_without_slang_bias(self):
        prompt = self.engine.get_system_prompt_for_language('taglish', 'anxiety', 0.7, 'help_request')
        self.assertIn('Use natural Taglish when the student materially mixes Tagalog and English', prompt)
        self.assertIn('do not switch entirely to English', prompt)
        self.assertIn('Do not add filler slang', prompt)
        self.assertNotIn('Filipino talking casually', prompt)
    def test_explicit_positive_words_override_conflicting_tone_only(self):
        guidance = self.engine._generation_guidance('I am happy about my scholarship.', 'sadness')
        self.assertEqual(guidance['kind'], 'positive')
        self.assertEqual(guidance['tone_emotion'], 'joy')
        system = self.engine.get_system_prompt_for_language('en', 'sadness', 0.9, 'statement', 'joy')
        self.assertIn('Be upbeat and positive', system)
        self.assertNotIn('Be empathetic and understanding. Validate their feelings.', system)

    def test_explicit_concern_does_not_inherit_positive_tone(self):
        guidance = self.engine._generation_guidance('I feel overwhelmed by school expenses.', 'joy')
        self.assertEqual(guidance['kind'], 'concern')
        self.assertEqual(guidance['tone_emotion'], 'neutral')

    def test_fallback_uses_explicit_positive_wording(self):
        result = self.engine._fallback_response('I am happy about passing my exam.', 'sadness', 0.92)
        self.assertIn('wonderful', result['response'].lower())
        self.assertNotIn('sadness', result['response'].lower())

    def test_conflicting_emotion_does_not_add_emoji(self):
        from response_formatter import should_add_emoji
        self.assertFalse(should_add_emoji('sadness', 0.92, 'That is good news.', 'I am happy about my scholarship.'))
        self.assertTrue(should_add_emoji('joy', 0.92, 'That is good news.', 'I am happy about my scholarship.'))
    def test_compact_budgets_keep_practical_help_bounded_but_creative_output_available(self):
        practical = self.engine._response_token_budget(
            'Can you make a plan for my three exams tomorrow?', False, 'practical'
        )
        creative = self.engine._response_token_budget(
            'Write a short story about starting over.', False, 'creative'
        )
        self.assertEqual(practical, self.engine.practical_max_tokens)
        self.assertEqual(creative, min(self.engine.default_max_tokens, 320))
        self.assertLessEqual(creative, practical)

    def test_application_identity_context_is_sent_only_when_the_question_requires_it(self):
        class FakeResponse:
            status_code = 200

            @staticmethod
            def json():
                return {'message': {'content': 'Here is a direct answer.'}}

        with patch('hybrid_response_engine.requests.post', return_value=FakeResponse()) as post:
            self.engine.generate('Can you help me organize my review?', 'neutral', 0.5, [])
        normal_system = post.call_args.kwargs['json']['messages'][0]['content']
        self.assertNotIn('APPLICATION IDENTITY', normal_system)

        with patch('hybrid_response_engine.requests.post', return_value=FakeResponse()) as post:
            self.engine.generate('Who developed MinSuansCore?', 'neutral', 0.5, [])
        identity_system = post.call_args.kwargs['json']['messages'][0]['content']
        self.assertIn('APPLICATION IDENTITY', identity_system)
    def test_creative_preface_is_removed_without_a_second_generation(self):
        self.assertEqual(
            self.engine._strip_creative_preface("Okay, here's a short poem for you:\nA quiet dawn begins."),
            'A quiet dawn begins.',
        )
        self.assertEqual(
            self.engine._strip_creative_preface('A quiet dawn begins.'),
            'A quiet dawn begins.',
        )
    def test_creative_closing_is_removed_without_a_second_generation(self):
        self.assertEqual(
            self.engine._strip_creative_preface('A quiet dawn begins.\n\n*** Would you like me to create something else for you?'),
            'A quiet dawn begins.',
        )
    def test_creative_craft_leadin_is_removed_without_another_generation(self):
        self.assertEqual(
            self.engine._strip_creative_preface("Okay, let's craft a hopeful poem for you!\nThe week turns toward a brighter dawn."),
            'The week turns toward a brighter dawn.',
        )
    def test_compound_creative_wrapper_and_generic_closing_are_removed(self):
        wrapped = (
            "Okay, let's get this done! Here's a poem for you: The week is done.\n\n"
            "--- Would you like to explore any related ideas or perhaps create a different kind of poem?"
        )
        self.assertEqual(self.engine._strip_creative_preface(wrapped), 'The week is done.')
        meaningful = "Okay, this is a meaningful opening, not a hand-off."
        self.assertEqual(self.engine._strip_creative_preface(meaningful), meaningful)
    def test_study_book_request_uses_factual_recommendation_mode(self):
        message = 'Can you recommend three books for building study habits?'
        self.assertEqual(self.engine._request_mode(message, []), 'recommendation')
        guidance = self.engine._request_mode_guidance('recommendation')
        self.assertIn('Atomic Habits by James Clear', guidance)
        self.assertIn('Never invent a title, author', guidance)
    def test_bracketed_conversational_filler_is_removed_but_meaningful_brackets_remain(self):
        self.assertEqual(
            self.engine._strip_bracketed_interjections("[Talaga!] Pagkatapos nito, magpahinga ka muna. [Okay ba ‘yun?]"),
            "Pagkatapos nito, magpahinga ka muna.",
        )
        self.assertEqual(
            self.engine._strip_bracketed_interjections("Use [0, 1] as the valid range. [quoted] is part of the answer."),
            "Use [0, 1] as the valid range. [quoted] is part of the answer.",
        )
        self.assertEqual(
            self.engine._strip_bracketed_interjections("```python\nvalues = [1, 2]\n```"),
            "```python\nvalues = [1, 2]\n```",
        )

    def test_fallback_reason_codes_are_private_and_distinct(self):
        timeout = self.engine._fallback_response('ordinary question', 'neutral', 0.5, 'timeout')
        safety = self.engine._fallback_response('ordinary question', 'neutral', 0.5, 'safety_rejection')
        self.assertEqual(timeout['fallback_reason'], 'timeout')
        self.assertEqual(safety['fallback_reason'], 'safety_rejection')
        self.assertNotIn('ordinary question', timeout['response'])
        self.assertNotIn('fallback_reason', timeout['response'])
if __name__ == '__main__':
    unittest.main()




