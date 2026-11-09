"""Isolated Phase 4A Flask entry point.

The existing unified API remains the response engine.  This wrapper adds an
internal-authentication boundary to every currently memory-capable route and
uses a request-local memory gate so ordinary AI responses can run without
long-term memory.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any, Dict

from flask import g, jsonify, request

os.environ.setdefault(
    "MINDCARE_MEMORY_DB_PATH",
    str(Path(__file__).with_name("phase4a-memory.db").resolve()),
)

import unified_ml_api_with_model as base_api
from internal_auth import InternalAuthError, require_internal_auth, verify_request


class _MemoryStoreProxy:
    """Prevent the imported manager from bypassing the request auth context."""

    def __init__(self, store):
        self._store = store

    @staticmethod
    def _authorized(user_id: Any) -> bool:
        context = getattr(g, "mindcare_auth", None)
        return bool(
            context
            and context.memory_authorized
            and str(user_id) == context.subject
        )

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
            return []
        return self._store.get_preferences(user_id, *args, **kwargs)

    def create_memory(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return None
        return self._store.create_memory(user_id, *args, **kwargs)

    def create_project_memory(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return None
        return self._store.create_project_memory(user_id, *args, **kwargs)

    def update_preference(self, user_id, *args, **kwargs):
        if not self._authorized(user_id):
            return None
        return self._store.update_preference(user_id, *args, **kwargs)

    def __getattr__(self, name):
        return getattr(self._store, name)


class _AuthenticatedMemoryManager:
    def __init__(self, manager):
        self._manager = manager
        self.store = _MemoryStoreProxy(manager.store)

    @staticmethod
    def _authorized() -> bool:
        context = getattr(g, "mindcare_auth", None)
        return bool(context and context.memory_authorized)

    def retrieve_context(self, user_id, *args, **kwargs):
        context = getattr(g, "mindcare_auth", None)
        if not context or not context.memory_authorized or str(user_id) != context.subject:
            return {"memories": [], "projects": [], "preferences": [], "context_text": ""}
        return self._manager.retrieve_context(user_id, *args, **kwargs)

    def handle_message_pair(self, user_id, *args, **kwargs):
        context = getattr(g, "mindcare_auth", None)
        if not context or not context.memory_authorized or str(user_id) != context.subject:
            return {"count": 0, "memory_ids": [], "memories_extracted": [], "memories_stored": []}
        return self._manager.handle_message_pair(user_id, *args, **kwargs)

    def __getattr__(self, name):
        return getattr(self._manager, name)


if base_api._memory_manager is not None:
    base_api._memory_manager = _AuthenticatedMemoryManager(base_api._memory_manager)

app = base_api.app


@app.before_request
def enforce_internal_memory_boundary():
    operation = {
        "/chat": "memory.chat",
        "/api/initial-greeting": "memory.initial_greeting",
        "/internal/memory/delete": "memory.delete",
    }.get(request.path)
    if operation is None or getattr(g, "mindcare_auth", None) is not None:
        return None
    try:
        context = verify_request(request, operation)
        payload = request.get_json(silent=True) or {}
        # Chat and greeting retain their existing subject-binding requirement.
        # Deletion derives the subject exclusively from the authenticated HMAC
        # claim; an optional body user_id is only checked by verify_request.
        if operation != "memory.delete" and "user_id" not in payload:
            return jsonify({"success": False, "error": "authenticated_subject_required"}), 400
        g.mindcare_auth = context
    except InternalAuthError as exc:
        return jsonify({"success": False, "error": "internal_authorization_failed", "code": exc.code}), exc.status
    return None


# These aliases make route-level intent explicit in reviews and tests. The
# before_request boundary remains the single enforcement point for the loaded
# legacy routes, avoiding duplicate response or crisis engines.
secure_chat = require_internal_auth("memory.chat")
secure_initial_greeting = require_internal_auth("memory.initial_greeting")


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=int(os.getenv("MINDCARE_PHASE4A_PORT", "5014")),
        debug=False,
    )

