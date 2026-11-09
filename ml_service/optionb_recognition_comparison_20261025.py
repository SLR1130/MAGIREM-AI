from __future__ import annotations

import collections
import hashlib
import json
import statistics
import sys
import time
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(Path(__file__).resolve().parent))
import phase2_emotion_feasibility_eval as ev
from emotion_interpreter_schema import predicted_uncertain
from hybrid_response_engine import HybridResponseEngine

DEV = ROOT / "ml_service" / "optionb_recognition_dev_20261025.json"
FINAL = ROOT / "ml_service" / "optionb_recognition_final_20261025.json"
OUT = ROOT / "storage" / "app" / "audit-recovery" / "optionb-recognition-comparison-20261025.json"
OLLAMA_URL = "http://127.0.0.1:11434"

GUIDANCE = (
    "Additional concise disambiguation rules: classify the lived feeling actually asserted, not merely the topic. "
    "Excitement is energized eagerness or a buzz about an imminent event; anticipation is forward-looking expectation or waiting. "
    "Overwhelm means simultaneous demands exceed the student's capacity or they cannot choose a starting point; anxiety is threat-oriented worry about what may happen; "
    "frustration is blocked effort after trying; stress is sustained pressure over time. "
    "Guilt requires regret or responsibility for the student's own action or consequence; shame requires exposed or globally negative self-evaluation. "
    "For indirect Filipino or Taglish wording, infer only when the whole clause supports the lived state. "
    "For a secondary emotion, require a distinct supported clause or exact bodily/experiential evidence; do not add a related emotion by association. "
    "If the message cannot distinguish candidates, keep status uncertain."
)


def prompt(text: str, keys: list[str], categories: list[str], candidate: bool) -> str:
    base = ev.gemma_prompt(text, keys, categories)
    if not candidate:
        return base
    return base.replace("Student message:\n", GUIDANCE + "\n\nStudent message:\n", 1)


def run_set(cases: list[dict], taxonomy: dict, index: dict, candidate: bool) -> dict:
    keys = sorted(index)
    categories = sorted(item["key"] for item in taxonomy["categories"])
    schema = ev.build_ollama_output_schema(index, set(categories))
    engine = HybridResponseEngine(ollama_url=OLLAMA_URL, model="gemma3:4b")
    rows = []
    for n, case in enumerate(cases, 1):
        started = time.perf_counter()
        row = ev._case_expectation(case, index)
        row.update({"id": case["id"], "message_sha256": hashlib.sha256(case["text"].encode()).hexdigest(), "candidate": candidate})
        try:
            engine._correlation_id = ("optionb-candidate-" if candidate else "optionb-frozen-") + uuid.uuid4().hex[:16]
            response = engine._post_to_ollama(
                f"{OLLAMA_URL}/api/chat",
                {"model": engine.model, "messages": [{"role": "system", "content": "You are a cautious emotion annotation assistant. Output only the requested JSON."}, {"role": "user", "content": prompt(case["text"], keys, categories, candidate)}], "format": schema, "stream": False, "options": {"temperature": 0, "num_predict": 180}},
            )
            payload = response.json()
            content = ((payload.get("message") or {}).get("content") or payload.get("response") or "")
            parsed = ev.parse_gemma_json(content, index, set(categories), str(taxonomy.get("version", "1.0.0")), source_text=case["text"])
            row.update(parsed)
            row["exact_key_match"] = parsed.get("primary_emotion") == case.get("key")
            row["category_match"] = parsed.get("category_key") == ev.category_for(case.get("key"), index) if case.get("key") else None
            row["http_status"] = response.status_code
        except Exception as exc:
            row["error"] = type(exc).__name__ + ": " + str(exc)[:180]
        row["latency_ms"] = round((time.perf_counter() - started) * 1000, 2)
        rows.append(row)
        if n % 10 == 0:
            print(f"{'CANDIDATE' if candidate else 'FROZEN'}_PROGRESS={n}/{len(cases)}", flush=True)
    valid = [r for r in rows if "error" not in r]
    metric = ev._metric_summary(rows, categories)
    extras, missing = [], []
    for r in valid:
        expected, actual = set(r.get("expected_secondary_keys", [])), set(r.get("secondary_emotions", []))
        if actual - expected: extras.append({"id": r["id"], "expected": sorted(expected), "actual": sorted(actual)})
        if expected - actual: missing.append({"id": r["id"], "expected": sorted(expected), "actual": sorted(actual)})
    by_label = {}
    for label in sorted({r.get("expected_key") for r in valid if r.get("expected_key")}):
        subset = [r for r in valid if r.get("expected_key") == label]
        by_label[label] = {"correct": sum(r.get("primary_emotion") == label for r in subset), "denominator": len(subset)}
    latencies = [r["latency_ms"] for r in valid]
    return {"candidate": candidate, "case_count": len(rows), "metrics": metric, "secondary_extras": extras, "secondary_missing": missing, "per_label": by_label, "latency_ms": {"median": statistics.median(latencies) if latencies else None, "p95": ev.percentile(latencies, .95)}, "rows": rows}


def main() -> int:
    taxonomy = ev.load_taxonomy(); index = ev.taxonomy_index(taxonomy)
    dev = json.loads(DEV.read_text(encoding="utf-8"))["cases"]
    final = json.loads(FINAL.read_text(encoding="utf-8"))["cases"]
    result = {"evaluation_only": True, "synthetic_only": True, "frozen_81_case_baseline_untouched": True, "guidance_is_candidate_only": True, "taxonomy_version": taxonomy["version"], "development": {}, "unseen_final": {}}
    result["development"]["frozen"] = run_set(dev, taxonomy, index, False)
    result["development"]["candidate"] = run_set(dev, taxonomy, index, True)
    result["unseen_final"]["frozen"] = run_set(final, taxonomy, index, False)
    result["unseen_final"]["candidate"] = run_set(final, taxonomy, index, True)
    OUT.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    print("RESULT_FILE=" + str(OUT))
    for group in ("development", "unseen_final"):
        for name in ("frozen", "candidate"):
            block = result[group][name]
            m = block["metrics"]
            print(group + "_" + name + "=" + json.dumps({"exact":m.get("exact_detailed_key_accuracy"),"category":m.get("category_accuracy"),"category_set":m.get("category_set_accuracy"),"secondary":m.get("secondary_label_accuracy"),"mixed":m.get("mixed"),"uncertainty":m.get("uncertainty"),"attribution":m.get("attribution_scope"),"latency":block["latency_ms"],"extras":len(block["secondary_extras"]),"missing":len(block["secondary_missing"])},ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
