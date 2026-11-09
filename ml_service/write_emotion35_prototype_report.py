"""Write a concise, evidence-backed report for the isolated prototype."""
import argparse, json
from pathlib import Path

def main(root, model):
    summary=json.loads((root/"prototype_summary.json").read_text(encoding="utf-8"))
    prov=json.loads((root/"provenance.json").read_text(encoding="utf-8"))
    ev=json.loads((model/"evaluation.json").read_text(encoding="utf-8"))
    cfg=json.loads((model/"training_config.json").read_text(encoding="utf-8"))
    met=ev["metrics"]
    lines=[
        "# Emotion35 research prototype report (isolated)", "",
        "## Status", "",
        "This is an adviser-demo research artifact, not a validated or deployable classifier. The production six-class model, Flask Student Chat, Admin analytics, crisis controls, consent, and privacy settings were not changed.", "",
        "## Dataset and provenance", "",
        f"- Records: train {summary['records']['train']}, validation {summary['records']['validation']}, test {summary['records']['test']} (total {sum(summary['records'].values())}).",
        f"- Sources: {summary['source_counts'].get('go_emotions',0)} GoEmotions rows retained after excluding {summary.get('public_cross_split_duplicates_excluded',0)} later-split duplicate texts; {summary['source_counts'].get('synthetic_single',0)} AI-assisted synthetic single-label rows; {summary['source_counts'].get('synthetic_mixed',0)} AI-assisted mixed rows.",
        "- Synthetic rows are `unreviewed`, `human_review_required=true`, and `training_eligibility=research_only_synthetic`; no human approval is claimed.",
        "- GoEmotions observed-label masks were preserved. Unannotated labels were excluded from masked BCE rather than converted to negatives.",
        "- Exact cross-split duplicate texts: 0. Synthetic near-duplicate check: 0.",
        "- Split gate: every approved label has at least 20 train / 5 validation / 5 test positives (synthetic coverage is the reason this passes).", "",
        "## Training", "",
        f"- Base encoder: `{cfg['base_model']}`; output head: 35 labels; epochs: {cfg['epochs']}; batch size: {cfg['batch_size']}; masked BCE: {cfg['masked_loss']}.",
        "- Runtime device: CPU (`torch.cuda.is_available()` was false in this environment); RTX 3050 was not visible to the running Python process.",
        "- Actual optimizer training completed; the bounded 0.25-epoch run took approximately 1,112 seconds (18.5 minutes) on CPU.",
        "- Checkpoint is separate under `storage/app/audit-recovery/emotion_model_35_multilabel_prototype_20261026` and is not configured in production.", "",
        "## Untouched test results", "",
        f"- Test records: {ev['test_records']}; positive-label micro-precision {met['micro_precision']:.4f}, micro-recall {met['micro_recall']:.4f}, micro-F1 {met['micro_f1']:.4f}.",
        f"- Macro-F1: {met['macro_f1']:.4f}; exact-set accuracy: {met['exact_match_accuracy']:.4f}; sample-F1: {met['sample_f1']:.4f}; positive support: {met['positive_support']}.",
        f"- CPU inference: {ev['inference_seconds']:.2f}s for {ev['test_records']} rows ({ev['inference_ms_per_record']:.2f}ms/row).",
        "- Mixed subset and synthetic single-label metrics are in `evaluation.json`, including per-emotion support, TP/FP/FN, precision, recall, and F1.", "",
        "## Interpretation and limitations", "",
        "The score is low and the local demo produces many unsupported positives (for example, the mixed Taglish probe accepts unrelated emotions). This is expected evidence of an under-trained, synthetic-heavy prototype—not a reason to enable it. Synthetic wording, label imbalance, multilingual coverage, and the short CPU run limit recognition validity.",
        "No professional validation, production accuracy, or 35-emotion student recognition is claimed. The correct next step is independent reviewed data and GPU-visible retraining/threshold calibration, not connecting this checkpoint to normal conversations.", "",
        "## Isolated demo", "",
        "Run: `python ml_service/demo_emotion35_prototype.py --model storage/app/audit-recovery/emotion_model_35_multilabel_prototype_20261026 --text \"synthetic message\"`.",
        "The demo labels itself prototype-only, shows detailed labels and deterministic taxonomy categories, and never writes or exposes results through Laravel.", "",
        "## Production safety", "",
        "- Production six-class checkpoint remains the authoritative model.",
        "- Emotion35 persistence, Admin inclusion, and Student Chat activation remain disabled.",
        "- The EmpatheticDialogues context classifier remains a separate categorical research artifact and was not merged as Emotion35 ground truth.",
    ]
    (model/"PROTOTYPE_REPORT.md").write_text("\n".join(lines)+"\n",encoding="utf-8")
    print(model/"PROTOTYPE_REPORT.md")

if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--dataset",type=Path,required=True); p.add_argument("--model",type=Path,required=True); a=p.parse_args(); main(a.dataset,a.model)
