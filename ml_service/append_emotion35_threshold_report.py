"""Append the bounded validation-only threshold experiment to the report."""
import argparse, json
from pathlib import Path
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--report",type=Path,required=True); p.add_argument("--evaluation",type=Path,required=True); a=p.parse_args()
    e=json.loads(a.evaluation.read_text(encoding="utf-8")); m=e["test_metrics"]
    text=a.report.read_text(encoding="utf-8")
    text += "\n## Development-only threshold check\n\n"
    text += f"A validation-only threshold floor/precision constraint raised the untouched-test positive-label micro-F1 to {m['micro_f1']:.4f} (precision {m['micro_precision']:.4f}, recall {m['micro_recall']:.4f}) in a separate JSON artifact. This is a calibration experiment, not independent validation; the original `thresholds.json` was not overwritten and the low baseline remains the primary reported result.\n"
    a.report.write_text(text,encoding="utf-8")
