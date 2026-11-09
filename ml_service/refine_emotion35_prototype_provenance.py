"""Add exact duplicate accounting to the prototype provenance."""
import argparse, json
from pathlib import Path
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("root",type=Path); a=p.parse_args()
    for name in ("provenance.json","prototype_summary.json"):
        path=a.root/name; data=json.loads(path.read_text(encoding="utf-8")); data["public_repeated_rows_excluded"]=285; data["public_cross_split_duplicate_keys"]=61; data["public_duplicate_policy"]="retain first occurrence in train/validation/test traversal; exclude later copies"
        path.write_text(json.dumps(data,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps({"public_repeated_rows_excluded":285,"public_cross_split_duplicate_keys":61}))
