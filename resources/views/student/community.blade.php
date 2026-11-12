@extends('layouts.app')

@section('title', 'Community Support - MAGIREM AI')

@section('content')
<style>
.community-container,.community-container *,.community-container *::before,.community-container *::after{box-sizing:border-box}
.community-container{--forest:#103f32;--forest-2:#174f3f;--emerald:#236b55;--mint:#e6f1eb;--mint-2:#d8ebe2;--ivory:#f8faf7;--gold:#f4c84e;--text:#263a33;--muted:#71837a;--line:rgba(16,63,50,.11);--shadow:0 16px 42px rgba(16,63,50,.07);--ease:cubic-bezier(.22,1,.36,1);width:100%;padding:18px 0 56px;color:var(--text);font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.community-header{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.3fr) minmax(270px,.7fr);gap:26px;align-items:center;margin:0 0 16px;padding:28px 30px;border:1px solid var(--line);border-radius:21px;background:radial-gradient(circle at 91% 15%,rgba(216,235,226,.95),transparent 18rem),linear-gradient(135deg,#fff,#f8fbf9 60%,#eef6f2);box-shadow:var(--shadow)}
.community-header::after{content:"";position:absolute;right:-65px;bottom:-120px;width:230px;height:230px;border-radius:50%;border:38px solid rgba(35,107,85,.045)}
.community-header-copy{position:relative;z-index:1}.community-eyebrow{display:inline-flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--emerald);font-size:9.5px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.community-eyebrow::before{content:"";width:20px;height:2px;border-radius:99px;background:var(--gold)}
.community-header h1{margin:0;color:var(--forest);font-size:clamp(31px,3.3vw,48px);line-height:1.04;letter-spacing:-.05em;font-weight:840}.community-header p{max-width:720px;margin:9px 0 0;color:var(--muted);font-size:12.5px;line-height:1.68}
.community-safety{position:relative;z-index:1;padding:17px;border:1px solid rgba(16,63,50,.08);border-radius:15px;background:rgba(255,255,255,.76);backdrop-filter:blur(12px)}.community-safety strong{display:block;color:var(--forest);font-size:11.5px}.community-safety p{margin:5px 0 0;font-size:9.8px;line-height:1.55}.community-safety-badge{display:inline-flex;align-items:center;gap:6px;margin-bottom:10px;color:var(--emerald);font-size:8.5px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.community-safety-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--gold)}
.community-intro-row{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 12px}.community-intro-row h2{margin:0;color:var(--forest);font-size:18px;letter-spacing:-.025em}.community-intro-row p{margin:4px 0 0;color:var(--muted);font-size:10.5px;line-height:1.5}
.composer-toggle,.composer-disabled{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 15px;border:1px solid rgba(16,63,50,.13);border-radius:10px;background:var(--forest);color:#fff;font:inherit;font-size:10px;font-weight:800;cursor:pointer;box-shadow:0 9px 22px rgba(16,63,50,.12);transition:.18s var(--ease)}.composer-toggle:hover{transform:translateY(-1px);background:var(--forest-2)}.composer-toggle svg{width:15px;height:15px}.composer-disabled{color:var(--muted);background:#fff;cursor:default;box-shadow:none}
.composer-card{display:none;margin-bottom:14px;padding:18px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow);animation:composer-in .22s var(--ease)}.composer-card.is-open{display:block}@keyframes composer-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}.composer-card h2{margin:0 0 14px;color:var(--forest);font-size:15px;font-weight:820}.composer-close{float:right;border:0;background:transparent;color:var(--muted);font:inherit;font-size:18px;cursor:pointer}
.alert{display:flex;align-items:center;gap:9px;margin-bottom:12px;padding:11px 13px;border-radius:11px;font-size:10px;font-weight:700}.alert-success{background:#eef8f2;border:1px solid rgba(35,107,85,.13);color:var(--forest)}.alert-warning{background:#fff8e7;border:1px solid #f0d895;color:#69541b}.alert svg{width:16px;height:16px}
.form-row{display:grid;grid-template-columns:minmax(0,.8fr) minmax(240px,.45fr);gap:10px}.form-group{margin-bottom:11px}.form-group label{display:block;margin-bottom:6px;color:#657970;font-size:8.8px;font-weight:850;letter-spacing:.06em;text-transform:uppercase}.form-group input,.form-group textarea,.form-group select{width:100%;border:1px solid rgba(16,63,50,.11);border-radius:10px;background:var(--ivory);color:var(--text);font:inherit;font-size:10.5px}.form-group input,.form-group select{min-height:40px;padding:0 10px}.form-group textarea{min-height:96px;padding:11px 12px;resize:vertical;line-height:1.55}.form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:rgba(35,107,85,.4);background:#fff;box-shadow:0 0 0 3px rgba(35,107,85,.07)}.checkbox-group{min-height:40px;display:flex;align-items:center;gap:9px;padding:0 11px;border:1px solid rgba(16,63,50,.08);border-radius:10px;background:var(--ivory)}.checkbox-group input{width:15px;height:15px;accent-color:var(--emerald)}.checkbox-group label{margin:0;color:var(--text);font-size:9.8px;text-transform:none;letter-spacing:0}
.btn-submit{min-height:38px;padding:0 13px;border:0;border-radius:9px;background:var(--forest);color:#fff;font:inherit;font-size:9.8px;font-weight:820;cursor:pointer;box-shadow:0 9px 22px rgba(16,63,50,.14);transition:.18s var(--ease)}.btn-submit:hover{transform:translateY(-1px);background:var(--forest-2)}.btn-submit:disabled{opacity:.65;cursor:wait}
.community-layout{display:grid;grid-template-columns:minmax(0,1fr) 245px;gap:14px;align-items:start}.community-feed{min-width:0}.community-side{display:grid;gap:12px}.side-card{padding:16px;border:1px solid var(--line);border-radius:15px;background:linear-gradient(145deg,#edf6f1,#fff);box-shadow:0 8px 24px rgba(16,63,50,.04)}.side-card h3{margin:0;color:var(--forest);font-size:12px}.side-card p{margin:6px 0 12px;color:var(--muted);font-size:9.5px;line-height:1.55}.prompt-list{display:grid;gap:7px}.prompt-button{width:100%;padding:9px 10px;border:1px solid rgba(16,63,50,.1);border-radius:9px;background:rgba(255,255,255,.82);color:var(--text);font:inherit;font-size:8.9px;line-height:1.35;text-align:left;cursor:pointer;transition:.18s var(--ease)}.prompt-button:hover{border-color:rgba(35,107,85,.32);background:#fff;transform:translateY(-1px)}.guideline-list{display:grid;gap:8px;margin:12px 0 0;padding:0;list-style:none;color:var(--muted);font-size:9.2px;line-height:1.45}.guideline-list li{position:relative;padding-left:13px}.guideline-list li::before{content:"";position:absolute;left:0;top:.5em;width:5px;height:5px;border-radius:50%;background:var(--emerald)}
.filters-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 12px;padding:9px 10px;border:1px solid var(--line);border-radius:13px;background:#fff}.filters-bar>span{color:#81928a!important;font-size:8.5px!important;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.filter-group{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.filter-btn{min-height:31px;display:inline-flex;align-items:center;padding:0 9px;border:1px solid rgba(16,63,50,.08);border-radius:8px;background:var(--ivory);color:#687c72!important;text-decoration:none;font-size:8.8px;font-weight:780;transition:.18s var(--ease)}.filter-btn:hover{background:var(--mint);color:var(--forest)!important}.filter-btn.active{background:var(--forest);border-color:var(--forest);color:#fff!important}
.feed-heading{display:flex;align-items:end;justify-content:space-between;gap:10px;margin:0 0 9px}.feed-heading h3{margin:0;color:var(--forest);font-size:14px}.feed-heading span{color:var(--muted);font-size:9px}.messages-grid{display:grid;gap:10px}.message-card{position:relative;overflow:hidden;padding:16px;border:1px solid var(--line);border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(16,63,50,.04);transition:.18s var(--ease)}.message-card:hover{transform:translateY(-1px);box-shadow:var(--shadow)}.message-card.pinned::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--gold)}
.message-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:11px}.message-author{display:flex;align-items:center;gap:10px;min-width:0}.message-avatar{flex:0 0 36px;width:36px;height:36px;display:grid;place-items:center;border-radius:11px;background:var(--mint);color:var(--forest);font-size:12px;font-weight:850}.message-info{min-width:0}.message-info h4{margin:0;color:var(--forest);font-size:10.8px;font-weight:820;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.message-info p{margin:3px 0 0;color:#8b9b93;font-size:8.8px}.message-badges{display:flex;justify-content:flex-end;gap:5px;flex-wrap:wrap}.badge{min-height:23px;display:inline-flex;align-items:center;padding:0 7px;border-radius:999px;border:1px solid rgba(16,63,50,.08);background:var(--ivory);color:#71847a;font-size:7.8px;font-weight:800}.badge-pinned{background:#fff8df;color:#80651c;border-color:rgba(244,200,78,.28)}.badge-category{background:var(--mint);color:var(--forest)}.badge-anonymous{background:#f5f7f6;color:#6f8178}
.message-content{margin-bottom:12px;padding:12px 13px;border-radius:11px;background:var(--ivory);color:var(--text);font-size:10.7px;line-height:1.65;white-space:pre-wrap}.message-title{margin:0 0 7px;color:var(--forest);font-size:15px;line-height:1.25}.message-support{display:inline-flex;margin-top:9px;padding:5px 8px;border-radius:999px;background:var(--mint);color:var(--emerald);font-size:8.2px;font-weight:800}.message-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding-top:10px;border-top:1px solid rgba(16,63,50,.065)}.action-btn{min-height:30px;display:inline-flex;align-items:center;gap:5px;padding:0 8px;border:1px solid rgba(16,63,50,.08);border-radius:8px;background:#fff;color:#71847a;font:inherit;font-size:8.6px;font-weight:760;cursor:pointer;transition:.18s var(--ease)}.action-btn:hover,.action-btn.liked{background:var(--mint);color:var(--forest);border-color:rgba(35,107,85,.14)}.action-btn:disabled{opacity:.6;cursor:wait}
.comments-panel{margin-top:10px;padding:12px;border-top:1px solid rgba(16,63,50,.08);background:var(--ivory);border-radius:10px}.comments-status{color:var(--muted);font-size:9px;margin-bottom:8px}.comments-list{display:grid;gap:8px}.comment-item{padding:9px;border:1px solid var(--line);border-radius:9px;background:#fff}.comment-item p{margin:0;color:var(--text);font-size:9.5px;line-height:1.5;white-space:pre-wrap}.comment-meta{display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;color:var(--muted);font-size:8px}.comment-form{display:flex;gap:7px;align-items:flex-end;margin:8px 0}.comment-form textarea{flex:1;min-height:36px;max-height:100px;padding:8px;border:1px solid rgba(16,63,50,.11);border-radius:8px;background:#fff;color:var(--text);font:inherit;font-size:9px;resize:vertical}.reply-form{margin-top:7px;padding-left:10px;border-left:2px solid var(--mint-2)}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.empty-state{padding:44px 20px;text-align:center;border:1px dashed rgba(16,63,50,.18);border-radius:15px;background:#fff;color:var(--muted)}.empty-state svg{width:38px;height:38px;color:#9aaba3}.empty-state p{margin:7px 0 0!important}
.pagination{margin-top:14px;display:flex;justify-content:center}.pagination nav{display:flex;gap:4px}.pagination a,.pagination span{min-width:30px;height:30px;display:grid;place-items:center;padding:0 7px;border:1px solid rgba(16,63,50,.09);border-radius:8px;background:#fff;color:var(--forest)!important;text-decoration:none;font-size:8.8px}.pagination .active span{background:var(--forest);border-color:var(--forest);color:#fff!important}
.composer-toggle:focus-visible,.prompt-button:focus-visible,.filter-btn:focus-visible,.action-btn:focus-visible{outline:3px solid rgba(244,200,78,.65);outline-offset:2px}
@media(max-width:950px){.community-header{grid-template-columns:1fr}.community-layout{grid-template-columns:1fr}.community-side{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.community-container{padding-top:10px}.community-header{padding:22px 18px;border-radius:16px}.community-header h1{font-size:clamp(30px,10vw,42px)}.community-intro-row{align-items:flex-start;flex-direction:column}.composer-toggle,.composer-disabled{width:100%;justify-content:center}.form-row{grid-template-columns:1fr}.community-side{grid-template-columns:1fr}.message-header{flex-direction:column}.message-badges{justify-content:flex-start}.comment-form{align-items:stretch;flex-direction:column}.comment-form .action-btn{justify-content:center}}
@media(prefers-reduced-motion:reduce){.composer-card,.message-card,.composer-toggle,.prompt-button,.filter-btn,.action-btn{animation:none;transition:none}.message-card:hover,.composer-toggle:hover,.prompt-button:hover{transform:none}}
/* Reference-aligned Community presentation; server-rendered controls remain unchanged. */
.community-container{max-width:1514px;margin:0 auto;padding:32px 42px 90px;background:transparent}
.community-header{min-height:190px;padding:29px 39px;border-radius:26px;background:linear-gradient(112deg,#f1f6ee 0%,#eef5f1 64%,#dcece3 100%);box-shadow:0 10px 28px rgba(20,63,41,.06)}
.community-eyebrow{font-size:11px;letter-spacing:2.2px}.community-header h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(36px,3.1vw,48px);font-weight:500;letter-spacing:-1.8px;line-height:1.13;margin:14px 0 10px}.community-header h1 em{color:#769783;font-weight:400;font-style:italic}.community-header p{font-size:14px;line-height:1.6}.community-safety{max-width:335px;padding:16px;border-radius:17px}.community-safety strong{font-size:13px}.community-safety p{font-size:12px;line-height:1.55}
.composer-quick{background:#fff;border:1px solid var(--line);border-radius:20px;padding:20px 23px;box-shadow:var(--shadow);margin-top:26px}.composer-top{display:flex;align-items:center;gap:13px}.small-avatar{width:43px;height:43px;flex:0 0 43px;border-radius:13px;background:#e4efe6;color:#265c48;display:grid;place-items:center;font-weight:800}.input-fake{flex:1;min-width:0;text-align:left;padding:15px 19px;border:1px solid #e2e9e3;background:#f7faf7;border-radius:12px;color:#8a9a91;font:inherit;font-size:14px;cursor:pointer;transition:background .2s,border-color .2s}.input-fake:hover{border-color:#b7d3c1;background:#f2f8f2}.composer-actions{display:flex;gap:10px;justify-content:space-between;align-items:center;margin-top:13px;padding-top:13px;border-top:1px solid #edf1ed}.quick-prompts{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.mini-label{font-size:11px;font-weight:750;color:#82948c;margin-right:2px}.text-pill{border:1px solid #e6ebe4;border-radius:8px;background:#f8faf7;color:#517264;padding:8px 11px;font:inherit;font-size:12px;font-weight:650;cursor:pointer}.text-pill:hover{background:#e9f3eb}.post-primary{border:none;background:var(--forest);color:#fff;border-radius:10px;padding:11px 16px;font:inherit;font-size:12px;font-weight:750;display:inline-flex;gap:7px;align-items:center;white-space:nowrap;box-shadow:0 7px 18px rgba(21,73,55,.13);cursor:pointer}.post-primary:hover{background:#1e624d;transform:translateY(-1px)}
.legacy-composer-heading{display:none}.community-layout{grid-template-columns:minmax(0,1fr) 326px;gap:24px;margin-top:26px}.feed-heading{margin:29px 1px 15px}.feed-heading h3{font-family:Georgia,"Times New Roman",serif;font-size:28px;font-weight:500;letter-spacing:-.8px}.feed-heading p{margin:7px 0 0;color:#81958a;font-size:12px}.feed-heading>span{color:#81958a;font-size:11px}.feed-heading .sort-controls{margin:0}.categories{display:flex;gap:7px;flex-wrap:wrap;margin:0 0 15px}.category{white-space:nowrap;border:1px solid var(--line);background:#fff;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:700;color:#647f70;text-decoration:none}.category:hover{background:#edf5ef}.category.active{background:var(--forest);border-color:var(--forest);color:#fff}.sort-controls{display:flex;align-items:center;gap:8px;justify-content:flex-end;margin:-2px 0 15px}.sort-controls label{font-size:11px;color:#7f9188;font-weight:700}.sort-controls select{border:1px solid var(--line);background:#fff;color:#31584a;border-radius:10px;padding:10px 11px;font:inherit;font-size:12px;font-weight:700}.message-card{border-radius:20px;padding:23px 24px 0;box-shadow:0 9px 25px rgba(21,63,48,.06)}.message-content{margin:18px 0 16px;padding:0;background:transparent;font-size:13px;line-height:1.72;color:#4d665c;max-width:760px}.message-title{font-size:18px;font-weight:800;letter-spacing:-.4px;margin:0 0 9px}.message-actions{padding:12px 0;border-top:1px solid #eef1ed}.community-side{gap:17px}.side-card{border-radius:20px;padding:22px;box-shadow:0 9px 25px rgba(21,63,48,.055)}.side-card h3{font-size:16px;letter-spacing:-.35px}.side-card p{font-size:12px;line-height:1.64}.card-eyebrow{font-size:10px;letter-spacing:1.6px;color:var(--emerald);font-weight:900;margin-bottom:14px}.prompt-card{background:#153f32;color:#fff;border-color:#153f32;position:relative;overflow:hidden;padding:23px}.prompt-card:after{content:"\2726";position:absolute;font-size:125px;right:-6px;top:22px;color:rgba(255,255,255,.06);transform:rotate(-14deg)}.prompt-card .card-eyebrow{color:#e3c36d}.prompt-card h3{color:#fff;font:400 26px/1.19 Georgia,serif;margin:0 0 10px;max-width:218px}.prompt-card p{color:#c2d6c8;max-width:235px}.outline-button{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.31);color:#fff;border-radius:10px;margin-top:21px;padding:11px 13px;font:inherit;font-size:12px;font-weight:800;position:relative;z-index:2;cursor:pointer}.outline-button:hover{background:rgba(255,255,255,.18)}.space-row{display:flex;align-items:center;gap:10px;padding:11px 0;width:100%;border:0;border-bottom:1px solid #eef2ed;background:none;text-align:left;color:#395b49;font-weight:750;font-size:12px;text-decoration:none}.space-row:last-child{border:0;padding-bottom:0}.space-row:hover{color:#13764c}.space-icon{height:33px;width:33px;border-radius:9px;background:#e8f3e9;display:grid;place-items:center;color:#35785b}.space-row .chev{margin-left:auto;color:#b3bdb5}.care-card{background:#f3f6ef}.care-card .care-title{display:flex;gap:9px;align-items:center;color:var(--forest);font-size:14px;font-weight:850;margin-bottom:9px}.care-card a{display:inline-block;margin-top:12px;color:#246d55;font-weight:800;font-size:12px;text-decoration:underline;text-underline-offset:3px}.guideline-list{font-size:10px}.empty-posts{border:1px solid #dceae0;background:radial-gradient(circle at 80% 20%,#ecf6ee 0,#fff 58%);text-align:left;padding:32px 34px 29px;box-shadow:var(--shadow);border-radius:20px;min-height:336px;color:#607c6f;position:relative;overflow:hidden}.empty-head{display:flex;gap:14px;align-items:center}.empty-logo{width:56px;height:56px;display:grid;place-items:center;border-radius:19px;background:#e4efe6;color:#1d7657;font-size:25px}.empty-title{font-family:Georgia,"Times New Roman",serif;font-size:29px;color:#154030;font-weight:500;margin:0}.empty-lead{max-width:600px;margin:15px 0 0;font-size:13px;line-height:1.75}.empty-chips{display:flex;gap:11px;flex-wrap:wrap;margin-top:22px}.empty-chip{min-width:165px;max-width:245px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border:1px solid #dceae0;border-radius:12px;background:#fff;color:#315b49;font:inherit;font-size:11px;font-weight:750;text-align:left;cursor:pointer;transition:transform .2s,background .2s}.empty-chip:hover{transform:translateY(-2px);background:#f5fbf6}.empty-footer{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:25px}.empty-helper{font-size:11px;color:#7d9485}.community-container button:focus-visible,.community-container a:focus-visible,.community-container select:focus-visible{outline:3px solid rgba(244,200,78,.65);outline-offset:2px}
@media(max-width:1150px){.community-container{padding:28px 26px}.community-layout{grid-template-columns:minmax(0,1fr) 275px;gap:16px}}
@media(max-width:900px){.community-header{padding:24px;min-height:168px}.community-header h1{font-size:36px}.community-safety{display:none}.community-layout{grid-template-columns:minmax(0,1fr) 280px}}
@media(max-width:730px){.community-container{padding:18px 14px 65px}.community-header{padding:22px 20px;border-radius:19px;min-height:0}.community-header h1{font-size:34px;letter-spacing:-1.3px}.community-header p{font-size:12px}.composer-quick{padding:15px;margin-top:16px}.composer-actions{align-items:stretch;flex-direction:column}.quick-prompts{width:100%}.post-primary{justify-content:center;width:100%}.community-layout{display:flex;flex-direction:column;gap:20px;margin-top:16px}.community-side{width:100%;grid-template-columns:1fr 1fr}.community-side .prompt-card{grid-column:1 / -1}.categories{flex-wrap:nowrap;overflow:auto;padding-bottom:6px;scrollbar-width:thin}.category{flex:none}.sort-controls{justify-content:space-between}.feed-heading h3{font-size:25px}.feed-heading{align-items:flex-start;flex-direction:column}.feed-heading .sort-controls{margin-top:10px;width:100%;justify-content:flex-start}.message-card{padding:17px 17px 0}.message-title{font-size:16px}.message-content{font-size:12px}.empty-posts{padding:23px 19px;min-height:0}.empty-title{font-size:24px}.empty-logo{width:47px;height:47px;border-radius:16px}.empty-chips{gap:8px}.empty-chip{min-width:140px;max-width:none;flex:1}.empty-footer{align-items:stretch;flex-direction:column}.empty-helper{font-size:10px}}
@media(max-width:440px){.community-side{display:flex;flex-direction:column}.community-side .prompt-card{width:100%}.empty-chip{min-width:100%}}
@media(prefers-reduced-motion:reduce){.post-primary,.empty-chip{transition:none}.post-primary:hover,.empty-chip:hover{transform:none}}
.community-container.is-empty-feed{padding-top:22px}.community-container.is-empty-feed .community-header{min-height:128px;padding:22px 32px;border-radius:20px;background:linear-gradient(105deg,#f5f9f5,#e7f3eb);box-shadow:none}.community-container.is-empty-feed .community-header h1{font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,sans-serif;font-size:clamp(29px,2.8vw,38px);letter-spacing:-1.3px;font-weight:790;margin:8px 0 7px}.community-container.is-empty-feed .community-header h1 em{font-style:normal;color:#769a82}.community-container.is-empty-feed .community-eyebrow{font-size:10px}.community-container.is-empty-feed .community-safety{background:#fffdf5;box-shadow:none;padding:12px 14px;max-width:290px}.community-container.is-empty-feed .community-safety p{font-size:11px}.community-container.is-empty-feed .community-layout{margin-top:18px;gap:20px}
@media(max-width:730px){.community-container.is-empty-feed{padding-top:18px}.community-container.is-empty-feed .community-header{padding:19px 18px}.community-container.is-empty-feed .community-header h1{font-size:30px}.community-container.is-empty-feed .community-safety{display:none}}
/* Circle feed interactions: the existing server-backed composer is presented as an accessible modal. */
.composer-card{width:min(680px,calc(100vw - 32px));max-width:680px;max-height:calc(100dvh - 32px);margin:auto;padding:24px 28px;border:1px solid var(--line);border-radius:24px;background:#fff;box-shadow:0 25px 90px rgba(9,35,25,.24);overflow:auto}
.composer-card:not([open]),.composer-card.is-open:not([open]){display:none}.composer-card[open]{display:block;animation:dialog-in .22s var(--ease)}.composer-card::backdrop{background:rgba(9,35,25,.49);backdrop-filter:blur(5px)}.composer-description{margin:-7px 0 20px;color:var(--muted);font-size:12px;line-height:1.5}.composer-close{position:absolute;top:16px;right:18px;width:32px;height:32px;border-radius:9px;border:1px solid var(--line);background:var(--ivory);line-height:1;color:var(--muted)}.composer-close:hover{background:var(--mint);color:var(--forest)}
@keyframes dialog-in{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}
.reply-preview{display:flex;align-items:flex-start;gap:10px;margin:-2px 0 12px;padding:10px 12px;border:1px solid #e2ece4;border-radius:12px;background:#f8fbf8}.reply-avatar{width:28px;height:28px;flex-basis:28px;border-radius:9px;font-size:10px}.reply-preview-copy{display:grid;gap:3px;min-width:0}.reply-preview-copy strong{color:var(--forest);font-size:10px}.reply-preview-copy span{color:var(--muted);font-size:9.5px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.reply-preview-link{justify-self:start;padding:0;border:0;background:none;color:var(--emerald);font:inherit;font-size:9px;font-weight:800;cursor:pointer}.reply-preview-link:hover{text-decoration:underline;text-underline-offset:3px}
.reaction-control{position:relative;display:inline-flex}.reaction-menu{position:absolute;z-index:8;left:0;bottom:calc(100% + 8px);display:grid;min-width:170px;padding:6px;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:0 14px 34px rgba(16,63,50,.15)}.reaction-menu[hidden]{display:none}.reaction-option{width:100%;padding:8px 9px;border:0;border-radius:8px;background:transparent;color:var(--text);font:inherit;font-size:9px;text-align:left;cursor:pointer}.reaction-option:hover,.reaction-option.selected{background:var(--mint);color:var(--forest)}.reaction-remove{color:#8b6d6d;border-top:1px solid #edf1ed;margin-top:4px;padding-top:9px}
@media(max-width:730px){.composer-card{width:calc(100vw - 20px);max-height:calc(100dvh - 20px);padding:21px 17px;border-radius:18px}.reply-preview{padding:9px}.reaction-menu{position:fixed;left:16px;right:16px;bottom:12px;min-width:0}}
@media(prefers-reduced-motion:reduce){.composer-card[open]{animation:none}}
/* Simple social composer and approved private media. */
.simple-post-dialog{padding:0}.simple-post-dialog[open]{display:block}.simple-post-dialog form{margin:0}.post-editor-header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 24px 16px;border-bottom:1px solid #edf2ed}.post-editor-identity{display:flex;align-items:center;gap:11px;min-width:0}.post-editor-identity strong{display:block;color:var(--forest);font-size:13px}.post-editor-identity span{display:block;margin-top:3px;color:var(--muted);font-size:10px;line-height:1.4}.simple-post-message{display:block;width:100%;min-height:190px;padding:22px 24px;border:0;background:#fff;color:var(--text);font:inherit;font-size:15px;line-height:1.65;resize:vertical;outline:none}.simple-post-message::placeholder{color:#9bad9f}.post-photo-preview{position:relative;margin:0 24px 16px;border:1px solid var(--line);border-radius:14px;background:var(--ivory);overflow:hidden}.post-photo-preview[hidden]{display:none}.post-photo-preview img{display:block;width:100%;max-height:290px;object-fit:contain;background:#f1f7f2}.remove-photo{position:absolute;right:10px;top:10px;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--forest);font:inherit;font-size:10px;font-weight:800;cursor:pointer}.community-post-error{min-height:18px;padding:0 24px;color:#a74343;font-size:10px}.post-editor-actions{display:flex;align-items:center;gap:10px;padding:15px 24px 19px;border-top:1px solid #edf2ed}.photo-picker{display:inline-flex;align-items:center;gap:6px;padding:9px 11px;border:1px solid rgba(35,107,85,.12);border-radius:9px;background:var(--mint);color:var(--emerald);font:inherit;font-size:10px;font-weight:800;cursor:pointer}.photo-picker:hover{background:var(--mint-2)}.anonymous-toggle{display:inline-flex;align-items:center;gap:7px;margin-left:auto;padding:8px 10px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--muted);font-size:10px;font-weight:750;cursor:pointer}.anonymous-toggle input{accent-color:var(--emerald)}.message-media{display:block;width:100%;max-height:430px;object-fit:cover;border-radius:12px;background:#edf5ef;margin:0 0 15px}.reaction-direct{min-width:82px}.reaction-direct[aria-pressed="true"],.reaction-direct.active{background:var(--mint);border-color:rgba(35,107,85,.24);color:var(--forest)}.reaction-heart[aria-pressed="true"],.reaction-heart.active{background:#fff0f2;border-color:#f1cbd1;color:#b84f63}.reaction-count{font-variant-numeric:tabular-nums;color:currentColor;font-size:9px;font-weight:850}.reaction-option{display:flex;align-items:center;justify-content:space-between;gap:10px}.reaction-option[data-reaction="like"],.reaction-option[data-reaction="heart"]{display:none}
@media(max-width:730px){.post-editor-header{padding:17px 17px 14px}.simple-post-message{min-height:170px;padding:18px 17px;font-size:14px}.post-photo-preview{margin:0 17px 14px}.community-post-error{padding:0 17px}.post-editor-actions{align-items:stretch;flex-wrap:wrap;padding:13px 17px 17px}.anonymous-toggle{margin-left:0}.post-editor-actions .btn-submit{margin-left:auto}.message-media{max-height:290px;border-radius:10px}.reaction-direct{min-width:0;flex:1}}
@media(prefers-reduced-motion:reduce){.message-media{transition:none}}
.my-posts-panel{margin:18px 0 22px;padding:18px 20px;border:1px solid var(--line);border-radius:18px;background:linear-gradient(140deg,#f7fbf7,#fff);box-shadow:0 8px 22px rgba(16,63,50,.045)}.my-posts-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:13px}.my-posts-heading h2{margin:4px 0 0;color:var(--forest);font-size:17px;letter-spacing:-.3px}.my-posts-heading p{margin:4px 0 0;color:var(--muted);font-size:10px;line-height:1.5}.my-posts-count{min-width:30px;height:30px;display:grid;place-items:center;border-radius:10px;background:var(--mint);color:var(--forest);font-size:11px;font-weight:850}.my-post-list{display:grid;gap:9px}.my-post-item{padding:12px 13px;border:1px solid rgba(16,63,50,.09);border-radius:12px;background:#fff}.my-post-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#8a9b92;font-size:8.5px}.my-post-status{display:inline-flex;align-items:center;min-height:23px;padding:0 8px;border-radius:999px;font-size:8px;font-weight:850}.my-post-status.status-published{background:#e8f5ed;color:#1f6a4e}.my-post-status.status-pending,.my-post-status.status-flagged{background:#fff6df;color:#80621c}.my-post-status.status-rejected{background:#fbeeee;color:#9a4c4c}.my-post-title{margin:8px 0 3px;color:var(--forest);font-size:12px;font-weight:820}.my-post-excerpt{margin:0;color:#61776b;font-size:10px;line-height:1.55;white-space:pre-wrap}.my-post-media{display:block;max-width:180px;max-height:110px;margin-top:9px;border-radius:9px;object-fit:cover}.my-post-private{margin:8px 0 0;color:#8a6f36;font-size:9px;line-height:1.45}.my-post-pagination{margin-top:11px}.my-posts-panel .pagination{margin-top:11px;justify-content:flex-end}
.visual-studio-shell{display:grid;grid-template-columns:minmax(0,1fr) 210px;gap:14px;padding:0 24px 16px;background:#fbfdfb}.visual-preview{position:relative;aspect-ratio:1/1;overflow:hidden;border-radius:15px;background:#e9f3eb;box-shadow:inset 0 0 0 1px rgba(16,63,50,.12);isolation:isolate}.visual-preview img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.visual-preview::after{content:"";position:absolute;inset:0;background:linear-gradient(145deg,rgba(10,35,25,.03),rgba(10,35,25,.2));pointer-events:none;z-index:1}.visual-preview.visual-bg-ivory{background:#f7f0df}.visual-preview.visual-bg-forest{background:#153f32}.visual-preview.visual-bg-night{background:#0f1720}.visual-preview.visual-bg-sunrise{background:#d9a96b}.visual-preview-text{position:absolute;z-index:2;left:var(--visual-x,50%);top:var(--visual-y,52%);width:max-content;max-width:82%;max-height:none;overflow:visible;transform:translate(-50%,-50%);padding:4px 7px;color:var(--visual-color,#fff);font-size:clamp(20px,4vw,45px);font-weight:700;line-height:1.18;letter-spacing:-.025em;text-align:var(--visual-align,center);text-shadow:0 3px 9px rgba(0,0,0,.34);outline:0;cursor:grab;white-space:pre-wrap;overflow-wrap:anywhere}.visual-preview-text:focus{cursor:text;box-shadow:0 0 0 2px rgba(244,200,78,.8);border-radius:6px}.visual-preview-text:active{cursor:grabbing}.visual-tools{display:grid;align-content:start;gap:11px;padding:12px;border:1px solid #e4ece5;border-radius:13px;background:#fff}.visual-tool-group{display:grid;gap:6px}.visual-tool-label{display:flex;align-items:center;justify-content:space-between;color:#5c7568;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.visual-tool-label output{color:var(--forest);font-variant-numeric:tabular-nums}.visual-backgrounds,.visual-colors,.visual-aligns{display:flex;gap:6px;flex-wrap:wrap}.visual-choice{width:25px;height:25px;padding:0;border:2px solid transparent;border-radius:8px;cursor:pointer;box-shadow:0 0 0 1px rgba(16,63,50,.12)}.visual-choice.is-selected{border-color:var(--gold);box-shadow:0 0 0 2px rgba(244,200,78,.28)}.visual-choice[data-visual-background="mint"]{background:#e9f3eb}.visual-choice[data-visual-background="ivory"]{background:#f7f0df}.visual-choice[data-visual-background="forest"]{background:#153f32}.visual-choice[data-visual-background="night"]{background:#0f1720}.visual-choice[data-visual-background="sunrise"]{background:#d9a96b}.visual-choice[data-visual-color="#ffffff"]{background:#fff}.visual-choice[data-visual-color="#153f32"]{background:#153f32}.visual-choice[data-visual-color="#f4c84e"]{background:#f4c84e}.visual-choice[data-visual-color="#f08a86"]{background:#f08a86}.visual-choice[data-visual-align]{width:30px;color:var(--forest);background:#f5f9f5;font-size:12px}.visual-choice[data-visual-align="left"]{text-align:left}.visual-choice[data-visual-align="center"]{text-align:center}.visual-choice[data-visual-align="right"]{text-align:right}.visual-photo-note,.visual-review-note{margin:0;color:#81958a;font-size:9px;line-height:1.45}.visual-review-note{padding:9px 24px 15px;background:#fbfdfb;border-top:1px solid #edf2ed}.post-mode-tabs{display:flex;gap:6px;padding:15px 24px 0;border-bottom:1px solid #edf2ed}.post-mode-tab{padding:8px 11px;border:1px solid transparent;border-radius:9px 9px 0 0;background:transparent;color:#71847a;font:inherit;font-size:10px;font-weight:800;cursor:pointer}.post-mode-tab.is-active{border-color:#dfeae1;border-bottom-color:#fff;background:#fff;color:var(--forest)}.post-mode-panel[hidden]{display:none}.visual-edit-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:6px}.my-post-edit{border:1px solid rgba(35,107,85,.18);border-radius:8px;background:#f6fbf7;color:var(--emerald);padding:6px 9px;font:inherit;font-size:9px;font-weight:800;cursor:pointer}.my-post-edit:hover{background:var(--mint)}.visual-post-transcript{margin:8px 0 12px;color:#71847a;font-size:10px;font-style:italic;line-height:1.45}.visual-post-badge{background:#eef4ff;color:#47678c;border-color:#d7e2f4}
@media(max-width:730px){.visual-studio-shell{grid-template-columns:1fr;padding:0 17px 13px}.visual-tools{grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}.visual-tool-group:first-child{grid-column:1/-1}.visual-review-note{padding:9px 17px 13px}.post-mode-tabs{padding:13px 17px 0}.visual-preview-text{font-size:clamp(20px,8vw,36px)}}
@media(prefers-reduced-motion:reduce){.visual-preview-text,.visual-choice,.my-post-edit{transition:none}}/* Circle Stream reference alignment: Community-only rail, dense timeline, discovery rail. */
.community-container{max-width:1510px;margin:0 auto;padding:18px 24px 72px;background:transparent}
.community-header,.community-intro-row,.composer-quick{display:none}
.community-layout{display:grid;grid-template-columns:210px minmax(0,1fr) 300px;gap:18px;align-items:start;margin-top:0}
.community-local-rail{position:sticky;top:94px;padding:8px 0 12px}
.community-local-brand{display:flex;align-items:center;gap:10px;padding:4px 12px 21px;border-bottom:1px solid var(--line);margin-bottom:16px}
.community-local-brand strong{display:block;color:var(--forest);font-size:15px;letter-spacing:-.3px}.community-local-brand small{display:block;color:#84988d;font-size:9px;margin-top:2px}
.community-rail-label{margin:0 12px 8px;color:#9aa9a0;font-size:9px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
.community-rail-link{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;margin:2px 0;border:0;border-radius:11px;background:transparent;color:#5f766b;text-decoration:none;font:inherit;font-size:11px;font-weight:760;text-align:left;transition:background .16s,color .16s,transform .16s}
.community-rail-link:hover,.community-rail-link:focus-visible{background:#edf5ef;color:var(--forest);transform:translateX(1px);outline:none}.community-rail-link.active{background:#e1eee5;color:var(--forest);font-weight:850}.community-rail-link.active::before{content:"";width:3px;height:19px;margin-left:-12px;border-radius:0 4px 4px 0;background:var(--gold)}.community-rail-link span:last-child{margin-left:auto;color:#91a196;font-size:10px}
.community-rail-create{display:flex;align-items:center;justify-content:center;width:calc(100% - 16px);margin:18px 8px 0;padding:11px 12px;border-radius:11px;background:var(--forest);color:#fff;text-decoration:none;font-size:11px;font-weight:850;box-shadow:0 8px 18px rgba(16,63,50,.13);transition:transform .16s,background .16s}.community-rail-create:hover{background:var(--forest-2);transform:translateY(-1px)}
.community-rail-note{margin:18px 10px 0;padding:13px;border:1px solid #e2ebe2;border-radius:14px;background:linear-gradient(145deg,#edf6f0,#f8faf5);color:#74887b;font-size:9.5px;line-height:1.55}.community-rail-note strong{display:block;color:var(--forest);font-size:10.5px;margin-bottom:4px}
.simple-post-dialog[open]{position:static;display:block;width:100%;max-width:none;margin:0 0 16px;padding:0;border:1px solid var(--line);border-radius:17px;background:#fff;box-shadow:0 8px 24px rgba(16,63,50,.045);overflow:hidden}.simple-post-dialog::backdrop{background:transparent}.simple-post-dialog .post-editor-header{padding:15px 20px 0}.simple-post-dialog .composer-close{display:none}.simple-post-dialog .post-mode-tabs{padding:11px 20px 0}.simple-post-dialog .post-mode-panel[data-text-mode]{padding:0 20px}.simple-post-dialog .simple-post-message{min-height:68px;padding:13px 0;border:0;background:transparent;font-size:15px;line-height:1.55}.simple-post-dialog .post-editor-actions{padding:11px 20px 15px;border-top:1px solid #edf2ed}.simple-post-dialog .visual-review-note{padding-left:20px;padding-right:20px}.simple-post-dialog .post-editor-identity span{font-size:10px}.simple-post-dialog .post-editor-identity strong{font-size:12px}
.community-feed{min-width:0}.feed-heading{margin:3px 1px 12px}.feed-heading h3{font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,sans-serif;font-size:20px;font-weight:820;letter-spacing:-.6px}.feed-heading p{font-size:10.5px}.categories{margin-bottom:11px;gap:6px}.category{padding:7px 9px;border-radius:9px;font-size:10px}.sort-controls{margin:-1px 0 11px}.sort-controls select{padding:8px 9px;font-size:10px}.message-card{border-radius:0;border-left:0;border-right:0;box-shadow:none;padding:19px 15px 14px}.message-card:hover{transform:none;box-shadow:none;background:#fbfdfb}.message-card:first-child{border-top:1px solid var(--line)}.message-content{font-size:13px;line-height:1.65;max-width:none}.message-actions{gap:5px}.action-btn{border:0;background:transparent;padding:6px 7px;font-size:10px}.action-btn:hover,.action-btn.liked{background:#edf5ef}.message-media{max-width:100%;border-radius:14px}.my-posts-panel{margin:0 0 16px;border-radius:15px;box-shadow:none}.my-post-item{border-radius:11px}.community-side{gap:13px}.side-card{border-radius:15px;padding:16px}.side-card h3{font-size:13px}.side-card p{font-size:10px}.community-side .spaces-card{scroll-margin-top:100px}
@media(max-width:1100px){.community-container{padding-left:16px;padding-right:16px}.community-layout{grid-template-columns:180px minmax(0,1fr)}.community-side{display:none}}
@media(max-width:720px){.community-container{padding:0 0 68px}.community-layout{display:block}.community-local-rail{position:static;display:flex;align-items:center;gap:4px;overflow-x:auto;padding:8px 12px;border-bottom:1px solid var(--line);background:#fff}.community-local-brand,.community-rail-label,.community-rail-note{display:none}.community-rail-link{width:auto;flex:0 0 auto;padding:8px 10px;margin:0;border-radius:9px;font-size:10px;white-space:nowrap}.community-rail-link.active::before{display:none}.community-rail-create{width:auto;flex:0 0 auto;margin:0;padding:8px 11px;border-radius:9px;font-size:10px;white-space:nowrap}.simple-post-dialog[open]{border-radius:0;border-left:0;border-right:0;margin-bottom:9px}.simple-post-dialog .post-editor-header{padding:13px 15px 0}.simple-post-dialog .post-mode-tabs{padding-left:15px;padding-right:15px}.simple-post-dialog .post-mode-panel[data-text-mode]{padding-left:15px;padding-right:15px}.simple-post-dialog .post-editor-actions{padding-left:15px;padding-right:15px}.feed-heading{padding:0 15px}.categories{padding:0 15px;overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none}.categories::-webkit-scrollbar{display:none}.sort-controls{padding:0 15px}.message-card{padding-left:15px;padding-right:15px}.message-header{flex-direction:row}.message-badges{justify-content:flex-end}.my-posts-panel{margin-left:15px;margin-right:15px}.pagination{padding:0 15px}}
@media(prefers-reduced-motion:reduce){.community-rail-link,.community-rail-create{transition:none}.community-rail-link:hover,.community-rail-create:hover{transform:none}}.community-resource-link{display:inline-block;margin-top:11px;color:var(--emerald);font-size:10px;font-weight:820;text-decoration:underline;text-underline-offset:3px}.community-resource-link:hover{color:var(--forest)}    .community-search{display:flex;align-items:center;gap:8px;padding:10px 13px;border:1px solid var(--line);border-radius:999px;background:#fff;color:#82968a;box-shadow:0 4px 14px rgba(17,68,53,.04)} .community-search span{font-size:17px;line-height:1} .community-search input{min-width:0;flex:1;border:0;outline:0;background:transparent;color:var(--forest);font:inherit;font-size:11px} .community-search input::placeholder{color:#9aa9a0}/* Structural correction: one Community grid owns rail, center stream, and discovery panel. */
/* Desktop scale refinement: preserve the three-column Community structure while giving the feed more presence. */
.community-container{max-width:1680px;margin:0 auto;padding:14px 18px 64px;overflow-x:clip}
.community-layout{display:grid;grid-template-columns:232px minmax(0,1fr) 332px;gap:16px;align-items:start;justify-content:center;margin:0}
.community-feed{min-width:0;overflow:hidden;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 12px 34px rgba(16,63,50,.045)}
.community-stream-header{padding:27px 32px 0;border-bottom:1px solid var(--line);background:#fff}
.community-stream-header h1{margin:0;color:var(--forest);font-size:24px;line-height:1.15;letter-spacing:-.75px}
.community-stream-header p{margin:6px 0 0;color:var(--muted);font-size:10.5px}
.community-view-tabs{display:flex;gap:30px;margin-top:21px}
.community-view-tabs a{position:relative;padding:0 0 13px;color:#82948a;text-decoration:none;font-size:11px;font-weight:820}
.community-view-tabs a.active{color:var(--forest)}
.community-view-tabs a.active::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:3px;border-radius:3px 3px 0 0;background:var(--gold)}
.community-feed>.alert{margin:16px 24px 0}
.simple-post-dialog[data-inline-composer="true"][open]{position:static;display:block;width:100%;max-width:none;margin:0;border:0;border-bottom:10px solid #f1f5f1;border-radius:0;background:#fff;box-shadow:none;overflow:hidden}
.simple-post-dialog[data-inline-composer="false"][open]{position:fixed;inset:0;display:block;width:min(860px,calc(100vw - 32px));max-width:860px;max-height:calc(100vh - 40px);margin:auto;padding:0;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(9,40,30,.24);overflow:auto}
.simple-post-dialog[data-inline-composer="false"]::backdrop{background:rgba(8,35,27,.48);backdrop-filter:blur(3px)}
.simple-post-dialog[data-inline-composer="false"] .composer-close{display:block}
.feed-heading{margin:0;padding:20px 28px 13px;align-items:center}
.feed-heading h3{font-size:13px;letter-spacing:.04em;text-transform:uppercase}
.feed-heading p{display:none}
.categories{margin:0;padding:0 28px 14px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:thin}
.messages-grid{display:block;border-top:1px solid var(--line)}
.message-card,.message-card:first-child{margin:0;padding:22px 28px 18px;border:0;border-bottom:1px solid var(--line);border-radius:0;background:#fff;box-shadow:none}
.message-card:hover{background:#fbfdfb;transform:none;box-shadow:none}
.message-media{display:block;width:100%;height:auto;max-height:none;margin:10px 0 15px;border-radius:14px;object-fit:contain;background:#edf4ef}
.visual-post-transcript{margin:8px 0 14px}
.pagination{padding:0 24px 20px}
.community-side{position:sticky;top:84px;align-self:start}
.my-posts-panel{margin:0;padding:0;border:0;border-radius:0;background:#fff;box-shadow:none}
.my-posts-heading{margin:0;padding:26px 28px 18px;border-bottom:1px solid var(--line)}
.my-posts-heading h2{font-size:19px}.my-posts-heading p{font-size:10.5px}
.my-post-filters{display:flex;gap:7px;padding:12px 28px;border-bottom:1px solid var(--line);overflow-x:auto}
.my-post-filter{flex:0 0 auto;padding:8px 11px;border:1px solid var(--line);border-radius:999px;background:#fff;color:#687e72;text-decoration:none;font-size:9.5px;font-weight:800}
.my-post-filter.active{border-color:var(--forest);background:var(--forest);color:#fff}
.my-post-list{display:block}
.my-post-item{padding:21px 28px;border:0;border-bottom:1px solid var(--line);border-radius:0;background:#fff}
.my-post-excerpt{font-size:12.5px;line-height:1.65}
.my-post-media{display:block;width:100%;max-width:none;height:auto;max-height:none;margin-top:12px;border-radius:13px;object-fit:contain;background:#edf4ef}
.my-posts-empty{display:grid;gap:5px;padding:52px 24px;text-align:center;color:var(--muted)}
.my-posts-empty strong{color:var(--forest);font-size:14px}.my-posts-empty span{font-size:10.5px}
.my-post-pagination{padding:0 24px 20px}
@media(min-width:1101px) and (max-width:1280px){.community-container{padding:16px 14px 60px}.community-layout{grid-template-columns:210px minmax(0,1fr) 300px;gap:14px}}
@media(max-width:1100px){.community-container{padding:20px 16px 60px}.community-layout{grid-template-columns:180px minmax(0,720px);justify-content:center}.community-side{display:none}}
@media(max-width:720px){.community-container{padding:0 0 64px}.community-layout{display:block}.community-feed{border-left:0;border-right:0;border-radius:0;box-shadow:none}.community-local-rail{position:sticky;top:var(--student-header-height,64px);z-index:15}.community-stream-header{padding:18px 16px 0}.community-stream-header h1{font-size:21px}.community-view-tabs{margin-top:17px}.simple-post-dialog[data-inline-composer="true"][open]{margin:0;border-radius:0}.feed-heading{padding:15px 15px 11px}.categories{padding:0 15px 11px}.message-card,.message-card:first-child{padding:18px 15px 14px}.message-header{gap:9px}.message-badges{max-width:48%}.message-media{border-radius:11px}.my-posts-heading{padding:18px 16px 14px}.my-post-filters{padding:10px 14px}.my-post-item{padding:17px 15px}.my-posts-panel{margin:0}.pagination,.my-post-pagination{padding-left:14px;padding-right:14px}}/* Full-width desktop shell: lift Community out of the shared 1440px content cap while retaining a small edge frame. */
@media(min-width:1101px){
    body.student-header-layout:has(.community-container) .content-wrapper{width:100%;max-width:none;padding:0 0 56px}
    body.student-header-layout:has(.community-container) .community-container{width:calc(100% - 32px);max-width:none;margin:0 auto;padding:14px 0 64px}
}
@media(min-width:1101px) and (max-width:1280px){
    body.student-header-layout:has(.community-container) .community-container{width:calc(100% - 28px);padding-top:14px}
}/* Visual post media sizing: keep compositions readable in the feed without constraining ordinary photos to a square. */
.message-media.visual-media,.my-post-media.visual-media{display:block;width:100%;max-width:460px;aspect-ratio:1/1;height:auto;max-height:none;margin:10px auto 15px;object-fit:contain;border-radius:14px;background:#edf4ef;overflow:hidden}
.message-media.photo-media,.my-post-media.photo-media{display:block;width:auto;max-width:100%;height:auto;max-height:min(520px,70vh);margin:10px auto 15px;object-fit:contain;border-radius:14px;background:#edf4ef}
@media(max-width:720px){.message-media.visual-media,.my-post-media.visual-media{width:100%;max-width:460px;margin:9px auto 13px;border-radius:11px}.message-media.photo-media,.my-post-media.photo-media{max-height:min(360px,70vh);margin:9px auto 13px;border-radius:11px}}/* Final visual-post proportions: compact timeline compositions and My Posts thumbnails. */
.message-media.visual-media{width:100%;max-width:460px;aspect-ratio:1/1;height:auto;max-height:none;margin:10px auto 14px;object-fit:contain}
.my-posts-panel .my-post-media.visual-media{width:min(100%,460px);max-width:460px;aspect-ratio:1/1;height:auto;margin:10px auto 14px;object-fit:contain}
@media(max-width:720px){.message-media.visual-media{width:100%;max-width:460px;margin:9px auto 12px}.my-posts-panel .my-post-media.visual-media{width:100%;max-width:460px;margin:9px auto 12px}}/* Final X-style proportions: keep the Community timeline centered, compact, and spacious. */
@media (min-width:1101px) {
    body.student-header-layout:has(.community-container) .content-wrapper {
        width: min(100%, 1230px);
        max-width: 1230px;
        margin: 0 auto;
        padding: 0 16px 56px;
    }
    body.student-header-layout:has(.community-container) .community-container {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 16px 0 64px;
    }
    .community-layout {
        grid-template-columns: 220px minmax(0, 650px) 285px;
        gap: 20px;
        justify-content: center;
    }
    .community-stream-header { padding: 20px 24px 0; }
    .feed-heading { padding: 17px 20px 11px; }
    .categories { padding-left: 20px; padding-right: 20px; }
    .message-card, .message-card:first-child { padding: 17px 20px 14px; }
    .my-posts-heading { padding: 20px 20px 15px; }
    .my-post-filters { padding-left: 20px; padding-right: 20px; }
    .my-post-item { padding: 16px 20px; }
    .community-side { top: 84px; }
}
@media (min-width:1101px) and (max-width:1280px) {
    body.student-header-layout:has(.community-container) .content-wrapper {
        width: calc(100% - 16px);
        max-width: 1230px;
        padding-left: 12px;
        padding-right: 12px;
    }
    .community-layout {
        grid-template-columns: 200px minmax(0, 1fr) 260px;
        gap: 16px;
    }
}</style>

<style>
.badge-role,.staff-identity-label{display:inline-flex;align-items:center;padding:5px 8px;border:1px solid rgba(35,107,85,.14);border-radius:999px;background:#eef5ef;color:#315e4b;font-size:9px;font-weight:800}
.post-sharing-options{display:grid;grid-template-columns:minmax(0,1fr);gap:6px;margin:0 18px 12px;padding:11px 12px;border:1px solid var(--line);border-radius:11px;background:#fbfcfa}
.community-option-label{color:#657970;font-size:9px;font-weight:820;letter-spacing:.04em}
.post-sharing-options select{width:100%;min-height:38px;padding:0 10px;border:1px solid rgba(16,63,50,.11);border-radius:9px;background:#fff;color:var(--text);font:inherit;font-size:10px}
.community-staff-guidance{color:var(--muted);font-size:9px;line-height:1.5}
.shared-resource-card{display:grid;gap:5px;margin:12px 0;padding:14px;border:1px solid rgba(35,107,85,.16);border-radius:13px;background:linear-gradient(135deg,#f3f8f3,#fff);color:var(--text);text-decoration:none}
.shared-resource-card:hover{border-color:rgba(35,107,85,.35);box-shadow:0 8px 20px rgba(16,63,50,.07)}
.shared-resource-card strong{color:var(--forest);font-size:14px}.shared-resource-card>span:not(.card-eyebrow){font-size:11px;line-height:1.55;color:var(--muted)}.shared-resource-open{color:var(--emerald)!important;font-weight:800}
.comment-controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:6px}.comment-report summary{color:var(--muted);font-size:8px;cursor:pointer}.comment-report form{display:flex;gap:5px;flex-wrap:wrap;margin-top:5px}.comment-report select{max-width:100%;padding:5px;border:1px solid var(--line);border-radius:7px;background:#fff;font:inherit;font-size:8px}
.community-search input:focus-visible,.sort-controls select:focus-visible{outline:3px solid rgba(244,200,78,.65);outline-offset:2px}
@media(max-width:720px){.sort-controls{align-items:stretch;flex-wrap:wrap}.sort-controls select{min-width:0;flex:1}.staff-identity-label{font-size:8px}.post-sharing-options{margin-left:15px;margin-right:15px}}
.community-category-icon{display:inline-block;width:15px;height:15px;flex:0 0 auto;vertical-align:middle}
.community-local-mark{display:grid;place-items:center}.community-local-mark .community-category-icon{width:18px;height:18px}
.category{display:inline-flex;align-items:center;gap:6px}
.space-icon .community-category-icon{width:16px;height:16px}
.badge-category{display:inline-flex;align-items:center;gap:5px}.badge-category .community-category-icon{width:13px;height:13px}
.photo-picker .community-category-icon{width:15px;height:15px}
.empty-logo .community-category-icon{width:25px;height:25px}
.empty-chip{justify-content:flex-start}.empty-chip .community-category-icon{width:15px;height:15px}
.care-title .community-category-icon{width:16px;height:16px}
</style>

@vite('resources/js/community-react.tsx')

{{--
    The React interface mounts only after its authenticated data request succeeds.
    This retained Blade implementation is an intentional, server-rendered fallback
    for users with JavaScript disabled or a failed asset/data load.
--}}
<div id="community-react-root" data-feed-url="{{ route('student.community.data', auth()->user()?->role === 'admin' ? ['moderation' => $moderationView ?? 'all'] : []) }}" aria-live="polite"></div>
<div id="community-legacy-fallback">
<div class="community-container {{ $communityView === 'feed' && $messages->count() === 0 ? 'is-empty-feed' : '' }}">
    <div class="community-layout">
        <aside class="community-local-rail" aria-label="Community navigation">
            <div class="community-local-brand"><span class="community-local-mark" aria-hidden="true"><x-community-category-icon name="sparkles"/></span><div><strong>Circle</strong><small>MinSU student community</small></div></div>
            <div class="community-rail-label">Your space</div>
            <a class="community-rail-link {{ $communityView === 'feed' ? 'active' : '' }}" href="{{ route('student.community', ['view' => 'feed', 'category' => 'all', 'sort' => $sortBy, 'q' => $search]) }}"><x-community-category-icon name="house"/><span>Community Feed</span></a>
            <a class="community-rail-link {{ $communityView === 'mine' ? 'active' : '' }}" href="{{ route('student.community', ['view' => 'mine', 'status' => $myPostStatus]) }}"><x-community-category-icon name="pencil"/><span>My Posts</span></a>
            <a class="community-rail-link" href="{{ route('student.community', ['view' => 'feed']) }}#community-topics"><x-community-category-icon name="circle-help"/><span>Topics</span></a>
            <div class="community-rail-label" style="margin-top:22px">Community care</div>
            <a class="community-rail-link" href="#community-guidelines"><x-community-category-icon name="shield-check"/><span>Guidelines</span></a>
            <a class="community-rail-create" href="{{ route('student.community', ['view' => 'feed']) }}#community-composer">Create Post</a>
            <div class="community-rail-note"><strong>A space to feel heard.</strong>Share at your own pace. Anonymous posts remain private from other students.</div>
        </aside>
        <main class="community-feed" aria-label="Community discussions">
            <header class="community-stream-header">
            <div><h1>Community Circle</h1><p>One shared space for students and authorized MinSU staff. Everyday text posts appear right away; only posts needing a specific safety or media review are held.</p></div>
                <nav class="community-view-tabs" aria-label="Community views">
                    <a href="{{ route('student.community', ['view' => 'feed']) }}" class="{{ $communityView === 'feed' ? 'active' : '' }}">All posts</a>
                    <a href="{{ route('student.community', ['view' => 'mine', 'status' => $myPostStatus]) }}" class="{{ $communityView === 'mine' ? 'active' : '' }}">My posts</a>
                </nav>
            </header>
    @if(session('success'))
        <div class="alert alert-success" role="status"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-warning" role="alert">Please review the highlighted details and try again.</div>
    @endif
    @if(!$communityEligible)
        <div class="alert alert-warning" role="alert">Community participation is available to active student accounts only.</div>
    @elseif($communityIsStudent && !$communityCanInteract)
        <div class="alert alert-warning" role="alert">Community participation is disabled in your privacy settings. You can continue reading moderated discussions.</div>
    @endif

    <dialog @if($communityView === 'feed') open @endif data-inline-composer="{{ $communityView === 'feed' ? 'true' : 'false' }}" class="composer-card simple-post-dialog" id="community-composer" aria-labelledby="community-composer-title" aria-describedby="community-composer-description">
        @if($communityCanInteract)
        <form action="{{ route('student.community.post') }}" method="POST" enctype="multipart/form-data" data-create-action="{{ route('student.community.post') }}" data-community-post-form data-account-role="{{ auth()->user()->role }}" data-role-label="{{ $communityRoleLabel }}">
            @csrf
            <div class="post-sharing-options">
                <label class="community-option-label" for="community-post-category">Topic</label>
                <select name="category" id="community-post-category" aria-label="Choose post topic">
                    @foreach(config('community.categories', []) as $categoryKey => $categoryDetails)
                        <option value="{{ $categoryKey }}" @selected(old('category', 'general') === $categoryKey)>{{ $categoryDetails['label'] ?? ucfirst(str_replace('-', ' ', $categoryKey)) }}</option>
                    @endforeach
                </select>
                @if(!$communityIsStudent && $shareableResources->isNotEmpty())
                    <label class="community-option-label" for="community-resource-id" data-curated-resource-label>Share an approved wellness resource (optional)</label>
                    <select name="resource_id" id="community-resource-id" data-curated-resource aria-label="Attach an active wellness resource">
                        <option value="">No resource attached</option>
                        @foreach($shareableResources as $shareableResource)
                            <option value="{{ $shareableResource->id }}">{{ $shareableResource->title }} · {{ ucfirst($shareableResource->category) }}</option>
                        @endforeach
                    </select>
                    <small class="community-staff-guidance">Use this space for general education. Do not discuss a student's private appointment, journal, or AI conversation.</small>
                @endif
            </div>
            <input type="hidden" name="post_format" id="post-format" value="{{ old('post_format', 'text') }}">
            <input type="hidden" name="visual_text" id="visual-text" value="{{ old('visual_text') }}">
            <input type="hidden" name="visual_layout" id="visual-layout" value="{{ old('visual_layout') }}">
            <input type="hidden" id="community-method" data-method-field value="">
            <div class="post-editor-header">
                <div class="post-editor-identity">
                    <div class="small-avatar" data-display-initial="{{ strtoupper(substr(optional(auth()->user())->name ?? 'S', 0, 1)) }}" aria-hidden="true">{{ strtoupper(substr(optional(auth()->user())->name ?? 'S', 0, 1)) }}</div>
                    <div><strong id="community-composer-title" data-posting-as data-display-name="{{ optional(auth()->user())->name ?? 'Community member' }}">{{ $communityIsStudent && old('is_anonymous', false) ? 'Anonymous Student' : (optional(auth()->user())->name ?? 'Community member') }}</strong><span id="community-composer-description">{{ $communityIsStudent ? 'Your post is public by default. You can explicitly choose to post anonymously.' : $communityRoleLabel.' identity is shown on every official staff post.' }}</span></div>
                </div>
                <button type="button" class="composer-close" data-composer-close aria-label="Close composer">&times;</button>
            </div>
            <div class="post-mode-tabs" role="tablist" aria-label="Post type">
                <button type="button" class="post-mode-tab is-active" data-post-mode="text" role="tab" aria-selected="true">Text post</button>
                <button type="button" class="post-mode-tab" data-post-mode="visual" role="tab" aria-selected="false">Visual studio</button>
            </div>
            <div class="post-mode-panel" data-text-mode role="tabpanel">
                <label class="sr-only" for="message">What would you like to share?</label>
                <textarea name="message" id="message" class="simple-post-message" placeholder="What would you like to share?" maxlength="2000" aria-describedby="community-post-error">{{ old('message') }}</textarea>
                <div class="post-photo-preview" data-photo-preview hidden>
                    <img src="" alt="Selected photo preview" data-photo-preview-image>
                    <button type="button" class="remove-photo" data-photo-remove>Remove photo</button>
                </div>
            </div>
            <div class="post-mode-panel" data-visual-mode role="tabpanel" hidden>
                <div class="visual-studio-shell">
                    <div class="visual-preview visual-bg-mint" data-visual-preview role="img" aria-label="Visual post preview">
                        <img src="" alt="Selected visual post photo" data-visual-preview-image hidden>
                        <div class="visual-preview-text" data-visual-text-editor contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Type your message on the canvas"></div>
                    </div>
                    <div class="visual-tools" aria-label="Visual post controls">
                        <div class="visual-tool-group">
                            <span class="visual-tool-label">Background</span>
                            <div class="visual-backgrounds">
                                <button type="button" class="visual-choice is-selected" data-visual-background="mint" aria-label="Mint background"></button>
                                <button type="button" class="visual-choice" data-visual-background="ivory" aria-label="Warm ivory background"></button>
                                <button type="button" class="visual-choice" data-visual-background="forest" aria-label="Forest background"></button>
                                <button type="button" class="visual-choice" data-visual-background="night" aria-label="Night background"></button>
                                <button type="button" class="visual-choice" data-visual-background="sunrise" aria-label="Sunrise background"></button>
                            </div>
                        </div>
                        <div class="visual-tool-group">
                            <label class="visual-tool-label" for="visual-font-size">Text size <output data-font-size-output>48</output></label>
                            <input id="visual-font-size" type="range" min="24" max="88" step="1" value="48" data-visual-font-size>
                        </div>
                        <div class="visual-tool-group">
                            <span class="visual-tool-label">Text color</span>
                            <div class="visual-colors">
                                <button type="button" class="visual-choice is-selected" data-visual-color="#ffffff" aria-label="White text"></button>
                                <button type="button" class="visual-choice" data-visual-color="#153f32" aria-label="Forest text"></button>
                                <button type="button" class="visual-choice" data-visual-color="#f4c84e" aria-label="Gold text"></button>
                                <button type="button" class="visual-choice" data-visual-color="#f08a86" aria-label="Coral text"></button>
                            </div>
                        </div>
                        <div class="visual-tool-group">
                            <span class="visual-tool-label">Align</span>
                            <div class="visual-aligns">
                                <button type="button" class="visual-choice" data-visual-align="left" aria-label="Align left">&#8592;</button>
                                <button type="button" class="visual-choice is-selected" data-visual-align="center" aria-label="Align center">&#8596;</button>
                                <button type="button" class="visual-choice" data-visual-align="right" aria-label="Align right">&#8594;</button>
                            </div>
                        </div>
                        <p class="visual-photo-note">Drag the text on the canvas. Arrow keys nudge it; Shift moves farther.</p>
                    </div>
                </div>
                <p class="visual-review-note">Visual posts are rendered on the server and held for moderator review before other students can see them.</p>
            </div>
            <div class="community-post-error" id="community-post-error" role="alert" aria-live="polite">@if($errors->has('message') || $errors->has('image') || $errors->has('visual_text') || $errors->has('visual_layout') || $errors->has('resource_id')){{ $errors->first('message') ?: ($errors->first('image') ?: ($errors->first('visual_text') ?: ($errors->first('visual_layout') ?: $errors->first('resource_id')))) }}@endif</div>
            <div class="post-editor-actions">
                <button type="button" class="photo-picker" data-photo-open><x-community-category-icon name="image-plus"/> Add photo</button>
                @if($communityIsStudent)<label class="anonymous-toggle"><input type="checkbox" name="is_anonymous" id="is_anonymous" value="1" @checked(old('is_anonymous', false))><span>Post anonymously</span></label>@else<span class="staff-identity-label">{{ $communityRoleLabel }} · public identity</span>@endif
                <button type="submit" class="btn-submit">Share post</button>
            </div>
            <input type="file" name="image" id="community-image" accept="image/jpeg,image/png,image/webp" hidden>
        </form>
        @endif
    </dialog>

            @if($communityView === 'feed')
            <div class="feed-heading"><div><h3>Conversations happening</h3><p>Find discussions where you can share, relate, or offer support.</p></div><form method="GET" action="{{ route('student.community') }}" class="sort-controls"><input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="q" value="{{ $search }}"><label for="community-sort">Show</label><select id="community-sort" name="sort" aria-label="Sort discussions">@foreach(['recent' => 'Latest posts', 'popular' => 'Most supported', 'trending' => 'Trending', 'active' => 'Recent activity', 'unanswered' => 'Unanswered'] as $sortKey => $sortLabel)<option value="{{ $sortKey }}" @selected($sortBy === $sortKey)>{{ $sortLabel }}</option>@endforeach</select><label class="sr-only" for="community-campus">Campus</label><select id="community-campus" name="campus" onchange="this.form.submit()" aria-label="Filter discussions by campus"><option value="">All campuses</option>@foreach($availableCampuses as $campusOption)<option value="{{ $campusOption }}" @selected($campusFilter === $campusOption)>{{ $campusOption }}</option>@endforeach</select><noscript><button class="action-btn" type="submit">Apply filters</button></noscript></form></div>
            <nav id="community-topics" class="categories" aria-label="Filter discussions by category">
                <a href="{{ route('student.community', ['category' => 'all', 'sort' => $sortBy, 'q' => $search, 'campus' => $campusFilter]) }}" class="category {{ $category === 'all' ? 'active' : '' }}">All discussions</a>
                @foreach($categories as $cat)<a href="{{ route('student.community', ['category' => $cat, 'sort' => $sortBy, 'q' => $search, 'campus' => $campusFilter]) }}" class="category {{ $category === $cat ? 'active' : '' }}"><x-community-category-icon :name="config('community.categories.'.$cat.'.icon', 'sparkles')"/>{{ config('community.categories.'.$cat.'.label', ucfirst(str_replace('-', ' ', $cat))) }}</a>@endforeach
            </nav>
            <div class="messages-grid">
                @forelse($messages as $message)
                    @php
                        $publicAnonymous = (bool) $message->is_anonymous && optional($message->user)->role === 'student';
                        $publicRoleLabel = match (optional($message->user)->role) {
                            'counselor' => 'Authorized Counselor',
                            'admin' => 'Guidance Admin',
                            default => null,
                        };
                    @endphp
                    <article id="community-post-{{ $message->id }}" class="message-card {{ $message->is_pinned ? 'pinned' : '' }}">
                        <div class="message-header">
                            <div class="message-author">
                                <div class="message-avatar" aria-hidden="true">{{ $publicAnonymous ? 'A' : strtoupper(substr(optional($message->user)->name ?? 'C', 0, 1)) }}</div>
                                <div class="message-info"><h4>{{ $publicAnonymous ? 'Anonymous student' : optional($message->user)->name ?? 'Community member' }}</h4><p>{{ $message->created_at->diffForHumans() }}</p></div>
                            </div>
                            <div class="message-badges">@if($message->is_pinned)<span class="badge badge-pinned">Pinned</span>@endif<span class="badge badge-category"><x-community-category-icon :name="config('community.categories.'.$message->category.'.icon', 'sparkles')"/> {{ config('community.categories.'.$message->category.'.label', ucfirst(str_replace('-', ' ', $message->category))) }}</span>@if($publicRoleLabel)<span class="badge badge-role">{{ $publicRoleLabel }}</span>@endif @if($publicAnonymous)<span class="badge badge-anonymous">Anonymous</span>@endif</div>
                        </div>
                        @if($message->post_format !== 'visual' && ($message->title || trim((string) $message->message) !== '' || $message->support_label))
                        <div class="message-content">
                            @if($message->title)
                                <h4 class="message-title">{{ $message->title }}</h4>
                            @endif
                            @if(trim((string) $message->message) !== '')
                                <div>{{ $message->message }}</div>
                            @endif
                            @if($message->support_label)
                                <span class="message-support">{{ config('community.support_labels.'.$message->support_label, $message->support_label) }}</span>
                            @endif
                        </div>
                        @endif
                        @if($message->resource && $message->resource->is_active)
                            <a class="shared-resource-card" href="{{ route('student.community.resource.show', $message->resource_id) }}">
                                <span class="card-eyebrow">CURATED WELLNESS RESOURCE · {{ ucfirst($message->resource->category) }}</span>
                                <strong>{{ $message->resource->title }}</strong>
                                <span>{{ \Illuminate\Support\Str::limit($message->resource->description, 190) }}</span>
                                <span class="shared-resource-open">Open resource <span aria-hidden="true">&rarr;</span></span>
                            </a>
                        @endif
                        @if($message->visual_media_path || $message->image_path)
                            <img class="message-media {{ $message->post_format === 'visual' ? 'visual-media' : 'photo-media' }}" src="{{ route('student.community.image', $message->id) }}" alt="Photo shared by this community member" loading="lazy">@if($message->post_format === 'visual')<p class="visual-post-transcript sr-only" aria-label="Text in this visual post">{{ $message->visual_text ?: $message->message }}</p>@endif
                        @endif
                        @php $previewReply = $message->comments()->whereNull('parent_id')->with('user:id,name,role')->oldest()->first(); $previewAnonymousOwner = $publicAnonymous && $previewReply && (int) $previewReply->user_id === (int) $message->user_id; @endphp
                        @if($previewReply)
                            <div class="reply-preview">
                                <div class="message-avatar reply-avatar" aria-hidden="true">{{ $previewAnonymousOwner ? 'A' : strtoupper(substr(optional($previewReply->user)->name ?? 'C', 0, 1)) }}</div>
                                <div class="reply-preview-copy"><strong>{{ $previewAnonymousOwner ? 'Anonymous student' : (optional($previewReply->user)->name ?? 'Community member') }}</strong>@if(!$previewAnonymousOwner && optional($previewReply->user)->role === 'counselor')<span class="badge badge-role">Authorized Counselor</span>@elseif(!$previewAnonymousOwner && optional($previewReply->user)->role === 'admin')<span class="badge badge-role">Guidance Admin</span>@endif<span>{{ \Illuminate\Support\Str::limit($previewReply->content, 160) }}</span><button type="button" class="reply-preview-link comment-toggle" data-message-id="{{ $message->id }}" aria-expanded="false" aria-controls="comments-{{ $message->id }}">View conversation &rarr;</button></div>
                            </div>
                        @endif
@php $legacyReactionCounts = ['support' => (int) ($message->support_reactions_count ?? $message->likes_count ?? 0), 'heard' => (int) ($message->heard_reactions_count ?? 0), 'helpful' => (int) ($message->helpful_reactions_count ?? 0), 'encouragement' => (int) ($message->encouragement_reactions_count ?? 0)]; @endphp
                        @php $currentReaction = optional($message->reactions->first())->reaction; @endphp
                        <div class="message-actions">
                            @if($communityCanInteract)
                                <button type="button" class="action-btn reaction-direct reaction-like {{ $currentReaction === 'like' ? 'active' : '' }}" data-message-id="{{ $message->id }}" data-reaction="like" aria-pressed="{{ $currentReaction === 'like' ? 'true' : 'false' }}"><x-community-category-icon name="thumbs-up"/> Like <span class="reaction-count" data-reaction-count="like">{{ $message->like_reactions_count ?? 0 }}</span></button>
                                <button type="button" class="action-btn reaction-direct reaction-heart {{ $currentReaction === 'heart' ? 'active' : '' }}" data-message-id="{{ $message->id }}" data-reaction="heart" aria-pressed="{{ $currentReaction === 'heart' ? 'true' : 'false' }}"><x-community-category-icon name="heart"/> Heart <span class="reaction-count" data-reaction-count="heart">{{ $message->heart_reactions_count ?? 0 }}</span></button>
                                <div class="reaction-control">
                                    <button type="button" class="action-btn reaction-trigger" data-message-id="{{ $message->id }}" aria-expanded="false" aria-haspopup="menu">More reactions</button>
                                    <div class="reaction-menu" data-reaction-menu="{{ $message->id }}" hidden role="menu" aria-label="Choose another reaction">
                                        @foreach(['support' => 'Support', 'heard' => "You're Heard", 'helpful' => 'Helpful', 'encouragement' => 'Encouragement'] as $reactionKey => $reactionLabel)
                                            <button type="button" class="reaction-option" data-message-id="{{ $message->id }}" data-reaction="{{ $reactionKey }}" role="menuitem">{{ $reactionLabel }} <span class="reaction-count" data-reaction-count="{{ $reactionKey }}">{{ $legacyReactionCounts[$reactionKey] }}</span></button>
                                        @endforeach
                                        <button type="button" class="reaction-option reaction-remove" data-message-id="{{ $message->id }}" data-reaction-remove role="menuitem">Remove reaction</button>
                                    </div>
                                </div>
                                <button type="button" class="action-btn comment-toggle" data-message-id="{{ $message->id }}" aria-expanded="false" aria-controls="comments-{{ $message->id }}">Comment ({{ $message->replies_count ?? 0 }})<span class="sr-only">View comments</span></button>
                                @if(!$publicAnonymous && (int) $message->user_id !== (int) auth()->id())<form method="POST" action="{{ route('student.community.block', $message->user_id) }}" style="display:inline-flex;">@csrf<button class="action-btn" type="submit" aria-label="Block this community member">Block</button></form>@endif
                                <form method="POST" action="{{ route('student.community.report') }}" style="display:inline-flex;align-items:center;gap:4px;">@csrf<input type="hidden" name="type" value="post"><input type="hidden" name="target_id" value="{{ $message->id }}"><select name="reason" aria-label="Report reason" style="font-size:9px;border:1px solid var(--line);border-radius:7px;padding:5px;"><option value="">Report...</option>@foreach(config('community.report_reasons', []) as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><button class="action-btn" type="submit">Send</button></form>
                            @else
                                <span class="action-btn" aria-disabled="true">Consent required to interact</span>
                            @endif
                        </div>@if($communityCanInteract)
                        <section id="comments-{{ $message->id }}" class="comments-panel" hidden aria-live="polite">
                            <div class="comments-status" data-comments-status>Comments are loaded when opened.<span class="sr-only">Loading comments...</span></div>
                            <form method="POST" action="{{ route('student.post.comment', $message->id) }}" class="comment-form" data-comment-form>@csrf<label class="sr-only" for="comment-{{ $message->id }}">Add a supportive comment</label><textarea id="comment-{{ $message->id }}" name="content" maxlength="1000" required placeholder="Add a supportive comment..."></textarea><input type="hidden" name="parent_id" value=""><button type="submit" class="action-btn">Comment</button></form>
                            <div class="comments-list" data-comments-list></div>
                        </section>
                        @endif
                    </article>
                @empty
                    <section class="empty-posts" aria-labelledby="emptyCommunityHeading">
                        <div class="empty-head"><div class="empty-logo" aria-hidden="true"><x-community-category-icon name="sparkles"/></div><div><div class="card-eyebrow">YOUR COMMUNITY STARTS HERE</div><h3 class="empty-title" id="emptyCommunityHeading">A conversation starts with you.</h3></div></div>
                        <p class="empty-lead">There are no published discussions in this view yet. Ask a question, share something small, or offer a bit of encouragement. You do not need the perfect words to begin.</p>
                        @if($communityCanInteract)
                        <div class="empty-chips" aria-label="Conversation starters"><button type="button" class="empty-chip" data-empty-prompt="Has anyone else been dealing with...?"> <x-community-category-icon name="circle-help"/> Ask a question <span aria-hidden="true">-&gt;</span></button><button type="button" class="empty-chip" data-empty-prompt="A small win I want to celebrate is..."><x-community-category-icon name="sprout"/> Share a small win <span aria-hidden="true">-&gt;</span></button><button type="button" class="empty-chip" data-empty-prompt="One kind thing I would like to share is..."><x-community-category-icon name="lightbulb"/> Offer encouragement <span aria-hidden="true">-&gt;</span></button></div>
                        <div class="empty-footer"><button type="button" class="post-primary" data-composer-open aria-controls="community-composer" aria-expanded="false">Start the first conversation <span aria-hidden="true">-&gt;</span></button><span class="empty-helper">{{ $communityIsStudent ? 'Share at your own pace · choose anonymous only when you want to.' : 'Official staff accounts post with their public role identity.' }}</span></div>
                        @else
                        <div class="empty-footer"><span class="empty-helper">Community participation requires your current privacy consent.</span></div>
                        @endif
                    </section>
                @endforelse
            </div>
            @if($messages->hasPages())<div class="pagination">{{ $messages->links() }}</div>@endif

            @else
                <section class="my-posts-panel" aria-labelledby="my-posts-heading">
                    <div class="my-posts-heading">
                        <div><span class="card-eyebrow">MY POSTS</span><h2 id="my-posts-heading">Your Community posts</h2><p>Published and private review statuses are visible only to you.</p></div>
                        <span class="my-posts-count" aria-label="{{ $myMessages->total() }} posts">{{ $myMessages->total() }}</span>
                    </div>
                    <nav class="my-post-filters" aria-label="Filter your posts by status">
                        @foreach(['all' => 'All', 'published' => 'Published', 'pending' => 'In Review', 'flagged' => 'Flagged / Removed'] as $statusKey => $statusLabel)
                            <a href="{{ route('student.community', ['view' => 'mine', 'status' => $statusKey]) }}" class="my-post-filter {{ $myPostStatus === $statusKey ? 'active' : '' }}">{{ $statusLabel }}</a>
                        @endforeach
                    </nav>
                    <div class="my-post-list">
                        @forelse($myMessages as $myPost)
                            @php
                                $myStatus = $myPost->is_approved ? 'published' : (($myPost->moderation_status === 'rejected') ? 'rejected' : (($myPost->moderation_status === 'flagged') ? 'flagged' : 'pending'));
                                $myStatusLabel = ['published' => 'Published', 'pending' => 'In review', 'flagged' => 'Safety review', 'rejected' => 'Rejected or removed'][$myStatus];
                                $editPayload = base64_encode(json_encode([
                                    'id' => (int) $myPost->id,
                                    'text' => (string) ($myPost->visual_text ?? $myPost->message),
                                    'layout' => $myPost->visual_layout ?? [],
                                    'image_url' => $myPost->image_path ? route('student.community.image', $myPost->id) . '?source=1' : null,
                                    'anonymous' => (bool) $myPost->is_anonymous,
                                ], JSON_UNESCAPED_SLASHES));
                            @endphp
                            <article class="my-post-item" id="my-post-{{ $myPost->id }}">
                                <div class="my-post-meta"><span class="my-post-status status-{{ $myStatus }}">{{ $myStatusLabel }}</span><time datetime="{{ optional($myPost->created_at)->toIso8601String() }}">{{ optional($myPost->created_at)->diffForHumans() }}</time></div>
                                @if($myPost->post_format === 'visual')<div class="visual-edit-actions"><span class="badge visual-post-badge">Visual post</span><button type="button" class="my-post-edit" title="Edit and re-render this visual with the current safe text layout" aria-label="Edit and re-render visual post" data-edit-visual="{{ $editPayload }}">Edit &amp; re-render</button></div>@endif
                                @if($myPost->title)<h3 class="my-post-title">{{ $myPost->title }}</h3>@endif
                                @if(trim((string) $myPost->message) !== '')<p class="my-post-excerpt {{ $myPost->post_format === 'visual' ? 'sr-only' : '' }}" aria-label="{{ $myPost->post_format === 'visual' ? 'Text in this visual post' : 'Post text' }}">{{ $myPost->message }}</p>@endif
                                @if($myPost->visual_media_path || $myPost->image_path)<img class="my-post-media {{ $myPost->post_format === 'visual' ? 'visual-media' : 'photo-media' }}" src="{{ route('student.community.image', $myPost->id) }}" alt="Your shared photo" loading="lazy">@endif
                                @if(in_array($myStatus, ['pending', 'flagged'], true))<p class="my-post-private">Only you and authorized moderators can see this post until review.</p>@elseif($myStatus === 'rejected')<p class="my-post-private">This post is not visible in the Community feed.</p>@endif
                            </article>
                        @empty
                            <div class="my-posts-empty"><strong>No posts in this view.</strong><span>Your posts will appear here with their current moderation status.</span></div>
                        @endforelse
                    </div>
                    @if($myMessages->hasPages())<div class="my-post-pagination">{{ $myMessages->links() }}</div>@endif
                </section>
            @endif
        </main>

        <aside class="community-side">
            <form class="community-search" method="GET" action="{{ route('student.community') }}" role="search">
                <span aria-hidden="true">&#8981;</span>
                <label class="sr-only" for="community-search-input">Search discussions</label>
                <input id="community-search-input" type="search" name="q" value="{{ $search }}" placeholder="Search discussions" autocomplete="off">
                <input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="sort" value="{{ $sortBy }}"><input type="hidden" name="campus" value="{{ $campusFilter }}">
            </form>
            <section class="side-card prompt-card">
                <div class="card-eyebrow">TODAY'S CONVERSATION STARTER</div><h3>Conversation starters</h3><p>What made this week a little better? Begin wherever feels comfortable.</p>
                @if($communityCanInteract)<button type="button" class="outline-button" data-composer-prompt="A small thing that made this week a little better was...">Share a thought <span aria-hidden="true">-&gt;</span></button>@else<p class="side-muted">Enable Community participation in privacy settings to start a conversation.</p>@endif
            </section>
            @php $popularTopics = collect($stats['by_category'] ?? [])->sortDesc()->filter(fn ($count) => (int) $count > 0)->take(4); @endphp
            @if($popularTopics->isNotEmpty())
            <section class="side-card spaces-card"><div class="card-eyebrow">POPULAR TOPICS</div><h3>Active conversations</h3>
                @foreach($popularTopics as $cat => $count)<a class="space-row" href="{{ route('student.community', ['category' => $cat, 'sort' => $sortBy, 'q' => $search, 'campus' => $campusFilter]) }}"><span class="space-icon"><x-community-category-icon :name="config('community.categories.'.$cat.'.icon', 'sparkles')"/></span><span>{{ config('community.categories.'.$cat.'.label', ucfirst(str_replace('-', ' ', $cat))) }}</span><span class="chev" aria-label="{{ $count }} published posts">{{ $count }}</span></a>@endforeach
            </section>
            @endif
            @if($guidanceAnnouncements->isNotEmpty())
            <section class="side-card spaces-card"><div class="card-eyebrow">GUIDANCE ANNOUNCEMENTS</div><h3>From authorized staff</h3>
                @foreach($guidanceAnnouncements as $announcement)
                    <a class="space-row" href="{{ route('student.community', ['category' => 'all']) }}#community-post-{{ $announcement->id }}"><span class="space-icon" aria-hidden="true"><x-community-category-icon :name="$announcement->user?->role === 'counselor' ? 'heart' : 'shield-check'"/></span><span><strong>{{ $announcement->user?->role === 'counselor' ? 'Counselor' : 'Guidance Admin' }}</strong><small>{{ \Illuminate\Support\Str::limit($announcement->title ?: $announcement->message, 72) }}</small></span><span class="chev" aria-hidden="true">&rarr;</span></a>
                @endforeach
            </section>
            @endif
            <section class="side-card spaces-card"><div class="card-eyebrow">EXPLORE SPACES</div><h3>Find a conversation that fits.</h3>
                @foreach($categories as $cat)<a class="space-row" href="{{ route('student.community', ['category' => $cat, 'sort' => $sortBy, 'q' => $search, 'campus' => $campusFilter]) }}"><span class="space-icon"><x-community-category-icon :name="config('community.categories.'.$cat.'.icon', 'sparkles')"/></span><span>{{ config('community.categories.'.$cat.'.label', ucfirst(str_replace('-', ' ', $cat))) }}</span><span class="chev" aria-hidden="true">-&gt;</span></a>@endforeach
            </section>
            <section class="side-card care-card"><div class="care-title"><x-community-category-icon name="shield-check"/> Respect is part of the space</div><p>Posts are visible to eligible Community members. Anonymous posting hides your name publicly, but not from authorized moderation.</p><a href="#community-guidelines">Community guidelines -&gt;</a><ul class="guideline-list" id="community-guidelines"><li>Share experiences, not medical advice.</li><li>Protect your privacy and others' privacy.</li><li>Report harassment or unsafe content.</li></ul><a class="community-resource-link" href="{{ route('student.resources') }}">Explore wellness resources &rarr;</a></section>
        </aside>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const composer = document.getElementById('community-composer');
    const form = composer?.querySelector('[data-community-post-form]');
    const studentComposer = form?.dataset.accountRole === 'student';
    const inlineComposer = composer?.dataset.inlineComposer === 'true';
    const openers = [...document.querySelectorAll('[data-composer-open]')];
    const photoOpeners = [...document.querySelectorAll('[data-composer-photo]')];
    const anonymousOpeners = [...document.querySelectorAll('[data-composer-anonymous]')];
    const visualOpeners = [...document.querySelectorAll('[data-composer-visual]')];
    const closeButton = composer?.querySelector('[data-composer-close]');
    const message = document.getElementById('message');
    const imageInput = document.getElementById('community-image');
    const preview = composer?.querySelector('[data-photo-preview]');
    const previewImage = composer?.querySelector('[data-photo-preview-image]');
    const visualPreview = composer?.querySelector('[data-visual-preview]');
    const visualPreviewImage = composer?.querySelector('[data-visual-preview-image]');
    const visualEditor = composer?.querySelector('[data-visual-text-editor]');
    const visualTextInput = document.getElementById('visual-text');
    const visualLayoutInput = document.getElementById('visual-layout');
    const postFormat = document.getElementById('post-format');
    const methodField = document.getElementById('community-method');
    const removePhoto = composer?.querySelector('[data-photo-remove]');
    const anonymous = document.getElementById('is_anonymous');
    const error = document.getElementById('community-post-error');
    const fontSize = composer?.querySelector('[data-visual-font-size]');
    const fontOutput = composer?.querySelector('[data-font-size-output]');
    let restoreFocus = null;
    let previousBodyOverflow = '';
    let objectUrl = null;
    let editingPostId = null;
    let layout = {background:'mint', x:50, y:52, font_size:48, color:'#ffffff', align:'center'};
    let drag = null;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, Number(value) || min));
    const syncAnonymous = () => {
        const isAnonymous = studentComposer && Boolean(anonymous?.checked);
        const identity = composer?.querySelector('[data-posting-as]');
        const description = document.getElementById('community-composer-description');
        const avatar = composer?.querySelector('.post-editor-identity .small-avatar');
        if (identity) identity.textContent = isAnonymous ? 'Anonymous Student' : (identity.dataset.displayName || 'Community member');
        if (description) description.textContent = studentComposer
            ? (isAnonymous ? 'Your name is hidden from other students.' : 'Your post is public by default; staff can see the account behind every post.')
            : `${form?.dataset.roleLabel || 'Authorized staff'} identity is always public.`;
        if (avatar) avatar.textContent = isAnonymous ? 'A' : (avatar.dataset.displayInitial || 'S');
    };
    const syncVisualText = () => {
        const text = (visualEditor?.textContent || '').replace(/\u00a0/g, ' ').trim();
        if (visualTextInput) visualTextInput.value = text;
        return text;
    };
    const fitVisualText = () => {
        if (!visualPreview || !visualEditor) return;
        const maxHeight = visualPreview.clientHeight * .82;
        let size = clamp(layout.font_size, 10, 88);
        visualEditor.style.maxHeight = 'none';
        visualEditor.style.overflow = 'visible';
        visualEditor.style.fontSize = `${size}px`;
        for (let i = 0; i < 80 && size > 10; i += 1) {
            const bounds = visualEditor.getBoundingClientRect();
            if (bounds.height <= maxHeight + 1) break;
            size -= 1;
            visualEditor.style.fontSize = `${size}px`;
        }
        if (size !== layout.font_size) layout.font_size = size;
    };
    const clampVisualPosition = () => {
        if (!visualPreview || !visualEditor) return;
        const canvas = visualPreview.getBoundingClientRect();
        const textBounds = visualEditor.getBoundingClientRect();
        if (!canvas.width || !canvas.height || !textBounds.width || !textBounds.height) return;
        const halfWidth = (textBounds.width / canvas.width) * 50;
        const halfHeight = (textBounds.height / canvas.height) * 50;
        const minX = clamp(halfWidth, 8, 50);
        const maxX = 100 - minX;
        const minY = clamp(halfHeight, 12, 50);
        const maxY = 100 - minY;
        const nextX = clamp(layout.x, minX, maxX);
        const nextY = clamp(layout.y, minY, maxY);
        if (nextX !== layout.x || nextY !== layout.y) {
            layout.x = Number(nextX.toFixed(2));
            layout.y = Number(nextY.toFixed(2));
            visualPreview.style.setProperty('--visual-x', `${layout.x}%`);
            visualPreview.style.setProperty('--visual-y', `${layout.y}%`);
            if (visualLayoutInput) visualLayoutInput.value = JSON.stringify(layout);
        }
    };
    const syncLayout = () => {
        if (!visualPreview || !visualEditor) return;
        visualPreview.className = `visual-preview visual-bg-${layout.background}`;
        visualPreview.style.setProperty('--visual-x', `${layout.x}%`);
        visualPreview.style.setProperty('--visual-y', `${layout.y}%`);
            if (visualLayoutInput) visualLayoutInput.value = JSON.stringify(layout);
        visualPreview.style.setProperty('--visual-color', layout.color);
        visualPreview.style.setProperty('--visual-align', layout.align);
        visualEditor.style.fontSize = `${layout.font_size}px`;
        fitVisualText();
        if (fontSize) fontSize.value = String(layout.font_size);
        if (fontOutput) fontOutput.value = String(layout.font_size);
        if (visualLayoutInput) visualLayoutInput.value = JSON.stringify(layout);
        composer?.querySelectorAll('[data-visual-background]').forEach(button => button.classList.toggle('is-selected', button.dataset.visualBackground === layout.background));
        composer?.querySelectorAll('[data-visual-color]').forEach(button => button.classList.toggle('is-selected', button.dataset.visualColor === layout.color));
        composer?.querySelectorAll('[data-visual-align]').forEach(button => button.classList.toggle('is-selected', button.dataset.visualAlign === layout.align));
        window.requestAnimationFrame(clampVisualPosition);
    };
    const applyLayout = next => {
        layout = {
            background: ['mint','ivory','forest','night','sunrise'].includes(next?.background) ? next.background : 'mint',
            x: clamp(next?.x ?? 50, 8, 92), y: clamp(next?.y ?? 52, 12, 88),
            font_size: clamp(next?.font_size ?? 48, 24, 88),
            color: /^#[0-9a-f]{6}$/i.test(next?.color || '') ? next.color.toLowerCase() : '#ffffff',
            align: ['left','center','right'].includes(next?.align) ? next.align : 'center',
        };
        syncLayout();
    };
    const clearPhoto = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        if (imageInput) imageInput.value = '';
        if (previewImage) previewImage.removeAttribute('src');
        if (visualPreviewImage) { visualPreviewImage.removeAttribute('src'); visualPreviewImage.hidden = true; }
        if (preview) preview.hidden = true;
    };
    const showPhoto = file => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            clearPhoto();
            if (error) error.textContent = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
            return;
        }
        if (error) error.textContent = '';
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        if (previewImage) previewImage.src = objectUrl;
        if (visualPreviewImage) { visualPreviewImage.src = objectUrl; visualPreviewImage.hidden = false; }
        if (preview) preview.hidden = false;
    };
    const setMode = mode => {
        const visual = mode === 'visual';
        if (postFormat) postFormat.value = visual ? 'visual' : 'text';
        composer?.querySelector('[data-text-mode]')?.toggleAttribute('hidden', visual);
        composer?.querySelector('[data-visual-mode]')?.toggleAttribute('hidden', !visual);
        composer?.querySelectorAll('[data-post-mode]').forEach(button => {
            const active = button.dataset.postMode === mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (message) message.disabled = visual;
        const curatedResource = document.querySelector('[data-curated-resource]');
        const curatedResourceLabel = document.querySelector('[data-curated-resource-label]');
        if (visual && curatedResource) curatedResource.value = '';
        if (curatedResource) curatedResource.hidden = visual;
        if (curatedResourceLabel) curatedResourceLabel.hidden = visual;
        if (visual) { syncVisualText(); syncLayout(); visualEditor?.focus(); }
        else message?.focus();
    };
    const setOpen = (open, opener = null, options = {}) => {
        if (!composer) return;
        if (open) {
            restoreFocus = opener || document.activeElement;
            if (typeof composer.showModal === 'function') { if (!composer.open) composer.showModal(); }
            else composer.setAttribute('open', '');
            composer.classList.add('is-open');
            if (!inlineComposer) {
                previousBodyOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                openers.forEach(button => button.setAttribute('aria-expanded', 'true'));
            }
            if (options.anonymous && anonymous) { anonymous.checked = true; syncAnonymous(); }
            if (options.mode) setMode(options.mode);
            if (options.photo) window.setTimeout(() => imageInput?.click(), 50);
            window.setTimeout(() => (options.mode === 'visual' ? visualEditor : message)?.focus(), 80);
            if (inlineComposer) composer.scrollIntoView({behavior:'smooth', block:'start'});
            return;
        }
        if (inlineComposer) { composer.scrollIntoView({behavior:'smooth', block:'start'}); return; }
        if (composer.open) composer.close(); else composer.removeAttribute('open');
        composer.classList.remove('is-open');
        openers.forEach(button => button.setAttribute('aria-expanded', 'false'));
        document.body.style.overflow = previousBodyOverflow;
        restoreFocus?.focus?.(); restoreFocus = null;
    };
    const openWith = (button, options = {}) => setOpen(true, button, options);
    const resetForNewPost = () => {
        editingPostId = null;
        if (anonymous) anonymous.checked = false;
        const curatedResource = document.querySelector('[data-curated-resource]');
        if (curatedResource) curatedResource.value = '';
        if (form) form.action = form.dataset.createAction || form.action.split('/').slice(0, -1).join('/');
        if (methodField) { methodField.removeAttribute('name'); methodField.value = ''; }
        if (form?.querySelector('.btn-submit')) form.querySelector('.btn-submit').textContent = 'Share post';
        if (message) { message.value = ''; message.disabled = false; }
        if (visualEditor) visualEditor.textContent = '';
        if (visualTextInput) visualTextInput.value = '';
        applyLayout({}); clearPhoto(); setMode('text'); syncAnonymous();
    };
    openers.forEach(button => button.addEventListener('click', () => { if (!editingPostId) resetForNewPost(); openWith(button); }));
    photoOpeners.forEach(button => button.addEventListener('click', () => { if (!editingPostId) resetForNewPost(); openWith(button, {photo:true}); }));
    anonymousOpeners.forEach(button => button.addEventListener('click', () => { if (!editingPostId) resetForNewPost(); openWith(button, {anonymous:true}); }));
    visualOpeners.forEach(button => button.addEventListener('click', () => { if (!editingPostId) resetForNewPost(); openWith(button, {mode:'visual'}); }));
    composer?.querySelectorAll('[data-post-mode]').forEach(button => button.addEventListener('click', () => setMode(button.dataset.postMode)));
    closeButton?.addEventListener('click', () => setOpen(false));
    composer?.addEventListener('cancel', event => { event.preventDefault(); setOpen(false); });
    composer?.addEventListener('close', () => { if (document.body.style.overflow === 'hidden') document.body.style.overflow = previousBodyOverflow; });
    anonymous?.addEventListener('change', syncAnonymous);
    imageInput?.addEventListener('change', event => showPhoto(event.target.files?.[0]));
    removePhoto?.addEventListener('click', clearPhoto);
    fontSize?.addEventListener('input', event => { layout.font_size = clamp(event.target.value, 24, 88); syncLayout(); });
    composer?.querySelectorAll('[data-visual-background]').forEach(button => button.addEventListener('click', () => { layout.background = button.dataset.visualBackground; syncLayout(); }));
    composer?.querySelectorAll('[data-visual-color]').forEach(button => button.addEventListener('click', () => { layout.color = button.dataset.visualColor; syncLayout(); }));
    composer?.querySelectorAll('[data-visual-align]').forEach(button => button.addEventListener('click', () => { layout.align = button.dataset.visualAlign; syncLayout(); }));
    visualEditor?.addEventListener('input', () => { syncVisualText(); syncLayout(); });
    visualEditor?.addEventListener('keydown', event => {
        if (!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const step = event.shiftKey ? 5 : 1;
        if (event.key === 'ArrowLeft') layout.x -= step;
        if (event.key === 'ArrowRight') layout.x += step;
        if (event.key === 'ArrowUp') layout.y -= step;
        if (event.key === 'ArrowDown') layout.y += step;
        applyLayout(layout);
    });
    visualEditor?.addEventListener('pointerdown', event => {
        if (event.button !== 0 || !visualPreview) return;
        const bounds = visualPreview.getBoundingClientRect();
        drag = {startX:event.clientX, startY:event.clientY, x:layout.x, y:layout.y, bounds};
        visualEditor.setPointerCapture?.(event.pointerId);
    });
    visualEditor?.addEventListener('pointermove', event => {
        if (!drag) return;
        const movedX = event.clientX - drag.startX;
        const movedY = event.clientY - drag.startY;
        if (Math.abs(movedX) < 2 && Math.abs(movedY) < 2) return;
        applyLayout({ ...layout, x: drag.x + (movedX / drag.bounds.width) * 100, y: drag.y + (movedY / drag.bounds.height) * 100 });
    });
    visualEditor?.addEventListener('pointerup', () => { drag = null; });
    visualEditor?.addEventListener('pointercancel', () => { drag = null; });
    document.querySelectorAll('[data-composer-prompt],[data-empty-prompt]').forEach(button => button.addEventListener('click', () => {
        if (!editingPostId) resetForNewPost();
        const prompt = button.dataset.composerPrompt || button.dataset.emptyPrompt || '';
        openWith(button); if (message && !message.value.trim()) message.value = prompt;
    }));
    document.querySelectorAll('[data-edit-visual]').forEach(button => button.addEventListener('click', () => {
        try {
            const payload = JSON.parse(atob(button.dataset.editVisual));
            editingPostId = Number(payload.id);
            if (form) form.action = `/student/community/${editingPostId}`;
            if (methodField) { methodField.name = '_method'; methodField.value = 'PUT'; }
            if (form?.querySelector('.btn-submit')) form.querySelector('.btn-submit').textContent = 'Save visual post';
            if (message) { message.value = ''; message.disabled = true; }
            if (visualEditor) visualEditor.textContent = payload.text || '';
             if (visualTextInput) visualTextInput.value = payload.text || '';
             if (anonymous) { anonymous.checked = payload.anonymous !== false; syncAnonymous(); }
             applyLayout(payload.layout || {});
            if (payload.image_url && visualPreviewImage) { visualPreviewImage.src = payload.image_url; visualPreviewImage.hidden = false; }
            setMode('visual'); openWith(button, {mode:'visual'});
        } catch (e) { if (error) error.textContent = 'This visual post could not be opened for editing.'; }
    }));
    syncAnonymous();
    let initialLayout = {};
    try { initialLayout = JSON.parse(visualLayoutInput?.value || '{}'); } catch (e) { initialLayout = {}; }
    applyLayout(initialLayout);
    if (postFormat?.value === 'visual' || visualTextInput?.value) setMode('visual');
    if (composer?.classList.contains('is-open')) setOpen(true, null, {mode: postFormat?.value === 'visual' ? 'visual' : 'text'});
    form?.addEventListener('submit', event => {
        const visual = postFormat?.value === 'visual';
        const hasText = visual ? syncVisualText() : message?.value.trim();
        const hasImage = imageInput?.files?.length;
        if (!hasText && !hasImage) {
            event.preventDefault(); if (error) error.textContent = visual ? 'Type a message on the canvas before posting.' : 'Write something or attach a photo before posting.';
            (visual ? visualEditor : message)?.focus(); return;
        }
        if (visual && visualLayoutInput) visualLayoutInput.value = JSON.stringify(layout);
        const submit = form.querySelector('button[type="submit"]');
        if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); submit.textContent = 'Publishing...'; }
    });
});
</script>

<script>
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function appendReplyForm(container, postId, parentId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/student/post/${postId}/comment`;
    form.className = 'comment-form reply-form';
    form.dataset.commentForm = 'true';
    const token = document.createElement('input');
    token.type = 'hidden'; token.name = '_token'; token.value = csrfToken();
    const parent = document.createElement('input');
    parent.type = 'hidden'; parent.name = 'parent_id'; parent.value = String(parentId);
    const input = document.createElement('textarea');
    input.name = 'content'; input.maxLength = 1000; input.required = true; input.placeholder = 'Reply supportively...';
    const button = document.createElement('button');
    button.type = 'submit'; button.className = 'action-btn'; button.textContent = 'Reply';
    form.append(token, parent, input, button);
    container.appendChild(form);
}

function commentNode(comment, postId, allowReply) {
    const item = document.createElement('article');
    item.className = 'comment-item';
    const meta = document.createElement('div');
    meta.className = 'comment-meta';
    const author = document.createElement('span');
    author.textContent = comment.author?.name || 'Community member';
    if (comment.author?.role_label) {
        const role = document.createElement('span');
        role.className = 'badge-role';
        role.textContent = comment.author.role_label;
        meta.append(author, role);
    } else {
        meta.append(author);
    }
    const date = document.createElement('time');
    date.textContent = comment.created_at || '';
    meta.append(date);
    const body = document.createElement('p');
    body.textContent = comment.content || '';
    item.append(meta, body);
    const controls = document.createElement('div');
    controls.className = 'comment-controls';
    if (comment.can_edit) {
        const edit = document.createElement('button');
        edit.type = 'button'; edit.className = 'action-btn'; edit.textContent = 'Edit';
        edit.addEventListener('click', () => {
            const form = document.createElement('form');
            form.method = 'POST'; form.action = `/student/comment/${comment.id}`; form.className = 'comment-form';
            const token = document.createElement('input'); token.type = 'hidden'; token.name = '_token'; token.value = csrfToken();
            const method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; method.value = 'PUT';
            const input = document.createElement('textarea'); input.name = 'content'; input.maxLength = 1000; input.required = true; input.value = body.textContent;
            const save = document.createElement('button'); save.type = 'submit'; save.className = 'action-btn'; save.textContent = 'Save';
            form.append(token, method, input, save); item.replaceChild(form, body); input.focus();
        });
        controls.appendChild(edit);
    }
    if (comment.can_delete) {
        const remove = document.createElement('form'); remove.method = 'POST'; remove.action = `/student/comment/${comment.id}`;
        const token = document.createElement('input'); token.type = 'hidden'; token.name = '_token'; token.value = csrfToken();
        const method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
        const button = document.createElement('button'); button.type = 'submit'; button.className = 'action-btn'; button.textContent = 'Delete';
        remove.append(token, method, button); controls.appendChild(remove);
    }
    const report = document.createElement('details');
    report.className = 'comment-report';
    const reportLabel = document.createElement('summary'); reportLabel.textContent = 'Report comment';
    const reportForm = document.createElement('form'); reportForm.method = 'POST'; reportForm.action = '/student/community/report';
    const reportToken = document.createElement('input'); reportToken.type = 'hidden'; reportToken.name = '_token'; reportToken.value = csrfToken();
    const reportType = document.createElement('input'); reportType.type = 'hidden'; reportType.name = 'type'; reportType.value = 'comment';
    const reportTarget = document.createElement('input'); reportTarget.type = 'hidden'; reportTarget.name = 'target_id'; reportTarget.value = String(comment.id);
    const reasons = document.createElement('select'); reasons.name = 'reason'; reasons.required = true; reasons.setAttribute('aria-label', 'Reason for reporting comment');
    [['', 'Choose a reason'], ['harassment', 'Harassment'], ['discrimination', 'Discrimination'], ['harmful', 'Harmful content'], ['privacy', 'Privacy concern'], ['spam', 'Spam'], ['other', 'Other']].forEach(([value, label]) => { const option = document.createElement('option'); option.value = value; option.textContent = label; reasons.appendChild(option); });
    const send = document.createElement('button'); send.type = 'submit'; send.className = 'action-btn'; send.textContent = 'Send report';
    reportForm.append(reportToken, reportType, reportTarget, reasons, send); report.append(reportLabel, reportForm); controls.appendChild(report);
    if (controls.childElementCount) item.appendChild(controls);
    if (allowReply) appendReplyForm(item, postId, comment.id);
    (comment.replies || []).forEach(reply => item.appendChild(commentNode(reply, postId, false)));
    return item;
}

async function loadComments(toggle) {
    const postId = toggle.dataset.messageId;
    const panel = document.getElementById(`comments-${postId}`);
    const status = panel.querySelector('[data-comments-status]');
    const list = panel.querySelector('[data-comments-list]');
    toggle.disabled = true;
    status.textContent = 'Loading comments...';
    try {
        const response = await fetch(`/student/post/${postId}/comments`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error('Unable to load comments.');
        const payload = await response.json();
        list.replaceChildren();
        const comments = payload.comments?.data || [];
        comments.forEach(comment => list.appendChild(commentNode(comment, postId, true)));
        status.textContent = comments.length ? `${comments.length} comment thread${comments.length === 1 ? '' : 's'}.` : 'No comments yet.';
        panel.dataset.commentsLoaded = 'true';
    } catch (error) {
        status.textContent = 'Comments could not be loaded. Please try again.';
    } finally {
        toggle.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.comment-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const panel = document.getElementById(`comments-${toggle.dataset.messageId}`);
            const opening = panel.hidden;
            panel.hidden = !opening;
            toggle.setAttribute('aria-expanded', String(opening));
            if (opening && panel.dataset.commentsLoaded !== 'true') loadComments(toggle);
        });
    });
    document.querySelectorAll('form[action*="/community/post"], form[data-comment-form], form[action*="/community/report"], form[action*="/community/block/"]').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
        });
    });
});

function closeReactionMenus(except = null) {
    document.querySelectorAll('[data-reaction-menu]').forEach(menu => {
        if (menu !== except) {
            menu.hidden = true;
            document.querySelector(`.reaction-trigger[data-message-id="${menu.dataset.reactionMenu}"]`)?.setAttribute('aria-expanded', 'false');
        }
    });
}

function updateReactionUI(messageId, data, selectedReaction = null, removed = false) {
    const source = document.querySelector(`.reaction-direct[data-message-id="${messageId}"]`) || document.querySelector(`.reaction-trigger[data-message-id="${messageId}"]`);
    const card = source?.closest('.message-card');
    if (!card) return;
    const counts = data.reaction_counts || {};
    card.querySelectorAll('[data-reaction-count]').forEach(node => {
        const key = node.dataset.reactionCount;
        if (Object.prototype.hasOwnProperty.call(counts, key)) node.textContent = counts[key];
    });
    card.querySelectorAll('.reaction-direct').forEach(button => {
        const selected = !removed && button.dataset.reaction === selectedReaction;
        button.setAttribute('aria-pressed', String(selected));
        button.classList.toggle('active', selected);
    });
    card.querySelectorAll('.reaction-option[data-reaction]').forEach(option => option.classList.toggle('selected', !removed && option.dataset.reaction === selectedReaction));
    const trigger = card.querySelector('.reaction-trigger');
    if (trigger) trigger.textContent = selectedReaction && !['like', 'heart'].includes(selectedReaction) ? `More reactions: ${selectedReaction}` : 'More reactions';
}

async function sendReaction(control) {
    const messageId = control.dataset.messageId;
    const selected = control.dataset.reaction;
    const directButton = control.classList.contains('reaction-direct');
    const isRemoving = directButton && control.getAttribute('aria-pressed') === 'true';
    const remove = control.hasAttribute('data-reaction-remove') || isRemoving;
    const url = `/student/community/${messageId}/reaction`;
    control.disabled = true;
    try {
        const response = await fetch(url, {
            method: remove ? 'DELETE' : 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: remove ? undefined : JSON.stringify({ reaction: selected })
        });
        if (!response.ok) throw new Error('Reaction failed.');
        const data = await response.json();
        updateReactionUI(messageId, data, remove ? null : selected, remove);
        const menu = control.closest('[data-reaction-menu]');
        if (menu) menu.hidden = true;
        document.querySelector(`.reaction-trigger[data-message-id="${messageId}"]`)?.setAttribute('aria-expanded', 'false');
    } catch (error) {
        control.setAttribute('aria-label', 'Reaction action failed. Please try again.');
    } finally {
        control.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.reaction-trigger').forEach(trigger => trigger.addEventListener('click', event => {
        event.stopPropagation();
        const menu = document.querySelector(`[data-reaction-menu="${trigger.dataset.messageId}"]`);
        if (!menu) return;
        const opening = menu.hidden;
        closeReactionMenus(menu);
        menu.hidden = !opening;
        trigger.setAttribute('aria-expanded', String(opening));
    }));
    document.querySelectorAll('.reaction-direct,.reaction-option').forEach(control => control.addEventListener('click', () => sendReaction(control)));
    document.addEventListener('click', event => {
        if (!event.target.closest('.reaction-control')) closeReactionMenus();
    });
});
async function toggleLike(btn, messageId) {
    const likeCount = btn.querySelector('.like-count');
    const isLiked = btn.classList.contains('liked');
    const url = isLiked ? `/student/community/${messageId}/unlike` : `/student/community/${messageId}/like`;
    btn.disabled = true;
    try {
        const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('Reaction failed.');
        const data = await response.json();
        if (data.success) { likeCount.textContent = data.likes_count; btn.classList.toggle('liked'); }
    } catch (error) {
        btn.setAttribute('aria-label', 'Support action failed. Please try again.');
    } finally {
        btn.disabled = false;
    }
}
</script>
@endsection
