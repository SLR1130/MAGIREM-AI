#!/usr/bin/env python3
"""Privacy-conscious preparation and scoring for the Phase 3 six-class holdout.

The candidate key is provisional. This tool will not score model predictions
until two blind annotations, adjudication, a frozen manifest, and a clear
de-identified training-overlap audit are present.
"""
from __future__ import annotations

import argparse
import csv
from datetime import datetime, timezone
import hashlib
import json
import math
import random
import re
import secrets
import statistics
import sys
import time
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent
BANK = ROOT / "candidate_bank.jsonl"
MANIFEST = ROOT / "manifest.json"
CASE_MAP = ROOT / "blind_case_id_map.json"
LANGUAGES = ("ENGLISH", "FILIPINO", "TAGALOG", "TAGLISH")
LABELS = ("sadness", "joy", "anxiety", "anger", "neutral", "grief")
STATUSES = ("clear", "mixed", "uncertain", "unclear", "attributed_other", "out_of_scope")
REVIEW_FIELDS = (
    "case_id", "language", "text", "reviewer_id", "language_proficiency_confirmed",
    "independent_review_attested", "status", "label", "certainty_1_to_3",
    "language_correction", "issue_tags", "rationale",
)
ADJ_FIELDS = (
    "case_id", "language", "text", "reviewer_a_id", "reviewer_a_status",
    "reviewer_a_label", "reviewer_a_issue_tags", "reviewer_a_rationale",
    "reviewer_b_id", "reviewer_b_status", "reviewer_b_label", "reviewer_b_issue_tags",
    "reviewer_b_rationale", "reviewer_decision_disagreement", "adjudicator_id",
    "adjudicator_language_proficiency_confirmed", "adjudicator_blind_to_model_and_key_attested",
    "final_language", "final_status", "final_label", "final_issue_tags", "adjudication_reason",
)
REVIEWED_MANIFEST = ROOT / "reviewed_dataset_manifest.json"
REVIEWED_MANIFEST_SHA = ROOT / "reviewed_dataset_manifest.sha256"
ISSUE_TAGS = {
    "unclear_language", "mixed_emotion", "third_person_attribution", "sarcasm",
    "slang", "filipino_taglish_interpretation", "other",
}
EMAIL_RE = re.compile(r"\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b")
URL_RE = re.compile(r"\b(?:https?://|www\.)\S+", re.I)
PHONE_RE = re.compile(r"(?<!\d)(?:\+?\d[\d\s().-]{8,}\d)(?!\d)")
PSEUDONYM_RE = re.compile(r"^[A-Za-z0-9_-]{3,32}$")
EXPECTED_PRODUCTION_WEIGHT_SHA256 = "76700A16D018EB4619F342A12E7B0E3976E17EE64C46E435F204B6516A6E5EBF"


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest().upper()


def _load_or_create_case_map(families: list[dict[str, Any]]) -> dict[str, dict[str, str]]:
    family_ids = {str(family["family_id"]) for family in families}
    if CASE_MAP.exists():
        stored = json.loads(CASE_MAP.read_text(encoding="utf-8"))
        if stored.get("dataset_id") != "magirem-six-class-phase3-synthetic-candidates-v1":
            raise ValueError("Blind case-ID map belongs to a different dataset")
        mapping = stored.get("mapping")
    else:
        mapping = {
            family_id: {language: "C-" + secrets.token_hex(8).upper() for language in LANGUAGES}
            for family_id in sorted(family_ids)
        }
        payload = {"dataset_id": "magirem-six-class-phase3-synthetic-candidates-v1", "mapping": mapping}
        with CASE_MAP.open("x", encoding="utf-8", newline="\n") as handle:
            json.dump(payload, handle, ensure_ascii=False, indent=2)
            handle.write("\n")
    if not isinstance(mapping, dict) or set(mapping) != family_ids:
        raise ValueError("Blind case-ID map has missing or extra scenario families")
    values = []
    for family_id, language_map in mapping.items():
        if not isinstance(language_map, dict) or set(language_map) != set(LANGUAGES):
            raise ValueError(f"Blind case-ID map has missing language IDs for {family_id}")
        for language in LANGUAGES:
            opaque_id = language_map[language]
            if not isinstance(opaque_id, str) or not re.fullmatch(r"C-[0-9A-F]{16}", opaque_id):
                raise ValueError("Blind case-ID map contains a malformed opaque ID")
            values.append(opaque_id)
    if len(values) != len(set(values)):
        raise ValueError("Blind case-ID map contains duplicate opaque IDs")
    return mapping


