"""Compatibility wrapper for the Emotion35 prototype preparation script."""
from prepare_emotion35_prototype_dataset import build, DEFAULT_OUT

if __name__ == "__main__":
    import argparse, json
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(DEFAULT_OUT))
    args = parser.parse_args()
    print(json.dumps(build(__import__('pathlib').Path(args.output)), indent=2, ensure_ascii=False))
