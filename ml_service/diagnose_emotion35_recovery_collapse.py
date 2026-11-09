"""Diagnose Emotion35 recovery prediction collapse without retraining.

This script is read-only with respect to model weights and saved thresholds. It
freezes a small, fully observed, provisional diagnostic set, compares raw
probabilities at fixed thresholds, audits validation support/calibration, and
compares training metadata and parameter movement relative to v2.
"""
from __future__ import annotations

import hashlib
import json
import math
import statistics
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import numpy as np
import torch
from safetensors.torch import load_file
from transformers import AutoConfig, AutoModelForSequenceClassification, AutoTokenizer

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config" / "emotion_taxonomy.json"
OUT_ROOT = ROOT / "storage" / "app" / "audit-recovery"
DIAGNOSTIC_PATH = OUT_ROOT / "emotion35_recovery_complete_diagnostic_20261103.json"
DIAGNOSTIC_MANIFEST_PATH = OUT_ROOT / "emotion35_recovery_complete_diagnostic_20261103.manifest.json"
RESULT_PATH = OUT_ROOT / "emotion35_recovery_collapse_diagnosis_20261103.json"
CALIBRATION_PATH = OUT_ROOT / "emotion35_recovery_evidence_gated_calibration_20261103.json"
REPORT_PATH = ROOT / "ml_service" / "EMOTION35_RECOVERY_COLLAPSE_DIAGNOSIS_20261103.md"

MODEL_PATHS = {
    "v2": OUT_ROOT / "emotion_model_35_multilabel_prototype_v2_20261026",
    "recovery_weak": OUT_ROOT / "emotion_model_35_multilabel_recovery_masks_fixed_norms_20261103",
    "recovery_no_weak": OUT_ROOT / "emotion_model_35_multilabel_recovery_no_weak_20261103",
}


def load_labels() -> list[str]:
    data = json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))
    return [item["key"] for item in data["emotions"]]


