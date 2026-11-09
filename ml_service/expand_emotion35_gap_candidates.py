"""Create a multilingual, review-only batch for the seven uncovered labels.

The sentences below are synthetic candidate prompts, not labels or ground truth.
They are deliberately varied (direct, indirect, mixed, and contextual) and are
written to a private CSV using the existing review-sheet schema.
"""
from __future__ import annotations

import argparse
import difflib
import json
import re
from collections import Counter
from pathlib import Path
from typing import Any

from build_emotion35_review_sheet import FIELDS, stable_id, write_csv

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT = ROOT / "storage" / "app" / "datasets" / "emotion35_review_workflow" / "review_batch_gap_001.csv"

GAP_EXAMPLES: dict[str, dict[str, list[str]]] = {
    "calmness": {
        "ENGLISH": [
            "After the noise died down, my breathing settled and I felt quietly at ease.",
            "I am not excited or sad today; my mind feels steady.",
            "Sitting by the window gave me a gentle sense that everything was okay for this moment.",
            "The problem is still there, but I can think about it without rushing.",
        ],
        "FILIPINO": [
            "Pagkatapos ng mahabang araw, kumalma ang paghinga ko at naging tahimik ang isip ko.",
            "Hindi man perpekto ang araw, magaan at steady ang pakiramdam ko ngayon.",
            "Habang nakaupo sa tabi ng bintana, parang sapat muna ang katahimikan.",
            "May problema pa rin, pero kaya ko itong isipin nang hindi nagpapanic.",
        ],
        "TAGLISH": [
            "After ko magpahinga, mas calm na ang dibdib ko at hindi na nagmamadali ang isip ko.",
            "Walang malaking nangyari today, pero okay and steady lang ako.",
            "I just sat quietly and felt at ease kahit may pending tasks.",
            "The issue is still there, but I can handle it one step at a time.",
        ],
    },
    "hopelessness": {
        "ENGLISH": [
            "I keep looking for an option, but every path seems closed to me.",
            "Even when someone offers help, I cannot imagine anything changing.",
            "I am tired of trying because the future feels already decided.",
            "I do not see a reason to expect things to improve right now.",
        ],
        "FILIPINO": [
            "Paulit-ulit akong naghahanap ng paraan pero parang sarado lahat ng daan.",
            "Kahit may nag-aalok ng tulong, hindi ko maisip na may magbabago.",
            "Pagod na akong sumubok dahil parang nakatakda nang mabigo ang bukas.",
            "Hindi ko makita kung bakit gagaan pa ang sitwasyon.",
        ],
        "TAGLISH": [
            "I keep making plans pero parang wala ring patutunguhan ang lahat.",
            "May nag-aadvise sa akin, but I cannot picture things getting better.",
            "Suko na ang energy ko kasi parang decided na ang future against me.",
            "I do not see any point in hoping right now kahit gusto kong maniwala.",
        ],
    },
    "rejection_emotional_hurt": {
        "ENGLISH": [
            "They chose everyone else for the group and I felt like I was not wanted.",
            "The invitation disappeared, and I kept wondering what was wrong with me.",
            "Being turned down mattered more than I expected; it left me hurt.",
            "I tried to join the conversation, but they made space for everyone except me.",
        ],
        "FILIPINO": [
            "Hindi nila ako isinama at pakiramdam ko ay hindi nila ako gusto.",
            "Nang hindi nila ako pinili, napaisip ako kung may mali ba sa akin.",
            "Mas masakit pala ang pagtanggi kaysa inaasahan ko.",
            "Sinubukan kong sumali sa usapan pero parang para sa lahat lang iyon.",
        ],
        "TAGLISH": [
            "They said no to me, and parang hindi talaga ako welcome sa group.",
            "Everyone was invited except me, kaya ang sakit at ang awkward.",
            "I applied again but got rejected; it made me question if I belong.",
            "I reached out, pero na-left out pa rin ako.",
        ],
    },
    "stress": {
        "ENGLISH": [
            "The constant deadlines keep my body tense even when I finally sit down.",
            "I am carrying several responsibilities and cannot fully switch off.",
            "The pressure of keeping up is making ordinary tasks feel heavy.",
            "I keep checking the time because the schedule never gives me a break.",
        ],
        "FILIPINO": [
            "Dahil sa sunod-sunod na deadline, naninigas ang katawan ko kahit nagpapahinga.",
            "Marami akong responsibilidad at hindi ko mapahinto ang isip ko.",
            "Mabigat ang pressure na makasabay sa lahat ng gawain.",
            "Paulit-ulit kong tinitingnan ang oras dahil walang pahinga ang schedule.",
        ],
        "TAGLISH": [
            "Back-to-back ang requirements kaya tense ako kahit nakaupo lang.",
            "I have too many responsibilities, hindi ko ma-switch off ang utak ko.",
            "The pressure to keep up makes even small tasks feel heavy.",
            "Always akong nagche-check ng time kasi walang break ang week.",
        ],
    },
    "overwhelm": {
        "ENGLISH": [
            "I have so many tasks, messages, and decisions that I cannot choose where to begin.",
            "Every request arrived at once and my thoughts feel crowded.",
            "The list keeps growing faster than I can finish anything.",
            "I want to respond to everyone, but there is more than I can hold today.",
        ],
        "FILIPINO": [
            "Sabay-sabay ang gawain, mensahe, at desisyon kaya hindi ko alam ang uunahin.",
            "Dumating lahat ng kailangan ko nang sabay at punô ang isip ko.",
            "Mas mabilis lumalaki ang listahan kaysa sa natatapos ko.",
            "Gusto kong sagutin lahat pero sobra na para sa kaya ko ngayon.",
        ],
        "TAGLISH": [
            "Tasks, chats, deadlines—sabay-sabay kaya hindi ko alam saan magsisimula.",
            "Everything came at once and my brain feels full.",
            "The to-do list keeps growing, hindi ko na mahabol.",
            "I want to help everyone pero too much na for one day.",
        ],
    },
    "insecurity_self_doubt": {
        "ENGLISH": [
            "I keep comparing my work with theirs and wonder whether I belong here.",
            "Even after preparing, I doubt that I can explain my idea well.",
            "A small mistake makes me question whether I am capable at all.",
            "I assume everyone notices what I do not know.",
        ],
        "FILIPINO": [
            "Kinukumpara ko ang gawa ko sa kanila at duda akong kabilang ako rito.",
            "Kahit naghanda ako, hindi ako sigurado kung maipapaliwanag ko ito.",
            "Isang maliit na pagkakamali lang at iniisip kong hindi ko kaya.",
            "Pakiramdam ko napapansin ng lahat ang mga hindi ko alam.",
        ],
        "TAGLISH": [
            "I studied naman, pero I keep doubting myself sa recitation.",
            "Their work looks better, kaya feeling ko hindi ako belong.",
            "One mistake happened and bigla kong na-question ang ability ko.",
            "I worry everyone can see how unsure I am.",
        ],
    },
    "boredom": {
        "ENGLISH": [
            "The afternoon feels flat; nothing holds my attention for more than a few minutes.",
            "I keep scrolling without wanting any of the things I see.",
            "The routine is not painful, but it feels empty and repetitive.",
            "I have free time, yet nothing seems interesting enough to start.",
        ],
        "FILIPINO": [
            "Parang mabagal ang oras at walang gustong humawak ng atensyon ko.",
            "Paulit-ulit lang ang ginagawa ko at wala akong gana.",
            "Hindi naman masakit ang routine, pero parang walang buhay at nakakaumay.",
            "May oras ako pero walang bagay na gusto kong simulan.",
        ],
        "TAGLISH": [
            "Bored ako sa routine; kahit may choices, wala akong ma-feel na interesting.",
            "I keep scrolling pero wala talaga akong gustong panoorin.",
            "Nothing is wrong today, pero sobrang repetitive lang.",
            "May free time ako, but I cannot find anything exciting to do.",
        ],
    },
}

