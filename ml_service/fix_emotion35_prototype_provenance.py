"""Record the public split duplicates excluded by the v4 wrapper."""
import argparse, json
from pathlib import Path
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("root",type=Path); a=p.parse_args()
    excluded=29994-29709
    prov=json.loads((a.root/"provenance.json").read_text(encoding="utf-8")); prov["public_cross_split_duplicates_excluded"]=excluded
    (a.root/"provenance.json").write_text(json.dumps(prov,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    summary=json.loads((a.root/"prototype_summary.json").read_text(encoding="utf-8")); summary["public_cross_split_duplicates_excluded"]=excluded
    (a.root/"prototype_summary.json").write_text(json.dumps(summary,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print(json.dumps({"public_cross_split_duplicates_excluded":excluded}))
