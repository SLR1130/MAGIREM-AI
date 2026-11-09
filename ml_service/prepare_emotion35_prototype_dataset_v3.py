"""Run the isolated Emotion35 prototype builder with split deduplication."""
import argparse, json
from pathlib import Path
import prepare_emotion35_prototype_dataset as builder

_seen = set()
_original_reader = builder._read_jsonl
def _dedup_reader(path):
    for record in _original_reader(path):
        key = builder._norm(record.get("text", ""))
        if key in _seen:
            continue
        _seen.add(key)
        yield record
builder._read_jsonl = _dedup_reader

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(builder.DEFAULT_OUT))
    args = parser.parse_args()
    print(json.dumps(builder.build(Path(args.output)), indent=2, ensure_ascii=False))
