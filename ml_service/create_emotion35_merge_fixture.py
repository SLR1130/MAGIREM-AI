"""Create a temporary full-coverage fixture for merge/audit command testing."""
import argparse, json, shutil
from pathlib import Path
from emotion_35_multilabel_pipeline import load_taxonomy

def main():
    p=argparse.ArgumentParser();p.add_argument('--root',type=Path,required=True);a=p.parse_args()
    if a.root.exists(): shutil.rmtree(a.root)
    labels=[x['key'] for x in load_taxonomy()['emotions']]
    for directory, subset, source in ((a.root/'go',labels[:15],'go_fixture'),(a.root/'reviewed',labels[15:],'reviewed')):
        directory.mkdir(parents=True,exist_ok=True)
        for split,count in [('train',20),('validation',5),('test',5)]:
            with (directory/f'{split}.jsonl').open('w',encoding='utf-8') as h:
                for label in subset:
                    for i in range(count):
                        sid=f'{source}-{split}-{label}-{i}'
                        h.write(json.dumps({'text':f'fixture {sid}','labels':[label],'observed_labels':[label],
                            'language':'ENGLISH','source_dataset':source,'source_id':sid,'group_id':sid,
                            'synthetic':source=='reviewed'})+'\n')
    print(a.root); return 0
if __name__=='__main__': raise SystemExit(main())
