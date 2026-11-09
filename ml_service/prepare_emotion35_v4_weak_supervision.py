"""Prepare an isolated Emotion35 v4 weak-supervision research corpus.

This path is intentionally separate from the reviewed-data importer.  The
existing v3 corpus is copied byte-for-byte for validation/test, then the
304-candidate bank and a larger multilingual synthetic bank are appended to
training only.  Synthetic rows expose only their weak positive labels unless
an explicit contrastive negative is documented; unmentioned emotions remain
masked and are never treated as verified negatives.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import shutil
from collections import Counter
from pathlib import Path

from prepare_emotion35_prototype_dataset import STEMS

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / "storage/app/audit-recovery/emotion35_prototype_multilabel_v3_20261026"
CANDIDATES = ROOT / "storage/app/audit-recovery/emotion35_v4_ai_candidate_bank_20261103.jsonl"
HOLDOUT = ROOT / "storage/app/audit-recovery/emotion35_independent_eval_20261103.json"
DEFAULT_OUT = ROOT / "storage/app/audit-recovery/emotion35_v4_weak_supervision_20261103"

TAXONOMY = [item["key"] for item in json.loads((ROOT / "config/emotion_taxonomy.json").read_text(encoding="utf-8"))["emotions"]]
LANGUAGES = ("ENGLISH", "FILIPINO", "TAGLISH")
CONTEXTS = {
    "ENGLISH": (
        "while I am preparing for a school decision",
        "after a long day of classes and messages",
        "as I think about the next small step",
    ),
    "FILIPINO": (
        "habang inaayos ko ang susunod kong hakbang sa pag-aaral",
        "pagkatapos ng mahabang araw sa klase at biyahe",
        "habang iniisip ko ang desisyong kailangan kong gawin",
    ),
    "TAGLISH": (
        "habang inaayos ko yung next step sa school",
        "after a long day of classes and messages",
        "habang iniisip ko kung ano ang susunod kong gagawin",
    ),
}

# These examples make the weak negative explicit only where the wording
# intentionally contrasts two related emotions.  All other synthetic rows use
# positive-only observation masks.
CONTRASTS = [
    ("ENGLISH", "I feel energized because the presentation is happening now, not because I am waiting for an uncertain result.", ["excitement"], ["anticipation"]),
    ("FILIPINO", "Masaya at ganado ako dahil nagsisimula na ang event; hindi ako naghihintay ng resulta.", ["excitement"], ["anticipation"]),
    ("TAGLISH", "Excited ako dahil nangyayari na ngayon yung performance, not because I am waiting for news.", ["excitement"], ["anticipation"]),
    ("ENGLISH", "I have a full schedule, but I can still choose one task and finish it before the next.", ["stress"], ["overwhelm"]),
    ("FILIPINO", "Marami akong deadline pero kaya ko pa silang ayusin isa-isa; hindi sabay-sabay na hindi ko na alam ang uunahin.", ["stress"], ["overwhelm"]),
    ("TAGLISH", "Stressful yung workload pero kaya ko pang i-prioritize ang tasks; hindi pa ako totally overwhelmed.", ["stress"], ["overwhelm"]),
    ("ENGLISH", "I regret the harm my choice caused, but I do not feel that my whole identity is bad.", ["guilt"], ["shame"]),
    ("FILIPINO", "Nakokonsensya ako dahil may nasaktan ako, pero hindi ko iniisip na masama akong tao.", ["guilt"], ["shame"]),
    ("TAGLISH", "I feel guilty about the specific mistake, not ashamed of who I am as a person.", ["guilt"], ["shame"]),
    ("ENGLISH", "I am proud of the work I completed; I am not claiming that I can handle every future task easily.", ["pride"], ["confidence"]),
    ("FILIPINO", "Ipinagmamalaki ko ang natapos kong proyekto, pero hindi ibig sabihin na sigurado ako sa lahat ng susunod na gawain.", ["pride"], ["confidence"]),
    ("TAGLISH", "Proud ako sa result ng project, pero hindi ako automatically confident sa bawat bagong challenge.", ["pride"], ["confidence"]),
    ("ENGLISH", "I miss my family and familiar home routines; I am not simply feeling socially alone here.", ["homesickness"], ["loneliness"]),
    ("FILIPINO", "Namimiss ko ang bahay at pamilya ko; hindi naman ibig sabihin na wala akong kasamang kaibigan dito.", ["homesickness"], ["loneliness"]),
    ("TAGLISH", "Homesick ako sa bahay namin, kahit may mga kaibigan naman akong kasama sa dorm.", ["homesickness"], ["loneliness"]),
]

MIXED = [
    ("ENGLISH", "I am proud of my project, but I still doubt whether the result is good enough.", ["pride", "insecurity_self_doubt"]),
    ("FILIPINO", "Masaya ako sa scholarship pero kinakabahan ako sa responsibilidad na kasama nito.", ["happiness_joy", "anxiety"]),
    ("TAGLISH", "I am excited for graduation, pero anxious ako about what happens after.", ["excitement", "anxiety"]),
    ("FILIPINO", "Miss ko ang bahay at nalulungkot ako kapag tahimik ang gabi sa dorm.", ["homesickness", "loneliness"]),
    ("TAGLISH", "Stressful ang workload and I am overwhelmed by the number of choices.", ["stress", "overwhelm"]),
    ("ENGLISH", "I feel relieved that the problem ended, while still worried about what comes next.", ["relief", "anxiety"]),
    ("FILIPINO", "Natuwa ako sa feedback pero nahihiya pa rin ako sa mga mali ko sa presentation.", ["happiness_joy", "shame"]),
    ("TAGLISH", "I feel hopeful about the plan, but disappointed that it took so long.", ["hope_optimism", "disappointment"]),
]


def norm(text: str) -> str:
    return " ".join(text.casefold().split())


def make_row(text: str, language: str, labels: list[str], source_id: str, *, negatives: list[str] | None = None, note: str) -> dict:
    negatives = negatives or []
    observed = list(dict.fromkeys(labels + negatives))
    return {
        "text": text.strip(),
        "labels": labels,
        "observed_labels": observed,
        "weak_positive_labels": labels,
        "weak_negative_labels": negatives,
        "weak_label_policy": "contrastive_weak_negative" if negatives else "positive_only_unknown_negatives",
        "original_labels": [],
        "language": language,
        "source_dataset": "synthetic_ai_assisted_emotion35_v4_research",
        "source_id": source_id,
        "source_group": f"synthetic_ai_assisted_emotion35_v4_research:{source_id}",
        "split": "train",
        "synthetic": True,
        "ai_assisted": True,
        "weak_supervision": True,
        "human_review_required": True,
        "review_status": "unreviewed",
        "training_eligibility": "research_only_weak_supervision",
        "mixed_emotion": len(labels) > 1,
        "negative_annotation_basis": "explicit_contrast_only" if negatives else "unknown_labels_masked",
        "provenance_note": note,
    }


def load_jsonl(path: Path) -> list[dict]:
    return [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]


def repair_inherited_weak_masks(base_rows: list[dict]) -> tuple[list[dict], list[dict]]:
    """Repair legacy weak rows without inventing negative annotations.

    Older synthetic generators wrote the complete taxonomy to
    ``observed_labels``. For weak rows that did not carry an explicit
    contrastive-negative declaration, only the proposed positives are
    supported; every other label must remain unknown.
    """
    repaired = []
    changes = []
    allowed = set(TAXONOMY)
    for row in base_rows:
        current = dict(row)
        raw_labels = list(current.get("labels") or [])
        invalid_labels = set(raw_labels) - allowed
        if invalid_labels:
            raise ValueError(f"invalid taxonomy labels in {current.get('source_id')}: {sorted(invalid_labels)}")
        labels = list(dict.fromkeys(raw_labels))
        if current.get("weak_supervision") and current.get("synthetic"):
            raw_negatives = list(current.get("weak_negative_labels") or [])
            invalid_negatives = set(raw_negatives) - allowed
            if invalid_negatives:
                raise ValueError(f"invalid weak negative labels in {current.get('source_id')}: {sorted(invalid_negatives)}")
            explicit_negatives = list(dict.fromkeys(raw_negatives))
            policy = current.get("weak_label_policy")
            if policy != "contrastive_weak_negative":
                explicit_negatives = []
            expected_observed = list(dict.fromkeys(labels + explicit_negatives))
            observed = list(current.get("observed_labels") or [])
            if set(observed) != set(expected_observed) or len(observed) != len(expected_observed):
                changes.append({
                    "source_id": current.get("source_id"),
                    "source_dataset": current.get("source_dataset"),
                    "previous_observed_count": len(observed),
                    "new_observed_count": len(expected_observed),
                    "reason": "legacy_weak_mask_replaced_with_positive_and_explicit_negative_labels_only",
                })
                current["labels"] = labels
                current["observed_labels"] = expected_observed
                current["weak_positive_labels"] = labels
                current["weak_negative_labels"] = explicit_negatives
                current["weak_label_policy"] = "contrastive_weak_negative" if explicit_negatives else "positive_only_unknown_negatives"
                current["negative_annotation_basis"] = "explicit_contrast_only" if explicit_negatives else "none_unspecified_labels_unknown"
                current["review_status"] = "unreviewed"
                current["human_review_required"] = True
        repaired.append(current)
    return repaired, changes


def validate_weak_records(rows_by_split: dict[str, list[dict]]) -> None:
    """Fail closed on taxonomy, mask, provenance, and split-integrity errors."""
    allowed = set(TAXONOMY)
    seen_text: dict[str, str] = {}
    seen_group: dict[str, str] = {}
    for split, records in rows_by_split.items():
        for row in records:
            source_id = str(row.get("source_id", ""))
            positives = set(row.get("labels") or [])
            observed = set(row.get("observed_labels") or [])
            if not positives.issubset(allowed) or not observed.issubset(allowed):
                raise ValueError(f"non-taxonomy label in {source_id}")
            if not positives.issubset(observed):
                raise ValueError(f"positive label outside observation mask: {source_id}")
            if split == "train" and row.get("weak_supervision") and row.get("synthetic"):
                negatives = set(row.get("weak_negative_labels") or [])
                policy = row.get("weak_label_policy")
                if not negatives.issubset(allowed):
                    raise ValueError(f"non-taxonomy weak negative in {source_id}")
                if policy == "positive_only_unknown_negatives" and negatives:
                    raise ValueError(f"positive-only row carries negatives: {source_id}")
                if policy == "contrastive_weak_negative" and not negatives:
                    raise ValueError(f"contrastive row has no explicit negative: {source_id}")
                expected_observed = positives | negatives
                if observed != expected_observed:
                    raise ValueError(f"weak mask disagrees with positive/negative annotations: {source_id}")
            normalized = norm(str(row.get("text", "")))
            if normalized in seen_text and seen_text[normalized] != split:
                raise ValueError(f"cross-split text leakage: {source_id} vs {normalized}")
            seen_text[normalized] = split
            group = str(row.get("source_group", ""))
            if group and group in seen_group and seen_group[group] != split:
                raise ValueError(f"cross-split source-group leakage: {source_id}")
            if group:
                seen_group[group] = split


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", type=Path, default=BASE)
    parser.add_argument("--candidates", type=Path, default=CANDIDATES)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()
    if args.output.exists():
        raise SystemExit(f"Refusing to overwrite {args.output}")

    shutil.copytree(args.base, args.output)
    train_path = args.output / "train.jsonl"
    base_rows = load_jsonl(train_path)
    base_rows, mask_repairs = repair_inherited_weak_masks(base_rows)
    seen = {norm(row["text"]) for row in base_rows}
    holdout = {norm(item["text"]) for item in json.loads(HOLDOUT.read_text(encoding="utf-8"))}
    additions: list[dict] = []
    rejected: list[dict] = []

    # Existing 304-row candidate bank: retain proposed labels as weak positives,
    # but do not manufacture an all-taxonomy negative mask.
    for item in load_jsonl(args.candidates):
        text = item.get("text", "")
        labels = [label for label in item.get("proposed_labels", []) if label in TAXONOMY]
        key = norm(text)
        if not text or not labels or key in seen or key in holdout:
            rejected.append({"source_id": item.get("source_id"), "reason": "duplicate_or_invalid_or_holdout_overlap"})
            continue
        additions.append(make_row(text, str(item.get("language", "unknown")).upper(), labels, f"bank-{item.get('candidate_id', item.get('source_id'))}", note="Existing 304-row AI-assisted candidate; proposed labels are weak positives only and remain unreviewed."))
        seen.add(key)

    # Two language-diverse variants per taxonomy label.  The source stems are
    # deliberately kept separate from public GoEmotions and remain weak data.
    generated = 0
    for label in TAXONOMY:
        stem_set = STEMS.get(label)
        if not stem_set:
            continue
        for language, stem in zip(LANGUAGES, stem_set):
            for idx, context in enumerate(CONTEXTS[language][:2], 1):
                text = f"{stem.rstrip('.')} {context}."
                key = norm(text)
                if key in seen or key in holdout:
                    continue
                additions.append(make_row(text, language, [label], f"generated-{label}-{language.lower()}-{idx:02d}", note="AI-assisted multilingual expansion; weak positive only, no human approval."))
                seen.add(key)
                generated += 1

    for idx, (language, text, labels, negatives) in enumerate(CONTRASTS, 1):
        key = norm(text)
        if key in seen or key in holdout:
            continue
        additions.append(make_row(text, language, labels, f"contrast-{idx:03d}", negatives=negatives, note="AI-assisted hard contrast; listed negatives are weak negatives only because the text explicitly contrasts them."))
        seen.add(key)

    for idx, (language, text, labels) in enumerate(MIXED, 1):
        key = norm(text)
        if key in seen or key in holdout:
            continue
        additions.append(make_row(text, language, labels, f"mixed-{idx:03d}", note="AI-assisted mixed-emotion candidate; both labels are weak positives and require review."))
        seen.add(key)

    combined_train = base_rows + additions
    rows_by_split = {"train": combined_train}
    for split in ("validation", "test"):
        rows_by_split[split] = load_jsonl(args.output / f"{split}.jsonl")
    validate_weak_records(rows_by_split)
    train_path.write_text("".join(json.dumps(row, ensure_ascii=False) + "\n" for row in combined_train), encoding="utf-8")
    provenance_path = args.output / "provenance.json"
    provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
    provenance.update({
        "version": "emotion35-v4-weak-supervision-20261103",
        "source": "v3 public partial corpus plus 304 existing candidates and multilingual weak-supervision expansion",
        "usage": "research-only; disconnected from Student Chat and Admin analytics",
        "weak_supervision_policy": "positive-only synthetic rows mask unmentioned emotions; explicit contrast rows carry weak negatives; public source masks are preserved",
        "v4_weak_rows_added": len(additions),
        "v4_generated_multilingual_rows": generated,
        "v4_rejected_candidates": rejected,
        "v4_repaired_inherited_masks": mask_repairs,
        "human_reviewed_rows": 0,
        "production_model_untouched": True,
    })
    provenance_path.write_text(json.dumps(provenance, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    summary = {
        "base_dataset": str(args.base),
        "output_dataset": str(args.output),
        "base_train_rows": len(base_rows),
        "weak_rows_added": len(additions),
        "generated_multilingual_rows": generated,
        "rejected_candidates": len(rejected),
        "train_rows": len(base_rows) + len(additions),
        "validation_test_unchanged": True,
        "synthetic_rows": sum(1 for row in base_rows + additions if row.get("synthetic")),
        "weak_positive_only_rows": sum(1 for row in additions if not row.get("weak_negative_labels")),
        "contrastive_rows": sum(1 for row in additions if row.get("weak_negative_labels")),
        "inherited_mask_repairs": len(mask_repairs),
        "languages_added": dict(Counter(row["language"] for row in additions)),
        "labels_added": dict(sorted(Counter(label for row in additions for label in row["labels"]).items())),
        "human_reviewed_rows": 0,
        "production_model_untouched": True,
    }
    (args.output / "v4_weak_supervision_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
