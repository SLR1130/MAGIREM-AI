"""Small, deterministic output guard for the non-diagnostic support role.

This module does not classify risk, change emotion labels, or make crisis
decisions.  It only catches a narrow set of unsupported clinical or external
action claims in generated text and replaces them with transparent guidance.
"""

from __future__ import annotations

import re
from typing import Tuple


# These patterns intentionally target direct assertions, not ordinary
# (for example, "What is depression?") or a user quoting something another
# qualified human reviewer.
_UNSUPPORTED_DIAGNOSTIC_PATTERNS = (
    re.compile(r"\b(?:i|we)\s+(?:have\s+)?diagnos(?:e|ed|is|ing)\b", re.I),
    re.compile(
        r"\byou\s+(?:have|are\s+suffering\s+from|are\s+clinically\s+)",
        re.I,
    ),
    re.compile(
        r"\b(?:this|that|your\s+(?:emotion|mood|risk|wellbeing)\s*(?:score|result)?|the\s+score)"
        r"\s+(?:proves|confirms|means|shows)\b.*\b(?:you|depressed|anxiety|disorder|mentally\s+ill)",
        re.I,
    ),
    re.compile(
        r"\b(?:emotion|mood|risk|wellbeing)\s*(?:score|label|result)\b"
        r".*\b(?:diagnos|confirms?\s+(?:depression|anxiety|a\s+disorder)|proves?)\b",
        re.I,
    ),
)

_UNSUPPORTED_EXTERNAL_ACTION_PATTERNS = (
    re.compile(
        r"\b(?:i|we)\s+(?:have\s+)?(?:contacted|notified|alerted|assigned|booked|scheduled)\b"
        r".*\b(?:counselor|counsellor|emergency|parent|family|police|authority)\b",
        re.I,
    ),
)


def validate_non_diagnostic_output(response: str) -> Tuple[bool, str]:
    """Return whether *response* stays within the approved support role."""

    text = (response or "").strip()
    if not text:
        return False, "empty_response"

    clinical_terms = ('depression', 'anxiety', 'bipolar', 'ptsd', 'ocd', 'disorder')
    filipino_diagnostic = ('may depression ka', 'may anxiety ka', 'may bipolar ka', 'mayroon kang depression', 'mayroon kang anxiety')
    if any(phrase in text.lower() for phrase in filipino_diagnostic):
        return False, 'unsupported_diagnostic_assertion'
    for index, pattern in enumerate(_UNSUPPORTED_DIAGNOSTIC_PATTERNS):
        if index == 1 and not any(term in text.lower() for term in clinical_terms):
            continue
        if pattern.search(text):
            return False, "unsupported_diagnostic_assertion"

    for pattern in _UNSUPPORTED_EXTERNAL_ACTION_PATTERNS:
        if pattern.search(text):
            return False, "unsupported_external_action_claim"

    return True, "accepted"


def safe_non_diagnostic_fallback(language: str = "en", crisis_level: str = "none") -> str:
    """Provide a transparent support response after an output is rejected.

    Laravel remains the authority for crisis handling.  The crisis branch here
    is only a safe Flask-side message for direct service use and never creates
    alerts, appointments, or notifications.
    """

    normalized = (language or "en").lower()
    if normalized in {"tl", "tagalog"}:
        if crisis_level in {"severe", "critical"}:
            return (
                "Hindi ako makakapagbigay ng diagnosis mula sa chat. Kung nasa agarang panganib ka, "
                "humingi ka ngayon ng tulong sa emergency services o sa taong pinagkakatiwalaan mo."
            )
        return (
            "Hindi ako makakapagbigay ng diagnosis mula sa chat o emotion signal. Maaari kitang tulungan "
            "unawain ang nararanasan mo at mag-isip ng mga susunod na hakbang. Maaari mo bang ikuwento pa?"
        )
    if normalized in {"taglish", "tl-en", "filipino-english"}:
        if crisis_level in {"severe", "critical"}:
            return (
                "Hindi ako makakapag-diagnose mula sa chat. Kung may immediate danger, please contact "
                "emergency services or a trusted person ngayon."
            )
        return (
            "I can't diagnose a mental health condition from a chat or emotion signal. Maaari kitang "
            "tulungan mag-explore ng nararanasan mo at practical next steps. Gusto mo bang mag-share pa?"
        )
    if crisis_level in {"severe", "critical"}:
        return (
            "I can't diagnose a mental health condition from a chat or emotion signal. If you may be in "
            "immediate danger, contact emergency services or a trusted person now."
        )
    return (
        "I can't diagnose a mental health condition from a chat or emotion signal. I can help you explore "
        "what you're experiencing and consider practical next steps. Would you like to share more?"
    )


def enforce_non_diagnostic_output(
    response: str, language: str = "en", crisis_level: str = "none"
) -> Tuple[str, str]:
    """Return safe text and a machine-readable reason without raising."""

    try:
        valid, reason = validate_non_diagnostic_output(response)
        if valid:
            return response.strip(), reason
        return safe_non_diagnostic_fallback(language, crisis_level), reason
    except Exception:
        # A guard failure must not leak unchecked model output.
        return safe_non_diagnostic_fallback(language, crisis_level), "guard_error"
