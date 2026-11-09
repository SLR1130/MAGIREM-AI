"""Create a provenance-readable quality report for existing calibration files."""
from __future__ import annotations

import json
from pathlib import Path

from emotion35_calibration import calibration_evidence_summary


ROOT = Path(__file__).resolve().parents[1]
ARTIFACTS = ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final"
NAMES = ("v2", "v3", "v4_quarantined", "recovery_masks_fixed_diagnostic", "recovery_no_weak_diagnostic")
OUTPUT = ROOT / "storage/app/audit-recovery/emotion35_evidence_quality_20261104.json"


def main() -> None:
    models = {}
    for name in NAMES:
        artifact = json.loads((ARTIFACTS / f"{name}_calibration.json").read_text(encoding="utf-8"))
        summary = calibration_evidence_summary(artifact)
        per_label = {}
        for label, status in artifact["label_status"].items():
            quality = status.get("evidence_quality")
            if not isinstance(quality, dict):
                # Older artifacts still contain reconciled source-level counts.
                from emotion35_calibration import _evidence_quality
                quality = _evidence_quality(status["provenance_by_source"], calibrated=status["status"] == "calibrated")
            per_label[label] = {
                "calibration_status": status["status"],
                "threshold": status["threshold"],
                "reason": status["reason"],
                "positive_support": status["observed_positive_count"],
                "negative_support": status["observed_negative_count"],
                "unknown_support": status["unknown_count"],
                "evidence_quality": quality,
            }
        models[name] = {
            "checkpoint_role": artifact.get("checkpoint_role", "role_not_recorded"),
            "checkpoint_sha256": artifact["checkpoint"]["sha256"],
            "validation_sha256": artifact["validation_provenance"]["sha256"],
            "validation_evidence_kind_counts": artifact["validation_provenance"]["evidence_kind_counts"],
            "evidence_quality_summary": summary,
            "labels": per_label,
        }
    report = {
        "schema": "emotion35-calibration-evidence-quality-v1",
        "generated_from_existing_artifacts": True,
        "independently_evaluated_validation_records": 0,
        "no_human_approval_claimed": True,
        "note": "Calibration provenance is not model validation. AI-assisted synthetic and weak-supervision labels are explicitly provisional and are not human-approved ground truth.",
        "models": models,
    }
    if OUTPUT.exists():
        raise FileExistsError(f"refusing to overwrite evidence quality report: {OUTPUT}")
    OUTPUT.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"output": str(OUTPUT), "models": {name: value["evidence_quality_summary"]["label_counts_by_quality"] for name, value in models.items()}, "independent_examples": 0}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
