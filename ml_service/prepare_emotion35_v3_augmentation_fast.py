"""Fast wrapper for the isolated v3 training-only augmentation.

It reuses the curated bank in prepare_emotion35_v3_augmentation and performs
near-duplicate checks only within each language, which is the relevant leakage
boundary for the new Filipino/Taglish records.
"""
from __future__ import annotations
import argparse, json, shutil
from pathlib import Path
from difflib import SequenceMatcher
from prepare_emotion35_v3_augmentation import BANK, MIXED, TAXONOMY, make_record, norm

def main() -> None:
    ap = argparse.ArgumentParser(); ap.add_argument("--base", type=Path, required=True); ap.add_argument("--output", type=Path, required=True)
    args = ap.parse_args()
    if args.output.exists(): raise SystemExit(f"Refusing to overwrite {args.output}")
    shutil.copytree(args.base, args.output)
    path = args.output / "train.jsonl"
    base = [json.loads(x) for x in path.read_text(encoding="utf-8").splitlines() if x.strip()]
    rows, excluded = [], []
    for row in base:
        if row.get("synthetic") and "भय" in row.get("text", ""):
            excluded.append({"source_id": row.get("source_id"), "reason": "malformed Filipino fear text"}); continue
        rows.append(row)
    candidates=[]
    for label, langs in BANK.items():
        for lang, texts in langs.items():
            for i,text in enumerate(texts,1): candidates.append(make_record(text,lang,[label],i))
    for i,(lang,texts,labels) in enumerate(MIXED,1):
        for text in texts: candidates.append(make_record(text,lang,labels,i))
    existing={norm(r.get("text","")) for r in rows}; by_lang={}
    for r in rows: by_lang.setdefault(str(r.get("language","UNKNOWN")).upper(),[]).append(norm(r.get("text","")))
    accepted=[]; rejected=[]
    for row in candidates:
        key=norm(row["text"])
        if not key or key in existing:
            rejected.append({"source_id":row["source_id"],"reason":"exact normalized duplicate"}); continue
        near=next((old for old in by_lang.get(row["language"],[]) if SequenceMatcher(None,key,old).ratio()>=.92),None)
        if near:
            rejected.append({"source_id":row["source_id"],"reason":"near duplicate"}); continue
        existing.add(key); by_lang.setdefault(row["language"],[]).append(key); accepted.append(row)
    path.write_text("".join(json.dumps(r,ensure_ascii=False)+"\n" for r in rows+accepted),encoding="utf-8")
    prov_path=args.output/"provenance.json"; prov=json.loads(prov_path.read_text(encoding="utf-8")); prov.update({
        "version":"emotion35-v3-augmentation-20261026","source":"GoEmotions partial annotations plus isolated v3 synthetic training augmentation",
        "license":"GoEmotions license/provenance retained; synthetic rows are original research candidates",
        "usage":"research-only; no student chat or Admin analytics","curator":"MinSuansCore research pipeline; human_review_required for synthetic rows",
        "v3_training_only_additions":len(accepted),"v3_excluded_malformed_training_rows":excluded,"v3_rejected_candidate_rows":rejected,
        "validation_test_unchanged_from_v2":True,"human_reviewed_rows":0})
    prov_path.write_text(json.dumps(prov,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    summary={"base_dataset":str(args.base),"output_dataset":str(args.output),"base_train_rows":len(base),"clean_train_rows":len(rows),"accepted_training_additions":len(accepted),"rejected_candidates":rejected,"excluded_malformed_rows":excluded,"validation_test_unchanged":True,"human_reviewed_rows":0,"production_model_untouched":True}
    (args.output/"v3_augmentation_summary.json").write_text(json.dumps(summary,ensure_ascii=False,indent=2)+"\n",encoding="utf-8"); print(json.dumps(summary,ensure_ascii=False,indent=2))
if __name__=="__main__": main()
