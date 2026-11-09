"""Prepare an isolated Emotion35 v3 training-only augmentation.

This script deliberately leaves the v2 validation/test files byte-for-byte
unchanged.  It removes only malformed synthetic training rows and adds a
small, hand-authored research-only bank with more natural Filipino and
Taglish phrasing, hard distinctions between related emotions, and a few
genuine mixed examples.  Every added record remains unreviewed synthetic
data and is excluded from any production path.
"""
from __future__ import annotations

import argparse
import json
import re
import shutil
from pathlib import Path
from difflib import SequenceMatcher

TAXONOMY = [
    "happiness_joy", "excitement", "calmness", "contentment", "hope_optimism",
    "pride", "curiosity", "gratitude", "love_affection", "relief", "confidence",
    "sadness", "grief", "disappointment", "loneliness", "homesickness",
    "hopelessness", "rejection_emotional_hurt", "anxiety", "fear", "stress",
    "overwhelm", "anger", "frustration", "jealousy_envy", "disgust", "shame",
    "guilt", "insecurity_self_doubt", "boredom", "confusion", "neutral", "surprise",
    "compassion_empathy", "anticipation",
]

# Four intentionally different expressions per language for the priority
# confusion pairs.  These are candidates, not human-reviewed ground truth.
BANK: dict[str, dict[str, list[str]]] = {
    "shame": {
        "FILIPINO": [
            "Gusto kong magtago matapos kong mapahiya sa harap ng klase; ayokong balikan ang eksena.",
            "Kapag naaalala ko ang pagkakamali ko, parang lumiit ako at ayaw kong makipag-usap.",
            "Nahihiya akong harapin ang groupmates ko dahil ako ang nakasira sa presentation.",
            "Hindi ko masabi sa bahay ang nangyari; sobrang hiya ko sa sarili ko.",
        ],
        "TAGLISH": [
            "I want to disappear after that mistake in class; ang hirap tumingin sa kanila.",
            "Every time I remember what I said, I feel small and I avoid the group chat.",
            "Nahihiya akong humarap sa teammates ko because I messed up the presentation.",
            "I keep hiding the result from my family kasi sobrang nakakahiya sa akin.",
        ],
    },
    "guilt": {
        "FILIPINO": [
            "Hindi ako mapakali dahil nasaktan ko siya sa sinabi ko at gusto kong humingi ng tawad.",
            "Iniisip ko kung paano ko aayusin ang pagkukulang ko sa kaibigan kong umasa sa akin.",
            "May bigat sa dibdib ko dahil pinabayaan kong ako ang dapat tumulong noong kailangan niya ako.",
            "Alam kong mali ang ginawa ko sa kapatid ko, kaya paulit-ulit kong binabalikan iyon.",
        ],
        "TAGLISH": [
            "I feel bad because my words hurt her, and I want to apologize properly.",
            "Hindi ako mapalagay; I let my friend down when they were counting on me.",
            "I keep replaying what I did kasi alam kong may nasaktan dahil sa akin.",
            "I should have helped my brother that night, and I feel responsible for not doing it.",
        ],
    },
    "stress": {
        "FILIPINO": [
            "Marami akong deadline ngayong linggo pero kaya ko pa silang ayusin isa-isa.",
            "Nape-pressure ako sa exams at requirements, kaya hirap akong magpahinga sa gabi.",
            "Sumisikip ang schedule ko sa school at part-time work, pero may oras pa akong mapaplano.",
            "Pagod ako sa sunod-sunod na gawain at kailangan kong gumawa ng malinaw na timetable.",
        ],
        "TAGLISH": [
            "I have several deadlines this week, pero manageable pa kung hahatiin ko ang tasks.",
            "Nape-pressure ako sa exams and requirements, kaya hindi ako makapag-relax sa gabi.",
            "My class and part-time schedule are packed, but I can still plan the next few steps.",
            "Stressful ang sunod-sunod na tasks; I need a realistic timetable before I continue.",
        ],
    },
    "overwhelm": {
        "FILIPINO": [
            "Sabay-sabay ang requirements, messages, at problema sa bahay kaya hindi ko alam kung alin ang uunahin.",
            "Parang napupuno na ang utak ko; kahit maliit na gawain ay hindi ko na masimulan.",
            "Hindi ko maayos ang mga kailangan dahil lahat ay urgent sa pakiramdam ko ngayon.",
            "Masyadong maraming nangyayari nang sabay at gusto ko munang huminto para makahinga.",
        ],
        "TAGLISH": [
            "Everything is piling up—school, family, and messages—and I cannot choose what to do first.",
            "Sobrang full ng utak ko; even a small task feels impossible to start.",
            "All of it feels urgent at once, so I keep jumping between tasks without finishing one.",
            "I am overwhelmed by what is happening simultaneously and need a quiet reset.",
        ],
    },
    "excitement": {
        "FILIPINO": [
            "Hindi ako mapakali sa saya habang naghihintay sa unang araw ng internship.",
            "Ang lakas ng energy ko dahil malapit na ang concert na matagal kong inaabangan.",
            "Gusto kong ikuwento agad ang magandang balita; sobrang saya at gana kong magsimula.",
            "Napapangiti ako kapag iniisip ko ang bagong project na matagal kong gustong subukan.",
        ],
        "TAGLISH": [
            "I cannot sit still because I am so excited for my first day at the internship.",
            "Ang taas ng energy ko; the concert I have waited for is finally this weekend.",
            "I want to tell everyone the good news because I am genuinely thrilled to begin.",
            "I keep smiling when I picture the new project I have wanted to try for months.",
        ],
    },
    "anticipation": {
        "FILIPINO": [
            "Iniisip ko kung ano ang magiging resulta ng interview na bukas.",
            "Abang na abang ako sa announcement dahil hindi ko pa alam kung ano ang desisyon.",
            "Bawat araw ay parang mabagal habang hinihintay ko ang sagot sa application ko.",
            "Pinaplano ko na ang mga susunod na hakbang habang papalapit ang graduation.",
        ],
        "TAGLISH": [
            "I keep wondering what tomorrow's interview result will be.",
            "Abang ako sa announcement; I do not know yet what decision they will make.",
            "The days feel slow while I wait for the answer to my application.",
            "I am preparing the next steps as graduation gets closer.",
        ],
    },
    "anxiety": {
        "FILIPINO": [
            "Paulit-ulit kong iniisip ang mga posibleng mangyari at hindi mapakali ang tiyan ko.",
            "Kinakabahan ako sa meeting dahil baka hindi ko maipaliwanag nang maayos ang punto ko.",
            "Kahit wala pang nangyayari, nag-aalala na ako sa maaaring maging problema bukas.",
            "Hirap akong makatulog habang iniisip kung tatanggapin ba ang request ko.",
        ],
        "TAGLISH": [
            "I keep imagining what could go wrong, and my stomach will not settle.",
            "Kinakabahan ako sa meeting because I might not explain my point clearly.",
            "Nothing has happened yet, pero I am already worrying about tomorrow's problems.",
            "I cannot sleep while thinking about whether they will accept my request.",
        ],
    },
    "fear": {
        "FILIPINO": [
            "Natakot ako nang biglang may sumunod na estranghero sa madilim na daan.",
            "Nanginginig ako sa tunog ng alarma dahil may banta sa labas ng gusali.",
            "Ayokong lumapit sa aso dahil mukhang handa itong umatake.",
            "Napatigil ako nang marinig ang malakas na pagsabog sa tabi ng bahay.",
        ],
        "TAGLISH": [
            "I felt afraid when a stranger followed me on the dark street.",
            "Nanginginig ako dahil may actual threat outside the building.",
            "I will not approach that dog; it looks ready to attack.",
            "I froze when I heard a loud explosion beside our house.",
        ],
    },
    "pride": {
        "FILIPINO": [
            "Ipinagmamalaki ko ang thesis na natapos ko matapos ang maraming revisions.",
            "Masaya akong makita ang pangalan ko sa listahan dahil pinaghirapan ko iyon.",
            "Natuwa ako sa sarili ko nang maipasa ko ang presentation na ako ang nanguna.",
            "Pakiramdam ko sulit ang practice nang mapuri ng mentor ang gawa ko.",
        ],
        "TAGLISH": [
            "I am proud of the thesis I finished after months of revisions.",
            "Ang saya makita ang name ko sa list because I worked hard for it.",
            "I feel proud that I led the presentation even though I was nervous at first.",
            "All the practice felt worth it when my mentor praised my work.",
        ],
    },
    "confidence": {
        "FILIPINO": [
            "Naniniwala akong kaya kong ipaliwanag ang proposal kahit may mahirap na tanong.",
            "Handa akong subukan ulit dahil alam kong natuto ako sa unang attempt.",
            "May tiwala ako sa paghahanda ko para sa exam bukas.",
            "Kaya kong magsabi ng opinyon ko nang mahinahon sa meeting.",
        ],
        "TAGLISH": [
            "I trust myself to explain the proposal even if the panel asks a hard question.",
            "I can try again because I learned from my first attempt.",
            "May tiwala ako sa preparation ko for tomorrow's exam.",
            "I can share my opinion calmly in the meeting.",
        ],
    },
    "loneliness": {
        "FILIPINO": [
            "Kasama ko ang mga kaklase ko pero parang wala akong mapagsabihan ng tunay kong iniisip.",
            "Tahimik ang gabi at pakiramdam ko walang nakakaintindi sa pinagdadaanan ko.",
            "Namimiss ko ang koneksyon na dati kong nararamdaman sa mga kaibigan ko.",
            "Kahit online ang marami, wala akong taong malapitan kapag mabigat ang araw.",
        ],
        "TAGLISH": [
            "I am with my classmates, but I still feel like nobody knows what I am carrying.",
            "Tahimik ang room and I do not have someone I can honestly talk to tonight.",
            "I miss feeling connected to my friends; our chats do not feel the same anymore.",
            "There are people online, pero I still feel alone when the day gets heavy.",
        ],
    },
    "homesickness": {
        "FILIPINO": [
            "Madalas kong naiisip ang luto ni Mama at ang ingay ng bahay namin sa probinsya.",
            "Gusto kong makauwi kahit saglit dahil miss ko na ang pamilya at pamilyar na lugar.",
            "Ibang-iba ang dorm; hinahanap-hanap ko ang boses ng mga kasama ko sa bahay.",
            "Kapag may special na araw, mas ramdam kong malayo ako sa mga mahal ko.",
        ],
        "TAGLISH": [
            "I miss my mother's cooking and the ordinary noise of our home in the province.",
            "Gusto kong umuwi kahit weekend lang because I miss my family and familiar places.",
            "The dorm feels different; I keep looking for the voices I hear at home.",
            "Special days make me feel how far away I am from the people I love.",
        ],
    },
    "sadness": {
        "FILIPINO": [
            "Mabigat ang pakiramdam ko mula nang hindi natuloy ang planong pinaghirapan ko.",
            "Tahimik lang ako ngayon dahil nalulungkot ako sa nangyari sa kaibigan ko.",
            "Wala akong gana sa mga dating kinagigiliwan ko matapos ang masamang balita.",
            "Gusto ko munang mapag-isa habang inaayos ko ang lungkot ko.",
        ],
        "TAGLISH": [
            "I feel heavy since the plan I worked for did not happen.",
            "Tahimik lang ako today because what happened to my friend made me sad.",
            "I have little energy for things I used to enjoy after the bad news.",
            "I need some quiet time to process the sadness I am carrying.",
        ],
    },
    "hopelessness": {
        "FILIPINO": [
            "Kahit anong gawin ko, parang wala nang mababago sa sitwasyon ko.",
            "Hindi ko makita ang susunod na hakbang at pakiramdam ko sarado na ang lahat ng pinto.",
            "Nawawala ang pag-asa ko dahil paulit-ulit na lang ang parehong problema.",
            "Hindi ko maisip kung paano pa gaganda ang buhay ko mula rito.",
        ],
        "TAGLISH": [
            "Whatever I try, it feels like nothing can change in my situation.",
            "Hindi ko makita ang next step; every door seems closed to me right now.",
            "I am losing hope because the same problem keeps returning.",
            "I cannot imagine life getting better from here.",
        ],
    },
}