def read_bank(path: Path = BANK) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    families: list[dict[str, Any]] = []
    for line_no, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
        if not line.strip():
            continue
        try:
            row = json.loads(line)
        except json.JSONDecodeError as exc:
            raise ValueError(f"Invalid JSONL at line {line_no}: {exc}") from exc
        families.append(row)
    if len(families) != 48:
        raise ValueError(f"Expected 48 scenario families, found {len(families)}")
    family_ids_precheck = [str(family.get("family_id", "")) for family in families]
    if any(not family_id for family_id in family_ids_precheck) or len(family_ids_precheck) != len(set(family_ids_precheck)):
        raise ValueError("Candidate bank has missing or duplicate scenario family IDs")
    case_map = _load_or_create_case_map(families)
    seen_family: set[str] = set()
    family_counts: Counter[str] = Counter()
    cases: list[dict[str, Any]] = []
    seen_text: dict[str, str] = {}
    seen_case_ids: set[str] = set()
    for family in families:
        family_id = str(family.get("family_id", ""))
        label = str(family.get("provisional_label", ""))
        if not family_id or family_id in seen_family:
            raise ValueError(f"Missing or duplicate source family id: {family_id!r}")
        if label not in LABELS:
            raise ValueError(f"Invalid provisional label in {family_id}: {label!r}")
        seen_family.add(family_id)
        family_counts[label] += 1
        for language in LANGUAGES:
            text = str(family.get(language, "")).strip()
            if not text or len(text) > 420:
                raise ValueError(f"Missing/overlong {language} text in {family_id}")
            if EMAIL_RE.search(text) or URL_RE.search(text) or PHONE_RE.search(text):
                raise ValueError(f"Possible contact identifier in synthetic case {family_id}; inspect before use")
            normalized = re.sub(r"\W+", " ", text.casefold()).strip()
            if normalized in seen_text:
                raise ValueError(f"Exact normalized duplicate: {family_id} matches {seen_text[normalized]}")
            # Random per-utterance IDs are looked up in a private mapping file;
            # they cannot be derived from visible family/language identifiers.
            case_id = case_map[family_id][language]
            if case_id in seen_case_ids:
                raise ValueError("Opaque candidate ID collision detected")
            seen_case_ids.add(case_id)
            seen_text[normalized] = case_id
            cases.append({
                "case_id": case_id,
                "source_group_id": family_id,
                "language": language,
                "text": text,
                "provisional_label": label,
                "expression_type": str(family.get("expression_type", "")),
                "source_type": "ai_authored_synthetic",
                "split_role": "final_holdout_only",
                "annotation_status": "awaiting_independent_review",
            })
    if any(family_counts[label] != 8 for label in LABELS):
        raise ValueError(f"Unbalanced scenario-family target counts: {dict(family_counts)}")
    return families, cases


def audit() -> dict[str, Any]:
    _, cases = read_bank()
    by_lang: Counter[str] = Counter(row["language"] for row in cases)
    by_label: Counter[str] = Counter(row["provisional_label"] for row in cases)
    by_expression: Counter[str] = Counter(row["expression_type"] for row in cases)
    cell_counts: Counter[tuple[str, str]] = Counter(
        (row["provisional_label"], row["language"]) for row in cases
    )
    near_pairs = []
    for index, left in enumerate(cases):
        left_norm = _normalized(left["text"])
        for right in cases[index + 1:]:
            if left["source_group_id"] == right["source_group_id"]:
                continue
            similarity = SequenceMatcher(None, left_norm, _normalized(right["text"]), autojunk=False).ratio()
            if similarity >= 0.85:
                near_pairs.append({
                    "case_a": left["case_id"], "case_b": right["case_id"],
                    "similarity": round(similarity, 4),
                })
    return {
        "dataset_id": "magirem-six-class-phase3-synthetic-candidates-v1",
        "candidate_cases": len(cases),
        "source_groups": len({row["source_group_id"] for row in cases}),
        "languages": dict(by_lang),
        "provisional_intended_label_counts_not_ground_truth": dict(by_label),
        "provisional_label_by_language": {
            label: {language: cell_counts[(label, language)] for language in LANGUAGES}
            for label in LABELS
        },
        "exact_normalized_duplicates_within_candidate_set": 0,
        "cross_group_near_duplicate_pairs_at_or_above_0_85": near_pairs,
        "all_language_variants_kept_in_same_holdout_group": True,
        "expression_type_counts_not_adjudicated": dict(by_expression),
        "provenance": {
            "source_type": "new AI-authored synthetic scenarios",
            "authoring_date": "2026-11-09",
            "source_corpus": None,
            "identifiable_student_messages_used": False,
            "human_reviewed_count": 0,
            "intended_labels_are_provisional": True,
        },
        "external_training_corpus_overlap_check": "not_run_in_prep; requires approved de-identified corpus",
        "human_reviewed_count": 0,
        "annotation_state": "AI-authored provisional stimuli; no independent labels yet",
        "pinned_production_weights_sha256": EXPECTED_PRODUCTION_WEIGHT_SHA256,
    }


