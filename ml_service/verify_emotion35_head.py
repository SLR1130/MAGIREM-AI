"""Verify a separate 35-output head can load from the untouched six-class encoder."""
from __future__ import annotations

import argparse
import json
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--init-model', type=Path, default=Path(__file__).resolve().parent / 'emotion_model_multilingual_deployable')
    args = parser.parse_args()
    from transformers import AutoModelForSequenceClassification
    import torch

    labels = json.loads((Path(__file__).resolve().parents[1] / 'config' / 'emotion_taxonomy.json').read_text(encoding='utf-8'))['emotions']
    label_keys = [item['key'] for item in labels]
    original = AutoModelForSequenceClassification.from_pretrained(str(args.init_model), local_files_only=True)
    original_num = int(original.config.num_labels)
    original_encoder = original.state_dict()['roberta.embeddings.word_embeddings.weight'].detach().cpu()
    candidate = AutoModelForSequenceClassification.from_pretrained(
        str(args.init_model), local_files_only=True, num_labels=len(label_keys),
        id2label={i: label for i, label in enumerate(label_keys)},
        label2id={label: i for i, label in enumerate(label_keys)},
        problem_type='multi_label_classification', ignore_mismatched_sizes=True,
    )
    candidate_encoder = candidate.state_dict()['roberta.embeddings.word_embeddings.weight'].detach().cpu()
    encoder_equal = bool(torch.equal(original_encoder, candidate_encoder))
    result = {'initial_num_labels': original_num, 'candidate_num_labels': int(candidate.config.num_labels),
              'taxonomy_labels': len(label_keys), 'problem_type': candidate.config.problem_type,
              'encoder_weights_unchanged': encoder_equal, 'checkpoint_written': False,
              'note': 'This is an in-memory initialization check; no production checkpoint was written.'}
    print(json.dumps(result, indent=2))
    return 0 if original_num == 6 and len(label_keys) == 35 and encoder_equal else 2


if __name__ == '__main__': raise SystemExit(main())
