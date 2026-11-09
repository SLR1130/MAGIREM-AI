<?php
namespace App\Services;

/**
 * Safety net that runs on the RAW message, next to EnhancedCrisisAnalysisService.
 * It can only RAISE the crisis level, never lower it. Emojis, "lol"/"haha" and
 * sarcasm scores are ignored on purpose. It returns the ids of the rules that
 * matched, so logs never need to contain the student's message.
 *
 * DRAFT: the English and Tagalog/Taglish phrase lists must be reviewed by a
 * counselor or Filipino-speaking psychology staff before production use.
 */
class RiskPhraseScreen
{
    private const ORDER = ['none' => 0, 'mild' => 1, 'moderate' => 2, 'severe' => 3, 'critical' => 4];

    /**
     * id => [level, regex, guarded]
     * guarded = skip the match when negated ("i dont want to die"), and cap it
     * at "moderate" when it is about the past ("i used to want to die").
     * Text is normalized first: lowercase, no apostrophes, no punctuation/emoji.
     */
    private const RULES = [
        // ---- critical: explicit intent ----
        'en_kill_myself'     => ['critical', '/\bkill(ing)?\s+my\s?self\b/', true],
        'en_end_my_life'     => ['critical', '/\b(end|ending)\s+(my\s+life|it\s+all)\b/', true],
        'en_take_own_life'   => ['critical', '/\btake\s+my\s+own\s+life\b/', true],
        'en_suicide'         => ['critical', '/suicid(e|al)\b/', true],
        'en_want_to_die'     => ['critical', '/\b(want|wanna)\s+(to\s+)?die\b/', true],
        'en_better_dead'     => ['critical', '/\bbetter\s+off\s+dead\b/', false],
        'tl_magpakamatay'    => ['critical', '/\bmag(pa)?pakamatay\b/', true],
        'tl_gusto_mamatay'   => ['critical', '/\bgusto(ng)?\b.{0,12}\bmamatay\b(?!\s+sa\s+(kilig|hiya|tuwa|saya|init|lamig|antok|gutom|puyat|kaba|takot))/', true],
        'tl_patayin_sarili'  => ['critical', '/patayin\b.{0,15}\bsarili\b/', true],
        'tl_wakasan_buhay'   => ['critical', '/\b(wakasan|tapusin)\b.{0,15}\bbuhay\b/', true],
        'tl_magbigti'        => ['critical', '/\bmag(bi)?bigti\b/', true],
        // ---- severe: self-harm, passive ideation, "better off without me" ----
        'en_self_harm'       => ['severe', '/\bself\s?harm/', true],
        'en_hurt_myself'     => ['severe', '/\b(cut|cutting|hurt|hurting|harm|harming)\s+my\s?self\b/', true],
        'en_dont_want_here'  => ['severe', '/\b(dont|do\s+not)\s+want\s+to\s+(be\s+here|be\s+alive|exist|wake\s+up|live(?!\s+(with|in|at|on|here|there)))\b/', false],
        'en_cant_go_on'      => ['severe', '/\b(cant|cannot|can\s+not)\s+go\s+on\b/', false],
        'en_no_reason_live'  => ['severe', '/\bno\s+(reason|point)\s+(to|in)\s+(live|living|go\s+on|going\s+on|being\s+alive)\b/', false],
        'en_better_without'  => ['severe', '/\b(better|best)\s+off\s+without\s+me\b/', false],
        'en_nobody_notice'   => ['severe', '/\b(nobody|no\s?one)\s+(would|will)\s+(even\s+)?(notice|care|miss)\b.{0,40}\b(disappear\w*|gone|die[ds]?|dead|not\s+here|without\s+me)\b/', false],
        'en_wish_gone'       => ['severe', '/\bwish\b.{0,20}\b(dead|disappear\w*|never\s+(born|existed|wake|woke))\b/', false],
        'en_want_disappear'  => ['severe', '/\b(want|wanna)\s+(to\s+)?(disappear|vanish|be\s+gone|not\s+exist)\b/', true],
        'en_tired_of_living' => ['severe', '/\b(tired|sick|exhausted|done)\s+(of|with)\s+(living|life|being\s+alive)\b/', false],
        'tl_sana_nagising'   => ['severe', '/\bsana\s+(hindi|di)\s+(na\s+)?lang\s+ako\s+(nagising|ipinanganak|pinanganak|nabuhay)\b/', false],
        'tl_mawala_na_lang'  => ['severe', '/\bmawala\s+(na\s+)?lang\s+ako\b/', false],
        'tl_pagod_mabuhay'   => ['severe', '/\b(pagod|sawa|suko)\b.{0,25}\bmabuhay\b/', false],
        'tl_ayoko_mabuhay'   => ['severe', '/\b(ayoko|ayaw\s+ko)\s+(na|nang|ng)\s+(mabuhay|sa\s+buhay|magpatuloy)\b/', false],
        'tl_mas_mabuti_wala' => ['severe', '/\bmas\s+mabuti\b.{0,15}\b(wala|mawala|patay)\b.{0,10}\bako\b/', false],
        'tl_saysay_buhay'    => ['severe', '/\bwala(ng|\s+na(ng)?|\s+ng)?\s*saysay\b.{0,20}\bbuhay\b/', false],
        // ---- moderate: distress signals (two or more together become severe) ----
        'en_hopeless'        => ['moderate', '/\b(hopeless|worthless)\b/', false],
        'en_cant_do_this'    => ['moderate', '/\b(cant|cannot|can\s+not)\s+(do\s+this|take\s+it)\s+anymore\b/', false],
        'en_im_a_burden'     => ['moderate', '/\b(im|i\s+am)\s+(a\s+)?(burden|useless)\b/', false],
        'en_nobody_cares'    => ['moderate', '/\b(no\s?one|nobody)\s+cares\b/', false],
        'tl_ayoko_na'        => ['moderate', '/\b(ayoko|ayaw\s+ko)\s+(na|nang|ng)\b/', false],
        'tl_walang_silbi'    => ['moderate', '/\bwala(ng|\s+na(ng)?)\s+(akong\s+)?silbi\b/', false],
        'tl_walang_saysay'   => ['moderate', '/\bwala(ng|\s+na(ng)?|\s+ng)?\s*saysay\b/', false],
        'tl_pabigat'         => ['moderate', '/\bpabigat\b/', false],
        'tl_walang_pakialam' => ['moderate', '/\bwalang\s+(may\s+)?(pakialam|nagmamahal)\s+sa\s+akin\b/', false],
        'tl_suko_na'         => ['moderate', '/\b(sukong\s+suko|suko\s+na\s+ako)\b/', false],
        'tl_pagod_na_pagod'  => ['moderate', '/\bpagod\s+na\s+pagod\b/', false],
        'tl_sawa_na'         => ['moderate', '/\bsawa\s+na\s+ako\b/', false],
    ];

