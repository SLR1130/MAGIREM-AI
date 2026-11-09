"""Evaluation-only schema for the optional fine-grained emotion interpreter.

The six-class classifier and crisis pipeline do not import this module. It is
used by the offline Gemma feasibility evaluation to turn a model proposal into
a small, auditable result. Categories are always derived from the approved
detailed-emotion taxonomy; a model supplied category is retained only as a
diagnostic mismatch field.
"""

from __future__ import annotations

import re
import unicodedata
from typing import Any, Dict, Mapping, Optional


STATUS_CLEAR = "clear"
STATUS_MIXED = "mixed"
STATUS_UNCERTAIN = "uncertain"
STATUS_NO_EMOTION = "no_emotion"
STATUS_ATTRIBUTED_OTHER = "attributed_other"
VALID_STATUSES = {
    STATUS_CLEAR,
    STATUS_MIXED,
    STATUS_UNCERTAIN,
    STATUS_NO_EMOTION,
    STATUS_ATTRIBUTED_OTHER,
}

ATTR_STUDENT = "student"
ATTR_OTHER = "other"
ATTR_BOTH = "both"
ATTR_HYPOTHETICAL = "hypothetical"
ATTR_NONE = "none"
VALID_ATTRIBUTION_SCOPES = {ATTR_STUDENT, ATTR_OTHER, ATTR_BOTH, ATTR_HYPOTHETICAL, ATTR_NONE}


def build_ollama_output_schema(
    index: Mapping[str, Mapping[str, Any]],
    valid_categories: set[str],
) -> Dict[str, Any]:
    """Return strict taxonomy-constrained JSON schema for Ollama ``format``."""
    emotion_keys = sorted(index)
    category_keys = sorted(valid_categories)
    nullable_emotion = {"anyOf": [{"type": "string", "enum": emotion_keys}, {"type": "null"}]}
    nullable_category = {"anyOf": [{"type": "string", "enum": category_keys}, {"type": "null"}]}
    return {
        "type": "object",
        "additionalProperties": False,
        "required": ["status", "attribution_scope", "primary_emotion", "secondary_emotions", "other_emotions", "uncertainty_reason", "felt_by_student", "evidence", "category_key", "emotion_evidence"],
        "properties": {
            "status": {"type": "string", "enum": sorted(VALID_STATUSES)},
            "attribution_scope": {"type": "string", "enum": sorted(VALID_ATTRIBUTION_SCOPES)},
            "primary_emotion": nullable_emotion,
            "secondary_emotions": {"type": "array", "items": {"type": "string", "enum": emotion_keys}, "maxItems": 4},
            "other_emotions": {"type": "array", "items": {"type": "string", "enum": emotion_keys}, "maxItems": 4},
            "uncertainty_reason": {"anyOf": [{"type": "string", "maxLength": 240}, {"type": "null"}]},
            "felt_by_student": {"type": "boolean"},
            "evidence": {"type": "string", "maxLength": 240},
            "category_key": nullable_category,
            # Per-label evidence lets the constrained interpreter
            # support an implicit feeling without weakening vocabulary checks.
            "emotion_evidence": {
                "type": "array",
                "maxItems": 4,
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "required": ["emotion", "evidence"],
                    "properties": {
                        "emotion": {"type": "string", "enum": emotion_keys},
                        "evidence": {"type": "string", "minLength": 3, "maxLength": 160},
                    },
                },
            },
        },
    }