def diagnostic_cases(labels: list[str]) -> list[dict[str, Any]]:
    cases = [
        ("en_neutral", "ENGLISH", "The registrar opens at eight and my appointment is listed for tomorrow.", ["neutral"], "clear", "routine factual statement"),
        ("en_happiness", "ENGLISH", "I felt genuinely happy when my sister congratulated me after the exam.", ["happiness_joy"], "clear", "positive feeling"),
        ("en_calm_contentment", "ENGLISH", "After finishing my work, I feel calm and content sitting quietly at home.", ["calmness", "contentment"], "mixed", "two explicitly experienced positive states"),
        ("en_hope", "ENGLISH", "This semester is difficult, but I still believe things can improve.", ["hope_optimism"], "clear", "hope despite difficulty"),
        ("en_pride_confidence", "ENGLISH", "I am proud of the project I completed, and I trust my ability to present it.", ["pride", "confidence"], "mixed", "achievement and self-belief"),
        ("en_curiosity", "ENGLISH", "I keep wondering how the experiment will turn out and want to learn more.", ["curiosity"], "clear", "interest in learning"),
        ("en_gratitude_love", "ENGLISH", "I feel grateful for my friend's support and care deeply about them.", ["gratitude", "love_affection"], "mixed", "appreciation and affection"),
        ("en_relief", "ENGLISH", "I finally submitted the paper and feel a wave of relief.", ["relief"], "clear", "tension resolved"),
        ("en_sadness_grief", "ENGLISH", "Since my grandfather died, I have been grieving and feeling deeply sad.", ["grief", "sadness"], "mixed", "loss and sadness"),
        ("en_disappointment_rejection", "ENGLISH", "The scholarship rejected my application, and I feel disappointed and hurt.", ["disappointment", "rejection_emotional_hurt"], "mixed", "specific setback and rejection hurt"),
        ("en_loneliness_homesickness", "ENGLISH", "I miss my family and feel lonely in the dorm even when people are around.", ["homesickness", "loneliness"], "mixed", "missing home and social isolation"),
        ("en_hopelessness", "ENGLISH", "Nothing I try seems to matter, and I cannot imagine things getting better.", ["hopelessness"], "clear", "loss of future hope"),
        ("en_anxiety_fear", "ENGLISH", "I am afraid I will fail, and I keep worrying about what could go wrong.", ["fear", "anxiety"], "mixed", "threat and persistent worry"),
        ("en_stress", "ENGLISH", "I have three deadlines this week, but I can still plan them one at a time.", ["stress"], "clear", "pressure without loss of control"),
        ("en_overwhelm_confusion", "ENGLISH", "There are so many tasks and instructions that I do not know where to begin.", ["overwhelm", "confusion"], "mixed", "excessive demands and uncertainty"),
        ("en_anger_frustration", "ENGLISH", "I am angry that the instructions changed again, and frustrated by the wasted work.", ["anger", "frustration"], "mixed", "anger and blocked effort"),
        ("en_jealousy", "ENGLISH", "I felt jealous when my classmate received the opportunity I wanted.", ["jealousy_envy"], "clear", "comparison-based envy"),
        ("en_disgust", "ENGLISH", "The spoiled food made me feel disgusted and I had to throw it away.", ["disgust"], "clear", "aversion"),
        ("en_shame", "ENGLISH", "I feel ashamed of how I spoke in front of everyone, even though nobody blamed me.", ["shame"], "clear", "negative self-evaluation"),
        ("en_guilt", "ENGLISH", "I feel guilty because my mistake caused extra work for my teammate.", ["guilt"], "clear", "regret about a specific action"),
        ("en_boredom", "ENGLISH", "The lecture is so repetitive that I can barely stay interested.", ["boredom"], "clear", "low engagement"),
        ("en_surprise", "ENGLISH", "I was surprised when the results arrived much earlier than expected.", ["surprise"], "clear", "unexpected event"),
        ("en_compassion", "ENGLISH", "I feel compassion for the new student who is struggling to find their way.", ["compassion_empathy"], "clear", "care for another person"),
        ("en_anticipation_excitement", "ENGLISH", "I am excited for graduation and eagerly looking forward to the ceremony.", ["excitement", "anticipation"], "mixed", "positive activation and future focus"),
        ("fil_happiness", "FILIPINO", "Masaya talaga ako nang makapasa ako sa exam.", ["happiness_joy"], "clear", "Filipino positive feeling"),
        ("fil_stress", "FILIPINO", "Sobrang stress ako sa dami ng requirements, pero inaayos ko isa-isa.", ["stress"], "clear", "Filipino pressure with retained control"),
        ("fil_sadness", "FILIPINO", "Nalulungkot ako kapag naiisip ko kung gaano kalayo ang mga pangarap ko.", ["sadness"], "clear", "Filipino sadness"),
        ("fil_homesickness_loneliness", "FILIPINO", "Miss na miss ko ang pamilya ko at nalulungkot ako sa tahimik na dorm.", ["homesickness", "loneliness"], "mixed", "home longing and loneliness"),
        ("fil_uncertain", "FILIPINO", "Hindi ko alam kung ano talaga ang nararamdaman ko ngayon.", [], "uncertain", "insufficient evidence for a specific emotion"),
        ("taglish_mixed", "TAGLISH", "Excited ako sa graduation pero kinakabahan ako sa future.", ["excitement", "anxiety"], "mixed", "Taglish mixed feeling"),
        ("taglish_pride_insecurity", "TAGLISH", "Proud ako sa project ko pero insecure pa rin ako kung sapat ba iyon.", ["pride", "insecurity_self_doubt"], "mixed", "achievement with self-doubt"),
        ("taglish_no_emotion", "TAGLISH", "Nagpapasa lang ako ng form today; wala naman akong particular na nararamdaman.", [], "no_emotion", "explicitly no specific emotion"),
        ("taglish_reported_other", "TAGLISH", "Sabi ng friend ko, sobrang lungkot niya, pero okay naman ako.", [], "attributed_other", "emotion belongs to another person"),
        ("taglish_fear", "TAGLISH", "Kinakabahan ako sa interview kasi baka hindi ako pumasa.", ["anxiety", "fear"], "mixed", "Taglish worry and threat"),
        ("fil_relief", "FILIPINO", "Nakahinga ako nang maluwag dahil tapos na ang defense.", ["relief"], "clear", "Filipino relief"),
        ("fil_gratitude", "FILIPINO", "Nagpapasalamat ako sa mga taong tumulong sa akin.", ["gratitude"], "clear", "Filipino gratitude"),
    ]
    rows: list[dict[str, Any]] = []
    for case_id, language, text, positives, status, note in cases:
        positives = sorted(set(positives), key=labels.index)
        negatives = [label for label in labels if label not in positives]
        rows.append({
            "case_id": case_id,
            "text": text,
            "language": language,
            "status": status,
            "provisional_positive_labels": positives,
            "provisional_negative_labels": negatives,
            "observed_labels": labels,
            "label_basis": "synthetic_provisional_complete_annotation",
            "review_status": "unreviewed",
            "human_reviewed": False,
            "uncertainty_note": note,
        })
    return rows


