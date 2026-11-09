"""Run the Phase 6 final Gemma evaluation exactly against an adjudicated set.

The command refuses Phase 5 draft data. It is intentionally separate from the
frozen evaluator so the final holdout cannot be mistaken for a development set.
No production records are written.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path
from typing import Any, Dict, List


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "audit-recovery" / "emotion-taxonomy-20261024" / "phase6-final-gemma-results.json"


def _load_adjudicated(path: Path) -> tuple[Dict[str, Any], List[Dict[str, Any]]]:
    if not path.is_file():
        raise SystemExit(f"FINAL_EVALUATION_BLOCKED: adjudicated dataset not found: {path}")
    document = json.loads(path.read_text(encoding="utf-8"))
    if document.get("status") != "adjudicated" or document.get("evaluation_holdout") is not True:
        raise SystemExit("FINAL_EVALUATION_BLOCKED: dataset must be status=adjudicated and evaluation_holdout=true")
    reviewers = document.get("reviewers")
    if not isinstance(reviewers, list) or len(reviewers) < 2:
        raise SystemExit("FINAL_EVALUATION_BLOCKED: at least two reviewer records are required")
    if not document.get("adjudicated_at") or not document.get("adjudication_manifest_sha256"):
        raise SystemExit("FINAL_EVALUATION_BLOCKED: adjudication timestamp and manifest hash are required")
    cases = document.get("cases")
    if not isinstance(cases, list) or len(cases) != 50:
        raise SystemExit("FINAL_EVALUATION_BLOCKED: expected exactly 50 adjudicated cases")
    ids = [case.get("case_id") for case in cases]
    if any(not isinstance(case_id, str) for case_id in ids) or len(set(ids)) != len(ids):
        raise SystemExit("FINAL_EVALUATION_BLOCKED: case IDs must be unique")
    if any("draft_" in key for case in cases for key in case):
        raise SystemExit("FINAL_EVALUATION_BLOCKED: draft annotations remain in the final set")
    return document, cases


def _normalize(cases: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    normalized = []
    for case in cases:
        normalized.append({
            "case_id": case["case_id"],
            "text": case["text"],
            "key": case.get("adjudicated_primary_key"),
            "expected_status": case["adjudicated_status"],
            "expected_secondary_keys": list(case.get("adjudicated_secondary_keys", [])),
            "expected_other_keys": list(case.get("adjudicated_other_keys", [])),
            "expected_attribution_scope": case.get("adjudicated_attribution_scope", "none"),
        })
    return normalized


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True, help="adjudicated 50-case JSON artifact")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--confirm-final", action="store_true", help="acknowledge this is the one final holdout run")
    args = parser.parse_args()
    if not args.confirm_final:
        parser.error("--confirm-final is required; do not rerun after tuning")

    document, cases = _load_adjudicated(args.dataset)
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    from emotion_taxonomy import load_taxonomy, taxonomy_index
    from phase2_emotion_feasibility_eval import run_gemma

    taxonomy = load_taxonomy()
    index = taxonomy_index(taxonomy)
    normalized = _normalize(cases)
    result = run_gemma(normalized, taxonomy, index)
    output = {
        "evaluation_only": True,
        "final_holdout": True,
        "dataset_path": str(args.dataset),
        "dataset_sha256": hashlib.sha256(args.dataset.read_bytes()).hexdigest(),
        "adjudicated_at": document["adjudicated_at"],
        "reviewer_count": len(document["reviewers"]),
        "taxonomy_version": taxonomy.get("version"),
        "production_activation": "not enabled by this command",
        "gemma_interpretation": result,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(output, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({key: value for key, value in output.items() if key != "gemma_interpretation"}, indent=2))
    print(json.dumps(result, ensure_ascii=False, indent=2))
    print(f"RESULT_FILE={args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
