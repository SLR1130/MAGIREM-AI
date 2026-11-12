@extends('layouts.app')

@section('title', 'Wellness Resources - MAGIREM AI')

@section('content')
@php
    $categoryLabel = fn ($category) => ucwords(str_replace(['-', '_'], ' ', $category ?: 'general'));
    $youtubeVideoId = function ($url) {
        $parts = parse_url((string) $url);
        if (($parts['scheme'] ?? '') !== 'https') return null;
        $host = strtolower((string) ($parts['host'] ?? ''));
        $id = null;
        if ($host === 'youtu.be') $id = trim((string) ($parts['path'] ?? ''), '/');
        elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $id = $query['v'] ?? (preg_match('#^(?:embed|shorts)/([A-Za-z0-9_-]{11})$#', $path, $matches) ? $matches[1] : null);
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    };
    $hasSearchContext = $searchQuery !== '' || $category !== null;
    // Fixed editorial links: no provider request, user profile, or query is sent on page load.
    $starterGuides = [
        ['title' => 'Understanding and managing stress', 'provider' => 'MedlinePlus', 'description' => 'Learn about common sources of stress and ways to manage it.', 'url' => 'https://medlineplus.gov/stress.html', 'keywords' => ['stress', 'academic pressure', 'burnout', 'pagod sa school', 'stress sa school']],
        ['title' => 'Healthy sleep', 'provider' => 'MedlinePlus', 'description' => 'Explore why sleep matters and ways to support healthy sleep.', 'url' => 'https://medlineplus.gov/healthysleep.html', 'keywords' => ['sleep', 'matulog', 'makatulog']],
        ['title' => 'Anxiety, fear and panic', 'provider' => 'NHS', 'description' => 'Understand anxiety and find self-help and support options.', 'url' => 'https://www.nhs.uk/mental-health/feelings-symptoms-behaviours/feelings-and-symptoms/anxiety-fear-panic/', 'keywords' => ['anxiety', 'anxious', 'worry', 'fear', 'kinakabahan']],
        ['title' => 'Writing down your thoughts', 'provider' => 'NHS', 'description' => 'Try a structured exercise for reflecting on thoughts and feelings.', 'url' => 'https://www.nhs.uk/every-mind-matters/mental-wellbeing-tips/self-help-cbt-techniques/thought-record/', 'keywords' => ['journal', 'journaling', 'reflection', 'writing']],
        ['title' => 'Ways to support mental wellbeing', 'provider' => 'NHS', 'description' => 'Explore everyday ways to connect, learn and care for your wellbeing.', 'url' => 'https://www.nhs.uk/mental-health/self-help/guides-tools-and-activities/five-steps-to-mental-wellbeing/', 'keywords' => ['self-care', 'self care', 'wellbeing', 'wellness']],
    ];
    $guideMatches = function (array $guide) use ($searchQuery, $category, $remoteResults) {
        if ($category !== null) return false; // Library categories do not classify external guides.
        if ($remoteResults->contains(fn ($item) => rtrim($item['url'] ?? '', '/') === rtrim($guide['url'], '/'))) return false;
        if ($searchQuery === '') return true;
        foreach ($guide['keywords'] as $keyword) {
            if (mb_stripos($searchQuery, $keyword, 0, 'UTF-8') !== false) return true;
        }
        return false;
    };
    $guideCount = collect($starterGuides)->filter($guideMatches)->count();
    // Fixed, source-verified examples; no external player or request is loaded on page view.
    $featuredAudio = [
        ['topic' => 'Stress', 'type' => 'Podcast', 'title' => 'Stress and mental health', 'source' => 'Mental Health Foundation', 'url' => 'https://www.mentalhealth.org.uk/podcasts/stress-and-mental-health', 'image' => 'topic-stress-outdoors-v1.png', 'player' => 'spotify', 'id' => '18xva5FyNLPICun7GU3dhc'],
        ['topic' => 'Sleep', 'type' => 'Podcast', 'title' => 'How sleep and green space can help your mental health', 'source' => 'Mental Health Foundation', 'url' => 'https://www.mentalhealth.org.uk/explore-mental-health/podcasts/how-sleep-and-green-space-can-help-your-mental-health', 'image' => 'topic-better-sleep-v1.png', 'player' => 'spotify', 'id' => '1xXWQnIULL9NgpDG3q6s68'],
        ['topic' => 'Anxiety', 'type' => 'Audio guide', 'title' => 'How to cope with anxiety', 'source' => 'NHS', 'url' => 'https://www.nhs.uk/mental-health/self-help/guides-tools-and-activities/mental-wellbeing-audio-guides/', 'image' => 'topic-anxiety-education-v1.png', 'player' => 'nhs', 'id' => '2163080377001'],
        ['topic' => 'Connection', 'type' => 'Podcast', 'title' => 'Friendships and mental health', 'source' => 'Mental Health Foundation', 'url' => 'https://www.mentalhealth.org.uk/explore-mental-health/podcasts/friendships-and-mental-health', 'image' => 'home-daylight-community-1600.webp', 'player' => 'spotify', 'id' => '0NAd8OCBQ93Are2orDJRDC'],
    ];
    $localResultCount = method_exists($resources, 'total') ? (int) $resources->total() : $resources->count();
    $publicResultCount = $remoteResults->count();
    $videoResultCount = $videoResults->count();
    $totalSearchResultCount = $localResultCount + $publicResultCount + $videoResultCount + $guideCount;
    $hasProviderError = count($searchErrors) > 0;
    $initialOverallEmpty = $hasSearchContext && $totalSearchResultCount === 0;
