"""Development-only threshold check; never overwrites the trained artifact."""
import argparse, json, time
from pathlib import Path
import numpy as np
from evaluate_emotion35_prototype import read_jsonl, sigmoid, metrics_for

def infer(model, tokenizer, rows, torch):
    out=[]
    with torch.no_grad():
        for i in range(0,len(rows),32):
            enc=tokenizer([r["text"] for r in rows[i:i+32]],padding=True,truncation=True,max_length=256,return_tensors="pt")
            out.extend(sigmoid(model(**enc).logits.detach().cpu().numpy()))
    return np.asarray(out)

if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--dataset",type=Path,required=True); p.add_argument("--model",type=Path,required=True); p.add_argument("--output",type=Path,required=True); a=p.parse_args()
    import torch
    from transformers import AutoModelForSequenceClassification, AutoTokenizer
    taxonomy=json.loads(Path("config/emotion_taxonomy.json").read_text(encoding="utf-8")); labels=[e["key"] for e in taxonomy["emotions"]]
    train=read_jsonl(a.dataset/"validation.jsonl"); test=read_jsonl(a.dataset/"test.jsonl")
    tokenizer=AutoTokenizer.from_pretrained(str(a.model),local_files_only=True); model=AutoModelForSequenceClassification.from_pretrained(str(a.model),local_files_only=True); model.eval()
    vp=infer(model,tokenizer,train,torch); tp=infer(model,tokenizer,test,torch)
    def target(rows): return np.asarray([[1 if x in r.get("labels",[]) else 0 for x in labels] for r in rows],dtype=int)
    vt,tt=target(train),target(test); chosen={}
    for i,label in enumerate(labels):
        best=(0.5,-1.0)
        for threshold in np.arange(0.25,0.91,0.05):
            pred=(vp[:,i]>=threshold).astype(int); t=vt[:,i]; tp0=int(((t==1)&(pred==1)).sum()); fp=int(((t==0)&(pred==1)).sum()); fn=int(((t==1)&(pred==0)).sum()); precision=tp0/(tp0+fp) if tp0+fp else 0.0; recall=tp0/(tp0+fn) if tp0+fn else 0.0; f1=2*precision*recall/(precision+recall) if precision+recall else 0.0
            score=f1 if precision>=0.5 else f1-1.0
            if score>best[1]: best=(float(threshold),float(score))
        chosen[label]=best[0]
    pred=np.asarray([[1 if tp[j,i]>=chosen[label] else 0 for i,label in enumerate(labels)] for j in range(len(test))])
    report={"thresholds":chosen,"calibration_split":"validation","test_metrics":metrics_for(tt,pred,labels),"warning":"development-only threshold experiment; original thresholds.json and model were not modified"}
    a.output.write_text(json.dumps(report,indent=2)+"\n",encoding="utf-8"); print(json.dumps({"test_micro_f1":report["test_metrics"]["micro_f1"],"test_micro_precision":report["test_metrics"]["micro_precision"],"test_micro_recall":report["test_metrics"]["micro_recall"]},indent=2))