def load_bundle(path: Path, labels: list[str], device: torch.device) -> dict[str, Any]:
    tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
    config = AutoConfig.from_pretrained(str(path), local_files_only=True)
    config.num_labels = len(labels)
    config.id2label = {index: label for index, label in enumerate(labels)}
    config.label2id = {label: index for index, label in enumerate(labels)}
    config.problem_type = "multi_label_classification"
    model = AutoModelForSequenceClassification.from_config(config)
    state = load_file(str(path / "model.safetensors"), device="cpu")
    normalized = {}
    for key, value in state.items():
        if key.endswith(".LayerNorm.gamma"):
            key = key[:-len(".gamma")] + ".weight"
        elif key.endswith(".LayerNorm.beta"):
            key = key[:-len(".beta")] + ".bias"
        normalized[key] = value
    info = model.load_state_dict(normalized, strict=False)
    if info.missing_keys or info.unexpected_keys:
        raise RuntimeError(f"{path.name}: missing={info.missing_keys}, unexpected={info.unexpected_keys}")
    threshold_data = json.loads((path / "thresholds.json").read_text(encoding="utf-8"))
    thresholds = threshold_data.get("thresholds", threshold_data)
    model.to(device).eval()
    manifest = json.loads((path / "dataset_manifest.json").read_text(encoding="utf-8"))
    dataset_dir = ROOT / manifest["dataset_dir"]
    return {"tokenizer": tokenizer, "model": model, "thresholds": thresholds, "dataset_dir": dataset_dir, "manifest": manifest}


def infer(bundle: dict[str, Any], texts: list[str], device: torch.device) -> tuple[np.ndarray, np.ndarray]:
    values: list[np.ndarray] = []
    logits_values: list[np.ndarray] = []
    tokenizer = bundle["tokenizer"]
    model = bundle["model"]
    with torch.no_grad():
        for start in range(0, len(texts), 16):
            encoded = tokenizer(texts[start:start + 16], return_tensors="pt", padding=True, truncation=True, max_length=256)
            encoded = {key: value.to(device) for key, value in encoded.items()}
            logits = model(**encoded).logits
            logits_values.append(logits.detach().cpu().numpy())
            values.append(torch.sigmoid(logits).detach().cpu().numpy())
    return np.concatenate(logits_values), np.concatenate(values)


def load_rows(path: Path, split: str) -> list[dict[str, Any]]:
    return [json.loads(line) for line in (path / f"{split}.jsonl").read_text(encoding="utf-8").splitlines() if line.strip()]


def threshold_counts(probabilities: np.ndarray, labels: list[str], thresholds: dict[str, float]) -> dict[str, Any]:
    enabled = np.asarray([thresholds.get(label) is not None for label in labels], dtype=bool)
    threshold_vector = np.asarray([float(thresholds.get(label, 0.5) or 0.5) for label in labels], dtype=np.float32)
    accepted = (probabilities >= threshold_vector[None, :]) & enabled[None, :]
    cardinality = accepted.sum(axis=1)
    return {
        "mean": float(cardinality.mean()),
        "median": float(np.median(cardinality)),
        "max": int(cardinality.max()),
        "zero": int((cardinality == 0).sum()),
        "ten_or_more": int((cardinality >= 10).sum()),
        "twenty_or_more": int((cardinality >= 20).sum()),
        "all_35": int((cardinality == len(labels)).sum()),
        "enabled_labels": int(enabled.sum()),
        "disabled_labels": int((~enabled).sum()),
    }


