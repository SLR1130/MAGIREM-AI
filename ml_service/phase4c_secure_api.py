"""Phase 4C internal deletion route for the disposable prototype."""
from __future__ import annotations

import os

from flask import jsonify, g, request

from consent_store import ConsentStore
from deletion_ledger import DeletionLedger, DeletionRequestError
from phase4a_secure_api import app, base_api


_consent = ConsentStore()
_memory_path = os.getenv("MINDCARE_MEMORY_DB_PATH", os.path.join(os.path.dirname(__file__), "phase4c-memory.db"))
_ledger = DeletionLedger(_consent, _memory_path)


def _status_code(status: str) -> int:
    if status == "completed":
        return 200
    if status in {"requested", "pending", "processing", "failed_retryable"}:
        return 202
    return 500


@app.post("/internal/memory/delete")
def delete_memory():
    context = getattr(g, "mindcare_auth", None)
    if context is None:
        return jsonify({"success": False, "error": "internal_authorization_failed"}), 401
    payload = request.get_json(silent=True) or {}
    request_id = payload.get("deletion_request_id")
    if not isinstance(request_id, str):
        return jsonify({"success": False, "error": "deletion_request_id_required"}), 400
    try:
        fault_stage = os.getenv("MINDCARE_PHASE4C_TEST_FAIL_STAGE")
        operation = _ledger.request(context.subject, request_id, test_fail_stage=fault_stage)
        if operation["status"] != "completed":
            operation = _ledger.process(operation["request_id"], test_fail_stage=fault_stage)
        return jsonify({"success": operation["status"] == "completed", "deletion": operation}), _status_code(operation["status"])
    except DeletionRequestError as exc:
        return jsonify({"success": False, "error": "deletion_request_rejected", "code": str(exc)}), 403
    except Exception:
        return jsonify({"success": False, "error": "deletion_unavailable"}), 503


if __name__ == "__main__":
    _ledger.recover_pending()
    app.run(host="127.0.0.1", port=int(os.getenv("MINDCARE_PHASE4C_PORT", "5017")), debug=False)

