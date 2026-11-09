"""Short-lived HMAC authentication for the isolated Phase 4A prototype."""

from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import re
import sqlite3
import threading
import time
from dataclasses import dataclass
from functools import wraps
from typing import Any, Callable, Dict, Optional

from flask import g, jsonify, request

VERSION = "v1"
ALGORITHM = "HS256"
TOKEN_PREFIX = "MindCare-HMAC"
ALLOWED_OPERATIONS = {"memory.chat", "memory.initial_greeting", "memory.delete"}
_NONCE_LOCK = threading.Lock()
_SEEN_NONCES: Dict[str, float] = {}


def _replay_db_path() -> str:
    return os.getenv("MINDCARE_INTERNAL_HMAC_REPLAY_DB", os.path.join(os.path.dirname(__file__), "phase4a-replay.db"))


def _open_replay_db():
    path = _replay_db_path()
    if path != ":memory":
        os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    db = sqlite3.connect(path, timeout=5.0)
    db.execute("PRAGMA busy_timeout=5000")
    db.execute("CREATE TABLE IF NOT EXISTS replay_nonces (jti TEXT PRIMARY KEY, expires_at REAL NOT NULL)")
    db.commit()
    return db


class InternalAuthError(Exception):
    def __init__(self, code: str, status: int = 401):
        super().__init__(code)
        self.code = code
        self.status = status


@dataclass(frozen=True)
class AuthContext:
    subject: str
    operation: str
    memory_authorized: bool
    request_id: str
    claims: Dict[str, Any]


def _setting(name: str, default: str = "") -> str:
    return os.getenv(name, default)


def _secret() -> bytes:
    value = _setting("MINDCARE_INTERNAL_HMAC_SECRET")
    if not value:
        raise InternalAuthError("auth_not_configured", 503)
    return value.encode("utf-8")


def _b64(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).decode("ascii").rstrip("=")


def _unb64(value: str) -> bytes:
    if not re.fullmatch(r"[A-Za-z0-9_-]+", value):
        raise ValueError("invalid_base64url")
    return base64.urlsafe_b64decode(value + "=" * (-len(value) % 4))


def _json_bytes(value: Dict[str, Any]) -> bytes:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":")).encode("utf-8")


def body_digest(body: bytes) -> str:
    return hashlib.sha256(body).hexdigest()


def _signing_input(key_id: str, header_segment: str, claims_segment: str, digest: str) -> bytes:
    return f"{VERSION}.{key_id}.{header_segment}.{claims_segment}.{digest}".encode("ascii")


def build_token(
    body: bytes,
    *,
    subject: str,
    operation: str,
    method: str = "POST",
    path: Optional[str] = None,
    memory_authorized: bool = False,
    consent_purpose: str = "long_term_memory",
    consent_state: str = "not_granted",
    consent_policy_version: str = "",
    consent_revision: int = 0,
    request_id: Optional[str] = None,
    issued_at: Optional[int] = None,
    expires_at: Optional[int] = None,
) -> str:
    key_id = _setting("MINDCARE_INTERNAL_HMAC_KEY_ID", "phase4a-dev")
    now = int(time.time()) if issued_at is None else int(issued_at)
    ttl = int(_setting("MINDCARE_INTERNAL_HMAC_TTL", "60"))
    exp = now + ttl if expires_at is None else int(expires_at)
    claims = {
        "iss": _setting("MINDCARE_INTERNAL_HMAC_ISSUER", "laravel-phase4a"),
        "aud": _setting("MINDCARE_INTERNAL_HMAC_AUDIENCE", "mindcare-flask-phase4a"),
        "sub": str(subject),
        "op": operation,
        "method": method.upper(),
        "path": path or ("/api/initial-greeting" if operation == "memory.initial_greeting" else ("/internal/memory/delete" if operation == "memory.delete" else "/chat")),
        "iat": now,
        "exp": exp,
        "jti": request_id or _b64(os.urandom(16)),
        "purpose": "internal-memory-operation",
        "body_sha256": body_digest(body),
        "memory_authorized": bool(memory_authorized),
        "consent_purpose": consent_purpose,
        "consent_state": consent_state,
        "consent_policy_version": consent_policy_version,
        "consent_revision": int(consent_revision),
    }
    header = {"alg": ALGORITHM, "typ": "MINDCARE-HMAC", "kid": key_id}
    header_segment = _b64(_json_bytes(header))
    claims_segment = _b64(_json_bytes(claims))
    signature = hmac.new(
        _secret(),
        _signing_input(key_id, header_segment, claims_segment, claims["body_sha256"]),
        hashlib.sha256,
    ).digest()
    return ".".join((VERSION, key_id, header_segment, claims_segment, _b64(signature)))


def _remember_nonce(nonce: str, expires_at: int) -> None:
    now = time.time()
    # SQLite provides atomic uniqueness across threads, worker processes, and
    # service restarts. The database contains only short-lived request IDs.
    try:
        with _NONCE_LOCK:
            db = _open_replay_db()
            try:
                db.execute("BEGIN IMMEDIATE")
                db.execute("DELETE FROM replay_nonces WHERE expires_at < ?", (now,))
                db.execute("INSERT INTO replay_nonces (jti, expires_at) VALUES (?, ?)", (nonce, float(expires_at)))
                db.commit()
            except sqlite3.IntegrityError:
                db.rollback()
                raise InternalAuthError("replayed_request", 401)
            finally:
                db.close()
    except InternalAuthError:
        raise
    except (sqlite3.Error, OSError):
        raise InternalAuthError("replay_store_unavailable", 503)


