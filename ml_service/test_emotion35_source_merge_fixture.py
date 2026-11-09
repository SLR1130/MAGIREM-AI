"""Run the source merge and full-coverage audit on synthetic fixture data."""
import json, shutil, sys
from pathlib import Path
from emotion_35_multilabel_pipeline import audit_dataset, load_taxonomy
import merge_emotion35_sources

ROOT=Path(__file__).resolve().parents[1]
FIXTURE=ROOT/'storage'/'app'/'audit-recovery'/'emotion35_merge_fixture_test'

def write_rows(directory, labels, source):
    directory.mkdir(parents=True,exist_ok=True)
    for split,count in [('train',20),('validation',5),('test',5)]:
        with (directory/f'{split}.jsonl').open('w',encoding='utf-8') as h:
            for label in labels:
                for i in range(count):
                    sid=f'{source}-{split}-{label}-{i}'
                    h.write(json.dumps({'text':f'fixture {sid}','labels':[label],'observed_labels':[label],
                        'language':'ENGLISH','source_dataset':source,'source_id':sid,'group_id':sid,
                        'synthetic':source=='reviewed'})+'\n')

def main():
    if FIXTURE.exists(): shutil.rmtree(FIXTURE)
    labels=[x['key'] for x in load_taxonomy()['emotions']]
    write_rows(FIXTURE/'go',labels[:15],'go_fixture'); write_rows(FIXTURE/'reviewed',labels[15:],'reviewed')
    old=sys.argv[:]
    try:
        sys.argv=['merge_emotion35_sources.py','--go-dir',str(FIXTURE/'go'),'--reviewed-dir',str(FIXTURE/'reviewed'),'--output-dir',str(FIXTURE/'merged')]
        if merge_emotion35_sources.main()!=0: raise AssertionError('fixture merge did not pass')
    finally: sys.argv=old
    output=FIXTURE/'merged'; prov=json.loads((output/'provenance.json').read_text(encoding='utf-8'))
    audit=audit_dataset(output,provenance=prov)
    assert audit.sufficient_for_training, audit.to_dict()
    assert audit.total_records==35*30 and not audit.missing_labels
    print(json.dumps({'fixture':'pass','records':audit.total_records,'labels':len(labels),'training_ready':True},indent=2))
    shutil.rmtree(FIXTURE); return 0

if __name__=='__main__': raise SystemExit(main())
