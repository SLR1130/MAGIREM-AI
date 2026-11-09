"""Local-only interactive adviser demo for the Emotion35 research models.

The server binds to loopback, loads the unchanged six-class production model
plus the isolated v2/v3/v4 research checkpoints, and never touches Laravel,
Student Chat, databases, or Admin analytics. V2 is the default comparison;
v4 is retained only as quarantined raw diagnostic evidence after its
all-positive/domain-collapse failures. It is intentionally a small
standard-library HTTP server so the demo has no new runtime dependency.
"""
from __future__ import annotations

import argparse
import csv
import html
import json
import threading
import time
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer
from emotion35_calibration import accepted_predictions, calibration_evidence_summary, cardinality_warning, load_calibration_artifact

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config/emotion_taxonomy.json"
PRODUCTION = ROOT / "ml_service/emotion_model_multilingual_deployable"
V2 = ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v2_20261026"
V3 = ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v3_20261026"
V4 = ROOT / "storage/app/audit-recovery/emotion_model_35_multilabel_prototype_v4_weak_20261103"
RESEARCH_CHECKPOINTS = (("v2", V2), ("v3", V3), ("v4", V4))
CALIBRATION_DIR = ROOT / "storage/app/audit-recovery/emotion35_calibrations_20261104_final"
CALIBRATION_ARTIFACTS = {
    "v2": CALIBRATION_DIR / "v2_calibration.json",
    "v3": CALIBRATION_DIR / "v3_calibration.json",
    "v4": CALIBRATION_DIR / "v4_quarantined_calibration.json",
}
DEFAULT_RESEARCH_CHECKPOINT = "v2"
V4_QUARANTINE_CARDINALITY = 10
EVALUATION_CSV = ROOT / "storage/app/audit-recovery/emotion35_adviser_evaluation.csv"
PROMPT_BANK = ROOT / "storage/app/audit-recovery/emotion35_adviser_prompt_bank.json"


def _load_taxonomy():
    data = json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))
    emotions = data["emotions"]
    labels = [item["key"] for item in emotions]
    categories = {item["key"]: item.get("category_key", "unclassified") for item in emotions}
    return labels, categories, str(data["version"])


