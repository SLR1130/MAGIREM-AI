"""Run adviser-only fresh Emotion35 cases against a research checkpoint."""
import argparse, json
from pathlib import Path
import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

ROOT=Path(__file__).resolve().parents[1]
TAXONOMY=ROOT/"config/emotion_taxonomy.json"
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--model",type=Path,required=True); p.add_argument("--cases",type=Path,required=True); p.add_argument("--output",type=Path,required=True); a=p.parse_args()
    taxonomy=json.loads(TAXONOMY.read_text(encoding="utf-8")); labels=[e["key"] for e in taxonomy["emotions"]]; categories={e["key"]:e["category_key"] for e in taxonomy["emotions"]}
    raw=json.loads((a.model/"thresholds.json").read_text(encoding="utf-8")); thresholds=raw.get("thresholds",raw)
    tok=AutoTokenizer.from_pretrained(str(a.model),local_files_only=True); model=AutoModelForSequenceClassification.from_pretrained(str(a.model),local_files_only=True); model.eval(); cases=json.loads(a.cases.read_text(encoding="utf-8")); out=[]
    with torch.no_grad():
        for case in cases:
            enc=tok(case["text"],return_tensors="pt",truncation=True,max_length=256); probs=torch.sigmoid(model(**enc).logits[0]).tolist(); scored=[]
            for i,label in enumerate(labels):
                th=float(thresholds.get(label,.5)); scored.append({"emotion":label,"category":categories[label],"probability":float(probs[i]),"threshold":th,"accepted":bool(probs[i]>=th)})
            scored.sort(key=lambda x:x["probability"],reverse=True); out.append({**case,"accepted_detailed_emotions":[x for x in scored if x["accepted"]],"top_predictions":scored[:5]})
    result={"prototype_only":True,"checkpoint":str(a.model),"cases":out,"warning":"Synthetic adviser evidence only; not human-validated recognition accuracy."}; a.output.write_text(json.dumps(result,indent=2,ensure_ascii=False)+"\n",encoding="utf-8"); print(json.dumps({"cases":len(out),"output":str(a.output)},indent=2))
