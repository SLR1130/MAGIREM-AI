"""Focused regression checks for the Flask initial-greeting guard."""

from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))

from non_diagnostic_safety import enforce_non_diagnostic_output, validate_non_diagnostic_output


def test_generated_diagnostic_greeting_is_replaced():
    safe, reason = enforce_non_diagnostic_output(
        "You have depression based on your emotion score.", "en", "none"
    )

    assert reason == "unsupported_diagnostic_assertion"
    assert validate_non_diagnostic_output(safe)[0]
    assert "you have depression" not in safe.lower()


def test_generated_external_action_claim_is_replaced():
    safe, reason = enforce_non_diagnostic_output(
        "I contacted your counselor and booked an appointment for you.", "en", "none"
    )

    assert reason == "unsupported_external_action_claim"
    assert validate_non_diagnostic_output(safe)[0]


def test_initial_greeting_route_contains_guard_boundary():
    source = (Path(__file__).resolve().parent / "unified_ml_api_with_model.py").read_text(
        encoding="utf-8"
    )
    greeting_start = source.index("def initial_greeting():")
    greeting_end = source.index("@app.route('/analyze'", greeting_start)
    greeting_source = source[greeting_start:greeting_end]

    assert "enforce_non_diagnostic_output" in greeting_source
    assert "response_source" in greeting_source
