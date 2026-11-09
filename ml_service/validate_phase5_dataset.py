"""Static integrity checks for the Phase 5 unseen draft dataset.

This script deliberately does not call Ollama or the Flask service. It only checks
that the review-ready synthetic artifact is complete and remains separate from
production/evaluation-history data.
"""

from __future__ import annotations

import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config" / "emotion_taxonomy.json"
DATASET_PATH = Path(__file__).with_name("phase5_final_unseen_cases.json")
PACKAGE_PATH = Path(__file__).with_name("PHASE5_COUNSELOR_VALIDATION_PACKAGE.json")


def main() -> None:
    taxonomy = json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))
    dataset = json.loads(DATASET_PATH.read_text(encoding="utf-8"))
    package = json.loads(PACKAGE_PATH.read_text(encoding="utf-8"))

    emotion_keys = {item["key"] for item in taxonomy["emotions"]}
    category_keys = {item["key"] for item in taxonomy["categories"]}
    assert taxonomy["version"] == dataset["taxonomy_version"] == package["taxonomy_version"]
    assert len(emotion_keys) == 35, "taxonomy must contain exactly 35 unique keys"
    assert len(category_keys) == 8, "taxonomy must contain exactly 8 categories"
    assert {item["key"] for item in package["emotions"]} == emotion_keys
    assert {item["category_key"] for item in package["emotions"]} <= category_keys

    cases = dataset["cases"]
    ids = [case["case_id"] for case in cases]
    assert len(cases) == 50
    assert len(ids) == len(set(ids)), "case IDs must be unique"
    assert all(case.get("draft_status") for case in cases)
    assert dataset["status"] == "pending_counselor_review"
    assert dataset["synthetic_only"] is True

    clear_labels = {
        case["draft_primary_key"]
        for case in cases[:35]
        if case["draft_primary_key"] is not None
    }
    assert clear_labels == emotion_keys, "first 35 cases must cover each detailed label once"

    for case in cases:
        for key in [case.get("draft_primary_key"), *case.get("draft_secondary_keys", []), *case.get("draft_other_keys", [])]:
            if key is not None:
                assert key in emotion_keys, f"unknown emotion key in {case['case_id']}: {key}"
        for key in [*case.get("draft_category_keys", []), *case.get("draft_other_category_keys", [])]:
            assert key in category_keys, f"unknown category key in {case['case_id']}: {key}"

    print(f"phase5 dataset valid: {len(cases)} synthetic cases; {len(clear_labels)} detailed labels; pending counselor review")


if __name__ == "__main__":
    main()
