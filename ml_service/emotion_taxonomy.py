"""Shared reporting taxonomy loader for the Flask service.

The authoritative vocabulary lives in config/emotion_taxonomy.json, which is
also consumed by Laravel. This module only enriches responses; it never
changes the trained model label or the safety decision.
"""

import json
from functools import lru_cache
from pathlib import Path


@lru_cache(maxsize=1)
def load_taxonomy():
    path = Path(__file__).resolve().parents[1] / "config" / "emotion_taxonomy.json"
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def taxonomy_version():
    return str(load_taxonomy().get("version", "1.0.0"))


def _normalize(value):
    if value is None:
        return None
    value = " ".join(str(value).strip().lower().replace("_", " ").split())
    return value or None


def classify_label(label):
    """Return a validated detailed/category record, or None for unknown labels."""
    normalized = _normalize(label)
    if not normalized:
        return None

    taxonomy = load_taxonomy()
    categories = {item.get("key"): item for item in taxonomy.get("categories", [])}
    for emotion in taxonomy.get("emotions", []):
        aliases = [emotion.get("key", ""), *emotion.get("aliases", [])]
        if normalized in {_normalize(alias) for alias in aliases}:
            category_key = emotion.get("category_key")
            return {
                "key": emotion.get("key"),
                "label": emotion.get("label"),
                "category_key": category_key,
                "category_label": categories.get(category_key, {}).get("label", category_key),
                "version": taxonomy_version(),
                "status": "classified",
            }

    return None


def response_metadata(label):
    mapped = classify_label(label)
    if mapped:
        return mapped
    return {
        "key": None,
        "label": None,
        "category_key": None,
        "category_label": None,
        "version": taxonomy_version(),
        "status": "unclassified",
    }
