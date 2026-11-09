"""Correct duplicate wording in the generated report without changing metrics."""
import argparse
from pathlib import Path
if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("report",type=Path); a=p.parse_args()
    text=a.report.read_text(encoding="utf-8")
    text=text.replace("excluding 285 later-split duplicate texts", "excluding 285 repeated public rows (61 duplicate text keys crossed official splits)")
    a.report.write_text(text,encoding="utf-8")
