"""Synthetic Gate 3 checks; no Laravel, Ollama, or student memory is used."""

import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

from non_diagnostic_safety import enforce_non_diagnostic_output, safe_non_diagnostic_fallback, validate_non_diagnostic_output


def test_ordinary_support_and_educational_questions_are_allowed():
    for text in (
        "It sounds like exam pressure is exhausting. What part feels hardest today?",
        "What is depression? I want general information.",
        "You have support from your friends, and we can think through one next step.",
    ):
        assert validate_non_diagnostic_output(text) == (True, "accepted")


def test_diagnostic_assertions_are_replaced_in_each_supported_language():
    samples = (
        ("You have depression based on your emotion score.", "en"),
        ("May depression ka dahil sa score mo.", "tl"),
        ("Your mood proves na may anxiety ka.", "taglish"),
    )
    for text, language in samples:
        safe, reason = enforce_non_diagnostic_output(text, language)
        assert reason == "unsupported_diagnostic_assertion"
        assert validate_non_diagnostic_output(safe)[0]


def test_unsupported_external_action_claim_is_replaced_without_crisis_rerouting():
    safe, reason = enforce_non_diagnostic_output(
        "I contacted your counselor and scheduled an appointment for you.",
        "en",
        "none",
    )
    assert reason == "unsupported_external_action_claim"
    assert "contacted" not in safe.lower()
    assert validate_non_diagnostic_output(safe)[0]


def test_crisis_safe_fallback_is_supportive_and_does_not_create_actions():
    response = safe_non_diagnostic_fallback("taglish", "critical")
    assert "emergency" in response.lower()
    assert "appointment" not in response.lower()
    assert "alert" not in response.lower()
