"""Synthetic Phase 4C deletion and revocation regression tests."""
from __future__ import annotations

import contextlib
import io
import json
import os
import sqlite3
import subprocess
import sys
import threading
import time
import unittest
import uuid

ROOT = os.path.dirname(os.path.dirname(__file__))
PREFIX = os.path.join(ROOT, "phase4c-test-" + uuid.uuid4().hex)
CONSENT_DB = PREFIX + "-consent.db"
MEMORY_DB = PREFIX + "-memory.db"
REPLAY_DB = PREFIX + "-replay.db"
os.environ["MINDCARE_PHASE4B_CONSENT_DB"] = CONSENT_DB
os.environ['MINDCARE_PHASE4A_AUTH_ENABLED'] = 'true'
os.environ["MINDCARE_PHASE4B_POLICY_VERSION"] = "synthetic-policy-v1"
os.environ["MINDCARE_MEMORY_DB_PATH"] = MEMORY_DB
os.environ["MINDCARE_INTERNAL_HMAC_SECRET"] = "phase4c-test-secret"
os.environ["MINDCARE_INTERNAL_HMAC_KEY_ID"] = "phase4c-test"
os.environ["MINDCARE_INTERNAL_HMAC_ISSUER"] = "laravel-phase4c"
os.environ["MINDCARE_INTERNAL_HMAC_AUDIENCE"] = "mindcare-flask-phase4c"
os.environ["MINDCARE_INTERNAL_HMAC_REPLAY_DB"] = REPLAY_DB

from memory_store import MemoryStore
from consent_store import ConsentStore, ConsentAwareMemoryManager
from internal_auth import AuthContext, build_token, reset_replay_cache
from deletion_ledger import DeletionLedger
from flask import Flask, g

with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
    import phase4c_secure_api as secure
    import unified_ml_api_with_model as base


