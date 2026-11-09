"""Development-only structured diagnostics for the MindCare ML service.

Diagnostics deliberately contain metadata only. Callers must never pass raw
messages, generated text, credentials, memory contents, or personal data.
"""

from __future__ import annotations

import json
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def _json_safe(value: Any) -> Any:
    if isinstance(value, (str, int, float, bool)) or value is None:
        return value
    if isinstance(value, dict):
        return {str(key): _json_safe(item) for key, item in value.items()}
    if isinstance(value, (list, tuple)):
        return [_json_safe(item) for item in value]
    return str(value)


def emit_diagnostic(component: str, event: str, correlation_id: str | None = None, **fields: Any) -> None:
    """Emit one metadata-only JSON diagnostic event.

    Events are always written to stderr (captured by the development launcher)
    and optionally appended to MINDCARE_DIAGNOSTICS_LOG.
    """

    enabled = os.getenv('MINDCARE_DIAGNOSTICS_ENABLED', 'false').strip().lower()
    if enabled not in {'1', 'true', 'yes', 'on'}:
        return

    payload = {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "component": component,
        "event": event,
        "correlation_id": correlation_id,
        "fields": _json_safe(fields),
    }
    line = "[MINDCARE_DIAGNOSTIC] " + json.dumps(payload, ensure_ascii=True, sort_keys=True)
    try:
        sys.stderr.write(line + "\n")
        sys.stderr.flush()
    except Exception:
        pass

    destination = os.getenv("MINDCARE_DIAGNOSTICS_LOG", "").strip()
    if not destination:
        return
    try:
        path = Path(destination)
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open("a", encoding="utf-8") as handle:
            handle.write(line + "\n")
    except Exception:
        # Diagnostics must never affect an AI response.
        pass


def elapsed_ms(start: float) -> float:
    return round((time.perf_counter() - start) * 1000, 2)