def _write_csv(path: Path, rows: list[dict[str, Any]], fields: tuple[str, ...]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def _ensure_form_unannotated(path: Path, immutable_fields: set[str]) -> None:
    """Prevent prepare from clobbering reviewer work if it is rerun."""
    if not path.exists():
        return
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        for row in csv.DictReader(handle):
            if any((value or "").strip() for key, value in row.items() if key not in immutable_fields):
                raise ValueError(f"Refusing to overwrite non-empty annotation file: {path.name}")


def prepare() -> dict[str, Any]:
    _, cases = read_bank()
    for name in ("reviewer_a_blind.csv", "reviewer_b_blind.csv"):
        _ensure_form_unannotated(ROOT / name, {"case_id", "language", "text"})
    _ensure_form_unannotated(ROOT / "adjudication_template.csv", {"case_id", "language", "text"})
    reviewers = []
    for reviewer_num, seed in (("a", 3109), ("b", 8311)):
        rows = [{
            "case_id": case["case_id"],
            "language": case["language"],
            "text": case["text"],
            "reviewer_id": "",
            "language_proficiency_confirmed": "",
            "independent_review_attested": "",
            "status": "",
            "label": "",
            "certainty_1_to_3": "",
            "language_correction": "",
            "issue_tags": "",
            "rationale": "",
        } for case in cases]
        # Stable but different blind order per reviewer; no target labels/groups exposed.
        import random
        random.Random(seed).shuffle(rows)
        _write_csv(ROOT / f"reviewer_{reviewer_num}_blind.csv", rows, REVIEW_FIELDS)
        reviewers.append(f"reviewer_{reviewer_num}_blind.csv")
    adjudication = [{
        "case_id": case["case_id"], "language": case["language"], "text": case["text"],
        "reviewer_a_id": "", "reviewer_a_status": "", "reviewer_a_label": "",
        "reviewer_a_issue_tags": "", "reviewer_a_rationale": "", "reviewer_b_id": "",
        "reviewer_b_status": "", "reviewer_b_label": "", "reviewer_b_issue_tags": "",
        "reviewer_b_rationale": "", "reviewer_decision_disagreement": "",
        "adjudicator_id": "", "adjudicator_language_proficiency_confirmed": "",
        "adjudicator_blind_to_model_and_key_attested": "", "final_language": "",
        "final_status": "", "final_label": "", "final_issue_tags": "", "adjudication_reason": "",
    } for case in cases]
    _write_csv(ROOT / "adjudication_template.csv", adjudication, ADJ_FIELDS)
    report = audit()
    report["candidate_bank_sha256"] = sha256_bytes(BANK.read_bytes())
    report["blind_case_id_map_sha256"] = sha256_file(CASE_MAP)
    report["reviewer_case_id_scheme"] = "random opaque per-utterance IDs stored in a private mapping file; no scenario-family/language suffix"
    report["reviewer_forms_exclude_provisional_labels_and_group_ids"] = True
    report["score_requires_completed_review_manifest_and_sidecar"] = True
    report["blind_forms"] = reviewers
    report["adjudication_template"] = "adjudication_template.csv"
    MANIFEST.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return report


def _normalized(text: str) -> str:
    return re.sub(r"\W+", " ", text.casefold()).strip()


def overlap_audit(corpus: Path, output: Path, deidentified_confirmed: bool) -> dict[str, Any]:
    if not deidentified_confirmed:
        raise ValueError("Refusing to inspect corpus unless --deidentified-confirmed is supplied after owner/privacy review")
    _, cases = read_bank()
    records = []
    for line_no, line in enumerate(corpus.read_text(encoding="utf-8").splitlines(), 1):
        if not line.strip():
            continue
        row = json.loads(line)
        if not isinstance(row, dict):
            raise ValueError(f"Training export row {line_no} is not a JSON object; no overlap scan completed")
        text = str(row.get("text", "")).strip()
        if not text:
            raise ValueError(f"Training export row {line_no} has no text; no overlap scan completed")
        split = str(row.get("split", "")).strip().casefold()
        if split not in {"train", "training"}:
            raise ValueError(f"Training export row {line_no} is missing a recognized train split; no overlap scan completed")
        if EMAIL_RE.search(text) or URL_RE.search(text) or PHONE_RE.search(text):
            raise ValueError(f"Obvious identifier pattern found at corpus row {line_no}; no overlap scan performed")
        records.append((line_no, text))
    if not records:
        raise ValueError("Approved overlap export contains no training records; no clearance issued")
    overlaps = []
    corpus_norm = [(_normalized(text), line_no) for line_no, text in records]
    for case in cases:
        c_norm = _normalized(case["text"])
        c_hash = sha256_bytes(c_norm.encode("utf-8"))
        for train_norm, row_index in corpus_norm:
            score = SequenceMatcher(None, c_norm, train_norm, autojunk=False).ratio()
            if c_hash == sha256_bytes(train_norm.encode("utf-8")) or score >= 0.85:
                overlaps.append({
                    "candidate_case_id": case["case_id"],
                    "training_row_index": row_index,
                    "similarity": round(score, 4),
                    "kind": "exact" if c_norm == train_norm else "near",
                })
    result = {
        "candidate_bank_sha256": sha256_bytes(BANK.read_bytes()),
        "blind_case_id_map_sha256": sha256_file(CASE_MAP),
        "training_file_sha256": sha256_bytes(corpus.read_bytes()),
        "deidentified_input_confirmation": True,
        "training_records_scanned": len(records),
        "similarity_threshold": 0.85,
        "overlap_pairs": overlaps,
        "overlap_pair_count": len(overlaps),
        "exact_overlap_count": sum(pair["kind"] == "exact" for pair in overlaps),
        "near_overlap_count": sum(pair["kind"] == "near" for pair in overlaps),
        "status": "clear" if not overlaps else "blocked_overlap_found",
        "raw_training_text_written": False,
        "pii_scan": "email/URL/phone patterns only; not a complete person-name detector",
    }
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return result


def _load_csv(path: Path, required: tuple[str, ...]) -> dict[str, dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        forbidden = {"provisional_label", "intended_label", "target_label", "source_group_id", "family_id", "expression_type", "split_role", "source_type"}
        leaked = forbidden.intersection(reader.fieldnames or [])
        if leaked:
            raise ValueError(f"{path.name} exposes answer-key/group fields: {sorted(leaked)}")
        missing = set(required) - set(reader.fieldnames or [])
        if missing:
            raise ValueError(f"{path.name} missing required columns: {sorted(missing)}")
        rows = {}
        for row in reader:
            case_id = (row.get("case_id") or "").strip()
            if not case_id or case_id in rows:
                raise ValueError(f"{path.name} has blank or duplicate case_id")
            rows[case_id] = {key: (value or "").strip() for key, value in row.items()}
        return rows


def _parse_issue_tags(value: str, field_name: str) -> list[str]:
    tags = [part.strip() for part in value.split(";") if part.strip()]
    if len(tags) != len(set(tags)) or set(tags) - ISSUE_TAGS:
        raise ValueError(f"{field_name} contains duplicate or unsupported issue tags")
    return tags


def _validate_review(rows: dict[str, dict[str, str]], expected: set[str], form_name: str) -> str:
    if set(rows) != expected:
        raise ValueError(f"{form_name} case IDs do not exactly match the frozen 192-case set")
    ids = {row["reviewer_id"] for row in rows.values()}
    if "" in ids or len(ids) != 1:
        raise ValueError(f"{form_name} must use one non-empty pseudonymous reviewer_id")
    reviewer_id = next(iter(ids))
    if not PSEUDONYM_RE.fullmatch(reviewer_id) or EMAIL_RE.search(reviewer_id) or PHONE_RE.search(reviewer_id):
        raise ValueError(f"{form_name} reviewer_id must be a non-identifying pseudonym")
    for case_id, row in rows.items():
        status = row["status"]
        label = row["label"]
        if status not in STATUSES:
            raise ValueError(f"{form_name}:{case_id} has invalid/blank status")
        if row["language_proficiency_confirmed"].casefold() not in {"yes", "y"}:
            raise ValueError(f"{form_name}:{case_id} reviewer language proficiency is not confirmed")
        if row["independent_review_attested"].casefold() not in {"yes", "y"}:
            raise ValueError(f"{form_name}:{case_id} independent-review attestation is missing")
        if status == "clear" and label not in LABELS:
            raise ValueError(f"{form_name}:{case_id} clear status requires a six-class label")
        if status != "clear" and label:
            raise ValueError(f"{form_name}:{case_id} non-clear status must not be forced into a class")
        if status != "clear" and not row["rationale"]:
            raise ValueError(f"{form_name}:{case_id} needs rationale for non-clear status")
        if row["certainty_1_to_3"] not in {"1", "2", "3"}:
            raise ValueError(f"{form_name}:{case_id} certainty must be 1, 2, or 3")
        _parse_issue_tags(row["issue_tags"], f"{form_name}:{case_id}")
        if row["language_correction"] and row["language_correction"] not in LANGUAGES:
            raise ValueError(f"{form_name}:{case_id} has invalid language correction")
    return reviewer_id


def _validate_review_stimuli(rows: dict[str, dict[str, str]], cases: list[dict[str, Any]], form_name: str) -> None:
    frozen = {case["case_id"]: case for case in cases}
    for case_id, row in rows.items():
        case = frozen[case_id]
        if row["text"] != case["text"] or row["language"] != case["language"]:
            raise ValueError(f"{form_name}:{case_id} stimulus or language was changed after freeze")


def _review_package(
    reviewer_a: Path, reviewer_b: Path, adjudication: Path, overlap_report: Path,
) -> dict[str, Any]:
    if not MANIFEST.exists():
        raise ValueError("Run prepare first; candidate manifest is missing")
    candidate_manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    bank_hash = sha256_bytes(BANK.read_bytes())
    if candidate_manifest.get("candidate_bank_sha256") != bank_hash or candidate_manifest.get("blind_case_id_map_sha256") != sha256_file(CASE_MAP):
        raise ValueError("Candidate bank changed after freeze; create a new dataset version")
    overlap = json.loads(overlap_report.read_text(encoding="utf-8"))
    if overlap.get("candidate_bank_sha256") != bank_hash or overlap.get("blind_case_id_map_sha256") != sha256_file(CASE_MAP) or overlap.get("status") != "clear":
        raise ValueError("Matching clear training-overlap report is required")
    if overlap.get("deidentified_input_confirmation") is not True or overlap.get("training_records_scanned", 0) < 1 or overlap.get("overlap_pair_count") != 0 or overlap.get("raw_training_text_written") is not False:
        raise ValueError("Overlap report is incomplete, contains matches, or does not confirm text-safe output")
    _, cases = read_bank()
    expected = {row["case_id"] for row in cases}
    ra = _load_csv(reviewer_a, REVIEW_FIELDS)
    rb = _load_csv(reviewer_b, REVIEW_FIELDS)
    adjud = _load_csv(adjudication, ADJ_FIELDS)
    reviewer_a_id = _validate_review(ra, expected, "reviewer A")
    reviewer_b_id = _validate_review(rb, expected, "reviewer B")
    _validate_review_stimuli(ra, cases, "reviewer A")
    _validate_review_stimuli(rb, cases, "reviewer B")
    if reviewer_a_id == reviewer_b_id:
        raise ValueError("Independent reviewers must have distinct pseudonymous IDs")
    if set(adjud) != expected:
        raise ValueError("Adjudication IDs do not exactly match the frozen 192-case set")

    status_counts: Counter[str] = Counter()
    issue_counts: Counter[str] = Counter()
    reviewer_a_issue_counts: Counter[str] = Counter()
    reviewer_b_issue_counts: Counter[str] = Counter()
    disagreement_count = 0
    group_by_case = {case["case_id"]: case["source_group_id"] for case in cases}
    language_by_case = {case["case_id"]: case["language"] for case in cases}
    text_by_case = {case["case_id"]: case["text"] for case in cases}
    for case in cases:
        cid = case["case_id"]
        a, b, d = ra[cid], rb[cid], adjud[cid]
        if d["language"] != language_by_case[cid] or d["text"] != text_by_case[cid]:
            raise ValueError(f"{cid}: adjudication worksheet text/language differs from frozen stimulus")
        for prefix, review, reviewer_id in (("reviewer_a", a, reviewer_a_id), ("reviewer_b", b, reviewer_b_id)):
            if d[f"{prefix}_id"] != reviewer_id:
                raise ValueError(f"{cid}: merged {prefix} ID does not match the submitted review")
            for source_field, merged_field in (("status", "status"), ("label", "label"), ("issue_tags", "issue_tags"), ("rationale", "rationale")):
                if d[f"{prefix}_{merged_field}"] != review[source_field]:
                    raise ValueError(f"{cid}: merged {prefix} annotation differs from submitted review")
        decision_a = (a["status"], a["label"] if a["status"] == "clear" else "")
        decision_b = (b["status"], b["label"] if b["status"] == "clear" else "")
        language_a = a["language_correction"] or case["language"]
        language_b = b["language_correction"] or case["language"]
        disagreement = decision_a != decision_b or language_a != language_b
        disagreement_count += int(disagreement)
        reviewer_a_issue_counts.update(_parse_issue_tags(a["issue_tags"], f"reviewer A:{cid}"))
        reviewer_b_issue_counts.update(_parse_issue_tags(b["issue_tags"], f"reviewer B:{cid}"))
        if d["reviewer_decision_disagreement"] != ("yes" if disagreement else "no"):
            raise ValueError(f"{cid}: disagreement flag is incorrect")

        adjudicator_id = d["adjudicator_id"]
        if not PSEUDONYM_RE.fullmatch(adjudicator_id) or EMAIL_RE.search(adjudicator_id) or PHONE_RE.search(adjudicator_id) or adjudicator_id in {reviewer_a_id, reviewer_b_id}:
            raise ValueError(f"{cid}: every final decision requires a distinct human adjudicator ID")
        if d["adjudicator_language_proficiency_confirmed"].casefold() not in {"yes", "y"}:
            raise ValueError(f"{cid}: adjudicator language proficiency is not confirmed")
        if d["adjudicator_blind_to_model_and_key_attested"].casefold() not in {"yes", "y"}:
            raise ValueError(f"{cid}: adjudicator must attest not seeing model output or provisional key")

        status, label = d["final_status"], d["final_label"]
        if status not in STATUSES:
            raise ValueError(f"{cid}: final status is missing or invalid")
        if status == "clear" and label not in LABELS:
            raise ValueError(f"{cid}: clear final status requires one of the six labels")
        if status != "clear" and label:
            raise ValueError(f"{cid}: non-clear final status must not be assigned a class")
        if d["final_language"] not in LANGUAGES:
            raise ValueError(f"{cid}: adjudicator must confirm a valid final language")
        final_tags = _parse_issue_tags(d["final_issue_tags"], f"adjudication:{cid}")
        issue_counts.update(final_tags)
        final_decision = (status, label if status == "clear" else "")
        reason_required = (
            disagreement or final_decision != decision_a or final_decision != decision_b
            or status != "clear" or d["final_language"] != case["language"]
        )
        if reason_required and not d["adjudication_reason"]:
            raise ValueError(f"{cid}: adjudication reason is required for disagreement, non-clear status, or correction")
        status_counts[status] += 1
    return {
        "candidate_bank_sha256": bank_hash,
        "reviewer_a": {"path": reviewer_a, "rows": ra, "id": reviewer_a_id},
        "reviewer_b": {"path": reviewer_b, "rows": rb, "id": reviewer_b_id},
        "adjudication": {"path": adjudication, "rows": adjud},
        "overlap_report": {"path": overlap_report, "data": overlap},
        "cases": cases,
        "source_group_by_case": group_by_case,
        "status_counts": status_counts,
        "issue_counts": issue_counts,
        "reviewer_a_issue_counts": reviewer_a_issue_counts,
        "reviewer_b_issue_counts": reviewer_b_issue_counts,
        "disagreement_count": disagreement_count,
    }


def merge_reviews(reviewer_a: Path, reviewer_b: Path, output: Path) -> dict[str, Any]:
    if output.exists():
        raise ValueError(f"Refusing to overwrite existing adjudication file: {output}")
    _, cases = read_bank()
    expected = {row["case_id"] for row in cases}
    ra = _load_csv(reviewer_a, REVIEW_FIELDS)
    rb = _load_csv(reviewer_b, REVIEW_FIELDS)
    reviewer_a_id = _validate_review(ra, expected, "reviewer A")
    reviewer_b_id = _validate_review(rb, expected, "reviewer B")
    _validate_review_stimuli(ra, cases, "reviewer A")
    _validate_review_stimuli(rb, cases, "reviewer B")
    if reviewer_a_id == reviewer_b_id:
        raise ValueError("Independent reviewers must have distinct pseudonymous IDs")
    rows = []
    disagreements = []
    for case in cases:
        cid = case["case_id"]
        a, b = ra[cid], rb[cid]
        da = (a["status"], a["label"] if a["status"] == "clear" else "")
        db = (b["status"], b["label"] if b["status"] == "clear" else "")
        lang_a = a["language_correction"] or case["language"]
        lang_b = b["language_correction"] or case["language"]
        disagree = da != db or lang_a != lang_b
        if disagree:
            disagreements.append(cid)
        rows.append({
            "case_id": cid, "language": case["language"], "text": case["text"],
            "reviewer_a_id": reviewer_a_id, "reviewer_a_status": a["status"],
            "reviewer_a_label": a["label"], "reviewer_a_issue_tags": a["issue_tags"],
            "reviewer_a_rationale": a["rationale"], "reviewer_b_id": reviewer_b_id,
            "reviewer_b_status": b["status"], "reviewer_b_label": b["label"],
            "reviewer_b_issue_tags": b["issue_tags"], "reviewer_b_rationale": b["rationale"],
            "reviewer_decision_disagreement": "yes" if disagree else "no",
            "adjudicator_id": "", "adjudicator_language_proficiency_confirmed": "",
            "adjudicator_blind_to_model_and_key_attested": "", "final_language": "",
            "final_status": "", "final_label": "", "final_issue_tags": "", "adjudication_reason": "",
        })
    _write_csv(output, rows, ADJ_FIELDS)
    return {"case_count": len(rows), "disagreement_count": len(disagreements), "disagreement_case_ids": disagreements, "output": str(output)}


def freeze_reviews(reviewer_a: Path, reviewer_b: Path, adjudication: Path, overlap_report: Path) -> dict[str, Any]:
    if REVIEWED_MANIFEST.exists() or REVIEWED_MANIFEST_SHA.exists():
        raise ValueError("A reviewed manifest already exists; do not overwrite a frozen review version")
    package = _review_package(reviewer_a, reviewer_b, adjudication, overlap_report)
    def file_record(path: Path) -> dict[str, str]:
        return {"file": path.name, "sha256": sha256_file(path)}
    clear_count = package["status_counts"]["clear"]
    reviewed = {
        "dataset_id": "magirem-six-class-phase3-synthetic-candidates-v1",
        "manifest_type": "frozen_human_review_and_adjudication",
        "frozen_at_utc": datetime.now(timezone.utc).isoformat(),
        "candidate_bank_sha256": package["candidate_bank_sha256"],
        "blind_case_id_map_sha256": sha256_file(CASE_MAP),
        "candidate_count": len(package["cases"]),
        "reviewer_a": {**file_record(reviewer_a), "pseudonymous_id": package["reviewer_a"]["id"], "independence_attestation_validated": True},
        "reviewer_b": {**file_record(reviewer_b), "pseudonymous_id": package["reviewer_b"]["id"], "independence_attestation_validated": True},
        "reviewers_have_distinct_ids": True,
        "adjudication": {**file_record(adjudication), "distinct_adjudicator_per_row_validated": True},
        "overlap_report": {**file_record(overlap_report), "training_file_sha256": package["overlap_report"]["data"]["training_file_sha256"], "status": "clear"},
        "final_status_counts": dict(package["status_counts"]),
        "reviewer_decision_disagreement_count": package["disagreement_count"],
        "clear_adjudicated_label_count": clear_count,
        "non_clear_cases_excluded_from_classifier_accuracy": len(package["cases"]) - clear_count,
        "adjudicated_issue_tag_counts": dict(package["issue_counts"]),
        "reviewer_issue_tag_counts": {
            "reviewer_a": dict(package["reviewer_a_issue_counts"]),
            "reviewer_b": dict(package["reviewer_b_issue_counts"]),
        },
        "reviewer_identity_verification": "must be verified by project coordinator separately; only pseudonymous IDs retained here",
        "provisional_key_used_as_ground_truth": False,
        "accuracy_or_model_results_present": False,
    }
    serialized = (json.dumps(reviewed, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    with REVIEWED_MANIFEST.open("xb") as handle:
        handle.write(serialized)
    digest = hashlib.sha256(serialized).hexdigest()
    with REVIEWED_MANIFEST_SHA.open("x", encoding="ascii", newline="\n") as handle:
        handle.write(digest + "  reviewed_dataset_manifest.json\n")
    return reviewed


def _wilson(successes: int, total: int, z: float = 1.96) -> list[float] | None:
    if total == 0:
        return None
    p = successes / total
    den = 1 + z * z / total
    center = (p + z * z / (2 * total)) / den
    half = z * ((p * (1 - p) / total + z * z / (4 * total * total)) ** 0.5) / den
    return [round(max(0.0, center - half), 4), round(min(1.0, center + half), 4)]


def _grouped_accuracy_interval(rows: list[dict[str, Any]], *, replicates: int = 2000) -> dict[str, Any] | None:
    """Deterministic cluster bootstrap so parallel language renderings stay together."""
    if not rows or not all(row.get("source_group_id") for row in rows):
        return None
    groups: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        groups[str(row["source_group_id"])].append(row)
    group_ids = sorted(groups)
    if len(group_ids) < 2:
        return None
    rng = random.Random(20261109)
    estimates = []
    for _ in range(replicates):
        sample = [row for group_id in rng.choices(group_ids, k=len(group_ids)) for row in groups[group_id]]
        estimates.append(sum(row["truth"] == row["prediction"] for row in sample) / len(sample))
    estimates.sort()
    lower = estimates[max(0, math.floor(0.025 * (replicates - 1)))]
    upper = estimates[min(replicates - 1, math.ceil(0.975 * (replicates - 1)))]
    return {
        "metric": "accuracy",
        "cluster_unit": "original scenario family",
        "cluster_count": len(group_ids),
        "replicates": replicates,
        "seed": 20261109,
        "percentile_95_ci": [round(lower, 4), round(upper, 4)],
    }


def _score_rows(rows: list[dict[str, Any]], labels: tuple[str, ...]) -> dict[str, Any]:
    n = len(rows)
    if not n:
        return {"n": 0}
    confusion = {truth: {pred: 0 for pred in labels} for truth in labels}
    counts = {label: {"tp": 0, "fp": 0, "fn": 0, "support": 0, "predicted": 0} for label in labels}
    correct = 0
    brier_sum = 0.0
    ece_bins = [{"n": 0, "conf_sum": 0.0, "correct": 0} for _ in range(10)]
    uncertain = 0
    for row in rows:
        truth, pred = row["truth"], row["prediction"]
        confusion[truth][pred] += 1
        counts[truth]["support"] += 1
        counts[pred]["predicted"] += 1
        if truth == pred:
            correct += 1
            counts[truth]["tp"] += 1
        else:
            counts[pred]["fp"] += 1
            counts[truth]["fn"] += 1
        probabilities = row["probabilities"]
        brier_sum += sum((probabilities[label] - (1.0 if label == truth else 0.0)) ** 2 for label in labels)
        confidence = max(probabilities.values())
        is_correct = truth == pred
        bin_index = min(9, int(confidence * 10))
        ece_bins[bin_index]["n"] += 1
        ece_bins[bin_index]["conf_sum"] += confidence
        ece_bins[bin_index]["correct"] += int(is_correct)
        if confidence < 0.5:
            uncertain += 1
    per_class = {}
    for label in labels:
        c = counts[label]
        precision = c["tp"] / (c["tp"] + c["fp"]) if c["tp"] + c["fp"] else 0.0
        recall = c["tp"] / c["support"] if c["support"] else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if precision + recall else 0.0
        per_class[label] = {**c, "precision": round(precision, 4), "recall": round(recall, 4), "f1": round(f1, 4)}
    ece = sum((b["n"] / n) * abs(b["correct"] / b["n"] - b["conf_sum"] / b["n"]) for b in ece_bins if b["n"])
    accuracy = correct / n
    result = {
        "n": n,
        "accuracy": round(accuracy, 4),
        # Wilson is a familiar case-level interval; the clustered interval below
        # is the preferred uncertainty summary where scenario renderings repeat.
        "accuracy_wilson_95_ci": _wilson(correct, n),
        "micro_precision": round(accuracy, 4),
        "micro_recall": round(accuracy, 4),
        "micro_f1": round(accuracy, 4),
        "macro_precision": round(sum(x["precision"] for x in per_class.values()) / len(labels), 4),
        "macro_recall": round(sum(x["recall"] for x in per_class.values()) / len(labels), 4),
        "macro_f1": round(sum(x["f1"] for x in per_class.values()) / len(labels), 4),
        "weighted_f1": round(sum(x["f1"] * x["support"] for x in per_class.values()) / n, 4),
        "top_label_ece_10bin": round(ece, 4),
        "multiclass_brier": round(brier_sum / n, 4),
        "low_confidence_below_0_50_count": uncertain,
        "per_class": per_class,
        "confusion_rows_true_columns_predicted": confusion,
    }
    grouped_interval = _grouped_accuracy_interval(rows)
    if grouped_interval:
        result["accuracy_source_group_cluster_bootstrap"] = grouped_interval
    return result


def _cohen_kappa(left: list[str], right: list[str]) -> float | None:
    if not left or len(left) != len(right):
        return None
    n = len(left)
    categories = set(left) | set(right)
    observed = sum(a == b for a, b in zip(left, right)) / n
    expected = sum((left.count(c) / n) * (right.count(c) / n) for c in categories)
    return round((observed - expected) / (1 - expected), 4) if expected < 1 else 1.0


def _verify_reviewed_manifest(path: Path, package: dict[str, Any]) -> dict[str, Any]:
    sidecar = path.with_suffix(".sha256")
    if not path.is_file() or not sidecar.is_file():
        raise ValueError("A frozen reviewed-dataset manifest and its SHA-256 sidecar are required")
    data = path.read_bytes()
    expected_line = sidecar.read_text(encoding="ascii").strip().split()[0]
    if hashlib.sha256(data).hexdigest() != expected_line:
        raise ValueError("Reviewed-dataset manifest hash check failed")
    manifest = json.loads(data.decode("utf-8"))
    if manifest.get("manifest_type") != "frozen_human_review_and_adjudication":
        raise ValueError("The reviewed manifest is missing the required frozen-human-review marker")
    if manifest.get("candidate_bank_sha256") != package["candidate_bank_sha256"] or manifest.get("blind_case_id_map_sha256") != sha256_file(CASE_MAP) or manifest.get("candidate_count") != 192:
        raise ValueError("Reviewed manifest does not match the frozen 192-case candidate bank")
    if manifest.get("reviewers_have_distinct_ids") is not True:
        raise ValueError("Reviewed manifest does not certify distinct reviewer identities")
    if manifest.get("adjudication", {}).get("distinct_adjudicator_per_row_validated") is not True:
        raise ValueError("Reviewed manifest does not certify adjudicator separation")
    if manifest.get("reviewer_a", {}).get("pseudonymous_id") != package["reviewer_a"]["id"] or manifest.get("reviewer_b", {}).get("pseudonymous_id") != package["reviewer_b"]["id"]:
        raise ValueError("Reviewed manifest reviewer IDs do not match submitted forms")
    if manifest.get("final_status_counts") != dict(package["status_counts"]):
        raise ValueError("Reviewed manifest final-status counts do not match adjudication")
    if manifest.get("clear_adjudicated_label_count") != package["status_counts"]["clear"]:
        raise ValueError("Reviewed manifest scorable-label count does not match adjudication")
    for key, path_key in (("reviewer_a", "reviewer_a"), ("reviewer_b", "reviewer_b"), ("adjudication", "adjudication")):
        record = manifest.get(key, {})
        submitted_path = package[path_key]["path"]
        if record.get("file") != submitted_path.name or record.get("sha256") != sha256_file(submitted_path):
            raise ValueError(f"Reviewed manifest does not match submitted {key} file")
    overlap_record = manifest.get("overlap_report", {})
    overlap_path = package["overlap_report"]["path"]
    if overlap_record.get("file") != overlap_path.name or overlap_record.get("sha256") != sha256_file(overlap_path):
        raise ValueError("Reviewed manifest does not match the supplied overlap report")
    if overlap_record.get("training_file_sha256") != package["overlap_report"]["data"].get("training_file_sha256"):
        raise ValueError("Reviewed manifest training-export hash differs from overlap report")
    if overlap_record.get("status") != "clear" or manifest.get("provisional_key_used_as_ground_truth") is not False:
        raise ValueError("Reviewed manifest does not certify a clear overlap audit and blinded labels")
    return manifest


def score(reviewer_a: Path, reviewer_b: Path, adjudication: Path, overlap_report: Path, reviewed_manifest: Path, output: Path | None) -> dict[str, Any]:
    package = _review_package(reviewer_a, reviewer_b, adjudication, overlap_report)
    frozen_reviews = _verify_reviewed_manifest(reviewed_manifest, package)
    current_hash = package["candidate_bank_sha256"]
    overlap = package["overlap_report"]["data"]
    ra = package["reviewer_a"]["rows"]
    rb = package["reviewer_b"]["rows"]
    adjud = package["adjudication"]["rows"]
    cases = package["cases"]
    reviewer_a_id = package["reviewer_a"]["id"]
    reviewer_b_id = package["reviewer_b"]["id"]
    reviewed_cases: list[dict[str, Any]] = []
    status_counts: Counter[str] = package["status_counts"]
    agree_values_a, agree_values_b = [], []
    for case in cases:
        cid = case["case_id"]
        a, b, d = ra[cid], rb[cid], adjud[cid]
        da = (a["status"], a["label"] if a["status"] == "clear" else "")
        db = (b["status"], b["label"] if b["status"] == "clear" else "")
        agree_values_a.append(da[0] + (":" + da[1] if da[1] else ""))
        agree_values_b.append(db[0] + (":" + db[1] if db[1] else ""))
        status = d["final_status"]
        label = d["final_label"]
        if status == "clear":
            reviewed_cases.append({"case_id": cid, "source_group_id": case["source_group_id"], "language": d["final_language"], "text": case["text"], "truth": label, "expression_type": case["expression_type"]})
    if not reviewed_cases:
        raise ValueError("No clear, adjudicated cases are available to score")
    sys.path.insert(0, str(ROOT.parent.resolve()))
    model_dir = ROOT.parent / "emotion_model_multilingual_deployable"
    model_weights = model_dir / "model.safetensors"
    actual_model_hash = sha256_file(model_weights)
    if actual_model_hash != EXPECTED_PRODUCTION_WEIGHT_SHA256:
        raise ValueError("Production checkpoint hash changed; freeze a new model/evaluation protocol before scoring")
    from trained_emotion_classifier import get_classifier
    classifier = get_classifier()
    if not classifier.enabled:
        raise RuntimeError("Existing production classifier did not load; no scores produced")
    scored = []
    inference_times_ms = []
    for row in reviewed_cases:
        started = time.perf_counter()
        prediction = classifier.predict(row["text"])
        inference_times_ms.append((time.perf_counter() - started) * 1000)
        if not prediction:
            raise RuntimeError(f"Production inference failed for opaque case {row['case_id']}")
        scored.append({**row, "prediction": prediction["emotion"], "confidence": prediction["confidence"], "probabilities": prediction["probabilities"]})
    result = {
        "dataset_id": frozen_reviews["dataset_id"],
        "candidate_bank_sha256": current_hash,
        "reviewed_dataset_manifest_sha256": sha256_file(reviewed_manifest),
        "training_corpus_sha256": overlap["training_file_sha256"],
        "model_artifact": "ml_service/emotion_model_multilingual_deployable",
        "model_weights_sha256": actual_model_hash,
        "evaluation_kind": "independently adjudicated synthetic-only diagnostic; not real-student accuracy",
        "reviewer_ids_are_pseudonymous": True,
        "reviewer_a_id": reviewer_a_id,
        "reviewer_b_id": reviewer_b_id,
        "interrater_exact_status_label_agreement": round(sum(a == b for a, b in zip(agree_values_a, agree_values_b)) / len(agree_values_a), 4),
        "interrater_cohen_kappa": _cohen_kappa(agree_values_a, agree_values_b),
        "adjudicated_status_counts": dict(status_counts),
        "adjudicated_issue_tag_counts": dict(package["issue_counts"]),
        "clear_case_coverage": round(len(reviewed_cases) / len(cases), 4),
        "overall": _score_rows(scored, LABELS),
        "inference_latency_ms": {
            "n": len(inference_times_ms),
            "median": round(statistics.median(inference_times_ms), 3),
            "p95": round(float(sorted(inference_times_ms)[max(0, math.ceil(0.95 * len(inference_times_ms)) - 1)]), 3),
        },
        "by_language": {},
        "by_expression_type": {},
        "by_length_bin": {},
        "source_group_count": len({r["source_group_id"] for r in cases}),
        "candidate_count": len(cases),
        "provisional_key_not_used_as_gold": True,
    }
    for language in LANGUAGES:
        subset = [r for r in scored if r["language"] == language]
        result["by_language"][language] = _score_rows(subset, LABELS)
    for kind in sorted({r["expression_type"] for r in scored}):
        result["by_expression_type"][kind] = _score_rows([r for r in scored if r["expression_type"] == kind], LABELS)
    length_bins = {
        "short_1_to_8_words": lambda n: n <= 8,
        "medium_9_to_24_words": lambda n: 9 <= n <= 24,
        "long_25_plus_words": lambda n: n >= 25,
    }
    for name, predicate in length_bins.items():
        subset = [r for r in scored if predicate(len(r["text"].split()))]
        result["by_length_bin"][name] = _score_rows(subset, LABELS)
    if output:
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("audit", help="validate candidate balance/provenance and report duplicates")
    sub.add_parser("prepare", help="create blank blind reviewer forms without overwriting completed reviews")
    merge_parser = sub.add_parser("merge-reviews", help="create an adjudication worksheet from two completed independent reviews")
    merge_parser.add_argument("--reviewer-a", type=Path, required=True)
    merge_parser.add_argument("--reviewer-b", type=Path, required=True)
    merge_parser.add_argument("--output", type=Path, required=True)
    overlap_parser = sub.add_parser("overlap", help="compare against an approved de-identified JSONL corpus")
    overlap_parser.add_argument("--training-corpus", type=Path, required=True)
    overlap_parser.add_argument("--deidentified-confirmed", action="store_true", help="confirm owner/privacy review of the input corpus")
    overlap_parser.add_argument("--output", type=Path, required=True)
    freeze_parser = sub.add_parser("freeze-reviews", help="validate and hash-pin completed annotations before any model evaluation")
    freeze_parser.add_argument("--reviewer-a", type=Path, required=True)
    freeze_parser.add_argument("--reviewer-b", type=Path, required=True)
    freeze_parser.add_argument("--adjudication", type=Path, required=True)
    freeze_parser.add_argument("--overlap-report", type=Path, required=True)
    score_parser = sub.add_parser("score", help="score only completed independent reviews/adjudication")
    score_parser.add_argument("--reviewer-a", type=Path, required=True)
    score_parser.add_argument("--reviewer-b", type=Path, required=True)
    score_parser.add_argument("--adjudication", type=Path, required=True)
    score_parser.add_argument("--overlap-report", type=Path, required=True)
    score_parser.add_argument("--reviewed-manifest", type=Path, required=True)
    score_parser.add_argument("--output", type=Path)
    args = parser.parse_args()
    try:
        if args.command == "audit":
            result = audit()
        elif args.command == "prepare":
            result = prepare()
        elif args.command == "merge-reviews":
            result = merge_reviews(args.reviewer_a, args.reviewer_b, args.output)
        elif args.command == "overlap":
            result = overlap_audit(args.training_corpus, args.output, args.deidentified_confirmed)
        elif args.command == "freeze-reviews":
            result = freeze_reviews(args.reviewer_a, args.reviewer_b, args.adjudication, args.overlap_report)
        else:
            result = score(args.reviewer_a, args.reviewer_b, args.adjudication, args.overlap_report, args.reviewed_manifest, args.output)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0
    except (ValueError, OSError, json.JSONDecodeError) as exc:
        print(f"BLOCKED: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
