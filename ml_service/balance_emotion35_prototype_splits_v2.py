"""Balance synthetic single-label coverage to 20/5/5."""
import argparse, json
from pathlib import Path
from collections import defaultdict

def load(path):
    with path.open(encoding="utf-8") as f: return [json.loads(line) for line in f if line.strip()]
def save(path, rows):
    with path.open("w", encoding="utf-8") as f:
        for r in rows: f.write(json.dumps(r, ensure_ascii=False) + "\n")
def main(root):
    splits={s:load(root/f"{s}.jsonl") for s in ("train","validation","test")}
    labels={r["labels"][0] for rows in splits.values() for r in rows if r.get("synthetic") and not r.get("mixed_emotion") and len(r.get("labels",[]))==1}
    for label in labels:
        pool=defaultdict(list)
        for split, rows in splits.items():
            pool[split]=[r for r in rows if r.get("synthetic") and not r.get("mixed_emotion") and r.get("labels")==[label]]
        if len(pool["test"]) < 5 and len(pool["train"]) > 20:
            row=pool["train"].pop(); splits["train"].remove(row); row["split"]="test"; splits["test"].append(row); pool["test"].append(row)
        if len(pool["test"]) < 5 and len(pool["validation"]) > 5:
            row=pool["validation"].pop(); splits["validation"].remove(row); row["split"]="test"; splits["test"].append(row); pool["test"].append(row)
        if len(pool["train"])<20 or len(pool["validation"])<5 or len(pool["test"])<5:
            raise RuntimeError(f"unable to balance {label}: { {k:len(v) for k,v in pool.items()} }")
    for split, rows in splits.items(): save(root/f"{split}.jsonl", rows)
    print(json.dumps({"records":{s:len(r) for s,r in splits.items()},"synthetic_single_target":{"train":20,"validation":5,"test":5}},indent=2))
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("root",type=Path); a=p.parse_args(); main(a.root)