class EmotionModels:
    def __init__(self, device_name: str = "auto"):
        self.labels, self.categories, self.taxonomy_version = _load_taxonomy()
        if device_name == "auto":
            self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        else:
            self.device = torch.device(device_name)
        self._lock = threading.Lock()
        self.production_tokenizer = AutoTokenizer.from_pretrained(str(PRODUCTION), local_files_only=True)
        self.production_model = AutoModelForSequenceClassification.from_pretrained(str(PRODUCTION), local_files_only=True)
        self.production_model.to(self.device).eval()
        self.research = {}
        for name, path in RESEARCH_CHECKPOINTS:
            if not path.exists():
                continue
            tokenizer = AutoTokenizer.from_pretrained(str(path), local_files_only=True)
            model = AutoModelForSequenceClassification.from_pretrained(str(path), local_files_only=True)
            if int(getattr(model.config, "num_labels", 0)) != len(self.labels):
                raise RuntimeError(f"{name} checkpoint output dimension does not match taxonomy")
            configured_labels = model.config.id2label or {}
            if configured_labels:
                configured_order = [configured_labels.get(index, configured_labels.get(str(index))) for index in range(len(self.labels))]
                if configured_order != self.labels:
                    raise RuntimeError(f"{name} checkpoint label mapping does not match approved taxonomy order")
            model.to(self.device).eval()
            calibration = load_calibration_artifact(
                CALIBRATION_ARTIFACTS[name], self.labels,
                taxonomy_version=self.taxonomy_version, checkpoint_path=path,
            )
            self.research[name] = {
                "tokenizer": tokenizer,
                "model": model,
                "calibration": calibration,
                "evidence_quality": calibration_evidence_summary(calibration),
                "path": str(path),
                "quarantined": name == "v4",
            }

    @staticmethod
    def _sigmoid(logits):
        return torch.sigmoid(logits).detach().cpu().tolist()

    def predict(self, text: str) -> dict:
        text = text.strip()
        if not text:
            raise ValueError("Enter a sentence first.")
        with self._lock, torch.no_grad():
            encoded = self.production_tokenizer(text, return_tensors="pt", truncation=True, max_length=256)
            encoded = {key: value.to(self.device) for key, value in encoded.items()}
            production_logits = self.production_model(**encoded).logits[0]
            production_probs = torch.softmax(production_logits, dim=-1).detach().cpu().tolist()
            id2label = self.production_model.config.id2label or {}
            production_labels = []
            for index in range(len(production_probs)):
                label = id2label.get(index, id2label.get(str(index)))
                if not isinstance(label, str) or not label.strip():
                    raise RuntimeError(f"Production checkpoint has no valid label mapping for class ID {index}.")
                production_labels.append(label.strip())
            broad = sorted(
                [
                    {
                        "class_id": index,
                        "emotion": label,
                        "probability": float(probability),
                    }
                    for index, (label, probability) in enumerate(zip(production_labels, production_probs))
                ],
                key=lambda item: item["probability"], reverse=True,
            )
            detailed = {}
            for name, item in self.research.items():
                tokenizer = item["tokenizer"]
                research_encoded = tokenizer(text, return_tensors="pt", truncation=True, max_length=256)
                research_encoded = {key: value.to(self.device) for key, value in research_encoded.items()}
                probabilities = self._sigmoid(item["model"](**research_encoded).logits[0])
                scored = accepted_predictions(probabilities, self.labels, item["calibration"])
                for value in scored:
                    value["category"] = self.categories[value["emotion"]]
                scored.sort(key=lambda value: value["probability"], reverse=True)
                accepted = [value for value in scored if value["accepted"]]
                quarantine_triggered = name == "v4"
                cardinality = cardinality_warning(len(accepted), V4_QUARANTINE_CARDINALITY)
                collapse_signal = len(accepted) >= V4_QUARANTINE_CARDINALITY
                detailed[name] = {
                    "status": "quarantined_all_positive_collapse" if quarantine_triggered and collapse_signal else ("quarantined_checkpoint" if quarantine_triggered else ("excessive_prediction_cardinality" if collapse_signal else ("accepted" if accepted else "uncertain_or_no_confident_prediction"))),
                    "primary": accepted[0] if accepted else None,
                    "secondary": accepted[1:] if len(accepted) > 1 else [],
                    "accepted": accepted,
                    "accepted_count": len(accepted),
                    "quarantined": quarantine_triggered,
                    "collapse_signal": collapse_signal,
                    "cardinality_warning": cardinality,
                    "calibrated_label_count": sum(value["calibration_status"] == "calibrated" for value in scored),
                    "uncalibrated_label_count": sum(value["calibration_status"] == "uncalibrated" for value in scored),
                    "evidence_quality": item["evidence_quality"],
                    "quarantine_warning": (
                        f"v4 accepted {len(accepted)} of {len(self.labels)} labels; raw output is diagnostic only and is not a valid emotion list."
                        + (f" This exceeds the {V4_QUARANTINE_CARDINALITY}-label collapse warning threshold." if collapse_signal else "")
                        if quarantine_triggered else ""
                    ),
                    "top_predictions": scored[:5],
                    "checkpoint": item["path"],
                }
        return {
            "text": text,
            "broad_six_class": {
                "prediction": broad[0],
                "top_predictions": broad[:3],
                "all_predictions": broad,
                "model": str(PRODUCTION),
            },
            "detailed_research": detailed,
            "notice": "Research Prototype — Not Yet Validated. v2 is the default research comparison. v4 is quarantined after all-positive prediction failures; raw v4 output is diagnostic only and is never shown to students.",
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "device": str(self.device),
        }


