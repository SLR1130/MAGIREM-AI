"""Create a local-only, weakly supervised Filipino/Taglish Emotion35 experiment.

The frozen candidate evaluation package is generated first and never added to
the training dataset. Local Gemma output is explicitly synthetic and
unreviewed. Unknown labels stay masked; only target positives and explicit
target-denial contrast labels are observed.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import re
import shutil
import time
import urllib.error
import urllib.request
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config" / "emotion_taxonomy.json"
BASE = ROOT / "storage/app/audit-recovery/emotion35_prototype_multilabel_masks_fixed_20261103"
OUT_ROOT = ROOT / "storage/app/audit-recovery"
RUN_TAG = "20261104"
DATASET_OUT = OUT_ROOT / f"emotion35_fil_tag_research_dataset_{RUN_TAG}"
EVAL_OUT = OUT_ROOT / f"emotion35_fil_tag_frozen_eval_{RUN_TAG}"
CACHE_OUT = OUT_ROOT / f"emotion35_fil_tag_generation_cache_{RUN_TAG}"
MODEL = "gemma3:4b"
OLLAMA_URL = "http://127.0.0.1:11434/api/generate"
SEED = 20261104

# Explicit contrast pairs used to create weak negative evidence. The denial
# must be present in the generated sentence; all other labels remain unknown.
ALTERNATIVE = {
    "calmness": "contentment", "contentment": "calmness", "hope_optimism": "confidence",
    "love_affection": "gratitude", "confidence": "pride", "loneliness": "homesickness",
    "homesickness": "loneliness", "hopelessness": "sadness", "rejection_emotional_hurt": "disappointment",
    "anxiety": "fear", "stress": "overwhelm", "overwhelm": "stress", "frustration": "anger",
    "jealousy_envy": "pride", "shame": "guilt", "guilt": "shame",
    "insecurity_self_doubt": "anxiety", "boredom": "neutral", "compassion_empathy": "gratitude",
    "anticipation": "excitement", "happiness_joy": "relief", "excitement": "anticipation",
    "pride": "confidence", "curiosity": "confusion", "gratitude": "love_affection",
    "relief": "calmness", "sadness": "disappointment", "grief": "sadness",
    "disappointment": "rejection_emotional_hurt", "fear": "anxiety", "anger": "frustration",
    "disgust": "anger", "confusion": "curiosity", "neutral": "confusion", "surprise": "excitement",
}

MIXED_PAIRS = [
    ("happiness_joy", "anxiety"), ("excitement", "fear"), ("pride", "insecurity_self_doubt"),
    ("relief", "grief"), ("stress", "frustration"), ("homesickness", "loneliness"),
    ("hope_optimism", "disappointment"), ("guilt", "shame"), ("anticipation", "anxiety"),
    ("contentment", "sadness"), ("gratitude", "grief"), ("confidence", "fear"),
]

FILIPINO_MARKERS = {
    "ako", "ko", "ka", "siya", "ang", "ng", "sa", "na", "pero", "parang", "dahil", "kasi", "yung",
    "lang", "ito", "may", "wala", "hirap", "hindi", "naman", "talaga", "kami", "amin", "gusto", "miss",
    "bahay", "pamilya", "araw", "isip", "pakiramdam", "ako'y", "nang", "kapag", "pa", "rin", "din", "sana",
    # Common conversational Taglish vocabulary used as language-quality
    # markers only (never as emotion labels).
    "astig", "saya", "masaya", "nakakatuwa", "nakakakilig", "galing", "grabe", "bongga", "totoo", "basta",
    "diba", "di", "ayoko", "natin", "namin", "ngayon", "bukas", "kanina", "sobra", "medyo", "muna", "nga",
    "yawa", "sayang", "bitin", "sulit", "okay", "naka", "pagod", "kaya", "ganito", "ganyan", "nito", "niya",
    "kulit", "lungkot", "malungkot", "nalulungkot", "lungkot", "kaba", "kinakabahan", "nakakakaba", "takot",
    "nakakatakot", "hiya", "nakakahiya", "inis", "nakakainis", "gaan", "magaan", "bigat", "mabigat", "miss",
    "namimiss", "pangungulila", "proud", "nakakaproud", "hirap", "nahihirapan", "saya-saya", "nakakapagod",
    "excited", "nakaka-excite", "nakakainspire", "sana", "siguro", "ewan", "kakayanin", "kayang", "parang",
}
ENGLISH_MARKERS = {
    "i", "me", "my", "we", "our", "feel", "felt", "the", "and", "but", "because", "when", "after", "before",
    "still", "just", "can't", "cannot", "have", "has", "had", "was", "were", "am", "is", "are", "it", "this",
    "that", "for", "to", "with", "from", "in", "on", "of", "so", "really", "very", "going", "will", "can",
    "want", "wanted", "think", "thought", "about", "more", "less", "good", "bad", "happy", "sad", "excited",
    "project", "class", "exam", "group", "time", "today", "tomorrow", "maybe", "now", "not", "don't", "doesn't",
}
BAD_ENCODING = ("\ufffd", "Ã", "â€”", "â€™", "â€œ", "â€", "à¤")


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_taxonomy() -> tuple[dict[str, Any], list[str], dict[str, str]]:
    data = json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))
    emotions = data["emotions"]
    labels = [item["key"] for item in emotions]
    if len(labels) != 35 or len(set(labels)) != 35:
        raise ValueError("approved taxonomy must contain exactly 35 unique keys")
    return data, labels, {item["key"]: str(item.get("definition") or item.get("description") or item["key"]) for item in emotions}


def norm(text: str) -> str:
    return " ".join(text.casefold().split())


def validate_text(text: Any, language: str) -> str:
    if not isinstance(text, str):
        raise ValueError("message is not a string")
    text = " ".join(text.strip().strip('"“”\'').split())
    if any(marker in text for marker in BAD_ENCODING):
        raise ValueError("malformed encoding")
    words = re.findall(r"[\w’'-]+", text.casefold(), flags=re.UNICODE)
    if not 7 <= len(words) <= 65:
        raise ValueError(f"word count outside 7..65 ({len(words)})")
    filipino_count = sum(word in FILIPINO_MARKERS for word in words)
    if language == "FILIPINO" and filipino_count < 2:
        raise ValueError("too little Filipino vocabulary for Filipino label")
    if language == "TAGLISH":
        english_count = sum(word in ENGLISH_MARKERS for word in words)
        # Require at least one marker from each language.  This is a simple
        # quality screen, not a language-identification model; semantic review
        # remains necessary before any candidate can be considered ground truth.
        if filipino_count < 1 or english_count < 1:
            raise ValueError("Taglish row lacks clear code-switching markers")
    return text


def ollama_json(prompt: str, *, num_predict: int, request_tag: str) -> tuple[dict[str, Any], dict[str, Any]]:
    prompt_hash = hashlib.sha256(prompt.encode("utf-8")).hexdigest()
    cache_name = re.sub(r"[^a-zA-Z0-9_.-]+", "_", request_tag)[:180] + ".json"
    cache_path = CACHE_OUT / cache_name
    if cache_path.exists():
        cached = json.loads(cache_path.read_text(encoding="utf-8"))
        if cached.get("prompt_sha256") == prompt_hash and isinstance(cached.get("response"), dict):
            original_meta = cached.get("metadata") or {}
            return cached["response"], {**original_meta, "request_tag": request_tag, "duration_seconds": 0.0,
                                        "cached": True, "cached_from_seconds": original_meta.get("duration_seconds", 0.0)}
    request = {
        "model": MODEL,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": {"temperature": 0.62, "top_p": 0.9, "seed": SEED + sum(request_tag.encode("utf-8")), "num_ctx": 4096, "num_predict": num_predict},
    }
    body = json.dumps(request, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(OLLAMA_URL, data=body, headers={"Content-Type": "application/json"}, method="POST")
    last_error = None
    for attempt in range(1, 4):
        started = time.perf_counter()
        try:
            with urllib.request.urlopen(req, timeout=360) as response:
                payload = json.loads(response.read().decode("utf-8"))
            decoded = json.loads(payload["response"])
            if not isinstance(decoded, dict):
                raise ValueError("Gemma response root is not an object")
            meta = {
                "request_tag": request_tag, "attempt": attempt, "model": MODEL,
                "duration_seconds": round(time.perf_counter() - started, 3),
                "prompt_sha256": prompt_hash,
                "prompt_eval_count": payload.get("prompt_eval_count"), "eval_count": payload.get("eval_count"),
                "cached": False,
            }
            print(f"generated {request_tag}: {meta['duration_seconds']}s, {meta['eval_count']} output tokens", flush=True)
            CACHE_OUT.mkdir(parents=True, exist_ok=True)
            temporary = cache_path.with_suffix(cache_path.suffix + ".tmp")
            temporary.write_text(json.dumps({"prompt_sha256": prompt_hash, "response": decoded, "metadata": meta},
                                            ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
            temporary.replace(cache_path)
            return decoded, meta
        except urllib.error.HTTPError as exc:
            response_detail = exc.read(1200).decode("utf-8", "replace")
            last_error = f"HTTP {exc.code}: {response_detail[:900]}"
        except Exception as exc:  # bounded retry for local generation/JSON errors
            last_error = str(exc)
    raise RuntimeError(f"local Gemma generation failed for {request_tag}: {last_error}")


def definitions_for(labels: list[str], definitions: dict[str, str]) -> list[dict[str, str]]:
    return [{"emotion_key": label, "meaning": definitions[label]} for label in labels]


def _message_list(value: Any) -> list[str]:
    if isinstance(value, str):
        return [value]
    if isinstance(value, list):
        return [item for item in value if isinstance(item, str)]
    return []


def make_record(text: str, *, labels: list[str], observed: list[str], language: str, split: str,
                source_id: str, group: str, kind: str, negative_basis: str = "none_unspecified_labels_unknown",
                metadata: dict[str, Any] | None = None) -> dict[str, Any]:
    record = {
        "text": text, "labels": labels, "observed_labels": observed, "language": language,
        "source_dataset": "gemma3_local_ai_assisted_synthetic", "source_id": source_id,
        "source_group": group, "split": split, "synthetic": True, "ai_assisted": True,
        "weak_supervision": True, "human_review_required": True, "review_status": "unreviewed",
        "training_eligibility": "research_only_synthetic", "taxonomy_version": "1.0.0",
        "generation_model": MODEL, "generation_prompt_family": kind,
        "weak_positive_labels": list(labels),
        "weak_negative_labels": sorted(set(observed) - set(labels)),
        "weak_label_policy": "positive_only_unknown_negatives" if len(observed) == len(labels) else "explicit_contrast_negatives_only",
        "negative_annotation_basis": negative_basis,
        "provenance_note": "AI-assisted local synthetic candidate; provisional noisy target, not human-reviewed ground truth.",
    }
    if metadata:
        record.update(metadata)
    return record


def request_single_examples(labels: list[str], definitions: dict[str, str], *, role: str, count: int,
                            request_tag: str, log: list[dict[str, Any]]) -> dict[str, dict[str, list[str]]]:
    items = definitions_for(labels, definitions)
    prompt = (
        "Generate original, natural student-style messages for a research-only emotion model. Return JSON only as "
        "{\"items\":[{\"emotion_key\":KEY,\"filipino\":[...],\"taglish\":[...]}]}. "
        f"For each supplied key make exactly {count} Filipino messages and {count} Taglish messages. "
        "Use varied academic, family, friendship, dorm, commute, money, hobbies, and everyday contexts; vary length and syntax. "
        "Include both direct everyday expressions and indirect/contextual expression. Avoid repeated templates, translations of the same sentence, advice, names, diagnoses, crisis content, and the exact taxonomy key or English synonym as a keyword shortcut. "
        "For EVERY Taglish message, include an English clause AND a Filipino phrase using at least one of: ako, ko, yung, kasi, pero, parang, hindi, na, kaya. English-only lines are invalid. Filipino messages must be primarily Filipino, with ordinary English loanwords allowed. "
        "Use plain ASCII punctuation only, avoid apostrophes and curly quotes, and write full forms such as 'I am'. "
        "Do not mention these instructions. A label definition guides intended meaning but the message should sound like an actual person. "
        f"Targets: {json.dumps(items, ensure_ascii=False)}"
    )
    data, meta = ollama_json(prompt, num_predict=3000, request_tag=request_tag)
    log.append(meta)
    raw_items = data.get("items")
    if not isinstance(raw_items, list):
        raise ValueError(f"{request_tag}: response lacks items array")
    result: dict[str, dict[str, list[str]]] = {}
    expected = set(labels)
    for item in raw_items:
        if not isinstance(item, dict):
            continue
        key = item.get("emotion_key")
        if key not in expected:
            continue
        current = result.setdefault(key, {"FILIPINO": [], "TAGLISH": []})
        current["FILIPINO"].extend(_message_list(item.get("filipino")))
        current["TAGLISH"].extend(_message_list(item.get("taglish")))
    missing = expected - set(result)
    if missing:
        raise ValueError(f"{request_tag}: Gemma omitted target keys {sorted(missing)}")
    # Gemma sometimes ignores the language instruction in a large batch.
    # Regenerate only deficient Taglish rows in a smaller, explicit local call;
    # never relabel or post-process English text into fake Taglish.
    min_candidates = min(count, 2)
    deficient_fil = {
        key: sum(1 for text in result[key]["FILIPINO"] if _is_valid_language_text(text, "FILIPINO")) < min_candidates
        for key in labels
    }
    if any(deficient_fil.values()):
        replacements_fil = request_filipino_only(
            [key for key, needs_retry in deficient_fil.items() if needs_retry], definitions,
            count=min_candidates + 2, request_tag=f"{request_tag}-filipino-retry", log=log,
        )
        for key, candidates in replacements_fil.items():
            valid_originals = [text for text in result[key]["FILIPINO"] if _is_valid_language_text(text, "FILIPINO")]
            result[key]["FILIPINO"] = valid_originals + candidates
    deficient = {
        key: sum(1 for text in result[key]["TAGLISH"] if _is_valid_language_text(text, "TAGLISH")) < min_candidates
        for key in labels
    }
    if any(deficient.values()):
        replacements = request_taglish_only(
            [key for key, needs_retry in deficient.items() if needs_retry], definitions,
            count=min_candidates + 2, request_tag=f"{request_tag}-taglish-retry", log=log,
        )
        for key, candidates in replacements.items():
            valid_originals = [text for text in result[key]["TAGLISH"] if _is_valid_language_text(text, "TAGLISH")]
            result[key]["TAGLISH"] = valid_originals + candidates
    return result


def _is_valid_language_text(text: Any, language: str) -> bool:
    try:
        validate_text(text, language)
        return True
    except ValueError:
        return False


def request_mixed_example(first: str, second: str, language: str, definitions: dict[str, str],
                          banks: dict[str, list[str]], *, request_tag: str,
                          log: list[dict[str, Any]]) -> str:
    if language not in {"FILIPINO", "TAGLISH"}:
        raise ValueError(f"unsupported mixed-example language: {language}")
    language_instruction = (
        "Write primarily Filipino." if language == "FILIPINO"
        else "Include a complete English clause and a natural Filipino phrase."
    )
    prompt = (
        f"Write exactly 1 natural {language} student message (7-30 words) that clearly expresses BOTH "
        f"{first} ({definitions[first]}) and {second} ({definitions[second]}) in its situation. "
        f"{language_instruction} Do not invent a third emotion. Use a natural academic, family, friendship, or everyday context. "
        "Use plain ASCII punctuation only; avoid apostrophes, curly quotes, names, and identifying details. "
        "Return ONLY JSON: "
        f"{{\"items\":[{{\"first_emotion\":\"{first}\",\"second_emotion\":\"{second}\",\"message\":\"...\"}}]}}"
    )
    for attempt in range(1, 5):
        data, meta = ollama_json(prompt, num_predict=450, request_tag=f"{request_tag}-attempt-{attempt}")
        log.append(meta)
        raw_items = data.get("items")
        if raw_items is None and data.get("first_emotion") == first:
            raw_items = [data]
        if not isinstance(raw_items, list):
            continue
        for item in raw_items:
            if not isinstance(item, dict) or item.get("first_emotion") != first or item.get("second_emotion") != second:
                continue
            candidate = item.get("message")
            if not _is_valid_language_text(candidate, language):
                continue
            try:
                return choose_texts([candidate], language, 1, banks, strict=True)[0]
            except ValueError:
                continue
    raise ValueError(f"{request_tag}: no valid distinct {language} mixed example for {first}+{second}")


def request_filipino_only(labels: list[str], definitions: dict[str, str], *, count: int,
                          request_tag: str, log: list[dict[str, Any]]) -> dict[str, list[str]]:
    if len(labels) > 1:
        combined: dict[str, list[str]] = {}
        for key in labels:
            combined.update(request_filipino_only([key], definitions, count=count,
                                                   request_tag=f"{request_tag}-{key}", log=log))
        return combined
    key = labels[0]
    # This helper only supplements a weak batch; it returns a small number of
    # genuinely new candidates and never fabricates padding.
    required = 1
    contexts = ["a group task", "a quiet moment at home", "a daily commute", "a class activity", "a hobby or routine"]
    result: list[str] = []
    for attempt in range(1, required + 4):
        context = contexts[(attempt - 1) % len(contexts)]
        prompt = (
        "Write exactly 1 natural Filipino student message (7-30 words) expressing the student's own "
        f"feeling of {key}, meaning: {definitions[key]}. Write primarily Filipino. Return ONLY JSON with this shape: "
        f"{{\"items\":[{{\"emotion_key\":\"{key}\",\"filipino\":[\"message\"]}}]}}. "
        "No markdown or explanation. Use plain ASCII punctuation only; avoid apostrophes, curly quotes, and special symbols. "
        f"Place the feeling in this context: {context}. Do not copy prompt wording, use the taxonomy key as a keyword shortcut, add another emotion, or include identifying details."
        )
        data, meta = ollama_json(prompt, num_predict=450, request_tag=f"{request_tag}-attempt-{attempt}")
        log.append(meta)
        raw_items = data.get("items")
        if raw_items is None and data.get("emotion_key") == key:
            raw_items = [data]
        if not isinstance(raw_items, list):
            continue
        for item in raw_items:
            if isinstance(item, dict) and item.get("emotion_key") == key:
                result.extend(text for text in _message_list(item.get("filipino")) if _is_valid_language_text(text, "FILIPINO"))
        unique: list[str] = []
        for text in result:
            if not any(norm(text) == norm(old) or near_duplicate(text, "FILIPINO", {"FILIPINO": [norm(old)]}) for old in unique):
                unique.append(text)
        result = unique
        if len(result) >= required:
            return {key: result}
    raise ValueError(f"{request_tag}: insufficient valid Filipino candidates for {key} ({len(result)}/{required})")


def request_taglish_only(labels: list[str], definitions: dict[str, str], *, count: int,
                         request_tag: str, log: list[dict[str, Any]]) -> dict[str, list[str]]:
    # Single-label prompts are materially more reliable with Gemma's local
    # JSON mode. Batch requests only for the first generation; retry each
    # deficient label independently rather than accepting a malformed batch.
    if len(labels) > 1:
        combined: dict[str, list[str]] = {}
        for key in labels:
            combined.update(request_taglish_only([key], definitions, count=count,
                                                  request_tag=f"{request_tag}-{key}", log=log))
        return combined
    key = labels[0]
    meaning = definitions[key]
    required = 1
    contexts = ["a group project", "a family conversation", "a club activity", "a study routine", "a daily decision"]
    prompt_suffix = (
        f" feeling of {key}, meaning: {meaning}. Each message must have a complete English clause and a natural Filipino phrase. "
        "No markdown or explanation. Use plain ASCII punctuation only; avoid apostrophes and curly quotes. Do not copy phrases from this prompt, do not use only an English sentence plus one pasted Filipino word, "
        "do not add a second emotion, and do not include names or identifying details."
    )
    last_error = "no valid Taglish candidates returned"
    result: dict[str, list[str]] = {key: []}
    contexts = ["a group project", "a family conversation", "a club activity", "a study routine", "a daily decision"]
    for attempt in range(1, required + 4):
        prompt = (
            f"Write exactly 1 natural Taglish student message (7-30 words) about {contexts[(attempt - 1) % len(contexts)]}, "
            + prompt_suffix + " Return ONLY JSON with this shape and exact key: "
            + f"{{\"items\":[{{\"emotion_key\":\"{key}\",\"taglish\":[\"message\"]}}]}}."
        )
        current_tag = f"{request_tag}-attempt-{attempt}"
        data, meta = ollama_json(prompt, num_predict=450, request_tag=current_tag)
        log.append(meta)
        raw_items = data.get("items")
        if raw_items is None and data.get("emotion_key") in labels:
            raw_items = [data]
        if not isinstance(raw_items, list):
            last_error = f"response lacks items array (keys={sorted(data.keys())})"
            continue
        for item in raw_items:
            if not isinstance(item, dict) or item.get("emotion_key") not in labels:
                continue
            key = item["emotion_key"]
            result.setdefault(key, []).extend(text for text in _message_list(item.get("taglish")) if _is_valid_language_text(text, "TAGLISH"))
        unique: list[str] = []
        for text in result.get(key, []):
            if not any(norm(text) == norm(old) or near_duplicate(text, "TAGLISH", {"TAGLISH": [norm(old)]}) for old in unique):
                unique.append(text)
        result[key] = unique
        if len(result[key]) >= required:
            return result
        last_error = f"insufficient valid candidates for {key}: {len(result[key])}/{required}"
    raise ValueError(f"{request_tag}: {last_error}")


def request_contrasts(targets: list[str], labels: list[str], definitions: dict[str, str], *, count_each: int,
                      role: str, request_tag: str, log: list[dict[str, Any]]) -> dict[str, dict[str, list[str]]]:
    if len(targets) > 1:
        combined: dict[str, dict[str, list[str]]] = {}
        for key in targets:
            combined.update(request_contrasts([key], labels, definitions, count_each=count_each, role=role,
                request_tag=f"{request_tag}-{key}", log=log))
        return combined
    key = targets[0]
    alternative = ALTERNATIVE[key]
    is_validation = "validation" in role.lower()
    filipino_requested = count_each
    taglish_requested = 1 if is_validation and count_each > 1 else count_each
    filipino_minimum = min(filipino_requested, 3) if is_validation else filipino_requested
    taglish_minimum = 1
    return {key: {
        "FILIPINO": request_contrast_language(key, alternative, "FILIPINO", filipino_requested, filipino_minimum,
            definitions, request_tag=f"{request_tag}-filipino", log=log),
        "TAGLISH": request_contrast_language(key, alternative, "TAGLISH", taglish_requested, taglish_minimum,
            definitions, request_tag=f"{request_tag}-taglish", log=log),
    }}


def request_contrast_language(negative: str, positive: str, language: str, requested: int, minimum: int,
                              definitions: dict[str, str], *, request_tag: str,
                              log: list[dict[str, Any]]) -> list[str]:
    contexts = ["a class task", "a family plan", "a group conversation", "a daily routine", "a club activity", "a quiet evening"]
    results: list[str] = []
    max_attempts = max(requested + 3, minimum + 3)
    for attempt in range(1, max_attempts + 1):
        language_instruction = (
            "Write primarily Filipino." if language == "FILIPINO"
            else "Write genuinely code-switched Taglish with an English clause and a natural Filipino phrase."
        )
        prompt = (
            f"Write exactly one original 7-30 word student message in {language}. The speaker clearly does NOT experience "
            f"{negative} ({definitions[negative]}) but DOES experience {positive} ({definitions[positive]}). "
            f"Set it in this context: {contexts[(attempt - 1) % len(contexts)]}. {language_instruction} "
            "Return only JSON with these exact values and a text field: "
            f"{{\"negative_emotion_key\":\"{negative}\",\"positive_emotion_key\":\"{positive}\",\"language\":\"{language}\",\"text\":\"...\"}}. "
            "No markdown, names, identifying details, third emotion, or explanation. Use plain ASCII punctuation."
        )
        data, meta = ollama_json(prompt, num_predict=350, request_tag=f"{request_tag}-{attempt:02d}")
        log.append(meta)
        candidate = data.get("text")
        if (data.get("negative_emotion_key") != negative or data.get("positive_emotion_key") != positive
                or data.get("language") != language or not _is_valid_language_text(candidate, language)):
            items = data.get("items")
            if isinstance(items, list):
                item = next((entry for entry in items if isinstance(entry, dict)
                    and entry.get("negative_emotion_key") == negative and entry.get("positive_emotion_key") == positive
                    and entry.get("language") == language), None)
                candidate = item.get("text") if item else None
            if not _is_valid_language_text(candidate, language):
                continue
        if any(norm(candidate) == norm(old) or near_duplicate(candidate, language, {language: [norm(old)]}) for old in results):
            continue
        results.append(candidate)
        if len(results) >= requested:
            break
    if len(results) < minimum:
        raise ValueError(f"{request_tag}: only {len(results)}/{minimum} distinct {language} contrast examples survived")
    return results


def request_taglish_contrasts(targets: list[str], definitions: dict[str, str], *, count_each: int,
                              request_tag: str, log: list[dict[str, Any]]) -> dict[str, list[str]]:
    if len(targets) > 1:
        combined: dict[str, list[str]] = {}
        for key in targets:
            combined.update(request_taglish_contrasts([key], definitions, count_each=count_each,
                request_tag=f"{request_tag}-{key}", log=log))
        return combined
    key = targets[0]
    positive = ALTERNATIVE[key]
    required = min(count_each, 1)
    contexts = ["a study group", "a family plan", "a club activity", "a commute", "a quiet evening"]
    result: list[str] = []
    for attempt in range(1, required + 4):
        prompt = (
            "Write exactly 1 original Taglish student message (7-30 words) for a weakly supervised contrast example. "
            f"The speaker clearly does NOT feel {key} ({definitions[key]}) but DOES feel {positive} ({definitions[positive]}); "
            f"place it in this context: {contexts[(attempt - 1) % len(contexts)]}. Include an English clause and a Filipino phrase. "
            "Return ONLY JSON with shape "
            f"{{\"items\":[{{\"negative_emotion_key\":\"{key}\",\"positive_emotion_key\":\"{positive}\",\"taglish\":[\"message\"]}}]}}. "
            "Use plain ASCII punctuation only; avoid apostrophes and curly quotes. No markdown, explanation, names, or third emotion."
        )
        data, meta = ollama_json(prompt, num_predict=450, request_tag=f"{request_tag}-attempt-{attempt}")
        log.append(meta)
        raw_items = data.get("items")
        if raw_items is None and data.get("negative_emotion_key") == key:
            raw_items = [data]
        if not isinstance(raw_items, list):
            continue
        for item in raw_items:
            if (isinstance(item, dict) and item.get("negative_emotion_key") == key
                    and item.get("positive_emotion_key") == positive):
                result.extend(text for text in _message_list(item.get("taglish")) if _is_valid_language_text(text, "TAGLISH"))
        unique: list[str] = []
        for text in result:
            if not any(norm(text) == norm(old) or near_duplicate(text, "TAGLISH", {"TAGLISH": [norm(old)]}) for old in unique):
                unique.append(text)
        result = unique
        if len(result) >= required:
            return {key: result}
    raise ValueError(f"{request_tag}: insufficient contrastive Taglish for {key} ({len(result)}/{required})")


def near_duplicate(text: str, language: str, banks: dict[str, list[str]], threshold: float = 0.91) -> str | None:
    key = norm(text)
    for old in banks.get(language, []):
        if abs(len(key) - len(old)) > max(18, int(max(len(key), len(old)) * 0.35)):
            continue
        if SequenceMatcher(None, key, old, autojunk=False).ratio() >= threshold:
            return old
    return None


def row_banks(rows_by_split: dict[str, list[dict[str, Any]]]) -> dict[str, list[str]]:
    banks: dict[str, list[str]] = defaultdict(list)
    for rows in rows_by_split.values():
        for row in rows:
            language = str(row.get("language", "UNKNOWN")).upper()
            if language in {"FILIPINO", "TAGLISH"}:
                banks[language].append(norm(row.get("text", "")))
    return banks


def choose_texts(values: list[Any], language: str, needed: int, banks: dict[str, list[str]], *, strict: bool = False,
                 minimum: int | None = None) -> list[str]:
    chosen: list[str] = []
    for raw in values:
        try:
            text = validate_text(raw, language)
        except ValueError:
            continue
        if near_duplicate(text, language, banks) or near_duplicate(text, language, {language: [norm(v) for v in chosen]}):
            continue
        chosen.append(text)
        if len(chosen) == needed:
            break
    required = minimum if minimum is not None else (needed if strict else max(1, min(3, needed)))
    if len(chosen) < required:
        raise ValueError(f"only {len(chosen)}/{needed} distinct usable {language} rows survived validation (minimum {required})")
    banks.setdefault(language, []).extend(norm(text) for text in chosen)
    return chosen


def common_eval_row(case_id: str, text: str, language: str, status: str, positives: list[str], labels: list[str],
                    expression_type: str) -> dict[str, Any]:
    return {
        "case_id": case_id, "text": text, "language": language, "scenario_group": case_id,
        "source_dataset": "AI_authored_synthetic_v5_frozen_evaluation", "generation_provenance": f"Local {MODEL} AI-authored synthetic evaluation example; no student record used",
        "evaluation_split": "frozen_v5_eval_only_20261104", "expression_type": expression_type,
        "provisional_expected_status": status, "provisional_expected_positive_labels": positives,
        "provisional_expected_negative_labels": sorted(set(labels) - set(positives)) if status not in {"uncertain"} else [],
        "provisional_evaluated_labels": labels if status not in {"uncertain"} else [],
        "targets_are_independently_validated": False, "human_review_status": "pending", "human_reviewer": "",
        "review_notes": "", "training_eligible": False, "taxonomy_version": "1.0.0",
    }


def write_eval_package(labels: list[str], definitions: dict[str, str], banks: dict[str, list[str]], generation_log: list[dict[str, Any]]) -> dict[str, Any]:
    if EVAL_OUT.exists():
        raise FileExistsError(f"refusing to overwrite frozen evaluation folder {EVAL_OUT}")
    rows: list[dict[str, Any]] = []
    batch_size = 5
    for start in range(0, len(labels), batch_size):
        batch = labels[start:start + batch_size]
        generated = request_single_examples(batch, definitions, role="frozen evaluation", count=1,
                                            request_tag=f"eval-single-{start//batch_size+1}", log=generation_log)
        for label in batch:
            for language, field in (("FILIPINO", "FILIPINO"), ("TAGLISH", "TAGLISH")):
                selected = choose_texts(generated[label][field], language, 1, banks, strict=True)[0]
                rows.append(common_eval_row(f"frozen_{label}_{'fil' if language == 'FILIPINO' else 'tag'}", selected,
                                            language, "clear_single", [label], labels, "direct_or_contextual"))
    # Fresh challenge cases use distinct situations and are never placed in a
    # training or validation split.
    challenges = [
        ("frozen_mix_01", "FILIPINO", "mixed", ["happiness_joy", "anxiety"], "Masaya ako na pumasa ako, pero hindi ako mapakali habang iniisip ang susunod na semester.", "mixed_emotion"),
        ("frozen_mix_02", "TAGLISH", "mixed", ["relief", "grief"], "I know hindi na ganoon kasakit para kay Lola, pero ang bigat pa rin isipin na wala na siya.", "mixed_emotion"),
        ("frozen_mix_03", "FILIPINO", "mixed", ["pride", "insecurity_self_doubt"], "Ipinagmamalaki ko yung ginawa ko sa org, pero iniisip ko kung may ambag ba talaga ako.", "mixed_emotion"),
        ("frozen_mix_04", "TAGLISH", "mixed", ["homesickness", "contentment"], "Okay naman yung dormmates ko, but I still long for Sunday lunch with my family.", "mixed_emotion"),
        ("frozen_mix_05", "FILIPINO", "mixed", ["stress", "frustration"], "Sunod-sunod ang kailangan kong tapusin at nakakainis na paulit-ulit nag-e-error ang portal.", "mixed_emotion"),
        ("frozen_mix_06", "TAGLISH", "mixed", ["anticipation", "fear"], "I keep checking the audition date; excited ako, pero baka hindi ko kayanin pag nandoon na.", "mixed_emotion"),
        ("frozen_mix_07", "FILIPINO", "mixed", ["gratitude", "sadness"], "Nagpapasalamat ako sa mga bumisita, pero nalulungkot pa rin ako kapag tahimik na ulit ang bahay.", "mixed_emotion"),
        ("frozen_mix_08", "TAGLISH", "mixed", ["hope_optimism", "disappointment"], "There may be a chance next term, pero disappointed pa rin ako sa result na nakuha ko ngayon.", "mixed_emotion"),
        ("frozen_mix_09", "FILIPINO", "mixed", ["guilt", "shame"], "Pinagsisisihan ko yung sinabi ko sa kaibigan ko, at ayokong humarap sa kanya bukas.", "mixed_emotion"),
        ("frozen_mix_10", "TAGLISH", "mixed", ["calmness", "anticipation"], "I feel settled after organizing my things, and curious din ako sa bagong routine bukas.", "mixed_emotion"),
        ("frozen_reported_01", "FILIPINO", "no_student_emotion", [], "Sabi ng kapatid ko, kinakabahan siya sa biyahe; ako naman ang nag-book ng tiket.", "reported_other"),
        ("frozen_reported_02", "TAGLISH", "no_student_emotion", [], "My classmate said na lonely daw siya lately; I just helped her find the meeting time.", "reported_other"),
        ("frozen_hypothetical_01", "FILIPINO", "no_student_emotion", [], "Kung matalo ako sa paligsahan, baka malungkot ako, pero wala pang resulta.", "hypothetical"),
        ("frozen_neutral_01", "FILIPINO", "neutral", ["neutral"], "Naisumite ko ang form bago mag-alas singko at lumabas ang confirmation number.", "neutral_fact"),
        ("frozen_neutral_02", "TAGLISH", "neutral", ["neutral"], "The room assignment is 204 na, and our class starts after lunch.", "neutral_fact"),
        ("frozen_uncertain_01", "FILIPINO", "uncertain", [], "Hindi ko pa maipaliwanag kung ano ang nararamdaman ko; halo-halo lang sa ngayon.", "uncertainty"),
        ("frozen_uncertain_02", "TAGLISH", "uncertain", [], "I can't tell kung excited ba ako sa result or kinakabahan lang.", "uncertainty"),
        ("frozen_negation_01", "FILIPINO", "clear_single", ["relief"], "Hindi ako galit sa kaibigan ko; gumaan lang ang pakiramdam ko nang magkaayos kami.", "negation"),
        ("frozen_negation_02", "TAGLISH", "clear_single", ["excitement"], "Hindi ako kinakabahan sa open mic; I actually can't wait to perform.", "negation"),
        ("frozen_indirect_01", "FILIPINO", "clear_single", ["loneliness"], "May mga kakilala naman ako sa bagong lugar, pero wala pa rin akong matawagan kapag mahaba ang araw.", "indirect_context"),
    ]
    for case_id, language, status, positives, text, kind in challenges:
        text = validate_text(text, language)
        if status == "uncertain":
            # Deliberately withheld labels: this is an abstention probe.
            row = common_eval_row(case_id, text, language, status, [], labels, kind)
        else:
            row = common_eval_row(case_id, text, language, status, positives, labels, kind)
        row["generation_provenance"] = "Synthetic challenge authored for this diagnostic by an AI assistant; not an actual student message or Ollama-generated training record."
        if near_duplicate(text, language, banks):
            raise ValueError(f"frozen challenge {case_id} duplicates a prepared example")
        rows.append(row)
        banks.setdefault(language, []).append(norm(text))
    # Challenge text must not overlap any already prepared training/validation
    # language family or the earlier inspected 90-case package.
    old_eval_path = OUT_ROOT / "emotion35_fil_tag_evaluation_20261104" / "emotion35_fil_tag_eval_cases.jsonl"
    old_eval_rows = [json.loads(line) for line in old_eval_path.read_text(encoding="utf-8").splitlines() if line.strip()] if old_eval_path.exists() else []
    for row in rows:
        old_same = [norm(item["text"]) for item in old_eval_rows if item.get("language") == row["language"]]
        if near_duplicate(row["text"], row["language"], {row["language"]: old_same}, threshold=0.91):
            raise ValueError(f"new frozen case too similar to previously inspected evaluation: {row['case_id']}")
    EVAL_OUT.mkdir(parents=True)
    jsonl = EVAL_OUT / "emotion35_fil_tag_eval_cases.jsonl"
    jsonl.write_text("".join(json.dumps(row, ensure_ascii=False) + "\n" for row in rows), encoding="utf-8")
    csv_path = EVAL_OUT / "emotion35_fil_tag_eval_cases.csv"
    with csv_path.open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]))
        writer.writeheader()
        for row in rows:
            writer.writerow({key: json.dumps(value, ensure_ascii=False) if isinstance(value, (list, dict)) else value for key, value in row.items()})
    manifest = {
        "schema": "emotion35-fil-tag-v5-frozen-eval-v1", "records": len(rows),
        "by_language": dict(Counter(row["language"] for row in rows)),
        "by_status": dict(Counter(row["provisional_expected_status"] for row in rows)),
        "by_positive_label": dict(sorted(Counter(label for row in rows for label in row["provisional_expected_positive_labels"]).items())),
        "all_ai_authored_synthetic": True, "human_reviewed": 0, "independently_validated": 0,
        "training_eligible": False, "used_for_threshold_selection": False,
        "created_before_candidate_training": True, "target_definitions": "AI-authored provisional; not ground truth",
        "taxonomy_version": "1.0.0", "evaluation_jsonl_sha256": sha256(jsonl), "evaluation_csv_sha256": sha256(csv_path),
        "generation_log": "generation_metadata.jsonl", "warning": "Diagnostic synthetic-domain evidence only; no student conversations used.",
    }
    (EVAL_OUT / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (EVAL_OUT / "generation_metadata.jsonl").write_text("".join(json.dumps(item, ensure_ascii=False) + "\n" for item in generation_log), encoding="utf-8")
    (EVAL_OUT / "README.md").write_text(
        "# Frozen Filipino/Taglish v5 diagnostic\n\n"
        f"This package contains {len(rows)} AI-authored synthetic examples, created before the candidate training run. It is distinct from the previously inspected 90-case set. "
        "The 70 single-label prompts cover each of the 35 labels in Filipino and Taglish; the remaining 20 are mixed, negation, attribution, neutral, hypothetical, or uncertainty probes. "
        "Targets are provisional, not human-reviewed or independently validated. The two uncertainty examples intentionally have no expected label mask. "
        "Do not use this package for training, calibration, or hyperparameter selection. Any metric is synthetic diagnostic evidence only.\n",
        encoding="utf-8",
    )
    return manifest


def create_dataset(labels: list[str], definitions: dict[str, str], eval_rows: list[dict[str, Any]], generation_log: list[dict[str, Any]]) -> dict[str, Any]:
    if DATASET_OUT.exists():
        raise FileExistsError(f"refusing to overwrite dataset folder {DATASET_OUT}")
    shutil.copytree(BASE, DATASET_OUT)
    rows_by_split: dict[str, list[dict[str, Any]]] = {}
    for split in ("train", "validation", "test"):
        rows_by_split[split] = [json.loads(line) for line in (DATASET_OUT / f"{split}.jsonl").read_text(encoding="utf-8").splitlines() if line.strip()]
    banks = row_banks(rows_by_split)
    eval_banks: dict[str, list[str]] = defaultdict(list)
    for row in eval_rows:
        eval_banks[row["language"]].append(norm(row["text"]))
    for language, vals in eval_banks.items():
        banks.setdefault(language, []).extend(vals)

    additions: dict[str, list[dict[str, Any]]] = {"train": [], "validation": []}
    # Four diverse positives in each language for every label (280 requested).
    for start in range(0, len(labels), 5):
        batch = labels[start:start + 5]
        generated = request_single_examples(batch, definitions, role="training", count=2,
                                            request_tag=f"train-positive-{start//5+1}", log=generation_log)
        for label in batch:
            for language in ("FILIPINO", "TAGLISH"):
                # Do not pad weak synthetic coverage with near-duplicates. A
                # single genuinely distinct accepted row is preferable to
                # manufacturing duplicates; exact counts are recorded below.
                chosen = choose_texts(generated[label][language], language, 2, banks, strict=False, minimum=1)
                for index, text in enumerate(chosen, 1):
                    sid = f"v5train-pos-{label}-{language.lower()}-{index:02d}"
                    additions["train"].append(make_record(text, labels=[label], observed=[label], language=language,
                        split="train", source_id=sid, group=sid, kind="positive_only", metadata={
                            "weak_supervision_quality": "AI_assisted_synthetic_positive_only",
                            "scenario_variation": "direct_or_indirect_student_context",
                        }))
    # Explicit denials teach targeted hard negatives; all other labels stay unknown.
    weak_only = [label for label in labels if label not in {
        "happiness_joy", "excitement", "pride", "curiosity", "gratitude", "relief", "sadness", "grief",
        "disappointment", "fear", "anger", "disgust", "confusion", "neutral", "surprise",
    }]
    for start in range(0, len(weak_only), 5):
        batch = weak_only[start:start + 5]
        generated = request_contrasts(batch, labels, definitions, count_each=1, role="training contrast",
                                      request_tag=f"train-explicit-negative-{start//5+1}", log=generation_log)
        for target in batch:
            alternative = ALTERNATIVE[target]
            for language in ("FILIPINO", "TAGLISH"):
                chosen = choose_texts(generated[target][language], language, 1, banks, strict=True)
                sid = f"v5train-neg-{target}-{language.lower()}"
                additions["train"].append(make_record(chosen[0], labels=[alternative], observed=[alternative, target],
                    language=language, split="train", source_id=sid, group=sid, kind="explicit_contrast_negative",
                    negative_basis=f"AI-generated contrast explicitly denies {target} and expresses {alternative}; weak provisional annotation",
                    metadata={"weak_supervision_quality": "AI_assisted_synthetic_explicit_contrast", "contrast_positive_label": alternative,
                              "contrast_negative_label": target}))
    # Add contrastive multilingual mixed examples from distinct generation calls.
    for start in range(0, len(MIXED_PAIRS), 4):
        batch_pairs = MIXED_PAIRS[start:start + 4]
        batch_targets = [a for a, _ in batch_pairs]
        # Generate by choosing one member as the primary key; we then require
        # the partner to be present in the generated structure and preserve both.
        items = [{"first_emotion": a, "first_meaning": definitions[a], "second_emotion": b, "second_meaning": definitions[b]} for a, b in batch_pairs]
        prompt = (
            "Return JSON only: {\"items\":[{\"first_emotion\":KEY,\"second_emotion\":KEY,\"filipino\":[...],\"taglish\":[...]}]}. "
            "For each pair, write exactly one natural Filipino and one natural Taglish short student message that clearly expresses BOTH feelings in context. "
            "Use varied personal, family, academic, or everyday settings, not templates or literal translations. Every Taglish message must include an English clause plus a Filipino phrase with ako, ko, yung, kasi, pero, parang, hindi, na, or kaya. Do not invent a third emotion. "
            f"Pairs: {json.dumps(items, ensure_ascii=False)}"
        )
        data, meta = ollama_json(prompt, num_predict=1100, request_tag=f"train-mixed-{start//4+1}")
        generation_log.append(meta)
        returned = data.get("items") or []
        for first, second in batch_pairs:
            item = next((obj for obj in returned if isinstance(obj, dict) and obj.get("first_emotion") == first and obj.get("second_emotion") == second), None)
            for language, field in (("FILIPINO", "filipino"), ("TAGLISH", "taglish")):
                candidates = _message_list(item.get(field)) if item else []
                try:
                    text = choose_texts(candidates, language, 1, banks, strict=True)[0]
                except ValueError:
                    text = request_mixed_example(first, second, language, definitions, banks,
                        request_tag=f"train-mixed-{start//4+1}-{first}-{second}-{language.lower()}-retry",
                        log=generation_log)
                sid = f"v5train-mix-{first}-{second}-{language.lower()}"
                additions["train"].append(make_record(text, labels=[first, second], observed=[first, second], language=language,
                    split="train", source_id=sid, group=sid, kind="mixed_positive_only", metadata={
                        "mixed_emotion": True, "weak_supervision_quality": "AI_assisted_synthetic_mixed_positive_only"}))
    # Explicit weak negatives for labels which lack source-native negative
    # calibration evidence. A minimum of three Filipino plus one Taglish is
    # retained where available; the calibration gate still requires five
    # observed negatives and marks any shortfall uncalibrated.
    for start in range(0, len(weak_only), 4):
        batch = weak_only[start:start + 4]
        generated = request_contrasts(batch, labels, definitions, count_each=4, role="validation contrast",
                                      request_tag=f"validation-explicit-negative-{start//4+1}", log=generation_log)
        for target in batch:
            alternative = ALTERNATIVE[target]
            for language, amount, minimum in (("FILIPINO", 4, 3), ("TAGLISH", 1, 1)):
                chosen = choose_texts(generated[target][language], language, amount, banks, strict=False, minimum=minimum)
                for index, text in enumerate(chosen, 1):
                    sid = f"v5val-neg-{target}-{language.lower()}-{index:02d}"
                    additions["validation"].append(make_record(text, labels=[alternative], observed=[alternative, target],
                        language=language, split="validation", source_id=sid, group=sid, kind="explicit_contrast_validation_negative",
                        negative_basis=f"AI-generated contrast explicitly denies {target} and expresses {alternative}; weak provisional calibration evidence",
                        metadata={"weak_supervision_quality": "AI_assisted_synthetic_explicit_contrast", "contrast_positive_label": alternative,
                                  "contrast_negative_label": target, "calibration_evidence_only": True}))

    original_test_sha = sha256(DATASET_OUT / "test.jsonl")
    for split, new_rows in additions.items():
        target = DATASET_OUT / f"{split}.jsonl"
        existing_ids = {str(row.get("source_id")) for row in rows_by_split[split]}
        if any(row["source_id"] in existing_ids for row in new_rows):
            raise ValueError(f"new {split} row ID collides with source data")
        rows_by_split[split].extend(new_rows)
        target.write_text("".join(json.dumps(row, ensure_ascii=False) + "\n" for row in rows_by_split[split]), encoding="utf-8")
    if sha256(DATASET_OUT / "test.jsonl") != original_test_sha:
        raise AssertionError("source test split changed during data preparation")
    # No exact or close train/validation/test leakage, including generated
    # language-specific records and the separately frozen final diagnostic.
    text_by_split: dict[str, set[str]] = {}
    group_splits: dict[str, set[str]] = defaultdict(set)
    for split, rows in rows_by_split.items():
        text_by_split[split] = {norm(row["text"]) for row in rows}
        for row in rows:
            if row.get("source_group"):
                group_splits[str(row["source_group"])].add(split)
            if set(row.get("labels") or []) - set(row.get("observed_labels") or []):
                raise ValueError(f"positive outside observed mask in {split}:{row.get('source_id')}")
            if row.get("synthetic") and row.get("weak_supervision"):
                positives = set(row.get("weak_positive_labels") or [])
                negatives = set(row.get("weak_negative_labels") or [])
                if positives != set(row.get("labels") or []) or positives & negatives:
                    raise ValueError(f"weak provenance/mask mismatch: {row.get('source_id')}")
                if not row.get("human_review_required") or row.get("review_status") != "unreviewed":
                    raise ValueError(f"synthetic row falsely marked reviewed: {row.get('source_id')}")
                if negatives and not str(row.get("negative_annotation_basis", "")).strip():
                    raise ValueError(f"weak negative lacks justification: {row.get('source_id')}")
    if any(len(splits) > 1 for splits in group_splits.values()):
        raise ValueError("source_group leakage detected across dataset splits")
    overlap = text_by_split["train"] & text_by_split["validation"] | text_by_split["train"] & text_by_split["test"] | text_by_split["validation"] & text_by_split["test"]
    if overlap:
        raise ValueError(f"exact text leakage detected: {len(overlap)} records")
    # Add calibration support provenance while keeping all new weak targets
    # clearly separate from source-native annotations.
    provenance = json.loads((DATASET_OUT / "provenance.json").read_text(encoding="utf-8"))
    provenance.update({
        "version": "emotion35-fil-tag-research-20261104-v1",
        "source": "GoEmotions approved source annotations plus local Gemma synthetic Filipino/Taglish research candidates",
        "license": "GoEmotions source license retained (Apache-2.0); local synthetic rows are original, research-only, not human-reviewed",
        "usage": "isolated research training/evaluation only; never production Student Chat or Admin analytics",
        "curator": "MinSuansCore research preparation; automated local generation; human_review_required for synthetic rows",
        "human_reviewed_rows": 0, "all_synthetic_labels_are_weak": True,
        "unknown_labels_remain_unobserved": True, "explicit_weak_negatives_require_denial": True,
        "frozen_external_synthetic_evaluation": str(EVAL_OUT), "frozen_evaluation_hash": sha256(EVAL_OUT / "emotion35_fil_tag_eval_cases.jsonl"),
        "test_split_unchanged_from_mask_fixed_v2": True,
        "new_train_candidates": len(additions["train"]), "new_validation_candidates": len(additions["validation"]),
    })
    (DATASET_OUT / "provenance.json").write_text(json.dumps(provenance, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    summary = {
        "schema": "emotion35-fil-tag-research-dataset-v1", "base_dataset": str(BASE), "output_dataset": str(DATASET_OUT),
        "taxonomy_version": "1.0.0", "source_test_sha256_preserved": original_test_sha,
        "split_records": {split: len(rows) for split, rows in rows_by_split.items()},
        "new_rows_by_split": {split: len(rows) for split, rows in additions.items()},
        "new_train_languages": dict(Counter(row["language"] for row in additions["train"])),
        "new_validation_languages": dict(Counter(row["language"] for row in additions["validation"])),
        "new_rows_by_target": {
            label: {
                "train_positive": sum(label in row["labels"] and label in row["observed_labels"] for row in additions["train"]),
                "train_explicit_negative": sum(label in row["weak_negative_labels"] for row in additions["train"]),
                "validation_explicit_negative": sum(label in row["weak_negative_labels"] for row in additions["validation"]),
            } for label in labels
        },
        "all_new_records_synthetic_unreviewed": True, "human_reviewed_new_records": 0,
        "test_examples_touched_for_tuning": False, "fresh_eval_training_eligible": False,
        "generation_metadata_records": len(generation_log),
        "generation_total_seconds": round(sum(float(item.get("cached_from_seconds", item["duration_seconds"])) for item in generation_log), 3),
    }
    (DATASET_OUT / "fil_tag_generation_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (DATASET_OUT / "generation_metadata.jsonl").write_text("".join(json.dumps(item, ensure_ascii=False) + "\n" for item in generation_log), encoding="utf-8")
    for split in ("train", "validation", "test"):
        summary[f"{split}_sha256"] = sha256(DATASET_OUT / f"{split}.jsonl")
    (DATASET_OUT / "fil_tag_generation_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return summary


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--stage", choices=("all", "freeze-eval", "dataset"), default="all",
                        help="freeze the separate diagnostic first, then create weakly supervised train/validation splits")
    args = parser.parse_args()
    taxonomy, labels, definitions = load_taxonomy()
    if DATASET_OUT.exists():
        raise SystemExit(f"Refusing to overwrite existing dataset artifact: {DATASET_OUT}")
    if not BASE.is_dir():
        raise SystemExit(f"mask-fixed baseline dataset is missing: {BASE}")
    log: list[dict[str, Any]] = []
    base_rows = {split: [json.loads(line) for line in (BASE / f"{split}.jsonl").read_text(encoding="utf-8").splitlines() if line.strip()] for split in ("train", "validation", "test")}
    eval_manifest = None
    if args.stage in {"all", "freeze-eval"} and not EVAL_OUT.exists():
        # Reserve the fresh synthetic diagnostic before generating any
        # training rows and freeze hashes before candidate training.
        eval_banks = row_banks(base_rows)
        eval_manifest = write_eval_package(labels, definitions, eval_banks, log)
    elif args.stage == "freeze-eval" and EVAL_OUT.exists():
        raise SystemExit(f"Refusing to overwrite frozen evaluation: {EVAL_OUT}")
    elif not EVAL_OUT.exists():
        raise SystemExit(f"Freeze the new evaluation set first: {EVAL_OUT}")
    if args.stage == "freeze-eval":
        print(json.dumps({"frozen_eval": str(EVAL_OUT), "manifest": json.loads((EVAL_OUT / "manifest.json").read_text(encoding="utf-8"))}, ensure_ascii=False, indent=2))
        return
    if eval_manifest is None:
        eval_manifest = json.loads((EVAL_OUT / "manifest.json").read_text(encoding="utf-8"))
        expected_hash = eval_manifest.get("evaluation_jsonl_sha256")
        if expected_hash != sha256(EVAL_OUT / "emotion35_fil_tag_eval_cases.jsonl"):
            raise ValueError("frozen evaluation hash does not match its manifest")
    eval_rows = [json.loads(line) for line in (EVAL_OUT / "emotion35_fil_tag_eval_cases.jsonl").read_text(encoding="utf-8").splitlines() if line.strip()]
    try:
        summary = create_dataset(labels, definitions, eval_rows, log)
    except Exception:
        # main() refuses pre-existing destinations before entering the builder,
        # so a leftover here can only be the incomplete artifact from this run.
        if DATASET_OUT.exists():
            shutil.rmtree(DATASET_OUT)
        raise
    summary["frozen_eval_manifest_sha256"] = sha256(EVAL_OUT / "manifest.json")
    summary["frozen_eval_record_count"] = eval_manifest["records"]
    (DATASET_OUT / "fil_tag_generation_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({
        "frozen_eval": {"path": str(EVAL_OUT), "records": eval_manifest["records"], "sha256": eval_manifest["evaluation_jsonl_sha256"]},
        "dataset": summary,
    }, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
