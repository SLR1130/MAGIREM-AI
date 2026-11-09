"""Synthetic Phase 4A authentication and memory-boundary checks."""

import os
import sys
import threading
import unittest
from types import SimpleNamespace

os.environ.setdefault("MINDCARE_INTERNAL_HMAC_SECRET", "phase4a-test-only-secret")
os.environ.setdefault("MINDCARE_INTERNAL_HMAC_KEY_ID", "phase4a-test")
os.environ.setdefault("MINDCARE_INTERNAL_HMAC_ISSUER", "laravel-phase4a")
os.environ.setdefault("MINDCARE_INTERNAL_HMAC_AUDIENCE", "mindcare-flask-phase4a")
os.environ.setdefault("MINDCARE_INTERNAL_HMAC_TTL", "60")

from flask import Flask, g, jsonify

from internal_auth import (
    AuthContext,
    InternalAuthError,
    build_token,
    reset_replay_cache,
    require_internal_auth,
)


class InternalAuthTests(unittest.TestCase):
    def setUp(self):
        reset_replay_cache()
        self.app = Flask(__name__)

        @self.app.post("/protected")
        @require_internal_auth("memory.chat")
        def protected():
            return jsonify({"subject": g.mindcare_auth.subject})

        self.client = self.app.test_client()

    def _headers(self, body, **kwargs):
        return {
            "Authorization": "MindCare-HMAC " + build_token(body, **kwargs),
            "Content-Type": "application/json",
        }

    def test_valid_signed_request(self):
        body = b'{"user_id":"synthetic-a","message":"hello"}'
        response = self.client.post(
            "/protected", data=body, headers=self._headers(body, subject="synthetic-a", operation="memory.chat")
        )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["subject"], "synthetic-a")

    def test_missing_signature_fails_closed(self):
        response = self.client.post("/protected", json={"user_id": "synthetic-a"})
        self.assertEqual(response.status_code, 401)

    def test_modified_body_fails_integrity_check(self):
        original = b'{"user_id":"synthetic-a","message":"hello"}'
        response = self.client.post(
            "/protected",
            data=b'{"user_id":"synthetic-a","message":"tampered"}',
            headers=self._headers(original, subject="synthetic-a", operation="memory.chat"),
        )
        self.assertEqual(response.status_code, 401)

    def test_expired_signature_fails(self):
        body = b'{"user_id":"synthetic-a"}'
        token = build_token(
            body,
            subject="synthetic-a",
            operation="memory.chat",
            issued_at=100,
            expires_at=101,
        )
        response = self.client.post(
            "/protected", data=body, headers={"Authorization": "MindCare-HMAC " + token, "Content-Type": "application/json"}
        )
        self.assertEqual(response.status_code, 401)

    def test_replay_is_rejected(self):
        body = b'{"user_id":"synthetic-a"}'
        headers = self._headers(body, subject="synthetic-a", operation="memory.chat", request_id="replay-1")
        self.assertEqual(self.client.post("/protected", data=body, headers=headers).status_code, 200)
        self.assertEqual(self.client.post("/protected", data=body, headers=headers).status_code, 401)

    def test_forged_subject_body_is_rejected(self):
        signed = b'{"user_id":"synthetic-b"}'
        response = self.client.post(
            "/protected",
            data=b'{"user_id":"synthetic-b"}',
            headers=self._headers(signed, subject="synthetic-a", operation="memory.chat"),
        )
        self.assertEqual(response.status_code, 403)

    def test_method_and_path_binding_is_enforced(self):
        body = b'{"user_id":"synthetic-a"}'
        headers = self._headers(body, subject="synthetic-a", operation="memory.chat", path="/api/initial-greeting")
        self.assertEqual(self.client.post("/protected", data=body, headers=headers).status_code, 403)

    def test_unsupported_operation_fails_closed(self):
        body = b'{"user_id":"synthetic-a"}'
        token = build_token(body, subject="synthetic-a", operation="unsupported.operation")
        response = self.client.post("/protected", data=body, headers={"Authorization": "MindCare-HMAC " + token, "Content-Type": "application/json"})
        self.assertEqual(response.status_code, 403)

    def test_concurrent_replay_allows_only_one_request(self):
        body = b'{"user_id":"synthetic-a"}'
        headers = self._headers(body, subject="synthetic-a", operation="memory.chat", request_id="concurrent-replay")
        results = []
        lock = threading.Lock()
        def worker():
            with self.app.test_client() as client:
                status = client.post("/protected", data=body, headers=headers).status_code
            with lock:
                results.append(status)
        threads = [threading.Thread(target=worker) for _ in range(12)]
        for thread in threads: thread.start()
        for thread in threads: thread.join()
        self.assertEqual(results.count(200), 1)
        self.assertEqual(results.count(401), 11)


class MemoryBoundaryTests(unittest.TestCase):
    def test_cross_user_retrieval_and_creation_are_denied(self):
        from phase4a_secure_api import _MemoryStoreProxy, app

        fake = SimpleNamespace(
            get_user_memories=lambda user_id, *a, **k: [{"user_id": user_id}],
            create_memory=lambda user_id, *a, **k: 42,
        )
        proxy = _MemoryStoreProxy(fake)
        with app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti", {})
            self.assertEqual(proxy.get_user_memories("synthetic-b"), [])
            self.assertIsNone(proxy.create_memory("synthetic-b", "note"))
            self.assertEqual(proxy.get_user_memories("synthetic-a"), [{"user_id": "synthetic-a"}])

    def test_direct_flask_routes_fail_closed_without_wrapper(self):
        import unified_ml_api_with_model as direct
        client = direct.app.test_client()
        self.assertEqual(client.post("/chat", json={"user_id": "synthetic-a", "message": "hello"}).status_code, 401)
        self.assertEqual(client.post("/api/initial-greeting", json={"user_id": "synthetic-a"}).status_code, 401)

    def test_memory_disabled_keeps_manager_operations_empty(self):
        from phase4a_secure_api import _AuthenticatedMemoryManager, app

        fake = SimpleNamespace(
            store=SimpleNamespace(),
            retrieve_context=lambda *a, **k: {"memories": [{"content": "secret"}]},
            handle_message_pair=lambda *a, **k: {"count": 1},
        )
        manager = _AuthenticatedMemoryManager(fake)
        with app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", False, "jti", {})
            self.assertEqual(manager.retrieve_context("synthetic-a")["memories"], [])
            self.assertEqual(manager.handle_message_pair("synthetic-a")["count"], 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)