class Phase4CDeletionTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.consent = ConsentStore(CONSENT_DB, "synthetic-policy-v1")
        cls.client = secure.app.test_client()
        class Stub:
            def generate_response(self, **kwargs):
                return {"response": "Synthetic supportive response", "emotion": "neutral"}
        base._smart_gen = Stub()

    def setUp(self):
        reset_replay_cache()
        os.environ.pop("MINDCARE_PHASE4C_TEST_FAIL_STAGE", None)

    def seed(self, subject):
        store = MemoryStore(MEMORY_DB)
        memory_id = store.create_memory(subject, "semantic", "synthetic content", source_message=subject + "-source")
        store.create_project_memory(subject, subject + "-project", "synthetic project")
        store.set_preference(subject, "style", "quiet")
        store.save_conversation_summary(subject, "synthetic summary", None, None, "synthetic")
        store._log_access(memory_id)
        store.create_memory("other-" + subject, "semantic", "other content", source_message="other-" + subject)
        store.connection.close()
        self.consent.set_state(subject, "granted")
        return memory_id

    def token_request(self, subject, request_id, *, operation="memory.delete", state=None, revision=None, jti=None, user_id=None):
        decision = self.consent.decision(subject)
        state = decision.state if state is None else state
        revision = decision.revision if revision is None else revision
        payload = {"user_id": subject if user_id is None else user_id, "deletion_request_id": request_id}
        body = json.dumps(payload, separators=(",", ":")).encode()
        token = build_token(
            body,
            subject=subject,
            operation=operation,
            memory_authorized=state == "granted",
            consent_purpose="long_term_memory",
            consent_state=state,
            consent_policy_version="synthetic-policy-v1",
            consent_revision=revision,
            request_id=jti or (request_id + "-auth-" + uuid.uuid4().hex),
        )
        return body, {"Authorization": "MindCare-HMAC " + token, "Content-Type": "application/json"}

    def delete(self, subject, request_id, **kwargs):
        body, headers = self.token_request(subject, request_id, **kwargs)
        return self.client.post("/internal/memory/delete", data=body, headers=headers)

    def counts(self, subject):
        db = sqlite3.connect(MEMORY_DB)
        values = {
            "memories": db.execute("SELECT COUNT(*) FROM memories WHERE user_id=?", (subject,)).fetchone()[0],
            "projects": db.execute("SELECT COUNT(*) FROM project_memories WHERE user_id=?", (subject,)).fetchone()[0],
            "preferences": db.execute("SELECT COUNT(*) FROM preferences WHERE user_id=?", (subject,)).fetchone()[0],
            "summaries": db.execute("SELECT COUNT(*) FROM conversation_summaries WHERE user_id=?", (subject,)).fetchone()[0],
        }
        db.close()
        return values

    def test_authorized_deletion_removes_all_categories_and_is_idempotent(self):
        subject = "delete-a"; self.seed(subject)
        response = self.delete(subject, "delete-request-a")
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["deletion"]["status"], "completed")
        self.assertEqual(self.counts(subject), {"memories": 0, "projects": 0, "preferences": 0, "summaries": 0})
        retry = self.delete(subject, "delete-request-a")
        self.assertEqual(retry.status_code, 200)
        self.assertEqual(retry.json["deletion"]["status"], "completed")

    def test_authentication_and_subject_fail_closed(self):
        body = json.dumps({"user_id": "delete-b", "deletion_request_id": "delete-request-b"}).encode()
        self.assertEqual(self.client.post("/internal/memory/delete", data=body).status_code, 401)
        self.assertEqual(self.client.post("/internal/memory/delete", data=body, headers={"Authorization": "MindCare-HMAC invalid"}).status_code, 401)
        self.seed("delete-b")
        wrong_body, headers = self.token_request("delete-b", "delete-request-b2", user_id="delete-c")
        self.assertEqual(self.client.post("/internal/memory/delete", data=wrong_body, headers=headers).status_code, 403)

    def test_expired_and_replayed_delete_requests_fail(self):
        self.seed("delete-c")
        body, headers = self.token_request("delete-c", "delete-request-c", jti="delete-expired")
        expired = build_token(
            body,
            subject="delete-c",
            operation="memory.delete",
            memory_authorized=True,
            consent_purpose="long_term_memory",
            consent_state="granted",
            consent_policy_version="synthetic-policy-v1",
            consent_revision=self.consent.decision("delete-c").revision,
            request_id="delete-expired-auth",
            issued_at=int(time.time()) - 120,
            expires_at=int(time.time()) - 60,
        )
        expired_headers = {"Authorization": "MindCare-HMAC " + expired, "Content-Type": "application/json"}
        self.assertEqual(self.client.post("/internal/memory/delete", data=body, headers=expired_headers).status_code, 401)
        token = headers["Authorization"].replace("MindCare-HMAC ", "")
        self.assertEqual(self.client.post("/internal/memory/delete", data=body, headers={"Authorization": "MindCare-HMAC " + token[:-2] + "xx", "Content-Type": "application/json"}).status_code, 401)
        first = self.client.post("/internal/memory/delete", data=body, headers=headers)
        self.assertEqual(first.status_code, 200)
        second = self.client.post("/internal/memory/delete", data=body, headers=headers)
        self.assertEqual(second.status_code, 401)

    def test_deletion_subject_comes_from_signed_claim_without_body_user_id(self):
        subject = "delete-body-free"
        self.seed(subject)
        body = json.dumps({"deletion_request_id": "delete-request-body-free"}, separators=(",", ":")).encode()
        decision = self.consent.decision(subject)
        token = build_token(
            body,
            subject=subject,
            operation="memory.delete",
            memory_authorized=True,
            consent_purpose="long_term_memory",
            consent_state=decision.state,
            consent_policy_version=decision.policy_version,
            consent_revision=decision.revision,
            request_id="delete-body-free-auth",
        )
        response = self.client.post("/internal/memory/delete", data=body, headers={"Authorization": "MindCare-HMAC " + token, "Content-Type": "application/json"})
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["deletion"]["subject"], subject)
    def test_interrupted_deletion_is_retryable_and_records_remain_until_retry(self):
        subject = "delete-d"; self.seed(subject)
        os.environ["MINDCARE_PHASE4C_TEST_FAIL_STAGE"] = "after_delete_before_commit"
        failed = self.delete(subject, "delete-request-d")
        self.assertEqual(failed.status_code, 202)
        self.assertEqual(failed.json["deletion"]["status"], "failed_retryable")
        self.assertGreater(self.counts(subject)["memories"], 0)
        os.environ.pop("MINDCARE_PHASE4C_TEST_FAIL_STAGE", None)
        retried = self.delete(subject, "delete-request-d")
        self.assertEqual(retried.status_code, 200)
        self.assertEqual(retried.json["deletion"]["status"], "completed")
        self.assertEqual(self.counts(subject)["memories"], 0)
    def test_retry_after_ledger_reinitialization_completes(self):
        subject = "delete-restart"
        self.seed(subject)
        ledger = DeletionLedger(self.consent, MEMORY_DB)
        requested = ledger.request(subject, "delete-request-restart")
        failed = ledger.process(requested["request_id"], test_fail_stage="after_delete_before_commit")
        self.assertEqual(failed["status"], "failed_retryable")
        restarted = DeletionLedger(self.consent, MEMORY_DB)
        resumed = restarted.resume(requested["request_id"])
        self.assertEqual(resumed["status"], "completed")
        self.assertEqual(self.counts(subject)["memories"], 0)

    def test_simultaneous_same_subject_requests_are_safe(self):
        subject = "delete-e"; self.seed(subject)
        results = []
        def worker(index):
            body, headers = self.token_request(subject, "delete-request-e", jti=f"delete-e-auth-{index}")
            result = secure.app.test_client().post("/internal/memory/delete", data=body, headers=headers)
            results.append(result.status_code)
        threads = [threading.Thread(target=worker, args=(i,)) for i in range(2)]
        for thread in threads: thread.start()
        for thread in threads: thread.join(10)
        self.assertTrue(all(code == 200 for code in results))
        self.assertEqual(self.counts(subject)["memories"], 0)

    def test_reconsent_does_not_restore_deleted_records(self):
        subject = "delete-f"; self.seed(subject)
        self.assertEqual(self.delete(subject, "delete-request-f").status_code, 200)
        decision = self.consent.set_state(subject, "granted")
        self.assertTrue(decision.allows_memory)
        self.assertEqual(self.counts(subject), {"memories": 0, "projects": 0, "preferences": 0, "summaries": 0})

    def test_memory_db_unavailable_is_retryable_not_success(self):
        subject = "delete-g"; self.seed(subject)
        ledger = DeletionLedger(self.consent, os.path.join(ROOT, "missing-parent", "memory.db"))
        op = ledger.request(subject, "delete-request-g")
        result = ledger.process(op["request_id"])
        self.assertEqual(result["status"], "failed_retryable")

    def test_no_consent_chat_and_greeting_have_no_persistent_memory(self):
        subject = "delete-h"
        self.consent.set_state(subject, "not_granted")
        body = json.dumps({"user_id": subject, "message": "hello"}, separators=(",", ":")).encode()
        decision = self.consent.decision(subject)
        token = build_token(body, subject=subject, operation="memory.chat", consent_state=decision.state, consent_policy_version=decision.policy_version, consent_revision=decision.revision, request_id="chat-no-consent-h")
        response = self.client.post("/chat", data=body, headers={"Authorization": "MindCare-HMAC " + token, "Content-Type": "application/json"})
        self.assertEqual(response.status_code, 200)
        self.assertEqual((response.json.get("memory") or {}).get("memories_created"), 0)


    def test_atomic_revocation_intent_faults_are_durable_and_block_claims(self):
        before_subject = "atomic-before"
        self.seed(before_subject)
        before_ledger = DeletionLedger(self.consent, MEMORY_DB)
        before = before_ledger.request(before_subject, "delete-request-before", test_fail_stage="before_revocation_commit")
        self.assertEqual(before["status"], "requested")
        self.assertEqual(self.consent.decision(before_subject).state, "granted")
        self.assertEqual(before_ledger.process("delete-request-before")["status"], "completed")

        subject = "atomic-gap"
        self.seed(subject)
        ledger = DeletionLedger(self.consent, MEMORY_DB)
        failed = ledger.request(subject, "delete-request-atomic", test_fail_stage="after_revocation_before_intent_commit")
        self.assertEqual(failed["status"], "requested")
        self.assertEqual(self.consent.decision(subject).state, "granted")
        claims = {
            "consent_purpose": "long_term_memory",
            "consent_state": "granted",
            "consent_policy_version": "synthetic-policy-v1",
            "consent_revision": self.consent.decision(subject).revision,
        }
        self.assertFalse(self.consent.allows_claim(subject, claims))
        retried = ledger.request(subject, "delete-request-atomic")
        self.assertEqual(retried["status"], "pending")
        self.assertEqual(self.consent.decision(subject).state, "revoked")
        completed = ledger.process("delete-request-atomic")
        self.assertEqual(completed["status"], "completed")

    def test_partial_failure_boundaries_are_retryable(self):
        stages = (
            "before_memory",
            "during_memories_delete",
            "during_project_delete",
            "during_preference_delete",
            "during_summary_delete",
            "after_delete_before_commit",
            "after_memory_commit_before_ledger",
        )
        for index, stage in enumerate(stages):
            subject = "partial-" + str(index)
            request_id = "delete-partial-" + str(index).zfill(8)
            self.seed(subject)
            ledger = DeletionLedger(self.consent, MEMORY_DB)
            requested = ledger.request(subject, request_id)
            failed = ledger.process(request_id, test_fail_stage=stage)
            self.assertEqual(failed["status"], "failed_retryable", stage)
            restarted = DeletionLedger(self.consent, MEMORY_DB)
            completed = restarted.resume(request_id)
            self.assertEqual(completed["status"], "completed", stage)
            self.assertEqual(self.counts(subject), {"memories": 0, "projects": 0, "preferences": 0, "summaries": 0}, stage)
            self.assertEqual(completed["verification"], "all_required_categories_zero", stage)

    def test_worker_termination_leaves_durable_intent_and_restart_recovers(self):
        subject = "worker-termination"
        request_id = "delete-worker-termination"
        self.seed(subject)
        child = (
            "import os,sys; "
            "sys.path.insert(0, os.path.join(os.getcwd(), 'ml_service')); "
            "from consent_store import ConsentStore, ConsentAwareMemoryManager; "
            "from deletion_ledger import DeletionLedger; "
            "c=ConsentStore(os.environ['MINDCARE_PHASE4B_CONSENT_DB'], 'synthetic-policy-v1'); "
            "DeletionLedger(c, os.environ['MINDCARE_MEMORY_DB_PATH']).request('worker-termination', 'delete-worker-termination', test_fail_stage='terminate_after_intent_commit')"
        )
        environment = os.environ.copy()
        environment["MINDCARE_PHASE4B_CONSENT_DB"] = CONSENT_DB
        environment["MINDCARE_PHASE4B_POLICY_VERSION"] = "synthetic-policy-v1"
        environment["MINDCARE_MEMORY_DB_PATH"] = MEMORY_DB
        terminated = subprocess.run([sys.executable, "-c", child], cwd=ROOT, env=environment, timeout=30)
        self.assertEqual(terminated.returncode, 17)
        ledger = DeletionLedger(self.consent, MEMORY_DB)
        durable = ledger.get(request_id)
        self.assertIsNotNone(durable)
        self.assertEqual(durable["status"], "requested")
        self.assertEqual(self.consent.decision(subject).state, "granted")
        self.assertFalse(self.consent.allows_claim(subject, {
            "consent_purpose": "long_term_memory",
            "consent_state": "granted",
            "consent_policy_version": "synthetic-policy-v1",
            "consent_revision": self.consent.decision(subject).revision,
        }))
        recovered = ledger.recover_pending()
        self.assertTrue(any(item["status"] == "completed" for item in recovered))
        self.assertEqual(self.counts(subject), {"memories": 0, "projects": 0, "preferences": 0, "summaries": 0})
    def test_concurrent_chat_and_deletion_preserves_ordering(self):
        subject = "concurrent-chat-delete"
        self.consent.set_state(subject, "granted")
        app = Flask("phase4c-concurrency")
        started = threading.Event()
        release = threading.Event()
        result = {}

        class Store:
            def create_memory(self, user_id, *args, **kwargs):
                return 1
            def get_user_memories(self, user_id, *args, **kwargs):
                return []

        class Manager:
            def __init__(self):
                self.store = Store()
            def handle_message_pair(self, user_id, *args, **kwargs):
                started.set()
                release.wait(5)
                self.store.create_memory(user_id, "semantic", "synthetic")
                return {"count": 1}

        manager = ConsentAwareMemoryManager(Manager(), self.consent)
        decision = self.consent.decision(subject)
        claims = {
            "consent_purpose": "long_term_memory",
            "consent_state": "granted",
            "consent_policy_version": decision.policy_version,
            "consent_revision": decision.revision,
        }

        def writer():
            with app.test_request_context("/chat"):
                g.mindcare_auth = AuthContext(subject, "memory.chat", True, "chat-before-delete", claims)
                result["write"] = manager.handle_message_pair(subject)

        writer_thread = threading.Thread(target=writer)
        writer_thread.start()
        self.assertTrue(started.wait(5))
        ledger = DeletionLedger(self.consent, MEMORY_DB)
        delete_result = {}

        def deleter():
            delete_result["operation"] = ledger.request(subject, "delete-concurrent-chat")

        delete_thread = threading.Thread(target=deleter)
        delete_thread.start()
        time.sleep(0.1)
        self.assertTrue(delete_thread.is_alive())
        release.set()
        writer_thread.join(5)
        delete_thread.join(5)
        self.assertEqual(result["write"]["count"], 1)
        self.assertEqual(delete_result["operation"]["status"], "pending")
        self.assertEqual(self.consent.decision(subject).state, "revoked")
        with app.test_request_context("/chat"):
            g.mindcare_auth = AuthContext(subject, "memory.chat", True, "chat-stale-retry", claims)
            self.assertEqual(manager.handle_message_pair(subject)["count"], 0)
        self.assertEqual(ledger.process("delete-concurrent-chat")["status"], "completed")
if __name__ == "__main__":
    unittest.main(verbosity=2)