_REPORTED_SPEECH = re.compile(r"\b(?:sabi|sinabi|ayon|said|says|told|according\s+to)\b", re.IGNORECASE)
_OTHER_ROLE = re.compile(
    r"\b(?:friend|classmate|cousin|roommate|brother|sister|mother|father|someone|kaibigan|kaklase|pinsan|kapatid|siya)\b",
    re.IGNORECASE,
)
_OTHER_CLAUSE = re.compile(
    r"\b(?:he|she|they|siya)\s+(?:is|was|feels?|felt|seems?|looks?|nakak|masaya|malungkot|malungkot|kinakabahan)\b",
    re.IGNORECASE,
)
_OTHER_ROLE_CLAUSE = re.compile(
    r"\b(?:friend|classmate|cousin|roommate|brother|sister|mother|father|someone|kaibigan|kaklase|pinsan|kapatid)\b\s+(?:is|was|feels?|felt|seems?|looks?|nakak|masaya|malungkot|kinakabahan|distressed|happy|sad|anxious)\b",
    re.IGNORECASE,
)
_SELF_LIVED = re.compile(
    r"\b(?:i|ako)\s+(?:feel|felt|am|was|have|had|nakakaramdam|naramdaman|masaya|malungkot|kinakabahan|galit|pagod|proud|excited|anxious)\b",
    re.IGNORECASE,
)
_SELF_NEGATED = re.compile(r"(?:\b(?:i|ako)\s+(?:do\s+not|don't|cannot|can't|hindi\s+(?:ako|ko))\s+(?:feel|feelings?|nararamdaman)\b|\b(?:not\s+describing|no\s+feeling)\b|\bwala\s+akong\s+(?:nararamdaman|maramdaman|feelings?|sinasabing\s+(?:emotion|feeling))\b)", re.IGNORECASE)
_HYPOTHETICAL = re.compile(r"\b(?:if|would|might|could)\b.*\b(?:feel|felt)\b|\bkung\b.*\b(?:maramdaman|feel|baka)\b", re.IGNORECASE | re.DOTALL)
_INFORMATION_REQUEST = re.compile(r"(?:\?|\b(?:what|how|when|where|which|can\s+you|define|definition|meaning|explain|asking\s+for)\b)", re.IGNORECASE)


def infer_attribution_scope(text: str) -> Optional[str]:
    """Infer only broad attribution cues from source text, never emotion labels.

    This is a conservative guard for reported speech. It is intentionally used
    only by the evaluation/shadow parser: the six-class production classifier
    and crisis pipeline do not call it.
    """
    if not isinstance(text, str) or not text.strip():
        return None
    value = text.strip()
    if _HYPOTHETICAL.search(value) and not _SELF_LIVED.search(value):
        return ATTR_HYPOTHETICAL
    has_other = bool(
        (_REPORTED_SPEECH.search(value) and _OTHER_ROLE.search(value))
        or _OTHER_CLAUSE.search(value)
        or _OTHER_ROLE_CLAUSE.search(value)
    )
    has_self = bool(_SELF_LIVED.search(value)) and not bool(_SELF_NEGATED.search(value))
    if has_other and has_self:
        return ATTR_BOTH
    if has_other:
        return ATTR_OTHER
    if (not has_self and _INFORMATION_REQUEST.search(value)) or _SELF_NEGATED.search(value):
        return ATTR_NONE
    return None

MODEL_STATE_TO_STATUS = {
    "clear": STATUS_CLEAR,
    "mixed": STATUS_MIXED,
    "mentioned_other_person": STATUS_ATTRIBUTED_OTHER,
    "attributed_other": STATUS_ATTRIBUTED_OTHER,
    "insufficient_evidence": STATUS_UNCERTAIN,
    "uncertain": STATUS_UNCERTAIN,
    "no_specific_emotion": STATUS_NO_EMOTION,
    "no_emotion": STATUS_NO_EMOTION,
}


def _clean_reason(value: Any) -> Optional[str]:
    if value is None:
        return None
    value = str(value).strip()
    return value[:240] or None


def _normalize_cue_text(value: str) -> str:
    """Normalize text only for conservative cue matching.

    This is deliberately not an emotion classifier. It is used to decide
    whether a model-proposed *secondary* label has an explicit lexical cue in
    the student's source message before the label is allowed into the
    structured result.
    """
    value = unicodedata.normalize("NFKC", value).casefold()
    value = re.sub(r"[\u2010-\u2015\u2212]", "-", value)
    return re.sub(r"\s+", " ", value).strip()


