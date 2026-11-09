"""Regression checks for emotion confidence provenance and grief routing.

This uses only the checked-in synthetic multilingual holdout corpus.  It does
not alter model weights or treat model scores as calibrated probabilities.
"""

import contextlib
import io
import json
from pathlib import Path

from semantic_analysis import analyze_semantic_layer
from trained_emotion_classifier import classify_emotion


CONFIDENCE_THRESHOLD = 0.50
GRIEF_PRESERVATION_THRESHOLD = 0.35
GRIEF_PRESERVATION_MARGIN = 0.10


def classify_route(row):
    """Mirror the bounded production route without emitting message content."""
    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
        trained = classify_emotion(row["text"])
        semantic = analyze_semantic_layer(row["text"])

    if trained and trained["confidence"] >= CONFIDENCE_THRESHOLD:
        return trained["emotion"], trained
    if trained and trained["emotion"] == "grief":
        scores = trained.get("probabilities", {}) or {}
        margin = trained["confidence"] - max(
            (float(score) for label, score in scores.items() if label != "grief"),
            default=0.0,
        )
        if (
            trained["confidence"] >= GRIEF_PRESERVATION_THRESHOLD
            and margin >= GRIEF_PRESERVATION_MARGIN
        ):
            return "grief", trained
    return semantic["emotion"]["dominant"], trained


def main():
    corpus = Path(__file__).with_name("emotion_data_multilingual_final_v2") / "test.jsonl"
    rows = [json.loads(line) for line in corpus.read_text(encoding="utf-8").splitlines()]

    baseline_correct = 0
    routed_correct = 0
    changed = 0
    preserved_low_conf_grief = []

    for index, row in enumerate(rows):
        routed, trained = classify_route(row)
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            semantic = analyze_semantic_layer(row["text"])
        baseline = (
            trained["emotion"]
            if trained and trained["confidence"] >= CONFIDENCE_THRESHOLD
            else semantic["emotion"]["dominant"]
        )
        baseline_correct += int(baseline == row["emotion"])
        routed_correct += int(routed == row["emotion"])
        changed += int(routed != baseline)
        if (
            trained
            and trained["emotion"] == "grief"
            and trained["confidence"] < CONFIDENCE_THRESHOLD
            and routed == "grief"
        ):
            preserved_low_conf_grief.append(index)

    assert len(rows) == 96, f"unexpected holdout size: {len(rows)}"
    assert baseline_correct == 64, f"route baseline changed unexpectedly: {baseline_correct}/96"
    assert routed_correct == 68, f"bounded grief route result changed unexpectedly: {routed_correct}/96"
    assert changed == 4, f"unexpected number of bounded route changes: {changed}"
    assert preserved_low_conf_grief, "no low-confidence grief case was preserved"
    assert preserved_low_conf_grief == [28, 34, 58, 77], f"unexpected grief preservation set: {preserved_low_conf_grief}"
    print("EMOTION_CONFIDENCE_ROUTING_REGRESSION=PASS")
    print(f"RAW_ROUTED_BASELINE={baseline_correct}/96")
    print(f"BOUNDED_GRIEF_ROUTED={routed_correct}/96")
    print(f"ROUTING_CHANGES={changed}")
    print(f"GRIEF_PRESERVED={preserved_low_conf_grief}")


if __name__ == "__main__":
    main()