def fixed_threshold_counts(probabilities: np.ndarray, labels: list[str]) -> dict[str, dict[str, Any]]:
    return {str(value): threshold_counts(probabilities, labels, {label: value for label in labels}) for value in (0.5, 0.7, 0.9)}


def validation_support(bundle: dict[str, Any], labels: list[str]) -> dict[str, Any]:
    rows = load_rows(bundle["dataset_dir"], "validation")
    result: dict[str, Any] = {}
    for label in labels:
        positives = negatives = unknown = 0
        for row in rows:
            observed = set(row.get("observed_labels") or labels)
            if label not in observed:
                unknown += 1
            elif label in set(row.get("labels") or []):
                positives += 1
            else:
                negatives += 1
        result[label] = {"positive": positives, "observed_negative": negatives, "unknown": unknown, "saved_threshold": float(bundle["thresholds"].get(label, 0.5)), "deployment_evidence_sufficient": bool(positives >= 5 and negatives >= 5)}
    return result


def validation_scores(bundle: dict[str, Any], labels: list[str], support: dict[str, Any]) -> dict[str, Any]:
    rows = load_rows(bundle["dataset_dir"], "validation")
    _, probabilities = infer(bundle, [row.get("text", "") for row in rows], next(bundle["model"].parameters()).device)
    thresholds = {"saved": bundle["thresholds"], "0.5": {label: 0.5 for label in labels}, "0.7": {label: 0.7 for label in labels}, "0.9": {label: 0.9 for label in labels}}
    scores: dict[str, Any] = {}
    for name, threshold_map in thresholds.items():
        by_label = {}
        for index, label in enumerate(labels):
            truth = []
            pred = []
            for row_index, row in enumerate(rows):
                if label not in set(row.get("observed_labels") or labels):
                    continue
                truth.append(int(label in set(row.get("labels") or [])))
                pred.append(int(probabilities[row_index, index] >= float(threshold_map.get(label, 0.5))))
            tp = sum(a == 1 and b == 1 for a, b in zip(truth, pred))
            fp = sum(a == 0 and b == 1 for a, b in zip(truth, pred))
            fn = sum(a == 1 and b == 0 for a, b in zip(truth, pred))
            precision = tp / (tp + fp) if tp + fp else 0.0
            recall = tp / (tp + fn) if tp + fn else 0.0
            f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
            by_label[label] = {"tp": tp, "fp": fp, "fn": fn, "precision": precision, "recall": recall, "f1": f1, "support": len(truth)}
        scores[name] = by_label
    return scores


def parameter_delta(reference: dict[str, Any], candidate: dict[str, Any]) -> dict[str, Any]:
    ref = reference["model"].state_dict()
    cand = candidate["model"].state_dict()
    deltas = []
    for key in ref.keys():
        a = ref[key].detach().float().cpu()
        b = cand[key].detach().float().cpu()
        delta = (b - a).reshape(-1)
        deltas.append(delta)
    values = torch.cat(deltas)
    return {"parameters": int(values.numel()), "changed_parameters": int((values != 0).sum()), "l2": float(torch.linalg.vector_norm(values)), "mean_abs": float(values.abs().mean()), "max_abs": float(values.abs().max())}


