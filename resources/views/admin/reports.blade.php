@extends('layouts.app')

@section('title', 'Emotional Analytics · MAGIREM AI')

@section('content')
<style>
.admin-reports,
.admin-reports *,
.admin-reports *::before,
.admin-reports *::after { box-sizing:border-box; }

.admin-reports {
    --forest:#103F32;
    --forest-2:#174F3F;
    --emerald:#236B55;
    --mint:#E6F1EB;
    --ivory:#F8FAF7;
    --gold:#F4C84E;
    --text:#263A33;
    --muted:#71837A;
    --soft:#91A098;
    --line:rgba(16,63,50,.10);
    --danger:#B95E57;
    --danger-bg:#FFF2F0;
    --warning:#C18B2F;
    --warning-bg:#FFF8E8;
    --blue:#4B7E8F;
    --blue-bg:#EDF5F7;

    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:18px 22px 36px;
    color:var(--text);
    font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.ar-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:22px;
    margin-bottom:16px;
}
.ar-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:8px;
    color:var(--emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}
.ar-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--gold);
}
.ar-head h1 {
    margin:0;
    color:var(--forest);
    font-size:clamp(34px,3.2vw,48px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:850;
}
.ar-head p {
    max-width:760px;
    margin:10px 0 0;
    color:var(--muted);
    font-size:11px;
    line-height:1.6;
}
.ar-scope {
    min-width:180px;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:13px;
    background:#fff;
}
.ar-scope strong {
    display:block;
    color:var(--forest);
    font-size:9px;
}
.ar-scope span {
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:7.4px;
}

.ar-stats {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:9px;
    margin-bottom:12px;
}
.ar-stat {
    min-width:0;
    display:flex;
    align-items:center;
    gap:11px;
    padding:14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
}
.ar-stat-icon {
    width:35px;
    height:35px;
    flex:0 0 35px;
    display:grid;
    place-items:center;
    border-radius:10px;
    background:var(--mint);
    color:var(--forest);
}
.ar-stat-icon.blue { background:var(--blue-bg); color:var(--blue); }
.ar-stat-icon.gold { background:var(--warning-bg); color:var(--warning); }
.ar-stat-icon svg { width:16px; height:16px; }
.ar-stat-copy { min-width:0; }
.ar-stat-copy span {
    display:block;
    color:var(--muted);
    font-size:7.4px;
    font-weight:750;
}
.ar-stat-copy strong {
    display:block;
    margin-top:4px;
    overflow:hidden;
    color:var(--forest);
    font-size:22px;
    line-height:1;
    letter-spacing:-.03em;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.ar-stat-copy small {
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:7px;
}
.ar-trend-up { color:#2F8767!important; }
.ar-trend-down { color:var(--danger)!important; }
.ar-trend-neutral { color:#71847d!important; }

.ar-tabs {
    position:sticky;
    top:0;
    z-index:20;
    display:flex;
    gap:5px;
    padding:5px;
    margin-bottom:12px;
    border:1px solid var(--line);
    border-radius:13px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(14px);
    box-shadow:0 8px 24px rgba(16,63,50,.035);
}
.ar-tab {
    flex:1;
    min-height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    border:0;
    border-radius:9px;
    background:transparent;
    color:var(--muted);
    font:inherit;
    font-size:8.5px;
    font-weight:820;
    cursor:pointer;
}
.ar-tab svg { width:14px; height:14px; }
.ar-tab:hover { background:var(--ivory); color:var(--forest); }
.ar-tab.active { background:var(--mint); color:var(--forest); }

.ar-panel { display:none; }
.ar-panel.active { display:block; animation:arFade .18s ease; }
@keyframes arFade {
    from { opacity:0; transform:translateY(4px); }
    to { opacity:1; transform:none; }
}

.ar-intro {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    margin-bottom:10px;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:12px;
    background:var(--ivory);
}
.ar-intro strong {
    color:var(--forest);
    font-size:9.5px;
}
.ar-intro p {
    max-width:760px;
    margin:4px 0 0;
    color:var(--muted);
    font-size:7.7px;
    line-height:1.55;
}
.ar-pill {
    min-height:24px;
    display:inline-flex;
    align-items:center;
    padding:0 8px;
    border-radius:999px;
    background:#fff;
    color:var(--emerald);
    font-size:6.8px;
    font-weight:850;
    white-space:nowrap;
}

.ar-grid-two {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}
.ar-chart {
    min-width:0;
    padding:13px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
}
.ar-chart.large { grid-column:1 / -1; }
.ar-chart h2 {
    margin:0;
    color:var(--forest);
    font-size:9.5px;
    font-weight:830;
}
.ar-chart p {
    margin:4px 0 9px;
    color:var(--muted);
    font-size:7.2px;
}
.ar-chart-wrap {
    position:relative;
    height:280px;
}
.ar-chart.large .ar-chart-wrap { height:320px; }

.ar-distribution-grid {
    display:grid;
    grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr);
    gap:10px;
}
.ar-emotion-list {
    display:grid;
    gap:7px;
}
.ar-emotion-row {
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:9px;
    align-items:center;
    padding:10px 11px;
    border:1px solid var(--line);
    border-radius:10px;
    background:var(--ivory);
}
.ar-emotion-row strong {
    color:var(--forest);
    font-size:8.4px;
    text-transform:capitalize;
}
.ar-emotion-row span {
    color:var(--muted);
    font-size:7.2px;
}
.ar-emotion-count {
    min-width:34px;
    height:27px;
    display:grid;
    place-items:center;
    border-radius:8px;
    background:var(--mint);
    color:var(--forest)!important;
    font-size:8px!important;
    font-weight:850;
}

.ar-risk-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:9px;
}
.ar-risk {
    padding:14px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
}
.ar-risk.danger { border-top:3px solid var(--danger); }
.ar-risk.warning { border-top:3px solid var(--warning); }
.ar-risk.safe { border-top:3px solid #2F8767; }
.ar-risk span {
    color:var(--muted);
    font-size:7px;
    text-transform:uppercase;
    letter-spacing:.06em;
}
.ar-risk strong {
    display:block;
    margin-top:7px;
    color:var(--forest);
    font-size:26px;
    line-height:1;
    letter-spacing:-.04em;
}
.ar-risk p {
    margin:5px 0 0;
    color:var(--muted);
    font-size:7.5px;
}

.ar-student-list {
    display:grid;
    gap:7px;
}
.ar-student {
    display:grid;
    grid-template-columns:38px minmax(0,1fr) auto;
    gap:10px;
    align-items:center;
    padding:10px 11px;
    border:1px solid var(--line);
    border-radius:11px;
    background:#fff;
}
.ar-student-avatar {
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:10px;
    background:var(--mint);
    color:var(--forest);
    font-size:10px;
    font-weight:850;
}
.ar-student-copy {
    min-width:0;
}
.ar-student-copy strong,
.ar-student-copy span {
    display:block;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.ar-student-copy strong {
    color:var(--forest);
    font-size:8.6px;
}
.ar-student-copy span {
    max-width:360px;
    margin-top:2px;
    color:var(--muted);
    font-size:7.2px;
}
.ar-student-count {
    min-width:70px;
    padding:8px 10px;
    border-radius:9px;
    background:var(--ivory);
    text-align:center;
}
.ar-student-count strong {
    display:block;
    color:var(--forest);
    font-size:15px;
}
.ar-student-count span {
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-size:6.5px;
}
.ar-empty {
    padding:54px 20px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    color:var(--muted);
    text-align:center;
    font-size:8px;
}
.ar-empty strong {
    display:block;
    margin-bottom:5px;
    color:var(--forest);
    font-size:10px;
}
.ar-taxonomy-note {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin:0 0 12px;
    padding:10px 12px;
    border:1px solid rgba(35,107,85,.12);
    border-radius:11px;
    background:var(--ivory);
    color:var(--muted);
    font-size:8px;
    line-height:1.5;
}
.ar-taxonomy-note strong { color:var(--forest); }
.ar-period-filter { display:inline-flex; align-items:center; gap:6px; color:var(--muted); font-size:8px; }
.ar-period-filter select { min-height:27px; padding:4px 8px; border:1px solid var(--line); border-radius:8px; background:#fff; color:var(--forest); font:inherit; font-weight:750; }
.ar-category-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    margin-bottom:14px;
}
.ar-category-card {
    width:100%;
    padding:11px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    color:var(--text);
    text-align:left;
    cursor:pointer;
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.ar-category-card:hover,
.ar-category-card:focus-visible,
.ar-category-card.active {
    border-color:rgba(35,107,85,.35);
    box-shadow:0 7px 16px rgba(16,63,50,.08);
    outline:none;
    transform:translateY(-1px);
}
.ar-category-card strong { display:block; color:var(--forest); font-size:9px; line-height:1.25; }
.ar-category-card small { display:block; margin-top:5px; color:var(--muted); font-size:7px; }
.ar-category-card .ar-category-count { display:block; margin-top:8px; color:var(--emerald); font-size:19px; font-weight:850; letter-spacing:-.03em; }
.ar-drilldown { margin:0 0 14px; padding:13px; border:1px solid var(--line); border-radius:13px; background:var(--ivory); }
.ar-drilldown[hidden] { display:none; }
.ar-drilldown-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:9px; }
.ar-drilldown-head strong { color:var(--forest); font-size:10px; }
.ar-drilldown-head button { border:0; background:transparent; color:var(--emerald); font:inherit; font-size:8px; font-weight:800; cursor:pointer; }
.ar-drilldown-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px; }
.ar-drilldown-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 9px; border-radius:9px; background:#fff; color:var(--muted); font-size:8px; }
.ar-drilldown-row strong { color:var(--forest); font-size:9px; }

@media(max-width:1000px) {
    .ar-distribution-grid,
    .ar-grid-two { grid-template-columns:1fr; }
    .ar-chart.large { grid-column:auto; }
}
@media(max-width:760px) {
    .admin-reports { padding-inline:12px; }
    .ar-head { align-items:flex-start; flex-direction:column; }
    .ar-scope { width:100%; }
    .ar-stats { grid-template-columns:1fr; }
    .ar-tabs { overflow-x:auto; }
    .ar-tab { min-width:125px; }
    .ar-intro { flex-direction:column; }
    .ar-category-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .ar-drilldown-list { grid-template-columns:1fr; }
}
</style>

<div class="admin-reports">
    <header class="ar-head">
        <div>
            <span class="ar-eyebrow">Wellbeing intelligence</span>
            <h1>Analytics & reports</h1>
            <p>Explore emotional trends, distribution, risk levels, and engagement in separate analytical views instead of one overloaded reporting page.</p>
        </div>

        <aside class="ar-scope">
            <strong>Current reporting scope</strong>
            <span>System wellbeing dataset</span>
        </aside>
    </header>

    <section class="ar-stats">
        <article class="ar-stat">
            <span class="ar-stat-icon blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="ar-stat-copy">
                <span>This week</span>
                <strong>{{ $thisWeekCount }}</strong>
                @if($weeklyChange !== null)
                    <small class="{{ $weeklyChange >= 0 ? 'ar-trend-up' : 'ar-trend-down' }}">{{ abs($weeklyChange) }}% {{ $weeklyChange >= 0 ? 'increase' : 'decrease' }} vs last week</small>
                @else
                    <small class="ar-trend-neutral">Not enough prior-week data</small>
                @endif
            </span>
        </article>

        <article class="ar-stat">
            <span class="ar-stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M16 10h.01M9 15a4 4 0 006 0M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
            </span>
            <span class="ar-stat-copy">
                <span>Classified messages</span>
                <strong>{{ $classifiedTotal }}</strong>
                <small>{{ $classifiedTotal }} of {{ $analyticsTotal }} consented entries have taxonomy metadata</small>
            </span>
        </article>

        <article class="ar-stat">
            <span class="ar-stat-icon gold">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="ar-stat-copy">
                <span>Most common category</span>
                <strong>{{ $mostFrequentCategory['label'] ?? 'N/A' }}</strong>
                <small>
                    @if($mostFrequentCategory)
                        {{ $mostFrequentCategory['percentage'] }}% of classified messages
                    @else
                        Not enough classified data
                    @endif
                </small>
            </span>
        </article>
    </section>

    <nav class="ar-tabs" aria-label="Analytics report sections">
        <button type="button" class="ar-tab active" data-report-tab="trends">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19l5-5 4 4 7-9"/></svg>
            Trends
        </button>
        <button type="button" class="ar-tab" data-report-tab="distribution">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 3a9 9 0 109 9h-9V3zm4 0v5h5a9 9 0 00-5-5z"/></svg>
            Distribution
        </button>
        <button type="button" class="ar-tab" data-report-tab="risk">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg>
            Risk
        </button>
        <button type="button" class="ar-tab" data-report-tab="engagement">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg>
            Engagement
        </button>
    </nav>

    <section class="ar-panel active" data-report-panel="trends">
        <div class="ar-intro">
            <div>
                <strong>Trend analysis</strong>
                <p>Compare short-term emotion movement with six-month journal activity. These charts use the same datasets already supplied by your reports controller.</p>
            </div>
            <span class="ar-pill">Time series</span>
        </div>

        <div class="ar-grid-two">
            <article class="ar-chart large">
                <h2>7-day emotion trends</h2>
                <p>Daily emotion counts across the last seven days.</p>
                <div class="ar-chart-wrap"><canvas id="emotionTrendsChart"></canvas></div>
            </article>

            <article class="ar-chart large">
                <h2>6-month journal activity</h2>
                <p>Monthly entry volume across the existing six-month dataset.</p>
                <div class="ar-chart-wrap"><canvas id="monthlyTrendChart"></canvas></div>
            </article>
        </div>
    </section>

    <section class="ar-panel" data-report-panel="distribution">
        <div class="ar-intro">
            <div>
                <strong>Emotion distribution</strong>
                <p>See the overall emotional mix alongside its exact aggregate counts.</p>
            </div>
            <span class="ar-pill">All-time aggregate</span>
        </div>

        <div class="ar-taxonomy-note">
            <span><strong>Reporting taxonomy {{ $taxonomy->version() }}</strong> · 35 proposed labels grouped into 8 categories. The trained six-class output remains unchanged; unsupported labels stay unclassified.</span>
            <span>Denominator: {{ $categoryDenominatorLabel }} · {{ $unclassifiedCount }} unclassified/historical. {{ $categoryPrivacyNote }}</span>
            <form method="get" action="{{ route('admin.reports') }}" class="ar-period-filter">
                <label for="emotion-period">Period</label>
                <select id="emotion-period" name="period" onchange="this.form.submit()">
                    @foreach(['all' => 'All available time', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'] as $value => $label)
                        <option value="{{ $value }}" @selected($categoryPeriod === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="ar-category-grid" aria-label="Emotion reporting categories">
            @foreach($categoryStats as $category)
                <button type="button" class="ar-category-card" data-category-key="{{ $category['key'] }}" aria-controls="emotionCategoryDrilldown">
                    <strong>{{ $category['label'] }}</strong>
                    <span class="ar-category-count">{{ $category['suppressed'] ? '—' : $category['count'] }}</span>
                    <small>{{ $category['suppressed'] ? 'Suppressed for privacy' : number_format($category['percentage'], 1).'%' }} · view details</small>
                </button>
            @endforeach
        </div>

        <div id="emotionCategoryDrilldown" class="ar-drilldown" hidden>
            <div class="ar-drilldown-head">
                <strong data-drilldown-title>Category details</strong>
                <button type="button" data-drilldown-close>Back to categories</button>
            </div>
            <div class="ar-drilldown-list" data-drilldown-list></div>
        </div>

        <div class="ar-distribution-grid">
            <article class="ar-chart">
                <h2>Overall emotion breakdown</h2>
                <p>Distribution across all available emotional entries.</p>
                <div class="ar-chart-wrap"><canvas id="emotionDistributionChart"></canvas></div>
            </article>

            <div class="ar-emotion-list">
                @forelse($emotionStats as $emotion)
                    <article class="ar-emotion-row">
                        <span>
                            <strong>{{ $emotion->detected_emotion }}</strong>
                            <span>Detected emotion category</span>
                        </span>
                        <span class="ar-emotion-count">{{ $emotion->count }}</span>
                    </article>
                @empty
                    <div class="ar-empty"><strong>No emotion data</strong>No aggregated emotion records are available.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="ar-panel" data-report-panel="risk">
        <div class="ar-intro">
            <div>
                <strong>Crisis distribution</strong>
                <p>Risk levels are isolated from general trend charts so crisis data remains clear and does not visually compete with routine analytics.</p>
            </div>
            <span class="ar-pill">Risk signal</span>
        </div>

        @if($crisisStats->count() > 0)
            <div class="ar-risk-grid">
                @foreach($crisisStats as $crisis)
                    @php
                        $riskClass = in_array((string) $crisis->crisis_level, ['critical','3','severe'], true)
                            ? 'danger'
                            : (in_array((string) $crisis->crisis_level, ['moderate','2'], true) ? 'warning' : 'safe');
                    @endphp

                    <article class="ar-risk {{ $riskClass }}">
                        <span>Level {{ $crisis->crisis_level }}</span>
                        <strong>{{ $crisis->count }}</strong>
                        <p>recorded entries</p>
                    </article>
                @endforeach
            </div>
        @else
            <div class="ar-empty">
                <strong>No crisis distribution data</strong>
                Crisis-level aggregates will appear here when available.
            </div>
        @endif
    </section>

    <section class="ar-panel" data-report-panel="engagement">
        <div class="ar-intro">
            <div>
                <strong>Student engagement</strong>
                <p>Identify the students contributing the most journal activity in the current report dataset.</p>
            </div>
            <span class="ar-pill">Journal participation</span>
        </div>

        @if($activeStudents->count() > 0)
            <div class="ar-student-list">
                @foreach($activeStudents as $student)
                    <article class="ar-student">
                        <span class="ar-student-avatar">{{ strtoupper(substr($student->user->name ?? 'U', 0, 1)) }}</span>
                        <span class="ar-student-copy">
                            <strong>{{ $student->user->name ?? 'Unknown' }}</strong>
                            <span>{{ $student->user->email ?? 'N/A' }}</span>
                        </span>
                        <span class="ar-student-count">
                            <strong>{{ $student->entry_count }}</strong>
                            <span>entries</span>
                        </span>
                    </article>
                @endforeach
            </div>
        @else
            <div class="ar-empty">
                <strong>No engagement data</strong>
                Active student journal participation will appear here.
            </div>
        @endif
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-report-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-report-panel]'));
    const charts = [];

    function openReportTab(name) {
        tabButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.reportTab === name);
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.reportPanel === name);
        });

        requestAnimationFrame(function () {
            charts.forEach(function (chart) {
                if (chart && typeof chart.resize === 'function') chart.resize();
            });
        });
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openReportTab(button.dataset.reportTab);
        });
    });

    if (typeof Chart === 'undefined') return;

    Chart.defaults.color = '#71837A';
    Chart.defaults.borderColor = 'rgba(16,63,50,.08)';
    Chart.defaults.font.family = 'Inter, sans-serif';

    const emotionColors = {
        happy:   { bg:'rgba(47,135,103,.22)', border:'#2F8767' },
        sad:     { bg:'rgba(75,126,143,.20)', border:'#4B7E8F' },
        anxious: { bg:'rgba(193,139,47,.20)', border:'#C18B2F' },
        angry:   { bg:'rgba(185,94,87,.18)', border:'#B95E57' },
        neutral: { bg:'rgba(122,154,136,.20)', border:'#7A9A88' }
    };
    const categoryColors = {
        positive_wellbeing: { bg:'rgba(47,135,103,.18)', border:'#2F8767' },
        sadness_disconnection: { bg:'rgba(75,126,143,.18)', border:'#4B7E8F' },
        anxiety_stress: { bg:'rgba(193,139,47,.18)', border:'#C18B2F' },
        anger_aversion: { bg:'rgba(185,94,87,.17)', border:'#B95E57' },
        shame_self_doubt: { bg:'rgba(142,103,130,.17)', border:'#8E6782' },
        disengagement_confusion: { bg:'rgba(118,137,153,.17)', border:'#768999' },
        neutral: { bg:'rgba(122,154,136,.18)', border:'#7A9A88' },
        context_dependent: { bg:'rgba(218,169,75,.18)', border:'#C79834' }
    };

    const trendsCanvas = document.getElementById('emotionTrendsChart');
    if (trendsCanvas) {
        charts.push(new Chart(trendsCanvas, {
            type:'line',
            data:{
                labels:@json($last7Days),
                datasets:[
                    @foreach($taxonomy->categories() as $category)
                        { label:@json($category['label']), data:@json($categoryTrends[$category['key']] ?? []), borderColor:categoryColors[@json($category['key'])].border, backgroundColor:categoryColors[@json($category['key'])].bg, tension:.38, fill:false, borderWidth:2, pointRadius:3 },
                    @endforeach
                ]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                interaction:{ mode:'index', intersect:false },
                plugins:{
                    legend:{ position:'bottom', labels:{ padding:10, boxWidth:9, font:{ size:9 } } },
                    tooltip:{ backgroundColor:'#103F32', padding:10, cornerRadius:8 }
                },
                scales:{
                    x:{ grid:{ display:false } },
                    y:{ beginAtZero:true, ticks:{ precision:0 }, grid:{ color:'rgba(16,63,50,.07)' } }
                }
            }
        }));
    }

    const monthlyCanvas = document.getElementById('monthlyTrendChart');
    if (monthlyCanvas) {
        charts.push(new Chart(monthlyCanvas, {
            type:'bar',
            data:{
                labels:@json($monthlyLabels),
                datasets:[{
                    label:'Journal Entries',
                    data:@json($monthlyData),
                    backgroundColor:'rgba(35,107,85,.68)',
                    borderColor:'#236B55',
                    borderWidth:1,
                    borderRadius:7,
                    borderSkipped:false
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                plugins:{
                    legend:{ display:false },
                    tooltip:{ backgroundColor:'#103F32', padding:10, cornerRadius:8 }
                },
                scales:{
                    x:{ grid:{ display:false } },
                    y:{ beginAtZero:true, ticks:{ precision:0 }, grid:{ color:'rgba(16,63,50,.07)' } }
                }
            }
        }));
    }

    const distributionCanvas = document.getElementById('emotionDistributionChart');
    if (distributionCanvas) {
        const categoryData = @json($categoryStats);

        charts.push(new Chart(distributionCanvas, {
            type:'doughnut',
            data:{
                labels:categoryData.map(function (item) { return item.label; }),
                datasets:[{
                    data:categoryData.map(function (item) { return item.count; }),
                    backgroundColor:categoryData.map(function (item) {
                        return categoryColors[item.key]?.border || '#91A098';
                    }),
                    borderColor:'#FFFFFF',
                    borderWidth:2
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                cutout:'64%',
                plugins:{
                    legend:{ position:'bottom', labels:{ padding:10, boxWidth:9, font:{ size:9 } } },
                    tooltip:{
                        backgroundColor:'#103F32',
                        padding:10,
                        cornerRadius:8,
                        callbacks:{
                            label:function (context) {
                                const total = context.dataset.data.reduce(function (sum, value) { return sum + value; }, 0);
                                const percentage = total ? ((context.parsed / total) * 100).toFixed(1) : '0.0';
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        }));
    }

    const categoryButtons = Array.from(document.querySelectorAll('[data-category-key]'));
    const drilldown = document.getElementById('emotionCategoryDrilldown');
    const drilldownTitle = drilldown?.querySelector('[data-drilldown-title]');
    const drilldownList = drilldown?.querySelector('[data-drilldown-list]');
    const categoryDrilldown = @json($categoryDrilldown->toArray());
    const closeDrilldown = drilldown?.querySelector('[data-drilldown-close]');

    function showCategoryDetails(key) {
        const category = categoryDrilldown[key];
        if (!category || !drilldown || !drilldownList) return;
        categoryButtons.forEach(function (button) { button.classList.toggle('active', button.dataset.categoryKey === key); });
        if (drilldownTitle) drilldownTitle.textContent = category.label + ' · detailed labels';
        drilldownList.innerHTML = '';
        category.emotions.forEach(function (emotion) {
            const row = document.createElement('div');
            row.className = 'ar-drilldown-row';
            row.innerHTML = '<strong></strong><span></span>';
            row.querySelector('strong').textContent = emotion.label;
            row.querySelector('span').textContent = String(emotion.count) + ' messages';
            drilldownList.appendChild(row);
        });
        drilldown.hidden = false;
        drilldown.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }

    categoryButtons.forEach(function (button) {
        button.addEventListener('click', function () { showCategoryDetails(button.dataset.categoryKey); });
    });
    closeDrilldown?.addEventListener('click', function () {
        if (drilldown) drilldown.hidden = true;
        categoryButtons.forEach(function (button) { button.classList.remove('active'); });
    });
});
</script>
@endsection
