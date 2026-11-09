"""Prepare an isolated Emotion35 research prototype dataset.

This is intentionally *not* a replacement for the reviewed-data workflow.  It
combines the approved, partially observed GoEmotions records with explicitly
marked AI-assisted synthetic examples so the 35-output trainer can be exercised
end-to-end.  Synthetic rows are research-only and are never treated as human
ground truth or wired into Student Chat/Admin analytics.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
from collections import Counter, defaultdict
from pathlib import Path
from difflib import SequenceMatcher

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY = ROOT / "config" / "emotion_taxonomy.json"
GO = ROOT / "storage/app/datasets/emotion35_go_emotions_approved"
DEFAULT_OUT = ROOT / "storage/app/audit-recovery/emotion35_prototype_multilabel_20261026"

ALL_LANGS = ("ENGLISH", "FILIPINO", "TAGLISH")

# Short, deliberately plain expressions make the examples auditable by a
# reviewer.  They are synthetic prompts, not claims about student data.
STEMS = {
    "happiness_joy": ("I feel genuinely happy and light", "Masaya at magaan ang pakiramdam ko", "I feel happy and magaan ang loob ko"),
    "excitement": ("I feel thrilled and energized", "Sobrang excited at ganado ako", "Excited at super ganado ako"),
    "calmness": ("I feel calm and settled", "Kalmado at payapa ang pakiramdam ko", "Calm at settled na ang pakiramdam ko"),
    "contentment": ("I feel quietly satisfied with where I am", "Kontento ako sa kung nasaan ako ngayon", "Contented ako sa progress ko"),
    "hope_optimism": ("I still believe things can get better", "Umaasa pa rin akong gaganda ang mga bagay", "I still have hope na aayos din ito"),
    "pride": ("I feel proud of what I worked for", "Ipinagmamalaki ko ang pinaghirapan ko", "Proud ako sa effort na binigay ko"),
    "curiosity": ("I really want to understand how this works", "Gusto kong malaman kung paano ito gumagana", "Curious ako kung paano ito nagwo-work"),
    "gratitude": ("I feel thankful for the help I received", "Nagpapasalamat ako sa tulong na natanggap ko", "Grateful ako sa tumulong sa akin"),
    "love_affection": ("I feel warm affection for the people close to me", "May pagmamahal at lambing akong nararamdaman sa mga mahal ko", "I feel love and care para sa mga taong malapit sa akin"),
    "relief": ("I feel relieved now that the difficult part is over", "Nakahinga ako nang maluwag dahil tapos na ang mahirap na bahagi", "Relieved ako ngayong nalagpasan na yung mahirap"),
    "confidence": ("I trust my ability to handle this", "May tiwala ako sa kakayahan kong harapin ito", "Confident akong kaya kong i-handle ito"),
    "sadness": ("I feel sad and low today", "Malungkot at mabigat ang pakiramdam ko ngayon", "Sad ako at mabigat ang mood ko today"),
    "grief": ("I am grieving a loss that still hurts", "Nagluluksa ako sa pagkawala na masakit pa rin", "I am grieving pa rin sa pagkawala"),
    "disappointment": ("I feel disappointed that the outcome was different", "Nadismaya ako dahil iba ang kinalabasan", "Disappointed ako kasi hindi nangyari yung inaasahan"),
    "loneliness": ("I feel alone even when people are around", "Pakiramdam ko nag-iisa ako kahit may mga tao sa paligid", "Lonely ako kahit may kasama naman"),
    "homesickness": ("I miss home and the people there", "Nami-miss ko ang bahay at mga taong mahalaga sa akin", "Homesick ako at miss ko na yung bahay"),
    "hopelessness": ("I cannot see a realistic way forward right now", "Hindi ko makita kung paano pa uusad ang mga bagay", "Parang wala nang way forward para sa akin"),
    "rejection_emotional_hurt": ("Being turned away left me feeling hurt and unwanted", "Nasaktan ako nang hindi ako tanggapin", "Hurt ako kasi ni-reject nila ako"),
    "anxiety": ("I feel uneasy and worried about what might happen", "Kinakabahan at nag-aalala ako sa maaaring mangyari", "Anxious ako kung ano ang mangyayari"),
    "fear": ("I feel afraid of the danger I am imagining", "Nat भय ako sa panganib na naiisip ko", "Natatakot ako sa possible na mangyari"),
    "stress": ("I feel under pressure from too many demands", "Nape-pressure ako sa dami ng kailangang gawin", "Stressed ako sa sabay-sabay na tasks"),
    "overwhelm": ("Everything feels like too much for me to hold at once", "Parang sabay-sabay at sobra-sobra ang lahat", "Overwhelmed ako sa lahat ng nangyayari"),
    "anger": ("I feel angry about the unfair way I was treated", "Galit ako sa hindi patas na pagtrato sa akin", "Angry ako sa unfair na ginawa sa akin"),
    "frustration": ("I feel frustrated because my effort is not working", "Nabibigo ako dahil hindi gumagana ang effort ko", "Frustrated ako kasi hindi umuusad kahit nagta-try ako"),
    "jealousy_envy": ("I wish I had the opportunity that person has", "Naiinggit ako sa oportunidad na mayroon siya", "Jealous ako sa chance na nakuha niya"),
    "disgust": ("I feel strong aversion toward what happened", "Nasusuka at nandidiri ako sa nangyari", "Disgusted ako sa ginawa nila"),
    "shame": ("I feel exposed and ashamed of my own mistake", "Nahihiya ako sa sarili kong pagkakamali", "Shame ang nararamdaman ko sa nagawa ko"),
    "guilt": ("I feel responsible and guilty for the harm I caused", "Nakokonsensya ako dahil may nasaktan ako", "Guilty ako sa naging epekto ng ginawa ko"),
    "insecurity_self_doubt": ("I doubt whether I am good enough for this", "Nagdududa ako sa sarili at sa kakayahan ko", "Insecure ako kung kaya ko ba talaga ito"),
    "boredom": ("I feel uninterested because nothing is engaging me", "Nababagot ako dahil walang nakakakuha ng atensyon ko", "Bored ako kasi walang exciting na ginagawa"),
    "confusion": ("I cannot make sense of what I am supposed to do", "Nalilito ako kung ano ang dapat kong gawin", "Confused ako kung ano ba talaga ang next step"),
    "neutral": ("I do not notice a particular emotion right now", "Wala akong matukoy na partikular na emosyon ngayon", "Wala akong strong emotion ngayon"),
    "surprise": ("I was genuinely surprised by the unexpected result", "Nagulat ako sa hindi inaasahang resulta", "Surprised ako sa biglang nangyari"),
    "compassion_empathy": ("I feel concern and care for someone who is hurting", "Nakikiramay at nagmamalasakit ako sa taong nasasaktan", "I feel empathy para sa taong nahihirapan"),
    "anticipation": ("I am looking forward to what is about to happen", "Inaasahan ko ang malapit nang mangyari", "Anticipated ko yung susunod na mangyayari"),
}

SUFFIXES = {
    "ENGLISH": [
        "after thinking about my next semester", "while I make a decision this week", "when I review the changes today",
        "as I prepare for a difficult conversation", "after talking with a trusted person", "while I plan my next small step",
        "as I reflect on what happened yesterday", "when I picture the weeks ahead", "after finishing an important task", "while I give myself time to process",
    ],
    "FILIPINO": [
        "habang iniisip ko ang susunod na semester", "habang nagpapasya ako ngayong linggo", "kapag binabalikan ko ang nangyari ngayon",
        "habang naghahanda ako sa mahirap na usapan", "matapos kong makausap ang taong pinagkakatiwalaan ko", "habang nagpaplano ako ng maliit na susunod na hakbang",
        "habang pinoproseso ko ang nangyari kahapon", "kapag iniisip ko ang mga darating na linggo", "matapos kong tapusin ang mahalagang gawain", "habang binibigyan ko ang sarili ko ng oras",
    ],
    "TAGLISH": [
        "habang iniisip ko yung next semester", "while I decide what to do this week", "kapag nire-review ko yung nangyari today",
        " habang nagpe-prepare ako for a hard conversation", "after kong makausap yung trusted person ko", "while nagpa-plan ako ng next small step",
        "habang pinoprocess ko yung nangyari yesterday", "when I picture the weeks ahead", "after kong matapos yung important task", "while binibigyan ko yung sarili ko ng time",
    ],
}

MIX_TERMS = {
    "happiness_joy": ("happy", "masaya", "happy"), "excitement": ("excited", "excited", "excited"), "calmness": ("calm", "kalmado", "calm"),
    "contentment": ("content", "kontento", "contented"), "hope_optimism": ("hopeful", "umaasa", "hopeful"), "pride": ("proud", "proud", "proud"),
    "curiosity": ("curious", "curious", "curious"), "gratitude": ("grateful", "nagpapasalamat", "grateful"), "love_affection": ("loving", "mapagmahal", "loving"),
    "relief": ("relieved", "nakahinga", "relieved"), "confidence": ("confident", "may tiwala", "confident"), "sadness": ("sad", "malungkot", "sad"),
    "grief": ("grieving", "nagluluksa", "grieving"), "disappointment": ("disappointed", "nadismaya", "disappointed"), "loneliness": ("lonely", "nag-iisa", "lonely"),
    "homesickness": ("homesick", "nami-miss ang bahay", "homesick"), "hopelessness": ("hopeless", "walang pag-asa", "hopeless"),
    "rejection_emotional_hurt": ("hurt by rejection", "nasaktan sa pagtanggi", "hurt sa rejection"), "anxiety": ("anxious", "kinakabahan", "anxious"),
    "fear": ("afraid", "natatakot", "afraid"), "stress": ("stressed", "nai-stress", "stressed"), "overwhelm": ("overwhelmed", "nabibigatan", "overwhelmed"),
    "anger": ("angry", "galit", "angry"), "frustration": ("frustrated", "naiinis", "frustrated"), "jealousy_envy": ("envious", "naiinggit", "jealous"),
    "disgust": ("disgusted", "nandidiri", "disgusted"), "shame": ("ashamed", "nahihiya", "ashamed"), "guilt": ("guilty", "nakokonsensya", "guilty"),
    "insecurity_self_doubt": ("insecure", "nagdududa sa sarili", "insecure"), "boredom": ("bored", "nababagot", "bored"),
    "confusion": ("confused", "nalilito", "confused"), "neutral": ("neutral", "walang partikular na emosyon", "neutral"), "surprise": ("surprised", "nagulat", "surprised"),
    "compassion_empathy": ("compassionate", "nakikiramay", "empathetic"), "anticipation": ("anticipating", "umaabangan", "anticipating"),
}

MIX_PAIRS = [
    ("excitement", "anxiety"), ("stress", "overwhelm"), ("shame", "guilt"), ("pride", "confidence"),
    ("sadness", "homesickness"), ("hope_optimism", "fear"), ("loneliness", "rejection_emotional_hurt"), ("anger", "frustration"),
    ("happiness_joy", "gratitude"), ("anticipation", "insecurity_self_doubt"), ("compassion_empathy", "sadness"), ("surprise", "excitement"),
    ("contentment", "calmness"), ("boredom", "confusion"), ("grief", "love_affection"), ("relief", "hope_optimism"),
    ("curiosity", "anticipation"), ("disgust", "anger"), ("disappointment", "guilt"), ("confidence", "fear"),
]


def _read_jsonl(path: Path):
    with path.open(encoding="utf-8") as handle:
        for line in handle:
            if line.strip():
                yield json.loads(line)


def _norm(text: str) -> str:
    return re.sub(r"\s+", " ", re.sub(r"[^\w\s]", "", text.casefold())).strip()


def _sha(text: str) -> str:
    return hashlib.sha256(_norm(text).encode("utf-8")).hexdigest()


def _synthetic_row(text, labels, language, split, row_id, mixed=False):
    labels = list(dict.fromkeys(labels))
    return {
        "text": text.strip(), "labels": labels, "observed_labels": labels,
        "language": language, "source_dataset": "synthetic_ai_assisted_emotion35_prototype",
        "source_id": row_id, "source_group": row_id, "split": split,
        "synthetic": True, "ai_assisted": True, "weak_supervision": True,
        "human_review_required": True, "review_status": "unreviewed",
        "training_eligibility": "research_only_synthetic", "mixed_emotion": mixed,
        "weak_positive_labels": labels, "weak_negative_labels": [],
        "weak_label_policy": "positive_only_unknown_negatives",
        "negative_annotation_basis": "none_unspecified_labels_unknown",
        "provenance_note": "AI-assisted synthetic example; not human-reviewed ground truth.",
    }


def _single_text(label, lang, index, split):
    stem = STEMS[label][ALL_LANGS.index(lang)]
    suffix = SUFFIXES[lang][index]
    if split == "train":
        return f"{stem} {suffix}."
    if split == "validation":
        return f"{suffix[:1].upper() + suffix[1:]}, and {stem.lower()}."
    return f"When I think about {suffix}, I notice that {stem.lower()}."


def _mixed_text(a, b, lang, index):
    ta, tb = MIX_TERMS[a][ALL_LANGS.index(lang)], MIX_TERMS[b][ALL_LANGS.index(lang)]
    if lang == "ENGLISH":
        forms = [f"I feel {ta} about the opportunity, but also {tb} about what follows.", f"The good news makes me {ta}; at the same time, I am {tb}.", f"Part of me is {ta}, while another part feels {tb}."]
    elif lang == "FILIPINO":
        forms = [f"{ta.capitalize()} ako sa oportunidad, pero {tb} din sa susunod.", f"Masaya ang balita pero {tb} pa rin ang isa kong nararamdaman; {ta} ako sa unang bahagi.", f"May bahagi sa akin na {ta}, habang may bahagi ring {tb}."]
    else:
        forms = [f"{ta.capitalize()} ako sa opportunity, pero {tb} din sa mangyayari.", f"Good news siya kaya {ta} ako, pero {tb} pa rin ako sa next part.", f"Part of me is {ta}, pero another part feels {tb}."]
    return forms[index % 3]


def build(output: Path) -> dict:
    taxonomy = json.loads(TAXONOMY.read_text(encoding="utf-8"))
    global TAXONOMY_KEYS
    TAXONOMY_KEYS = [item["key"] for item in taxonomy["emotions"]]
    if set(STEMS) != set(TAXONOMY_KEYS):
        raise RuntimeError("synthetic stem coverage does not match taxonomy")
    output.mkdir(parents=True, exist_ok=False)
    rows = {"train": [], "validation": [], "test": []}
    source_counts = Counter()
    seen_public_text: dict[str, str] = {}
    public_duplicates: list[dict] = []

    # Preserve the approved GoEmotions rows and their partial observation masks.
    for split in rows:
        for record in _read_jsonl(GO / f"{split}.jsonl"):
            normalized_text = _norm(record["text"])
            if normalized_text in seen_public_text:
                public_duplicates.append({
                    "source_id": record.get("source_id", record.get("id")),
                    "kept_split": seen_public_text[normalized_text],
                    "excluded_split": split,
                    "reason": "normalized_public_text_duplicate_across_splits",
                })
                source_counts["public_duplicates_excluded"] += 1
                continue
            seen_public_text[normalized_text] = split
            row = dict(record)
            row["source_group"] = f"go_emotions:{record.get('source_id', record.get('id', _sha(record['text'])))}"
            row["split"] = split
            row["synthetic"] = False
            row["ai_assisted"] = False
            row["weak_supervision"] = False
            row["human_review_required"] = False
            row["review_status"] = "source_annotated"
            row["training_eligibility"] = "research_public_source"
            rows[split].append(row)
            source_counts["go_emotions"] += 1

    # Thirty single-emotion synthetic rows per label (20/5/5) across languages.
    for label in TAXONOMY_KEYS:
        for lang in ALL_LANGS:
            for index in range(10):
                split = "train" if index < 7 else "validation" if index < 9 else "test"
                # Distribute the three language groups slightly differently so
                # each split has varied language coverage.
                if lang == "FILIPINO" and index == 6:
                    split = "validation"
                if lang == "TAGLISH" and index == 7:
                    split = "train"
                row_id = f"syn-single-{label}-{lang.lower()}-{index:02d}"
                rows[split].append(_synthetic_row(_single_text(label, lang, index, split), [label], lang, split, row_id))
                source_counts["synthetic_single"] += 1

    # Three language variants per pair, with separate held-out examples.
    mixed_index = 0
    for pair_index, (a, b) in enumerate(MIX_PAIRS):
        for variant in range(3):
            lang = ALL_LANGS[variant]
            split = ("train", "validation", "test")[(pair_index + variant) % 3]
            row_id = f"syn-mixed-{pair_index:02d}-{lang.lower()}-{variant:02d}"
            rows[split].append(_synthetic_row(_mixed_text(a, b, lang, variant), [a, b], lang, split, row_id, mixed=True))
            source_counts["synthetic_mixed"] += 1
            mixed_index += 1

    # Remove later near-duplicate synthetic variants across splits rather than
    # allowing template leakage into validation/test. The first deterministic
    # occurrence is retained and every exclusion is recorded in provenance.
    synthetic_kept: list[dict] = []
    synthetic_near_exclusions: list[dict] = []
    for split in ("train", "validation", "test"):
        filtered = []
        for record in rows[split]:
            if record.get("synthetic"):
                match = next(
                    (old for old in synthetic_kept
                     if old["split"] != split
                     and SequenceMatcher(None, _norm(record["text"]), _norm(old["text"])).ratio() >= 0.96),
                    None,
                )
                if match is not None:
                    synthetic_near_exclusions.append({
                        "source_id": record.get("source_id"),
                        "kept_source_id": match.get("source_id"),
                        "reason": "near_duplicate_synthetic_template_across_splits",
                    })
                    source_counts["synthetic_near_duplicates_excluded"] += 1
                    continue
                synthetic_kept.append(record)
            filtered.append(record)
        rows[split] = filtered

    # Deterministic quality checks before writing.
    allowed = set(TAXONOMY_KEYS)
    seen_by_split = defaultdict(set)
    for split, values in rows.items():
        for record in values:
            if not record["text"].strip() or not set(record["labels"]).issubset(allowed):
                raise RuntimeError(f"invalid row in {split}: {record.get('source_id')}")
            if not set(record["labels"]).issubset(set(record["observed_labels"])):
                raise RuntimeError(f"positive label outside observation mask: {record['source_id']}")
            seen_by_split[split].add(_norm(record["text"]))
    overlap = set(seen_by_split["train"]) & set(seen_by_split["validation"]) | set(seen_by_split["train"]) & set(seen_by_split["test"]) | set(seen_by_split["validation"]) & set(seen_by_split["test"])
    if overlap:
        raise RuntimeError(f"exact text leakage across splits ({len(overlap)})")
    for split, values in rows.items():
        with (output / f"{split}.jsonl").open("w", encoding="utf-8") as handle:
            for record in values:
                handle.write(json.dumps(record, ensure_ascii=False) + "\n")
    provenance = {
        "source": "GoEmotions approved mappings plus controlled AI-assisted synthetic Emotion35 prototype examples",
        "license": "GoEmotions: Apache-2.0; synthetic examples: generated locally for research only",
        "usage": "research-only prototype; not production, not human-validated ground truth",
        "curator": "MinSuansCore research pipeline (automated preparation)",
        "version": "emotion35-prototype-20261026-v1",
        "taxonomy_version": taxonomy.get("version"),
        "public_source": "storage/app/datasets/emotion35_go_emotions_approved",
        "public_duplicates_excluded": public_duplicates,
        "synthetic_near_duplicates_excluded": synthetic_near_exclusions,
        "synthetic_rows": sum(source_counts[k] for k in ("synthetic_single", "synthetic_mixed")),
        "weak_label_policy": "synthetic rows observe intended positives only; explicit contrast negatives are observed only when documented; all other labels remain unknown",
        "human_reviewed_rows": 0,
        "production_model_untouched": True,
    }
    (output / "provenance.json").write_text(json.dumps(provenance, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    counts = {split: len(values) for split, values in rows.items()}
    label_counts = {split: dict(sorted(Counter(label for r in values for label in r["labels"]).items())) for split, values in rows.items()}
    summary = {"records": counts, "source_counts": dict(source_counts), "label_counts": label_counts, "taxonomy_version": taxonomy.get("version"), "exact_cross_split_duplicates": len(public_duplicates), "synthetic_near_duplicates": len(synthetic_near_exclusions), "human_reviewed_rows": 0, "production_model_untouched": True}
    (output / "prototype_summary.json").write_text(json.dumps(summary, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    return summary


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()
    if args.output.exists():
        raise SystemExit(f"Refusing to overwrite {args.output}")
    print(json.dumps(build(args.output), indent=2, ensure_ascii=False))
