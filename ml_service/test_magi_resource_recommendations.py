import json
import os
import unittest
import uuid
from unittest.mock import Mock, patch

from magi_resource_recommendations import RecommendationError, generate


class MagiResourceRecommendationTests(unittest.TestCase):
    def setUp(self):
        self.messages = ['I am struggling with exam deadlines this week.']
        self.resources = [{'id': 17, 'title': 'Study planning', 'description': 'A guide to study planning.', 'category': 'general'}]
        self.result = {
            'introduction': 'Here is a resource you might find useful.',
            'items': [{'resource_id': 17, 'topic': 'Academic planning', 'evidence': 'exam deadlines', 'reason': 'This guide offers planning ideas.'}],
        }

    def fake_response(self, result):
        response = Mock()
        response.json.return_value = {'message': {'content': json.dumps(result)}}
        return response

    @patch('magi_resource_recommendations.requests.Session.post')
    def test_structured_local_request_and_grounded_valid_response(self, post):
        post.return_value = self.fake_response(self.result)
        self.assertEqual(generate(self.messages, self.resources), self.result)
        args, kwargs = post.call_args
        self.assertEqual(args[0], 'http://127.0.0.1:11434/api/chat')
        self.assertEqual(kwargs['json']['format']['properties']['items']['items']['properties']['resource_id']['enum'], [17])
        self.assertEqual(kwargs['json']['stream'], False)

    @patch('magi_resource_recommendations.requests.Session.post')
    def test_rejects_invented_resource_and_unsupported_evidence(self, post):
        self.result['items'][0]['resource_id'] = 999
        post.return_value = self.fake_response(self.result)
        with self.assertRaises(RecommendationError):
            generate(self.messages, self.resources)
        self.result['items'][0]['resource_id'] = 17
        self.result['items'][0]['evidence'] = 'invented evidence'
        post.return_value = self.fake_response(self.result)
        with self.assertRaises(RecommendationError):
            generate(self.messages, self.resources)

    @patch('magi_resource_recommendations.requests.Session.post')
    def test_malformed_or_unavailable_model_does_not_produce_a_result(self, post):
        post.return_value = self.fake_response({'topics': []})
        with self.assertRaises(RecommendationError):
            generate(self.messages, self.resources)
        post.side_effect = __import__('requests').exceptions.Timeout()
        with self.assertRaises(RecommendationError):
            generate(self.messages, self.resources)

    @unittest.skipUnless(os.getenv('MAGI_RECOMMENDATIONS_LIVE_SYNTHETIC') == '1', 'Live synthetic model test not requested')
    def test_live_queued_model_on_synthetic_data(self):
        from gpu_inference_queue import queue
        with queue.acquire(str(uuid.uuid4()), priority='normal'):
            result = generate(self.messages, self.resources)
        self.assertIn('items', result)
        print('LIVE_SYNTHETIC_SELECTED_RESOURCES=', len(result['items']))
        print('LIVE_SYNTHETIC_RESOURCE_IDS=', [item['resource_id'] for item in result['items']])


if __name__ == '__main__':
    unittest.main()
