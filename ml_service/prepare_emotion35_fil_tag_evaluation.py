"""Prepare an AI-authored, evaluation-only Filipino/Taglish Emotion35 set.

Nothing produced here is training data, human-reviewed ground truth, or a
professionally validated instrument. Provisional targets are explicitly
separated from the training loader's labels/observed_labels schema.
"""
from __future__ import annotations

import csv
import hashlib
import json
import re
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TAXONOMY_PATH = ROOT / "config/emotion_taxonomy.json"
OUT_DIR = ROOT / "storage/app/audit-recovery/emotion35_fil_tag_evaluation_20261104"

# One direct Filipino expression and one less literal/contextual Taglish
# expression per approved taxonomy label. These are deliberately not training
# rows and require qualified human review before being used as evaluation truth.
PER_LABEL = [
    ("happiness_joy", "Pagkabasa ko na pumasa ako, napangiti ako nang husto—sobrang saya ko.", "I got the confirmation email and biglang gumaan yung mood ko; genuinely happy ako."),
    ("excitement", "Hindi ako makatulog sa sobrang pananabik para sa field trip bukas.", "May ticket na ako sa concert! Parang gusto ko nang tumalon sa excitement."),
    ("calmness", "Pagkatapos kong maglakad sa tabi ng dagat, kumalma ang isip ko.", "Tapos na yung requirements for today, tahimik na ang paligid, and my breathing feels calm."),
    ("contentment", "Hindi man espesyal ang araw, kuntento ako sa tahimik na hapunan kasama ang pamilya.", "Simple lang yung weekend—nakapagpahinga, nakapagluto, and content na ako sa ganun."),
    ("hope_optimism", "Mabagal ang progreso ko pero naniniwala akong makakahanap ako ng paraan para makapasa.", "Hindi pa sure yung scholarship, pero may chance pa, so I still want to try."),
    ("pride", "Pinagpaguran ko ang thesis na ito at ipinagmamalaki kong natapos ko.", "I handled the presentation on my own; proud ako na hindi ako umatras kahit kabado."),
    ("curiosity", "Nakita ko ang diagram sa lecture at gusto kong malaman kung paano gumagana ang proseso.", "Napasearch ako kung bakit nag-iiba yung kulay ng langit—curious talaga ako."),
    ("gratitude", "Nagpapasalamat ako sa kaklase kong nagbahagi ng notes noong absent ako.", "My instructor took time to explain the feedback, and grateful ako doon."),
    ("love_affection", "Kapag naaalala ko ang mga nakagawian namin sa bahay, mas lalo kong nararamdaman kung gaano ko kamahal ang pamilya ko.", "My dog waits by the door every afternoon; ang lambing niya, I feel so much affection for him."),
    ("relief", "Nang makita kong pasado ako, saka lang ako nakahinga nang maluwag.", "The appointment is done and the results were okay; finally, I can relax."),
    ("confidence", "Pinaghandaan ko ang report kaya handa akong sumagot sa mga tanong ng klase.", "I practiced the demo a few times; I trust my preparation and feel confident presenting."),
    ("sadness", "Nalulungkot ako dahil hindi matutuloy ang planong matagal ko nang hinihintay.", "I keep replaying that conversation and today feels heavy; medyo down ako."),
    ("grief", "Simula nang pumanaw si Lolo, iba na ang bahay; miss na miss ko siya.", "Since my classmate passed away, may mga araw pa ring gusto ko siyang i-message."),
    ("disappointment", "Inasahan kong mapipili ako sa grant kaya nadismaya ako nang iba ang nakuha.", "I studied for the contest but got a score far below what I expected; disappointed ako."),
    ("loneliness", "Kahit may mga tao sa paligid, pakiramdam ko wala akong mapagsabihan at nag-iisa ako.", "Everyone has a group at lunch and I end up by myself, parang wala akong ka-close."),
    ("homesickness", "Miss na miss ko ang bahay at ang ingay ng pamilya ko rito sa dorm.", "Okay naman yung campus, but at night I miss our kitchen and my siblings; gusto ko nang umuwi."),
    ("hopelessness", "Parang kahit anong gawin ko ay walang magbabago; wala na akong makita na paraan para umayos.", "I've tried every option I can think of and I can't picture anything improving; hopeless na ako."),
    ("rejection_emotional_hurt", "Nang hindi ako isinama sa group chat, nasaktan ako at pakiramdam ko tinanggihan ako.", "They picked everyone except me for the team; ang sakit, it felt like they didn't want me there."),
    ("anxiety", "Kinakabahan ako sa resulta bukas at paulit-ulit kong iniisip kung ano ang mangyayari.", "I keep refreshing my inbox before the interview; anxious ako sa magiging feedback."),
    ("fear", "Natatakot akong mag-commute nang mag-isa sa rutang hindi ko kabisado.", "May biglang malakas na kalabog sa labas and I got scared to check what happened."),
    ("stress", "Ang dami kong deadline ngayong linggo kaya stressed ako at hirap magpahinga.", "Three papers and two exams landed this week; sobrang stressed na ako sa schedule."),
    ("overwhelm", "Hindi ko alam kung alin ang uunahin; sabay-sabay ang gawain at parang nalulunod ako sa dami.", "The class chat, chores, and papers all need attention at once; everything feels too much."),
    ("anger", "Galit ako sa kaklaseng sumira ng gamit ko at itinanggi pa ang ginawa niya.", "I found out they deliberately lied about me; furious talaga ako."),
    ("frustration", "Ilang beses nang nag-e-error ang form kahit tama naman ang inilalagay ko; nakakainis.", "I keep following the instructions but the app won't submit; sobrang frustrated na ako."),
    ("jealousy_envy", "Naiinggit ako na nakakapagbiyahe ang kapatid ko habang ako ay nag-aaral pa.", "My friend got the internship I wanted; I feel envious when I see the announcement."),
    ("disgust", "Nandidiri ako sa amoy ng pagkaing panis na naiwan sa shared fridge.", "That clip of someone mistreating an animal made me feel sick; disgusted ako."),
    ("shame", "Nang mabasa ko sa harap ng lahat ang maling sagot ko, gusto kong magtago sa hiya.", "I shared something personal in the group chat and now I wish I could disappear; nakakahiya."),
    ("guilt", "Nagsinungaling ako sa kaibigan ko at nakokonsensya ako dahil nasaktan ko siya.", "I forgot the promise I made to my sister and the guilt keeps bothering me."),
    ("insecurity_self_doubt", "Kahit nag-aral ako, iniisip kong baka hindi ako sapat para sa kursong ito.", "Everyone sounds more prepared in class, so I keep doubting if I belong here."),
    ("boredom", "Paulit-ulit lang ang ginagawa ko buong hapon at nababagot na ako.", "Same slides for an hour and I can't stay interested; bored na bored ako."),
    ("confusion", "Hindi ko maintindihan kung bakit magkaiba ang instructions sa portal at sa email.", "I read the problem twice but the steps still don't make sense; confused ako."),
    ("neutral", "Naisumite ko ang form at nakalagay na natanggap na ito.", "The class starts at eight and the room assignment is 204."),
    ("surprise", "Hindi ko inaasahang makakasalubong ko ang dati kong kaibigan sa library; nagulat ako.", "My name appeared on the list even though I thought results were next week; surprised ako."),
    ("compassion_empathy", "Nang marinig kong nahihirapan ang kaklase ko, naawa ako at gusto ko siyang samahan.", "My friend sounded overwhelmed, and I felt for her; I just listened instead of rushing to fix it."),
    ("anticipation", "Bukas na ilalabas ang resulta kaya inaabangan ko kung ano ang kalalabasan.", "I'm counting down to the school fair; curious ako sa mga booth na makikita namin."),
]