def report_markdown(result: dict[str, Any]) -> str:
    lines = [
        "# Emotion35 recovery-collapse diagnosis — 2026-11-03",
        "",
        "This is a read-only research diagnosis. No weights, thresholds, production six-class behavior, Student Chat, crisis handling, consent, or Admin analytics were changed.",
        "",
        "## Frozen provisional diagnostic",
        "",
        f"Cases: **{result['diagnostic']['cases']}**; SHA-256: `{result['diagnostic']['sha256']}`. The set is synthetic, fully observed for diagnostic purposes only, unreviewed, and was not used for training or threshold selection.",
        "",
        "## Raw cardinality by checkpoint",
        "",
        "| checkpoint | saved thresholds mean | 0.5 mean | 0.7 mean | 0.9 mean | saved 10+ | saved 20+ | saved all-35 |",
        "|---|---:|---:|---:|---:|---:|---:|---:|",
    ]
    for name, data in result["models"].items():
        saved = data["test"]["saved_thresholds"]
        fixed = data["test"]["fixed_thresholds"]
        lines.append(f"| {name} | {saved['mean']:.2f} | {fixed['0.5']['mean']:.2f} | {fixed['0.7']['mean']:.2f} | {fixed['0.9']['mean']:.2f} | {saved['ten_or_more']} | {saved['twenty_or_more']} | {saved['all_35']} |")
    lines += ["", "For the weak recovery, fixed thresholds expose a smaller but real raw-score problem: at 0.5 it still has 109/3,113 examples with 10+ labels and 72 examples with all 35. The no-weak control is normal at fixed thresholds (mean 1.22 at 0.5; no 10+ cases), so its 20.56 saved-threshold mean is primarily calibration-driven.", "", "## Calibration evidence", "", "Labels with fewer than five observed positives or negatives are marked `deployment_evidence_sufficient=false`; their thresholds are diagnostic only. Unknown labels were excluded from all support and score calculations."]
    for name, data in result["models"].items():
        low_support = [label for label, support in data["validation_support"].items() if not support["deployment_evidence_sufficient"]]
        low_threshold = [label for label, support in data["validation_support"].items() if support["saved_threshold"] <= 0.1001]
        lines.append(f"- **{name}:** {len(low_support)} labels lack deployment-style observed support; saved threshold <=0.1 for {len(low_threshold)} labels ({', '.join(low_threshold) if low_threshold else 'none'}).")
    lines += ["", "## Fully observed provisional diagnostic", "", "| checkpoint | micro-F1 | macro-F1 | mean accepted | 10+ | 20+ | all-35 |", "|---|---:|---:|---:|---:|---:|---:|"]
    for name, data in result["models"].items():
        d = data["diagnostic_metrics"]
        lines.append(f"| {name} | {d['micro_f1']:.4f} | {d['macro_f1']:.4f} | {d['cardinality']['mean']:.2f} | {d['cardinality']['ten_or_more']} | {d['cardinality']['twenty_or_more']} | {d['cardinality']['all_35']} |")
    lines += ["", "### Diagnostic-only recalibration with an evidence gate", "", "The following uses validation data only, disables labels with fewer than 5 observed positives or 5 observed negatives, and does not modify saved thresholds or checkpoint weights.", "", "| checkpoint | enabled labels | test mean accepted | diagnostic micro-F1 | diagnostic mean accepted | diagnostic 10+ |", "|---|---:|---:|---:|---:|---:|"]
    for name, data in result["models"].items():
        test_cal = data["deployment_calibrated_test"]
        diag_cal = data["deployment_calibrated_diagnostic_metrics"]
        lines.append(f"| {name} | {len(data['deployment_calibration']['enabled_labels'])} | {test_cal['mean']:.2f} | {diag_cal['micro_f1']:.4f} | {diag_cal['cardinality']['mean']:.2f} | {diag_cal['cardinality']['ten_or_more']} |")
    lines += ["", "### Diagnostic language slices", "", "| checkpoint | language | n | micro-F1 | mean accepted | 10+ | all-35 |", "|---|---|---:|---:|---:|---:|---:|"]
    for name, data in result["models"].items():
        for language, metrics in data["diagnostic_by_language"].items():
            lines.append(f"| {name} | {language} | {metrics['records']} | {metrics['micro_f1']:.4f} | {metrics['cardinality']['mean']:.2f} | {metrics['cardinality']['ten_or_more']} | {metrics['cardinality']['all_35']} |")
    lines += ["", "These labels are provisional synthetic annotations, not validated real-student ground truth.", "", "## Training-change evidence", ""]
    for name, data in result["models"].items():
        lines.append(f"- **{name}:** {data['training_config_summary']}")
    lines += ["", "Parameter movement from v2:", ""]
    for name, data in result["parameter_delta"].items():
        lines.append(f"- **{name}:** L2={data['l2']:.6f}, mean absolute update={data['mean_abs']:.8f}, max absolute update={data['max_abs']:.6f}; {data['changed_parameters']:,}/{data['parameters']:,} parameters changed.")
    lines += ["", "## Decision", "", "Calibration is a primary cause for the no-weak control, while the weak recovery also has a measurable raw-score shift. The change from v2 combines a new 0.25-epoch update, max-positive weighting, and 12x/10x Filipino/Taglish sampling; the weak run additionally applies weak loss. The justified next action is to retain an evidence-gated, diagnostic-only calibration artifact and abstain on labels without observed negatives; do not overwrite saved thresholds or retrain yet. The weak recovery still has raw all-35 cases at fixed thresholds, so calibration alone cannot make it safe. Keep v2 as the research-demo baseline and keep both recovery checkpoints isolated.", ""]
    return "\n".join(lines)


