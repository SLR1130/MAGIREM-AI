@extends('layouts.app')

@section('title', 'Registration · MAGIREM AI')

@section('content')
@php
    $wizardInitialStep = max(1, min(5, (int) old('_wizard_step', 1)));
    if ($errors->any()) {
        $errorSteps = [
            'name' => 1, 'institutional_email' => 1, 'password' => 1, 'password_confirmation' => 1, 'role' => 1, 'requested_role' => 1,
            'campus' => 2, 'student_id' => 2, 'program' => 2, 'year_level' => 2,
            'notification_email' => 3,
            'id_photo' => 4, 'alternative_review' => 4,
            'privacy_acknowledgement' => 5,
        ];
        foreach ($errors->keys() as $errorKey) {
            if (isset($errorSteps[$errorKey])) {
                $wizardInitialStep = $errorSteps[$errorKey];
                break;
            }
        }
    }
@endphp
<style>
.mreg-brand-logo{width:clamp(7.5rem,11vw,9rem)}@media(max-width:700px){.mreg-brand-logo{width:clamp(6.5rem,24vw,7.5rem)}}
.mreg,.mreg *{box-sizing:border-box}.mreg{--forest:#103f32;--emerald:#236b55;--mint:#e6f1eb;--paper:#f8faf7;--ink:#263a33;--muted:#71837a;--line:rgba(16,63,50,.12);min-height:100vh;padding:34px 22px 48px;color:var(--ink);font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 92% 6%,rgba(230,241,235,.8),transparent 24rem),var(--paper)}
.mreg-shell{width:min(1060px,100%);margin:auto}.mreg-top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:22px}.mreg-top a{color:var(--forest);font-size:13px;font-weight:700;text-decoration:none}.mreg-top a:hover{text-decoration:underline}.mreg-panel{overflow:hidden;border:1px solid var(--line);border-radius:24px;background:white;box-shadow:0 20px 60px rgba(16,63,50,.08)}.mreg-header{padding:28px 34px 24px;background:linear-gradient(112deg,#fff 12%,#f3f8f5 100%);border-bottom:1px solid var(--line)}.mreg-kicker{display:flex;align-items:center;gap:9px;color:var(--emerald);font-size:10px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.mreg-kicker:before{content:"";width:20px;height:2px;background:#f4c84e;border-radius:9px}.mreg-header h1{margin:12px 0 7px;color:var(--forest);font-size:clamp(29px,4vw,40px);line-height:1.08;letter-spacing:-.045em}.mreg-header p{max-width:700px;margin:0;color:var(--muted);font-size:13px;line-height:1.65}.mreg-body{padding:26px 34px 32px}.mreg-flash,.mreg-errors{margin:0 0 16px;padding:13px 15px;border-radius:12px;font-size:13px;line-height:1.55}.mreg-flash{border:1px solid #c9e6d5;background:#f0faf4;color:#216e55}.mreg-flash.warning,.mreg-flash.error{border-color:#f1d39d;background:#fff9e9;color:#73520c}.mreg-errors{border:1px solid #efc8c3;background:#fff5f3;color:#a8433d}.mreg-errors ul{margin:6px 0 0;padding-left:20px}.mreg-sso{display:flex;align-items:flex-start;gap:15px;padding:20px;border:1px solid var(--line);border-radius:17px;background:#fff}.mreg-sso-icon{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:14px;background:var(--mint);color:var(--emerald)}.mreg-sso-icon svg{width:22px;height:22px}.mreg-sso h2{margin:0;color:var(--forest);font-size:16px}.mreg-sso p{margin:6px 0 0;color:var(--muted);font-size:12px;line-height:1.6}.mreg-button{min-height:44px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 17px;border:1px solid transparent;border-radius:11px;font:inherit;font-size:12px;font-weight:780;cursor:pointer;text-decoration:none;transition:transform .16s ease,background .16s ease,box-shadow .16s ease}.mreg-button:focus-visible,.mreg input:focus-visible,.mreg select:focus-visible,.mreg textarea:focus-visible,.mreg summary:focus-visible,.mreg dialog button:focus-visible{outline:3px solid rgba(35,107,85,.24);outline-offset:2px}.mreg-button:hover:not(:disabled){transform:translateY(-1px)}.mreg-button:disabled{opacity:.55;cursor:not-allowed}.mreg-button--primary{background:var(--forest);color:white;box-shadow:0 7px 16px rgba(16,63,50,.14)}.mreg-button--primary:hover:not(:disabled){background:#174f3f}.mreg-button--secondary{border-color:var(--line);background:white;color:var(--forest)}.mreg-button--secondary:hover{background:var(--paper)}.mreg-sso .mreg-button{margin-top:14px}.mreg-disabled-note{margin-top:14px;padding:12px 14px;border-left:3px solid #f4c84e;border-radius:0 10px 10px 0;background:#fff9e9;color:#705917;font-size:12px;line-height:1.55}.mreg-id-verified{margin-bottom:18px;padding:13px 15px;border:1px solid #c7e4d2;border-radius:12px;background:#f2faf5;color:#215d48;font-size:12px;line-height:1.5}.mreg-id-verified strong{display:block;margin-bottom:3px}.mreg-section{margin-top:18px;border:1px solid var(--line);border-radius:16px;background:white;overflow:hidden}.mreg-section-head{display:flex;align-items:center;gap:11px;padding:15px 18px;border-bottom:1px solid rgba(16,63,50,.08)}.mreg-section-icon{width:32px;height:32px;display:grid;place-items:center;flex:0 0 32px;border-radius:10px;background:var(--mint);color:var(--emerald)}.mreg-section-icon svg{width:17px;height:17px}.mreg-section-head h2{margin:0;color:var(--forest);font-size:15px}.mreg-section-head p{margin:3px 0 0;color:var(--muted);font-size:11px;line-height:1.45}.mreg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 16px;padding:18px}.mreg-field{min-width:0;display:flex;flex-direction:column;gap:6px}.mreg-field--wide{grid-column:1/-1}.mreg-field label,.mreg-label{color:var(--ink);font-size:12px;font-weight:740}.mreg-required{color:#b27b19}.mreg-field input,.mreg-field select{width:100%;height:44px;padding:0 12px;border:1px solid rgba(16,63,50,.17);border-radius:10px;background:#fbfcfb;color:var(--ink);font:inherit;font-size:13px}.mreg-field input:focus,.mreg-field select:focus{border-color:var(--emerald);background:#fff;box-shadow:0 0 0 3px rgba(35,107,85,.1);outline:none}.mreg-field input[aria-invalid=true],.mreg-field select[aria-invalid=true]{border-color:#b95e57}.mreg-help{margin:0;color:var(--muted);font-size:10px;line-height:1.5}.mreg-error{margin:0;color:#a8433d;font-size:11px}.mreg-role-fieldset{min-width:0;margin:0;padding:0;border:0}.mreg-role-fieldset:disabled{opacity:.55}.mreg-photo-tools{display:flex;flex-wrap:wrap;align-items:center;gap:9px}.mreg-file{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.mreg-file-label{min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--forest);font-size:11px;font-weight:750;cursor:pointer}.mreg-file-label:hover{background:var(--paper)}.mreg-file-label:focus-within{outline:3px solid rgba(35,107,85,.2);outline-offset:2px}.mreg-photo-preview{display:flex;align-items:flex-start;gap:14px;margin-top:13px}.mreg-photo-preview[hidden]{display:none}.mreg-photo-preview img{width:min(330px,70%);max-height:220px;object-fit:contain;border:1px solid var(--line);border-radius:12px;background:#f7faf8}.mreg-photo-actions{display:flex;flex-direction:column;gap:8px}.mreg-small-button{min-height:34px;padding:0 10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--forest);font:inherit;font-size:10px;font-weight:720;cursor:pointer}.mreg-small-button:hover{background:var(--mint)}.mreg-check{display:flex;align-items:flex-start;gap:9px;color:var(--ink);font-size:11px;line-height:1.5}.mreg-check input{width:17px;height:17px;flex:0 0 17px;margin:1px 0 0;accent-color:var(--emerald)}.mreg-privacy{padding:15px 17px;border:1px solid rgba(35,107,85,.13);border-radius:13px;background:#f5f9f6;color:#5d7067;font-size:11px;line-height:1.65}.mreg-privacy strong{color:var(--forest)}.mreg-submit-row{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:18px}.mreg-status-line{min-height:20px;color:var(--muted);font-size:11px;line-height:1.5}.mreg-status-line.error{color:#a8433d}.mreg-dialog{width:min(680px,calc(100% - 24px));max-height:calc(100% - 24px);padding:0;border:1px solid var(--line);border-radius:17px;background:#fff;color:var(--ink);box-shadow:0 24px 70px rgba(0,0,0,.24)}.mreg-dialog::backdrop{background:rgba(13,31,25,.58);backdrop-filter:blur(3px)}.mreg-camera-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--line)}.mreg-camera-head h2{margin:0;color:var(--forest);font-size:15px}.mreg-camera-content{padding:15px}.mreg-camera-content video{display:block;width:100%;max-height:56vh;aspect-ratio:4/3;object-fit:contain;border-radius:11px;background:#10201a}.mreg-camera-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;margin-top:12px}.mreg-camera-message{min-height:22px;margin:8px 0 0;color:var(--muted);font-size:11px;line-height:1.5}.mreg-camera-message.error{color:#a8433d}.mreg-footer-note{margin-top:15px;color:var(--muted);font-size:10px;line-height:1.55}.mreg footer{margin-top:16px;text-align:center;color:var(--muted);font-size:11px}.mreg footer a{color:var(--emerald);font-weight:750;text-decoration:none}.mreg footer a:hover{text-decoration:underline}
@media(max-width:700px){.mreg{padding:16px 11px 28px}.mreg-top{margin-bottom:12px}.mreg-panel{border-radius:17px}.mreg-header{padding:21px 18px}.mreg-body{padding:17px 14px 21px}.mreg-grid{grid-template-columns:1fr;gap:13px;padding:14px}.mreg-field--wide{grid-column:auto}.mreg-sso{padding:15px}.mreg-submit-row{align-items:stretch;flex-direction:column-reverse}.mreg-submit-row .mreg-button{width:100%}.mreg-photo-preview{flex-direction:column}.mreg-photo-preview img{width:100%;max-height:190px}}
@media(prefers-reduced-motion:reduce){.mreg *,.mreg *:before,.mreg *:after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
/* Registration-page visual refinement; all selectors stay inside .mreg. */
.mreg{padding:clamp(20px,4vh,42px) 20px 54px;background:radial-gradient(ellipse at 92% 7%,rgba(214,234,222,.68),transparent 30rem),radial-gradient(ellipse at 3% 92%,rgba(244,200,78,.08),transparent 26rem),#f8faf7}
.mreg-shell{width:min(1080px,100%)}
.mreg-brand-logo{width:clamp(7.5rem,11vw,9rem);height:auto;object-fit:contain;box-shadow:none}
.mreg-top{min-height:74px;margin:0 4px 22px}
.mreg-panel{border-color:rgba(16,63,50,.1);border-radius:26px;box-shadow:0 22px 60px rgba(16,63,50,.075)}
.mreg-header{padding:clamp(24px,4vw,40px) clamp(22px,4vw,40px) 26px;background:linear-gradient(115deg,#fff 8%,#f2f8f4 100%)}
.mreg-header h1{max-width:760px;font-size:clamp(30px,4vw,42px);line-height:1.08}
.mreg-header p{max-width:760px;font-size:14px;line-height:1.7}
.mreg-body{padding:clamp(18px,3vw,34px)}
.mreg-section{margin-top:16px;border-radius:17px;box-shadow:0 5px 16px rgba(16,63,50,.025)}
.mreg-section-head{padding:16px 20px;background:linear-gradient(100deg,#fff,#fbfdfb)}
.mreg-section-head h2{font-size:15px;letter-spacing:-.01em}
.mreg-section-head p{max-width:760px;font-size:11px;line-height:1.55}
.mreg-grid{gap:17px 20px;padding:20px}
.mreg-field label,.mreg-label{font-size:12px;line-height:1.4}
.mreg-field input,.mreg-field select{height:46px;border-color:rgba(16,63,50,.16);border-radius:11px;background:#fcfdfc;transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
.mreg-field input:hover,.mreg-field select:hover{border-color:rgba(35,107,85,.38)}
.mreg-field input:focus,.mreg-field select:focus{border-color:#236b55;background:#fff;box-shadow:0 0 0 4px rgba(35,107,85,.11)}
.mreg-help{font-size:10px;line-height:1.55}
.mreg-privacy{border-radius:14px;background:linear-gradient(120deg,#f4f9f5,#fff)}
.mreg-submit-row{align-items:flex-end;padding:15px 17px;border:1px solid rgba(16,63,50,.09);border-radius:14px;background:#fbfdfb}
.mreg-status-line{max-width:680px;line-height:1.55}
.mreg-button{min-height:46px;border-radius:11px}
.mreg-closed-card{display:grid;grid-template-columns:52px minmax(0,1fr);gap:18px;padding:clamp(20px,3vw,30px);border:1px solid rgba(16,63,50,.11);border-radius:19px;background:linear-gradient(125deg,#fff 0%,#f4f9f5 100%);box-shadow:0 8px 25px rgba(16,63,50,.035)}
.mreg-closed-icon{width:50px;height:50px;display:grid;place-items:center;border:1px solid rgba(35,107,85,.1);border-radius:16px;background:#e8f2ec;color:#236b55}
.mreg-closed-icon svg{width:23px;height:23px}
.mreg-closed-copy{min-width:0}
.mreg-state-pill{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid rgba(178,123,25,.18);border-radius:999px;background:#fff9e9;color:#785916;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.mreg-state-pill span{width:7px;height:7px;border-radius:50%;background:#d6a733}
.mreg-closed-copy h2{margin:12px 0 6px;color:#103f32;font-size:clamp(21px,2.7vw,27px);line-height:1.2;letter-spacing:-.025em}
.mreg-closed-copy>p{margin:0;color:#71837a;font-size:13px;line-height:1.65}
.mreg-disabled-note{margin-top:17px;padding:15px 17px;border:1px solid rgba(244,200,78,.28);border-left:3px solid #f4c84e;border-radius:0 12px 12px 0;background:#fffaf0;color:#65572f;font-size:11px;line-height:1.6}
.mreg-disabled-note>strong{display:block;margin-bottom:8px;color:#4e421d;font-size:11px}
.mreg-disabled-note ol{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0;padding:0;list-style:none;counter-reset:registration-step}
.mreg-disabled-note li{counter-increment:registration-step;min-width:0;padding:10px;border:1px solid rgba(16,63,50,.08);border-radius:10px;background:rgba(255,255,255,.78);color:#53675e}
.mreg-disabled-note li:before{content:counter(registration-step);display:grid;width:21px;height:21px;place-items:center;margin-bottom:7px;border-radius:7px;background:#e6f1eb;color:#236b55;font-size:10px;font-weight:800}
.mreg-disabled-note>span{display:block;margin-top:10px;color:#71837a;font-size:10px}
.mreg-closed-signin{margin-top:16px}
.mreg-local-preview{width:min(1080px,100%);margin:0 auto 14px;padding:11px 15px;border:1px solid rgba(178,123,25,.26);border-radius:12px;background:#fff9e9;color:#6a551d;font-size:11px;line-height:1.55}
.mreg-local-preview strong{display:block;margin-bottom:2px;color:#4d3b10;font-size:10px;letter-spacing:.08em;text-transform:uppercase}
.mreg footer{margin-top:19px;font-size:11px}
@media(max-width:700px){
    .mreg{padding:15px 11px 28px}
    .mreg-brand-logo{width:clamp(6.5rem,24vw,7.5rem)}
    .mreg-top{min-height:60px;margin:0 2px 14px}
    .mreg-top a{max-width:150px;text-align:right;font-size:12px;line-height:1.45}
    .mreg-panel{border-radius:19px}
    .mreg-header{padding:22px 18px 19px}
    .mreg-header h1{font-size:clamp(27px,8vw,34px)}
    .mreg-header p{font-size:12px;line-height:1.65}
    .mreg-body{padding:13px 12px 17px}
    .mreg-section{margin-top:12px;border-radius:14px}
    .mreg-section-head{padding:13px 14px}
    .mreg-grid{gap:13px;padding:14px}
    .mreg-field input,.mreg-field select{height:45px}
    .mreg-disabled-note ol{grid-template-columns:1fr;gap:7px}
    .mreg-disabled-note li{display:flex;align-items:center;gap:9px;padding:8px 10px}
    .mreg-disabled-note li:before{flex:0 0 21px;margin:0}
    .mreg-closed-card{grid-template-columns:38px minmax(0,1fr);gap:12px;padding:16px 13px;border-radius:15px}
    .mreg-closed-icon{width:38px;height:38px;border-radius:12px}
    .mreg-closed-icon svg{width:19px;height:19px}
    .mreg-closed-copy h2{font-size:21px}
    .mreg-closed-copy>p{font-size:12px}
    .mreg-disabled-note{padding:12px}
    .mreg-closed-signin{width:100%;justify-content:space-between}
    .mreg-submit-row{align-items:stretch;gap:10px;padding:12px}
}
@media(prefers-reduced-motion:reduce){.mreg *,.mreg *::before,.mreg *::after{transition:none!important;scroll-behavior:auto!important}}

/* Split auth treatment aligned with the login screen. */
.mreg{display:block;padding:28px clamp(14px,3vw,34px) 42px;background:radial-gradient(circle at 10% 8%,rgba(35,107,85,.08),transparent 28%),radial-gradient(circle at 90% 88%,rgba(244,200,78,.1),transparent 24%),linear-gradient(180deg,#fbfdfc 0%,#f5f9f6 100%);overflow:visible}
.mreg-shell{width:min(1180px,100%);margin:0 auto;display:grid;grid-template-columns:minmax(300px,41%) minmax(0,59%);align-items:stretch;overflow:hidden;border:1px solid rgba(16,63,50,.11);border-radius:30px;background:rgba(255,255,255,.96);box-shadow:0 30px 80px rgba(16,63,50,.11),0 4px 14px rgba(16,63,50,.04)}
.mreg-brand{position:relative;display:flex;min-width:0;flex-direction:column;justify-content:space-between;overflow:hidden;padding:clamp(25px,3vw,38px);color:#fff;background:radial-gradient(circle at 95% 4%,rgba(244,200,78,.19),transparent 28%),linear-gradient(155deg,#103f32 0%,#16513f 52%,#236b55 100%)}
.mreg-brand:before{content:"";position:absolute;right:-110px;top:155px;width:280px;height:280px;border:1px solid rgba(255,255,255,.1);border-radius:40px;transform:rotate(24deg);pointer-events:none}
.mreg-brand-lockup,.mreg-brand-copy,.mreg-brand-features{position:relative;z-index:1}
.mreg-brand-lockup{display:flex;align-items:center;gap:12px}
.mreg-brand-mark{width:48px;height:48px;flex:0 0 48px;display:grid;place-items:center;border-radius:15px;background:#fff;box-shadow:0 10px 24px rgba(0,0,0,.13)}
.mreg-brand-mark img{width:34px!important;height:34px!important;max-width:34px;object-fit:contain}
.mreg-brand-wordmark{margin:0;color:#fff;font-size:18px;font-weight:800;line-height:1.1;letter-spacing:-.03em}
.mreg-brand-tagline{margin:4px 0 0;color:rgba(255,255,255,.68);font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.mreg-brand-copy{max-width:370px;margin:auto 0;padding:46px 0}
.mreg-brand-pill{display:inline-flex;align-items:center;gap:8px;margin-bottom:17px;padding:7px 10px;border:1px solid rgba(255,255,255,.16);border-radius:999px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.84);font-size:10px;font-weight:750;letter-spacing:.04em}
.mreg-brand-pill:before{content:"";width:7px;height:7px;border-radius:50%;background:#f4c84e;box-shadow:0 0 0 4px rgba(244,200,78,.12)}
.mreg-brand-copy h2{max-width:355px;margin:0;color:#fff;font-size:clamp(31px,3vw,43px);font-weight:790;line-height:1.04;letter-spacing:-.045em}
.mreg-brand-copy p{max-width:350px;margin:16px 0 0;color:rgba(255,255,255,.72);font-size:13px;line-height:1.75}
.mreg-brand-features{display:grid;gap:9px}
.mreg-brand-feature{display:flex;align-items:flex-start;gap:10px;padding:11px 12px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(255,255,255,.07)}
.mreg-brand-feature svg{width:16px;height:16px;flex:0 0 16px;margin-top:1px;color:#f4d46d}
.mreg-brand-feature strong{display:block;color:#fff;font-size:11px;line-height:1.35}
.mreg-brand-feature span{display:block;margin-top:2px;color:rgba(255,255,255,.68);font-size:10px;line-height:1.45}
.mreg-panel{min-width:0;overflow:visible;border:0;border-radius:0;background:#fff;box-shadow:none}
.mreg-form-panel{padding:clamp(24px,3.3vw,42px)}
.mreg-top{margin:0 0 22px;justify-content:flex-end}
.mreg-top a{font-size:11px}
.mreg-header{padding:0 0 20px;border:0;background:transparent}
.mreg-kicker{font-size:9px;letter-spacing:.15em}
.mreg-header h1{margin:10px 0 7px;font-size:clamp(28px,3vw,36px);letter-spacing:-.04em}
.mreg-header p{font-size:12px;line-height:1.65}
.mreg-body{padding:0}
.mreg-section{margin:12px 0 0;border:0;border-top:1px solid rgba(16,63,50,.1);border-radius:0;background:transparent;box-shadow:none;overflow:visible}
.mreg-section-head{gap:9px;padding:13px 0 0;border:0;background:transparent}
.mreg-section-icon{width:27px;height:27px;flex-basis:27px;border-radius:9px}
.mreg-section-icon svg{width:15px;height:15px}
.mreg-section-head h2{font-size:13px}
.mreg-section-head p{font-size:10px}
.mreg-grid{gap:12px 14px;padding:12px 0 3px}
.mreg-field label,.mreg-label{font-size:11px}
.mreg-field input,.mreg-field select{height:42px;font-size:12px}
.mreg-help{font-size:9px}
.mreg-privacy{padding:12px 14px;font-size:10px}
.mreg-submit-row{align-items:flex-end;padding:12px 13px}
.mreg-status-line{max-width:390px;font-size:10px}
.mreg-button{min-height:43px;font-size:11px}
.mreg-closed-card{grid-template-columns:46px minmax(0,1fr);gap:14px;padding:22px}
.mreg-closed-icon{width:44px;height:44px;border-radius:14px}
.mreg-disabled-note ol{grid-template-columns:1fr;gap:7px}
.mreg-disabled-note li{display:flex;align-items:center;gap:9px;padding:8px 10px}
.mreg-disabled-note li:before{flex:0 0 21px;margin:0}
.mreg-local-preview{width:min(1180px,100%);margin:0 auto 13px}
.mreg footer{margin:15px 0 0;color:#748179;font-size:10px;line-height:1.55}
@media(max-width:900px){.mreg-shell{max-width:700px;grid-template-columns:1fr}.mreg-brand{padding:25px 30px}.mreg-brand-copy{padding:29px 0 23px}.mreg-brand-features{grid-template-columns:repeat(2,minmax(0,1fr))}.mreg-form-panel{padding:30px 32px}}
@media(max-width:560px){.mreg{padding:13px 10px 24px}.mreg-shell{border-radius:22px}.mreg-brand{padding:19px 20px}.mreg-brand-mark{width:42px;height:42px;flex-basis:42px;border-radius:13px}.mreg-brand-mark img{width:30px!important;height:30px!important;max-width:30px}.mreg-brand-wordmark{font-size:16px}.mreg-brand-copy{padding:21px 0 17px}.mreg-brand-copy h2{font-size:29px}.mreg-brand-copy p{margin-top:10px;font-size:11px;line-height:1.6}.mreg-brand-features{display:none}.mreg-form-panel{padding:23px 18px}.mreg-top{margin-bottom:16px}.mreg-header{padding-bottom:16px}.mreg-header h1{font-size:29px}.mreg-grid{grid-template-columns:1fr;gap:11px;padding:10px 0}.mreg-field--wide{grid-column:auto}.mreg-submit-row{align-items:stretch;flex-direction:column-reverse}.mreg-submit-row .mreg-button{width:100%}.mreg-closed-card{grid-template-columns:38px minmax(0,1fr);gap:11px;padding:15px 13px}.mreg-closed-icon{width:38px;height:38px;border-radius:12px}.mreg-closed-copy h2{font-size:20px}.mreg-closed-copy>p{font-size:11px}.mreg-closed-signin{width:100%}}
@media(prefers-reduced-motion:reduce){.mreg,.mreg *,.mreg *:before,.mreg *:after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
.mreg-wizard-progress{margin:0 0 15px;padding:12px 13px;border:1px solid rgba(16,63,50,.09);border-radius:13px;background:#f8fbf9}
.mreg-wizard-progress-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;color:#71837a;font-size:10px}
.mreg-wizard-progress-meta strong{color:#103f32;font-size:11px}
.mreg-wizard-steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:5px;margin:0;padding:0;list-style:none}
.mreg-wizard-steps li{min-width:0;display:flex;align-items:center;gap:5px;color:#829188;font-size:9px;font-weight:700;line-height:1.2}
.mreg-wizard-steps li span{width:21px;height:21px;flex:0 0 21px;display:grid;place-items:center;border:1px solid rgba(16,63,50,.14);border-radius:50%;background:#fff;color:#71837a;font-size:9px}
.mreg-wizard-steps li.is-current{color:#103f32}
.mreg-wizard-steps li.is-current span,.mreg-wizard-steps li.is-complete span{border-color:#103f32;background:#103f32;color:#fff}
.mreg-wizard-steps li.is-complete{color:#236b55}
.mreg-wizard-track{height:3px;margin-top:9px;overflow:hidden;border-radius:5px;background:#e4ede7}
.mreg-wizard-track-fill{width:20%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#236b55,#f4c84e);transition:width .22s ease}
.mreg-wizard-step[hidden]{display:none!important}
.mreg-wizard-step{outline:none}
.mreg-wizard-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:15px;padding-top:13px;border-top:1px solid rgba(16,63,50,.1)}
.mreg-wizard-actions .mreg-button[hidden]{display:none!important}
.mreg-wizard-action-spacer{flex:1}
.mreg-wizard-feedback{flex:1;min-width:0;margin:0;color:#71837a;font-size:10px;line-height:1.45;text-align:center}
.mreg-wizard-feedback.error{color:#a8433d}
.mreg-client-error{margin:10px 0 0;padding:9px 11px;border:1px solid #efc8c3;border-radius:9px;background:#fff5f3;color:#a8433d;font-size:11px;line-height:1.5}
.mreg-review-card{margin-top:3px;padding:14px;border:1px solid rgba(35,107,85,.13);border-radius:13px;background:linear-gradient(120deg,#f5faf6,#fff)}
.mreg-review-card h2{margin:0;color:#103f32;font-size:14px}
.mreg-review-card>p{margin:4px 0 11px;color:#71837a;font-size:10px;line-height:1.5}
.mreg-review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 12px;margin:0}
.mreg-review-item{min-width:0;padding:8px 9px;border:1px solid rgba(16,63,50,.07);border-radius:9px;background:rgba(255,255,255,.84)}
.mreg-review-item dt{margin-bottom:3px;color:#71837a;font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.mreg-review-item dd{margin:0;color:#263a33;font-size:10px;font-weight:650;overflow-wrap:anywhere}
.mreg-review-item--wide{grid-column:1/-1}
.mreg-review-security{margin:9px 0 0;color:#71837a;font-size:9px;line-height:1.45}
.mreg-client-error{margin:0 0 12px;padding:10px 12px;border:1px solid #efc8c3;border-radius:10px;background:#fff5f3;color:#a8433d;font-size:11px;line-height:1.5}
.mreg-client-error[hidden]{display:none!important}
.mreg-wizard-actions-spacer{flex:1}
.mreg-review-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:16px 18px 10px}
.mreg-review-summary>div{min-width:0;padding:9px 10px;border:1px solid rgba(16,63,50,.08);border-radius:10px;background:#f8fbf9}
.mreg-review-summary dt{margin:0 0 4px;color:#71837a;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.mreg-review-summary dd{margin:0;color:#263a33;font-size:11px;font-weight:650;overflow-wrap:anywhere}
.mreg-wizard-step>.mreg-help{margin:8px 18px 0}
.mreg-privacy-details{margin:14px 18px 0}
.mreg-privacy-details summary{color:#236b55;font-size:11px;font-weight:750;cursor:pointer}
.mreg-privacy-details .mreg-privacy{margin-top:9px}
@media(max-width:560px){.mreg-wizard-progress{padding:10px}.mreg-wizard-steps{gap:2px}.mreg-wizard-steps li{flex-direction:column;gap:3px;text-align:center;font-size:8px}.mreg-wizard-steps li span{width:20px;height:20px;flex-basis:20px}.mreg-wizard-progress-meta{margin-bottom:8px}.mreg-wizard-actions{gap:7px}.mreg-wizard-actions .mreg-button{min-width:86px;padding:0 11px}.mreg-wizard-feedback{font-size:9px}.mreg-review-grid{grid-template-columns:1fr 1fr;gap:6px}.mreg-review-item{padding:7px}.mreg-review-item dd{font-size:9px}}
@media(max-width:560px){.mreg-review-summary{grid-template-columns:1fr;margin:13px 12px 8px}.mreg-wizard-step>.mreg-help{margin-right:12px;margin-left:12px}.mreg-privacy-details{margin-right:12px;margin-left:12px}.mreg-wizard-actions{position:sticky;bottom:0;z-index:3;padding:10px 0;background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}}
/* Compact the existing split wizard on laptop and desktop; phone styles stay unchanged. */
@media (min-width:901px) {
    .mreg { padding:34px; }
    .mreg-shell { width:min(1120px,100%); min-height:650px; grid-template-columns:43% 57%; border-radius:30px; }
    .mreg-brand { padding:28px; }
    .mreg-brand-copy { padding:26px 0; }
    .mreg-brand-copy h2 { font-size:clamp(30px,2.7vw,38px); }
    .mreg-brand-copy p { margin-top:12px; line-height:1.55; }
    .mreg-brand-feature { padding:9px 11px; }
    .mreg-form-panel { padding:26px 30px; }
    .mreg-top { min-height:0; margin:0 0 18px; }
    .mreg-header { padding:0 0 14px; }
    .mreg-header h1 { margin:8px 0 6px; font-size:clamp(27px,2.6vw,33px); }
    .mreg-header p { line-height:1.5; }
    .mreg-wizard-progress { margin-bottom:12px; padding:10px 12px; }
    .mreg-section { margin-top:10px; }
    .mreg-section-head { padding-top:10px; }
    .mreg-grid { gap:10px 14px; padding:10px 0 2px; }
    .mreg-wizard-actions { margin-top:12px; padding-top:11px; }
    .mreg footer { margin-top:12px; }
}
</style>

<style>
    .mreg-password-wrap {
        position: relative;
        width: 100%;
    }

    .mreg-password-wrap input {
        padding-right: 48px;
    }

    .mreg-password-toggle {
        position: absolute;
        top: 50%;
        right: 8px;
        display: inline-grid;
        width: 32px;
        height: 32px;
        padding: 0;
        place-items: center;
        transform: translateY(-50%);
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #5f786d;
        cursor: pointer;
    }

    .mreg-password-toggle:hover {
        background: #e6f1eb;
        color: #103f32;
    }

    .mreg-password-toggle:focus-visible {
        outline: 3px solid rgba(35, 107, 85, .24);
        outline-offset: 1px;
    }

    .mreg-password-icon {
        display: block;
        width: 18px;
        height: 18px;
    }

    .mreg-password-icon--off {
        display: none;
    }

    .mreg-password-toggle.is-visible .mreg-password-icon--eye {
        display: none;
    }

    .mreg-password-toggle.is-visible .mreg-password-icon--off {
        display: block;
    }

    @media (prefers-reduced-motion: reduce) {
        .mreg-password-toggle {
            transition: none;
        }
    }
</style>

<main class="mreg">
    @if($localRegistrationPreview)
        <aside class="mreg-local-preview" role="status"><strong>Local registration test only</strong>This page uses an isolated development database and placeholder privacy/retention text. No external email is sent; do not enter real personal information.</aside>
    @endif
    <div class="mreg-shell">
        <aside class="mreg-brand" aria-label="MAGIREM AI student wellness">
            <div class="mreg-brand-lockup">
                <div class="mreg-brand-mark"><x-brand-logo variant="emblem" alt="MAGIREM AI emblem" /></div>
                <div><p class="mreg-brand-wordmark">MAGIREM AI</p><p class="mreg-brand-tagline">MinSU Student Wellness</p></div>
            </div>
            <div class="mreg-brand-copy">
                <span class="mreg-brand-pill">A supportive space for MinSU students</span>
                <h2>Your well-being belongs in the conversation.</h2>
                <p>Create a student account to access a private, student-centered space for reflection, support, and connection.</p>
            </div>
            <div class="mreg-brand-features" aria-label="Account safeguards">
                <div class="mreg-brand-feature"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 3.5 11 8 11zM9 12l2 2 4-4"/></svg><div><strong>Private by design</strong><span>Your application is available only to authorized reviewers.</span></div></div>
                <div class="mreg-brand-feature"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div><strong>Reviewed before access</strong><span>Student accounts remain pending until eligibility is checked.</span></div></div>
            </div>
        </aside>
        <section class="mreg-panel" aria-labelledby="mreg-title">
            <div class="mreg-form-panel">
                <div class="mreg-top"><a href="{{ route('login') }}">Already have an account? Sign in</a></div>
                <header class="mreg-header">
                    <span class="mreg-kicker">Student registration</span>
                    <h1 id="mreg-title">Create your student account</h1>
                    <p>Apply using your MinSU institutional email for sign-in. A separate personal Gmail is used for verification and account updates.</p>
                </header>
                <div class="mreg-body">
                @if(session('registration_error'))<div class="mreg-flash warning" role="alert">{{ session('registration_error') }}</div>@endif
                @if($errors->any())<div class="mreg-errors" role="alert"><strong>Please review the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                @if(!$registrationEnabled)
                    <section class="mreg-closed-card" aria-labelledby="registration-closed-title">
                        <div class="mreg-closed-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l8 3v5c0 5.1-3.5 8.5-8 10-4.5-1.5-8-4.9-8-10V6l8-3zm-3 9 2 2 4-4"/></svg></div>
                        <div class="mreg-closed-copy">
                            <span class="mreg-state-pill"><span aria-hidden="true"></span> Applications paused</span>
                            <h2 id="registration-closed-title">Student registration is not open yet</h2>
                            <p>{{ $registrationBlocker }}</p>
                            <div class="mreg-disabled-note" role="status">
                                <strong>Student account applications</strong>
                                <ol>
                                    <li>Use your <strong>@minsu.edu.ph</strong> address to sign in.</li>
                                    <li>Verify a separate personal Gmail for account updates.</li>
                                    <li>Wait for authorized reviewers to check your student eligibility.</li>
                                </ol>
                                <span>Staff and administrator accounts are provisioned internally by authorized administrators. An email domain, ID image, or OTP alone does not establish student eligibility.</span>
                            </div>
                            <a class="mreg-button mreg-button--secondary mreg-closed-signin" href="{{ route('login') }}">Already have an account? Sign in <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </section>
                @else
                    <form id="registrationForm" method="POST" action="{{ route('register.submit') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <input type="hidden" id="wizard-step-input" name="_wizard_step" value="{{ $wizardInitialStep }}">
                        <nav class="mreg-wizard-progress" aria-label="Registration progress">
                            <div class="mreg-wizard-progress-meta"><span id="wizard-step-count" aria-live="polite">Step {{ $wizardInitialStep }} of 5</span><strong id="wizard-step-name">{{ ['Account information','Student information','Personal Gmail','Student ID','Review & submit'][$wizardInitialStep - 1] }}</strong></div>
                            <ol class="mreg-wizard-steps">
                                @foreach(['Account','Student','Gmail','ID','Review'] as $index => $label)
                                    <li data-wizard-indicator="{{ $index + 1 }}" class="{{ $wizardInitialStep === $index + 1 ? 'is-current' : ($wizardInitialStep > $index + 1 ? 'is-complete' : '') }}" @if($wizardInitialStep === $index + 1) aria-current="step" @endif><span>{{ $index + 1 }}</span>{{ $label }}</li>
                                @endforeach
                            </ol>
                            <div class="mreg-wizard-track" role="progressbar" aria-label="Registration progress" aria-valuemin="1" aria-valuemax="5" aria-valuenow="{{ $wizardInitialStep }}" id="wizard-progressbar"><div class="mreg-wizard-track-fill" id="wizard-progress-fill" style="width:{{ $wizardInitialStep * 20 }}%"></div></div>
                        </nav>
                        <p class="mreg-client-error" id="wizard-validation-error" role="alert" hidden></p>
                        <div class="mreg-wizard-step" data-wizard-step="1" aria-labelledby="account-title" tabindex="-1" @if($wizardInitialStep !== 1) hidden @endif>
                        <section class="mreg-section" aria-labelledby="account-title">
                            <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m12-13a4 4 0 11-8 0 4 4 0 018 0z"/></svg></span><div><h2 id="account-title">Account information</h2><p>Use your MinSU institutional email and password for sign-in.</p></div></div>
                            <div class="mreg-grid">
                                <div class="mreg-field"><label for="name">Full name <span class="mreg-required">*</span></label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required @error('name') aria-invalid="true" @enderror>@error('name')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="institutional_email">MinSU institutional email <span class="mreg-required">*</span></label><input id="institutional_email" name="institutional_email" type="email" value="{{ old('institutional_email') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" placeholder="name@minsu.edu.ph" required @error('institutional_email') aria-invalid="true" @enderror><p class="mreg-help">This is your login email. The @minsu.edu.ph domain is required but does not by itself verify identity.</p>@error('institutional_email')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="password">Password <span class="mreg-required">*</span></label><div class="mreg-password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required @error('password') aria-invalid="true" @enderror><button class="mreg-password-toggle" type="button" data-password-toggle data-target="password" aria-controls="password" aria-label="Show password" aria-pressed="false"><svg class="mreg-password-icon mreg-password-icon--eye" aria-hidden="true" fill="none" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg><svg class="mreg-password-icon mreg-password-icon--off" aria-hidden="true" fill="none" viewBox="0 0 24 24"><path d="m3 3 18 18M10.6 6.2A10.2 10.2 0 0 1 12 6c6 0 9.5 6 9.5 6a17.7 17.7 0 0 1-3.1 3.8M6.1 6.9C3.9 8.4 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3-.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9.7 9.7a3.2 3.2 0 0 0 4.6 4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div><p class="mreg-help">At least 12 characters, including uppercase, lowercase, a number, and a symbol.</p>@error('password')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="password_confirmation">Confirm password <span class="mreg-required">*</span></label><div class="mreg-password-wrap"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><button class="mreg-password-toggle" type="button" data-password-toggle data-target="password_confirmation" aria-controls="password_confirmation" aria-label="Show confirm password" aria-pressed="false"><svg class="mreg-password-icon mreg-password-icon--eye" aria-hidden="true" fill="none" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg><svg class="mreg-password-icon mreg-password-icon--off" aria-hidden="true" fill="none" viewBox="0 0 24 24"><path d="m3 3 18 18M10.6 6.2A10.2 10.2 0 0 1 12 6c6 0 9.5 6 9.5 6a17.7 17.7 0 0 1-3.1 3.8M6.1 6.9C3.9 8.4 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3-.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9.7 9.7a3.2 3.2 0 0 0 4.6 4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></div></div>
                            </div>
                        </section>

                        </div>
                        <div class="mreg-wizard-step" data-wizard-step="2" aria-labelledby="student-title" tabindex="-1" @if($wizardInitialStep !== 2) hidden @endif>
                        <section class="mreg-section" aria-labelledby="student-title">
                            <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10l9-6 9 6-9 6-9-6zm4 3v5c3 2.5 7 2.5 10 0v-5"/></svg></span><div><h2 id="student-title">Student information</h2><p>Enter the details reviewers need to confirm your student eligibility.</p></div></div>
                            <div class="mreg-grid">
                                <div class="mreg-field"><label for="campus">MinSU campus <span class="mreg-required">*</span></label><select id="campus" name="campus" required @error('campus') aria-invalid="true" @enderror><option value="">Select your campus</option>@foreach(['Bongabong Campus','Calapan Campus','Victoria Campus'] as $campus)<option value="{{ $campus }}" @selected(old('campus') === $campus)>{{ $campus }}</option>@endforeach</select>@error('campus')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="student_id">Student ID <span class="mreg-required">*</span></label><input id="student_id" name="student_id" value="{{ old('student_id') }}" autocomplete="off" maxlength="80" placeholder="Enter your student ID" required @error('student_id') aria-invalid="true" @enderror>@error('student_id')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="program">Program <span class="mreg-required">*</span></label><input id="program" name="program" value="{{ old('program') }}" maxlength="160" placeholder="e.g., BS Psychology" required @error('program') aria-invalid="true" @enderror>@error('program')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                                <div class="mreg-field"><label for="year_level">Year level <span class="mreg-required">*</span></label><select id="year_level" name="year_level" required @error('year_level') aria-invalid="true" @enderror><option value="">Select year</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level') === $year)>{{ $year }}</option>@endforeach</select>@error('year_level')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                            </div>
                        </section>

                        </div>
                        <div class="mreg-wizard-step" data-wizard-step="3" aria-labelledby="notification-title" tabindex="-1" @if($wizardInitialStep !== 3) hidden @endif>
                        <section class="mreg-section" aria-labelledby="notification-title">
                            <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6l9 7 9-7M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span><div><h2 id="notification-title">Personal Gmail</h2><p>This is separate from your MinSU login. After you submit, a one-time code will be sent here; the address is not verified until you confirm it.</p></div></div>
                            <div class="mreg-grid"><div class="mreg-field mreg-field--wide"><label for="notification_email">Personal Gmail <span class="mreg-required">*</span></label><input id="notification_email" name="notification_email" type="email" value="{{ old('notification_email') }}" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" title="Enter a Gmail address ending in gmail.com or googlemail.com" placeholder="you@gmail.com" required>@error('notification_email')<p class="mreg-error">{{ $message }}</p>@enderror<p class="mreg-help">The verification code is sent after you submit your application. This page does not mark your email as verified.</p></div></div>
                        </section>

                        </div>
                        <div class="mreg-wizard-step" data-wizard-step="4" aria-labelledby="document-title" tabindex="-1" @if($wizardInitialStep !== 4) hidden @endif>
                        <section class="mreg-section" aria-labelledby="document-title">
                            <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v14H4zM8 9h3m-3 4h8m-8 3h8m2-7h.01"/></svg></span><div><h2 id="document-title">Student ID</h2><p>Upload or capture a clear image of your MinSU student ID. JPG, PNG, or WebP; maximum 5 MB.</p></div></div>
                            <div class="mreg-grid">
                                <div class="mreg-field mreg-field--wide">
                                    <div class="mreg-photo-tools">
                                        <label class="mreg-file-label">Choose ID photo<input class="mreg-file" type="file" id="id_photo" name="id_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="photo-help @error('id_photo')id-photo-error @enderror"></label>
                                        <button class="mreg-button mreg-button--secondary" type="button" id="open-camera">Use camera</button>
                                        <button class="mreg-small-button" type="button" id="remove-photo" hidden>Remove photo</button>
                                    </div>
                                    <p class="mreg-help" id="photo-help">For security, the server checks image contents and re-encodes the file to remove embedded metadata. The image is private and visible only to authorized account reviewers.</p>
                                    <div class="mreg-photo-preview" id="photo-preview" hidden><img id="photo-preview-image" alt="Preview of selected identification image"><div class="mreg-photo-actions"><strong id="photo-name" class="mreg-help"></strong><button class="mreg-small-button" type="button" id="retake-photo">Retake with camera</button></div></div>
                                    @error('id_photo')<p class="mreg-error" id="id-photo-error">{{ $message }}</p>@enderror
                                    <div class="mreg-status-line" id="photo-status" role="status" aria-live="polite"></div>
                                </div>
                                <div class="mreg-field mreg-field--wide"><label class="mreg-check"><input type="checkbox" name="alternative_review" value="1" @checked(old('alternative_review'))><span>I cannot provide an ID image and request an accessible alternative review method. An administrator will contact me through the verified channel.</span></label>@error('alternative_review')<p class="mreg-error">{{ $message }}</p>@enderror</div>
                            </div>
                        </section>

                        </div>
                        <div class="mreg-wizard-step" data-wizard-step="5" aria-labelledby="review-title" tabindex="-1" @if($wizardInitialStep !== 5) hidden @endif>
                            <section class="mreg-section" aria-labelledby="review-title">
                                <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12l4 4L19 6"/></svg></span><div><h2 id="review-title">Review your application</h2><p>Check your details before sending them for Guidance Office review.</p></div></div>
                                <dl class="mreg-review-summary" id="registration-review-summary" aria-live="polite">
                                    <div><dt>Full name</dt><dd data-review="name">Not entered</dd></div>
                                    <div><dt>MinSU email</dt><dd data-review="institutional_email">Not entered</dd></div>
                                    <div><dt>Campus</dt><dd data-review="campus">Not selected</dd></div>
                                    <div><dt>Student ID</dt><dd data-review="student_id">Not entered</dd></div>
                                    <div><dt>Program and year</dt><dd data-review="program_year">Not entered</dd></div>
                                    <div><dt>Personal Gmail</dt><dd data-review="notification_email">Not entered</dd></div>
                                    <div><dt>ID verification</dt><dd data-review="id_photo">No ID image selected</dd></div>
                                </dl>
                                <p class="mreg-help">Your password is never shown in this review.</p>
                            </section>
                            <section class="mreg-section" aria-labelledby="privacy-title">
                                <div class="mreg-section-head"><span class="mreg-section-icon" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 3.5 11 8 11zM9 12l2 2 4-4"/></svg></span><div><h2 id="privacy-title">Privacy and use of information</h2><p>@if($localRegistrationPreview)This synthetic local-test notice is not institutional policy.@else Review the institution-approved notice before submitting.@endif</p></div></div>
                                <details class="mreg-privacy-details">
                                    <summary>Read the account verification notice</summary>
                                    <div class="mreg-privacy"><strong>Account verification notice · {{ $privacyNoticeVersion }}</strong><div style="white-space:pre-line;margin-top:8px">{{ $privacyNoticeText }}</div><p style="margin:10px 0 0">Privacy contact: <strong>{{ $privacyContact }}</strong></p><p style="margin:6px 0 0">ID retention: <strong>{{ $retentionSchedule }}</strong></p></div>
                                </details>
                                <div class="mreg-grid"><div class="mreg-field mreg-field--wide"><label class="mreg-check"><input type="checkbox" name="privacy_acknowledgement" value="1" required @checked(old('privacy_acknowledgement'))><span>I have read this notice and understand that my application remains pending Guidance Office review. <span class="mreg-required">*</span></span></label>@error('privacy_acknowledgement')<p class="mreg-error">{{ $message }}</p>@enderror</div></div>
                            </section>
                            <p class="mreg-status-line">Submission creates a pending application. A domain, uploaded ID, or Gmail OTP alone does not prove MinSU identity or eligibility.</p>
                        </div>
                        <div class="mreg-wizard-actions">
                            <button class="mreg-button mreg-button--secondary" id="wizard-back" type="button" hidden>Back</button>
                            <span class="mreg-wizard-actions-spacer"></span>
                            <button class="mreg-button mreg-button--primary" id="wizard-next" type="button">Continue</button>
                            <button class="mreg-button mreg-button--primary" id="submit-registration" type="submit" hidden>Submit for review</button>
                        </div>
                    </form>
                @endif
            </div>
            <footer>Already applied? <a href="{{ route('login') }}">Sign in with your MinSU institutional email and password</a> to check your status.</footer>
            </div>
        </section>
    </div>
</main>

<dialog class="mreg-dialog" id="camera-dialog" aria-labelledby="camera-title">
    <div class="mreg-camera-head"><h2 id="camera-title">Capture your identification</h2><button class="mreg-small-button" type="button" data-camera-close>Close camera</button></div>
    <div class="mreg-camera-content"><video id="camera-video" autoplay playsinline muted></video><p class="mreg-camera-message" id="camera-message" aria-live="polite">Allow camera access when your browser asks. Point the camera at the ID in good light.</p><div class="mreg-camera-actions"><button class="mreg-button mreg-button--secondary" type="button" id="switch-camera">Switch camera</button><button class="mreg-button mreg-button--primary" type="button" id="capture-photo" disabled>Capture photo</button></div></div>
</dialog>

@if($registrationEnabled)
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        const target = document.getElementById(toggle.dataset.target);
        if (!target) {
            return;
        }

        toggle.addEventListener('click', function () {
            const visible = target.type === 'password';
            target.type = visible ? 'text' : 'password';
            toggle.classList.toggle('is-visible', visible);
            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            toggle.setAttribute('aria-label', visible
                ? (target.id === 'password_confirmation' ? 'Hide confirm password' : 'Hide password')
                : (target.id === 'password_confirmation' ? 'Show confirm password' : 'Show password'));
            target.focus({ preventScroll: true });
        });
    });

    const fileInput = document.getElementById('id_photo');
    const preview = document.getElementById('photo-preview');
    const previewImage = document.getElementById('photo-preview-image');
    const photoStatus = document.getElementById('photo-status');
    const removeButton = document.getElementById('remove-photo');
    let objectUrl = null;
    let cameraStream = null;
    let cameraFacing = 'environment';
    const dialog = document.getElementById('camera-dialog');
    const video = document.getElementById('camera-video');
    const capture = document.getElementById('capture-photo');
    const cameraMessage = document.getElementById('camera-message');
    const form = document.getElementById('registrationForm');
    const wizardPanels = Array.from(form.querySelectorAll('[data-wizard-step]'));
    const wizardNames = ['Account information', 'Student information', 'Personal Gmail', 'Student ID', 'Review & submit'];
    const wizardStepInput = document.getElementById('wizard-step-input');
    const wizardCount = document.getElementById('wizard-step-count');
    const wizardName = document.getElementById('wizard-step-name');
    const wizardProgress = document.getElementById('wizard-progressbar');
    const wizardProgressFill = document.getElementById('wizard-progress-fill');
    const wizardError = document.getElementById('wizard-validation-error');
    const backButton = document.getElementById('wizard-back');
    const nextButton = document.getElementById('wizard-next');
    const submitButton = document.getElementById('submit-registration');
    const stepIndicators = Array.from(form.querySelectorAll('[data-wizard-indicator]'));
    let currentStep = Math.max(1, Math.min(5, Number(wizardStepInput.value) || 1));

    function updateReview() {
        const value = id => (document.getElementById(id)?.value || '').trim();
        const write = (key, text, fallback) => {
            const target = form.querySelector('[data-review="' + key + '"]');
            if (target) target.textContent = text || fallback;
        };
        write('name', value('name'), 'Not entered');
        write('institutional_email', value('institutional_email'), 'Not entered');
        write('campus', value('campus'), 'Not selected');
        write('student_id', value('student_id'), 'Not entered');
        write('program_year', [value('program'), value('year_level')].filter(Boolean).join(' · '), 'Not entered');
        write('notification_email', value('notification_email'), 'Not entered');
        const selectedFile = fileInput.files && fileInput.files[0];
        const alternative = form.querySelector('[name="alternative_review"]')?.checked;
        write('id_photo', selectedFile ? selectedFile.name + ' · ' + Math.ceil(selectedFile.size / 1024) + ' KB' : (alternative ? 'Accessible alternative review requested' : ''), 'No ID image selected');
    }

    function setStep(step, moveFocus) {
        currentStep = Math.max(1, Math.min(5, step));
        wizardStepInput.value = String(currentStep);
        wizardPanels.forEach(panel => {
            const visible = Number(panel.dataset.wizardStep) === currentStep;
            panel.hidden = !visible;
            panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });
        wizardCount.textContent = 'Step ' + currentStep + ' of 5';
        wizardName.textContent = wizardNames[currentStep - 1];
        wizardProgress.setAttribute('aria-valuenow', String(currentStep));
        wizardProgressFill.style.width = (currentStep * 20) + '%';
        stepIndicators.forEach(item => {
            const index = Number(item.dataset.wizardIndicator);
            item.classList.toggle('is-current', index === currentStep);
            item.classList.toggle('is-complete', index < currentStep);
            if (index === currentStep) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        backButton.hidden = currentStep === 1;
        nextButton.hidden = currentStep === 5;
        submitButton.hidden = currentStep !== 5;
        wizardError.hidden = true;
        wizardError.textContent = '';
        if (currentStep === 5) updateReview();
        if (moveFocus) {
            const panel = wizardPanels.find(item => Number(item.dataset.wizardStep) === currentStep);
            panel?.focus({preventScroll:true});
            panel?.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block:'start'});
        }
    }

    function showWizardError(message, focusTarget) {
        wizardError.textContent = message;
        wizardError.hidden = false;
        if (focusTarget) focusTarget.focus();
        wizardError.scrollIntoView({behavior:'smooth', block:'nearest'});
    }

    function validateStep(step, focusInvalid) {
        const panel = wizardPanels.find(item => Number(item.dataset.wizardStep) === step);
        if (!panel) return true;
        if (step === 1) {
            const email = document.getElementById('institutional_email');
            email.setCustomValidity(email.value.trim() && !/^[^@\s]+@minsu\.edu\.ph$/i.test(email.value.trim()) ? 'Use your MinSU institutional email ending in @minsu.edu.ph.' : '');
        }
        if (step === 3) {
            const email = document.getElementById('notification_email');
            email.setCustomValidity(email.value.trim() && !/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i.test(email.value.trim()) ? 'Enter a Gmail address ending in gmail.com or googlemail.com.' : '');
        }
        const controls = Array.from(panel.querySelectorAll('input, select, textarea')).filter(control => !control.disabled && control.type !== 'hidden' && control.type !== 'file');
        const invalid = controls.find(control => !control.checkValidity());
        if (invalid) {
            if (focusInvalid) {
                invalid.reportValidity();
                showWizardError('Please complete the highlighted field before continuing.', invalid);
            }
            return false;
        }
        if (step === 4) {
            const file = fileInput.files && fileInput.files[0];
            const alternative = panel.querySelector('[name="alternative_review"]')?.checked;
            if (file && file.size > 5 * 1024 * 1024) {
                photoStatus.textContent = 'This image exceeds the 5 MB limit. Choose a smaller image.';
                photoStatus.classList.add('error');
                if (focusInvalid) showWizardError('Choose an ID image under 5 MB, or request an accessible alternative review.', document.getElementById('open-camera'));
                return false;
            }
            if (!file && !alternative) {
                photoStatus.textContent = 'Choose an ID image or request the accessible alternative review method.';
                photoStatus.classList.add('error');
                if (focusInvalid) showWizardError('An ID image or an accessible alternative review request is required.', fileInput);
                return false;
            }
        }
        return true;
    }

    nextButton.addEventListener('click', function () {
        if (validateStep(currentStep, true)) setStep(currentStep + 1, true);
    });
    backButton.addEventListener('click', function () { setStep(currentStep - 1, true); });
    form.addEventListener('input', updateReview);
    form.addEventListener('change', updateReview);
    setStep(currentStep, false);

    function setCameraMessage(text, isError) { cameraMessage.textContent = text; cameraMessage.classList.toggle('error', !!isError); }
    function stopCamera() {
        if (cameraStream) cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null; video.pause(); video.srcObject = null; capture.disabled = true;
    }
    async function startCamera() {
        stopCamera();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCameraMessage('Camera capture is unavailable in this browser or page context. Choose an image from your device instead.', true);
            return;
        }
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:cameraFacing},width:{ideal:1280},height:{ideal:720}},audio:false});
            video.srcObject = cameraStream;
            await video.play(); capture.disabled = false;
            setCameraMessage('Camera ready. Check that the full ID is visible and readable before capture.', false);
        } catch (error) {
            stopCamera();
            setCameraMessage(error && error.name === 'NotAllowedError' ? 'Camera permission was denied. You can still choose an image from your device.' : 'Could not start the camera. Check browser permission or choose an image from your device.', true);
        }
    }
    function showPreview(file) {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        if (!file) { objectUrl = null; preview.hidden = true; previewImage.removeAttribute('src'); removeButton.hidden = true; document.getElementById('photo-name').textContent=''; return; }
        objectUrl = URL.createObjectURL(file); previewImage.src = objectUrl;
        document.getElementById('photo-name').textContent = file.name + ' · ' + Math.ceil(file.size/1024) + ' KB';
        preview.hidden = false; removeButton.hidden = false;
    }
    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0]; showPreview(file);
        photoStatus.textContent = file ? 'Preview only. The server will verify the image contents and size.' : '';
        photoStatus.classList.remove('error');
        if (file && file.size > 5 * 1024 * 1024) { photoStatus.textContent = 'This image exceeds the 5 MB limit. Choose a smaller image.'; photoStatus.classList.add('error'); }
    });
    document.getElementById('open-camera').addEventListener('click', async function () { if (!dialog.open) dialog.showModal(); await startCamera(); });
    document.querySelectorAll('[data-camera-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', stopCamera);
    dialog.addEventListener('cancel', stopCamera);
    document.getElementById('switch-camera').addEventListener('click', async function () { cameraFacing = cameraFacing === 'environment' ? 'user' : 'environment'; await startCamera(); });
    capture.addEventListener('click', function () {
        if (!video.videoWidth || !video.videoHeight) { setCameraMessage('Wait for the camera preview, then try again.', true); return; }
        const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) { setCameraMessage('The photo could not be captured. Try again or upload an image.', true); return; }
            try {
                const file = new File([blob], 'minsu-id-capture.jpg', {type:'image/jpeg',lastModified:Date.now()});
                const transfer = new DataTransfer(); transfer.items.add(file); fileInput.files = transfer.files;
                showPreview(file); photoStatus.textContent = 'Photo captured. Review the preview or retake it before submitting.'; photoStatus.classList.remove('error'); dialog.close();
            } catch (error) { setCameraMessage('This browser cannot attach the captured photo to the form. Please use the upload option.', true); }
        },'image/jpeg',0.9);
    });
    removeButton.addEventListener('click', function () { fileInput.value=''; showPreview(null); photoStatus.textContent='Selected image removed.'; photoStatus.classList.remove('error'); });
    document.getElementById('retake-photo').addEventListener('click', async function () { if (!dialog.open) dialog.showModal(); await startCamera(); });
    window.addEventListener('pagehide', function () { stopCamera(); if (objectUrl) URL.revokeObjectURL(objectUrl); });
    form.addEventListener('submit', function (event) {
        if (this.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (currentStep !== 5) {
            event.preventDefault();
            if (validateStep(currentStep, true)) setStep(currentStep + 1, true);
            return;
        }
        for (let step = 1; step <= 5; step++) {
            if (!validateStep(step, false)) {
                event.preventDefault();
                setStep(step, true);
                validateStep(step, true);
                return;
            }
        }
        this.dataset.submitting='true'; this.setAttribute('aria-busy','true');
        submitButton.disabled=true; submitButton.textContent='Submitting application…';
    });
});
</script>
@endif
@endsection
