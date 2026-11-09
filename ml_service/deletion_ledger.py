"""Synthetic, idempotent long-term-memory deletion ledger for Phase 4C."""
from __future__ import annotations

import json
import os
import re
import sqlite3
from datetime import datetime, timezone
from typing import Any, Dict, Optional

from consent_store import ConsentStore

STATUSES = {"requested", "pending", "processing", "completed", "failed_retryable", "failed_terminal"}
ACTIVE_STATUSES = {"requested", "pending", "processing", "failed_retryable"}
REQUEST_ID_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9_-]{7,127}$")


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


class DeletionRequestError(Exception):
    pass


class RetryableDeletionError(Exception):
    pass


class DeletionLedger:
    def __init__(self, consent: ConsentStore, memory_db_path: str):
        self.consent = consent
        self.ledger_path = consent.path
        self.memory_db_path = memory_db_path
        self._init_schema()

    def _connect(self) -> sqlite3.Connection:
        db = sqlite3.connect(self.ledger_path, timeout=10.0)
        db.row_factory = sqlite3.Row
        db.execute("PRAGMA busy_timeout=10000")
        return db

    def _init_schema(self) -> None:
        db = self._connect()
        try:
            db.execute("""
                CREATE TABLE IF NOT EXISTS deletion_operations (
                    request_id TEXT PRIMARY KEY,
                    subject TEXT NOT NULL,
                    status TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    counts_json TEXT,
                    last_error TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            """)
            db.execute("CREATE INDEX IF NOT EXISTS idx_deletion_subject_status ON deletion_operations(subject,status)")
            db.execute("""
                CREATE TABLE IF NOT EXISTS deletion_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_id TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    event TEXT NOT NULL,
                    status TEXT NOT NULL,
                    occurred_at TEXT NOT NULL
                )
            """)
            db.commit()
        finally:
            db.close()

    def _row(self, db, request_id: str):
        return db.execute("SELECT * FROM deletion_operations WHERE request_id=?", (request_id,)).fetchone()
    def _revoke_in_transaction(self, db, subject: str, now: str) -> None:
        """Bind revocation to an already durable deletion intent."""
        consent_row = db.execute(
            "SELECT purpose,policy_version,state,revision FROM memory_consent WHERE subject=?",
            (subject,),
        ).fetchone()
        if consent_row is None:
            revision = 1
            db.execute(
                "INSERT INTO memory_consent(subject,purpose,policy_version,state,revision,granted_at,revoked_at,updated_at) VALUES(?,?,?,?,?,?,?,?)",
                (subject, "long_term_memory", self.consent.policy_version, "revoked", revision, None, now, now),
            )
            db.execute(
                "INSERT INTO consent_audit(subject,event,state,policy_version,revision,occurred_at) VALUES(?,?,?,?,?,?)",
                (subject, "consent_state_changed", "revoked", self.consent.policy_version, revision, now),
            )
            return
        if consent_row[2] == "revoked":
            return
        revision = int(consent_row[3]) + 1
        db.execute(
            "UPDATE memory_consent SET purpose=?,policy_version=?,state=?,revision=?,granted_at=NULL,revoked_at=?,updated_at=? WHERE subject=?",
            ("long_term_memory", self.consent.policy_version, "revoked", revision, now, now, subject),
        )
        db.execute(
            "INSERT INTO consent_audit(subject,event,state,policy_version,revision,occurred_at) VALUES(?,?,?,?,?,?)",
            (subject, "consent_state_changed", "revoked", self.consent.policy_version, revision, now),
        )

    def _ensure_revoked(self, request_id: str, *, test_fail_stage: Optional[str] = None) -> Dict[str, Any]:
        """Atomically revoke consent and bind the durable intent to that revocation."""
        db = self._connect()
        try:
            db.execute("BEGIN IMMEDIATE")
            row = self._row(db, request_id)
            if row is None:
                raise DeletionRequestError("unknown_deletion_request")
            if row["status"] == "completed":
                db.rollback()
                return self._public(row)
            if test_fail_stage == "before_revocation_commit":
                raise RetryableDeletionError("synthetic_before_revocation_commit")
            now = _now()
            self._revoke_in_transaction(db, row["subject"], now)
            if test_fail_stage == "after_revocation_before_intent_commit":
                raise RetryableDeletionError("synthetic_after_revocation_before_intent_commit")
            if row["status"] == "requested":
                db.execute(
                    "UPDATE deletion_operations SET status=?,updated_at=? WHERE request_id=?",
                    ("pending", now, request_id),
                )
                db.execute(
                    "INSERT INTO deletion_events(request_id,subject,event,status,occurred_at) VALUES(?,?,?,?,?)",
                    (request_id, row["subject"], "revocation_bound", "pending", now),
                )
            db.commit()
        except RetryableDeletionError:
            db.rollback()
            return self.get(request_id)
        except Exception:
            db.rollback()
            raise
        finally:
            db.close()
        return self.get(request_id)

    @staticmethod
    def _public(row) -> Dict[str, Any]:
        return {
            "request_id": row["request_id"],
            "subject": row["subject"],
            "status": row["status"],
            "attempts": int(row["attempts"]),
            "counts": json.loads(row["counts_json"]) if row["counts_json"] else None,
            "last_error": row["last_error"] if row["status"] in {"failed_retryable", "failed_terminal"} else None,
            "verification": "all_required_categories_zero" if row["counts_json"] else None,
        }

    def request(self, subject: Any, request_id: str, *, test_fail_stage: Optional[str] = None) -> Dict[str, Any]:
        """Create a durable intent, then atomically bind revocation to it."""
        subject = str(subject)
        if not REQUEST_ID_RE.fullmatch(request_id):
            raise DeletionRequestError("invalid_deletion_request_id")
        db = self._connect()
        created = False
        try:
            db.execute("BEGIN IMMEDIATE")
            existing = self._row(db, request_id)
            if existing is not None:
                if existing["subject"] != subject:
                    raise DeletionRequestError("deletion_subject_mismatch")
                operation_id = existing["request_id"]
            else:
                active = db.execute(
                    "SELECT * FROM deletion_operations WHERE subject=? AND status IN ('requested','pending','processing','failed_retryable') ORDER BY created_at LIMIT 1",
                    (subject,),
                ).fetchone()
                if active is not None:
                    operation_id = active["request_id"]
                else:
                    if test_fail_stage == "before_intent_commit":
                        raise RetryableDeletionError("synthetic_before_intent_commit")
                    now = _now()
                    db.execute(
                        "INSERT INTO deletion_operations(request_id,subject,status,attempts,created_at,updated_at) VALUES(?,?,?,?,?,?)",
                        (request_id, subject, "requested", 0, now, now),
                    )
                    db.execute(
                        "INSERT INTO deletion_events(request_id,subject,event,status,occurred_at) VALUES(?,?,?,?,?)",
                        (request_id, subject, "requested", "requested", now),
                    )
                    operation_id = request_id
                    created = True
            db.commit()
        except Exception:
            db.rollback()
            raise
        finally:
            db.close()
        if created and test_fail_stage == "terminate_after_intent_commit":
            os._exit(17)
        return self._ensure_revoked(operation_id, test_fail_stage=test_fail_stage)
    def _set_status(self, request_id: str, status: str, *, counts=None, error=None, attempts=None):
        if status not in STATUSES:
            raise ValueError("invalid_deletion_status")
        now = _now()
        db = self._connect()
        try:
            db.execute("BEGIN IMMEDIATE")
            row = self._row(db, request_id)
            if row is None:
                raise DeletionRequestError("unknown_deletion_request")
            next_attempts = int(row["attempts"]) if attempts is None else attempts
            db.execute(
                "UPDATE deletion_operations SET status=?,attempts=?,counts_json=?,last_error=?,updated_at=? WHERE request_id=?",
                (status, next_attempts, json.dumps(counts) if counts is not None else row["counts_json"], error, now, request_id),
            )
            db.execute(
                "INSERT INTO deletion_events(request_id,subject,event,status,occurred_at) VALUES(?,?,?,?,?)",
                (request_id, row["subject"], "status_changed", status, now),
            )
            db.commit()
        finally:
            db.close()

    def get(self, request_id: str) -> Optional[Dict[str, Any]]:
        db = self._connect()
        try:
            row = self._row(db, request_id)
            return self._public(row) if row else None
        finally:
            db.close()

    def process(self, request_id: str, *, test_fail_stage: Optional[str] = None) -> Dict[str, Any]:
        current = self.get(request_id)
        if current is None:
            raise DeletionRequestError("unknown_deletion_request")
        if current["status"] != "completed":
            current = self._ensure_revoked(request_id)
        if current["status"] == "completed":
            return current
        attempts = current["attempts"] + 1
        self._set_status(request_id, "processing", attempts=attempts, error=None)
        current = self.get(request_id)
        subject = current["subject"]
        memory = None
        try:
            if test_fail_stage == "before_memory":
                raise RetryableDeletionError("synthetic_memory_unavailable")
            memory = sqlite3.connect(self.memory_db_path, timeout=10.0)
            memory.row_factory = sqlite3.Row
            memory.execute("PRAGMA busy_timeout=10000")
            memory.execute("BEGIN IMMEDIATE")
            ids = [row[0] for row in memory.execute("SELECT id FROM memories WHERE user_id=?", (subject,)).fetchall()]
            access_deleted = 0
            if ids:
                marks = ",".join("?" for _ in ids)
                access_deleted = memory.execute(f"DELETE FROM memory_access_log WHERE memory_id IN ({marks})", ids).rowcount
                memory.execute("DELETE FROM memories WHERE user_id=?", (subject,))
            if test_fail_stage == "during_memories_delete":
                raise RetryableDeletionError("synthetic_during_memories_delete")
            project_deleted = memory.execute("DELETE FROM project_memories WHERE user_id=?", (subject,)).rowcount
            if test_fail_stage == "during_project_delete":
                raise RetryableDeletionError("synthetic_during_project_delete")
            preference_deleted = memory.execute("DELETE FROM preferences WHERE user_id=?", (subject,)).rowcount
            if test_fail_stage == "during_preference_delete":
                raise RetryableDeletionError("synthetic_during_preference_delete")
            summary_deleted = memory.execute("DELETE FROM conversation_summaries WHERE user_id=?", (subject,)).rowcount
            if test_fail_stage == "during_summary_delete":
                raise RetryableDeletionError("synthetic_during_summary_delete")
            if test_fail_stage == "after_delete_before_commit":
                raise RetryableDeletionError("synthetic_interruption_before_commit")
            remaining = {
                "memories": memory.execute("SELECT COUNT(*) FROM memories WHERE user_id=?", (subject,)).fetchone()[0],
                "project_memories": memory.execute("SELECT COUNT(*) FROM project_memories WHERE user_id=?", (subject,)).fetchone()[0],
                "preferences": memory.execute("SELECT COUNT(*) FROM preferences WHERE user_id=?", (subject,)).fetchone()[0],
                "conversation_summaries": memory.execute("SELECT COUNT(*) FROM conversation_summaries WHERE user_id=?", (subject,)).fetchone()[0],
                "memory_access_log": memory.execute("SELECT COUNT(*) FROM memory_access_log WHERE memory_id IN (" + ",".join("?" for _ in ids) + ")", ids).fetchone()[0] if ids else 0,
            }
            if any(remaining.values()):
                raise RetryableDeletionError("verification_incomplete")
            memory.commit()
            if test_fail_stage == "after_memory_commit_before_ledger":
                raise RetryableDeletionError("synthetic_after_memory_commit_before_ledger")
            counts = {
                "memories": len(ids),
                "memory_access_log": int(access_deleted),
                "project_memories": int(project_deleted),
                "preferences": int(preference_deleted),
                "conversation_summaries": int(summary_deleted),
            }
            self._set_status(request_id, "completed", counts=counts, attempts=attempts)
            return self.get(request_id)
        except RetryableDeletionError as exc:
            if memory is not None:
                memory.rollback()
            self._set_status(request_id, "failed_retryable", attempts=attempts, error=str(exc))
            return self.get(request_id)
        except (sqlite3.OperationalError, OSError) as exc:
            if memory is not None:
                memory.rollback()
            self._set_status(request_id, "failed_retryable", attempts=attempts, error="storage_unavailable")
            return self.get(request_id)
        except Exception:
            if memory is not None:
                memory.rollback()
            self._set_status(request_id, "failed_terminal", attempts=attempts, error="deletion_failed")
            return self.get(request_id)
        finally:
            if memory is not None:
                memory.close()

    def recover_pending(self) -> list[Dict[str, Any]]:
        """Reconcile durable intents after a worker restart."""
        db = self._connect()
        try:
            rows = db.execute(
                "SELECT request_id FROM deletion_operations WHERE status IN ('requested','pending','processing','failed_retryable') ORDER BY created_at"
            ).fetchall()
        finally:
            db.close()
        recovered = []
        for row in rows:
            request_id = row[0]
            try:
                recovered.append(self._ensure_revoked(request_id))
                recovered.append(self.process(request_id))
            except Exception:
                current = self.get(request_id)
                if current is not None:
                    recovered.append(current)
        return recovered
    def resume(self, request_id: str) -> Dict[str, Any]:
        return self.process(request_id)