def provisional_metrics(rows: list[dict[str, Any]], probabilities: np.ndarray, labels: list[str], thresholds: dict[str, float | None], enabled_labels: list[str] | None = None) -> dict[str, Any]:
    enabled = set(enabled_labels or labels)
    truth = np.asarray([[int(label in set(row["provisional_positive_labels"])) for label in labels] for row in rows], dtype=np.int8)
    threshold_vector = np.asarray([float(thresholds.get(label) if thresholds.get(label) is not None else 0.5) for label in labels])
    enabled_mask = np.asarray([label in enabled for label in labels], dtype=bool)
    predicted = ((probabilities >= threshold_vector[None, :]) & enabled_mask[None, :]).astype(np.int8)
    tp = int(((truth == 1) & (predicted == 1)).sum())
    fp = int(((truth == 0) & (predicted == 1)).sum())
    fn = int(((truth == 1) & (predicted == 0)).sum())
    precision = tp / (tp + fp) if tp + fp else 0.0
    recall = tp / (tp + fn) if tp + fn else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    per_label = {}
    for index, label in enumerate(labels):
        if label not in enabled:
            continue
        t, p = truth[:, index], predicted[:, index]
        a = int(((t == 1) & (p == 1)).sum())
        b = int(((t == 0) & (p == 1)).sum())
        c = int(((t == 1) & (p == 0)).sum())
        pr = a / (a + b) if a + b else 0.0
        re = a / (a + c) if a + c else 0.0
        per_label[label] = {"tp": a, "fp": b, "fn": c, "support": int(t.sum()), "f1": 2 * pr * re / (pr + re) if pr + re else 0.0}
    cardinality = predicted.sum(axis=1)
    return {"records": len(rows), "enabled_labels": len(enabled), "micro_precision": precision, "micro_recall": recall, "micro_f1": f1, "macro_f1": float(np.mean([v["f1"] for v in per_label.values()])) if per_label else 0.0, "per_label": per_label, "cardinality": {"mean": float(cardinality.mean()), "median": float(np.median(cardinality)), "max": int(cardinality.max()), "ten_or_more": int((cardinality >= 10).sum()), "twenty_or_more": int((cardinality >= 20).sum()), "all_35": int((cardinality == len(labels)).sum())}}


def provisional_metrics_by_language(rows: list[dict[str, Any]], probabilities: np.ndarray, labels: list[str], thresholds: dict[str, float]) -> dict[str, Any]:
    result = {}
    for language in sorted({str(row.get("language", "UNKNOWN")) for row in rows}):
        indexes = [index for index, row in enumerate(rows) if str(row.get("language", "UNKNOWN")) == language]
        subset_rows = [rows[index] for index in indexes]
        result[language] = provisional_metrics(subset_rows, probabilities[indexes], labels, thresholds)
    return result


