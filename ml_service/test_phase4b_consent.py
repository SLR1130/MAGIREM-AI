"""Synthetic Phase 4B consent and revocation tests."""
import os
import threading
import time
import unittest
import tempfile
from types import SimpleNamespace

TEST_ROOT = tempfile.mkdtemp(prefix='mindcare-phase4b-', dir=os.environ.get('MINDCARE_TEST_TMP', 'C:\\\\tmp'))
CONSENT_DB = os.path.join(TEST_ROOT, 'consent.db')
os.environ["MINDCARE_PHASE4B_CONSENT_DB"] = CONSENT_DB
os.environ["MINDCARE_PHASE4B_POLICY_VERSION"] = "synthetic-policy-v1"
os.environ["MINDCARE_INTERNAL_HMAC_SECRET"] = "phase4b-test-secret"
os.environ["MINDCARE_INTERNAL_HMAC_KEY_ID"] = "phase4b-test"
os.environ["MINDCARE_INTERNAL_HMAC_ISSUER"] = "laravel-phase4b"
os.environ["MINDCARE_INTERNAL_HMAC_AUDIENCE"] = "mindcare-flask-phase4b"
os.environ['MINDCARE_INTERNAL_HMAC_REPLAY_DB'] = os.path.join(TEST_ROOT, 'replay.db')

from flask import Flask, g
from consent_store import ConsentAwareMemoryManager, ConsentStore
from internal_auth import AuthContext


class ConsentStateTests(unittest.TestCase):
    def setUp(self):
        self.store = ConsentStore(CONSENT_DB, "synthetic-policy-v1")
        self.store.set_state("synthetic-a", "not_granted")

    def test_missing_granted_revoked_and_stale_states_fail_closed(self):
        self.assertFalse(self.store.decision("unknown").allows_memory)
        granted = self.store.set_state("synthetic-a", "granted")
        self.assertTrue(granted.allows_memory)
        self.store.set_state("synthetic-a", "stale", policy_version="synthetic-policy-v0")
        self.assertFalse(self.store.decision("synthetic-a").allows_memory)
        self.store.set_state("synthetic-a", "revoked")
        self.assertFalse(self.store.decision("synthetic-a").allows_memory)

    def test_claims_must_match_current_revision_and_policy(self):
        decision = self.store.set_state("synthetic-a", "granted")
        claims = {
            "consent_purpose": "long_term_memory",
            "consent_state": "granted",
            "consent_policy_version": "synthetic-policy-v1",
            "consent_revision": decision.revision,
            "memory_authorized": False,
        }
        self.assertTrue(self.store.allows_claim("synthetic-a", claims))
        claims["consent_revision"] -= 1
        self.assertFalse(self.store.allows_claim("synthetic-a", claims))
        claims["consent_revision"] = "not-an-integer"
        self.assertFalse(self.store.allows_claim("synthetic-a", claims))


class ConsentMemoryBoundaryTests(unittest.TestCase):
    def setUp(self):
        self.store = ConsentStore(CONSENT_DB, "synthetic-policy-v1")
        self.store.set_state("synthetic-a", "granted")
        self.app = Flask(__name__)

    def _manager(self, started=None, release=None):
        class FakeStore:
            def get_user_memories(inner, user_id, *args, **kwargs):
                return [{"user_id": user_id, "content": "synthetic"}]
            def create_memory(inner, user_id, *args, **kwargs):
                return 7
        class FakeManager:
            def __init__(inner): inner.store = FakeStore()
            def retrieve_context(inner, user_id, *args, **kwargs):
                return {"memories": inner.store.get_user_memories(user_id), "projects": [], "preferences": {}}
            def handle_message_pair(inner, user_id, *args, **kwargs):
                if started: started.set()
                if release: release.wait(5)
                return {"count": 1, "memories": ["synthetic"]}
        return ConsentAwareMemoryManager(FakeManager(), self.store)

    def _claims(self):
        d = self.store.decision("synthetic-a")
        return {"consent_purpose": "long_term_memory", "consent_state": "granted", "consent_policy_version": d.policy_version, "consent_revision": d.revision}

    def test_retrieval_and_write_require_current_consent(self):
        manager = self._manager()
        with self.app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti", self._claims())
            self.assertEqual(len(manager.retrieve_context("synthetic-a")["memories"]), 1)
            self.assertEqual(manager.handle_message_pair("synthetic-a")["count"], 1)
        self.store.set_state("synthetic-a", "revoked")
        with self.app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti2", self._claims())
            self.assertEqual(manager.retrieve_context("synthetic-a")["memories"], [])
            self.assertEqual(manager.handle_message_pair("synthetic-a")["count"], 0)

    def test_cross_user_isolation(self):
        manager = self._manager()
        with self.app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti", self._claims())
            self.assertEqual(manager.retrieve_context("synthetic-b")["memories"], [])
            self.assertEqual(manager.handle_message_pair("synthetic-b")["count"], 0)

    def test_multiple_simultaneous_writes_are_scoped_and_revocation_stops_future_writes(self):
        manager = self._manager()
        claims = self._claims()
        barrier = threading.Barrier(4)
        results = []
        lock = threading.Lock()
        def worker():
            with self.app.test_request_context("/chat"):
                g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti", claims)
                barrier.wait()
                value = manager.handle_message_pair("synthetic-a")["count"]
                with lock: results.append(value)
        threads = [threading.Thread(target=worker) for _ in range(4)]
        for thread in threads: thread.start()
        for thread in threads: thread.join(5)
        self.assertEqual(results, [1, 1, 1, 1])
        self.store.set_state("synthetic-a", "revoked")
        with self.app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti-after", claims)
            self.assertEqual(manager.handle_message_pair("synthetic-a")["count"], 0)

    def test_revocation_waits_for_inflight_write_and_blocks_later_writes(self):
        started, release = threading.Event(), threading.Event()
        manager = self._manager(started, release)
        result = {}
        old_claims = self._claims()
        def writer():
            with self.app.test_request_context("/chat"):
                g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti", old_claims)
                result["write"] = manager.handle_message_pair("synthetic-a")
        thread = threading.Thread(target=writer)
        thread.start()
        self.assertTrue(started.wait(5))
        revoked = threading.Event()
        def revoke():
            self.store.set_state("synthetic-a", "revoked")
            revoked.set()
        revoker = threading.Thread(target=revoke)
        revoker.start()
        time.sleep(0.1)
        self.assertFalse(revoked.is_set())
        release.set()
        thread.join(5); revoker.join(5)
        self.assertEqual(result["write"]["count"], 1)
        self.assertTrue(revoked.is_set())
        with self.app.test_request_context("/chat"):
            # The pre-revocation signed claims must not resurrect authorization.
            g.mindcare_auth = AuthContext("synthetic-a", "memory.chat", True, "jti2", old_claims)
            self.assertEqual(manager.handle_message_pair("synthetic-a")["count"], 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)

