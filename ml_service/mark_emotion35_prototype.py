"""Add an explicit non-production marker to a research checkpoint."""
import argparse, json
from pathlib import Path
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("model",type=Path); a=p.parse_args()
    marker={"prototype_only":True,"production_enabled":False,"student_chat_enabled":False,"admin_analytics_enabled":False,"human_reviewed":False,"reason":"AI-assisted synthetic rows are unreviewed; adviser demonstration only"}
    (a.model/"prototype_only.json").write_text(json.dumps(marker,indent=2)+"\n",encoding="utf-8"); print(json.dumps(marker))
