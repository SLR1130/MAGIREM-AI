<section class="msc-home-feature-row" id="{{ $guest ? 'mscPublicFeatures' : 'mscStudentFeatures' }}" aria-label="MAGIREM AI features">
    <a class="msc-home-feature" href="{{ $guest ? route('login') : route('student.ai-chat') }}" data-msc-reveal data-msc-spotlight data-msc-tilt style="--msc-card-order:0;--msc-reveal-order:0">
        <span class="msc-home-feature__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4.3-1.1L3 21l1.7-5.2A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8.5 11h7"/></svg></span>
        <span class="msc-home-feature__body"><strong>AI Companion</strong><span>Talk through your thoughts and explore practical next steps.</span></span><span class="msc-home-feature__arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="msc-home-feature" href="{{ $guest ? route('login') : route('student.journal') }}" data-msc-reveal data-msc-spotlight data-msc-tilt style="--msc-card-order:1;--msc-reveal-order:1">
        <span class="msc-home-feature__icon msc-home-feature__icon--gold" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 19.5c2.5-1.5 5.3-1.5 8 .1 2.7-1.6 5.5-1.6 8-.1V4.5c-2.7-1.6-5.3-1.6-8 0-2.7-1.6-5.3-1.6-8 0v15Z"/><path d="M12 4.5v15"/></svg></span>
        <span class="msc-home-feature__body"><strong>My Journal</strong><span>A private place to write, reflect, and revisit entries.</span></span><span class="msc-home-feature__arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="msc-home-feature" href="{{ $guest ? route('login') : route('student.appointments') }}" data-msc-reveal data-msc-spotlight data-msc-tilt style="--msc-card-order:2;--msc-reveal-order:2">
        <span class="msc-home-feature__icon msc-home-feature__icon--blue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18m-13 5h3"/></svg></span>
        <span class="msc-home-feature__body"><strong>Appointments</strong><span>View your schedule or request professional support.</span></span><span class="msc-home-feature__arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="msc-home-feature" href="{{ $guest ? route('login') : route('student.community') }}" data-msc-reveal data-msc-spotlight data-msc-tilt style="--msc-card-order:3;--msc-reveal-order:3">
        <span class="msc-home-feature__icon msc-home-feature__icon--rose" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6m2 9v-2a6 6 0 0 0-3-5.2"/></svg></span>
        <span class="msc-home-feature__body"><strong>Community</strong><span>Join respectful conversations with fellow MinSU students.</span></span><span class="msc-home-feature__arrow" aria-hidden="true">&rarr;</span>
    </a>
</section>