def secondary_emotion_has_source_cue(
    emotion_key: str,
    index: Mapping[str, Mapping[str, Any]],
    source_text: str,
) -> bool:
    """Return whether a secondary label has an explicit source-text cue.

    Primary labels may remain model-inferred because they summarize the whole
    message. Secondary labels are held to a stricter standard: an approved
    alias (including multilingual/Taglish aliases maintained in the taxonomy)
    must appear as a word or phrase in the source. This prevents plausible
    but unsupported emotion pairs without hardcoding individual sentences.
    """
    if not isinstance(source_text, str) or not source_text.strip():
        return True
    item = index.get(emotion_key) or {}
    aliases = [emotion_key, item.get("label", ""), *(item.get("aliases") or [])]
    text = _normalize_cue_text(source_text)
    for alias in aliases:
        if not isinstance(alias, str):
            continue
        cue = _normalize_cue_text(alias)
        if not cue:
            continue
        # Use word boundaries for single words, while permitting punctuation
        # and spaces inside multi-word aliases.
        pattern = r"(?<!\w)" + re.escape(cue).replace(r"\ ", r"\s+") + r"(?!\w)"
        if re.search(pattern, text, re.IGNORECASE):
            return True
    return False


_IMPLICIT_SOURCE_MARKER = re.compile(
    r"\b(?:i|me|my|ako|ko|kong|aking|parang|feel|feels?|felt|feeling|stomach|chest|heart|tight|knot|"
    r"sleep|breath|sabay-sabay|walang\s+makausap|miss\s+ko|malayo\s+sa|nangangamba|"
    r"nag-aalinlangan|nahihiya|nakokonsensya|pag-asa|ipinagmamalaki)\b",
    re.IGNORECASE,
)


def secondary_emotion_has_source_evidence(
    emotion_key: str,
    source_text: str,
    emotion_evidence: Any,
) -> bool:
    """Allow an implicit secondary only when the model quotes source text.

    The quoted span is a provenance check, not a classifier: the approved
    label must be paired with a short exact normalized span from the message.
    """
    if not isinstance(source_text, str) or not source_text.strip():
        return True
    if not isinstance(emotion_evidence, list):
        return False
    source = _normalize_cue_text(source_text)
    for item in emotion_evidence:
        if not isinstance(item, Mapping) or item.get("emotion") != emotion_key:
            continue
        evidence = item.get("evidence")
        if not isinstance(evidence, str):
            continue
        cue = _normalize_cue_text(evidence)
        if len(cue) < 3 or cue == source:
            continue
        # A cited span must look like a lived/self-referential or affective
        # expression, not merely any contextual noun phrase the model can quote.
        if not _IMPLICIT_SOURCE_MARKER.search(cue):
            continue
        if cue in source:
            return True
    return False


