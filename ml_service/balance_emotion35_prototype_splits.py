"""Balance synthetic single-label coverage to 20/5/5 without touching source rows."""
import argparse, json
from pathlib import Path
from collections import defaultdict

def load(path):
    with path.open(encoding="utf-8") as f:
        return [json.loads(line) for line in f if line.strip()]
def save(path, rows):
    with path.open("w", encoding="utf-8") as f:
        for r in rows:
            f.write(json.dumps(r, ensure_ascii=False) + "\n")

def main(root):
    splits = {s: load(root / f"{s}.jsonl") for s in ("train", "validation", "test")}
    # Rows are moved only within the synthetic single-label pool. Public
    # annotations and mixed examples remain in their original source split.
    for label in {r["labels"][0] for r in splits["train"] + splits["validation"] + splits["test"] if r.get("synthetic") and not r.get("mixed_emotion") and len(r.get("labels", [])) == 1}:
        pool = defaultdict(list)
        for split, rows in splits.items():
            for row in rows:
                if row.get("synthetic") and not row.get("mixed_emotion") and row.get("labels") == [label]:
                    pool[split].append(row)
        # The generator creates 21/6/3. Move one train and one validation row
        # to test, preserving source IDs and the explicit synthetic provenance.
        while len(pool["test"]) < 5 and pool["train"]:
            row = pool["train"].pop()
            splits["train"].remove(row); row["split"] = "test"; splits["test"].append(row); pool["test"].append(row)
        while len(pool["test"]) < 5 and pool["validation"]:
            row = pool["validation"].pop()
            splits["validation"].remove(row); row["split"] = "test"; splits["test"].append(row); pool["test"].append(row)
        if len(pool["train"]) < 20 or len(pool["validation"]) < 5 or len(pool["test"]) < 5:
            raise RuntimeError(f"unable to balance {label}: { {k: len(v) for k,v in pool.items()} }")
    for split, rows in splits.items():
        save(root / f"{split}.jsonl", rows)
    summary = {"records": {s: len(r) for s,r in splits.items()}, "balanced_single_label_target": {"train": 20, "validation": 5, "test": 5}}
    (root / "split_balance.json").write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary, indent=2))

if __name__ == "__main__":
    parser = argparse.ArgumentParser(); parser.add_argument("root", type=Path); args = parser.parse_args(); main(args.root)