def reset_replay_cache() -> None:
    with _NONCE_LOCK:
        _SEEN_NONCES.clear()
        try:
            db = _open_replay_db()
            db.execute("DELETE FROM replay_nonces")
            db.commit()
            db.close()
        except (sqlite3.Error, OSError):
            pass


def verify_request(req, expected_operation: str) -> AuthContext:
    if expected_operation not in ALLOWED_OPERATIONS:
        raise InternalAuthError("unsupported_operation", 403)
    authorization = req.headers.get("Authorization", "")
    if not authorization.startswith(TOKEN_PREFIX + " "):
        raise InternalAuthError("missing_signature", 401)
    parts = authorization[len(TOKEN_PREFIX) + 1 :].split(".")
    if len(parts) != 5:
        raise InternalAuthError("invalid_signature", 401)
    version, key_id, header_segment, claims_segment, signature_segment = parts
    if version != VERSION:
        raise InternalAuthError("invalid_version", 401)
    expected_key_id = _setting("MINDCARE_INTERNAL_HMAC_KEY_ID", "phase4a-dev")
    if not hmac.compare_digest(key_id, expected_key_id):
        raise InternalAuthError("unknown_key", 401)
    try:
        header = json.loads(_unb64(header_segment).decode("utf-8"))
        claims = json.loads(_unb64(claims_segment).decode("utf-8"))
        supplied_signature = _unb64(signature_segment)
    except (ValueError, UnicodeDecodeError, json.JSONDecodeError):
        raise InternalAuthError("invalid_signature", 401)
    if header.get("alg") != ALGORITHM or header.get("typ") != "MINDCARE-HMAC" or header.get("kid") != key_id:
        raise InternalAuthError("invalid_algorithm", 401)
    raw_body = req.get_data(cache=True)
    digest = body_digest(raw_body)
    supplied_digest = claims.get("body_sha256")
    if not isinstance(supplied_digest, str) or not hmac.compare_digest(supplied_digest, digest):
        raise InternalAuthError("body_integrity_failed", 401)
    expected_signature = hmac.new(
        _secret(), _signing_input(key_id, header_segment, claims_segment, supplied_digest), hashlib.sha256
    ).digest()
    if not hmac.compare_digest(expected_signature, supplied_signature):
        raise InternalAuthError("invalid_signature", 401)
    if claims.get("iss") != _setting("MINDCARE_INTERNAL_HMAC_ISSUER", "laravel-phase4a"):
        raise InternalAuthError("invalid_claims", 401)
    if claims.get("aud") != _setting("MINDCARE_INTERNAL_HMAC_AUDIENCE", "mindcare-flask-phase4a"):
        raise InternalAuthError("invalid_claims", 401)
    if claims.get("op") != expected_operation:
        raise InternalAuthError("operation_mismatch", 403)
    expected_path = "/api/initial-greeting" if expected_operation == "memory.initial_greeting" else ("/internal/memory/delete" if expected_operation == "memory.delete" else "/chat")
    if claims.get("method") != req.method.upper() or claims.get("path") != expected_path:
        raise InternalAuthError("request_binding_mismatch", 403)
    if claims.get("purpose") != "internal-memory-operation":
        raise InternalAuthError("invalid_purpose", 403)
    subject = claims.get("sub")
    request_id = claims.get("jti")
    issued_at = claims.get("iat")
    expires_at = claims.get("exp")
    if not isinstance(subject, str) or not subject or len(subject) > 128:
        raise InternalAuthError("invalid_subject", 403)
    if not isinstance(request_id, str) or not request_id or len(request_id) > 128:
        raise InternalAuthError("invalid_request_id", 401)
    if not isinstance(issued_at, (int, float)) or not isinstance(expires_at, (int, float)):
        raise InternalAuthError("invalid_time_claims", 401)
    now = time.time()
    skew = int(_setting("MINDCARE_INTERNAL_HMAC_CLOCK_SKEW", "10"))
    ttl = int(_setting("MINDCARE_INTERNAL_HMAC_TTL", "60"))
    if issued_at > now + skew or expires_at < now - skew or expires_at <= issued_at:
        raise InternalAuthError("expired_signature", 401)
    if expires_at - issued_at > ttl + skew:
        raise InternalAuthError("invalid_lifetime", 401)
    payload = req.get_json(silent=True) or {}
    if "user_id" in payload and str(payload.get("user_id")) != subject:
        raise InternalAuthError("subject_mismatch", 403)
    _remember_nonce(request_id, int(expires_at))
    return AuthContext(
        subject=subject,
        operation=expected_operation,
        memory_authorized=claims.get("memory_authorized") is True,
        request_id=request_id,
        claims=claims,
    )


def require_internal_auth(operation: str) -> Callable:
    def decorator(view: Callable) -> Callable:
        @wraps(view)
        def wrapped(*args, **kwargs):
            try:
                g.mindcare_auth = verify_request(request, operation)
            except InternalAuthError as exc:
                return jsonify({"success": False, "error": "internal_authorization_failed", "code": exc.code}), exc.status
            return view(*args, **kwargs)
        return wrapped
    return decorator

