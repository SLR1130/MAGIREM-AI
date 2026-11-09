"""Local-only demonstration for the isolated Emotion35 research checkpoint.

This script is deliberately opt-in and never imports or changes Student Chat.
It prints detailed predictions only for synthetic/local demonstrations.
"""
from __future__ import annotations
import argparse, json
from pathlib import Path
import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

ROOT=Path(__file__).resolve().parents[1]
TAXONOMY=ROOT/"config/emotion_taxonomy.json"

def predict(model_dir: Path, text: str):
    taxonomy=json.loads(TAXONOMY.read_text(encoding="utf-8")); emotions=taxonomy["emotions"]
    categories={e["key"]:e["category_key"] for e in emotions}
    raw=json.loads((model_dir/"thresholds.json").read_text(encoding="utf-8")); thresholds=raw.get("thresholds",raw)
    labels=[e["key"] for e in emotions]
    tokenizer=AutoTokenizer.from_pretrained(str(model_dir),local_files_only=True)
    model=AutoModelForSequenceClassification.from_pretrained(str(model_dir),local_files_only=True); model.eval()
    with torch.no_grad():
        encoded=tokenizer(text,return_tensors="pt",truncation=True,max_length=256)
        probabilities=torch.sigmoid(model(**encoded).logits[0]).tolist()
    scored=sorted(({"emotion":label,"category":categories[label],"probability":float(probabilities[i]),"threshold":float(thresholds.get(label,0.5)),"accepted":probabilities[i]>=float(thresholds.get(label,0.5))} for i,label in enumerate(labels)),key=lambda x:x["probability"],reverse=True)
    return {"prototype_only":True,"student_facing":False,"text_used_for_local_demo":text,"accepted_detailed_emotions":[x for x in scored if x["accepted"]],"top_predictions":scored[:5],"warning":"AI-assisted/unreviewed research model; not diagnostic and not connected to production services."}

if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--model",type=Path,required=True); p.add_argument("--text",required=True); a=p.parse_args(); print(json.dumps(predict(a.model,a.text),indent=2,ensure_ascii=False))
