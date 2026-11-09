"""CLI for auditing or training the isolated 35-emotion multilabel model.

Examples:
  python ml_service/train_emotion_35_multilabel.py --audit-only \
      --dataset-dir ml_service/emotion_data_multilingual_final_v2

Training requires an independently sourced, licensed, reviewed 35-label corpus
and a provenance JSON object.  The command refuses to write an artifact when
coverage or provenance is incomplete.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from emotion_35_multilabel_pipeline import DatasetAuditError, audit_dataset, dataset_manifest, load_taxonomy, train_model


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dataset-dir", type=Path, default=Path(__file__).resolve().parent / "emotion_data_multilingual_final_v2")
    parser.add_argument("--audit-only", action="store_true")
    parser.add_argument("--train", action="store_true")
    parser.add_argument("--output-dir", type=Path, default=Path(__file__).resolve().parent / "emotion_model_35_multilabel")
    parser.add_argument("--init-model", type=Path, default=Path(__file__).resolve().parent / "emotion_model_multilingual_deployable")
    parser.add_argument("--provenance", type=Path)
    parser.add_argument("--min-train-per-label", type=int, default=20)
    parser.add_argument("--min-validation-per-label", type=int, default=5)
    parser.add_argument("--min-test-per-label", type=int, default=5)
    parser.add_argument("--epochs", type=float, default=3.0)
    parser.add_argument("--research-only-partial", action="store_true", help="allow missing taxonomy labels for a clearly research-only masked-loss baseline")
    args = parser.parse_args()
    if args.train == args.audit_only:
        parser.error("choose exactly one of --audit-only or --train")

    provenance = None
    if args.provenance:
        provenance = json.loads(args.provenance.read_text(encoding="utf-8"))

    try:
        if args.audit_only:
            audit = audit_dataset(
                args.dataset_dir,
                min_train_per_label=args.min_train_per_label,
                min_validation_per_label=args.min_validation_per_label,
                min_test_per_label=args.min_test_per_label,
                provenance=provenance,
                allow_partial_labels=args.research_only_partial,
            )
            result = audit.to_dict()
            result["manifest_preview"] = dataset_manifest(args.dataset_dir, audit)
            print(json.dumps(result, indent=2, ensure_ascii=False))
            return 0 if audit.sufficient_for_training else 2
        if not provenance:
            raise DatasetAuditError("--provenance is required for training")
        audit = train_model(
            args.dataset_dir,
            args.output_dir,
            init_model=args.init_model,
            provenance=provenance,
            min_train_per_label=args.min_train_per_label,
            min_validation_per_label=args.min_validation_per_label,
            min_test_per_label=args.min_test_per_label,
            epochs=args.epochs,
            allow_partial_labels=args.research_only_partial,
        )
        print(json.dumps({"trained": True, "output_dir": str(args.output_dir), "audit": audit.to_dict()}, indent=2))
        return 0
    except (DatasetAuditError, OSError, json.JSONDecodeError) as exc:
        print(f"35-label pipeline refused to proceed: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())




