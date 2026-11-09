"""Generate the isolated prototype after removing duplicated public examples."""
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

_original_mixed = builder._mixed_text
_counter = 0
_contexts = [
    "during a new class project", "before sending an important message", "after checking a deadline", "while preparing for a presentation",
    "as I think about my family", "when I compare two possible plans", "after receiving a short reply", "while setting a personal boundary",
    "as I review my notes", "after a long commute", "while waiting for feedback", "before joining a group discussion",
    "as I start a new routine", "after remembering a difficult day", "while deciding whether to ask for help", "before a weekend visit",
    "as I make a small budget", "after hearing unexpected news", "while practicing a skill", "before a quiet evening",
]
def _unique_mixed(a, b, lang, variant):
    global _counter
    text = _original_mixed(a, b, lang, variant)
    suffix = _contexts[_counter % len(_contexts)]
    _counter += 1
    return text.rstrip(".") + " " + suffix + "."
builder._mixed_text = _unique_mixed

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(builder.DEFAULT_OUT))
    args = parser.parse_args()
    print(json.dumps(builder.build(Path(args.output)), indent=2, ensure_ascii=False))
