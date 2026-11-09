"""Create a review-required annotation template for labels absent from sources.

Every row is synthetic and explicitly marked ``needs_human_review``. This file
is not training data and must not be fed to the trainer until qualified review
confirms the intended label (including mixed/uncertain alternatives).
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path


TEMPLATE = {
    "anticipation": {
        "en": "I keep looking forward to the orientation because I want to know what comes next.",
        "fil": "Hindi ako mapakali sa paghihintay sa resulta; gusto ko nang malaman kung ano ang susunod.",
        "taglish": "I'm looking forward sa first day, kahit hindi ko pa alam kung ano ang mangyayari.",
    },
    "anxiety": {
        "en": "My thoughts keep racing about tomorrow, and I cannot settle down.",
        "fil": "Paulit-ulit ang pag-aalala ko tungkol bukas at hindi ako mapakali.",
        "taglish": "Kinakabahan ako sa presentation kaya paulit-ulit kong chine-check ang notes ko.",
    },
    "boredom": {
        "en": "The afternoon feels flat; I have nothing I want to do and nothing holds my attention.",
        "fil": "Parang ang bagal ng oras at wala akong ganang gawin ngayon.",
        "taglish": "Bored ako sa routine pero hindi ko rin alam kung anong bagong activity ang gusto ko.",
    },
    "calmness": {
        "en": "After the walk, my breathing feels steady and my mind is quiet.",
        "fil": "Tahimik ang isip ko at magaan ang pakiramdam ko pagkatapos maglakad.",
        "taglish": "Mas calm na ako ngayon matapos magpahinga at mag-breathe nang dahan-dahan.",
    },
    "compassion_empathy": {
        "en": "I can imagine how difficult that must be for my classmate, and I want to listen.",
        "fil": "Naiintindihan ko kung gaano kabigat iyon para sa kaklase ko at gusto ko siyang pakinggan.",
        "taglish": "I feel for my friend; gusto kong samahan siya kahit wala akong perfect na advice.",
    },
    "confidence": {
        "en": "I prepared carefully, and I trust myself to explain the project clearly.",
        "fil": "Pinaghandaan ko ito at naniniwala akong kaya kong ipaliwanag nang maayos.",
        "taglish": "Ready ako sa demo; I trust myself kahit may konting kaba.",
    },
    "contentment": {
        "en": "Nothing special happened today, but this quiet evening feels enough.",
        "fil": "Walang malaking nangyari, pero sapat na sa akin ang tahimik na gabi.",
        "taglish": "Okay na ako sa simple day na ito; I feel settled kahit ordinary lang.",
    },
    "frustration": {
        "en": "I have tried the same fix three times and the error keeps coming back.",
        "fil": "Ilang beses ko nang inayos pero bumabalik pa rin ang problema, nakakapagod.",
        "taglish": "Frustrated ako kasi ginawa ko naman nang tama pero ayaw pa ring gumana.",
    },
    "guilt": {
        "en": "I keep thinking about how my choice affected my teammate, and I wish I had spoken sooner.",
        "fil": "Iniisip ko kung paano naapektuhan ang kasama ko at nagsisisi akong hindi ako nagsabi agad.",
        "taglish": "Nagiguilty ako na naiwan ko siya sa group work; sana nagsabi ako nang mas maaga.",
    },
    "homesickness": {
        "en": "I miss the familiar sounds at home, especially when campus gets quiet at night.",
        "fil": "Namimiss ko ang bahay at pamilya ko lalo na kapag tahimik na sa gabi.",
        "taglish": "I miss home so much; kahit busy ako, hinahanap-hanap ko pa rin sila.",
    },
    "hope_optimism": {
        "en": "This week was hard, but I still believe a small change can make tomorrow better.",
        "fil": "Mahirap ang linggong ito pero naniniwala akong may puwang pa para gumaan ang bukas.",
        "taglish": "Hindi perfect ang progress, but I still feel hopeful na may next step ako.",
    },
    "hopelessness": {
        "en": "I cannot see a way forward right now, and every option feels pointless.",
        "fil": "Hindi ko makita kung paano uusad at parang walang saysay ang mga pagpipilian.",
        "taglish": "Parang wala nang magbabago kahit anong gawin ko, kaya hirap akong makakita ng direction.",
    },
    "insecurity_self_doubt": {
        "en": "Everyone seems more capable than me, so I keep questioning whether I belong in this class.",
        "fil": "Pakiramdam ko mas magaling silang lahat at duda ako kung kaya ko rito.",
        "taglish": "I keep doubting myself sa recitation kahit nag-aral naman ako.",
    },
    "jealousy_envy": {
        "en": "I felt a sting when my friend received the opportunity I had hoped for.",
        "fil": "Naiinggit ako nang mapunta sa kaibigan ko ang pagkakataong gusto ko rin.",
        "taglish": "Happy ako for her, pero may konting inggit kasi gusto ko rin sana iyon.",
    },
    "loneliness": {
        "en": "I am surrounded by classmates, but I still feel like I have no one to talk to.",
        "fil": "Maraming tao sa paligid pero pakiramdam ko wala akong mapagsabihan.",
        "taglish": "Kahit may kasama ako sa room, I still feel alone tonight.",
    },
    "love_affection": {
        "en": "I feel warmly connected to my sibling and grateful that we look after each other.",
        "fil": "Mahalaga sa akin ang kapatid ko at ramdam ko ang pag-aalaga namin sa isa't isa.",
        "taglish": "I care deeply about my family; sila ang comfort ko kapag pagod ako.",
    },
    "overwhelm": {
        "en": "There are so many deadlines and messages that I do not know where to start.",
        "fil": "Sabay-sabay ang gawain at hindi ko alam kung alin ang uunahin.",
        "taglish": "Overwhelmed ako sa tasks, chats, at deadlines na sabay-sabay dumating.",
    },
    "rejection_emotional_hurt": {
        "en": "Being left out of the group made me feel unwanted and hurt.",
        "fil": "Nasaktan ako nang hindi nila ako isinama at pakiramdam ko ayaw nila sa akin.",
        "taglish": "It hurt when they said no; parang hindi ako welcome sa group.",
    },
    "shame": {
        "en": "I keep replaying my mistake and feel exposed whenever I remember it.",
        "fil": "Paulit-ulit kong naaalala ang mali ko at nahihiya ako kapag naiisip ko iyon.",
        "taglish": "Sobrang hiya ko sa nangyari; gusto kong magtago kapag naaalala ko.",
    },
    "stress": {
        "en": "The constant schedule and pressure are making it hard to relax between classes.",
        "fil": "Dahil sa sunod-sunod na gawain at pressure, hirap akong magpahinga.",
        "taglish": "Stressed ako sa back-to-back requirements kaya hindi ako makapag-reset.",
    },
}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8") as handle:
        for label, examples in TEMPLATE.items():
            for language, text in examples.items():
                row = {
                    "text": text,
                    "proposed_labels": [label],
                    "language": {"en": "ENGLISH", "fil": "FILIPINO", "taglish": "TAGLISH"}[language],
                    "source": "synthetic_template",
                    "synthetic": True,
                    "review_status": "needs_human_review",
                    "reviewer_count": 0,
                    "notes": "Do not train until a qualified reviewer confirms the label, mixed-emotion alternatives, attribution, and uncertainty.",
                }
                handle.write(json.dumps(row, ensure_ascii=False) + "\n")
    print(json.dumps({"rows": len(TEMPLATE) * 3, "labels": len(TEMPLATE), "output": str(args.output), "status": "needs_human_review"}, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