    /**
     * @return array{level: string, rules: string[]}
     */
    public function screen(string $message): array
    {
        $text = $this->normalize($message);
        $level = 'none';
        $rules = [];
        $moderateHits = 0;

        foreach (self::RULES as $id => [$ruleLevel, $regex, $guarded]) {
            if (!preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[0] as [, $offset]) {
                $before = substr($text, max(0, $offset - 25), min($offset, 25));

                if ($guarded && preg_match('/\b(dont|do\s+not|never|not|hindi|ayaw|wag|huwag)\b[\w\s]{0,10}$/', $before)) {
                    continue; // negated, look at the next occurrence
                }

                $effective = ($guarded && preg_match('/\b(used\s+to|once|dati|noon)\b[\w\s]{0,10}$/', $before))
                    ? 'moderate'
                    : $ruleLevel;

                $rules[] = $id;

                if ($effective === 'moderate') {
                    $moderateHits++;
                }

                $level = self::higher($level, $effective);
                break;
            }
        }

        if ($moderateHits >= 2) {
            $level = self::higher($level, 'severe');
        }

        return ['level' => $level, 'rules' => $rules];
    }

    /** Returns whichever of the two levels is more serious. */
    public static function higher(string $a, string $b): string
    {
        return (self::ORDER[$b] ?? 0) > (self::ORDER[$a] ?? 0) ? $b : $a;
    }

    private function normalize(string $message): string
    {
        $t = mb_strtolower($message, 'UTF-8');
        $t = str_replace(["'", "\u{2019}", '`'], '', $t);
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? $t;
        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }
}