MIXED = [
    ("FILIPINO", ["Masaya ako sa scholarship pero kinakabahan ako sa responsibilidad na kasama nito."], ["happiness_joy", "anxiety"]),
    ("FILIPINO", ["Ipinagmamalaki ko ang project ko, pero nagdududa pa rin ako kung sapat ito."], ["pride", "insecurity_self_doubt"]),
    ("FILIPINO", ["Excited akong umuwi pero nalulungkot akong maiiwan ang mga bagong kaibigan ko."], ["excitement", "homesickness"]),
    ("FILIPINO", ["Nape-pressure ako sa deadline at nalilito kung alin ang dapat kong unahin."], ["stress", "confusion"]),
    ("TAGLISH", ["I am excited for graduation, pero anxious ako about what happens after."], ["excitement", "anxiety"]),
    ("TAGLISH", ["Proud ako sa result, but part of me feels ashamed that it took so long."], ["pride", "shame"]),
    ("TAGLISH", ["I miss home and feel lonely here, kahit may friends naman akong kasama."], ["homesickness", "loneliness"]),
    ("TAGLISH", ["Stressful ang workload and I am overwhelmed by the number of choices."], ["stress", "overwhelm"]),
]


def norm(text: str) -> str:
    return re.sub(r"\W+", " ", text.lower(), flags=re.UNICODE).strip()