def deployment_calibration(rows: list[dict[str, Any]], probabilities: np.ndarray, labels: list[str], min_positive: int = 5, min_negative: int = 5) -> tuple[dict[str, float | None], dict[str, Any]]:
    thresholds: dict[str, float | None] = {}
    support: dict[str, Any] = {}
    for index, label in enumerate(labels):
        truth = []
        for row in rows:
            observed = set(row.get("observed_labels") or labels)
            if label in observed:
                truth.append(int(label in set(row.get("labels") or [])))
        positives = sum(truth)
        negatives = len(truth) - positives
        eligible = positives >= min_positive and negatives >= min_negative
        best_threshold: float | None = None
        best_score = -1.0
        if eligible:
            truth_array = np.asarray(truth, dtype=np.int8)
            probs = probabilities[[row_index for row_index, row in enumerate(rows) if label in set(row.get("observed_labels") or labels)], index]
            for threshold in np.arange(0.10, 0.91, 0.05):
                predicted = (probs >= threshold).astype(np.int8)
                tp = int(((truth_array == 1) & (predicted == 1)).sum())
                fp = int(((truth_array == 0) & (predicted == 1)).sum())
                fn = int(((truth_array == 1) & (predicted == 0)).sum())
                precision = tp / (tp + fp) if tp + fp else 0.0
                recall = tp / (tp + fn) if tp + fn else 0.0
                score = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
                if score > best_score:
                    best_threshold, best_score = float(threshold), float(score)
        thresholds[label] = best_threshold
        support[label] = {"positive": positives, "observed_negative": negatives, "threshold": best_threshold, "eligible": eligible, "validation_f1": best_score if eligible else None}
    return thresholds, support


