"""Generate 210 unreviewed gap candidates with 20/5/5 proposed splits."""
from expand_emotion35_gap_candidates_v2 import GAPS, SUFFIXES
from build_emotion35_review_sheet import stable_id, write_csv
import argparse, json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
DEFAULT=ROOT/'storage'/'app'/'datasets'/'emotion35_review_workflow'/'review_batch_gap_002.csv'

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,default=DEFAULT);a=p.parse_args()
    rows=[]
    for label, bases in GAPS.items():
        for li,(lang,base) in enumerate(bases.items()):
            for oi,suffix in enumerate(SUFFIXES[lang]):
                n=li*10+oi+1; split='train' if n<=20 else 'validation' if n<=25 else 'test'
                rows.append({'review_id':stable_id('gap002',label,lang,oi),'text':f'{base}, {suffix}.',
                    'source_dataset':'synthetic_gap_candidates_v2','source_id':f'gap002-{label}-{lang.lower()}-{oi+1:02d}',
                    'conversation_id':'','source_split':split,'source_context':'','proposed_emotion':label,
                    'optional_secondary_emotions':'','language':lang,'review_status':'unreviewed','reviewer_decision':'',
                    'reviewed_labels':'','reviewed_observed_labels':'','rejection_reason':'','ambiguity_notes':'',
                    'provenance':'Synthetic candidate reserve; human semantic, attribution, and split review required.',
                    'license':'Internal synthetic example','group_id':f'synthetic_gap002:{label}:{lang.lower()}:{oi+1:02d}',
                    'synthetic':'true','annotation_scope':'candidate only; not ground truth','reviewer_id':'','reviewed_at':''})
    digest=write_csv(a.output,rows);manifest={'schema':'mindcare-emotion35-gap-candidate-reserve-v2','rows':len(rows),
      'rows_per_label':30,'languages_per_label':{'ENGLISH':10,'FILIPINO':10,'TAGLISH':10},
      'proposed_split_per_label':{'train':20,'validation':5,'test':5},'review_status':'unreviewed','sha256':digest,
      'note':'Proposed splits are planning only; unreviewed rows never count toward training.'}
    a.output.with_name(a.output.name+'.manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
    print(json.dumps(manifest,indent=2));return 0
if __name__=='__main__':raise SystemExit(main())