def validate_interpreter_result(
    raw: Mapping[str, Any],
    index: Mapping[str, Mapping[str, Any]],
    *,
    taxonomy_version: str,
    classification_source: str = "gemma_evaluation",
) -> Dict[str, Any]:
    """Validate a model proposal and derive its authoritative category.

    Invalid detailed labels are rejected rather than normalized into a nearby
    label. The return value deliberately contains no confidence score.
    """
    keys = set(index.keys())
    state_raw = raw.get("status") or raw.get("classification_state")
    state = MODEL_STATE_TO_STATUS.get(str(state_raw).strip().lower()) if state_raw is not None else None

    primary = raw.get("primary_emotion", raw.get("detailed_emotion_key"))
    primary = primary.strip() if isinstance(primary, str) else primary
    primary_valid = primary in keys

    attribution_scope = raw.get("attribution_scope")
    attribution_scope = str(attribution_scope).strip().lower() if isinstance(attribution_scope, str) else None
    if attribution_scope not in VALID_ATTRIBUTION_SCOPES:
        if state == STATUS_ATTRIBUTED_OTHER:
            attribution_scope = ATTR_OTHER
        elif raw.get("felt_by_student") is True:
            attribution_scope = ATTR_STUDENT
        else:
            attribution_scope = ATTR_NONE

    # Older deterministic Gemma JSON sometimes omitted the state field while
    # supplying a valid label and felt_by_student. Infer only the unambiguous
    # cases; missing attribution/no-emotion states remain invalid.
    if state is None:
        if raw.get("uncertain") is True:
            state = STATUS_UNCERTAIN
        elif primary_valid and isinstance(raw.get("secondary_emotions", raw.get("secondary_emotion_keys", [])), list) and raw.get("secondary_emotions", raw.get("secondary_emotion_keys", [])):
            state = STATUS_MIXED
        elif primary_valid and raw.get("felt_by_student") is True:
            state = STATUS_CLEAR

    secondary_raw = raw.get("secondary_emotions", raw.get("secondary_emotion_keys", []))
    if not isinstance(secondary_raw, list):
        secondary_raw = []
    secondary: list[str] = []
    invalid_secondary = False
    for item in secondary_raw:
        if not isinstance(item, str):
            invalid_secondary = True
            continue
        item = item.strip()
        if item in keys and item != primary and item not in secondary:
            secondary.append(item)
        elif item not in keys:
            invalid_secondary = True

    other_raw = raw.get("other_emotions", raw.get("other_emotion_keys", []))
    if not isinstance(other_raw, list):
        other_raw = []
    other_emotions: list[str] = []
    for item in other_raw:
        if not isinstance(item, str) or item.strip() not in keys:
            invalid_secondary = True
            continue
        item = item.strip()
        if item not in other_emotions:
            other_emotions.append(item)

    felt = raw.get("felt_by_student")
    if state == STATUS_ATTRIBUTED_OTHER:
        primary = None
        secondary = []
        felt = False
        attribution_scope = ATTR_OTHER
    elif state == STATUS_UNCERTAIN:
        # An uncertain interpretation must not carry a concrete label into
        # internal context or analytics. Keep attribution/felt metadata, but
        # require the emotion fields to remain unresolved.
        primary = None
        secondary = []
    elif state == STATUS_NO_EMOTION:
        primary = None
        secondary = []
        felt = False
        if attribution_scope == ATTR_STUDENT:
            attribution_scope = ATTR_NONE

    if attribution_scope in {ATTR_OTHER, ATTR_HYPOTHETICAL, ATTR_NONE}:
        if attribution_scope == ATTR_OTHER:
            primary = None
            secondary = []
            felt = False
            # Attribution is a semantic result, not merely a hint.  A model
            # that says ``clear`` while assigning the feeling to somebody else
            # is canonicalized to the explicit non-student state.
            state = STATUS_ATTRIBUTED_OTHER
        elif attribution_scope == ATTR_HYPOTHETICAL:
            primary = None
            secondary = []
            felt = False
            if state in {STATUS_CLEAR, STATUS_MIXED}:
                state = STATUS_UNCERTAIN
        elif attribution_scope == ATTR_NONE:
            # ``none`` covers an unclear/unsupported attribution.  Never let
            # a candidate label leak into the student's fields in that case.
            primary = None
            secondary = []
            felt = False
            if state in {STATUS_CLEAR, STATUS_MIXED}:
                state = STATUS_UNCERTAIN

    if attribution_scope == ATTR_BOTH and not other_emotions:
        invalid_secondary = True

    # A clear student result cannot carry a second student emotion. When the
    # constrained model supplies a valid secondary label, canonicalize the
    # state to mixed; uncertain/no-emotion states remain untouched.
    if state == STATUS_CLEAR and attribution_scope == ATTR_STUDENT and primary_valid and secondary and felt is True:
        state = STATUS_MIXED

    if state == STATUS_MIXED and not (primary_valid and secondary):
        # If secondary evidence was rejected but the primary is a valid,
        # clearly student-attributed feeling, preserve that primary as clear
        # rather than turning a supported result into an opaque failure.
        if primary_valid and felt is True and attribution_scope in {ATTR_STUDENT, ATTR_BOTH}:
            state = STATUS_CLEAR
        else:
            state = STATUS_UNCERTAIN

    schema_valid = (
        state in VALID_STATUSES
        and not invalid_secondary
        and (state in {STATUS_NO_EMOTION, STATUS_ATTRIBUTED_OTHER} or primary_valid or state == STATUS_UNCERTAIN)
        and isinstance(felt, bool)
        and attribution_scope in VALID_ATTRIBUTION_SCOPES
        and (
            state == STATUS_NO_EMOTION
            or (attribution_scope in {ATTR_STUDENT, ATTR_BOTH} and felt is True)
            or (attribution_scope in {ATTR_OTHER, ATTR_HYPOTHETICAL, ATTR_NONE} and felt is False)
        )
    )
    category = index.get(primary, {}).get("category_key") if primary_valid else None
    category_keys = []
    for emotion in ([primary] if primary_valid else []) + secondary:
        mapped = index.get(emotion, {}).get("category_key")
        if mapped and mapped not in category_keys:
            category_keys.append(mapped)
    other_category_keys = []
    for emotion in other_emotions:
        mapped = index.get(emotion, {}).get("category_key")
        if mapped and mapped not in other_category_keys:
            other_category_keys.append(mapped)
    model_category = raw.get("category_key")
    model_category = model_category if isinstance(model_category, str) else None

    return {
        "status": state,
        "attribution_scope": attribution_scope,
        "primary_emotion": primary if primary_valid else None,
        "secondary_emotions": secondary,
        "other_emotions": other_emotions,
        "uncertainty_reason": _clean_reason(raw.get("uncertainty_reason")),
        "classification_source": classification_source,
        "taxonomy_version": taxonomy_version,
        "category_key": category,
        "category_keys": category_keys,
        "other_category_keys": other_category_keys,
        "schema_valid": bool(schema_valid),
        "felt_by_student": felt if isinstance(felt, bool) else None,
        "evidence": _clean_reason(raw.get("evidence")),
        "model_category_key": model_category,
        "model_category_matches_derived": model_category == category,
    }