SPECIAL_CASES = [
    ("", "", "ENGLISH", "I do not know what I feel today; nothing stands out.", "ambiguous_or_no_emotion"),
    ("", "", "FILIPINO", "Wala naman akong partikular na nararamdaman ngayon.", "ambiguous_or_no_emotion"),
    ("", "", "TAGLISH", "Okay lang ako, pero hindi ko alam kung ano talaga ang nararamdaman ko.", "ambiguous_or_no_emotion"),
    ("", "", "ENGLISH", "My friend feels overwhelmed about finals, but I feel okay.", "other_person_attribution"),
    ("", "", "FILIPINO", "Masaya siya sa resulta; ako naman ay wala pang malinaw na nararamdaman.", "other_person_attribution"),
    ("", "", "TAGLISH", "If I fail the interview, I might feel ashamed, but it has not happened.", "hypothetical_emotion"),
]

MIXED_SECONDARIES = {
    ("calmness", 3): "anxiety",
    ("hopelessness", 2): "sadness",
    ("rejection_emotional_hurt", 2): "anger",
    ("stress", 1): "anxiety",
    ("overwhelm", 0): "excitement",
    ("insecurity_self_doubt", 1): "pride",
    ("boredom", 0): "anxiety",
}


def normalize(text: str) -> str:
    return re.sub(r"\s+", " ", text.casefold().strip())


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()
    rows: list[dict[str, str]] = []
    seen: list[str] = []
    per_label = Counter()
    index = 0

    for label, languages in GAP_EXAMPLES.items():
        for language, texts in languages.items():
            for position, text in enumerate(texts):
                normalized = normalize(text)
                if normalized in seen or any(difflib.SequenceMatcher(None, normalized, previous).ratio() >= 0.93 for previous in seen):
                    raise ValueError(f"duplicate or near-duplicate candidate: {text}")
                seen.append(normalized)
                index += 1
                secondary = MIXED_SECONDARIES.get((label, position), "")
                group = f"synthetic_gap:{label}:{language.lower()}:{position + 1}"
                rows.append({
                    "review_id": stable_id("synthetic_gap", label, language, position, text),
                    "text": text,
                    "source_dataset": "synthetic_gap_candidates",
                    "source_id": f"gap-{index:03d}",
                    "conversation_id": "",
                    "source_split": "review_only",
                    "source_context": "",
                    "proposed_emotion": label,
                    "optional_secondary_emotions": secondary,
                    "language": language,
                    "review_status": "unreviewed",
                    "reviewer_decision": "",
                    "reviewed_labels": "",
                    "reviewed_observed_labels": "",
                    "rejection_reason": "",
                    "ambiguity_notes": "",
                    "provenance": "Synthetic multilingual candidate; proposed label is not ground truth",
                    "license": "Internal synthetic example",
                    "group_id": group,
                    "synthetic": "true",
                    "annotation_scope": "human review required; verify meaning, attribution, mixed emotions, and uncertainty",
                    "reviewer_id": "",
                    "reviewed_at": "",
                })
                per_label[label] += 1

    for position, (primary, secondary, language, text, context) in enumerate(SPECIAL_CASES, start=1):
        normalized = normalize(text)
        if normalized in seen or any(difflib.SequenceMatcher(None, normalized, previous).ratio() >= 0.93 for previous in seen):
            raise ValueError(f"duplicate or near-duplicate special case: {text}")
        seen.append(normalized)
        index += 1
        rows.append({
            "review_id": stable_id("synthetic_special", position, text),
            "text": text,
            "source_dataset": "synthetic_gap_candidates",
            "source_id": f"gap-special-{position:02d}",
            "conversation_id": "",
            "source_split": "review_only",
            "source_context": context,
            "proposed_emotion": primary,
            "optional_secondary_emotions": secondary,
            "language": language,
            "review_status": "unreviewed",
            "reviewer_decision": "",
            "reviewed_labels": "",
            "reviewed_observed_labels": "",
            "rejection_reason": "",
            "ambiguity_notes": "",
            "provenance": "Synthetic ambiguity/attribution candidate; no target label proposed",
            "license": "Internal synthetic example",
            "group_id": f"synthetic_special:{position}",
            "synthetic": "true",
            "annotation_scope": "human review required; test ambiguity, third-person, or hypothetical attribution",
            "reviewer_id": "",
            "reviewed_at": "",
        })

    digest = write_csv(args.output, rows)
    manifest = {
        "schema": "mindcare-emotion35-gap-review-v1",
        "taxonomy_version": "1.0.0",
        "rows": len(rows),
        "target_rows": dict(per_label),
        "special_ambiguity_attribution_rows": len(SPECIAL_CASES),
        "all_unreviewed": True,
        "sha256": digest,
        "output": str(args.output),
        "note": "Synthetic candidates require human review; none are training annotations.",
    }
    args.output.with_suffix(".manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(manifest, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
