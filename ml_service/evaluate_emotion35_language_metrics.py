"""Compute the same masked metrics grouped by language for an isolated model."""
import argparse, json
from pathlib import Path
import numpy as np, torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer
from evaluate_emotion35_prototype import read_jsonl, sigmoid, metrics_for
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--dataset",type=Path,required=True); p.add_argument("--model",type=Path,required=True); p.add_argument("--output",type=Path,required=True); a=p.parse_args()
    tax=json.loads(Path("config/emotion_taxonomy.json").read_text(encoding="utf-8")); labels=[e["key"] for e in tax["emotions"]]; raw=json.loads((a.model/"thresholds.json").read_text(encoding="utf-8")); th=raw.get("thresholds",raw); rows=read_jsonl(a.dataset/"test.jsonl"); tok=AutoTokenizer.from_pretrained(str(a.model),local_files_only=True); model=AutoModelForSequenceClassification.from_pretrained(str(a.model),local_files_only=True); model.eval(); probs=[]
    with torch.no_grad():
        for i in range(0,len(rows),32):
            enc=tok([r["text"] for r in rows[i:i+32]],padding=True,truncation=True,max_length=256,return_tensors="pt"); probs.extend(sigmoid(model(**enc).logits.cpu().numpy()))
    probs=np.asarray(probs); target=np.asarray([[1 if x in r.get("labels",[]) else 0 for x in labels] for r in rows]); pred=np.asarray([[1 if probs[j,i]>=float(th.get(label,.5)) else 0 for i,label in enumerate(labels)] for j in range(len(rows))]); result={}
    for lang in sorted({r.get("language","UNKNOWN") for r in rows}):
        idx=np.asarray([i for i,r in enumerate(rows) if r.get("language","UNKNOWN")==lang]); result[lang]={"records":len(idx),"metrics":metrics_for(target[idx],pred[idx],labels)}
    a.output.write_text(json.dumps(result,indent=2,ensure_ascii=False)+"\n",encoding="utf-8"); print(json.dumps({k:{"records":v["records"],"micro_f1":v["metrics"]["micro_f1"],"macro_f1":v["metrics"]["macro_f1"]} for k,v in result.items()},indent=2))
