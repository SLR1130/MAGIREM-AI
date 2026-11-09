"""Create a second, split-balanced review reserve for the seven gap labels.

Rows are synthetic, explicitly marked unreviewed, and are never imported as
ground truth until a reviewer records an accepted label and mask.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

from build_emotion35_review_sheet import FIELDS, stable_id, write_csv

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow" / "review_batch_gap_002.csv"

GAPS = {
    "calmness": {
        "ENGLISH": "I feel calm enough to take this one step at a time",
        "FILIPINO": "Pakiramdam ko ay kalmado ako at kaya kong dahan-dahanin ito",
        "TAGLISH": "Calm lang ako ngayon, kaya kong gawin ito one step at a time",
    },
    "hopelessness": {
        "ENGLISH": "It is hard to imagine anything improving from here",
        "FILIPINO": "Mahirap isipin na may gaganda pa mula rito",
        "TAGLISH": "Parang wala nang mag-iimprove at hindi ko na makita ang way forward",
    },
    "rejection_emotional_hurt": {
        "ENGLISH": "Being turned down left me feeling deeply hurt and unwanted",
        "FILIPINO": "Masakit para sa akin na hindi ako tinanggap",
        "TAGLISH": "Ang sakit ma-reject; pakiramdam ko ay hindi ako welcome",
    },
    "stress": {
        "ENGLISH": "The demands have been weighing on me all week",
        "FILIPINO": "Mabigat ang mga kailangan kong tapusin ngayong linggo",
        "TAGLISH": "Ang daming demands this week at ramdam ko ang stress",
    },
    "overwhelm": {
        "ENGLISH": "There are so many things happening that I do not know where to begin",
        "FILIPINO": "Sabay-sabay ang lahat at hindi ko alam kung saan magsisimula",
        "TAGLISH": "Sobrang dami nang sabay-sabay; hindi ko na alam ang uunahin",
    },
    "insecurity_self_doubt": {
        "ENGLISH": "I keep questioning whether I am capable enough for this",
        "FILIPINO": "Palagi kong iniisip kung kaya ko ba talaga ito",
        "TAGLISH": "Napapaisip ako kung good enough ba talaga ako para dito",
    },
    "boredom": {
        "ENGLISH": "The routine feels flat and I cannot find anything that holds my attention",
        "FILIPINO": "Paulit-ulit ang araw at wala akong makitang kinagigiliwan",
        "TAGLISH": "Same routine lang lagi at parang wala nang interesting sa akin",
    },
}

SUFFIXES = {
    "ENGLISH": [
        "after a quiet morning", "while thinking about school", "even when plans change",
        "as I decide what to do next", "after talking with my family", "during a long study night",
        "when I compare myself with others", "after reading the latest message", "before tomorrow's deadline",
        "and I want to understand this feeling better",
    ],
    "FILIPINO": [
        "habang iniisip ang pag-aaral", "kahit maraming kailangang gawin", "matapos kausapin ang pamilya",
        "habang nagpapahinga sandali", "bago pumasok bukas", "kapag naiisip ko ang susunod na hakbang",
        "matapos kong ihambing ang sarili sa iba", "habang naghihintay ng sagot", "bago ang deadline",
        "at gusto kong maintindihan ang nararamdaman ko",
    ],
    "TAGLISH": [
        "habang iniisip ko ang school", "kahit maraming tasks", "after talking with my family",
        "while taking a short break", "before class tomorrow", "kapag iniisip ko ang next step",
        "when I compare myself sa iba", "habang naghihintay ng reply", "before the deadline",
        "at gusto kong maintindihan kung bakit",
    ],
}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    rows: list[dict[str, str]] = []
    index = 0
    for label, language_bases in GAPS.items():
        for language, base in language_bases.items():
            for offset, suffix in enumerate(SUFFIXES[language]):
                index += 1
                split = "train" if offset < 20 else "validation" if offset < 25 else "test"
                # The proposed split is a reviewer starting point. Reviewers may
                # correct it, but an accepted row must remain source-separated.
                text = f"{base}, {suffix}."
                rows.append({
                    "review_id": stable_id("gap002", label, language, offset),
                    "text": text,
                    "source_dataset": "synthetic_gap_candidates_v2",
                    "source_id": f"gap002-{label}-{language.lower()}-{offset + 1:02d}",
                    "conversation_id": "",
                    "source_split": split,
                    "source_context": "",
                    "proposed_emotion": label,
                    "optional_secondary_emotions": "",
                    "language": language,
                    "review_status": "unreviewed",
                    "reviewer_decision": "",
                    "reviewed_labels": "",
                    "reviewed_observed_labels": "",
                    "rejection_reason": "",
                    "ambiguity_notes": "",
                    "provenance": "Synthetic candidate reserve; requires human semantic, attribution, and split review.",
                    "license": "Internal synthetic example",
                    "group_id": f"synthetic_gap002:{label}:{language.lower()}:{offset + 1:02d}",
                    "synthetic": "true",
                    "annotation_scope": "candidate only; not ground truth",
                    "reviewer_id": "",
                    "reviewed_at": "",
                })
    digest = write_csv(args.output, rows)
    manifest = {
        "schema": "mindcare-emotion35-gap-candidate-reserve-v2",
        "rows": len(rows),
        "rows_per_label": 30,
        "languages_per_label": {"ENGLISH": 10, "FILIPINO": 10, "TAGLISH": 10},
        "proposed_split_per_label": {"train": 20, "validation": 5, "test": 5},
        "review_status": "unreviewed",
        "sha256": digest,
        "note": "Proposed splits are not accepted counts. Only reviewed accepted rows imported with explicit observed_labels can satisfy training coverage.",
    }
    args.with_name(args.name + ".manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(manifest, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