def _ensure_csv():
    fields = [
        "timestamp_utc", "test_sentence", "expected_emotions", "broad_prediction",
        "v2_predictions", "v3_predictions", "v4_predictions", "correctness",
        "appropriateness", "reviewer_comments", "review_status",
    ]
    EVALUATION_CSV.parent.mkdir(parents=True, exist_ok=True)
    if not EVALUATION_CSV.exists():
        with EVALUATION_CSV.open("w", newline="", encoding="utf-8-sig") as stream:
            csv.DictWriter(stream, fieldnames=fields).writeheader()
        return

    # Preserve previous adviser records while adding the v4 column when the
    # existing local research CSV predates the v4 checkpoint.
    with EVALUATION_CSV.open("r", newline="", encoding="utf-8-sig") as stream:
        reader = csv.DictReader(stream)
        existing_fields = reader.fieldnames or []
        if all(field in existing_fields for field in fields):
            return
        rows = list(reader)
    merged_fields = list(existing_fields)
    for field in fields:
        if field not in merged_fields:
            merged_fields.append(field)
    with EVALUATION_CSV.open("w", newline="", encoding="utf-8-sig") as stream:
        writer = csv.DictWriter(stream, fieldnames=merged_fields)
        writer.writeheader()
        writer.writerows(rows)


def _render_score_row(item: dict) -> str:
    threshold = f'{item["threshold"]:.2f}' if item["threshold"] is not None else "uncalibrated"
    decision = "yes" if item["accepted"] else ("uncalibrated" if item["calibration_status"] == "uncalibrated" else "no")
    return (
        f'<tr><td>{html.escape(item["emotion"])}</td><td>{html.escape(item["category"])}</td><td>{html.escape(item.get("evidence_quality", "evidence_not_recorded"))}</td>'
        f'<td>{item["probability"]:.3f}</td><td>{threshold}</td><td>{decision}</td></tr>'
    )


def _render_prediction(result: dict | None, error: str = "") -> str:
    if error:
        return f'<div class="error">{html.escape(error)}</div>'
    if not result:
        return '<p class="muted">Enter a message to compare the production model and available Emotion35 research checkpoints.</p>'
    broad = result["broad_six_class"]
    blocks = [f'<section class="result"><h2>Six-class production model</h2><p><strong>{html.escape(broad["prediction"]["emotion"])}</strong> · {broad["prediction"]["probability"]:.3f}</p><small>Authoritative production signal; unchanged and separate from the research models.</small></section>']
    for name, data in result["detailed_research"].items():
        if data.get("quarantined"):
            raw_labels = ", ".join(x["emotion"] for x in data.get("accepted", [])) or "none"
            blocks.append(
                f'<section class="result quarantine"><h2>Emotion35 {html.escape(name.upper())} checkpoint — QUARANTINED</h2>'
                f'<p class="error"><strong>Do not interpret this as detected student emotion.</strong> {html.escape(data.get("quarantine_warning", ""))}</p>'
                f'<details><summary>Raw diagnostic output ({data.get("accepted_count", 0)} accepted labels)</summary>'
                f'<p class="muted">{html.escape(raw_labels)}</p></details>'
                f'<p class="muted">Checkpoint retained for failure analysis: {html.escape(data["checkpoint"])}</p></section>'
            )
            continue
        if data["primary"] is None:
            primary = '<span class="uncertain">Uncertain / no confident prediction</span>'
        else:
            primary = f'<strong>{html.escape(data["primary"]["emotion"])}</strong> · {data["primary"]["probability"]:.3f} (threshold {data["primary"]["threshold"]:.2f})'
        rows = "".join(_render_score_row(x) for x in data["top_predictions"])
        label = "v4 (weak-supervision research)" if name == "v4" else ("v2 (default research comparison)" if name == DEFAULT_RESEARCH_CHECKPOINT else name.upper())
        cardinality_notice = (
            f'<p class="error">High prediction cardinality: {data["accepted_count"]} labels passed their calibrated thresholds. Raw outputs are shown; no labels were hidden.</p>'
            if data["cardinality_warning"]["warning"] else ""
        )
        quality = data.get("evidence_quality", {})
        quality_counts = quality.get("label_counts_by_quality", {})
        quality_note = (
            "Calibration evidence: "
            + ", ".join(f'{count} {name.replace("_", " ")}' for name, count in sorted(quality_counts.items()))
            + f'; independent evaluation examples: {quality.get("independently_evaluated_validation_records", 0)}. '
            + "Weak and AI-assisted evidence is provisional, not independently validated."
        )
        calibration_note = f'<p class="muted">{data["calibrated_label_count"]} labels calibrated; {data["uncalibrated_label_count"]} uncalibrated labels abstain and cannot be accepted.</p><p class="muted">{html.escape(quality_note)}</p>'
        blocks.append(f'<section class="result"><h2>Emotion35 {html.escape(label)} checkpoint</h2>{cardinality_notice}{calibration_note}<p>Primary: {primary}</p><p>Possible secondary: {html.escape(", ".join(x["emotion"] for x in data["secondary"]) or "none")}</p><p class="muted">Checkpoint: {html.escape(data["checkpoint"])}</p><table><tr><th>Emotion</th><th>Category</th><th>Evidence quality</th><th>Score</th><th>Threshold</th><th>Accepted</th></tr>{rows}</table></section>')
    return "".join(blocks)


