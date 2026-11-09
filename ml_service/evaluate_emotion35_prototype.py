"""Evaluate the isolated Emotion35 prototype with masked multilabel metrics."""
from __future__ import annotations
import argparse, json, math, time
from collections import Counter
from pathlib import Path
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY = ROOT / "config/emotion_taxonomy.json"

def read_jsonl(path):
    with path.open(encoding="utf-8") as f:
        return [json.loads(line) for line in f if line.strip()]

def sigmoid(x): return 1.0 / (1.0 + np.exp(-np.clip(x, -50, 50)))

def metrics_for(target, pred, labels):
    target=np.asarray(target,dtype=int); pred=np.asarray(pred,dtype=int)
    per={}; tp=fp=fn=0
    for i,label in enumerate(labels):
        t=target[:,i]; p=pred[:,i]
        a=int(((t==1)&(p==1)).sum()); b=int(((t==0)&(p==1)).sum()); c=int(((t==1)&(p==0)).sum())
        precision=a/(a+b) if a+b else 0.0; recall=a/(a+c) if a+c else 0.0; f1=2*precision*recall/(precision+recall) if precision+recall else 0.0
        per[label]={"support":int(t.sum()),"predicted_positive":int(p.sum()),"tp":a,"fp":b,"fn":c,"precision":precision,"recall":recall,"f1":f1}
        tp+=a; fp+=b; fn+=c
    precision=tp/(tp+fp) if tp+fp else 0.0; recall=tp/(tp+fn) if tp+fn else 0.0
    f1=2*precision*recall/(precision+recall) if precision+recall else 0.0
    macro=float(np.mean([v["f1"] for v in per.values()])) if per else 0.0
    exact=float(np.mean(np.all(target==pred,axis=1))) if len(target) else 0.0
    sample_f=[]
    for t,p in zip(target,pred):
        a=int(((t==1)&(p==1)).sum()); b=int(((t==0)&(p==1)).sum()); c=int(((t==1)&(p==0)).sum())
        pr=a/(a+b) if a+b else 0.0; re=a/(a+c) if a+c else 0.0
        sample_f.append(2*pr*re/(pr+re) if pr+re else 0.0)
    return {"micro_precision":precision,"micro_recall":recall,"micro_f1":f1,"macro_f1":macro,"exact_match_accuracy":exact,"sample_f1":float(np.mean(sample_f)) if sample_f else 0.0,"positive_support":int(target.sum()),"per_label":per}

def main(dataset, model_dir, output):
    taxonomy=json.loads(TAXONOMY.read_text(encoding="utf-8")); labels=[e["key"] for e in taxonomy["emotions"]]
    label_to_id={k:i for i,k in enumerate(labels)}
    thresholds=json.loads((model_dir/"thresholds.json").read_text(encoding="utf-8"))
    if "thresholds" in thresholds: thresholds=thresholds["thresholds"]
    test=read_jsonl(dataset/"test.jsonl"); validation=read_jsonl(dataset/"validation.jsonl")
    # The trainer selected thresholds from validation only; retain that fact in
    # the report and use the saved values unchanged for the untouched test set.
    from transformers import AutoModelForSequenceClassification, AutoTokenizer
    import torch
    tokenizer=AutoTokenizer.from_pretrained(str(model_dir),local_files_only=True)
    model=AutoModelForSequenceClassification.from_pretrained(str(model_dir),local_files_only=True)
    model.eval(); device=torch.device("cuda" if torch.cuda.is_available() else "cpu"); model.to(device)
    probs=[]; start=time.perf_counter()
    with torch.no_grad():
        for start_i in range(0,len(test),32):
            batch=test[start_i:start_i+32]
            enc=tokenizer([r["text"] for r in batch],padding=True,truncation=True,max_length=256,return_tensors="pt")
            enc={k:v.to(device) for k,v in enc.items()}
            probs.extend(sigmoid(model(**enc).logits.detach().cpu().numpy()))
    inference_seconds=time.perf_counter()-start
    target=[]
    for row in test:
        target.append([1 if label in row.get("labels",[]) else 0 for label in labels])
    target=np.asarray(target,dtype=int); probs=np.asarray(probs)
    pred=np.asarray([[1 if probs[j,i] >= float(thresholds.get(label,0.5)) else 0 for i,label in enumerate(labels)] for j in range(len(test))])
    report={"schema":"mindcare-emotion35-prototype-evaluation-v1","model_dir":str(model_dir),"dataset_dir":str(dataset),"taxonomy_version":taxonomy.get("version"),"device":str(device),"test_records":len(test),"threshold_source":"saved thresholds calibrated on validation split only","inference_seconds":inference_seconds,"inference_ms_per_record":inference_seconds*1000/len(test),"metrics":metrics_for(target,pred,labels)}
    mixed_idx=[i for i,r in enumerate(test) if r.get("mixed_emotion")]
    single_idx=[i for i,r in enumerate(test) if r.get("synthetic") and not r.get("mixed_emotion")]
    for name,idx in (("synthetic_mixed",mixed_idx),("synthetic_single",single_idx)):
        if idx:
            report[name]={"records":len(idx),"metrics":metrics_for(target[idx],pred[idx],labels),"languages":dict(Counter(test[i].get("language","unknown") for i in idx))}
    report["all_outputs"]=labels; report["unsupported_outputs"]=[]
    report["caveats"]=["Synthetic rows are AI-assisted, unreviewed research examples; scores are not professionally verified recognition accuracy.","GoEmotions labels remain partially observed; masked loss excludes labels not annotated by that source.","The model is not connected to Student Chat or Admin analytics."]
    output.write_text(json.dumps(report,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps({"test_records":len(test),"device":str(device),"inference_seconds":inference_seconds,"micro_f1":report["metrics"]["micro_f1"],"macro_f1":report["metrics"]["macro_f1"],"exact_match":report["metrics"]["exact_match_accuracy"],"mixed_records":len(mixed_idx)},indent=2))

if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--dataset",type=Path,required=True); p.add_argument("--model",type=Path,required=True); p.add_argument("--output",type=Path,required=True); a=p.parse_args(); main(a.dataset,a.model,a.output)