@endphp
<style>
    .wellness-hub{--wellness-ink:#083f34;--wellness-deep:#043b31;--wellness-green:#08735c;--wellness-mint:#e9f3e9;--wellness-cream:#fcfcf6;--wellness-gold:#d8a825;--wellness-muted:#6d7b77;color:#243f37;max-width:1480px;margin:0 auto;padding:1rem .25rem 4.5rem;font-family:inherit}.wellness-hub *{box-sizing:border-box}.wellness-hub a:focus-visible,.wellness-hub button:focus-visible,.wellness-hub input:focus-visible{outline:3px solid rgba(216,168,37,.78);outline-offset:3px}.wellness-breadcrumb{display:flex;gap:.45rem;align-items:center;margin:0 0 .8rem;padding-left:.75rem;font-size:.82rem;color:#718079}.wellness-breadcrumb a{color:var(--wellness-green);font-weight:750;text-decoration:none}.wellness-breadcrumb a:hover{text-decoration:underline}
    .wellness-hero{position:relative;isolation:isolate;display:grid;grid-template-columns:minmax(0,49%) minmax(0,51%);height:342px;min-height:0;overflow:hidden;border:1px solid #dbe7dd;border-radius:26px;background:#edf5e9;box-shadow:0 18px 44px rgba(10,65,51,.10)}.wellness-hero:before{position:absolute;z-index:-1;inset:0 42% 0 0;content:"";background:linear-gradient(90deg,#eff7eb 0%,#f8fbf5 72%,rgba(248,251,245,0) 100%)}.wellness-hero-copy-wrap{position:relative;z-index:2;display:flex;flex-direction:column;justify-content:center;padding:clamp(2rem,4.3vw,4rem) clamp(1.7rem,4.2vw,4.5rem)}.wellness-eyebrow{display:inline-flex;align-items:center;gap:.75rem;color:#215b4d;font-size:.73rem;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.wellness-eyebrow:before{width:1.75rem;height:2px;content:"";background:var(--wellness-gold)}.wellness-hero h1{max-width:600px;margin:.8rem 0 .55rem;color:var(--wellness-deep);font-family:Georgia,'Times New Roman',serif;font-size:clamp(2.7rem,5vw,4.45rem);font-weight:700;letter-spacing:-.06em;line-height:.93}.wellness-hero-copy{max-width:545px;margin:0;color:#465a53;font-family:Georgia,'Times New Roman',serif;font-size:clamp(1rem,1.35vw,1.22rem);line-height:1.43}.hero-proof{display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:1.55rem;color:#164a3d;font-size:.79rem;line-height:1.32}.hero-proof-item{display:flex;align-items:center;gap:.55rem;max-width:135px}.hero-proof-mark{display:grid;place-items:center;width:2.12rem;height:2.12rem;flex:0 0 auto;border-radius:999px;background:#dceee0;color:var(--wellness-green);font-size:1rem}.wellness-hero-art{position:absolute;z-index:0;inset:0 0 0 43%;overflow:hidden}.wellness-hero-art:before{position:absolute;z-index:1;inset:0;content:"";background:linear-gradient(90deg,#eff7eb 0%,rgba(239,247,235,.83) 8%,rgba(239,247,235,0) 31%)}.wellness-hero-art img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}.hero-note{position:absolute;z-index:2;top:16%;right:5.5%;color:#fffdf2;font-family:cursive;font-size:clamp(1rem,1.65vw,1.42rem);font-weight:600;line-height:1.15;rotate:-5deg;text-align:center;text-shadow:0 1px 9px rgba(6,54,42,.28)}.hero-note:after{display:block;margin:.35rem auto 0;content:"\2661";font-family:inherit;font-size:1.45rem}.hero-wellbeing-badge{position:absolute;z-index:2;right:-1.2rem;bottom:13%;display:grid;place-items:center;width:138px;min-height:126px;padding:1.2rem 1.5rem 1rem;border-radius:49% 43% 48% 41%;background:#edf1c8;color:#235548;font-family:Georgia,'Times New Roman',serif;font-size:.92rem;font-weight:700;line-height:1.2;text-align:center;rotate:8deg}.hero-wellbeing-badge:before{position:absolute;top:18px;right:26px;content:"\2767";color:#5b916f;font-size:1.55rem;rotate:-30deg}
    .wellness-search-panel{position:relative;z-index:4;display:block;margin:-1.72rem clamp(1rem,2vw,1.65rem) 0;padding:.85rem 1rem 1rem;border:1px solid rgba(9,63,52,.1);border-radius:18px;background:rgba(255,255,255,.97);box-shadow:0 15px 34px rgba(9,63,52,.13)}.wellness-search-main{display:flex;gap:.68rem;align-items:stretch}.wellness-search-input{display:flex;min-width:0;flex:1;align-items:center;gap:.8rem;padding:0 1rem;border:1px solid #d4e2d9;border-radius:12px;background:#fcfdfb}.wellness-search-input svg{width:1.32rem;height:1.32rem;flex:0 0 auto;fill:none;stroke:#426b5e;stroke-width:2}.wellness-search-input input{width:100%;min-height:3.42rem;border:0;outline:0;background:transparent;color:#26483e;font:inherit}.wellness-search-input input::placeholder{color:#7f8d87}.wellness-button{min-height:3.42rem;border:1px solid transparent;border-radius:12px;padding:.65rem 1.15rem;background:var(--wellness-deep);color:#fff;font:inherit;font-size:.9rem;font-weight:800;cursor:pointer;transition:transform .18s ease,background .18s ease,box-shadow .18s ease}.wellness-button:hover{background:#085543;box-shadow:0 8px 17px rgba(6,59,49,.18);transform:translateY(-1px)}.wellness-button:disabled{cursor:wait;opacity:.7;transform:none}.wellness-button.secondary{background:#e8f3ed;border-color:#c7e1d4;color:#185d48}.wellness-button.secondary:hover{background:#dceee5}.wellness-search-options{display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.75rem .25rem 0;color:#74817c;font-size:.77rem}.public-source-toggle{display:inline-flex;gap:.58rem;align-items:flex-start;cursor:pointer}.public-source-toggle input{position:relative;width:2.35rem;height:1.38rem;margin:.08rem 0 0;appearance:none;border-radius:999px;background:#d1dfd7;cursor:pointer;transition:background .18s}.public-source-toggle input:before{position:absolute;top:3px;left:3px;width:1.02rem;height:1.02rem;border-radius:50%;content:"";background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.16);transition:transform .18s}.public-source-toggle input:checked{background:#08735c}.public-source-toggle input:checked:before{transform:translateX(.97rem)}.public-source-toggle strong{display:block;color:#254d42;font-size:.82rem}.public-source-toggle small{display:block;color:#87928e;line-height:1.4}.wellness-clear{border:0;background:transparent;color:#21755d;font:inherit;font-weight:700;cursor:pointer;padding:.3rem}.wellness-clear:hover{text-decoration:underline}
    .wellness-topic-strip{display:flex;align-items:center;gap:.56rem;flex-wrap:wrap;margin:1.18rem 1rem 0}.topic-strip-label{display:inline-flex;align-items:center;gap:.45rem;margin-right:.28rem;color:#285448;font-size:.82rem;font-weight:850;white-space:nowrap}.topic-strip-label span{font-size:1rem}.topic-chip{border:1px solid #d7e4dc;border-radius:999px;padding:.54rem .88rem;background:#fff;color:#285548;font:inherit;font-size:.8rem;font-weight:750;cursor:pointer;box-shadow:0 2px 8px rgba(11,62,49,.035);transition:background .16s ease,border-color .16s ease,transform .16s ease}.topic-chip:hover{border-color:#83ae9c;background:#edf6ef;transform:translateY(-1px)}.topic-chip[data-active="true"]{border-color:var(--wellness-deep);background:var(--wellness-deep);color:#fff}.topic-strip-label:before{content:"\1f525"}.topic-chip:before{margin-right:.36rem}.topic-chip[data-topic="Stress"]:before{content:"\1f33f"}.topic-chip[data-topic="Sleep"]:before{content:"\263e"}.topic-chip[data-topic="Anxiety"]:before{content:"\2665"}.topic-chip[data-topic="Journaling"]:before{content:"\25a4"}.topic-chip[data-topic="Self-care"]:before{content:"\273b"}.topic-chip[data-topic="Academic Pressure"]:before{content:"\1f393"}.topic-chip[data-topic="Burnout"]:before{content:"\2600"}
    .wellness-section{margin-top:2.55rem}.wellness-section-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin:0 .15rem 1rem}.wellness-section-head h2{margin:0;color:var(--wellness-deep);font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.72rem,2.5vw,2.25rem);letter-spacing:-.045em;line-height:1}.wellness-section-head p{margin:.4rem 0 0;color:#73817c;line-height:1.5}.wellness-section-action{color:#145544;font-size:.87rem;font-weight:850;text-decoration:none;white-space:nowrap}.wellness-section-action:hover{text-decoration:underline}
    .topic-discovery{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.topic-card{position:relative;display:flex;min-width:0;min-height:235px;overflow:hidden;flex-direction:column;justify-content:flex-end;padding:0;border:1px solid #dce7de;border-radius:13px;background:#fff;color:var(--wellness-ink);text-align:left;cursor:pointer;box-shadow:0 8px 21px rgba(11,62,49,.055);transition:transform .2s ease,box-shadow .2s ease}.topic-card:hover{box-shadow:0 16px 28px rgba(11,62,49,.13);transform:translateY(-4px)}.topic-card-photo{position:relative;display:block;height:122px;overflow:hidden;background:#dcece0}.topic-card-photo:after{position:absolute;inset:0;content:"";background:linear-gradient(180deg,rgba(3,55,45,.02),rgba(3,55,45,.15))}.topic-card img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .35s ease}.topic-card:hover img{transform:scale(1.045)}.topic-card-icon{position:absolute;z-index:2;top:13px;left:14px;display:grid;place-items:center;width:37px;height:37px;border:1px solid rgba(255,255,255,.75);border-radius:50%;background:rgba(255,253,240,.94);color:#307f69;font-size:1.1rem;box-shadow:0 3px 10px rgba(8,63,52,.12)}.topic-card-copy{display:block;padding:.72rem 3.5rem .85rem 1rem}.topic-card-title{display:block;color:var(--wellness-deep);font-family:Georgia,'Times New Roman',serif;font-size:1.08rem;font-weight:700;line-height:1.15}.topic-card-description{display:block;display:-webkit-box;overflow:hidden;margin-top:.32rem;color:#73817c;font-size:.8rem;line-height:1.35;-webkit-box-orient:vertical;-webkit-line-clamp:2}.topic-card-arrow{position:absolute;right:.95rem;bottom:1rem;display:grid;place-items:center;width:2.15rem;height:2.15rem;border-radius:50%;background:#f0f5ed;color:#0d5644;font-size:1.25rem;transition:background .18s,transform .18s}.topic-card:hover .topic-card-arrow{background:#dbeee0;transform:translateX(2px)}.topic-card:nth-child(1) .topic-card-icon:after{content:"\1f33f"}.topic-card:nth-child(2) .topic-card-icon:after{content:"\263e"}.topic-card:nth-child(3) .topic-card-icon:after{content:"\2665"}.topic-card:nth-child(4) .topic-card-icon:after{content:"\25a4"}
    .resource-results{margin-top:2.15rem}.resource-results-heading{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin:0 .15rem 1rem}.resource-results-heading h2{margin:0;color:var(--wellness-deep);font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.65rem,2.35vw,2.15rem);letter-spacing:-.04em;line-height:1.05}.resource-results-heading p{margin:.4rem 0 0;color:#73817c;font-size:.9rem;line-height:1.45}.resource-results-count{flex:0 0 auto;color:#5f796d;font-size:.8rem;font-weight:800;white-space:nowrap}.resource-filter-row{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1.15rem}.resource-filter{border:1px solid #d5e8df;border-radius:999px;padding:.44rem .72rem;background:#f8faf7;color:#236b55;text-decoration:none;font-size:.83rem;font-weight:750}.resource-filter.active,.resource-filter:hover{background:#e8f3ed;border-color:#21755d}.resource-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.resource-card{position:relative;display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid rgba(16,63,50,.12);border-radius:16px;background:#fff;box-shadow:0 8px 21px rgba(16,63,50,.055);transition:transform .18s ease,box-shadow .18s ease}.resource-card:hover{transform:translateY(-3px);box-shadow:0 15px 30px rgba(16,63,50,.11)}.resource-thumb{display:block;width:100%;aspect-ratio:16/9;object-fit:contain;object-position:center;padding:.62rem;background:linear-gradient(145deg,#edf6ef,#f9fbf7)}.resource-card-body{display:flex;flex:1;flex-direction:column;padding:1.05rem}.resource-badge-row{display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;margin-bottom:.62rem}.resource-badge{display:inline-flex;max-width:100%;border-radius:999px;padding:.28rem .55rem;background:#e8f3ed;color:#21755d;font-size:.72rem;font-weight:850;letter-spacing:.045em;text-transform:uppercase}.resource-badge.public{background:#fff5d6;color:#74560b}.resource-card h3{margin:0;color:#103f32;font-family:Georgia,'Times New Roman',serif;font-size:1.08rem;line-height:1.34}.resource-description{display:-webkit-box;overflow:hidden;margin:.6rem 0 0;color:#61736a;line-height:1.57;-webkit-line-clamp:3;-webkit-box-orient:vertical}.resource-attribution{margin:.72rem 0 0;color:#778982;font-size:.79rem;line-height:1.45}.resource-actions{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:auto;padding-top:1rem}.resource-link{color:#21755d;font-size:.9rem;font-weight:800;text-decoration:none}.resource-link:hover{text-decoration:underline}.resource-link.external:after{content:"\2197";display:inline-block;margin-left:.25rem}.resource-public{margin-top:2rem;padding:1.3rem;border:1px solid #d5e8df;border-radius:20px;background:#f8faf7}.resource-public[hidden]{display:none!important}.resource-public .resource-card{background:#fff}.resource-public-head{display:flex;align-items:flex-start;gap:.8rem;margin-bottom:1rem}.resource-public-icon{display:grid;place-items:center;width:2.25rem;height:2.25rem;flex:0 0 auto;border-radius:10px;background:#fff5d6;color:#8b6611;font-weight:900}.resource-public-head h3{margin:0;color:#103f32}.resource-public-head p{margin:.25rem 0 0;color:#657970;font-size:.9rem;line-height:1.5}
    .topic-chip,.topic-card{text-decoration:none}
    .featured-audio{margin:2.4rem .15rem 0}.featured-audio-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}.featured-audio-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid #dbe8dd;border-radius:16px;background:#fff;box-shadow:0 7px 20px rgba(16,63,50,.055);transition:transform .2s ease,box-shadow .2s ease}.featured-audio-card:hover{transform:translateY(-3px);box-shadow:0 14px 26px rgba(16,63,50,.12)}.featured-audio-image{position:relative;height:110px;overflow:hidden;background:#e7f1e7}.featured-audio-image img{display:block;width:100%;height:100%;object-fit:cover}.featured-audio-image:after{position:absolute;inset:0;content:"";background:linear-gradient(0deg,rgba(6,54,42,.35),transparent 65%)}.featured-audio-mark{position:absolute;z-index:1;left:.75rem;bottom:.65rem;display:grid;place-items:center;width:2.2rem;height:2.2rem;border:1px solid rgba(255,255,255,.65);border-radius:50%;background:#fffdf2;color:#155a46}.featured-audio-mark svg{width:1.1rem;height:1.1rem;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.featured-audio-body{display:flex;flex:1;flex-direction:column;padding:.9rem 1rem 1rem}.featured-audio-meta{color:#567a66;font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.featured-audio-body h3{margin:.45rem 0 .4rem;color:#103f32;font-size:.96rem;line-height:1.35}.featured-audio-source{margin:0;color:#667e70;font-size:.77rem;line-height:1.4}.featured-audio-link{margin-top:auto;padding-top:.85rem;color:#126044;font-size:.82rem;font-weight:800;text-decoration:none}.featured-audio-link:hover{text-decoration:underline}.featured-audio-note{margin:.75rem .15rem 0;color:#61796e;font-size:.78rem;line-height:1.45}@media(max-width:1160px){.featured-audio-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.featured-audio{margin:2rem 0 0}.featured-audio-grid{grid-template-columns:1fr}.featured-audio-card{flex-direction:row;min-height:120px}.featured-audio-image{width:110px;height:auto;min-height:120px;flex:0 0 110px}.featured-audio-body{padding:.75rem}.featured-audio-body h3{font-size:.88rem}.featured-audio-link{padding-top:.55rem}}@media(prefers-reduced-motion:reduce){.featured-audio-card{transition:none}}
    .featured-audio-card{height:100%;color:inherit;text-decoration:none;cursor:pointer}.featured-audio-card:focus-visible{outline:3px solid #d8a825;outline-offset:3px}
    .wellness-status.loading{display:flex;align-items:center;gap:.65rem;margin:.85rem 0 0;padding:.8rem 1rem;border:1px solid #badac9;border-radius:12px;background:#e9f5ec;color:#15523f;font-weight:750}
    .wellness-status.loading::before{content:"";width:1rem;height:1rem;flex:0 0 auto;border:2px solid #a5cab4;border-top-color:#116c50;border-radius:50%;animation:wellness-spin .8s linear infinite}
    .search-loading{padding:clamp(1.1rem,2vw,1.65rem);border:1px solid #cfe3d4;border-radius:20px;background:linear-gradient(135deg,#f3faf3,#fffefa);box-shadow:0 10px 28px rgba(16,63,50,.06)}
    .search-loading[hidden],.search-results-content[hidden]{display:none!important}
    .search-loading-heading{display:flex;align-items:center;gap:1rem}
    .search-loading-spinner{width:2.65rem;height:2.65rem;flex:0 0 auto;border:3px solid #c6e2cf;border-top-color:#176d52;border-radius:50%;animation:wellness-spin .8s linear infinite}
    .search-loading h2{margin:0;color:#103f32;font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.3rem,2vw,1.65rem);line-height:1.2}
    .search-loading p{margin:.28rem 0 0;color:#536f60;font-size:.9rem;line-height:1.5}
    .search-loading-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin-top:1.2rem}
    .search-loading-card{min-height:112px;padding:.95rem;border:1px solid #dcebe0;border-radius:14px;background:#fff}
    .search-loading-card .skeleton-block{margin:.55rem 0;height:11px}.search-loading-card .skeleton-block.short{width:58%}
    @keyframes wellness-spin{to{transform:rotate(360deg)}}
    @media(max-width:620px){.search-loading-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.search-loading-card:last-child{display:none}.search-loading-spinner{width:2.2rem;height:2.2rem}}
    @media(prefers-reduced-motion:reduce){.search-loading-spinner,.wellness-status.loading::before{animation:none}.skeleton-block{animation:none}}
    .wellness-empty{padding:1.4rem;border:1px dashed #b9d7c9;border-radius:16px;background:#f8faf7;color:#50685d;line-height:1.6}.wellness-empty strong{color:#103f32}.wellness-status{min-height:1.5rem;margin:.72rem 0 0;color:#48675a;font-size:.9rem;line-height:1.5}.wellness-status.error{color:#8a3529}.wellness-status.notice{padding:.7rem .85rem;border:1px solid #efce73;border-radius:10px;background:#fff8df;color:#5b4a12}.skeleton-card{overflow:hidden;border:1px solid #d9e8e1;border-radius:18px;background:#fff}.skeleton-block{margin:.9rem 1rem;border-radius:7px;background:linear-gradient(90deg,#eef5f0 25%,#f8faf7 38%,#eef5f0 55%);background-size:200% 100%;animation:wellness-shimmer 1.15s infinite}.skeleton-block.image{height:115px;margin:0}.skeleton-block.line{height:13px}.skeleton-block.line.short{width:54%}@keyframes wellness-shimmer{to{background-position:-200% 0}}
    .support-directory{padding-top:2.15rem;border-top:1px solid #dcebe4}.support-directory .wellness-section-head{margin-bottom:.7rem}.support-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}.support-card{display:flex;min-width:0;align-items:flex-start;gap:.78rem;border:1px solid #e0e9e1;border-radius:12px;background:#fff;padding:1rem .86rem;box-shadow:0 5px 15px rgba(11,62,49,.035);transition:transform .18s,box-shadow .18s}.support-card:hover{box-shadow:0 10px 21px rgba(11,62,49,.09);transform:translateY(-2px)}.support-mark{display:grid;place-items:center;width:2.45rem;height:2.45rem;flex:0 0 auto;border-radius:50%;background:#edf5e9;color:#20745e;font-size:1.1rem}.support-card:nth-child(1) .support-mark:after{content:"\2767"}.support-card:nth-child(2) .support-mark:after{content:"\271a"}.support-card:nth-child(3) .support-mark:after{content:"\25cf"}.support-card:nth-child(4) .support-mark:after{content:"\260e"}.support-card-content{min-width:0;flex:1}.support-card h3{margin:0;color:#103f32;font-family:Georgia,'Times New Roman',serif;font-size:.9rem;line-height:1.15}.support-type{margin:.26rem 0 0;color:#7b958b;font-size:.69rem;font-weight:800;text-transform:uppercase;letter-spacing:.045em}.support-card p{display:-webkit-box;overflow:hidden;margin:.42rem 0 0;color:#728079;font-size:.76rem;line-height:1.35;-webkit-box-orient:vertical;-webkit-line-clamp:2}.support-arrow{display:grid;place-items:center;width:1.9rem;height:1.9rem;margin-top:auto;border-radius:50%;background:#eef5ed;color:#0d5845;font-size:1.08rem;text-decoration:none}.support-arrow:hover{background:#dceee0}.support-note{margin:0 0 1rem;padding:.75rem .9rem;border-left:3px solid #e6b940;background:#fffaf0;color:#65521d;font-size:.88rem;line-height:1.55}.wellness-footer-note{margin-top:2.3rem;color:#71837a;font-size:.79rem;line-height:1.55}.wellness-footer-note a{color:#21755d}
    [data-wellness-reveal]{opacity:0;transform:translateY(14px);transition:opacity .5s ease,transform .5s ease}[data-wellness-reveal].is-visible{opacity:1;transform:none}@media(max-width:1160px){.wellness-hero{grid-template-columns:56% 44%}.wellness-hero-art{left:47%}.topic-discovery{grid-template-columns:repeat(2,minmax(0,1fr))}.resource-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.support-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:820px){.wellness-hub{padding:.8rem 0 3rem}.wellness-hero{grid-template-columns:1fr;min-height:460px}.wellness-hero:before{inset:48% 0 0}.wellness-hero-copy-wrap{justify-content:flex-start;padding:2rem 1.45rem}.wellness-hero h1{font-size:clamp(2.7rem,9vw,4.1rem)}.wellness-hero-copy{max-width:480px}.wellness-hero-art{inset:43% 0 0}.wellness-hero-art:before{background:linear-gradient(180deg,#eff7eb 0%,rgba(239,247,235,0) 38%)}.hero-proof{display:none}.hero-note{top:auto;right:11%;bottom:21%;font-size:1rem}.hero-wellbeing-badge{right:-.85rem;bottom:4%;width:114px;min-height:104px;font-size:.78rem}.wellness-search-panel{margin:-1.25rem .7rem 0}.wellness-section{margin-top:2.1rem}}@media(max-width:620px){.wellness-breadcrumb{padding-left:.35rem}.wellness-hero{min-height:440px;border-radius:20px}.wellness-hero-copy-wrap{padding:1.8rem 1.15rem}.wellness-hero h1{max-width:330px;font-size:3rem}.wellness-hero-copy{max-width:315px;font-size:.98rem}.wellness-search-panel{padding:.72rem;border-radius:15px}.wellness-search-main{flex-wrap:wrap}.wellness-search-input{flex-basis:100%}.wellness-button{flex:1}.wellness-search-options{align-items:flex-start;flex-direction:column;padding:.7rem .15rem 0}.wellness-topic-strip{margin-left:.15rem;margin-right:.15rem}.topic-strip-label{flex-basis:100%}.wellness-section-head{display:block}.wellness-section-action{display:inline-block;margin-top:.65rem}.topic-discovery,.resource-grid,.support-grid{grid-template-columns:1fr}.topic-card{min-height:225px}.topic-card-photo{height:128px}.resource-thumb{aspect-ratio:16/7.6}.support-card{padding:1rem}.hero-note{display:none}.hero-wellbeing-badge{right:-1rem;bottom:4%;width:100px;min-height:93px;padding:1rem;font-size:.72rem}.hero-wellbeing-badge:before{position:absolute;top:18px;right:26px;content:"\2767";color:#5b916f;font-size:1.55rem;rotate:-30deg}}@media(prefers-reduced-motion:reduce){.wellness-button,.topic-card,.topic-card img,.topic-card-arrow,.support-card,[data-wellness-reveal]{transition:none}.skeleton-block{animation:none}}
    /* Keep every resource thumbnail at a stable article ratio, including mobile. */
    .wellness-hub { width: 100%; min-width: 0; overflow-x: clip; }
    .wellness-search-panel, .wellness-search-main, .wellness-search-options { min-width: 0; }
    .resource-thumb { aspect-ratio: 16 / 9; }
    @media (max-width: 620px) {
        .wellness-hub { max-width: 100%; }
        .wellness-search-panel { width: 100%; max-width: 100%; }
        .wellness-search-main { width: 100%; }
        .wellness-button { min-width: 0; padding-inline: .6rem; font-size: .78rem; }
        .wellness-button:not(.secondary) { flex: 1 1 0; }
        .wellness-button.secondary { flex: 0 1 auto; }
        .wellness-button > span:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .public-source-toggle, .public-source-toggle > span { min-width: 0; }
        .public-source-toggle > span { overflow-wrap: anywhere; }
        .wellness-search-options > span { max-width: 100%; overflow-wrap: anywhere; }
    }
    .wellness-opt-ins{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem 1.35rem;width:min(100%,920px)}.wellness-search-privacy{grid-column:1/-1;max-width:760px;color:#687a72;font-size:.76rem;line-height:1.45}.source-tabs{display:flex;gap:.45rem;overflow-x:auto;padding:.25rem .1rem .8rem;margin:.1rem 0 1rem;border-bottom:1px solid #dfeae2}.source-tab{flex:0 0 auto;border:1px solid #d8e7dd;border-radius:999px;padding:.58rem .82rem;background:#fff;color:#426457;font:inherit;font-size:.83rem;font-weight:750;cursor:pointer}.source-tab span{display:inline-grid;place-items:center;min-width:1.45rem;height:1.45rem;margin-left:.24rem;padding:0 .3rem;border-radius:999px;background:#edf4ee;color:#335c4d;font-size:.72rem}.source-tab.active{border-color:#0b503f;background:#0b503f;color:#fff}.source-tab.active span{background:rgba(255,255,255,.18);color:#fff}.source-tab:focus-visible{outline:3px solid rgba(216,168,37,.8);outline-offset:2px}.result-group{margin:1.2rem 0 1.6rem}.result-group[hidden],.source-tabs[hidden],.video-dialog[hidden]{display:none!important}.result-group-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin:0 0 .9rem}.result-group-heading h3{margin:0;color:#164c3d;font-family:Georgia,'Times New Roman',serif;font-size:1.25rem}.result-group-heading p{margin:.28rem 0 0;color:#73817c;font-size:.83rem;line-height:1.45}.resource-provider-state{flex:0 0 auto;color:#71827a;font-size:.75rem;font-weight:750}.resource-media,.resource-type-mark{position:relative;display:grid;place-items:center;width:100%;aspect-ratio:16/9;overflow:hidden;background:#edf4ee}.resource-thumb{width:100%;height:100%;aspect-ratio:16/9;padding:0;object-fit:cover;object-position:center;background:#edf4ee}.resource-type-mark{align-content:center;gap:.25rem;color:#477261}.resource-type-mark span{display:grid;place-items:center;width:2.35rem;height:2.35rem;border:1px solid #d3e4d8;border-radius:50%;background:#fff;color:#187155;font-size:1.15rem}.resource-type-mark small{color:#73837b;font-size:.72rem;font-weight:750;letter-spacing:.035em}.resource-card-body{min-height:205px}.video-duration{position:absolute;right:.55rem;bottom:.55rem;border-radius:5px;padding:.19rem .35rem;background:rgba(2,36,29,.86);color:#fff;font-size:.72rem;font-variant-numeric:tabular-nums}.play-video{border:0;background:none;font:inherit;cursor:pointer}.overall-empty{margin-top:1rem}.library-empty-note{padding:.4rem .2rem;color:#77857e;font-size:.82rem}.video-dialog{width:min(94vw,960px);max-width:none;max-height:90vh;padding:0;border:1px solid #d6e4da;border-radius:18px;background:#fff;color:#20473b;box-shadow:0 24px 90px rgba(1,25,19,.38)}.video-dialog::backdrop{background:rgba(3,34,27,.72);backdrop-filter:blur(3px)}.video-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.2rem}.video-dialog-head h2{margin:0;color:#113f32;font-family:Georgia,'Times New Roman',serif;font-size:1.1rem}.video-dialog-close{border:1px solid #d7e5db;border-radius:999px;padding:.48rem .72rem;background:#f8fbf8;color:#174c3b;font:inherit;font-weight:750;cursor:pointer}.video-player-frame{width:100%;aspect-ratio:16/9;background:#061d17}.video-player-frame iframe{display:block;width:100%;height:100%;border:0}.video-dialog-note{margin:0;padding:.8rem 1.2rem 1rem;color:#73817c;font-size:.78rem;line-height:1.5}.wellness-status.error{padding:.62rem .8rem;border:1px solid #f1d0ca;border-radius:10px;background:#fff8f6}.wellness-status.notice{padding:.62rem .8rem;border:1px solid #efce73;border-radius:10px;background:#fff8df;color:#65521d}.topic-card-photo{height:auto;aspect-ratio:16/9;flex:0 0 auto}.topic-card-photo img{object-fit:cover;object-position:center;width:100%;height:100%}.topic-card{min-height:0}.topic-card-copy{min-height:90px}.resource-card[hidden]{display:none!important}
    @media(max-width:820px){.wellness-opt-ins{grid-template-columns:1fr}.wellness-search-privacy{grid-column:auto}.resource-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:620px){.wellness-opt-ins{gap:.75rem}.result-count{white-space:normal}.resource-results-heading{align-items:flex-start;flex-direction:column}.resource-results-count{white-space:normal}.result-group-heading{align-items:flex-start;flex-direction:column}.resource-provider-state{align-self:flex-start}.resource-grid{grid-template-columns:1fr}.topic-card-photo{height:auto;aspect-ratio:16/9}.topic-card-copy{min-height:84px}.video-dialog{width:96vw;border-radius:13px}.video-dialog-head{padding:.78rem}.video-dialog-note{padding:.65rem .78rem .8rem}}
    @media(prefers-reduced-motion:reduce){.resource-card,.resource-thumb{transition:none!important}}
    /* Editorial resource workspace: compact cards, text-first articles, restrained reader. */
    .resource-results{width:min(100%,1240px);margin-inline:auto}.resource-grid{gap:1rem}.resource-card{border-radius:15px;box-shadow:0 5px 18px rgba(16,63,50,.045)}.resource-card:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(16,63,50,.09)}.resource-card-body{min-height:0;padding:.95rem 1rem 1rem}.resource-card h3{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:1rem;font-weight:760;letter-spacing:-.015em;line-height:1.38}.resource-badge-row{gap:.35rem;margin-bottom:.48rem}.resource-badge{padding:.24rem .48rem;font-size:.67rem}.resource-description{margin:.45rem 0 0;font-size:.86rem;line-height:1.48;-webkit-line-clamp:3}.resource-attribution{margin:.58rem 0 0;font-size:.74rem;line-height:1.4}.resource-actions{gap:.7rem;padding-top:.78rem}.resource-link{font-size:.82rem}
    .article-card{min-height:250px}.article-card-main,.video-card-main{display:flex;min-width:0;flex:1;flex-direction:column;color:inherit;text-decoration:none}.article-card-main{padding:1rem 1rem 0}.article-card-main:hover h3,.article-card-main:focus-visible h3{text-decoration:underline;text-decoration-color:#8bb9a2;text-underline-offset:3px}.article-card-main:focus-visible,.video-card-main:focus-visible{outline:3px solid rgba(216,168,37,.85);outline-offset:-4px;border-radius:14px}.article-card .resource-card-body{padding:0;flex:1}.article-card .resource-actions{padding:.2rem 1rem 1rem}.article-card .article-read-cue{display:inline-flex;align-items:center;gap:.35rem;margin-top:auto;padding-top:.85rem;color:#12604a;font-size:.78rem;font-weight:800}.article-type-icon{display:inline-grid;place-items:center;width:1.65rem;height:1.65rem;margin-right:.38rem;border:1px solid #d7e5da;border-radius:50%;background:#f6faf6;color:#26715a;font-size:.83rem;vertical-align:middle}.article-card .resource-description{-webkit-line-clamp:3}.resource-card .resource-media{flex:0 0 auto}.video-card .resource-media{border-radius:0}.video-card-main{position:relative}.video-card-main .resource-card-body{min-height:0}.video-card-main .resource-media{position:relative}.video-play-affordance{position:absolute;inset:0;display:grid;place-items:center;color:#fff;pointer-events:none}.video-play-affordance span{display:grid;place-items:center;width:3rem;height:3rem;padding-left:.18rem;border:1px solid rgba(255,255,255,.78);border-radius:50%;background:rgba(4,54,42,.82);box-shadow:0 4px 14px rgba(0,0,0,.22);font-size:1.15rem}.video-card-main:hover .video-play-affordance span{background:#0b5944;transform:scale(1.04)}.video-card .resource-actions{padding:.1rem 1rem .9rem}.video-card .resource-card-body{padding:.8rem 1rem .65rem}.video-card .resource-description{display:none}.video-card .resource-card h3,.video-card h3{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2}.video-card .video-duration{right:.55rem;bottom:.55rem;z-index:1}
    .article-dialog{width:min(92vw,820px);max-width:none;max-height:min(88vh,900px);overflow:auto;padding:0;border:1px solid #d5e4da;border-radius:18px;background:#fff;color:#28483e;box-shadow:0 24px 90px rgba(1,25,19,.32)}.article-dialog::backdrop{background:rgba(3,34,27,.63);backdrop-filter:blur(2px)}.article-dialog-head{position:sticky;top:0;z-index:2;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #e4ece5;background:rgba(255,255,255,.97)}.article-dialog-head h2{margin:.4rem 0 0;color:#123f33;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:clamp(1.2rem,2vw,1.65rem);line-height:1.3;letter-spacing:-.025em}.article-dialog-kicker{color:#21755d;font-size:.7rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.article-dialog-close{display:grid;place-items:center;width:2.45rem;height:2.45rem;flex:0 0 auto;border:1px solid #d7e5db;border-radius:50%;background:#f7faf7;color:#174c3b;font-size:1.25rem;cursor:pointer}.article-dialog-meta{display:flex;flex-wrap:wrap;gap:.35rem .8rem;padding:.8rem 1.25rem 0;color:#708078;font-size:.8rem}.article-dialog-content{max-width:70ch;margin:0 auto;padding:1.1rem clamp(1rem,4vw,2.5rem) 1.5rem;color:#344f45;font-size:1rem;line-height:1.82;white-space:pre-line}.article-dialog-content p{margin:0 0 1em}.article-dialog-note{max-width:70ch;margin:0 auto;padding:.8rem clamp(1rem,4vw,2.5rem);border-top:1px solid #edf1ed;color:#728078;font-size:.82rem;line-height:1.55}.article-dialog-actions{display:flex;flex-wrap:wrap;gap:.7rem;padding:1rem 1.25rem 1.15rem;border-top:1px solid #e4ece5}.article-dialog-source{display:inline-flex;align-items:center;min-height:2.65rem;border-radius:10px;padding:.58rem .9rem;background:#0d503f;color:#fff;font-size:.84rem;font-weight:800;text-decoration:none}.article-dialog-source:hover{background:#08735c}.article-dialog-source[hidden]{display:none}
    .video-dialog{width:min(92vw,900px);max-height:88vh;border-radius:16px}.video-dialog-head{padding:.75rem 1rem}.video-dialog-head h2{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:1rem;font-weight:760}.video-dialog-close{width:2.4rem;height:2.4rem;padding:0;border-radius:50%;font-size:1.15rem}.video-dialog-note{padding:.62rem 1rem .8rem;font-size:.74rem}.topic-card{border-radius:15px}.topic-card-photo{aspect-ratio:16/9}.topic-card-copy{min-height:88px}
    @media(max-width:820px){.resource-results{padding-inline:.25rem}.article-card{min-height:230px}}
    @media(max-width:620px){.resource-results{padding-inline:0}.resource-grid{grid-template-columns:1fr}.article-card{min-height:0}.article-card-main{padding:.9rem .9rem 0}.article-card .resource-actions{padding:.2rem .9rem .9rem}.video-card .resource-actions{padding:.1rem .9rem .8rem}.article-dialog{width:calc(100vw - 24px);max-height:calc(100vh - 24px);border-radius:14px}.article-dialog-head{padding:.8rem}.article-dialog-meta{padding:.68rem .85rem 0}.article-dialog-content{padding:1rem .9rem 1.2rem;font-size:.96rem}.article-dialog-note{padding:.75rem .9rem}.article-dialog-actions{padding:.8rem}.video-dialog{width:calc(100vw - 24px);max-height:calc(100vh - 24px);border-radius:13px}.video-dialog-head{padding:.7rem .8rem}.video-dialog-note{padding:.65rem .8rem}}
    .search-submit{display:grid;width:3.42rem;min-width:3.42rem;flex:0 0 3.42rem;place-items:center;padding:0}.search-submit svg{width:1.25rem;height:1.25rem;fill:none;stroke:currentColor;stroke-width:2.2}.video-dialog-attribution{margin:0;padding:.25rem 1rem 0;color:#536b60;font-size:.76rem;font-weight:700}.video-dialog-attribution:empty{display:none}
    @media(max-width:620px){.search-submit{width:3rem;min-width:3rem;flex:0 0 3rem}.video-dialog-attribution{padding:.2rem .8rem 0}}
    @media(prefers-reduced-motion:reduce){.video-play-affordance span{transition:none}}

    /* The app layout resets all margins to zero; native dialog auto-centering is therefore restored explicitly. */
    .wellness-modal,.wellness-modal *{box-sizing:border-box}
    .wellness-modal{position:fixed!important;inset:0!important;z-index:1200!important;display:none!important;place-items:center!important;width:100vw!important;height:100vh!important;height:100dvh!important;max-width:none!important;max-height:none!important;margin:0!important;padding:max(12px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(12px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left))!important;overflow:hidden!important;border:0!important;background:transparent!important;color:#28483e}
    .wellness-modal[open]{display:grid!important}
    .wellness-modal::backdrop{position:fixed;inset:0;background:rgba(3,34,27,.68);backdrop-filter:blur(2px)}
    .wellness-modal-panel{display:grid;min-width:0;overflow:hidden;border:1px solid #d5e4da;border-radius:18px;background:#fff;box-shadow:0 24px 90px rgba(1,25,19,.34)}
    .article-dialog-panel{grid-template-rows:auto minmax(0,1fr) auto;width:min(100%,820px);max-height:min(88vh,900px);max-height:min(88dvh,900px)}
    .article-dialog-head{position:relative;top:auto;z-index:auto}
    .article-dialog-body{min-height:0;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable}
    .article-dialog-meta{max-width:70ch;margin:0 auto}
    .article-dialog-content{overflow:visible;white-space:normal;overflow-wrap:anywhere}
    .article-dialog-note{max-width:70ch;margin:0 auto}
    .article-dialog-actions{position:relative;z-index:1;background:#fff}
    .video-dialog-panel{grid-template-rows:auto auto auto auto auto;width:min(100%,880px);max-height:90vh;max-height:90dvh}
    .video-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-width:0}
    .video-dialog-head h2{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .video-dialog-close,.article-dialog-close{display:grid;place-items:center;width:2.4rem;height:2.4rem;flex:0 0 auto;padding:0;border:1px solid #d7e5db;border-radius:50%;background:#f7faf7;color:#174c3b;font:inherit;font-size:1.25rem;cursor:pointer}
    .video-player-frame{width:100%;height:auto;max-height:calc(90dvh - 11rem);aspect-ratio:16/9;overflow:hidden;background:#061d17}
    .video-player-frame iframe{display:block;width:100%;height:100%;border:0}
    .video-player-frame[data-player-provider="spotify"]{height:min(352px,calc(90dvh - 12rem));aspect-ratio:auto;background:#edf4ee}
    .video-dialog-source{display:inline-block;padding:0 1rem .8rem;color:#126044;font-size:.8rem;font-weight:750;text-decoration:underline}
    .video-dialog-source[hidden]{display:none!important}
    .video-dialog-attribution,.video-dialog-note{min-width:0;overflow-wrap:anywhere}
    .video-dialog-note{margin:0}
    html.wellness-modal-open,body.wellness-modal-open{overflow:hidden!important;overscroll-behavior:none}
    .app-main.wellness-modal-scroll-lock{overflow:hidden!important;overscroll-behavior:none}
    .video-hover-preview{position:fixed;z-index:40;display:block;overflow:hidden;opacity:0;pointer-events:none;background:#061d17;transition:opacity .12s ease}
    .video-hover-preview.is-playing{opacity:1}
    .video-hover-preview[hidden]{display:none!important}
    .video-hover-preview iframe{display:block;width:100%;height:100%;border:0;pointer-events:none}
    @media(max-width:620px){
        .article-dialog-panel{width:100%;max-height:calc(100vh - 24px);max-height:calc(100dvh - 24px);border-radius:14px}
        .article-dialog-head{padding:.8rem}
        .article-dialog-body{scrollbar-gutter:auto}
        .article-dialog-meta{padding:.68rem .85rem 0}
        .article-dialog-content{padding:1rem .9rem 1.2rem;font-size:.96rem}
        .article-dialog-note{padding:.75rem .9rem}
        .article-dialog-actions{padding:.8rem}
        .video-dialog-panel{width:100%;max-height:calc(100vh - 24px);max-height:calc(100dvh - 24px);border-radius:13px}
        .video-dialog-head{padding:.7rem .8rem}
        .video-player-frame{max-height:calc(90dvh - 11rem)}
        .video-dialog-attribution{padding:.2rem .8rem 0}
        .video-dialog-note{padding:.65rem .8rem .8rem}
    }
    @media(prefers-reduced-motion:reduce){.video-hover-preview{transition:none}}

    /* Compact resource discovery: preserve choices while reducing scroll and repeated copy. */
    .wellness-hub{padding:.65rem .25rem 2rem}
    .wellness-breadcrumb{margin-bottom:.5rem;font-size:.74rem}
    .wellness-hero{height:236px;min-height:236px;border-radius:20px;box-shadow:0 10px 28px rgba(10,65,51,.08)}
    .wellness-hero-copy-wrap{padding:1.3rem clamp(1.2rem,3vw,2.5rem)}
    .wellness-hero h1{max-width:490px;margin:.55rem 0 .45rem;font-size:clamp(2.15rem,3.9vw,3.25rem);line-height:.98}
    .wellness-hero-copy{max-width:430px;font-family:inherit;font-size:.94rem;line-height:1.45}
    .hero-proof,.hero-note,.hero-wellbeing-badge{display:none!important}
    .wellness-search-panel{margin:-.85rem clamp(.5rem,1.5vw,1.1rem) 0;padding:.58rem .68rem .62rem;border-radius:14px;box-shadow:0 8px 20px rgba(9,63,52,.09)}
    .wellness-search-main{gap:.45rem}.wellness-search-input{gap:.55rem;padding-inline:.75rem}
    .wellness-search-input input,.wellness-button{min-height:2.75rem}.wellness-button{padding:.5rem .75rem}
    .wellness-search-options{padding:.5rem .15rem 0;font-size:.7rem}.wellness-opt-ins{gap:.4rem 1rem;width:100%}
    .public-source-toggle{gap:.45rem;align-items:center}.public-source-toggle input{width:2rem;height:1.18rem;margin:0}
    .public-source-toggle input:before{width:.86rem;height:.86rem}.public-source-toggle input:checked:before{transform:translateX(.82rem)}
    .public-source-toggle strong{font-size:.74rem}.public-source-toggle small{font-size:.65rem;line-height:1.25}
    .wellness-search-privacy{max-width:none;font-size:.65rem;line-height:1.3}
    .wellness-section{margin-top:1.25rem}.wellness-section-head{margin:0 .1rem .55rem}
    .wellness-section-head h2{font-size:1.35rem;line-height:1.1}.featured-audio{margin:1.2rem .1rem 0}
    .featured-audio-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:.55rem}
    .featured-audio-card{height:100%;min-height:82px;flex-direction:row;border-radius:12px;box-shadow:0 3px 10px rgba(16,63,50,.04)}
    .featured-audio-card:hover{transform:translateY(-1px);box-shadow:0 7px 15px rgba(16,63,50,.08)}
    .featured-audio-image{width:68px;height:auto;min-height:82px;flex:0 0 68px}.featured-audio-image:after{background:linear-gradient(0deg,rgba(6,54,42,.2),transparent 65%)}
    .featured-audio-mark{left:.35rem;bottom:.35rem;width:1.55rem;height:1.55rem}.featured-audio-mark svg{width:.85rem;height:.85rem}
    .featured-audio-body{justify-content:center;padding:.48rem .52rem}.featured-audio-meta{font-size:.58rem;letter-spacing:.035em}
    .featured-audio-body h3{display:-webkit-box;overflow:hidden;margin:.18rem 0;color:#103f32;font-size:.75rem;line-height:1.22;-webkit-box-orient:vertical;-webkit-line-clamp:2}
    .featured-audio-source{font-size:.63rem;line-height:1.2}.featured-audio-note{margin:.32rem .1rem 0;font-size:.65rem;line-height:1.3}
    .resource-results{margin-top:1.1rem}.resource-results[hidden]{display:none!important}
    .resource-results-heading{margin-bottom:.65rem}.resource-results-heading h2{font-size:1.45rem}
    .resource-results-heading p,.result-group-heading p{font-size:.76rem}.result-group{margin:.8rem 0 1rem}
    .topic-discovery{grid-template-columns:repeat(4,minmax(0,1fr));gap:.55rem}
    .topic-card{display:grid;grid-template-columns:58px minmax(0,1fr);align-items:center;min-height:76px;justify-content:initial;border-radius:12px}
    .topic-card-photo{grid-row:1;grid-column:1;width:48px;height:48px;margin:.45rem;aspect-ratio:1;border-radius:9px}
    .topic-card-icon{display:none}.topic-card-copy{grid-row:1;grid-column:2;min-height:0;padding:.45rem 1.8rem .45rem .1rem}
    .topic-card-title{font-family:inherit;font-size:.76rem;line-height:1.2}.topic-card-description{margin-top:.15rem;font-size:.64rem;line-height:1.25;-webkit-line-clamp:2}
    .topic-card-arrow{right:.35rem;bottom:.35rem;width:1.35rem;height:1.35rem;font-size:.85rem}
    .support-directory{margin-top:1rem;padding-top:.7rem;border-top:1px solid #dcebe4}
    .support-directory-summary{display:flex;align-items:center;justify-content:space-between;gap:.7rem;list-style:none;padding:.62rem .78rem;border:1px solid #dce8dc;border-radius:11px;background:#fff;color:#174c3b;cursor:pointer}
    .support-directory-summary::-webkit-details-marker{display:none}.support-directory-summary strong{display:block;font-size:.8rem}
    .support-directory-summary small{display:block;margin-top:.1rem;color:#73817c;font-size:.67rem}
    .support-directory-chevron{font-size:1.1rem;transition:transform .16s ease}.support-directory[open] .support-directory-chevron{transform:rotate(45deg)}
    .support-directory-content{padding-top:.75rem}.support-directory .wellness-section-head h2{font-size:1.25rem}
    .support-note{margin-bottom:.65rem;padding:.55rem .7rem;font-size:.75rem;line-height:1.4}.support-grid{gap:.55rem}
    .support-card{gap:.5rem;padding:.65rem}.support-mark{width:1.8rem;height:1.8rem}
    .support-card h3{font-size:.8rem}.support-card p{font-size:.68rem}
    .wellness-footer-note{margin-top:.8rem;font-size:.68rem}
    @media(max-width:820px){
        .wellness-hub{padding:.55rem 0 1.5rem}.wellness-hero{height:220px;min-height:220px;grid-template-columns:1fr}
        .wellness-hero:before{inset:0 36% 0 0}.wellness-hero-copy-wrap{justify-content:center;padding:1.1rem 1.25rem}
        .wellness-hero h1{max-width:390px;font-size:clamp(2rem,7vw,2.8rem)}.wellness-hero-copy{max-width:330px;font-size:.86rem}
        .wellness-hero-art{inset:0 0 0 42%}.wellness-hero-art:before{background:linear-gradient(90deg,#eff7eb 0%,rgba(239,247,235,.62) 20%,rgba(239,247,235,0) 62%)}
        .wellness-search-panel{margin:-.75rem .55rem 0}.topic-discovery{grid-template-columns:repeat(2,minmax(0,1fr))}
        .support-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:620px){
        .wellness-breadcrumb{padding-left:.15rem}.wellness-hero{height:190px;min-height:190px;border-radius:15px}
        .wellness-hero-copy-wrap{padding:.9rem 1rem}.wellness-eyebrow{font-size:.61rem;letter-spacing:.1em}
        .wellness-hero h1{max-width:255px;margin:.4rem 0;font-size:2rem}.wellness-hero-copy{max-width:250px;font-size:.77rem;line-height:1.35}
        .wellness-hero-art{inset:0 0 0 48%}.wellness-hero-art:before{background:linear-gradient(90deg,#eff7eb 0%,rgba(239,247,235,.8) 12%,rgba(239,247,235,.03) 74%)}
        .wellness-search-panel{margin:-.65rem .35rem 0;padding:.48rem;border-radius:12px}.wellness-search-main{gap:.35rem}
        .wellness-search-input{flex-basis:auto;gap:.4rem;padding-inline:.55rem}.wellness-search-input input{min-height:2.55rem;font-size:.79rem}
        .search-submit{width:2.55rem;min-width:2.55rem;flex-basis:2.55rem;min-height:2.55rem}
        .wellness-button.secondary{min-height:2.55rem;padding:.35rem .48rem;font-size:.7rem}
        .wellness-search-options{padding:.42rem .1rem 0}.wellness-opt-ins{grid-template-columns:1fr;gap:.35rem}
        .public-source-toggle strong{font-size:.7rem}.public-source-toggle small{font-size:.61rem}.wellness-search-privacy{font-size:.6rem}
        .featured-audio{margin-top:.9rem}
        .featured-audio-grid{grid-auto-flow:column;grid-auto-columns:min(78vw,270px);grid-template-columns:none;gap:.45rem;overflow-x:auto;padding:.05rem .05rem .3rem;scroll-snap-type:x proximity}
        .featured-audio-card{min-height:72px;scroll-snap-align:start}.featured-audio-image{width:58px;min-height:72px;flex-basis:58px}
        .featured-audio-body{padding:.38rem .45rem}.featured-audio-meta{font-size:.54rem}
        .featured-audio-body h3{font-size:.71rem}.featured-audio-source{font-size:.6rem}.featured-audio-note{font-size:.61rem}
        .wellness-section{margin-top:.95rem}.wellness-section-head{display:flex;align-items:center;margin-bottom:.45rem}
        .wellness-section-head h2{font-size:1.2rem}
        .topic-discovery{grid-auto-flow:column;grid-auto-columns:min(76vw,265px);grid-template-columns:none;gap:.45rem;overflow-x:auto;padding:.05rem .05rem .3rem;scroll-snap-type:x proximity}
        .topic-card{min-height:70px;scroll-snap-align:start}.topic-card-photo{width:44px;height:44px;margin:.4rem}
        .topic-card-title{font-size:.72rem}.topic-card-description{font-size:.61rem}
        .support-grid{grid-template-columns:1fr}.support-directory .wellness-section-head{display:flex;align-items:center}
        .support-directory .wellness-section-action{margin:0;font-size:.7rem}.wellness-footer-note{margin-top:.7rem}
    }
    @media(prefers-reduced-motion:reduce){.support-directory-chevron{transition:none}}
    @media(max-width:620px){.wellness-hero-art{inset:0 0 0 48%}}
</style>

<div class="wellness-hub" data-wellness-hub>
    <nav class="wellness-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('student.insights') }}">Student space</a><span aria-hidden="true">/</span><span>Wellness resources</span></nav>

    <section class="wellness-hero" aria-labelledby="wellness-title" data-wellness-reveal>
        <div class="wellness-hero-copy-wrap">
            <span class="wellness-eyebrow">Wellness resource library</span>
            <h1 id="wellness-title">Wellness, at your pace.</h1>
            <p class="wellness-hero-copy">Helpful reads, calming audio, and practical tools for student life.</p>
        </div>
        <div class="wellness-hero-art" aria-hidden="true">
            <img src="{{ asset('images/wellness/hero-student-journal-v1.png') }}" alt="">
        </div>
    </section>

    <form class="wellness-search-panel" action="{{ route('student.resources') }}" method="get" data-wellness-search data-search-url="{{ route('student.resources.search') }}" aria-label="Search wellness resources">
        <div class="wellness-search-main">
            <label class="wellness-search-input" for="wellness-query"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.4"></circle><path d="m16 16 4.2 4.2"></path></svg><span class="sr-only">Search wellness resources</span><input id="wellness-query" type="search" name="q" maxlength="120" value="{{ $searchQuery }}" placeholder="Search stress, sleep, anxiety, and more" autocomplete="off"></label>
            <button class="wellness-button secondary" type="button" data-clear-search {{ $searchQuery === '' ? 'hidden' : '' }}>Clear</button>
            <button class="wellness-button search-submit" type="submit" data-search-button aria-label="Search resources" title="Search resources"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.4"></circle><path d="m16 16 4.2 4.2"></path></svg><span class="sr-only">Search library</span></button>
        </div>
        <div class="wellness-search-options">
            <div class="wellness-opt-ins">
                <label class="public-source-toggle"><input type="checkbox" name="public_sources" value="1" data-public-sources {{ $includePublicSources ? 'checked' : '' }}><span><strong>Web articles</strong><small>MedlinePlus and eligible research</small></span></label>
                <label class="public-source-toggle"><input type="checkbox" name="videos" value="1" data-video-sources {{ $includeVideos ? 'checked' : '' }}><span><strong>Videos</strong><small>YouTube @if(empty(config('services.wellness_resources.youtube.api_key'))) not configured @elseif(!config('services.wellness_resources.youtube.enabled')) unavailable @else available @endif</small></span></label>
                <span class="wellness-search-privacy">Only your submitted search terms are shared—not chats or journal entries.</span>
            </div>
        </div>
    </form>
    <div class="wellness-status" role="status" aria-live="polite" data-search-status></div>

    <section class="featured-audio" data-featured-audio-section aria-labelledby="featured-audio-title" {{ $hasSearchContext ? 'hidden' : '' }}>
        <div class="wellness-section-head"><div><h2 id="featured-audio-title">Listen</h2></div></div>
        <div class="featured-audio-grid">
            @foreach($featuredAudio as $audio)
                <article data-featured-audio>
                    <a class="featured-audio-card" href="{{ $audio['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" data-featured-player data-player-provider="{{ $audio['player'] }}" data-player-id="{{ $audio['id'] }}" data-player-title="{{ $audio['title'] }}" data-player-source="{{ $audio['source'] }}" aria-label="Play {{ $audio['title'] }} in MAGIREM AI">
                    <div class="featured-audio-image" aria-hidden="true">
                        <img src="{{ asset('images/wellness/' . $audio['image']) }}" alt="" loading="lazy" decoding="async">
                        <span class="featured-audio-mark"><svg viewBox="0 0 24 24"><path d="M4 13v-2a8 8 0 0 1 16 0v2M4 13h3v6H5a1 1 0 0 1-1-1v-5Zm16 0h-3v6h2a1 1 0 0 0 1-1v-5Z"/></svg></span>
                    </div>
                    <div class="featured-audio-body">
                        <span class="featured-audio-meta">{{ $audio['topic'] }} &middot; {{ $audio['type'] }}</span>
                        <h3>{{ $audio['title'] }}</h3>
                        <p class="featured-audio-source">{{ $audio['source'] }}</p>
                    </div>
                    </a>
                </article>
            @endforeach
        </div>
        <p class="featured-audio-note">Select a card to open its player.</p>
    </section>

    <section class="resource-results" aria-labelledby="results-title" data-resource-results {{ $hasSearchContext ? '' : 'hidden' }}>
        <div class="search-loading" data-search-loading hidden aria-hidden="true">
            <div class="search-loading-heading">
                <span class="search-loading-spinner"></span>
                <div><h2>Searching for resources</h2><p data-loading-detail>Checking the sources you selected...</p></div>
            </div>
            <div class="search-loading-grid">
                @for($i = 0; $i < 3; $i++)
                    <div class="search-loading-card"><div class="skeleton-block short"></div><div class="skeleton-block"></div><div class="skeleton-block short"></div></div>
                @endfor
            </div>
        </div>
        <div class="search-results-content" data-search-content>
        <div class="resource-results-heading">
            <div>
                <h2 id="results-title">{{ $hasSearchContext ? 'Search results' : 'Results' }}</h2>
                <p data-results-description>{{ $hasSearchContext ? 'Results from the sources you selected.' : '' }}</p>
            </div>
            <span class="resource-results-count" data-result-count {{ $hasSearchContext ? '' : 'hidden' }}>{{ $totalSearchResultCount }} {{ \Illuminate\Support\Str::plural('resource', $totalSearchResultCount) }}</span>
        </div>

        <nav class="source-tabs" aria-label="Resource result sources" role="tablist" data-source-tabs {{ $hasSearchContext ? '' : 'hidden' }}>
            <button class="source-tab active" type="button" role="tab" aria-selected="true" data-source-tab="all">All Results <span data-tab-count="all">{{ $totalSearchResultCount }}</span></button>
            <button class="source-tab" type="button" role="tab" aria-selected="false" data-source-tab="library">Library <span data-tab-count="library">{{ $localResultCount }}</span></button>
            <button class="source-tab" type="button" role="tab" aria-selected="false" data-source-tab="guides" {{ $guideCount ? '' : 'hidden' }}>Selected Guides <span data-tab-count="guides">{{ $guideCount }}</span></button>
            <button class="source-tab" type="button" role="tab" aria-selected="false" data-source-tab="web" {{ ($includePublicSources || $publicResultCount > 0) ? '' : 'hidden' }}>Web Articles <span data-tab-count="web">{{ $publicResultCount }}</span></button>
            <button class="source-tab" type="button" role="tab" aria-selected="false" data-source-tab="videos" {{ ($includeVideos || $videoResultCount > 0) ? '' : 'hidden' }}>Videos &amp; Podcasts <span data-tab-count="videos">{{ $videoResultCount }}</span></button>
        </nav>

        <section class="result-group" data-result-group="guides" {{ $guideCount ? '' : 'hidden' }} aria-labelledby="selected-guides-title">
            <div class="result-group-heading"><div><h3 id="selected-guides-title">Trusted guides</h3><p>Selected MedlinePlus and NHS guides; not live search results.</p></div></div>
            <div class="resource-grid" data-curated-guides>
                @foreach($starterGuides as $guide)
                    <article class="resource-card" data-curated-guide data-guide-url="{{ $guide['url'] }}" data-guide-keywords="{{ implode('|', $guide['keywords']) }}" {{ $guideMatches($guide) ? '' : 'hidden' }}>
                        <div class="resource-card-body">
                            <div class="resource-badge-row"><span class="resource-badge public">{{ $guide['provider'] }}</span><span class="resource-badge">Selected guide</span></div>
                            <h3>{{ $guide['title'] }}</h3>
                            <p class="resource-description">{{ $guide['description'] }}</p>
                            <p class="resource-attribution">External source &middot; Opens in a new tab</p>
                            <div class="resource-actions"><a class="resource-link external" href="{{ $guide['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Read at {{ $guide['provider'] }}</a></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="result-group" data-result-group="library">
            <div class="result-group-heading"><div><h3>MAGIREM AI Library</h3><p>Curated resources available in this platform.</p></div></div>
            <nav class="resource-filter-row" aria-label="Library categories" data-category-filters>
                <a class="resource-filter {{ !$category ? 'active' : '' }}" data-category="" href="{{ route('student.resources', array_filter(['q' => $searchQuery, 'public_sources' => $includePublicSources ? 1 : null, 'videos' => $includeVideos ? 1 : null])) }}">All resources</a>
                @foreach($resourceCategories as $resourceCategory)
                    <a class="resource-filter {{ $category === $resourceCategory ? 'active' : '' }}" data-category="{{ $resourceCategory }}" href="{{ route('student.resources', array_filter(['category' => $resourceCategory, 'q' => $searchQuery, 'public_sources' => $includePublicSources ? 1 : null, 'videos' => $includeVideos ? 1 : null])) }}">{{ $categoryLabel($resourceCategory) }}</a>
                @endforeach
            </nav>
            <div class="resource-grid" data-local-results aria-live="polite">
                @forelse($resources as $resource)
                    @php
                        $catalogVideoId = $youtubeVideoId($resource->url);
                        $catalogImage = $catalogVideoId ? 'https://i.ytimg.com/vi/' . $catalogVideoId . '/hqdefault.jpg' : null;
                    @endphp
                    <article class="resource-card">
                        @if($catalogImage)
                            <div class="resource-media"><img class="resource-thumb" loading="lazy" decoding="async" src="{{ $catalogImage }}" alt="YouTube thumbnail for {{ $resource->title }}" data-image-fallback data-media-kind="library"></div>
                        @else
                            <div class="resource-type-mark" aria-hidden="true"><span>▤</span><small>Library resource</small></div>
                        @endif
                        <div class="resource-card-body">
                            <div class="resource-badge-row"><span class="resource-badge">{{ $categoryLabel($resource->category) }}</span>@if($catalogVideoId)<span class="resource-badge">YouTube link</span>@endif</div>
                            <h3>{{ $resource->title }}</h3>
                            @if($resource->description)<p class="resource-description">{{ $resource->description }}</p>@endif
                            <p class="resource-attribution">MAGIREM AI educational catalog @if($resource->updated_at) &middot; Updated {{ $resource->updated_at->toDateString() }}@endif</p>
                            <div class="resource-actions">
                                <a class="resource-link" href="{{ route('student.resources.show', $resource->id) }}">Read details <span aria-hidden="true">&rarr;</span></a>
                                @if($resource->url && str_starts_with(strtolower((string) $resource->url), 'https://'))<a class="resource-link external" href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer">{{ $catalogVideoId ? 'Watch on YouTube' : 'View source' }}</a>@endif
                            </div>
                        </div>
                    </article>
                @empty
                    @if($hasSearchContext && ($publicResultCount + $videoResultCount + $guideCount) > 0)
                        <p class="library-empty-note" data-library-empty>No library matches this query. Other selected sources have results.</p>
                    @else
                        <div class="wellness-empty" data-local-empty><strong>{{ $hasSearchContext ? 'No library matches.' : 'No local resources are available yet.' }}</strong> {{ $hasSearchContext ? 'Try a related topic, or include optional sources to broaden discovery.' : 'Check back soon for approved student wellness resources.' }}</div>
                    @endif
                @endforelse
            </div>
        </div>

        <section class="result-group" data-result-group="web" data-public-results {{ $includePublicSources ? '' : 'hidden' }} aria-labelledby="public-results-title">
            <div class="result-group-heading"><div><h3 id="public-results-title">Trusted web articles</h3><p>MedlinePlus summaries and eligible Europe PMC records.</p></div><span class="resource-provider-state" data-web-provider-state>{{ $providerStatus['web']['available'] ? 'Provider responded' : ($includePublicSources ? 'Source status unavailable' : 'Opt-in to search') }}</span></div>
            <div class="resource-grid" data-public-grid>
                @foreach($remoteResults as $result)
                    <article class="resource-card article-card">
                        @if(!empty($result['thumbnail']) && !empty($result['thumbnail_is_permitted']))
                            <a class="article-card-main" href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer" data-open-article data-reader-title="{{ $result['title'] }}" data-reader-provider="{{ $result['provider'] }}" data-reader-author="{{ $result['author'] ?? '' }}" data-reader-date="{{ $result['date'] ?? '' }}" data-reader-kind="{{ $result['reader_content_kind'] ?? 'Source preview' }}" data-reader-note="{{ $result['reader_note'] ?? 'Open the official source to review the available content and access terms.' }}" data-reader-url="{{ $result['url'] }}">
                                <div class="resource-media"><img class="resource-thumb" loading="lazy" decoding="async" src="{{ $result['thumbnail'] }}" alt="Image supplied by {{ $result['provider'] }}" data-image-fallback data-media-kind="web"></div>
                                <div class="resource-card-body"><div class="resource-badge-row"><span class="resource-badge public">{{ $result['provider'] }}</span><span class="resource-badge">{{ $result['category'] }}</span></div><h3>{{ $result['title'] }}</h3>
                                    @if($result['summary'])<p class="resource-description">{{ $result['summary'] }}</p>@endif
                                    <p class="resource-attribution">{{ $result['organization'] ?? $result['provider'] }}@if(!empty($result['date'])) &middot; {{ $result['date'] }}@endif</p><span class="article-read-cue">Read preview in MAGIREM AI <span aria-hidden="true">&rarr;</span></span>
                                </div>
                            </a>
                        @else
                            <a class="article-card-main" href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer" data-open-article data-reader-title="{{ $result['title'] }}" data-reader-provider="{{ $result['provider'] }}" data-reader-author="{{ $result['author'] ?? '' }}" data-reader-date="{{ $result['date'] ?? '' }}" data-reader-kind="{{ $result['reader_content_kind'] ?? 'Source preview' }}" data-reader-note="{{ $result['reader_note'] ?? 'Open the official source to review the available content and access terms.' }}" data-reader-url="{{ $result['url'] }}">
                                <div class="resource-card-body"><div class="resource-badge-row"><span class="article-type-icon" aria-hidden="true">↗</span><span class="resource-badge public">{{ $result['provider'] }}</span><span class="resource-badge">{{ $result['category'] }}</span></div><h3>{{ $result['title'] }}</h3>
                                    @if($result['summary'])<p class="resource-description">{{ $result['summary'] }}</p>@endif
                                    <p class="resource-attribution">{{ $result['organization'] ?? $result['provider'] }}@if(!empty($result['date'])) &middot; {{ $result['date'] }}@endif</p><span class="article-read-cue">Read preview in MAGIREM AI <span aria-hidden="true">&rarr;</span></span>
                                </div>
                            </a>
                        @endif
                        <div class="resource-actions"><a class="resource-link external" href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer">{{ $result['provider'] === 'MedlinePlus' ? 'Open full topic at MedlinePlus' : ($result['provider'] === 'Europe PMC' ? 'Open Europe PMC record' : 'Open source') }}</a></div>
                        <template data-reader-content>{{ $result['reader_content'] ?? '' }}</template>
                    </article>
                @endforeach
            </div>
            <div class="wellness-status" data-public-status>
                @if($mappingNote)<p class="wellness-status">{{ $mappingNote }}</p>@endif
                @foreach($providerErrors['web'] as $error)<p class="wellness-status error">{{ $error }}</p>@endforeach
                @if($includePublicSources && $publicResultCount === 0 && empty($providerErrors['web']))<p class="wellness-status">No web articles matched this query.</p>@endif
            </div>
        </section>

        <section class="result-group" data-result-group="videos" data-video-results {{ $includeVideos ? '' : 'hidden' }} aria-labelledby="video-results-title">
            <div class="result-group-heading"><div><h3 id="video-results-title">Videos &amp; podcasts</h3><p>Search results are not endorsements. Select a video to play it here.</p></div><span class="resource-provider-state" data-video-provider-state>{{ $providerStatus['videos']['available'] ? 'YouTube responded' : ($includeVideos ? 'YouTube unavailable' : 'Opt-in to search') }}</span></div>
            <div class="resource-grid" data-video-grid>
                @foreach($videoResults as $video)
                    <article class="resource-card video-card">
                        <a class="video-card-main" href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer" data-open-video data-video-preview data-video-id="{{ $video['video_id'] }}" data-video-title="{{ $video['title'] }}" data-video-channel="{{ $video['channel'] }}" aria-label="Play {{ $video['title'] }} in MAGIREM AI">
                            @if(!empty($video['thumbnail']))<div class="resource-media video-media"><img class="resource-thumb" src="{{ $video['thumbnail'] }}" alt="YouTube thumbnail for {{ $video['title'] }}" loading="lazy" decoding="async" data-image-fallback data-media-kind="videos"><span class="video-duration">{{ $video['duration'] ?? '' }}</span><span class="video-play-affordance" aria-hidden="true"><span>▶</span></span></div>@else<div class="resource-type-mark video-type-mark" aria-hidden="true"><span>▶</span><small>YouTube video</small></div>@endif
                            <div class="resource-card-body"><div class="resource-badge-row"><span class="resource-badge public">YouTube</span><span class="resource-badge">{{ $video['category'] }}</span></div><h3>{{ $video['title'] }}</h3><p class="resource-attribution">{{ $video['channel'] }}@if(!empty($video['published_at'])) &middot; {{ substr($video['published_at'], 0, 10) }}@endif</p></div>
                        </a>
                        <div class="resource-actions"><a class="resource-link external" href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer">Watch on YouTube</a></div>
                    </article>
                @endforeach
            </div>
            <div class="wellness-status" data-video-status>
                @foreach($providerErrors['videos'] as $error)<p class="wellness-status {{ str_contains($error, 'unavailable') ? 'notice' : 'error' }}">{{ $error }}</p>@endforeach
                @if($includeVideos && $videoResultCount === 0 && empty($providerErrors['videos']))<p class="wellness-status">YouTube returned no embeddable videos for this search.</p>@endif
            </div>
        </section>
        <div class="wellness-status" data-related-topics></div>
        <div class="wellness-empty overall-empty" data-overall-empty {{ $initialOverallEmpty ? '' : 'hidden' }}>
            @if($initialOverallEmpty)
                @if($hasProviderError) No complete result set is available. A selected provider could not be reached; check the source status above and try again later.
                @elseif($includePublicSources || $includeVideos) No matches were returned by the selected sources. Try another phrase or topic.
                @else No library matches. You can optionally search trusted web articles or videos to broaden discovery.
                @endif
            @endif
        </div>
        </div>
    </section>

    <dialog class="video-dialog wellness-modal" data-video-dialog aria-labelledby="video-dialog-title">
        <section class="video-dialog-panel wellness-modal-panel">
        <div class="video-dialog-head"><h2 id="video-dialog-title" data-video-dialog-title>Play video</h2><button type="button" class="video-dialog-close" data-close-video aria-label="Close video player"><span aria-hidden="true">×</span></button></div>
        <div class="video-player-frame" data-video-frame></div>
        <p class="video-dialog-attribution" data-video-attribution></p>
        <p class="video-dialog-note" data-video-note>Embedded using YouTube's privacy-enhanced player. Playback begins only after you choose Play here.</p>
        <a class="video-dialog-source" data-video-source target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" hidden>Player unavailable? Open the original source &nearr;</a>
        </section>
    </dialog>

    <dialog class="article-dialog wellness-modal" id="article-reader" data-article-dialog aria-labelledby="article-dialog-title">
        <section class="article-dialog-panel wellness-modal-panel">
        <div class="article-dialog-head"><div><span class="article-dialog-kicker" data-article-kind>Source preview</span><h2 id="article-dialog-title" data-article-title>Article preview</h2></div><button type="button" class="article-dialog-close" data-close-article aria-label="Close article reader"><span aria-hidden="true">×</span></button></div>
        <div class="article-dialog-body">
            <div class="article-dialog-meta" data-article-meta></div>
            <div class="article-dialog-content" data-article-content></div>
            <p class="article-dialog-note" data-article-note></p>
        </div>
        <div class="article-dialog-actions"><a class="article-dialog-source" data-article-source target="_blank" rel="noopener noreferrer" hidden>Read full article at source ↗</a></div>
        </section>
    </dialog>

    <section class="wellness-section" aria-labelledby="explore-title" data-wellness-reveal>
        <div class="wellness-section-head"><div><h2 id="explore-title">Explore a topic</h2></div><span class="wellness-section-action">Choose one to search</span></div>
        <div class="topic-discovery" data-topic-discovery>
            @foreach([
                ['Stress management', 'Stress', 'Learn strategies to manage stress and feel more in control.', 'topic-stress-outdoors-v1.png'],
                ['Better sleep', 'Sleep', 'Explore tips for healthier sleep habits and more restful days.', 'topic-better-sleep-v1.png'],
                ['Anxiety education', 'Anxiety', 'Understand anxiety and find practical ways to cope.', 'topic-anxiety-education-v1.png'],
                ['Journaling & reflection', 'Journaling', 'Discover how journaling can support your mental wellbeing.', 'topic-journaling-reflection-v1.png'],
            ] as [$title, $topic, $description, $image])
                <a class="topic-card" href="{{ route('student.resources', ['q' => $topic]) }}#results-title" data-topic="{{ $topic }}">
                    <span class="topic-card-photo"><img src="{{ asset('images/wellness/' . $image) }}" alt="" loading="lazy" decoding="async"></span>
                    <span class="topic-card-icon" aria-hidden="true"></span>
                    <span class="topic-card-copy"><span class="topic-card-title">{{ $title }}</span><span class="topic-card-description">{{ $description }}</span></span>
                    <span class="topic-card-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>

    <details class="wellness-section support-directory" id="support-directory" data-wellness-reveal>
        <summary class="support-directory-summary"><span><strong>Trusted support directory</strong><small>Organizations and help options</small></span><span class="support-directory-chevron" aria-hidden="true">+</span></summary>
        <div class="support-directory-content" aria-labelledby="support-title">
            <div class="wellness-section-head"><div><h2 id="support-title">Trusted support &amp; organizations</h2></div><a class="wellness-section-action" href="{{ route('student.counseling-requests') }}">Request university counseling <span aria-hidden="true">&rarr;</span></a></div>
            <p class="support-note">Confirm current services directly. For immediate danger, use <a href="{{ route('student.emergency-resources') }}">Emergency Resources</a>.</p>
            <div class="support-grid">
                @foreach($supportOrganizations as $organization)
                    <article class="support-card"><span class="support-mark" aria-hidden="true"></span><div class="support-card-content"><h3>{{ $organization['name'] }}</h3><p class="support-type">{{ $organization['type'] }}</p><p>{{ $organization['description'] }}</p></div><a class="support-arrow" href="{{ $organization['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Visit {{ $organization['name'] }}"><span aria-hidden="true">&rarr;</span></a></article>
                @endforeach
            </div>
        </div>
    </details>

    <p class="wellness-footer-note">Search shares only the terms you submit—not your chats or journal entries.</p>
</div>

@if(request('section') === 'support')<script>const supportDirectory=document.getElementById('support-directory');if(supportDirectory){supportDirectory.open=true;supportDirectory.scrollIntoView({behavior:'smooth',block:'start'});}</script>@endif
<script>
(() => {
    const form = document.querySelector('[data-wellness-search]');
    if (!form || !window.fetch || !window.AbortController) return;

    const input = form.querySelector('[name="q"]');
    const publicSources = form.querySelector('[data-public-sources]');
    const videoSources = form.querySelector('[data-video-sources]');
    const button = form.querySelector('[data-search-button]');
    const clearButton = form.querySelector('[data-clear-search]');
    const localGrid = document.querySelector('[data-local-results]');
    const videoSection = document.querySelector('[data-video-results]');
    const publicGrid = document.querySelector('[data-public-grid]');
    const videoGrid = document.querySelector('[data-video-grid]');
    const publicStatus = document.querySelector('[data-public-status]');
    const videoStatus = document.querySelector('[data-video-status]');
    const status = document.querySelector('[data-search-status]');
    const resultsSection = document.querySelector('[data-resource-results]');
    const loadingPanel = document.querySelector('[data-search-loading]');
    const loadingDetail = document.querySelector('[data-loading-detail]');
    const resultsContent = document.querySelector('[data-search-content]');
    const featuredAudioSection = document.querySelector('[data-featured-audio-section]');
    const related = document.querySelector('[data-related-topics]');
    const resultsHeading = document.querySelector('#results-title');
    const resultsDescription = document.querySelector('[data-results-description]');
    const resultCount = document.querySelector('[data-result-count]');
    const sourceTabs = document.querySelector('[data-source-tabs]');
    const sourceTabButtons = Array.from(document.querySelectorAll('[data-source-tab]'));
    const resultGroups = Array.from(document.querySelectorAll('[data-result-group]'));
    const overallEmpty = document.querySelector('[data-overall-empty]');
    const videoDialog = document.querySelector('[data-video-dialog]');
    const videoDialogTitle = document.querySelector('[data-video-dialog-title]');
    const videoFrame = document.querySelector('[data-video-frame]');
    const videoAttribution = document.querySelector('[data-video-attribution]');
    const videoNote = document.querySelector('[data-video-note]');
    const videoSource = document.querySelector('[data-video-source]');
    const closeVideoButton = document.querySelector('[data-close-video]');
    const articleDialog = document.querySelector('[data-article-dialog]');
    const articleDialogTitle = document.querySelector('[data-article-title]');
    const articleDialogKind = document.querySelector('[data-article-kind]');
    const articleDialogMeta = document.querySelector('[data-article-meta]');
    const articleDialogContent = document.querySelector('[data-article-content]');
    const articleDialogNote = document.querySelector('[data-article-note]');
    const articleDialogSource = document.querySelector('[data-article-source]');
    const closeArticleButton = document.querySelector('[data-close-article]');
    const searchUrl = form.dataset.searchUrl;
    let category = document.querySelector('[data-category-filters] .active')?.dataset.category || '';
    let pendingController = null;
    let debounceTimer = null;
    let requestSequence = 0;
    let slowSearchTimer = null;
    let previousFeaturedAudioHidden = featuredAudioSection?.hidden ?? true;
    let activeSourceTab = 'all';
    let sourceAvailability = {
        library: true,
        guides: @json($guideCount > 0),
        web: @json($includePublicSources || $publicResultCount > 0),
        videos: @json($includeVideos || $videoResultCount > 0),
    };
    let lastVideoTrigger = null;
    let lastArticleTrigger = null;
    const appMain = document.querySelector('.app-main');
    const hoverCapable = window.matchMedia?.('(hover: hover) and (pointer: fine)');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const previewConnection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    let pageScrollLocked = false;
    let savedWindowScroll = 0;
    let savedAppMainScroll = 0;
    let hoverPreviewTimer = null;
    let hoverPreviewRequest = 0;
    let activePreview = null;
    let previewObserver = null;
    let previewPlayer = null;
    let previewPlayerReady = false;
    let previewCurrentId = null;
    let previewApiPromise = null;
    let previewApiFailed = false;
    let previewOverlay = null;

    // Portal the dialogs out of the app grid/overflow wrapper before opening.
    [videoDialog, articleDialog].filter(Boolean).forEach((dialog) => {
        if (dialog.parentElement !== document.body) document.body.append(dialog);
    });

    const allowedHttps = (value) => {
        try { const url = new URL(value, window.location.origin); return url.protocol === 'https:' ? url.href : null; } catch (_) { return null; }
    };
    const allowedInternal = (value) => {
        try { const url = new URL(value, window.location.origin); return url.origin === window.location.origin ? url.href : null; } catch (_) { return null; }
    };
    const element = (name, classes = []) => { const node = document.createElement(name); node.className = classes.join(' '); return node; };
    const text = (name, value, classes = []) => { const node = element(name, classes); node.textContent = value || ''; return node; };
    function lockPageScroll() {
        if (pageScrollLocked) return;
        pageScrollLocked = true;
        savedWindowScroll = window.scrollY;
        savedAppMainScroll = appMain?.scrollTop || 0;
        document.documentElement.classList.add('wellness-modal-open');
        document.body.classList.add('wellness-modal-open');
        appMain?.classList.add('wellness-modal-scroll-lock');
    }
    function unlockPageScroll() {
        if (articleDialog?.open || videoDialog?.open || !pageScrollLocked) return;
        document.documentElement.classList.remove('wellness-modal-open');
        document.body.classList.remove('wellness-modal-open');
        appMain?.classList.remove('wellness-modal-scroll-lock');
        pageScrollLocked = false;
        window.scrollTo(0, savedWindowScroll);
        if (appMain) appMain.scrollTop = savedAppMainScroll;
    }
    function canPreviewVideos() {
        const connectionType = String(previewConnection?.effectiveType || '').toLowerCase();
        const reducedData = window.matchMedia?.('(prefers-reduced-data: reduce)')?.matches;
        return Boolean(hoverCapable?.matches)
            && !reducedMotion?.matches
            && !reducedData
            && !previewConnection?.saveData
            && !['slow-2g', '2g', '3g'].includes(connectionType)
            && document.visibilityState === 'visible'
            && !document.querySelector('.wellness-modal[open]');
    }
    function previewMediaFor(trigger) {
        return trigger?.querySelector('.resource-media');
    }
    function previewMediaIsVisible(media) {
        if (!media || !media.isConnected || !media.getClientRects().length || document.visibilityState !== 'visible') return false;
        if (media.closest('[hidden]')) return false;
        const rect = media.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0
            && rect.top >= 0 && rect.left >= 0
            && rect.bottom <= window.innerHeight && rect.right <= window.innerWidth;
    }
    function ensurePreviewOverlay() {
        if (previewOverlay?.isConnected) return previewOverlay;
        previewOverlay = element('div', ['video-hover-preview']);
        previewOverlay.hidden = true;
        previewOverlay.setAttribute('aria-hidden', 'true');
        previewOverlay.inert = true;
        document.body.append(previewOverlay);
        return previewOverlay;
    }
    function positionPreview() {
        if (!activePreview || !previewMediaIsVisible(activePreview.media)) {
            stopHoverPreview();
            return false;
        }
        const rect = activePreview.media.getBoundingClientRect();
        const overlay = ensurePreviewOverlay();
        overlay.style.left = `${Math.round(rect.left)}px`;
        overlay.style.top = `${Math.round(rect.top)}px`;
        overlay.style.width = `${Math.round(rect.width)}px`;
        overlay.style.height = `${Math.round(rect.height)}px`;
        return true;
    }
    function hidePreviewOverlay() {
        previewOverlay?.classList.remove('is-playing');
        if (previewOverlay) previewOverlay.hidden = true;
    }
    function stopHoverPreview({destroy = false} = {}) {
        clearTimeout(hoverPreviewTimer);
        hoverPreviewTimer = null;
        hoverPreviewRequest += 1;
        activePreview = null;
        previewObserver?.disconnect();
        previewObserver = null;
        hidePreviewOverlay();
        try {
            if (previewPlayer && previewPlayerReady) previewPlayer.pauseVideo();
            if (destroy && previewPlayer) previewPlayer.destroy();
        } catch (_) { /* A blocked or already-disposed player is treated as a thumbnail fallback. */ }
        if (destroy) {
            previewPlayer = null;
            previewPlayerReady = false;
            previewCurrentId = null;
            previewOverlay?.remove();
            previewOverlay = null;
        }
    }
    function loadYouTubeIframeApi() {
        if (window.YT?.Player) return Promise.resolve(window.YT);
        if (previewApiPromise) return previewApiPromise;
        previewApiPromise = new Promise((resolve, reject) => {
            const timeout = window.setTimeout(() => {
                previewApiFailed = true;
                reject(new Error('YouTube preview API timed out.'));
            }, 12000);
            const previousReady = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                try { previousReady?.(); } finally {
                    window.clearTimeout(timeout);
                    if (window.YT?.Player) resolve(window.YT);
                    else { previewApiFailed = true; reject(new Error('YouTube preview API was unavailable.')); }
                }
            };
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.async = true;
            script.onerror = () => {
                window.clearTimeout(timeout);
                previewApiFailed = true;
                reject(new Error('YouTube preview API could not be loaded.'));
            };
            document.head.append(script);
        });
        return previewApiPromise;
    }
    function startPreviewPlayer() {
        const request = hoverPreviewRequest;
        const selected = activePreview;
        if (!selected || !positionPreview() || previewApiFailed) return;
        loadYouTubeIframeApi().then((YT) => {
            if (request !== hoverPreviewRequest || activePreview !== selected || !positionPreview()) return;
            if (previewPlayer) {
                if (!previewPlayerReady) return;
                previewPlayer.mute();
                previewPlayer.setVolume(0);
                if (previewCurrentId !== selected.id) {
                    previewCurrentId = selected.id;
                    previewPlayer.loadVideoById(selected.id);
                } else {
                    previewPlayer.playVideo();
                }
                return;
            }
            const overlay = ensurePreviewOverlay();
            overlay.replaceChildren();
            const iframe = document.createElement('iframe');
            iframe.title = 'Muted YouTube hover preview';
            iframe.loading = 'eager';
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
            iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(selected.id)}?enablejsapi=1&origin=${encodeURIComponent(window.location.origin)}&autoplay=0&controls=0&playsinline=1&rel=0`;
            overlay.append(iframe);
            previewCurrentId = selected.id;
            previewPlayerReady = false;
            previewPlayer = new YT.Player(iframe, {
                events: {
                    onReady: (event) => {
                        previewPlayerReady = true;
                        event.target.mute();
                        event.target.setVolume(0);
                        if (!activePreview || !canPreviewVideos() || !positionPreview()) {
                            event.target.pauseVideo();
                            return;
                        }
                        if (previewCurrentId !== activePreview.id) {
                            previewCurrentId = activePreview.id;
                            event.target.loadVideoById(activePreview.id);
                        } else event.target.playVideo();
                    },
                    onStateChange: (event) => {
                        if (event.data === YT.PlayerState.PLAYING && activePreview && positionPreview()) {
                            overlay.hidden = false;
                            requestAnimationFrame(() => {
                                if (activePreview && previewMediaIsVisible(activePreview.media)) overlay.classList.add('is-playing');
                                else stopHoverPreview();
                            });
                        }
                    },
                    onAutoplayBlocked: () => {
                        if (activePreview) stopHoverPreview();
                    },
                    onError: () => {
                        if (activePreview) stopHoverPreview();
                    },
                },
            });
        }).catch(() => {
            if (activePreview === selected) stopHoverPreview();
        });
    }
    function scheduleHoverPreview(trigger) {
        if (!canPreviewVideos() || !trigger?.dataset.videoId || !validVideoId(trigger.dataset.videoId)) return;
        const media = previewMediaFor(trigger);
        if (!previewMediaIsVisible(media)) return;
        if (activePreview?.trigger === trigger) return;
        stopHoverPreview();
        const request = hoverPreviewRequest;
        const id = trigger.dataset.videoId;
        hoverPreviewTimer = window.setTimeout(() => {
            if (request !== hoverPreviewRequest || !canPreviewVideos() || !previewMediaIsVisible(media)) return;
            activePreview = {trigger, media, id};
            positionPreview();
            if ('IntersectionObserver' in window) {
                previewObserver = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.target === media && (!entry.isIntersecting || entry.intersectionRatio < .45))) stopHoverPreview();
                }, {threshold: [.45]});
                previewObserver.observe(media);
            }
            startPreviewPlayer();
        }, 420);
    }
    function handlePreviewPointerOver(event) {
        if ((event.pointerType || 'mouse') !== 'mouse') return;
        const trigger = event.target.closest?.('[data-video-preview]');
        if (trigger && videoGrid?.contains(trigger)) scheduleHoverPreview(trigger);
    }
    function handlePreviewPointerOut(event) {
        const trigger = event.target.closest?.('[data-video-preview]');
        if (!trigger || (event.pointerType || 'mouse') !== 'mouse' || trigger.contains(event.relatedTarget)) return;
        if (activePreview?.trigger === trigger) stopHoverPreview();
        else if (hoverPreviewTimer) { clearTimeout(hoverPreviewTimer); hoverPreviewTimer = null; hoverPreviewRequest += 1; }
    }
    videoGrid?.addEventListener('pointerover', handlePreviewPointerOver);
    videoGrid?.addEventListener('pointerout', handlePreviewPointerOut);
    window.addEventListener('scroll', () => { if (activePreview) positionPreview(); }, true);
    window.addEventListener('resize', () => { if (activePreview) positionPreview(); }, {passive: true});
    document.addEventListener('visibilitychange', () => { if (document.visibilityState !== 'visible') stopHoverPreview(); });
    const addLink = (container, label, href, classes = []) => {
        const safeHref = classes.includes('external') ? allowedHttps(href) : allowedInternal(href);
        if (!safeHref) return;
        const link = element('a', ['resource-link', ...classes]);
        link.textContent = label; link.href = safeHref;
        if (classes.includes('external')) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
        container.append(link);
    };
    const validVideoId = (value) => typeof value === 'string' && /^[A-Za-z0-9_-]{11}$/.test(value);
    function typeMark(kind) {
        const labels = {library: ['▤', 'Library resource'], web: ['↗', 'Web article'], videos: ['▶', 'YouTube video']};
        const [icon, label] = labels[kind] || labels.library;
        const mark = element('div', ['resource-type-mark', kind === 'videos' ? 'video-type-mark' : '']);
        mark.setAttribute('aria-hidden', 'true'); mark.append(text('span', icon), text('small', label));
        return mark;
    }
    function videoIdFromUrl(value) {
        const safe = allowedHttps(value); if (!safe) return null;
        try {
            const url = new URL(safe); let id = null;
            if (url.hostname === 'youtu.be') id = url.pathname.replace(/^\/+|\/+$/g, '');
            else if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(url.hostname)) {
                id = url.searchParams.get('v');
                if (!id) id = url.pathname.match(/^\/(?:embed|shorts)\/([A-Za-z0-9_-]{11})$/)?.[1] || null;
            }
            return validVideoId(id) ? id : null;
        } catch (_) { return null; }
    }
    function safeThumbnail(value) {
        const safe = allowedHttps(value);
        if (!safe) return null;
        try { return new URL(safe).hostname === 'i.ytimg.com' ? safe : null; } catch (_) { return null; }
    }
    function mediaFor(item, kind) {
        const media = element('div', ['resource-media']);
        const imageUrl = safeThumbnail(item.image);
        if (!imageUrl) return typeMark(kind);
        const image = document.createElement('img');
        image.className = 'resource-thumb'; image.loading = 'lazy'; image.decoding = 'async'; image.alt = kind === 'videos' ? `YouTube thumbnail for ${item.title}` : '';
        image.src = imageUrl;
        image.addEventListener('error', () => media.replaceWith(typeMark(kind)), {once: true});
        media.append(image);
        if (kind === 'videos' && item.duration) media.append(text('span', item.duration, ['video-duration']));
        return media;
    }
    function resourceCard(item, kind) {
        const card = element('article', ['resource-card', kind === 'videos' ? 'video-card' : '']);
        const provider = item.provider || (kind === 'videos' ? 'YouTube' : '');
        const cardBody = () => {
            const body = element('div', ['resource-card-body']);
            const badges = element('div', ['resource-badge-row']);
            if (kind !== 'library') badges.append(text('span', provider, ['resource-badge', 'public']));
            badges.append(text('span', item.category || (kind === 'web' ? 'Article' : 'Library'), ['resource-badge'])); body.append(badges);
            body.append(text('h3', item.title));
            if (item.description && kind !== 'videos') body.append(text('p', item.description, ['resource-description']));
            let attribution = [];
            if (kind === 'library') attribution = ['MAGIREM AI educational catalog', item.updated_at ? `Updated ${item.updated_at}` : ''];
            else if (kind === 'videos') attribution = [item.channel, item.published_at?.slice(0, 10)];
            else attribution = [item.organization, item.author, item.date];
            body.append(text('p', attribution.filter(Boolean).join(' · '), ['resource-attribution']));
            return body;
        };

        if (kind === 'web') {
            card.classList.add('article-card');
            const main = element('a', ['article-card-main']); main.href = allowedHttps(item.source_url) || '#article-reader';
            main.target = '_blank'; main.rel = 'noopener noreferrer'; main.setAttribute('aria-label', `Read preview: ${item.title}`);
            main.dataset.openArticle = ''; main._resourceReaderItem = item;
            const safeImage = safeThumbnail(item.image);
            if (safeImage) main.append(mediaFor(item, 'web'));
            else {
                const iconRow = element('div', ['resource-badge-row']); iconRow.append(text('span', '↗', ['article-type-icon']));
                main.append(iconRow);
            }
            main.append(cardBody());
            const cue = text('span', 'Read preview in MAGIREM AI →', ['article-read-cue']);
            main.append(cue); card.append(main);
            const actions = element('div', ['resource-actions']);
            const sourceLabel = provider === 'MedlinePlus' ? 'Open full topic at MedlinePlus' : (provider === 'Europe PMC' ? 'Open Europe PMC record' : 'Open source');
            addLink(actions, sourceLabel, item.source_url, ['external']); card.append(actions);
            return card;
        }

        if (kind === 'videos') {
            const id = validVideoId(item.video_id) ? item.video_id : videoIdFromUrl(item.source_url);
            const main = element('a', ['video-card-main']); main.href = allowedHttps(item.source_url) || '#video-player';
            main.target = '_blank'; main.rel = 'noopener noreferrer'; main.setAttribute('aria-label', `Play ${item.title} in MAGIREM AI`);
            if (id && item.embeddable === true) {
                main.dataset.openVideo = ''; main.dataset.videoPreview = ''; main.dataset.videoId = id; main.dataset.videoTitle = item.title || 'YouTube video'; main.dataset.videoChannel = item.channel || '';
            }
            main.append(mediaFor(item, 'videos'), cardBody());
            card.append(main);
            const actions = element('div', ['resource-actions']); addLink(actions, 'Watch on YouTube', item.source_url, ['external']); card.append(actions);
            return card;
        }

        card.append(mediaFor(item, kind));
        const body = cardBody();
        const actions = element('div', ['resource-actions']);
        if (kind === 'library') {
            addLink(actions, 'Read details', item.detail_url);
            if (item.source_url) addLink(actions, videoIdFromUrl(item.source_url) ? 'Watch on YouTube' : 'View source', item.source_url, ['external']);
        }
        body.append(actions); card.append(body); return card;
    }
    function emptyState(message, subtle = false) { const empty = element(subtle ? 'p' : 'div', [subtle ? 'library-empty-note' : 'wellness-empty']); empty.textContent = message; return empty; }
    function wireImageFallbacks(root = document) {
        root.querySelectorAll('img[data-image-fallback]').forEach((image) => {
            if (image.dataset.fallbackBound) return;
            image.dataset.fallbackBound = '1';
            const replaceWithTypeMark = () => {
                const media = image.closest('.resource-media');
                if (media) media.replaceWith(typeMark(image.dataset.mediaKind || 'library'));
            };
            image.addEventListener('error', replaceWithTypeMark, {once: true});
            if (image.complete && image.naturalWidth === 0) replaceWithTypeMark();
        });
    }
    function setLoading(isLoading, detail = '') {
        if (isLoading) resultsSection?.removeAttribute('hidden');
        if (isLoading && featuredAudioSection) {
            if (loadingPanel?.hidden) previousFeaturedAudioHidden = featuredAudioSection.hidden;
            featuredAudioSection.hidden = true;
        } else if (featuredAudioSection) featuredAudioSection.hidden = previousFeaturedAudioHidden;
        if (loadingPanel) loadingPanel.hidden = !isLoading;
        if (resultsContent) resultsContent.hidden = isLoading;
        if (loadingDetail) loadingDetail.textContent = detail;
        if (isLoading) resultsSection?.setAttribute('aria-busy', 'true');
        else resultsSection?.removeAttribute('aria-busy');
        button.disabled = isLoading;
        button.setAttribute('aria-label', isLoading ? 'Searching resources' : 'Search resources');
    }
    function setStatus(message = '', type = '') { status.textContent = message; status.className = ['wellness-status', type].filter(Boolean).join(' '); }
    function renderRelated(topics) { related.replaceChildren(); if (!topics?.length || input.value.trim() === '') return; related.append(document.createTextNode('Related topics: ')); topics.slice(0, 4).forEach((topic, index) => { const chip = element('button', ['topic-chip']); chip.type = 'button'; chip.textContent = topic; chip.dataset.topic = topic; related.append(chip); if (index < Math.min(topics.length, 4) - 1) related.append(document.createTextNode(' ')); }); }
    function updateUrl(query, includePublic, includeVideos, push = false) {
        const url = new URL(window.location.href);
        query ? url.searchParams.set('q', query) : url.searchParams.delete('q');
        category ? url.searchParams.set('category', category) : url.searchParams.delete('category');
        includePublic ? url.searchParams.set('public_sources', '1') : url.searchParams.delete('public_sources');
        includeVideos ? url.searchParams.set('videos', '1') : url.searchParams.delete('videos');
        url.searchParams.delete('research');
        (push ? window.history.pushState : window.history.replaceState).call(window.history, {}, '', url);
    }
    function updateCategoryUI() { document.querySelectorAll('[data-category]').forEach((node) => node.classList.toggle('active', (node.dataset.category || '') === category)); }
    function filterCuratedGuides(query, selectedCategory, publicItems) {
        const normalized = String(query || '').trim().toLocaleLowerCase();
        const returnedUrls = new Set((publicItems || []).map((item) => String(item.source_url || '').replace(/\/$/, '')));
        let count = 0;
        document.querySelectorAll('[data-curated-guide]').forEach((card) => {
            const keywords = (card.dataset.guideKeywords || '').split('|');
            const matched = !selectedCategory && !returnedUrls.has((card.dataset.guideUrl || '').replace(/\/$/, '')) && (!normalized || keywords.some((keyword) => normalized.includes(keyword)));
            card.hidden = !matched;
            if (matched) count++;
        });
        return count;
    }
    function applySourceTab() {
        if (sourceTabs) sourceTabs.hidden = !input.value.trim() && !category;
        sourceTabButtons.forEach((tab) => {
            const key = tab.dataset.sourceTab;
            tab.hidden = key === 'guides' ? !sourceAvailability.guides : key === 'web' ? !sourceAvailability.web : key === 'videos' ? !sourceAvailability.videos : false;
            const selected = key === activeSourceTab;
            tab.classList.toggle('active', selected); tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        resultGroups.forEach((group) => {
            const key = group.dataset.resultGroup;
            const available = sourceAvailability[key] === true;
            group.hidden = !available || (activeSourceTab !== 'all' && activeSourceTab !== key);
        });
        const localEmpty = localGrid.querySelector('[data-local-empty], .library-empty-note');
        if (localEmpty) localEmpty.hidden = (input.value.trim() !== '' || Boolean(category)) && activeSourceTab === 'all';
    }
    function render(data, preserveOptIns = false, historyMode = 'replace') {
        localGrid.replaceChildren();
        const localItems = data.local?.items || [];
        const publicItems = data.public || [];
        const videoItems = data.videos || [];
        const libraryCount = Number(data.counts?.library ?? data.local?.pagination?.total ?? 0);
        const webCount = Number(data.counts?.web ?? publicItems.length);
        const videosCount = Number(data.counts?.videos ?? videoItems.length);
        const guideCount = filterCuratedGuides(data.query, data.category, publicItems);
        const total = Number(data.counts?.all ?? (libraryCount + webCount + videosCount)) + guideCount;
        const hasContext = Boolean(data.query || data.category);
        if (resultsSection) resultsSection.hidden = !hasContext;
        previousFeaturedAudioHidden = hasContext;
        sourceAvailability = {library: true, guides: guideCount > 0, web: Boolean(data.public_requested || webCount), videos: Boolean(data.videos_requested || videosCount)};
        activeSourceTab = 'all';
        if (localItems.length) localItems.forEach((item) => localGrid.append(resourceCard(item, 'library')));
        else localGrid.append(emptyState('No library matches this query. Try a related topic or select an optional source.', true));
        publicGrid.replaceChildren(); publicStatus.replaceChildren();
        if (webCount) publicItems.forEach((item) => publicGrid.append(resourceCard(item, 'web')));
        (data.provider_errors?.web || []).forEach((message) => publicStatus.append(text('p', message, ['wellness-status', message.toLowerCase().includes('unavailable') ? 'notice' : 'error'])));
        if (data.mapping_note) publicStatus.append(text('p', data.mapping_note, ['wellness-status']));
        if (data.public_requested && webCount === 0 && !(data.provider_errors?.web || []).length) publicStatus.append(text('p', 'No web articles matched this query.', ['wellness-status']));
        videoGrid.replaceChildren(); videoStatus.replaceChildren();
        if (videosCount) videoItems.forEach((item) => videoGrid.append(resourceCard(item, 'videos')));
        wireImageFallbacks(publicGrid); wireImageFallbacks(videoGrid);
        (data.provider_errors?.videos || []).forEach((message) => videoStatus.append(text('p', message, ['wellness-status', message.toLowerCase().includes('unavailable') ? 'notice' : 'error'])));
        if (data.videos_requested && videosCount === 0 && !(data.provider_errors?.videos || []).length) videoStatus.append(text('p', 'YouTube returned no embeddable videos for this search.', ['wellness-status']));
        const webState = document.querySelector('[data-web-provider-state]');
        const videoState = document.querySelector('[data-video-provider-state]');
        if (webState) webState.textContent = data.providers?.web?.available ? 'Provider responded' : (data.public_requested ? 'No provider response' : 'Opt in to search');
        if (videoState) videoState.textContent = data.providers?.videos?.available ? 'YouTube responded' : (data.videos_requested ? 'YouTube unavailable' : 'Opt in to search');
        document.querySelector('[data-tab-count="all"]').textContent = String(total);
        document.querySelector('[data-tab-count="library"]').textContent = String(libraryCount);
        document.querySelector('[data-tab-count="guides"]').textContent = String(guideCount);
        document.querySelector('[data-tab-count="web"]').textContent = String(webCount);
        document.querySelector('[data-tab-count="videos"]').textContent = String(videosCount);
        if (resultsHeading) resultsHeading.textContent = data.query ? `Results for “${data.query}”` : (data.category ? `Results in ${data.category}` : 'Start exploring');
        if (resultsDescription) resultsDescription.textContent = hasContext ? 'Browse selected guides and results from the sources you chose.' : 'Browse selected public guides and resources available in MAGIREM AI.';
        if (resultCount) { resultCount.textContent = `${total} ${total === 1 ? 'resource' : 'resources'}`; resultCount.hidden = !hasContext; }
        if (clearButton) clearButton.hidden = input.value.trim() === '';
        const hasProviderError = (data.errors || []).length > 0;
        if (overallEmpty) {
            overallEmpty.hidden = !hasContext || total > 0;
            if (hasContext && total === 0) {
                overallEmpty.textContent = hasProviderError
                    ? 'No results are available yet. One or more selected providers could not be reached; check the source status above and try again later.'
                    : (data.public_requested || data.videos_requested)
                        ? 'No matches were returned by the sources searched. Try another phrase or topic.'
                        : 'No library matches. You can optionally search trusted web articles or videos to broaden discovery.';
            }
        }
        renderRelated(data.related_topics);
        updateCategoryUI(); applySourceTab();
        const urlPublic = preserveOptIns ? publicSources.checked : Boolean(data.public_requested);
        const urlVideos = preserveOptIns ? videoSources.checked : Boolean(data.videos_requested);
        if (historyMode === 'push') updateUrl(data.query || '', urlPublic, urlVideos, true);
        else if (historyMode === 'replace') updateUrl(data.query || '', urlPublic, urlVideos);
        setStatus(!input.value.trim() && (publicSources.checked || videoSources.checked)
            ? 'Enter a topic and press Search to use your selected sources.'
            : hasProviderError ? 'Library results are current; an optional source may be unavailable.' : total ? `${total} ${total === 1 ? 'resource' : 'resources'} found across the selected sources.` : 'Search completed.');
    }
    async function search({includePublic = false, includeVideos = false, historyMode = 'replace', preserveOptIns = false} = {}) {
        clearTimeout(debounceTimer); const sequence = ++requestSequence;
        if (pendingController) pendingController.abort(); pendingController = new AbortController();
        const params = new URLSearchParams(); if (input.value.trim()) params.set('q', input.value.trim()); if (category) params.set('category', category); if (includePublic && input.value.trim()) params.set('public_sources', '1'); if (includeVideos && input.value.trim()) params.set('videos', '1');
        const sources = ['MAGIREM AI library'];
        if (includePublic && input.value.trim()) sources.push('trusted web articles');
        if (includeVideos && input.value.trim()) sources.push('videos and podcasts');
        const detail = `Checking ${sources.join(', ')}. Previous results will return if the search fails.`;
        setLoading(true, detail); setStatus(`Searching ${sources.join(', ')}...`, 'loading');
        clearTimeout(slowSearchTimer);
        slowSearchTimer = setTimeout(() => {
            if (sequence === requestSequence) setStatus('Still searching the selected sources. This may take a little longer.', 'loading');
        }, 8000);
        try {
            const response = await fetch(`${searchUrl}?${params.toString()}`, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin', signal: pendingController.signal});
            if (!response.ok) throw new Error(`Search request failed (${response.status}).`);
            const data = await response.json(); if (sequence !== requestSequence) return; render(data, preserveOptIns || !input.value.trim(), historyMode);
        } catch (error) {
            if (sequence !== requestSequence || error.name === 'AbortError') return;
            setStatus('We could not update the resource search right now. Your previous results are still available. Please try again.', 'error');
        } finally {
            if (sequence === requestSequence) { clearTimeout(slowSearchTimer); slowSearchTimer = null; pendingController = null; setLoading(false); }
        }
    }
    function cancelPendingSearch() {
        clearTimeout(debounceTimer);
        ++requestSequence;
        if (pendingController) pendingController.abort();
        pendingController = null;
        clearTimeout(slowSearchTimer); slowSearchTimer = null;
        setLoading(false);
    }
    function localDebouncedSearch() {
        cancelPendingSearch();
        if (publicSources.checked || videoSources.checked) {
            setStatus(input.value.trim()
                ? 'Press Search to search your selected sources. Nothing is sent while you type.'
                : 'Enter a topic and press Search to use your selected sources.');
            return;
        }
        debounceTimer = setTimeout(() => search({includePublic: false, includeVideos: false, preserveOptIns: true}), 400);
    }

    input.addEventListener('input', localDebouncedSearch);
    form.addEventListener('submit', (event) => { event.preventDefault(); search({includePublic: publicSources.checked, includeVideos: videoSources.checked, historyMode: 'push'}); });
    publicSources.addEventListener('change', () => { cancelPendingSearch(); setStatus(publicSources.checked ? 'Trusted web articles will be searched when you submit.' : 'Web articles are off. Press Search to update results.'); });
    videoSources.addEventListener('change', () => { cancelPendingSearch(); setStatus(videoSources.checked ? 'YouTube will be searched when you submit. Only this query is sent.' : 'YouTube is off. Press Search to update results.'); });
    clearButton.addEventListener('click', () => { input.value = ''; category = ''; search({includePublic: false, includeVideos: false, preserveOptIns: true, historyMode: 'push'}); input.focus(); });
    document.addEventListener('click', (event) => {
        const featuredPlayer = event.target.closest('[data-featured-player]');
        if (featuredPlayer && openFeaturedPlayer(featuredPlayer)) { event.preventDefault(); return; }
        const articleLink = event.target.closest('[data-open-article]');
        if (articleLink && articleDialog?.showModal) { event.preventDefault(); openArticle(articleLink); return; }
        const videoLink = event.target.closest('[data-open-video]');
        if (videoLink && videoDialog?.showModal) { event.preventDefault(); openVideo(videoLink); return; }
        const sourceTab = event.target.closest('[data-source-tab]');
        if (sourceTab) { activeSourceTab = sourceTab.dataset.sourceTab || 'all'; applySourceTab(); return; }
        if (event.target.closest('[data-close-video]')) { closeVideo(); return; }
        if (event.target.closest('[data-close-article]')) { closeArticle(); return; }
        const topic = event.target.closest('[data-topic]');
        if (topic) { event.preventDefault(); input.value = topic.dataset.topic; category = ''; search({includePublic: publicSources.checked, includeVideos: videoSources.checked, historyMode: 'push'}); form.scrollIntoView({behavior: 'smooth', block: 'start'}); return; }
        const filter = event.target.closest('[data-category]');
        if (filter) { event.preventDefault(); category = filter.dataset.category || ''; search({includePublic: publicSources.checked, includeVideos: videoSources.checked, historyMode: 'push'}); }
    });
    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        input.value = url.searchParams.get('q') || ''; category = url.searchParams.get('category') || '';
        publicSources.checked = url.searchParams.get('public_sources') === '1'; videoSources.checked = url.searchParams.get('videos') === '1';
        search({includePublic: publicSources.checked, includeVideos: videoSources.checked, historyMode: 'none'});
    });
    function openArticle(trigger) {
        if (!articleDialog?.showModal || !articleDialogTitle || !articleDialogContent) return;
        stopHoverPreview({destroy: true});
        const card = trigger.closest('.article-card');
        const item = trigger._resourceReaderItem || {};
        const templateContent = card?.querySelector('template[data-reader-content]')?.content?.textContent || '';
        const title = item.title || trigger.dataset.readerTitle || 'Article preview';
        const provider = item.provider || trigger.dataset.readerProvider || 'Source';
        const author = item.author || trigger.dataset.readerAuthor || '';
        const date = item.date || trigger.dataset.readerDate || '';
        const kind = item.reader_content_kind || item.readerContentKind || trigger.dataset.readerKind || 'Source preview';
        const note = item.reader_note || item.readerNote || trigger.dataset.readerNote || 'This preview contains only information returned by the source. Open the original for complete content and access terms.';
        const sourceUrl = item.source_url || trigger.dataset.readerUrl || trigger.href;
        const content = item.reader_content || item.readerContent || templateContent;

        lastArticleTrigger = trigger;
        articleDialogTitle.textContent = title;
        articleDialogKind.textContent = kind;
        articleDialogMeta.replaceChildren();
        [provider, author, date].filter(Boolean).forEach((value) => articleDialogMeta.append(text('span', value)));
        articleDialogContent.replaceChildren();
        const paragraphs = String(content || '').trim().split(/\n{2,}/).map((part) => part.trim()).filter(Boolean);
        if (paragraphs.length) paragraphs.forEach((paragraph) => articleDialogContent.append(text('p', paragraph)));
        else articleDialogContent.append(text('p', 'The source did not provide article text for this preview. Use the source link below to read available content.'));
        articleDialogNote.textContent = note;
        const safeSource = allowedHttps(sourceUrl);
        articleDialogSource.hidden = !safeSource;
        if (safeSource) {
            articleDialogSource.href = safeSource;
            articleDialogSource.textContent = provider === 'MedlinePlus'
                ? 'Open full topic at MedlinePlus ↗'
                : provider === 'Europe PMC' ? 'Open Europe PMC record ↗' : 'Open original source ↗';
        }
        lockPageScroll(); articleDialog.showModal(); closeArticleButton?.focus();
    }
    function closeArticle() { if (articleDialog?.open) articleDialog.close(); }
    articleDialog?.addEventListener('close', () => { if (lastArticleTrigger?.isConnected) lastArticleTrigger.focus(); lastArticleTrigger = null; unlockPageScroll(); });
    articleDialog?.addEventListener('click', (event) => { if (event.target === articleDialog) closeArticle(); });
    closeArticleButton?.addEventListener('click', closeArticle);

    function openVideo(button) {
        const id = button.dataset.videoId;
        if (!validVideoId(id) || !videoDialog?.showModal || !videoFrame) return;
        stopHoverPreview({destroy: true});
        lastVideoTrigger = button; videoFrame.replaceChildren(); videoDialogTitle.textContent = button.dataset.videoTitle || 'YouTube video';
        videoFrame.dataset.playerProvider = 'youtube';
        if (videoAttribution) videoAttribution.textContent = button.dataset.videoChannel ? `Channel: ${button.dataset.videoChannel}` : 'Source: YouTube';
        if (videoNote) videoNote.textContent = 'Embedded using YouTube’s privacy-enhanced player. If playback is blocked, open the original source.';
        setPlayerSource(button.href);
        const iframe = document.createElement('iframe');
        iframe.title = `YouTube video: ${videoDialogTitle.textContent}`;
        // This is set only in direct response to the student's Play click.
        iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1`;
        iframe.loading = 'eager'; iframe.referrerPolicy = 'strict-origin-when-cross-origin'; iframe.allow = 'accelerometer; encrypted-media; gyroscope; picture-in-picture; web-share'; iframe.allowFullscreen = true;
        videoFrame.append(iframe); lockPageScroll(); videoDialog.showModal(); closeVideoButton?.focus();
    }
    function setPlayerSource(url) {
        if (!videoSource) return;
        const safeSource = allowedHttps(url);
        videoSource.hidden = !safeSource;
        if (safeSource) videoSource.href = safeSource;
    }
    function openFeaturedPlayer(trigger) {
        if (!videoDialog?.showModal || !videoFrame || !videoDialogTitle) return false;
        const {playerProvider, playerId, playerTitle, playerSource} = trigger.dataset;
        const isSpotify = playerProvider === 'spotify' && /^[A-Za-z0-9]{22}$/.test(playerId || '');
        // Only the specific NHS-published anxiety guide is allowed in this player.
        const isNhs = playerProvider === 'nhs' && playerId === '2163080377001';
        if (!isSpotify && !isNhs) return false;

        stopHoverPreview({destroy: true});
        lastVideoTrigger = trigger;
        videoFrame.replaceChildren();
        videoFrame.dataset.playerProvider = playerProvider;
        videoDialogTitle.textContent = playerTitle || 'Audio guide';
        if (videoAttribution) videoAttribution.textContent = `Source: ${playerSource || (isSpotify ? 'Spotify' : 'NHS')}`;
        if (videoNote) videoNote.textContent = isSpotify
            ? 'Press Play in the Spotify player. Availability and sign-in depend on Spotify; use the original source if playback is unavailable.'
            : 'Press Play in the NHS player, hosted by Brightcove. If playback is unavailable, open the original NHS page.';
        setPlayerSource(trigger.href);
        const iframe = document.createElement('iframe');
        iframe.title = `${isSpotify ? 'Spotify podcast' : 'NHS audio guide'}: ${videoDialogTitle.textContent}`;
        iframe.src = isSpotify
            ? `https://open.spotify.com/embed/episode/${encodeURIComponent(playerId)}`
            : `https://players.brightcove.net/79855382001/EkC1XU82e_default/index.html?videoId=${encodeURIComponent(playerId)}`;
        iframe.loading = 'eager';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allow = isSpotify ? 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture' : 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        videoFrame.append(iframe);
        lockPageScroll(); videoDialog.showModal(); closeVideoButton?.focus();
        return true;
    }
    function closeVideo() { if (videoDialog?.open) videoDialog.close(); }
    videoDialog?.addEventListener('close', () => { videoFrame?.replaceChildren(); if (lastVideoTrigger?.isConnected) lastVideoTrigger.focus(); lastVideoTrigger = null; unlockPageScroll(); });
    videoDialog?.addEventListener('click', (event) => { if (event.target === videoDialog) closeVideo(); });
    closeVideoButton?.addEventListener('click', closeVideo);
    sourceTabs?.addEventListener('keydown', (event) => {
        if (!['ArrowRight', 'ArrowLeft'].includes(event.key)) return;
        const visibleTabs = sourceTabButtons.filter((tab) => !tab.hidden); const current = visibleTabs.indexOf(document.activeElement); if (current < 0) return;
        event.preventDefault(); const next = (current + (event.key === 'ArrowRight' ? 1 : visibleTabs.length - 1)) % visibleTabs.length; visibleTabs[next].focus(); visibleTabs[next].click();
    });
    wireImageFallbacks(); applySourceTab();
    const revealNodes = Array.from(document.querySelectorAll('[data-wellness-reveal]'));
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), {threshold: .08});
        revealNodes.forEach((node) => revealObserver.observe(node));
    } else { revealNodes.forEach((node) => node.classList.add('is-visible')); }
    if (!navigator.onLine) setStatus('You appear to be offline. You can still browse the local catalog; public search is unavailable until you reconnect.', 'notice');
})();
</script>
@endsection