def make_record(text: str, language: str, labels: list[str], index: int) -> dict:
    labels = list(dict.fromkeys(labels))
    label_slug = "-".join(labels)
    source_id = f"v3-aug-{language.lower()}-{label_slug}-{index:03d}"
    return {
        "text": text,
        "labels": labels,
        "observed_labels": labels,
        "original_labels": [],
        "language": language,
        "source_dataset": "synthetic_ai_assisted_emotion35_v3_research",
        "source_id": source_id,
        "source_group": source_id,
        "split": "train",
        "synthetic": True,
        "ai_assisted": True,
        "weak_supervision": True,
        "human_review_required": True,
        "review_status": "unreviewed",
        "training_eligibility": "research_only_synthetic",
        "mixed_emotion": len(labels) > 1,
        "weak_positive_labels": labels,
        "weak_negative_labels": [],
        "weak_label_policy": "positive_only_unknown_negatives",
        "negative_annotation_basis": "none_unspecified_labels_unknown",
        "provenance_note": "AI-assisted research candidate; not human-reviewed ground truth.",
    }


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", type=Path, required=True)
    ap.add_argument("--output", type=Path, required=True)
    args = ap.parse_args()
    if args.output.exists():
        raise SystemExit(f"Refusing to overwrite {args.output}")
    shutil.copytree(args.base, args.output)

    base_train_path = args.output / "train.jsonl"
    base_rows = [json.loads(line) for line in base_train_path.read_text(encoding="utf-8").splitlines() if line.strip()]
    clean_rows, excluded = [], []
    for row in base_rows:
        # Only synthetic training data is eligible for this quality correction;
        # never alter public, validation, or test records.
        if row.get("synthetic") and "भय" in row.get("text", ""):
            excluded.append({"source_id": row.get("source_id"), "reason": "malformed Filipino fear text"})
            continue
        clean_rows.append(row)

    additions: list[dict] = []
    for label, languages in BANK.items():
        for language, texts in languages.items():
            for index, text in enumerate(texts, 1):
                additions.append(make_record(text, language, [label], index))
    for index, (language, texts, labels) in enumerate(MIXED, 1):
        for text in texts:
            additions.append(make_record(text, language, labels, index))

    existing = {norm(r.get("text", "")) for r in clean_rows}
    # Bound near-duplicate comparisons with stable length/prefix buckets. The
    # previous all-pairs scan made a 24k-row corpus unnecessarily expensive.
    buckets: dict[tuple[int, str], set[str]] = {}
    for old in existing:
        words = old.split()
        bucket = (len(old) // 24, " ".join(words[:2]))
        buckets.setdefault(bucket, set()).add(old)

    def near_existing(key: str) -> str | None:
        words = key.split()
        prefix = " ".join(words[:2])
        length_bucket = len(key) // 24
        candidates = set()
        for offset in (-1, 0, 1):
            candidates.update(buckets.get((length_bucket + offset, prefix), set()))
        return next((old for old in candidates if SequenceMatcher(None, key, old).ratio() >= 0.92), None)

    accepted, rejected = [], []
    for row in additions:
        key = norm(row["text"])
        if not key or key in existing:
            rejected.append({"source_id": row["source_id"], "reason": "exact normalized duplicate"})
            continue
        # Compare only within compatible buckets to guard against template
        # leakage without an unbounded all-pairs scan.
        near = near_existing(key)
        if near:
            rejected.append({"source_id": row["source_id"], "reason": "near duplicate", "matched_hash": hash(near)})
            continue
        existing.add(key)
        bucket = (len(key) // 24, " ".join(key.split()[:2]))
        buckets.setdefault(bucket, set()).add(key)
        accepted.append(row)

    all_rows = clean_rows + accepted
    base_train_path.write_text("".join(json.dumps(r, ensure_ascii=False) + "\n" for r in all_rows), encoding="utf-8")
    provenance_path = args.output / "provenance.json"
    provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
    provenance.update({
        "version": "emotion35-v3-augmentation-20261026",
        "source": "GoEmotions partial annotations plus isolated v3 synthetic training augmentation",
        "license": "GoEmotions license/provenance retained; synthetic rows are original research candidates",
        "usage": "research-only; no student chat or Admin analytics",
        "curator": "MinSuansCore research pipeline; human_review_required for synthetic rows",
        "v3_training_only_additions": len(accepted),
        "v3_excluded_malformed_training_rows": excluded,
        "v3_rejected_candidate_rows": rejected,
        "validation_test_unchanged_from_v2": True,
        "human_reviewed_rows": 0,
    })
    provenance_path.write_text(json.dumps(provenance, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    summary = {
        "base_dataset": str(args.base), "output_dataset": str(args.output),
        "base_train_rows": len(base_rows), "clean_train_rows": len(clean_rows),
        "accepted_training_additions": len(accepted), "rejected_candidates": rejected,
        "excluded_malformed_rows": excluded, "validation_test_unchanged": True,
        "human_reviewed_rows": 0, "production_model_untouched": True,
    }
    (args.output / "v3_augmentation_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
