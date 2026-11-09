"""Refresh the private review-workflow README with the gap-002 reserve."""
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PATH=ROOT/'storage'/'app'/'datasets'/'emotion35_review_workflow'/'README.md'

def main():
    text=PATH.read_text(encoding='utf-8') if PATH.exists() else '# Emotion35 review workflow\n'
    marker='* `review_batch_gap_002.csv`: 210 additional unreviewed candidates (30 per uncovered label; proposed 20 train / 5 validation / 5 test, with English, Filipino, and Taglish).\n'
    if 'review_batch_gap_002.csv' not in text:
        text=text.replace('* `review_batch_gap_001.csv`: expanded synthetic candidates for the seven uncovered labels; every row remains unreviewed.\n', '* `review_batch_gap_001.csv`: expanded synthetic candidates for the seven uncovered labels; every row remains unreviewed.\n'+marker)
    text=text.replace('seven-gap goal of at least 30 accepted examples per target; current accepted count is zero.', 'seven-gap goal of at least 30 accepted examples per target with 20/5/5 split minimums; gap-002 adds a 30-row split-balanced reserve per target; current accepted count is zero.')
    PATH.write_text(text,encoding='utf-8');print(PATH);return 0
if __name__=='__main__':raise SystemExit(main())