class Handler(BaseHTTPRequestHandler):
    models: EmotionModels | None = None

    def _respond(self, body: str, status: int = 200):
        payload = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers(); self.wfile.write(payload)

    def do_GET(self):
        query = parse_qs(urlparse(self.path).query)
        text = query.get("text", [""])[0]
        result = error = ""
        if text:
            try: result = self.models.predict(text)  # type: ignore[union-attr]
            except Exception as exc: error = str(exc)
        prompt_options = ""
        if PROMPT_BANK.exists():
            for row in json.loads(PROMPT_BANK.read_text(encoding="utf-8")):
                prompt_options += f'<option value="{html.escape(row["text"], quote=True)}">{html.escape(row["prompt_id"])} · {html.escape(row["language"])}</option>'
        page = f'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Emotion35 Adviser Demo</title><style>
body{{font-family:system-ui,sans-serif;background:#f5faf7;color:#173f34;margin:0}}main{{max-width:1120px;margin:0 auto;padding:28px 18px}}header,.result,.review{{background:#fff;border:1px solid #d8e8df;border-radius:16px;padding:20px;margin:14px 0;box-shadow:0 8px 24px #174d3210}}h1{{margin:0 0 6px}}h2{{font-size:1.05rem;margin:0 0 8px}}.notice{{background:#fff4d7;border-left:4px solid #d8a62a;padding:12px;border-radius:8px}}textarea{{width:100%;min-height:130px;border:1px solid #b9d1c5;border-radius:10px;padding:12px;font:inherit;box-sizing:border-box}}button{{background:#0f4b3b;color:#fff;border:0;border-radius:9px;padding:11px 16px;font-weight:700;cursor:pointer}}select,input{{border:1px solid #b9d1c5;border-radius:8px;padding:9px;font:inherit;max-width:100%}}table{{width:100%;border-collapse:collapse;font-size:.9rem}}td,th{{border-bottom:1px solid #e4eee8;padding:6px;text-align:left}}.muted{{color:#698278}}.uncertain{{color:#9b6811;font-weight:700}}.error{{background:#ffe7e7;padding:12px;border-radius:8px;color:#8e2020}}.quarantine{{border-color:#e7a8a8;background:#fffafa}}.grid{{display:grid;grid-template-columns:1fr 1fr;gap:14px}}@media(max-width:720px){{.grid{{grid-template-columns:1fr}}main{{padding:16px 10px}}}}
</style></head><body><main><header><h1>Emotion35 Adviser Evaluation Demo</h1><p class="notice"><strong>Research Prototype — Not Yet Validated.</strong> Detailed outputs are experimental, non-diagnostic, and never sent to students, Laravel, Admin analytics, or student records.</p><p class="muted">Local loopback only · device: {html.escape(str(self.models.device if self.models else "unknown"))}</p></header><form method="get"><label for="text"><strong>Enter an independent English, Filipino, or Taglish sentence</strong></label><textarea id="text" name="text" placeholder="Example: Excited ako sa graduation pero kinakabahan ako sa future.">{html.escape(text)}</textarea><p><button type="submit">Compare predictions</button></p><label for="bank">Optional AI-generated prompt bank</label><select id="bank" onchange="document.getElementById('text').value=this.value"><option value="">Choose a prompt…</option>{prompt_options}</select></form>{_render_prediction(result,error)}<section class="review"><h2>Adviser evaluation record</h2><form method="post"><input type="hidden" name="sentence" value="{html.escape(text, quote=True)}"><label>Expected emotion(s), if supplied <input name="expected"></label> <label>Correctness <select name="correctness"><option>pending</option><option>correct</option><option>partially_correct</option><option>incorrect</option></select></label><label>Appropriateness <select name="appropriateness"><option>pending</option><option>appropriate</option><option>needs_review</option><option>inappropriate</option></select></label><p><textarea name="comments" placeholder="Comments or suggested corrections"></textarea></p><button type="submit">Save to local evaluation CSV</button></form><p class="muted">Saved only to {html.escape(str(EVALUATION_CSV))}; this is not a student or production record.</p></section></main></body></html>'''
        self._respond(page)

    def do_POST(self):
        length = int(self.headers.get("Content-Length", "0")); values = parse_qs(self.rfile.read(length).decode("utf-8"))
        _ensure_csv(); sentence = values.get("sentence", [""])[0].strip()
        if not sentence:
            self._respond(_render_prediction(None, "Enter a sentence first."), status=400)
            return
        try:
            result = self.models.predict(sentence)  # type: ignore[union-attr]
        except Exception as exc:
            self._respond(_render_prediction(None, str(exc)), status=503)
            return
        row = {"timestamp_utc": datetime.now(timezone.utc).isoformat(), "test_sentence": sentence, "expected_emotions": values.get("expected", [""])[0], "broad_prediction": json.dumps(result["broad_six_class"] if result else {}, ensure_ascii=False), "v2_predictions": json.dumps(result["detailed_research"].get("v2", {}) if result else {}, ensure_ascii=False), "v3_predictions": json.dumps(result["detailed_research"].get("v3", {}) if result else {}, ensure_ascii=False), "v4_predictions": json.dumps(result["detailed_research"].get("v4", {}) if result else {}, ensure_ascii=False), "correctness": values.get("correctness", ["pending"])[0], "appropriateness": values.get("appropriateness", ["pending"])[0], "reviewer_comments": values.get("comments", [""])[0], "review_status": "unreviewed"}
        with EVALUATION_CSV.open("a", newline="", encoding="utf-8-sig") as stream: csv.DictWriter(stream, fieldnames=row.keys()).writerow(row)
        self.send_response(303); self.send_header("Location", "/?text=" + sentence.replace(" ", "+")); self.end_headers()

    def log_message(self, *_):
        return


def main():
    parser = argparse.ArgumentParser(); parser.add_argument("--host", default="127.0.0.1"); parser.add_argument("--port", type=int, default=8765); parser.add_argument("--device", choices=["auto", "cpu", "cuda"], default="auto"); args = parser.parse_args()
    if args.host not in {"127.0.0.1", "localhost"}: raise SystemExit("This demo intentionally binds to loopback only.")
    _ensure_csv(); Handler.models = EmotionModels(args.device); server = ThreadingHTTPServer((args.host, args.port), Handler); print(f"Emotion35 adviser demo: http://{args.host}:{args.port}/", flush=True); print("Research-only; press Ctrl+C to stop.", flush=True)
    try: server.serve_forever()
    except KeyboardInterrupt: pass
    finally: server.server_close()

if __name__ == "__main__": main()
