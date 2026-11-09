"""Synthetic Phase 4B consent authority and memory enforcement helpers."""
from __future__ import annotations

import os
import sqlite3
import threading
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any, Callable, Dict, Optional

PURPOSE = "long_term_memory"
VALID_STATES = {"not_granted", "granted", "revoked", "stale"}


def _now() -> str:
    return datetime.now(timezone.utc).isoformat()


@dataclass(frozen=True)
class ConsentDecision:
    subject: str
    purpose: str
    policy_version: str
    state: str
    revision: int
    updated_at: str

    @property
    def allows_memory(self) -> bool:
        return self.purpose == PURPOSE and self.state == "granted"


class ConsentStore:
    """Disposable synthetic consent store; never use as institutional policy storage."""

    def __init__(self, path: Optional[str] = None, policy_version: Optional[str] = None):
        self.path = path or os.getenv("MINDCARE_PHASE4B_CONSENT_DB", "phase4b-consent.db")
        self.policy_version = policy_version or os.getenv("MINDCARE_PHASE4B_POLICY_VERSION", "synthetic-policy-v1")
        self._lock = threading.RLock()
        self._local = threading.local()
        self._init_schema()

    def _connect(self) -> sqlite3.Connection:
        if self.path != ":memory:":
            os.makedirs(os.path.dirname(os.path.abspath(self.path)), exist_ok=True)
        db = sqlite3.connect(self.path, timeout=10.0)
        db.execute("PRAGMA busy_timeout=10000")
        db.execute("PRAGMA foreign_keys=ON")
        return db

    def _init_schema(self) -> None:
        db = self._connect()
        try:
            db.execute("""
                CREATE TABLE IF NOT EXISTS memory_consent (
                    subject TEXT PRIMARY KEY,
                    purpose TEXT NOT NULL,
                    policy_version TEXT NOT NULL,
                    state TEXT NOT NULL,
                    revision INTEGER NOT NULL,
                    granted_at TEXT,
                    revoked_at TEXT,
                    updated_at TEXT NOT NULL
                )
            """)
            db.execute("""
                CREATE TABLE IF NOT EXISTS consent_audit (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject TEXT NOT NULL,
                    event TEXT NOT NULL,
                    state TEXT NOT NULL,
                    policy_version TEXT NOT NULL,
                    revision INTEGER NOT NULL,
                    occurred_at TEXT NOT NULL
                )
            """)
            db.commit()
        finally:
            db.close()

    def decision(self, subject: Any) -> ConsentDecision:
        db = self._connect()
        try:
            row = db.execute(
                "SELECT subject,purpose,policy_version,state,revision,updated_at FROM memory_consent WHERE subject=?",
                (str(subject),),
            ).fetchone()
        finally:
            db.close()
        if row is None:
            return ConsentDecision(str(subject), PURPOSE, self.policy_version, "not_granted", 0, "")
        return ConsentDecision(*row)

    def _has_pending_deletion(self, subject: str) -> bool:
        db = self._connect()
        try:
            try:
                row = db.execute("SELECT 1 FROM deletion_operations WHERE subject=? AND status IN ('requested','pending','processing','failed_retryable','failed_terminal') LIMIT 1", (str(subject),)).fetchone()
                return row is not None
            except sqlite3.OperationalError:
                return False
        finally:
            db.close()

    def revoke_if_needed(self, subject: Any) -> ConsentDecision:
        current = self.decision(subject)
        if current.state == "revoked":
            return current
        return self.set_state(subject, "revoked")

    def _claims_match(self, current: ConsentDecision, claims: Dict[str, Any]) -> bool:
        try:
            claimed_revision = int(claims.get("consent_revision", -1))
        except (TypeError, ValueError):
            return False
        return (
            current.allows_memory
            and current.policy_version == self.policy_version
            and claims.get("consent_purpose") == PURPOSE
            and claims.get("consent_state") == "granted"
            and str(claims.get("consent_policy_version")) == current.policy_version
            and claimed_revision == current.revision
            and not self._has_pending_deletion(current.subject)
        )

    def allows_claim(self, subject: Any, claims: Dict[str, Any]) -> bool:
        return self._claims_match(self.decision(subject), claims)

    def set_state(self, subject: Any, state: str, *, purpose: str = PURPOSE, policy_version: Optional[str] = None) -> ConsentDecision:
        if state not in VALID_STATES:
            raise ValueError("invalid_consent_state")
        version = policy_version or self.policy_version
        now = _now()
        with self._lock:
            db = self._connect()
            try:
                db.execute("BEGIN IMMEDIATE")
                row = db.execute("SELECT revision FROM memory_consent WHERE subject=?", (str(subject),)).fetchone()
                revision = int(row[0]) + 1 if row else 1
                granted_at = now if state == "granted" else None
                revoked_at = now if state == "revoked" else None
                db.execute(
                    """INSERT INTO memory_consent(subject,purpose,policy_version,state,revision,granted_at,revoked_at,updated_at)
                       VALUES(?,?,?,?,?,?,?,?)
                       ON CONFLICT(subject) DO UPDATE SET purpose=excluded.purpose,policy_version=excluded.policy_version,
                       state=excluded.state,revision=excluded.revision,granted_at=excluded.granted_at,
                       revoked_at=excluded.revoked_at,updated_at=excluded.updated_at""",
                    (str(subject), purpose, version, state, revision, granted_at, revoked_at, now),
                )
                db.execute(
                    "INSERT INTO consent_audit(subject,event,state,policy_version,revision,occurred_at) VALUES(?,?,?,?,?,?)",
                    (str(subject), "consent_state_changed", state, version, revision, now),
                )
                db.commit()
            finally:
                db.close()
        return self.decision(subject)

    def write_authorized(self, subject: Any) -> bool:
        return getattr(self._local, "write_subject", None) == str(subject)

    def with_write_authorization(self, subject: Any, claims: Dict[str, Any], callback: Callable[[], Any]):
        """Linearize revocation and memory commit on this consent database."""
        subject = str(subject)
        with self._lock:
            db = self._connect()
            try:
                db.execute("BEGIN IMMEDIATE")
                row = db.execute(
                    "SELECT purpose,policy_version,state,revision FROM memory_consent WHERE subject=?",
                    (subject,),
                ).fetchone()
                if row is None:
                    db.rollback()
                    return False, None
                current = ConsentDecision(subject, row[0], row[1], row[2], int(row[3]), "")
                if not self._claims_match(current, claims):
                    db.rollback()
                    return False, None
                self._local.write_subject = subject
                try:
                    result = callback()
                    db.commit()
                    return True, result
                finally:
                    self._local.write_subject = None
            except Exception:
                db.rollback()
                raise
            finally:
                db.close()