# Fresh edge cases exercise mixed affect, negation, reported emotion, and
# insufficient evidence. These labels are also provisional and review-required.
CHALLENGES = [
    ("mix_joy_anxiety_fil", "FILIPINO", "Masaya ako na nakapasa, pero kinakabahan pa rin ako sa susunod na semester.", ["happiness_joy", "anxiety"], "mixed"),
    ("mix_excitement_fear_tag", "TAGLISH", "Excited akong mag-present sa forum pero natatakot din ako na baka magkamali.", ["excitement", "fear"], "mixed"),
    ("mix_joy_homesick_fil", "FILIPINO", "Masaya ako sa bagong buhay sa campus, pero gabi-gabi kong nami-miss ang bahay.", ["happiness_joy", "homesickness"], "mixed"),
    ("mix_stress_frustration_tag", "TAGLISH", "Stressed na ako sa deadlines tapos ayaw pang mag-load ng portal; nakakafrustrate.", ["stress", "frustration"], "mixed"),
    ("mix_shame_guilt_fil", "FILIPINO", "Nahihiya ako sa sinabi ko sa klase at nakokonsensya rin dahil napahiya ko ang kaklase ko.", ["shame", "guilt"], "mixed"),
    ("mix_pride_insecurity_tag", "TAGLISH", "Proud ako sa project ko, pero nagdududa pa rin ako kung sapat ba yung gawa ko.", ["pride", "insecurity_self_doubt"], "mixed"),
    ("mix_relief_grief_fil", "FILIPINO", "Nakahinga ako nang maluwag na hindi na siya nahihirapan, pero labis ko pa ring ikinalulungkot ang pagkawala niya.", ["relief", "grief"], "mixed"),
    ("mix_homesick_lonely_tag", "TAGLISH", "I miss my family sa dorm, and even with roommates around I still feel alone.", ["homesickness", "loneliness"], "mixed"),
    ("mix_anticipation_anxiety_fil", "FILIPINO", "Inaabangan ko ang interview bukas, pero kinakabahan din ako sa mga itatanong nila.", ["anticipation", "anxiety"], "mixed"),
    ("mix_happy_envy_tag", "TAGLISH", "Masaya ako para sa friend ko na nanalo, pero naiinggit din ako kasi gusto ko rin sana yun.", ["happiness_joy", "jealousy_envy"], "mixed"),
    ("negated_anxiety_tag", "TAGLISH", "Hindi ako kinakabahan sa exam; excited lang akong makita yung questions.", ["excitement"], "clear_single"),
    ("reported_sadness_tag", "TAGLISH", "Sabi ng friend ko sobrang lungkot niya, pero wala naman akong sariling lungkot ngayon.", [], "no_student_emotion"),
    ("reported_fear_fil", "FILIPINO", "Natatakot daw ang kapatid ko sa interview; ako naman ay nag-aayos lang ng schedule.", [], "no_student_emotion"),
    ("neutral_admin_fil", "FILIPINO", "Natanggap ko ang email na ililipat sa Room 12 ang klase sa Biyernes.", ["neutral"], "neutral"),
    ("hypothetical_shame_tag", "TAGLISH", "Kung mapahiya ang isang student sa stage, baka gusto niyang magtago; example lang ito, hindi ko nararanasan.", [], "hypothetical_other"),
    ("uncertain_numb_fil", "FILIPINO", "Hindi ko matukoy kung ano ang nararamdaman ko ngayon; parang wala lang, pero hindi ko rin alam.", [], "uncertain"),
    ("indirect_grief_tag", "TAGLISH", "Naiwan pa rin yung paborito niyang mug sa mesa; napapatingin ako roon tuwing umaga simula nang mawala si Lola.", ["grief"], "clear_single"),
    ("indirect_rejection_fil", "FILIPINO", "Lahat ng kaklase ko ay naimbitahan sa study group maliban sa akin; simula noon, ayoko nang magtanong sa kanila.", ["rejection_emotional_hurt"], "clear_single"),
    ("mixed_calm_contentment_tag", "TAGLISH", "Walang espesyal na nangyari today, pero tahimik yung hapon and I feel calm and content.", ["calmness", "contentment"], "mixed"),
    ("uncertain_jealousy_pride_fil", "FILIPINO", "Masaya ako para sa kapatid ko, pero hindi ko maintindihan kung naiinggit ba ako o ipinagmamalaki ko lang siya.", [], "uncertain"),
]


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    taxonomy = json.loads(TAXONOMY_PATH.read_text(encoding="utf-8"))
    labels = [item["key"] for item in taxonomy["emotions"]]
    if len(labels) != 35 or len(set(labels)) != 35:
        raise RuntimeError("approved taxonomy must contain 35 unique keys")
    cases = []
    for key, fil_text, tag_text in PER_LABEL:
        if key not in labels:
            raise RuntimeError(f"unknown target key: {key}")
        cases.append((f"pos_{key}_fil", "FILIPINO", fil_text, [key], "clear_single", "direct"))
        cases.append((f"pos_{key}_tag", "TAGLISH", tag_text, [key], "clear_single", "indirect_or_contextual"))
    for case_id, language, text, positives, state in CHALLENGES:
        cases.append((case_id, language, text, positives, state, "contrast_or_attribution"))

    seen: set[str] = set()
    rows = []
    for case_id, language, text, positives, state, expression_type in cases:
        normalized = re.sub(r"\s+", " ", text.casefold()).strip()
        if normalized in seen:
            raise RuntimeError(f"duplicate evaluation wording: {case_id}")
        seen.add(normalized)
        unknown = set(positives) - set(labels)
        if unknown:
            raise RuntimeError(f"invalid expected labels in {case_id}: {sorted(unknown)}")
        is_uncertain = state == "uncertain"
        observed = [] if is_uncertain else labels.copy()
        rows.append({
            "case_id": case_id,
            "text": text,
            "language": language,
            "scenario_group": case_id,
            "source_dataset": "AI_authored_synthetic_evaluation_only",
            "generation_provenance": "AI-authored synthetic examples; not sourced from student records",
            "evaluation_split": "frozen_eval_only_20261104",
            "expression_type": expression_type,
            "provisional_expected_status": state,
            "provisional_expected_positive_labels": positives,
            "provisional_expected_negative_labels": sorted(set(observed) - set(positives)) if observed else [],
            "provisional_evaluated_labels": observed,
            "targets_are_independently_validated": False,
            "human_review_status": "pending",
            "human_reviewer": "",
            "review_notes": "",
            "training_eligible": False,
            "taxonomy_version": str(taxonomy["version"]),
        })
    if len(rows) != 90:
        raise RuntimeError(f"expected 90 evaluation cases, built {len(rows)}")
    counts_by_label = Counter(label for row in rows for label in row["provisional_expected_positive_labels"])
    if any(counts_by_label[key] < 2 for key in labels):
        raise RuntimeError("each emotion must have at least two positive evaluation examples")

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    jsonl_path = OUT_DIR / "emotion35_fil_tag_eval_cases.jsonl"
    csv_path = OUT_DIR / "emotion35_fil_tag_eval_cases.csv"
    if jsonl_path.exists() or csv_path.exists():
        raise FileExistsError("refusing to overwrite an existing evaluation package")
    with jsonl_path.open("w", encoding="utf-8", newline="\n") as stream:
        for row in rows:
            stream.write(json.dumps(row, ensure_ascii=False) + "\n")
    fields = list(rows[0].keys())
    with csv_path.open("w", encoding="utf-8-sig", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader()
        for row in rows:
            writer.writerow({key: json.dumps(value, ensure_ascii=False) if isinstance(value, (list, dict)) else value for key, value in row.items()})
    manifest = {
        "schema": "emotion35-fil-tag-evaluation-only-v1",
        "purpose": "research evaluation candidate set; not model-training data",
        "records": len(rows),
        "by_language": dict(Counter(row["language"] for row in rows)),
        "by_expression_type": dict(Counter(row["expression_type"] for row in rows)),
        "by_expected_status": dict(Counter(row["provisional_expected_status"] for row in rows)),
        "positive_case_count_by_emotion": {key: counts_by_label[key] for key in labels},
        "taxonomy_version": str(taxonomy["version"]),
        "all_text_is_synthetic": True,
        "all_targets_are_provisional": True,
        "independently_validated_cases": 0,
        "human_review_required": True,
        "training_eligible": False,
        "no_student_records_used": True,
        "jsonl_sha256": _sha256(jsonl_path),
        "csv_sha256": _sha256(csv_path),
        "warning": "This package must not be described as a validated benchmark. Uncertainty cases intentionally have no evaluated label mask and should be reported as abstention probes only.",
    }
    manifest_path = OUT_DIR / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    readme_path = OUT_DIR / "README.md"
    readme_path.write_text(
        "# Emotion35 Filipino/Taglish evaluation candidates\n\n"
        "This is a 90-row AI-authored synthetic evaluation-only package. It contains two positive examples per taxonomy label (one Filipino and one Taglish) plus 20 contrast, mixed, attribution, neutral, and uncertainty cases.\n\n"
        "Every expected label is provisional. No example is human-reviewed, independently validated, or suitable as training ground truth. `training_eligible` is false; targets use `provisional_*` field names and must not be imported by the training-data loader. The uncertainty cases deliberately have no evaluated label set and are abstention probes only.\n\n"
        "Review the text and target labels before using metrics. Keep the frozen file/hash unchanged during model comparison; any edits require a new version and hash.\n",
        encoding="utf-8",
    )
    print(json.dumps({"directory": str(OUT_DIR), "records": len(rows), "by_language": manifest["by_language"], "positive_examples_per_emotion_min": min(counts_by_label.values()), "independently_validated": 0, "training_eligible": False, "manifest": str(manifest_path)}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