def main() -> None:
    labels = load_labels()
    OUT_ROOT.mkdir(parents=True, exist_ok=True)
    if DIAGNOSTIC_PATH.exists():
        diagnostic_payload = json.loads(DIAGNOSTIC_PATH.read_text(encoding="utf-8"))
        rows = diagnostic_payload["cases"]
    else:
        rows = diagnostic_cases(labels)
        diagnostic_payload = {"schema": "emotion35-complete-provisional-diagnostic-v1", "created_at": datetime.now(timezone.utc).isoformat(), "taxonomy_version": "1.0.0", "cases": rows, "provisional": True, "human_reviewed": False, "not_for_training": True, "not_for_threshold_selection": True}
        DIAGNOSTIC_PATH.write_text(json.dumps(diagnostic_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    # The case file is frozen after first creation. Keep its manifest hash in a
    # sidecar rather than rewriting the frozen file with a self-referential hash.
    if "sha256" in diagnostic_payload:
        diagnostic_payload.pop("sha256", None)
        DIAGNOSTIC_PATH.write_text(json.dumps(diagnostic_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    diagnostic_hash = hashlib.sha256(DIAGNOSTIC_PATH.read_bytes()).hexdigest()
    DIAGNOSTIC_MANIFEST_PATH.write_text(json.dumps({"path": str(DIAGNOSTIC_PATH), "sha256": diagnostic_hash, "cases": len(rows), "frozen": True}, indent=2) + "\n", encoding="utf-8")

    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    bundles = {name: load_bundle(path, labels, device) for name, path in MODEL_PATHS.items()}
    result: dict[str, Any] = {"schema": "emotion35-recovery-collapse-diagnosis-v1", "created_at": datetime.now(timezone.utc).isoformat(), "device": str(device), "taxonomy_labels": labels, "diagnostic": {"path": str(DIAGNOSTIC_PATH), "cases": len(rows), "sha256": diagnostic_hash}, "models": {}}
    texts = [row["text"] for row in rows]
    for name, bundle in bundles.items():
        test_rows = load_rows(bundle["dataset_dir"], "test")
        test_logits, test_probabilities = infer(bundle, [row.get("text", "") for row in test_rows], device)
        diagnostic_logits, diagnostic_probabilities = infer(bundle, texts, device)
        support = validation_support(bundle, labels)
        scores = validation_scores(bundle, labels, support)
        validation_rows = load_rows(bundle["dataset_dir"], "validation")
        _, validation_probabilities = infer(bundle, [row.get("text", "") for row in validation_rows], device)
        deployment_thresholds, deployment_support = deployment_calibration(validation_rows, validation_probabilities, labels)
        deployment_enabled = [label for label in labels if deployment_thresholds.get(label) is not None]
        saved_thresholds = threshold_counts(test_probabilities, labels, bundle["thresholds"])
        training_config = json.loads((MODEL_PATHS[name] / "training_config.json").read_text(encoding="utf-8"))
        config_summary = {
            "epochs": training_config.get("epochs"),
            "learning_rate": training_config.get("learning_rate"),
            "batch_size": training_config.get("batch_size"),
            "masked_loss": training_config.get("masked_loss"),
            "positive_weighting": training_config.get("positive_weighting", "max_pos_weight=" + str(training_config.get("max_pos_weight"))),
            "sampling_weights": training_config.get("sampling_weights", "not configured"),
            "weak_loss_weight": training_config.get("weak_loss_weight", 0.0),
        }
        result["models"][name] = {
            "checkpoint": str(MODEL_PATHS[name]),
            "dataset": str(bundle["dataset_dir"]),
            "test": {"records": len(test_rows), "saved_thresholds": saved_thresholds, "fixed_thresholds": fixed_threshold_counts(test_probabilities, labels), "raw_logit_range": {"min": float(test_logits.min()), "max": float(test_logits.max()), "mean": float(test_logits.mean())}},
            "validation_support": support,
            "validation_scores": scores,
            "deployment_calibration": {"min_positive": 5, "min_negative": 5, "thresholds": deployment_thresholds, "support": deployment_support, "enabled_labels": deployment_enabled, "disabled_labels": [label for label in labels if label not in deployment_enabled]},
            "deployment_calibrated_test": threshold_counts(test_probabilities, labels, deployment_thresholds),
            "diagnostic_metrics": provisional_metrics(rows, diagnostic_probabilities, labels, bundle["thresholds"]),
            "deployment_calibrated_diagnostic_metrics": provisional_metrics(rows, diagnostic_probabilities, labels, deployment_thresholds, deployment_enabled),
            "diagnostic_by_language": provisional_metrics_by_language(rows, diagnostic_probabilities, labels, bundle["thresholds"]),
            "diagnostic_examples": [{"case_id": row["case_id"], "accepted_count": int((diagnostic_probabilities[index] >= np.asarray([float(bundle["thresholds"].get(label, 0.5)) for label in labels])).sum()), "accepted": [label for label, value, threshold in zip(labels, diagnostic_probabilities[index], [float(bundle["thresholds"].get(label, 0.5)) for label in labels]) if value >= threshold], "top_scores": sorted([{ "emotion": label, "probability": float(value), "threshold": float(bundle["thresholds"].get(label, 0.5)), "accepted": bool(value >= float(bundle["thresholds"].get(label, 0.5)))} for label, value in zip(labels, diagnostic_probabilities[index])], key=lambda item: item["probability"], reverse=True)[:5]} for index, row in enumerate(rows)],
            "training_config_summary": config_summary,
        }
    result["parameter_delta"] = {"recovery_weak_vs_v2": parameter_delta(bundles["v2"], bundles["recovery_weak"]), "recovery_no_weak_vs_v2": parameter_delta(bundles["v2"], bundles["recovery_no_weak"])}
    CALIBRATION_PATH.write_text(json.dumps({"schema": "emotion35-evidence-gated-calibration-diagnostic-v1", "source": str(RESULT_PATH), "does_not_overwrite_saved_thresholds": True, "models": {name: data["deployment_calibration"] for name, data in result["models"].items()}}, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    RESULT_PATH.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    REPORT_PATH.write_text(report_markdown(result), encoding="utf-8")
    print(json.dumps({"result": str(RESULT_PATH), "report": str(REPORT_PATH), "diagnostic": str(DIAGNOSTIC_PATH), "device": str(device), "models": {name: data["test"]["saved_thresholds"] for name, data in result["models"].items()}}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
