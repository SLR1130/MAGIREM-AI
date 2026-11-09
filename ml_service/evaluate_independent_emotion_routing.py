"""Score the unchanged routing policy on a separately labeled synthetic set.

The thresholds are fixed from the prior development corpus. This evaluator is
reporting-only and must not be used to select or update those thresholds.
"""

import contextlib
import io
import json
from collections import Counter, defaultdict
from pathlib import Path

from semantic_analysis import analyze_semantic_layer
from trained_emotion_classifier import classify_emotion


LABELS = ["sadness", "grief", "anxiety", "anger", "joy", "neutral"]
CONFIDENCE_THRESHOLD = 0.50
GRIEF_PRESERVATION_THRESHOLD = 0.35
GRIEF_PRESERVATION_MARGIN = 0.10
CANONICAL_LABELS = {"happiness": "joy", "happy": "joy", "sad": "sadness"}


def canonical(label):
    return CANONICAL_LABELS.get(label, label)


def analyze_without_content_logging(text):
    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
        trained = classify_emotion(text)
        semantic = analyze_semantic_layer(text)
    return trained, semantic


def route(trained, semantic):
    if trained and trained["confidence"] >= CONFIDENCE_THRESHOLD:
        return trained["emotion"], "trained_model"

    if trained and trained["emotion"] == "grief":
        scores = trained.get("probabilities", {}) or {}
        margin = trained["confidence"] - max(
            (float(value) for label, value in scores.items() if label != "grief"),
            default=0.0,
        )
        if (
            trained["confidence"] >= GRIEF_PRESERVATION_THRESHOLD
            and margin >= GRIEF_PRESERVATION_MARGIN
        ):
            return "grief", "trained_model_low_confidence_grief"

    return canonical(semantic["emotion"]["dominant"]), "semantic_fallback"


def metrics(rows, prediction_key):
    result = {}
    for label in LABELS:
        tp = sum(row["gold"] == label and row[prediction_key] == label for row in rows)
        fp = sum(row["gold"] != label and row[prediction_key] == label for row in rows)
        fn = sum(row["gold"] == label and row[prediction_key] != label for row in rows)
        precision = tp / (tp + fp) if tp + fp else 0.0
        recall = tp / (tp + fn) if tp + fn else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if precision + recall else 0.0
        result[label] = (precision, recall, f1)
    return result


def confusion(rows, prediction_key):
    matrix = {gold: Counter() for gold in LABELS}
    for row in rows:
        matrix[row["gold"]][row[prediction_key]] += 1
    return matrix


def main():
    corpus_path = Path(__file__).with_name("independent_emotion_routing_20261019.jsonl")
    corpus = [json.loads(line) for line in corpus_path.read_text(encoding="utf-8").splitlines()]
    rows = []
    route_counts = Counter()

    for item in corpus:
        trained, semantic = analyze_without_content_logging(item["text"])
        routed, source = route(trained, semantic)
        route_counts[source] += 1
        rows.append({
            "id": item["id"],
            "language": item["language"],
            "gold": item["emotion"],
            "raw": canonical(trained["emotion"]) if trained else "neutral",
            "routed": routed,
            "source": source,
            "raw_confidence": float(trained["confidence"]) if trained else None,
            "semantic_confidence": float(semantic["emotion"]["confidence"]),
        })

    assert len(rows) == 36
    raw_correct = sum(row["gold"] == row["raw"] for row in rows)
    routed_correct = sum(row["gold"] == row["routed"] for row in rows)

    print("INDEPENDENT_EMOTION_ROUTING_EVALUATION=PASS")
    print(f"SAMPLES={len(rows)} LANGUAGES=ENGLISH,TAGALOG,TAGLISH")
    print(f"RAW_ACCURACY={raw_correct}/{len(rows)} ({raw_correct / len(rows):.4f})")
    print(f"ROUTED_ACCURACY={routed_correct}/{len(rows)} ({routed_correct / len(rows):.4f})")
    print(f"ROUTE_COUNTS={dict(route_counts)}")
    for language in ("ENGLISH", "TAGALOG", "TAGLISH"):
        subset = [row for row in rows if row["language"] == language]
        raw_language = sum(row["gold"] == row["raw"] for row in subset)
        routed_language = sum(row["gold"] == row["routed"] for row in subset)
        print(
            f"{language}_ACCURACY raw={raw_language}/{len(subset)} "
            f"routed={routed_language}/{len(subset)}"
        )

    for name, key in (("RAW", "raw"), ("ROUTED", "routed")):
        print(f"{name}_PER_CLASS")
        for label, (precision, recall, f1) in metrics(rows, key).items():
            print(f"{label}: precision={precision:.3f} recall={recall:.3f} f1={f1:.3f}")
        print(f"{name}_CONFUSION")
        for gold, predictions in confusion(rows, key).items():
            print(f"{gold}: {dict(predictions)}")

    fallback = [row for row in rows if row["source"] == "semantic_fallback"]
    exact_one = sum(row["semantic_confidence"] == 1.0 for row in fallback)
    print(f"SEMANTIC_FALLBACK_CONFIDENCE_ONE={exact_one}/{len(fallback)}")


if __name__ == "__main__":
    main()
