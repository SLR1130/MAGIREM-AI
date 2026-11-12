@extends('layouts.app')

@section('title', 'Sign In - MAGIREM AI')

@section('content')
<style>
    .msa-auth-page,
    .msa-auth-page * { box-sizing: border-box; }

    .msa-auth-page {
        --forest: #103F32;
        --emerald: #236B55;
        --emerald-2: #2F7C62;
        --mint: #E6F1EB;
        --mint-soft: #F3F8F5;
        --gold: #F4C84E;
        --ivory: #F8FAF7;
        --ink: #263A33;
        --muted: #708078;
        --line: rgba(16, 63, 50, .11);

        position: relative;
        width: 100%;
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 34px;
        overflow: hidden;
        isolation: isolate;
        font-family: 'Inter', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--ink);
        background:
            radial-gradient(circle at 10% 8%, rgba(35,107,85,.08), transparent 28%),
            radial-gradient(circle at 90% 88%, rgba(244,200,78,.10), transparent 24%),
            linear-gradient(180deg, #FBFDFC 0%, #F5F9F6 100%);
    }

    .msa-auth-page::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -2;
        opacity: .38;
        background-image:
            linear-gradient(rgba(16,63,50,.035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(16,63,50,.035) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(to bottom, rgba(0,0,0,.65), transparent 72%);
    }

    .msa-auth-orb {
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
        z-index: -1;
    }

    .msa-auth-orb--a {
        width: 320px;
        height: 320px;
        left: -140px;
        top: 8%;
        background: rgba(35,107,85,.08);
        filter: blur(2px);
    }

    .msa-auth-orb--b {
        width: 240px;
        height: 240px;
        right: -70px;
        bottom: -60px;
        background: rgba(244,200,78,.14);
    }

    .msa-auth-shell {
        width: min(1120px, 100%);
        min-height: 650px;
        display: grid;
        grid-template-columns: 43% 57%;
        border: 1px solid var(--line);
        border-radius: 30px;
        overflow: hidden;
        background: rgba(255,255,255,.94);
        box-shadow: 0 30px 80px rgba(16,63,50,.12), 0 4px 14px rgba(16,63,50,.05);
    }

    .msa-auth-brand {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 38px;
        overflow: hidden;
        color: #fff;
        background:
            radial-gradient(circle at 95% 4%, rgba(244,200,78,.19), transparent 28%),
            linear-gradient(155deg, #103F32 0%, #16513F 52%, #236B55 100%);
    }

    .msa-auth-brand::before,
    .msa-auth-brand::after {
        content: '';
        position: absolute;
        pointer-events: none;
    }

    .msa-auth-brand::before {
        width: 280px;
        height: 280px;
        border: 1px solid rgba(255,255,255,.10);
        border-radius: 40px;
        right: -110px;
        top: 155px;
        transform: rotate(24deg);
    }

    .msa-auth-brand::after {
        left: 0;
        right: 0;
        bottom: 0;
        height: 180px;
        opacity: .18;
        background-image:
            linear-gradient(90deg, transparent 19px, rgba(255,255,255,.19) 20px, transparent 21px),
            linear-gradient(transparent 19px, rgba(255,255,255,.19) 20px, transparent 21px);
        background-size: 42px 42px;
        mask-image: linear-gradient(to top, #000, transparent);
    }

    .msa-brand-lockup { position: relative; z-index: 1; }

    .msa-brand-top {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .msa-brand-mark {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: grid;
        place-items: center;
        background: #fff;
        box-shadow: 0 10px 24px rgba(0,0,0,.13);
    }

    .msa-brand-mark img {
        width: 34px;
        height: 34px;
        object-fit: contain;
    }

    .msa-brand-name {
        margin: 0;
        font-size: 18px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.03em;
    }

    .msa-brand-kicker {
        margin: 4px 0 0;
        color: rgba(255,255,255,.68);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .msa-brand-copy {
        position: relative;
        z-index: 1;
        margin: auto 0;
        max-width: 360px;
        padding: 46px 0;
    }

    .msa-brand-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 20px;
        padding: 7px 10px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 999px;
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.84);
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .03em;
    }

    .msa-brand-pill::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--gold);
        box-shadow: 0 0 0 4px rgba(244,200,78,.12);
    }

    .msa-brand-title {
        margin: 0;
        max-width: 340px;
        font-size: clamp(34px, 3.1vw, 46px);
        line-height: 1.04;
        font-weight: 790;
        letter-spacing: -.045em;
    }

    .msa-brand-title span { color: #F8D96E; }

    .msa-brand-description {
        margin: 18px 0 0;
        max-width: 350px;
        color: rgba(255,255,255,.72);
        font-size: 13px;
        line-height: 1.75;
    }

    .msa-brand-features {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .msa-brand-feature {
        min-height: 72px;
        padding: 13px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 15px;
        background: rgba(255,255,255,.065);
    }

    .msa-brand-feature svg {
        width: 17px;
        height: 17px;
        margin-bottom: 9px;
        color: #F8D96E;
    }

    .msa-brand-feature strong {
        display: block;
        color: #fff;
        font-size: 11px;
        font-weight: 750;
    }

    .msa-brand-feature span {
        display: block;
        margin-top: 3px;
        color: rgba(255,255,255,.58);
        font-size: 9.5px;
        line-height: 1.45;
    }

    .msa-auth-panel {
        display: flex;
        align-items: center;
        padding: 52px 62px;
        background: rgba(255,255,255,.96);
    }

    .msa-auth-form-wrap {
        width: 100%;
        max-width: 430px;
        margin: 0 auto;
    }

    .msa-form-kicker {
        margin-bottom: 11px;
        color: var(--emerald);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .13em;
        text-transform: uppercase;
    }

    .msa-form-title {
        margin: 0;
        color: var(--forest);
        font-size: 31px;
        line-height: 1.08;
        font-weight: 790;
        letter-spacing: -.04em;
    }

    .msa-form-subtitle {
        margin: 10px 0 27px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.65;
    }

    .msa-error {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 18px;
        padding: 12px 13px;
        border: 1px solid rgba(185,28,28,.14);
        border-radius: 13px;
        background: #FFF7F6;
        color: #A43A34;
        font-size: 11px;
        line-height: 1.45;
    }

    .msa-error svg { width: 17px; height: 17px; flex: 0 0 auto; margin-top: 1px; }

    .msa-form { display: flex; flex-direction: column; gap: 16px; }

    .msa-field { display: flex; flex-direction: column; gap: 7px; }

    .msa-label {
        color: var(--ink);
        font-size: 11px;
        font-weight: 750;
    }

    .msa-login-hint {
        margin: -2px 0 0;
        color: #71837A;
        font-size: 10px;
        line-height: 1.45;
    }
    .msa-mobile-hint { display: none; }
    .msa-brand-art { display: none; }

    .msa-input-wrap { position: relative; }

    .msa-input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        width: 17px;
        height: 17px;
        transform: translateY(-50%);
        color: #8A9991;
        pointer-events: none;
    }

    .msa-input {
        width: 100%;
        height: 48px;
        padding: 0 44px;
        border: 1px solid #DCE6E0;
        border-radius: 13px;
        outline: none;
        background: #FBFDFC;
        color: var(--ink);
        font: inherit;
        font-size: 12px;
        font-weight: 600;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
    }

    .msa-input::placeholder { color: #A6B1AB; font-weight: 500; }

    .msa-input:hover { border-color: #C6D7CE; background: #fff; }

    .msa-input:focus {
        border-color: rgba(35,107,85,.62);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(35,107,85,.08);
    }

    .msa-toggle {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #83928A;
        cursor: pointer;
    }

    .msa-toggle:hover { background: var(--mint-soft); color: var(--forest); }
    .msa-toggle svg { width: 17px; height: 17px; }

    .msa-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: -2px;
    }

    .msa-remember {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #65766D;
        font-size: 11px;
        font-weight: 650;
        cursor: pointer;
    }

    .msa-remember input {
        width: 16px;
        height: 16px;
        accent-color: var(--emerald);
    }

    .msa-link {
        color: var(--emerald);
        font-size: 11px;
        font-weight: 750;
        text-decoration: none;
    }

    .msa-link:hover { color: var(--forest); text-decoration: underline; text-underline-offset: 3px; }

    .msa-recovery-note {
        color: #83928A;
        font-size: 10px;
        font-weight: 650;
        text-align: right;
    }

    .msa-submit {
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        margin-top: 3px;
        border: 0;
        border-radius: 13px;
        background: linear-gradient(135deg, var(--forest), var(--emerald));
        color: #fff;
        font: inherit;
        font-size: 12px;
        font-weight: 780;
        cursor: pointer;
        box-shadow: 0 10px 24px rgba(16,63,50,.16);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .msa-submit:hover { transform: translateY(-1px); box-shadow: 0 13px 28px rgba(16,63,50,.21); }
    .msa-submit svg { width: 16px; height: 16px; }

    .msa-security {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px;
        border-radius: 11px;
        background: var(--mint-soft);
        color: #6B7B73;
        font-size: 9.5px;
        font-weight: 650;
    }

    .msa-security svg { width: 14px; height: 14px; color: var(--emerald); }

    .msa-auth-footer {
        margin-top: 23px;
        padding-top: 20px;
        border-top: 1px solid #E6EDE9;
        text-align: center;
        color: #75837C;
        font-size: 11px;
        font-weight: 600;
    }

    .msa-auth-footer a { margin-left: 4px; }

    @media (max-width: 900px) {
        .msa-auth-page { padding: 22px; overflow: auto; }
        .msa-auth-shell { grid-template-columns: 1fr; min-height: auto; max-width: 620px; }
        .msa-auth-brand { padding: 26px 30px; }
        .msa-brand-copy { padding: 34px 0 28px; }
        .msa-brand-features { display: none; }
        .msa-auth-panel { padding: 38px 34px; }
    }

    @media (max-width: 560px) {
        .msa-auth-page { padding: 12px; place-items: start center; }
        .msa-auth-shell { border-radius: 22px; }
        .msa-auth-brand { padding: 20px; }
        .msa-brand-mark { width: 42px; height: 42px; flex-basis: 42px; border-radius: 13px; }
        .msa-brand-mark img { width: 30px; height: 30px; }
        .msa-brand-copy { padding: 26px 0 20px; }
        .msa-brand-title { font-size: 30px; }
        .msa-auth-panel { padding: 30px 20px; }
        .msa-form-title { font-size: 27px; }
        .msa-options { align-items: flex-start; flex-direction: column; gap: 9px; }
        .msa-input { font-size: 16px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .msa-auth-page *, .msa-auth-page *::before, .msa-auth-page *::after { transition: none !important; }
    }

    /* Interactive login enhancements: motion stays subtle and content remains usable. */
    .msa-auth-page {
        background-size: 140% 140%;
        animation: msa-background-drift 18s ease-in-out infinite alternate;
    }

    .msa-auth-orb--a { animation: msa-orb-float-a 12s ease-in-out infinite; }
    .msa-auth-orb--b { animation: msa-orb-float-b 14s ease-in-out infinite; }
    .msa-auth-brand::before { animation: msa-ring-drift 16s ease-in-out infinite alternate; }

    .msa-auth-brand { animation: msa-enter-left .58s cubic-bezier(.22,1,.36,1) both; }
    .msa-auth-panel { animation: msa-enter-right .58s .08s cubic-bezier(.22,1,.36,1) both; }
    .msa-brand-lockup { animation: msa-stagger-in .44s .16s cubic-bezier(.22,1,.36,1) both; }
    .msa-brand-copy { animation: msa-stagger-in .48s .24s cubic-bezier(.22,1,.36,1) both; }
    .msa-brand-features { animation: msa-stagger-in .48s .34s cubic-bezier(.22,1,.36,1) both; }
    .msa-form-kicker,.msa-form-title,.msa-form-subtitle,.msa-field,.msa-options,.msa-submit,.msa-security,.msa-auth-footer { animation: msa-stagger-in .42s .18s cubic-bezier(.22,1,.36,1) both; }
    .msa-field:nth-child(2) { animation-delay: .24s; }
    .msa-options { animation-delay: .29s; }
    .msa-submit { animation-delay: .34s; }
    .msa-security { animation-delay: .39s; }
    .msa-auth-footer { animation-delay: .44s; }

    .msa-brand-feature {
        position: relative;
        overflow: hidden;
        transition: transform .2s ease, border-color .2s ease, background .2s ease, box-shadow .2s ease;
    }

    .msa-brand-feature::after {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0;
        background: linear-gradient(120deg, transparent 20%, rgba(255,255,255,.12), transparent 75%);
        transform: translateX(-100%);
        transition: opacity .2s ease, transform .55s ease;
    }

    .msa-brand-feature:hover,.msa-brand-feature:focus-within {
        transform: translateY(-3px);
        border-color: rgba(248,217,110,.4);
        background: rgba(255,255,255,.10);
        box-shadow: 0 11px 24px rgba(0,0,0,.12);
    }

    .msa-brand-feature:hover::after,.msa-brand-feature:focus-within::after { opacity: 1; transform: translateX(100%); }
    .msa-brand-feature svg { transition: transform .22s ease, color .22s ease; }
    .msa-brand-feature:hover svg,.msa-brand-feature:focus-within svg { color: #fff; transform: translateY(-2px) rotate(-5deg); }

    .msa-input-wrap:focus-within .msa-input-icon { color: var(--emerald); transform: translateY(-50%) scale(1.08); }
    .msa-input-icon { transition: color .18s ease, transform .18s ease; }
    .msa-input-wrap:focus-within::after {
        content: '';
        position: absolute;
        left: 14px;
        right: 14px;
        bottom: -1px;
        height: 2px;
        border-radius: 99px;
        background: linear-gradient(90deg, var(--gold), var(--emerald));
        animation: msa-focus-line .25s ease both;
        pointer-events: none;
    }

    .msa-toggle { transition: background .18s ease, color .18s ease, transform .18s ease; }
    .msa-toggle:hover,.msa-toggle:focus-visible { transform: translateY(-50%) scale(1.04); outline: 2px solid rgba(35,107,85,.18); outline-offset: 2px; }
    .msa-toggle:active { transform: translateY(-50%) scale(.96); }
    .msa-remember input { transition: transform .16s ease, filter .16s ease; }
    .msa-remember:hover input { transform: scale(1.08); filter: brightness(1.05); }
    .msa-remember input:focus-visible { outline: 3px solid rgba(244,200,78,.48); outline-offset: 2px; }

    .msa-submit { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, filter .18s ease; }
    .msa-submit::after {
        content: '';
        position: absolute;
        inset: 0 auto 0 -70%;
        width: 42%;
        background: linear-gradient(100deg, transparent, rgba(255,255,255,.28), transparent);
        transform: skewX(-18deg);
        transition: left .55s ease;
        pointer-events: none;
    }
    .msa-submit:hover::after { left: 125%; }
    .msa-submit:active { transform: translateY(1px) scale(.99); filter: brightness(.97); }
    .msa-submit:focus-visible { outline: 3px solid rgba(244,200,78,.65); outline-offset: 3px; }
    .msa-error { animation: msa-error-in .28s ease both; }

    @keyframes msa-background-drift { from { background-position: 0% 0%; } to { background-position: 100% 100%; } }
    @keyframes msa-orb-float-a { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(18px,10px,0) scale(1.04); } }
    @keyframes msa-orb-float-b { 0%,100% { transform: translate3d(0,0,0); } 50% { transform: translate3d(-14px,-12px,0); } }
    @keyframes msa-ring-drift { from { transform: rotate(24deg) translate3d(0,0,0); } to { transform: rotate(30deg) translate3d(-11px,10px,0); } }
    @keyframes msa-enter-left { from { opacity: 0; transform: translateX(-18px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes msa-enter-right { from { opacity: 0; transform: translateX(18px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes msa-stagger-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes msa-focus-line { from { opacity: 0; transform: scaleX(.5); } to { opacity: 1; transform: scaleX(1); } }
    @keyframes msa-error-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 900px) {
        .msa-brand-features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .msa-brand-feature { min-height: 86px; }
    }

    @media (max-width: 560px) {
        .msa-brand-features { grid-template-columns: 1fr; gap: 8px; }
        .msa-brand-feature { min-height: 0; padding: 11px 12px; }
        .msa-brand-feature svg { width: 15px; height: 15px; margin-bottom: 5px; }
        .msa-brand-feature span { font-size: 9px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .msa-auth-page,.msa-auth-orb--a,.msa-auth-orb--b,.msa-auth-brand::before,.msa-auth-brand,.msa-auth-panel,.msa-brand-lockup,.msa-brand-copy,.msa-brand-features,.msa-form-kicker,.msa-form-title,.msa-form-subtitle,.msa-field,.msa-options,.msa-submit,.msa-security,.msa-auth-footer { animation: none !important; }
        .msa-brand-title,.msa-brand-feature,.msa-brand-feature::after,.msa-brand-feature svg,.msa-submit,.msa-submit::after,.msa-input-icon,.msa-toggle,.msa-remember input { transition: none !important; }
        .msa-brand-title.is-changing { opacity: 1; transform: none; }
    }
    .msa-brand-title[data-hero-quote] {
        /* The longest desktop quote wraps to four lines at the brand-panel width. */
        display: flex;
        align-items: center;
        min-height: 4.16em;
        transition: opacity .42s ease, transform .42s ease;
    }

    .msa-brand-title[data-hero-quote].is-changing {
        opacity: 0;
        transform: translateY(8px);
    }

    @media (max-width: 900px) {
        .msa-brand-title[data-hero-quote] { min-height: 3.12em; }
    }

    @media (max-width: 320px) {
        .msa-brand-title[data-hero-quote] { min-height: 4.16em; }
    }

    @media (prefers-reduced-motion: reduce) {
        .msa-brand-title[data-hero-quote] { transition: none !important; }
        .msa-brand-title[data-hero-quote].is-changing { opacity: 1; transform: none; }
    }

    /* Compact laptop sign-in without constraining errors or form content. */
    @media (min-width: 901px) {
        .msa-auth-shell { width: min(1120px, 100%); min-height: 580px; }
        .msa-auth-brand { padding: 28px; }
        .msa-brand-copy { padding: 24px 0; }
        .msa-brand-pill { margin-bottom: 12px; }
        .msa-brand-title[data-hero-quote] { font-size: clamp(28px, 2.5vw, 34px); }
        .msa-brand-description { margin-top: 12px; line-height: 1.55; }
        .msa-brand-features { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .msa-brand-feature { display: flex; align-items: center; gap: 7px; min-height: 48px; padding: 9px; }
        .msa-brand-feature svg { flex: 0 0 17px; margin: 0; }
        .msa-brand-feature span { display: none; }
        .msa-auth-panel { padding: 30px 42px; }
        .msa-form-subtitle { margin: 8px 0 20px; }
        .msa-form { gap: 13px; }
        .msa-desktop-hint, .msa-security { display: none; }
        .msa-mobile-hint { display: inline; }
        .msa-auth-footer { margin-top: 18px; padding-top: 16px; }
    }

    /* Compact mobile sign-in: keep the brand, bring the form into view sooner. */
    @media (max-width: 560px) {
        .msa-auth-page { padding: 12px; background: #edf4ee; }
        .msa-auth-orb { display: none; }
        .msa-auth-shell { border-radius: 22px; border-color: rgba(16,63,50,.08); box-shadow: 0 20px 45px rgba(16,63,50,.12); }
        .msa-auth-brand { padding: 20px 20px 25px; background: linear-gradient(145deg, #103f32 8%, #1c624a 100%); }
        .msa-auth-brand::before { display: none; }
        .msa-auth-brand::after { height: 80px; opacity: .08; }
        .msa-brand-art { display: block; position: absolute; z-index: 0; right: -4px; bottom: -5px; width: 142px; height: 142px; pointer-events: none; }
        .msa-brand-art .msa-art-leaf { transform-origin: 110px 145px; animation: msa-leaf-sway 5s ease-in-out infinite alternate; }
        .msa-brand-art .msa-art-spark { transform-origin: 122px 46px; animation: msa-spark-breathe 4s ease-in-out infinite alternate; }
        .msa-brand-top { gap: 10px; }
        .msa-brand-mark { box-shadow: 0 5px 18px rgba(0,0,0,.17); }
        .msa-brand-name { font-size: 16px; }
        .msa-brand-kicker { font-size: 9px; }
        .msa-brand-copy { max-width: none; margin: 0; padding: 14px 0 0; }
        .msa-brand-pill, .msa-brand-description, .msa-brand-features { display: none; }
        .msa-form-subtitle, .msa-security, .msa-desktop-hint { display: none; }
        .msa-mobile-hint { display: inline; }
        .msa-brand-title[data-hero-quote] { max-width: calc(100% - 62px); min-height: 4.6em; font-size: 22px; line-height: 1.15; text-wrap: balance; }
        .msa-auth-panel { position: relative; padding: 25px 20px 24px; background: #fffefa; }
        .msa-form-kicker { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .msa-form-kicker::before { content: ''; width: 17px; height: 2px; border-radius: 2px; background: #d0a83f; }
        .msa-form-title { margin-bottom: 20px; font-size: 29px; }
        .msa-form-subtitle { margin: 7px 0 18px; }
        .msa-form { gap: 14px; }
        .msa-field { gap: 8px; }
        .msa-label { font-size: 12px; }
        .msa-login-hint { font-size: 11px; line-height: 1.5; }
        .msa-input { height: 50px; border-color: #d6e4da; background: #f8fbf8; }
        .msa-input:focus { border-color: #398568; box-shadow: 0 0 0 4px rgba(35,107,85,.12); }
        .msa-options { min-height: 44px; align-items: center; flex-direction: row; gap: 10px; margin-top: -4px; }
        .msa-remember, .msa-recovery-note { min-height: 44px; display: inline-flex; align-items: center; font-size: 11px; }
        .msa-remember { padding-right: 8px; }
        .msa-recovery-note { justify-content: flex-end; color: var(--emerald); }
        .msa-recovery-help { margin: -5px 0 0; }
        .msa-toggle { width: 44px; height: 44px; right: 3px; }
        .msa-submit { min-height: 50px; background: linear-gradient(110deg, #103f32, #267456); box-shadow: 0 8px 18px rgba(16,63,50,.18); }
        .msa-auth-footer { margin-top: 20px; padding-top: 18px; }
    }
    @media (max-width: 320px) {
        .msa-brand-title[data-hero-quote] { max-width: calc(100% - 48px); min-height: 5.75em; font-size: 20px; }
        .msa-options { flex-wrap: wrap; }
    }
    @keyframes msa-leaf-sway { from { transform: rotate(-2deg); } to { transform: rotate(3deg); } }
    @keyframes msa-spark-breathe { from { opacity: .6; transform: scale(.93); } to { opacity: 1; transform: scale(1.05); } }
    @media (prefers-reduced-motion: reduce) {
        .msa-brand-art .msa-art-leaf, .msa-brand-art .msa-art-spark { animation: none; }
    }
</style>

<section class="msa-auth-page">
    <div class="msa-auth-orb msa-auth-orb--a" aria-hidden="true"></div>
    <div class="msa-auth-orb msa-auth-orb--b" aria-hidden="true"></div>

    <div class="msa-auth-shell">
        <aside class="msa-auth-brand">
            <svg class="msa-brand-art" viewBox="0 0 150 150" fill="none" aria-hidden="true" focusable="false">
                <circle cx="112" cy="54" r="33" fill="#f4c84e" fill-opacity=".13"/>
                <circle cx="112" cy="54" r="22" fill="#f4c84e" fill-opacity=".18"/>
                <g class="msa-art-spark" stroke="#f8d96e" stroke-width="1.5" stroke-linecap="round"><path d="M122 28v7m-3.5-3.5h7M76 39v5m-2.5-2.5h5"/></g>
                <g class="msa-art-leaf" stroke="#c9e6c7" stroke-linecap="round" stroke-linejoin="round"><path d="M110 148c-1-30 2-49 13-71M112 126c-12-4-21-12-25-25 15 1 23 9 25 25ZM116 113c3-13 11-20 24-22-2 12-9 19-24 22ZM120 91c-3-10-2-19 5-27 7 10 5 18-5 27Z" fill="#a9d2ac" fill-opacity=".30" stroke-opacity=".7" stroke-width="1.5"/><path d="M109 147c-12-18-23-24-40-28m26 17c-11 1-20-3-26-13 12-3 21 2 26 13Z" fill="#a9d2ac" fill-opacity=".22" stroke-opacity=".55" stroke-width="1.5"/></g>
            </svg>
            <div class="msa-brand-lockup">
                <div class="msa-brand-top">
                    <div class="msa-brand-mark"><x-brand-logo variant="emblem" alt="MAGIREM AI emblem" /></div>
                    <div>
                        <p class="msa-brand-name">MAGIREM AI</p>
                        <p class="msa-brand-kicker">A Guiding Light for Every Emotion</p>
                    </div>
                </div>
            </div>

            <div class="msa-brand-copy">
                <div class="msa-brand-pill">Mindoro State University</div>
                <h2 class="msa-brand-title" data-hero-quote aria-live="polite">A calmer space for your well-being.</h2>
                <p class="msa-brand-description">Reflect privately, understand your emotional patterns, and reach the right support from one student-centered wellness platform.</p>
            </div>

            <div class="msa-brand-features" aria-label="MAGIREM AI support principles">
                <div class="msa-brand-feature" tabindex="0">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.5-4.2A11.9 11.9 0 0112 3a11.9 11.9 0 01-8.5 2.8A12 12 0 003 9c0 5.6 3.8 10.3 9 11.6 5.2-1.3 9-6 9-11.6 0-1.1-.2-2.2-.5-3.2z"/></svg>
                    <strong>Private by design</strong>
                    <span>Your reflections stay personal and protected.</span>
                </div>
                <div class="msa-brand-feature" tabindex="0">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.4-4 8-9 8a9.8 9.8 0 01-4.3-.9L3 20l1.4-3.7A7.7 7.7 0 013 12c0-4.4 3.6-8 9-8s9 3.6 9 8z"/></svg>
                    <strong>Support when needed</strong>
                    <span>AI guidance and counselor access in one place.</span>
                </div>
                <div class="msa-brand-feature" tabindex="0">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M12 3.2a8.8 8.8 0 100 17.6 8.8 8.8 0 000-17.6zm0 2.1a6.7 6.7 0 110 13.4 6.7 6.7 0 010-13.4z"/><path fill="currentColor" stroke="none" d="M12 8.1a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4zm-3.1 4.2a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4zm6.2 0a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"/></svg>
                    <strong>Built for students</strong>
                    <span>Small tools for real campus-life moments.</span>
                </div>
            </div>
        </aside>

        <div class="msa-auth-panel">
            <div class="msa-auth-form-wrap">
                <div class="msa-form-kicker">Secure account access</div>
                <h2 class="msa-form-title">Welcome back</h2>
                <p class="msa-form-subtitle">Sign in to continue to your MAGIREM AI wellness space.</p>

                @if ($errors->any())
                    <div class="msa-error" role="alert">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.5m0 3.5h.01M10.3 4.5L2.8 17.3A2 2 0 004.5 20h15a2 2 0 001.7-2.7L13.7 4.5a2 2 0 00-3.4 0z"/></svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="msa-form">
                    @csrf

                    <div class="msa-field">
                        <label class="msa-label" for="email">Sign-in email</label>
                        <div class="msa-input-wrap">
                            <svg class="msa-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16v12H4V6zm0 1l8 6 8-6"/></svg>
                            <input class="msa-input" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="name@minsu.edu.ph" required autocomplete="email">
                        </div>
                        <p class="msa-login-hint"><span class="msa-desktop-hint"><strong>Students and counselors:</strong> sign in with your @minsu.edu.ph institutional email and the password you created during setup. Your personal Gmail is only for verification and password recovery—it is not your username. <strong>Administrators</strong> continue using their existing account credentials.</span><span class="msa-mobile-hint">Students and counselors: use your MinSU email. Admins: use your existing email.</span></p>
                    </div>

                    <div class="msa-field">
                        <label class="msa-label" for="password">Password</label>
                        <div class="msa-input-wrap">
                            <svg class="msa-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10V7a5 5 0 0110 0v3m-11 0h12a2 2 0 012 2v7H4v-7a2 2 0 012-2z"/></svg>
                            <input class="msa-input" style="padding-right:48px" type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                            <button class="msa-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12zm9.5 2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="msa-options">
                        <label class="msa-remember" for="remember">
                            <input type="checkbox" id="remember" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a class="msa-link msa-recovery-note" href="{{ route('password.request') }}">Forgot password?</a>
                    </div>
                    <p class="msa-login-hint msa-recovery-help"><span class="msa-desktop-hint">Password recovery is sent only to the verified personal Gmail linked to your account. If you have not completed the one-time setup invitation, contact the authorized Guidance reviewer.</span><span class="msa-mobile-hint">Reset links go to your verified Gmail. Need setup? Contact Guidance.</span></p>

                    <button type="submit" class="msa-submit">
                        <span>Sign in to MAGIREM AI</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-5-5l5 5-5 5"/></svg>
                    </button>

                    <div class="msa-security">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10V7a5 5 0 0110 0v3m-11 0h12a2 2 0 012 2v7H4v-7a2 2 0 012-2z"/></svg>
                        <span>Your sign-in information is transmitted securely.</span>
                    </div>
                </form>

                <div class="msa-auth-footer">
                    New to MAGIREM AI?
                    <a href="{{ route('register') }}" class="msa-link">Create an account</a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    (() => {
        const hero = document.querySelector('[data-hero-quote]');
        if (!hero) return;

        const quotes = [
            'A calmer space for your well-being.',
            'Your mind matters. So do you.',
            'You are allowed to pause.',
            'Small steps are still progress.',
            'Make space for your peace.',
            'Support begins with honesty.',
            'You do not have to carry everything alone.',
            'Healing takes time, and that is okay.'
        ];
        const storageKey = 'minsuanscore.login.hero-quote.index';
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        let current = 0;
        let transitionId = 0;

        try {
            const previous = Number(sessionStorage.getItem(storageKey));
            current = Number.isInteger(previous) && previous >= 0 ? (previous + 1) % quotes.length : Math.floor(Math.random() * quotes.length);
            sessionStorage.setItem(storageKey, String(current));
        } catch (error) {
            current = Math.floor(Math.random() * quotes.length);
        }

        const showQuote = (nextIndex, animate = true) => {
            current = (nextIndex + quotes.length) % quotes.length;
            const token = ++transitionId;
            if (!animate || reducedMotion) {
                hero.textContent = quotes[current];
                return;
            }
            hero.classList.add('is-changing');
            window.setTimeout(() => {
                if (token !== transitionId) return;
                hero.textContent = quotes[current];
                window.requestAnimationFrame(() => hero.classList.remove('is-changing'));
            }, 220);
            try { sessionStorage.setItem(storageKey, String(current)); } catch (error) { /* storage is optional */ }
        };

        showQuote(current, false);
        if (!reducedMotion) {
            window.setInterval(() => {
                if (!document.hidden) showQuote(current + 1);
            }, 5000);
        }
    })();
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;

        const input = document.getElementById(button.getAttribute('data-password-toggle'));
        if (!input) return;

        input.type = input.type === 'password' ? 'text' : 'password';
        button.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
        button.setAttribute('aria-pressed', String(input.type === 'text'));
    });
</script>
@endsection
