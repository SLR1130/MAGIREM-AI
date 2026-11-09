@extends('layouts.app')
@section('title', 'Psychosocial Support - MAGIREM AI')
@section('content')

<style>
.psy-page{position:relative;z-index:1;padding:2rem;max-width:1300px;margin:0 auto}

/* Hero */
.psy-hero{background:rgba(255,255,255,0.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem 2rem;margin-bottom:2rem;position:relative;overflow:hidden}
.psy-hero::before{content:'';position:absolute;top:-80px;right:-80px;width:280px;height:280px;background:radial-gradient(circle,rgba(139,92,246,0.2),transparent 70%);border-radius:50%;pointer-events:none}
.psy-hero::after{content:'';position:absolute;bottom:-60px;left:-60px;width:200px;height:200px;background:radial-gradient(circle,rgba(16,185,129,0.15),transparent 70%);border-radius:50%;pointer-events:none}
.psy-hero-inner{position:relative;z-index:1;display:flex;align-items:center;gap:2rem;flex-wrap:wrap}
.psy-hero-icon{width:80px;height:80px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);border-radius:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 8px 24px rgba(139,92,246,0.4)}
.psy-hero-icon svg{width:44px;height:44px;color:white}
.psy-hero-text h1{font-family:'Montserrat',sans-serif;font-size:2rem;font-weight:700;margin:0 0 0.5rem;background:linear-gradient(135deg,#e9d5ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.psy-hero-text p{color:rgba(255,255,255,0.65);font-size:1rem;margin:0;max-width:600px}

/* Section headers */
.psy-section{margin-bottom:2.5rem}
.psy-section-header{display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;padding-bottom:0.875rem;border-bottom:1px solid rgba(255,255,255,0.08)}
.psy-section-header h2{font-family:'Montserrat',sans-serif;font-size:1.25rem;font-weight:700;color:#fff;margin:0}
.psy-section-header svg{width:22px;height:22px;flex-shrink:0}
.psy-section-desc{color:rgba(255,255,255,0.55);font-size:0.875rem;margin:0 0 1.25rem}

/* Feature cards (Community Support & Peer Support) */
.psy-feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem;margin-bottom:1.5rem}
.psy-feature-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:16px;overflow:hidden;transition:all 0.3s ease;display:flex;flex-direction:column}
.psy-feature-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,0.3);border-color:rgba(139,92,246,0.3)}
.psy-feature-thumb{height:160px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.psy-feature-thumb svg.bg-art{position:absolute;top:0;left:0;width:100%;height:100%}
.psy-feature-thumb-icon{position:relative;z-index:1;width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px);background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25)}
.psy-feature-thumb-icon svg{width:32px;height:32px;color:white}
.psy-feature-body{padding:1.25rem;flex:1;display:flex;flex-direction:column}
.psy-feature-body h3{font-family:'Montserrat',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;margin:0 0 0.5rem}
.psy-feature-body p{font-size:0.875rem;color:rgba(255,255,255,0.65);line-height:1.6;flex:1;margin:0 0 1rem}
.psy-feature-tags{display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem}
.psy-tag{font-size:0.68rem;font-weight:700;padding:0.18rem 0.55rem;border-radius:20px;text-transform:uppercase;letter-spacing:0.04em;border:1px solid}
.tag-green{background:rgba(16,185,129,0.15);color:#34d399;border-color:rgba(16,185,129,0.3)}
.tag-purple{background:rgba(139,92,246,0.15);color:#c4b5fd;border-color:rgba(139,92,246,0.3)}
.tag-blue{background:rgba(59,130,246,0.15);color:#93c5fd;border-color:rgba(59,130,246,0.3)}
.tag-pink{background:rgba(236,72,153,0.15);color:#f9a8d4;border-color:rgba(236,72,153,0.3)}
.psy-btn{display:inline-flex;align-items:center;gap:0.5rem;padding:0.65rem 1.25rem;border-radius:10px;font-size:0.875rem;font-weight:600;text-decoration:none;transition:all 0.2s;border:1px solid}
.psy-btn-primary{background:rgba(139,92,246,0.25);color:#c4b5fd;border-color:rgba(139,92,246,0.4)}
.psy-btn-primary:hover{background:rgba(139,92,246,0.45);color:#fff}
.psy-btn-primary svg{width:16px;height:16px}

/* Organization cards */
.org-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem}
.org-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:1.25rem;transition:all 0.3s ease;display:flex;flex-direction:column;gap:0.875rem}
.org-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,0.25);border-color:rgba(139,92,246,0.25)}
.org-card-top{display:flex;align-items:flex-start;gap:0.875rem}
.org-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.org-icon svg{width:22px;height:22px;color:white}
.org-name{font-family:'Montserrat',sans-serif;font-size:0.9rem;font-weight:700;color:#fff;margin:0 0 0.2rem}
.org-type{font-size:0.72rem;color:rgba(255,255,255,0.5);margin:0}
.org-desc{font-size:0.82rem;color:rgba(255,255,255,0.65);line-height:1.55;margin:0}
.org-contacts{display:flex;flex-direction:column;gap:0.4rem}
.org-contact-row{display:flex;align-items:center;gap:0.5rem;font-size:0.8rem}
.org-contact-row svg{width:14px;height:14px;flex-shrink:0;color:#a78bfa}
.org-contact-row a{color:#93c5fd;text-decoration:none;font-weight:500;transition:color 0.2s}
.org-contact-row a:hover{color:#fff}
.org-contact-row span{color:rgba(255,255,255,0.7)}
.org-footer{display:flex;gap:0.5rem;flex-wrap:wrap;padding-top:0.5rem;border-top:1px solid rgba(255,255,255,0.07)}
.org-link{display:inline-flex;align-items:center;gap:0.35rem;font-size:0.75rem;font-weight:600;padding:0.35rem 0.7rem;border-radius:7px;text-decoration:none;transition:all 0.2s;border:1px solid}
.org-link-web{background:rgba(59,130,246,0.15);color:#93c5fd;border-color:rgba(59,130,246,0.3)}
.org-link-web:hover{background:rgba(59,130,246,0.3);color:#fff}
.org-link-call{background:rgba(16,185,129,0.15);color:#34d399;border-color:rgba(16,185,129,0.3)}
.org-link-call:hover{background:rgba(16,185,129,0.3);color:#fff}
.org-link svg{width:12px;height:12px}

/* Hotline banner */
.hotline-banner{background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(245,158,11,0.1));border:1px solid rgba(239,68,68,0.25);border-radius:16px;padding:1.5rem 2rem;margin-bottom:2rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap}
.hotline-banner-icon{width:48px;height:48px;background:rgba(239,68,68,0.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.hotline-banner-icon svg{width:24px;height:24px;color:#f87171}
.hotline-banner-text h3{font-family:'Montserrat',sans-serif;font-size:1rem;font-weight:700;color:#fca5a5;margin:0 0 0.25rem}
.hotline-banner-text p{font-size:0.85rem;color:rgba(255,255,255,0.65);margin:0}
.hotline-numbers{display:flex;gap:0.75rem;flex-wrap:wrap;margin-left:auto}
.hotline-num{display:flex;flex-direction:column;align-items:center;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:0.5rem 1rem;text-align:center}
.hotline-num-label{font-size:0.65rem;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.05em}
.hotline-num-val{font-family:'Montserrat',sans-serif;font-size:1.1rem;font-weight:700;color:#fca5a5}

@media(max-width:768px){.psy-page{padding:1rem}.psy-hero-inner{flex-direction:column;text-align:center}.hotline-banner{flex-direction:column}.hotline-numbers{margin-left:0;justify-content:center}}
</style>

<div class="psy-page">

<!-- Hero -->
<div class="psy-hero">
  <div class="psy-hero-inner">
    <div class="psy-hero-icon">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
    </div>
    <div class="psy-hero-text">
      <h1>Psychosocial Support</h1>
      <p>Connect with your community, find peer support, and access trusted mental health organizations in the Philippines. You are not alone.</p>
    </div>
  </div>
</div>

<!-- Emergency Hotline Banner -->
<div class="hotline-banner">
  <div class="hotline-banner-icon">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
  </div>
  <div class="hotline-banner-text">
    <h3>?? In Crisis? Call Now</h3>
    <p>If you or someone you know is in immediate danger, please reach out to these 24/7 hotlines</p>
  </div>
  <div class="hotline-numbers">
    <div class="hotline-num">
      <span class="hotline-num-label">NCMH Crisis</span>
      <span class="hotline-num-val">1553</span>
    </div>
    <div class="hotline-num">
      <span class="hotline-num-label">In Touch</span>
      <span class="hotline-num-val">893-7603</span>
    </div>
    <div class="hotline-num">
      <span class="hotline-num-label">Emergency</span>
      <span class="hotline-num-val">911</span>
    </div>
  </div>
</div>

<!-- --- SECTION 1: Community & Peer Support --- -->
<div class="psy-section">
  <div class="psy-section-header">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#a78bfa"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    <h2>Community & Peer Support</h2>
  </div>
  <p class="psy-section-desc">Connect with fellow students and build a supportive community around your mental wellness journey.</p>

  <div class="psy-feature-grid">

    <!-- Community Support Card -->
    <div class="psy-feature-card">
      <div class="psy-feature-thumb" style="background:linear-gradient(135deg,#1e3a5f,#3b82f6)">
        <svg class="bg-art" viewBox="0 0 400 160" fill="none">
          <circle cx="200" cy="80" r="70" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
          <circle cx="120" cy="70" r="28" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.12)" stroke-width="1"/>
          <circle cx="200" cy="60" r="28" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
          <circle cx="280" cy="70" r="28" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.12)" stroke-width="1"/>
          <path d="M148 80 Q165 95 200 90 Q235 95 252 80" stroke="rgba(255,255,255,0.2)" stroke-width="1.5" fill="none"/>
          <circle cx="120" cy="62" r="8" fill="rgba(255,255,255,0.2)"/>
          <circle cx="200" cy="52" r="8" fill="rgba(255,255,255,0.25)"/>
          <circle cx="280" cy="62" r="8" fill="rgba(255,255,255,0.2)"/>
        </svg>
        <div class="psy-feature-thumb-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
      </div>
      <div class="psy-feature-body">
        <div class="psy-feature-tags">
          <span class="psy-tag tag-blue">Community</span>
          <span class="psy-tag tag-purple">Anonymous</span>
          <span class="psy-tag tag-green">Safe Space</span>
        </div>
        <h3>Community Support Forum</h3>
        <p>Share your thoughts, experiences, and encouragement with fellow students in a safe, moderated space. Post anonymously or with your name � your choice. Read stories from others who understand what you're going through.</p>
        <a href="{{ route('student.community') }}" class="psy-btn psy-btn-primary">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
          Go to Community Forum
        </a>
      </div>
    </div>

    <!-- Peer Support Card -->
    <div class="psy-feature-card">
      <div class="psy-feature-thumb" style="background:linear-gradient(135deg,#14532d,#10b981)">
        <svg class="bg-art" viewBox="0 0 400 160" fill="none">
          <path d="M160 100 Q180 60 200 70 Q220 60 240 100" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.12)" stroke-width="1.5"/>
          <circle cx="160" cy="55" r="22" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
          <circle cx="240" cy="55" r="22" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
          <path d="M155 55 Q160 48 165 55" stroke="rgba(255,255,255,0.5)" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <path d="M235 55 Q240 48 245 55" stroke="rgba(255,255,255,0.5)" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <circle cx="157" cy="52" r="2" fill="rgba(255,255,255,0.6)"/>
          <circle cx="163" cy="52" r="2" fill="rgba(255,255,255,0.6)"/>
          <circle cx="237" cy="52" r="2" fill="rgba(255,255,255,0.6)"/>
          <circle cx="243" cy="52" r="2" fill="rgba(255,255,255,0.6)"/>
          <path d="M180 110 Q200 100 220 110" stroke="rgba(16,185,129,0.4)" stroke-width="1.5" fill="none"/>
          <circle cx="100" cy="80" r="15" fill="rgba(255,255,255,0.05)"/>
          <circle cx="300" cy="80" r="15" fill="rgba(255,255,255,0.05)"/>
        </svg>
        <div class="psy-feature-thumb-icon">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </div>
      </div>
      <div class="psy-feature-body">
        <div class="psy-feature-tags">
          <span class="psy-tag tag-green">Peer Support</span>
          <span class="psy-tag tag-pink">1-on-1</span>
          <span class="psy-tag tag-purple">Group Activities</span>
        </div>
        <h3>Peer Support Network</h3>
        <p>Connect with trained peer supporters � fellow students who have been through similar experiences. Join group activities, wellness challenges, and find a buddy who truly understands student life struggles.</p>
        <a href="{{ route('student.peer-support') }}" class="psy-btn psy-btn-primary">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
          Find Peer Support
        </a>
      </div>
    </div>

  </div>
</div>

<!-- ═══ SECTION 2: Mental Health Organizations in the Philippines ═══ -->
<div class="psy-section">
  <div class="psy-section-header">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#34d399"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
    <h2>Mental Health Organizations in the Philippines</h2>
  </div>
  <p class="psy-section-desc">Trusted organizations providing mental health support, counseling, and resources across the Philippines.</p>

  <div class="org-grid">

    <!-- National Center for Mental Health -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
        </div>
        <div>
          <h3 class="org-name">National Center for Mental Health (NCMH)</h3>
          <p class="org-type">Government Hospital • 24/7 Crisis Hotline</p>
        </div>
      </div>
      <p class="org-desc">The premier government mental health facility in the Philippines, providing psychiatric services, crisis intervention, and mental health programs.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          <span><strong>Crisis Hotline:</strong> 1553 (Toll-free) • 0917-899-8727</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
          <span>Mandaluyong City, Metro Manila</span>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://ncmh.gov.ph" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Website
        </a>
        <a href="tel:1553" class="org-link org-link-call">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          Call 1553
        </a>
      </div>
    </div>

    <!-- In Touch Crisis Line -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#10b981,#059669)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        </div>
        <div>
          <h3 class="org-name">In Touch Community Services</h3>
          <p class="org-type">Crisis Hotline • Counseling Services</p>
        </div>
      </div>
      <p class="org-desc">24/7 crisis intervention and emotional support hotline providing free, confidential counseling to anyone in distress.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          <span><strong>Hotline:</strong> (02) 8893-7603 • 0917-800-1123</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Available 24/7</span>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://www.in-touch.org" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Website
        </a>
        <a href="tel:88937603" class="org-link org-link-call">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          Call Now
        </a>
      </div>
    </div>

    <!-- Natasha Goulbourn Foundation -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#ec4899,#db2777)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
          <h3 class="org-name">Natasha Goulbourn Foundation</h3>
          <p class="org-type">Mental Health Advocacy • Suicide Prevention</p>
        </div>
      </div>
      <p class="org-desc">Dedicated to raising awareness about mental health and suicide prevention through education, advocacy, and support programs.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          <span><strong>Hotline:</strong> 0917-558-4673 (HOPE)</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          <a href="mailto:info@ngf-hope.org">info@ngf-hope.org</a>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://www.ngf-hope.org" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Website
        </a>
        <a href="tel:09175584673" class="org-link org-link-call">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          Call HOPE
        </a>
      </div>
    </div>

    <!-- Philippine Mental Health Association -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        </div>
        <div>
          <h3 class="org-name">Philippine Mental Health Association</h3>
          <p class="org-type">NGO • Mental Health Programs</p>
        </div>
      </div>
      <p class="org-desc">Leading mental health organization promoting mental wellness through education, advocacy, and community-based programs nationwide.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          <span><strong>Phone:</strong> (02) 8531-9001</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
          <span>Quezon City, Metro Manila</span>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://pmha.org.ph" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Website
        </a>
        <a href="tel:85319001" class="org-link org-link-call">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          Call
        </a>
      </div>
    </div>

    <!-- MindNation -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
        </div>
        <div>
          <h3 class="org-name">MindNation</h3>
          <p class="org-type">Online Therapy • Mental Wellness Platform</p>
        </div>
      </div>
      <p class="org-desc">Digital mental health platform offering online counseling, therapy sessions, and mental wellness programs accessible anytime, anywhere.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          <span><strong>Support:</strong> 0917-622-6463</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          <span>Online Platform • Mobile App Available</span>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://mindnation.com" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Website
        </a>
        <a href="tel:09176226463" class="org-link org-link-call">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
          Contact
        </a>
      </div>
    </div>

    <!-- Silakbo PH -->
    <div class="org-card">
      <div class="org-card-top">
        <div class="org-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2)">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </div>
        <div>
          <h3 class="org-name">Silakbo PH</h3>
          <p class="org-type">Youth Mental Health • Peer Support</p>
        </div>
      </div>
      <p class="org-desc">Youth-led organization providing mental health support, peer counseling, and advocacy specifically for Filipino young people and students.</p>
      <div class="org-contacts">
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
          <span>Contact your Guidance Office directly.</span>
        </div>
        <div class="org-contact-row">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
          <span>Facebook: @SilakboPH</span>
        </div>
      </div>
      <div class="org-footer">
        <a href="https://www.facebook.com/SilakboPH" target="_blank" class="org-link org-link-web">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
          Facebook
        </a>
      </div>
    </div>

  </div>
</div>

<!-- Back to Dashboard -->
<div style="text-align:center;margin-top:3rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,0.08)">
  <a href="{{ route('student.insights') }}" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.875rem 1.75rem;background:rgba(139,92,246,0.2);border:1px solid rgba(139,92,246,0.4);border-radius:12px;color:#c4b5fd;text-decoration:none;font-weight:600;transition:all 0.3s">
    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
    Back to Dashboard
  </a>
</div>

</div>
<!-- End psy-page -->

@endsection
