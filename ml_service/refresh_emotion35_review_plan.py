"""Refresh the private review plan after adding the second gap reserve."""
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'storage'/'app'/'datasets'/'emotion35_review_workflow'/'review_plan.json'
GAPS=['calmness','hopelessness','rejection_emotional_hurt','stress','overwhelm','insecurity_self_doubt','boredom']

def main():
    plan={'schema':'mindcare-emotion35-review-plan-v2','taxonomy_version':'1.0.0',
      'minimum_accepted_per_label_split':{'train':20,'validation':5,'test':5},
      'minimum_accepted_total_per_label':30,'independent_reviewers_recommended':2,
      'targets':[],'note':'Counts are review goals, never training truth. Only accepted importer rows count.'}
    for label in GAPS:
        plan['targets'].append({'target_emotion':label,'seed_rows_existing':15,'additional_reserve_rows':30,
          'candidate_rows_total':45,'proposed_split_reserve':{'train':20,'validation':5,'test':5},
          'accepted_reviews_current':0,'additional_accepted_needed_by_split':{'train':20,'validation':5,'test':5},
          'languages_required':['ENGLISH','FILIPINO','TAGLISH'],'status':'needs_human_review'})
    OUT.write_text(json.dumps(plan,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print(json.dumps(plan,indent=2));return 0
if __name__=='__main__':raise SystemExit(main())
