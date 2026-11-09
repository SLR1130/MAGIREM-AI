"""Train an isolated Emotion35 v2 research checkpoint with masked class weighting.

This keeps the existing 35-label architecture and observed-label masking.  The
only objective correction is a bounded positive-class weight computed from
observed training labels; unobserved source labels never enter the loss.
"""
from __future__ import annotations
import argparse, json, random, shutil, time
from pathlib import Path
import numpy as np

from emotion_35_multilabel_pipeline import (
    EXPECTED_SPLITS, DatasetAuditError, _jsonl_path, _labels, _observed_labels,
    _records, _text, audit_dataset, load_taxonomy, masked_bce_with_logits,
)
from emotion35_calibration import calibrate_validation, sha256_file

def main(args):
    provenance=json.loads(args.provenance.read_text(encoding="utf-8"))
    audit=audit_dataset(args.dataset, provenance=provenance)
    if not audit.sufficient_for_training: raise DatasetAuditError(json.dumps(audit.to_dict(),indent=2))
    if args.output.exists(): raise DatasetAuditError(f"refusing to overwrite {args.output}")
    from datasets import Dataset, DatasetDict
    from transformers import AutoModelForSequenceClassification, AutoTokenizer, Trainer, TrainingArguments, set_seed
    import torch
    from sklearn.metrics import f1_score
    taxonomy=load_taxonomy(); labels=[e["key"] for e in taxonomy["emotions"]]; label_to_id={x:i for i,x in enumerate(labels)}
    seed=args.seed; set_seed(seed); random.seed(seed); np.random.seed(seed); torch.manual_seed(seed)
    rows_by_split={}; masks={}; targets={}; raw_rows={}; pos=np.zeros(len(labels),dtype=np.float64); neg=np.zeros(len(labels),dtype=np.float64)
    for split in EXPECTED_SPLITS:
        rows=[]; split_masks=[]; split_targets=[]; split_raw_rows=[]
        for record in _records(_jsonl_path(args.dataset,split)):
            observed=_observed_labels(record)
            # A missing or empty annotation mask is unknown, never an implicit
            # all-negative supervision signal. Complete sources must say so.
            observed=set(observed or [])
            positives=set(_labels(record))
            if positives-observed: raise DatasetAuditError("positive targets must be present in observed_labels")
            vector=[1.0 if x in positives else 0.0 for x in labels]; mask=[1.0 if x in observed else 0.0 for x in labels]
            rows.append({"text":_text(record),"labels":vector,"label_mask":mask}); split_targets.append(vector); split_masks.append(mask)
            split_raw_rows.append(record)
            if split=="train":
                for i,m in enumerate(mask):
                    if m: (pos if vector[i] else neg)[i]+=1
        rows_by_split[split]=Dataset.from_list(rows); masks[split]=np.asarray(split_masks,dtype=np.float32); targets[split]=np.asarray(split_targets,dtype=np.float32); raw_rows[split]=split_raw_rows
    # Capped sqrt weighting keeps rare classes visible without recreating the
    # previous all-positive collapse. Observed negatives remain fully active.
    raw=np.sqrt((neg+1.0)/(pos+1.0)); pos_weight=np.clip(raw,1.0,args.max_pos_weight).astype(np.float32)
    encoded=DatasetDict({s:rows_by_split[s] for s in EXPECTED_SPLITS})
    tokenizer=AutoTokenizer.from_pretrained(str(args.init_model),local_files_only=True)
    model=AutoModelForSequenceClassification.from_pretrained(str(args.init_model),local_files_only=True,num_labels=len(labels),id2label={i:x for i,x in enumerate(labels)},label2id=label_to_id,problem_type="multi_label_classification",ignore_mismatched_sizes=True)
    def tokenize(batch): return tokenizer(batch["text"],truncation=True,max_length=256)
    encoded=encoded.map(tokenize,batched=True,remove_columns=["text"])
    validation_mask=masks["validation"]
    def parts(pred):
        logits=pred.predictions if hasattr(pred,"predictions") else pred[0]; target=pred.label_ids if hasattr(pred,"label_ids") else pred[1]
        if isinstance(logits,tuple): logits=logits[0]
        return np.asarray(logits),np.asarray(target)
    def metric_fn(pred):
        logits,target=parts(pred); prob=1/(1+np.exp(-np.clip(logits,-50,50))); mask=validation_mask if len(target)==len(validation_mask) else masks["test"]; tp=fp=fn=0; f=[]
        for i in range(len(labels)):
            valid=mask[:,i]>0
            if not valid.any(): continue
            p=(prob[valid,i]>=.5).astype(int); t=target[valid,i].astype(int); tp+=int(((t==1)&(p==1)).sum()); fp+=int(((t==0)&(p==1)).sum()); fn+=int(((t==1)&(p==0)).sum()); f.append(f1_score(t,p,zero_division=0))
        pr=tp/(tp+fp) if tp+fp else 0; re=tp/(tp+fn) if tp+fn else 0
        return {"micro_precision":pr,"micro_recall":re,"micro_f1":2*pr*re/(pr+re) if pr+re else 0,"macro_f1":float(np.mean(f)) if f else 0}
    class WeightedMaskedTrainer(Trainer):
        def compute_loss(self, model, inputs, return_outputs=False, num_items_in_batch=None):
            labels_tensor=inputs.pop("labels"); mask_tensor=inputs.pop("label_mask",None); outputs=model(**inputs)
            mask_tensor=mask_tensor if mask_tensor is not None else torch.zeros_like(labels_tensor)
            weights=torch.as_tensor(pos_weight,device=labels_tensor.device,dtype=labels_tensor.dtype)
            element=torch.nn.functional.binary_cross_entropy_with_logits(outputs.logits,labels_tensor,reduction="none",pos_weight=weights)
            loss=(element*mask_tensor).sum()/mask_tensor.sum().clamp_min(1.0)
            return (loss,outputs) if return_outputs else loss
    staging=args.output.parent/(f".{args.output.name}.staging-{__import__('os').getpid()}"); staging.mkdir(parents=True,exist_ok=False)
    start=time.perf_counter(); peak=0
    try:
        kwargs=dict(output_dir=str(staging/"checkpoints"),learning_rate=args.learning_rate,per_device_train_batch_size=args.batch_size,per_device_eval_batch_size=args.batch_size,num_train_epochs=args.epochs,weight_decay=.01,seed=seed,logging_strategy="epoch",save_strategy="epoch",load_best_model_at_end=True,metric_for_best_model="eval_macro_f1",greater_is_better=True,report_to=[],remove_unused_columns=False,fp16=bool(torch.cuda.is_available()))
        try: train_args=TrainingArguments(evaluation_strategy="epoch",**kwargs)
        except TypeError: train_args=TrainingArguments(eval_strategy="epoch",**kwargs)
        trainer_kwargs=dict(model=model,args=train_args,train_dataset=encoded["train"],eval_dataset=encoded["validation"],compute_metrics=metric_fn)
        try: trainer=WeightedMaskedTrainer(tokenizer=tokenizer,**trainer_kwargs)
        except TypeError: trainer=WeightedMaskedTrainer(processing_class=tokenizer,**trainer_kwargs)
        trainer.train()
        if torch.cuda.is_available(): peak=torch.cuda.max_memory_allocated()
        vp=trainer.predict(encoded["validation"]); vlogits,vtarget=parts(vp); vprob=1/(1+np.exp(-np.clip(vlogits,-50,50)))
        trainer.save_model(str(staging/"model")); tokenizer.save_pretrained(str(staging/"model"))
        calibration=calibrate_validation(
            raw_rows["validation"], vprob, labels, taxonomy_version=audit.taxonomy_version,
            checkpoint_id=args.output.name, checkpoint_sha256=sha256_file(staging/"model"/"model.safetensors"),
            validation_sha256=sha256_file(_jsonl_path(args.dataset,"validation")),
        )
        thresholds=calibration["thresholds"]
        tp=trainer.predict(encoded["test"]); tlogits,ttarget=parts(tp); tprob=1/(1+np.exp(-np.clip(tlogits,-50,50))); tm=masks["test"]; tpos=tpr=tfn=0; per=[]; calibrated_test_labels=[]
        for i,label in enumerate(labels):
            threshold=thresholds[label]
            if threshold is None: continue
            calibrated_test_labels.append(label)
            valid=tm[:,i]>0; p=(tprob[valid,i]>=threshold).astype(int); t=ttarget[valid,i].astype(int); a=int(((t==1)&(p==1)).sum()); b=int(((t==0)&(p==1)).sum()); c=int(((t==1)&(p==0)).sum()); tpos+=a; tpr+=b; tfn+=c; per.append(float(f1_score(t,p,zero_division=0)))
        precision=tpos/(tpos+tpr) if tpos+tpr else 0; recall=tpos/(tpos+tfn) if tpos+tfn else 0
        (staging/"model"/"label_mapping.json").write_text(json.dumps({"taxonomy_version":audit.taxonomy_version,"labels":labels,"label_to_id":label_to_id},indent=2)+"\n")
        (staging/"model"/"thresholds.json").write_text(json.dumps(calibration,indent=2)+"\n")
        (staging/"model"/"dataset_manifest.json").write_text(json.dumps({"dataset_dir":str(args.dataset),"audit":audit.to_dict()},indent=2)+"\n")
        config={"seed":seed,"epochs":args.epochs,"learning_rate":args.learning_rate,"batch_size":args.batch_size,"masked_loss":True,"positive_weighting":"sqrt_observed_neg_over_pos_capped","max_pos_weight":args.max_pos_weight,"pos_counts":pos.tolist(),"neg_counts":neg.tolist(),"cuda":bool(torch.cuda.is_available()),"gpu_name":torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,"peak_cuda_memory_bytes":int(peak),"production_model_untouched":True,"prototype_only":True}
        (staging/"model"/"training_config.json").write_text(json.dumps(config,indent=2)+"\n")
        metrics={"test_micro_precision_masked_calibrated_labels_only":precision,"test_micro_recall_masked_calibrated_labels_only":recall,"test_micro_f1_masked_calibrated_labels_only":2*precision*recall/(precision+recall) if precision+recall else 0,"test_macro_f1_masked_calibrated_labels_only":float(np.mean(per)) if per else 0,"test_observed_targets":int(tm.sum()),"calibrated_labels":calibrated_test_labels,"uncalibrated_labels":[label for label in labels if thresholds[label] is None],"training_wall_seconds":time.perf_counter()-start}
        (staging/"model"/"metrics.json").write_text(json.dumps(metrics,indent=2)+"\n")
        (staging/"model"/"prototype_only.json").write_text(json.dumps({"prototype_only":True,"student_chat_enabled":False,"admin_analytics_enabled":False,"human_reviewed":False},indent=2)+"\n")
        shutil.move(str(staging/"model"),str(args.output)); print(json.dumps({"trained":True,"output":str(args.output),"cuda":bool(torch.cuda.is_available()),"gpu":torch.cuda.get_device_name(0) if torch.cuda.is_available() else None,"peak_cuda_memory_bytes":int(peak),"metrics":metrics},indent=2))
    finally: shutil.rmtree(staging,ignore_errors=True)

if __name__=="__main__":
    p=argparse.ArgumentParser(); p.add_argument("--dataset",dest="dataset",type=Path,required=True); p.add_argument("--provenance",type=Path,required=True); p.add_argument("--output",type=Path,required=True); p.add_argument("--init-model",type=Path,required=True); p.add_argument("--epochs",type=float,default=2.0); p.add_argument("--batch-size",type=int,default=8); p.add_argument("--learning-rate",type=float,default=2e-5); p.add_argument("--max-pos-weight",type=float,default=6.0); p.add_argument("--seed",type=int,default=20261026); main(p.parse_args())