class ConsentAwareMemoryStore:
    def __init__(self, store, consent: ConsentStore, context_allowed: Callable[[str], bool]):
        self._store = store
        self._consent = consent
        self._context_allowed = context_allowed

    def _authorized(self, user_id: Any = None) -> bool:
        if user_id is not None and not self._context_allowed(str(user_id)):
            return False
        return self._context_allowed(None)

    def get_user_memories(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return []
        return self._store.get_user_memories(user_id, *args, **kwargs)

    def get_active_projects(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return []
        return self._store.get_active_projects(user_id, *args, **kwargs)

    def get_preferences(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return {}
        return self._store.get_preferences(user_id, *args, **kwargs)

    def create_memory(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return None
        return self._store.create_memory(user_id, *args, **kwargs)

    def create_project_memory(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return None
        return self._store.create_project_memory(user_id, *args, **kwargs)

    def set_preference(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return False
        return self._store.set_preference(user_id, *args, **kwargs)

    def __getattr__(self, name):
        target = getattr(self._store, name)
        if not callable(target):
            return target
        def guarded(*args, **kwargs):
            if not self._authorized():
                return [] if name.startswith("get_") else False
            return target(*args, **kwargs)
        return guarded


class ConsentAwareMemoryManager:
    def __init__(self, manager, consent: ConsentStore):
        self._manager = manager
        self._consent = consent
        self.store = ConsentAwareMemoryStore(manager.store, consent, self._context_allowed)

    def _context_allowed(self, subject: Optional[str]) -> bool:
        try:
            from flask import g, has_request_context
            if not has_request_context():
                return False
            context = getattr(g, "mindcare_auth", None)
        except Exception:
            return False
        if context is None:
            return False
        if subject is not None and str(subject) != context.subject:
            return False
        return self._consent.allows_claim(context.subject, context.claims)

    def retrieve_context(self, user_id, *args, **kwargs):
        if not self._context_allowed(str(user_id)):
            return {"memories": [], "projects": [], "preferences": {}}
        return self._manager.retrieve_context(user_id, *args, **kwargs)

    def handle_message_pair(self, user_id, *args, **kwargs):
        if not self._context_allowed(str(user_id)):
            return {"count": 0, "memories": []}
        context = getattr(__import__("flask").g, "mindcare_auth")
        allowed, result = self._consent.with_write_authorization(
            str(user_id), context.claims,
            lambda: self._manager.handle_message_pair(user_id, *args, **kwargs),
        )
        return result if allowed and result is not None else {"count": 0, "memories": []}

    def __getattr__(self, name):
        return getattr(self._manager, name)

