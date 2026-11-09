"""Consent-gated, non-persisting Gemma detailed-emotion shadow interpreter.

This module is intentionally dormant unless the runtime feature flag is enabled.
It reuses the shared structured prompt and schema, derives categories from the
central taxonomy, uses the shared cross-process GPU queue, and never stores or
returns a numeric confidence score. A shadow result must never become the live
six-class emotion or a crisis decision.
"""

from __future__ import annotations

import hashlib
import os
import sys
import time
from typing import Any, Dict

import requests

from emotion_taxonomy import load_taxonomy
from emotion_interpreter_schema import build_ollama_output_schema
from gpu_inference_queue import (
    InferenceAlreadyQueued,
    InferenceQueueBusy,
    InferenceQueueUnavailable,
    queue as gpu_queue,
)


class DetailedShadowUnavailable(RuntimeError):
    """A bounded, non-user-visible shadow failure."""

    code = "DETAILED_SHADOW_UNAVAILABLE"


def _env_bool(name: str, default: bool = False) -> bool:
    return os.getenv(name, "true" if default else "false").strip().lower() in {"1", "true", "yes", "on"}


class DetailedEmotionShadowInterpreter:
    """Run the frozen Gemma interpreter only for an explicitly consented request."""

    def __init__(self) -> None:
        self.enabled = (
            _env_bool("MINDCARE_DETAILED_EMOTION_SHADOW_ENABLED", False)
            and _env_bool("MINDCARE_DETAILED_EMOTION_SHADOW_APPROVED", False)
        )
        self.model = os.getenv("MINDCARE_OLLAMA_MODEL", "gemma3:4b")
        self.url = os.getenv("MINDCARE_OLLAMA_URL", "http://127.0.0.1:11434")
        try:
            configured_timeout = int(os.getenv("MINDCARE_DETAILED_EMOTION_SHADOW_TIMEOUT", "15"))
        except ValueError:
            configured_timeout = 15
        self.timeout = max(2, min(configured_timeout, 15))
        self.taxonomy = load_taxonomy()
        self.index = {item["key"]: item for item in self.taxonomy.get("emotions", [])}
        self.valid_categories = {item["key"] for item in self.taxonomy.get("categories", [])}
        self.output_schema = build_ollama_output_schema(self.index, self.valid_categories)

    def assess(self, message: str, *, consent_granted: bool, request_id: str) -> Dict[str, Any]:
        """Return a sanitized shadow result or a bounded failure.

        `consent_granted` must be supplied by an authorized Laravel caller. This
        method intentionally has no persistence side effect and does not accept a
        user-controlled category or confidence value.
        """
        if not self.enabled:
            raise DetailedShadowUnavailable("shadow_disabled")
        if not consent_granted:
            raise DetailedShadowUnavailable("shadow_consent_required")
        if not isinstance(message, str) or not message.strip() or len(message) > 12000:
            raise DetailedShadowUnavailable("invalid_shadow_message")
        if not isinstance(request_id, str) or len(request_id) < 8 or len(request_id) > 80:
            raise DetailedShadowUnavailable("invalid_shadow_request")

        # Import the evaluator so the frozen prompt and JSON parser cannot drift.
        sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
        from phase2_emotion_feasibility_eval import gemma_prompt, parse_gemma_json

        valid_keys = sorted(self.index)
        valid_categories = self.valid_categories
        started = time.perf_counter()
        try:
            with gpu_queue.acquire(request_id, priority="normal") as admission:
                response = requests.post(
                    f"{self.url.rstrip('/')}/api/chat",
                    json={
                        "model": self.model,
                        "messages": [
                            {"role": "system", "content": "You are a cautious emotion annotation assistant. Output only the requested JSON."},
                            {"role": "user", "content": gemma_prompt(message, valid_keys, sorted(valid_categories))},
                        ],
                        "format": self.output_schema,
                        "stream": False,
                        "options": {"temperature": 0, "num_predict": 180},
                    },
                    timeout=(2, self.timeout),
                )
                if response.status_code != 200:
                    raise DetailedShadowUnavailable("ollama_http_error")
                payload = response.json()
                content = ((payload.get("message") or {}).get("content") or payload.get("response") or "")
                parsed = parse_gemma_json(content, self.index, valid_categories, str(self.taxonomy.get("version", "1.0.0")), source_text=message)
                if not parsed.get("schema_valid"):
                    raise DetailedShadowUnavailable("invalid_shadow_schema")
                parsed["classification_source"] = "gemma_shadow"
                result = {key: parsed.get(key) for key in (
                    "status", "attribution_scope", "primary_emotion", "secondary_emotions",
                    "other_emotions", "uncertainty_reason", "classification_source",
                    "taxonomy_version", "category_key", "category_keys", "other_category_keys",
                    "schema_valid", "felt_by_student", "evidence",
                )}
                result.update({
                    "shadow_only": True,
                    "stored": False,
                    "message_sha256": hashlib.sha256(message.encode("utf-8")).hexdigest(),
                    "queue_wait_ms": admission.wait_ms,
                    "latency_ms": round((time.perf_counter() - started) * 1000, 2),
                })
                return result
        except (InferenceAlreadyQueued, InferenceQueueBusy, InferenceQueueUnavailable) as exc:
            raise DetailedShadowUnavailable(getattr(exc, "code", "gpu_queue_unavailable")) from exc
        except requests.RequestException as exc:
            raise DetailedShadowUnavailable("ollama_unavailable") from exc
        except (TypeError, ValueError, KeyError) as exc:
            raise DetailedShadowUnavailable("invalid_shadow_response") from exc
