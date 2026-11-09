<style>
    .mi-modern-card { background:#fff; border:1px solid #dce9e1; border-radius:20px; padding:clamp(1rem,3vw,1.7rem); margin-bottom:1rem; color:#174337; box-shadow:0 10px 30px rgba(15,63,46,.05); }
    .mi-modern-card h2,.mi-modern-card h3 { color:#143e30; margin:0 0 .5rem; }
    .mi-modern-card p { color:#567369; line-height:1.55; }
    .mi-modern-note { font-size:.88rem; }
    .mi-periods { display:flex; flex-wrap:wrap; gap:.5rem; margin:1rem 0; }
    .mi-periods button,.mi-history-search { border:1px solid #cfe1d6; border-radius:10px; padding:.6rem .85rem; background:#fff; color:#174337; font:inherit; }
    .mi-periods button[aria-pressed="true"] { background:#174337; color:white; }
    .mi-chart { display:flex; gap:.5rem; align-items:end; min-height:175px; overflow-x:auto; padding:1rem 0; }
    .mi-bar-group { flex:1 0 52px; text-align:center; font-size:.72rem; color:#567369; }
    .mi-bar { width:70%; min-height:2px; margin:auto auto .4rem; border-radius:6px 6px 0 0; background:#75ad8f; }
    .mi-dist { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
    .mi-chip { background:#edf6ef; border-radius:999px; padding:.35rem .7rem; font-size:.83rem; color:#315d49; }
    .mi-history { display:grid; gap:.8rem; }
    .mi-history-card { padding:1rem; border:1px solid #dce9e1; border-radius:14px; overflow-wrap:anywhere; }
    .mi-chart[hidden], .mi-dist[hidden], .mi-history-card[hidden] { display:none; }
    .mi-history-card time { font-size:.82rem; color:#658276; }
    .mi-history-card p { margin:.55rem 0; }
    .mi-modern-link { color:#12664b; font-weight:700; }
    .mi-history-search { width:100%; margin:1rem 0; }
    @media(max-width:600px) { .mi-modern-card { padding:1rem; } .mi-chart { min-height:140px; } }
</style>

<div id="section-statistics" class="insight-section">
    <div class="section-header">
        <h1 class="section-title">Your trends</h1>
        <p class="section-subtitle">A closer look at your recorded activity, at your own pace.</p>
    </div>
    <div class="mi-modern-card">
        <h2>Recorded emotion observations</h2>
        <p><strong>Total Conversations:</strong> {{ $totalConversations }} conversations</p>
        <p class="mi-modern-note">Counts of labels recorded with your messages in the past year. They are tentative observations, not diagnoses or a measure of your wellbeing.</p>
        @if(array_sum($todayEmotionCounts) === 0)
            <p>No emotional signals yet today.</p>
        @endif
        <div class="mi-periods" role="group" aria-label="Trend time period">
            <button type="button" data-trend-period="day" aria-pressed="true">Daily · 7 days</button>
            <button type="button" data-trend-period="week" aria-pressed="false">Weekly · 12 weeks</button>
            <button type="button" data-trend-period="month" aria-pressed="false">Monthly · 12 months</button>
        </div>
        <p id="mi-trend-empty" @if($trendObservations->isNotEmpty()) hidden @endif>Not enough recorded emotion observations yet. Your chart will appear when observations are available.</p>
        <div id="mi-observation-chart" class="mi-chart" aria-label="Recorded observations by time period"></div>
        <div id="mi-observation-distribution" class="mi-dist" aria-label="Recorded emotion distribution"></div>
    </div>
</div>

<div id="section-conversations" class="insight-section">
    <div class="section-header">
        <h1 class="section-title">Your conversations</h1>
        <p class="section-subtitle">Your private conversations with MAGI, grouped by session.</p>
    </div>
    <div class="mi-modern-card">
        @if($insightSessions->isNotEmpty())
            <label for="mi-history-search">Search conversation titles and previews</label>
            <input id="mi-history-search" class="mi-history-search" type="search" placeholder="Search your conversations">
            <label for="mi-history-date">Filter by date</label>
            <input id="mi-history-date" class="mi-history-search" type="date">
        @endif
        <div class="mi-history" id="mi-history-list">
            @forelse($insightSessions as $session)
                @php($firstMessage = $session->entries->first())
                <article class="mi-history-card" data-history-card data-history-date="{{ optional($session->last_message_at ?? $session->created_at)->format('Y-m-d') }}">
                    <h3>{{ $session->title ?: 'Conversation' }}</h3>
                    <time datetime="{{ optional($session->last_message_at ?? $session->created_at)->toIso8601String() }}">{{ optional($session->last_message_at ?? $session->created_at)->format('M j, Y · g:i A') }}</time>
                    @if($firstMessage)
                        <p><small>First message</small><br>{{ \Illuminate\Support\Str::limit($firstMessage->entry_text, 160) }}</p>
                    @endif
                    <a class="mi-modern-link" href="{{ route('student.ai-chat', ['session' => $session->id]) }}">Open conversation &rarr;</a>
                </article>
            @empty
                <p>No conversations yet. When you chat with MAGI, your conversation history will appear here.</p>
                <a class="mi-modern-link" href="{{ route('student.ai-chat') }}">Talk to MAGI &rarr;</a>
            @endforelse
        </div>
        <p id="mi-history-no-match" hidden>No conversations match your search.</p>
    </div>
</div>

<div id="section-recommendations" class="insight-section">
    <div class="section-header">
        <h1 class="section-title">MAGI Recommends</h1>
        <p class="section-subtitle">Wellness resources selected for you</p>
    </div>
    <div class="mi-modern-card">
        @include('student.partials.recommendation-availability', ['recommendationState' => $insightRecommendationState])
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const observations = {{ \Illuminate\Support\Js::from($trendObservations->map(fn($entry) => ['date' => $entry->created_at->format('Y-m-d'), 'emotion' => $entry->detected_emotion])->values()) }};
    const chart = document.getElementById('mi-observation-chart');
    const dist = document.getElementById('mi-observation-distribution');
    const empty = document.getElementById('mi-trend-empty');
    function draw(period) {
        const now = new Date(); now.setHours(0, 0, 0, 0);
        const buckets = [];
        const count = period === 'day' ? 7 : 12;
        for (let i = count - 1; i >= 0; i--) {
            let start = new Date(now);
            if (period === 'day') start.setDate(start.getDate() - i);
            if (period === 'week') { start.setDate(start.getDate() - ((start.getDay() + 6) % 7) - i * 7); }
            if (period === 'month') start = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const end = new Date(start);
            if (period === 'day') end.setDate(end.getDate() + 1);
            if (period === 'week') end.setDate(end.getDate() + 7);
            if (period === 'month') end.setMonth(end.getMonth() + 1);
            buckets.push({start, end, items:[]});
        }
        observations.forEach(o => {
            const date = new Date(o.date + 'T12:00:00');
            const bucket = buckets.find(b => date >= b.start && date < b.end);
            if (bucket) bucket.items.push(o);
        });
        const active = buckets.flatMap(b => b.items);
        empty.hidden = active.length > 0;
        chart.replaceChildren(); dist.replaceChildren();
        chart.hidden = active.length === 0; dist.hidden = active.length === 0;
        if (!active.length) return;
        const max = Math.max(1, ...buckets.map(b => b.items.length));
        buckets.forEach(b => {
            const label = b.start.toLocaleDateString(undefined, {month:'short', ...(period === 'month' ? {year:'2-digit'} : {day:'numeric'})});
            const group = document.createElement('div'); group.className = 'mi-bar-group';
            const bar = document.createElement('div'); bar.className = 'mi-bar';
            bar.style.height = (b.items.length / max * 120) + 'px';
            bar.title = label + ': ' + b.items.length + ' observation(s)';
            bar.setAttribute('aria-label', bar.title);
            const caption = document.createElement('div'); caption.textContent = label;
            const number = document.createElement('strong'); number.textContent = b.items.length;
            group.append(number, bar, caption); chart.append(group);
        });
        const counts = {};
        active.forEach(o => { counts[o.emotion] = (counts[o.emotion] || 0) + 1; });
        Object.entries(counts).sort((a,b) => b[1]-a[1]).forEach(([label, count]) => {
            const chip = document.createElement('span'); chip.className = 'mi-chip';
            chip.textContent = label + ': ' + count; dist.append(chip);
        });
    }
    document.querySelectorAll('[data-trend-period]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-trend-period]').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        draw(button.dataset.trendPeriod);
    }));
    draw('day');
    const search = document.getElementById('mi-history-search');
    const dateFilter = document.getElementById('mi-history-date');
    function filterHistory() {
        const term = search.value.trim().toLocaleLowerCase(); let shown = 0;
        document.querySelectorAll('[data-history-card]').forEach(card => {
            const matchesText = card.textContent.toLocaleLowerCase().includes(term);
            const matchesDate = !dateFilter.value || card.dataset.historyDate === dateFilter.value;
            card.hidden = !(matchesText && matchesDate);
            if (!card.hidden) shown++;
        });
        document.getElementById('mi-history-no-match').hidden = shown > 0;
    }
    if (search) {
        search.addEventListener('input', filterHistory);
        dateFilter.addEventListener('change', filterHistory);
    }
});
</script>
