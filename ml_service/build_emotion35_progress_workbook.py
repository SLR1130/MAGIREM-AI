"""Build an Excel-friendly Emotion35 coverage/progress workbook (CSV)."""
from __future__ import annotations

import argparse
import csv
import json
from collections import Counter, defaultdict
from pathlib import Path

from emotion_35_multilabel_pipeline import EXPECTED_SPLITS, _records, load_taxonomy

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_GO = ROOT / "storage" / "app" / "datasets" / "emotion35_go_emotions_approved"
DEFAULT_REVIEWS = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow"
DEFAULT_OUTPUT = DEFAULT_REVIEWS / "emotion35_review_progress.csv"


def split_labels(value):
    if isinstance(value, str):
        return [v.strip().casefold() for v in value.replace(',', ';').split(';') if v.strip()]
    return [str(v).strip().casefold() for v in (value or []) if str(v).strip()]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--go-dir', type=Path, default=DEFAULT_GO)
    parser.add_argument('--review-dir', type=Path, default=DEFAULT_REVIEWS)
    parser.add_argument('--output', type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument('--min-train', type=int, default=20)
    parser.add_argument('--min-validation', type=int, default=5)
    parser.add_argument('--min-test', type=int, default=5)
    args = parser.parse_args()
    labels = [item['key'] for item in load_taxonomy()['emotions']]
    accepted = {label: Counter() for label in labels}
    languages = {label: Counter() for label in labels}
    sources = {label: Counter() for label in labels}

    for split in EXPECTED_SPLITS:
        path = args.go_dir / f'{split}.jsonl'
        for row in _records(path):
            for label in split_labels(row.get('labels')):
                if label in accepted:
                    accepted[label][split] += 1
                    languages[label][str(row.get('language') or 'UNKNOWN').upper()] += 1
                    sources[label][str(row.get('source_dataset') or 'go_emotions')] += 1

    # Only imported accepted JSONL is counted; the master and candidate sheets
    # are intentionally excluded from accepted progress.
    imported = args.review_dir / 'reviewed_import'
    if imported.exists():
        for split in EXPECTED_SPLITS:
            path = imported / f'{split}.jsonl'
            if not path.exists():
                continue
            for row in _records(path):
                for label in split_labels(row.get('labels')):
                    if label in accepted:
                        accepted[label][split] += 1
                        languages[label][str(row.get('language') or 'UNKNOWN').upper()] += 1
                        sources[label][str(row.get('source_dataset') or 'human_review')] += 1

    candidate_counts = {label: Counter() for label in labels}
    seen = set()
    for path in sorted(args.review_dir.glob('review_batch*.csv')):
        with path.open('r', encoding='utf-8-sig', newline='') as handle:
            for row in csv.DictReader(handle):
                rid = row.get('review_id') or f'{path}:{row.get("text")}'
                if rid in seen:
                    continue
                seen.add(rid)
                label = str(row.get('proposed_emotion') or '').strip().casefold()
                split = str(row.get('source_split') or '').strip().casefold()
                if label in candidate_counts:
                    candidate_counts[label][split] += 1

    rows=[]
    for label in labels:
        counts=accepted[label]
        remaining={split:max(0,minimum-counts[split]) for split,minimum in {'train':args.min_train,'validation':args.min_validation,'test':args.min_test}.items()}
        priority='critical_gap' if any(remaining.values()) and sum(counts.values()) == 0 else 'underrepresented' if any(remaining.values()) else 'covered'
        rows.append({'emotion':label,'priority':priority,'accepted_train':counts['train'],'accepted_validation':counts['validation'],'accepted_test':counts['test'],
                     'accepted_total':sum(counts.values()),'still_needed_train':remaining['train'],'still_needed_validation':remaining['validation'],'still_needed_test':remaining['test'],
                     'candidate_train':candidate_counts[label]['train'],'candidate_validation':candidate_counts[label]['validation'],'candidate_test':candidate_counts[label]['test'],
                     'candidate_review_only':candidate_counts[label]['review_only'],'languages_seen':'|'.join(sorted(languages[label])) or 'NONE',
                     'sources_seen':'|'.join(sorted(sources[label])) or 'NONE','status':'ready_for_training' if not any(remaining.values()) else 'needs_accepted_review'})
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open('w',encoding='utf-8-sig',newline='') as handle:
        writer=csv.DictWriter(handle,fieldnames=list(rows[0]),extrasaction='ignore')
        writer.writeheader();writer.writerows(rows)
    summary={'schema':'mindcare-emotion35-progress-v1','accepted_total':sum(row['accepted_total'] for row in rows),
             'accepted_label_count':sum(row['accepted_total']>0 for row in rows),'training_ready':all(row['status']=='ready_for_training' for row in rows),
             'files':{'progress_csv':str(args.output)},'note':'Candidate counts are planning only; accepted counts come only from GoEmotions or reviewed_import JSONL.'}
    args.output.with_suffix('.json').write_text(json.dumps({'summary':summary,'rows':rows},indent=2)+'\n',encoding='utf-8')
    print(json.dumps(summary,indent=2))
    return 0

if __name__=='__main__': raise SystemExit(main())
