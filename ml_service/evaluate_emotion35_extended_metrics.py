"""Compute comparable masked label, language, FPR, and category metrics.

This report is evaluation-only. It uses each checkpoint's validation-calibrated
thresholds and the identical v2 test bytes (v3 copies them unchanged).
"""
from __future__ import annotations
import argparse, json, time
from collections import defaultdict
from pathlib import Path
import numpy as np

def read(path): return [json.loads(x) for x in path.open(encoding="utf-8") if x.strip()]
def sigmoid(x): return 1/(1+np.exp(-np.clip(x,-50,50)))

def main():
    ap=argparse.ArgumentParser(); ap.add_argument("--dataset",type=Path,required=True); ap.add_argument("--model",type=Path,required=True); ap.add_argument("--output",type=Path,required=True); a=ap.parse_args()
    tax=json.loads(Path("config/emotion_taxonomy.json").read_text(encoding="utf-8")); labels=[e["key"] for e in tax["emotions"]]; cat={e["key"]:e["category_key"] for e in tax["emotions"]}
    rows=read(a.dataset/"test.jsonl"); raw=json.loads((a.model/"thresholds.json").read_text(encoding="utf-8")); th=raw.get("thresholds",raw)
    from transformers import AutoTokenizer, AutoModelForSequenceClassification
    import torch
    tok=AutoTokenizer.from_pretrained(str(a.model),local_files_only=True); model=AutoModelForSequenceClassification.from_pretrained(str(a.model),local_files_only=True); dev=torch.device("cuda" if torch.cuda.is_available() else "cpu"); model.to(dev); model.eval(); probs=[]; start=time.perf_counter()
    with torch.no_grad():
        for i in range(0,len(rows),64):
            enc=tok([r["text"] for r in rows[i:i+64]],padding=True,truncation=True,max_length=256,return_tensors="pt"); enc={k:v.to(dev) for k,v in enc.items()}; probs.extend(sigmoid(model(**enc).logits.detach().cpu().numpy()))
    elapsed=time.perf_counter()-start; probs=np.asarray(probs); per={}; tp=fp=fn=tn=0
    predictions=[]; true_categories=[]; pred_categories=[]; lang_stats=defaultdict(lambda:[0,0,0,0])
    for j,row in enumerate(rows):
        observed=set(row.get("observed_labels") or labels); truth=set(row.get("labels") or []); pred={l for i,l in enumerate(labels) if probs[j,i]>=float(th.get(l,.5))}
        valid=observed; predictions.append(pred); true_categories.append({cat[l] for l in truth if l in valid}); pred_categories.append({cat[l] for l in pred if l in valid})
        lang=str(row.get("language","UNKNOWN")).upper()
        for l in labels:
            if l not in valid: continue
            t=l in truth; p=l in pred; tp_l=int(t and p); fp_l=int((not t) and p); fn_l=int(t and not p); tn_l=int((not t) and not p)
            z=per.setdefault(l,{"support":0,"predicted_positive":0,"tp":0,"fp":0,"fn":0,"tn":0}); z["support"]+=int(t); z["predicted_positive"]+=int(p); z["tp"]+=tp_l; z["fp"]+=fp_l; z["fn"]+=fn_l; z["tn"]+=tn_l
            tp+=tp_l;fp+=fp_l;fn+=fn_l;tn+=tn_l; lang_stats[lang][0]+=tp_l;lang_stats[lang][1]+=fp_l;lang_stats[lang][2]+=fn_l;lang_stats[lang][3]+=int(t)
    for l,z in per.items():
        z["precision"]=z["tp"]/(z["tp"]+z["fp"]) if z["tp"]+z["fp"] else 0; z["recall"]=z["tp"]/(z["tp"]+z["fn"]) if z["tp"]+z["fn"] else 0; z["f1"]=2*z["precision"]*z["recall"]/(z["precision"]+z["recall"]) if z["precision"]+z["recall"] else 0; z["false_positive_rate"]=z["fp"]/(z["fp"]+z["tn"]) if z["fp"]+z["tn"] else 0; z["threshold"]=float(th.get(l,.5))
    precision=tp/(tp+fp) if tp+fp else 0; recall=tp/(tp+fn) if tp+fn else 0; f1=2*precision*recall/(precision+recall) if precision+recall else 0
    category_tp=category_fp=category_fn=0; category_exact=0
    for t,p in zip(true_categories,pred_categories):
        category_exact+=int(t==p); category_tp+=len(t&p); category_fp+=len(p-t); category_fn+=len(t-p)
    cp=category_tp/(category_tp+category_fp) if category_tp+category_fp else 0; cr=category_tp/(category_tp+category_fn) if category_tp+category_fn else 0
    result={"schema":"emotion35-extended-evaluation-v1","model":str(a.model),"dataset":str(a.dataset),"device":str(dev),"records":len(rows),"inference_seconds":elapsed,"inference_ms_per_record":elapsed*1000/len(rows),"masked_micro":{"precision":precision,"recall":recall,"f1":f1,"tp":tp,"fp":fp,"fn":fn,"tn":tn},"macro_f1":float(np.mean([x["f1"] for x in per.values()])),"exact_label_set_accuracy":float(np.mean([set(row.get("labels") or [])==p for row,p in zip(rows,predictions)])),"category":{"micro_precision":cp,"micro_recall":cr,"micro_f1":2*cp*cr/(cp+cr) if cp+cr else 0,"exact_set_accuracy":category_exact/len(rows)},"languages":{},"per_label":per,"caveats":["Test data and thresholds are unchanged; scores are synthetic/research evidence, not professionally verified accuracy.","Predictions remain disconnected from Student Chat and Admin analytics."]}
    for lang,(ltp,lfp,lfn,lpos) in lang_stats.items():
        lp=ltp/(ltp+lfp) if ltp+lfp else 0; lr=ltp/(ltp+lfn) if ltp+lfn else 0; result["languages"][lang]={"records":sum(str(r.get("language","UNKNOWN")).upper()==lang for r in rows),"positive_support":lpos,"micro_precision":lp,"micro_recall":lr,"micro_f1":2*lp*lr/(lp+lr) if lp+lr else 0}
    a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+"\n",encoding="utf-8"); print(json.dumps({"model":str(a.model),"records":len(rows),"masked_micro_f1":f1,"macro_f1":result["macro_f1"],"category_f1":result["category"]["micro_f1"],"inference_seconds":elapsed},indent=2))
if __name__=="__main__": main()