def validated_response_context(
    raw: Mapping[str, Any],
    index: Mapping[str, Mapping[str, Any]],
    *,
    taxonomy_version: str,
) -> Optional[Dict[str, Any]]:
    """Reduce a validated shadow result to a safe conversational cue.

    Only clear or mixed emotions explicitly attributed to the student may
    influence a reply. Other-person, hypothetical, uncertain, and no-emotion
    results return ``None`` so the normal response path uses the student's
    message unchanged. No evidence text or model-supplied category is passed
    to the generator.
    """
    if not isinstance(raw, Mapping) or raw.get("schema_valid") is not True:
        return None
    if raw.get("classification_source") != "gemma_shadow":
        return None
    if str(raw.get("taxonomy_version") or "") != str(taxonomy_version):
        return None
    status = raw.get("status")
    scope = raw.get("attribution_scope")
    if status not in {STATUS_CLEAR, STATUS_MIXED} or scope not in {ATTR_STUDENT, ATTR_BOTH}:
        return None
    if raw.get("felt_by_student") is not True:
        return None

    primary = raw.get("primary_emotion")
    if not isinstance(primary, str) or primary not in index:
        return None

    secondary_raw = raw.get("secondary_emotions", [])
    if not isinstance(secondary_raw, list):
        return None
    secondary: list[str] = []
    for candidate in secondary_raw:
        if not isinstance(candidate, str) or candidate not in index:
            return None
        if candidate != primary and candidate not in secondary:
            secondary.append(candidate)

    if status == STATUS_MIXED and not secondary:
        return None
    if status == STATUS_CLEAR and secondary:
        # Two student emotions are mixed, never a single clear label with a
        # hidden second label.
        status = STATUS_MIXED

    emotions = [primary, *secondary[:4]]
    categories: list[str] = []
    for emotion in emotions:
        category = index.get(emotion, {}).get("category_key")
        if category and category not in categories:
            categories.append(category)

    return {
        "status": status,
        "attribution_scope": scope,
        "emotions": emotions,
        "category_keys": categories,
        "taxonomy_version": taxonomy_version,
    }


def expected_status(case: Mapping[str, Any]) -> str:
    if case.get("expected_status"):
        return str(case["expected_status"])
    if case.get("expected_attribution"):
        return STATUS_ATTRIBUTED_OTHER
    if case.get("expected_uncertain"):
        return STATUS_UNCERTAIN
    return STATUS_CLEAR


def predicted_uncertain(status: Optional[str]) -> bool:
    return status in {STATUS_UNCERTAIN, STATUS_MIXED}
