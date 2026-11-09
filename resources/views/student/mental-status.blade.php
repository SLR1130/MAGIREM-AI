@extends('layouts.app')

@section('title', 'Daily Mental Status')

@section('content')
<style>
    :root {
        --bg-primary: #0f0b1a;
        --bg-secondary: #1a1625;
        --bg-tertiary: #251f35;
        --purple-main: #8b5cf6;
        --purple-light: #a78bfa;
        --text-primary: #e9e3f5;
        --text-secondary: #b8aed4;
        --border-color: #2d2640;
    }
    
    body {
        background: linear-gradient(135deg, #0f0b1a 0%, #1a1230 100%);
        color: var(--text-primary);
        min-height: 100vh;
    }
    
    .status-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .status-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .status-header h1 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, var(--purple-main), var(--purple-light));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .status-header p {
        color: var(--text-secondary);
        font-size: 0.95rem;
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .status-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.3s;
    }
    
    .status-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
    }
    
    .card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .card-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--purple-main), var(--purple-light));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .card-icon svg {
        width: 24px;
        height: 24px;
        color: white;
    }
    
    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .mood-display {
        text-align: center;
        padding: 2rem 0;
    }
    
    .mood-emoji {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .mood-label {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: capitalize;
    }
    
    .mood-score {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--purple-main), var(--purple-light));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .mood-score-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .emotion-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .emotion-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border-radius: 8px;
    }
    
    .emotion-name {
        text-transform: capitalize;
        font-weight: 500;
    }
    
    .emotion-count {
        background: var(--purple-main);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .recommendations {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .recommendation-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        background: var(--bg-tertiary);
        border-radius: 12px;
        border-left: 3px solid var(--purple-main);
        transition: all 0.2s;
    }
    
    .recommendation-item:hover {
        background: var(--bg-primary);
        transform: translateX(4px);
    }
    
    .rec-icon {
        width: 40px;
        height: 40px;
        background: rgba(139, 92, 246, 0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .rec-icon svg {
        width: 20px;
        height: 20px;
        color: var(--purple-light);
    }
    
    .rec-content {
        flex: 1;
    }
    
    .rec-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .rec-description {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .rec-action {
        display: inline-block;
        color: var(--purple-light);
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
    }
    
    .rec-action:hover {
        color: var(--purple-main);
    }
    
    .chart-container {
        background: var(--bg-tertiary);
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }
    
    .loading {
        text-align: center;
        padding: 3rem;
        color: var(--text-secondary);
    }
    
    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid var(--border-color);
        border-top-color: var(--purple-main);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .no-data {
        text-align: center;
        padding: 3rem;
        color: var(--text-secondary);
    }
    
    .no-data-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .crisis-alert {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .crisis-alert svg {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
    }
</style>

<div class="status-container">
    <div class="status-header">
        <h1>📊 Daily Mental Status</h1>
        <p>Track your emotional wellbeing and get personalized recommendations</p>
    </div>
    
    <div id="loadingState" class="loading">
        <div class="loading-spinner"></div>
        <p>Loading your mental status...</p>
    </div>
    
    <div id="statusContent" style="display: none;">
        <!-- Crisis Alert (if needed) -->
        <div id="crisisAlert" style="display: none;" class="crisis-alert">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
                <strong>We're concerned about you.</strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem;">Please consider talking to a counselor or calling a crisis hotline.</p>
            </div>
        </div>
        
        <div class="status-grid">
            <!-- Overall Mood Card -->
            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                        </svg>
                    </div>
                    <h2 class="card-title">Overall Mood</h2>
                </div>
                <div class="mood-display">
                    <div class="mood-emoji" id="moodEmoji">😊</div>
                    <div class="mood-label" id="moodLabel">Loading...</div>
                    <div class="mood-score" id="moodScore">--</div>
                    <div class="mood-score-label">Mood Score</div>
                </div>
            </div>
            
            <!-- Emotions Breakdown Card -->
            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <h2 class="card-title">Today's Emotions</h2>
                </div>
                <div class="emotion-list" id="emotionList">
                    <!-- Emotions will be loaded here -->
                </div>
            </div>
            
            <!-- Activity Stats Card -->
            <div class="status-card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                        </svg>
                    </div>
                    <h2 class="card-title">Activity Today</h2>
                </div>
                <div style="padding: 1rem 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <span style="color: var(--text-secondary);">Messages</span>
                        <span style="font-weight: 600; font-size: 1.25rem;" id="messageCount">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <span style="color: var(--text-secondary);">Last Updated</span>
                        <span style="font-weight: 500;" id="lastUpdated">--</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Crisis Level</span>
                        <span style="font-weight: 600; text-transform: capitalize;" id="crisisLevel">none</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommendations Section -->
        <div class="status-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
                </div>
                <h2 class="card-title">Personalized Recommendations</h2>
            </div>
            <div class="recommendations" id="recommendations">
                <!-- Recommendations will be loaded here -->
            </div>
        </div>
        
        <!-- Weekly Trend Chart -->
        <div class="status-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                    </svg>
                </div>
                <h2 class="card-title">7-Day Mood Trend</h2>
            </div>
            <div class="chart-container">
                <canvas id="trendChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadMentalStatus();
});

async function loadMentalStatus() {
    try {
        const response = await fetch('/student/api/mental-status', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayMentalStatus(data.daily, data.weekly);
        }
    } catch (error) {
        console.error('Error loading mental status:', error);
        document.getElementById('loadingState').innerHTML = '<div class="no-data"><div class="no-data-icon">⚠️</div><p>Failed to load mental status data</p></div>';
    }
}

function displayMentalStatus(daily, weekly) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('statusContent').style.display = 'block';
    
    if (daily.status === 'no_data') {
        document.getElementById('statusContent').innerHTML = `
            <div class="no-data">
                <div class="no-data-icon">💭</div>
                <h3>No Data Yet Today</h3>
                <p>${daily.message}</p>
                <a href="/student/ai-chat" style="display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: var(--purple-main); color: white; text-decoration: none; border-radius: 8px;">Start Chatting</a>
            </div>
        `;
        return;
    }
    
    // Show crisis alert if needed
    if (daily.crisis_level === 'high' || daily.crisis_level === 'critical') {
        document.getElementById('crisisAlert').style.display = 'flex';
    }
    
    // Update mood display
    const moodEmojis = {
        'positive': '😊',
        'neutral': '😐',
        'negative': '😔',
        'concerned': '😟'
    };
    
    document.getElementById('moodEmoji').textContent = moodEmojis[daily.overall_mood] || '😐';
    document.getElementById('moodLabel').textContent = daily.overall_mood;
    document.getElementById('moodScore').textContent = daily.mood_score;
    
    // Update emotions list
    const emotionList = document.getElementById('emotionList');
    emotionList.innerHTML = '';
    for (const [emotion, count] of Object.entries(daily.emotions)) {
        emotionList.innerHTML += `
            <div class="emotion-item">
                <span class="emotion-name">${emotion}</span>
                <span class="emotion-count">${count}</span>
            </div>
        `;
    }
    
    // Update activity stats
    document.getElementById('messageCount').textContent = daily.message_count;
    document.getElementById('lastUpdated').textContent = daily.last_updated;
    document.getElementById('crisisLevel').textContent = daily.crisis_level;
    
    // Update recommendations
    const recommendations = document.getElementById('recommendations');
    recommendations.innerHTML = '';
    daily.recommendations.forEach(rec => {
        recommendations.innerHTML += `
            <div class="recommendation-item">
                <div class="rec-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                    </svg>
                </div>
                <div class="rec-content">
                    <div class="rec-title">${rec.title}</div>
                    <div class="rec-description">${rec.description}</div>
                    <a href="${rec.url}" class="rec-action">${rec.action} →</a>
                </div>
            </div>
        `;
    });
    
    // Draw trend chart
    drawTrendChart(weekly);
}

function drawTrendChart(weeklyData) {
    const ctx = document.getElementById('trendChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: weeklyData.map(d => d.date),
            datasets: [{
                label: 'Mood Score',
                data: weeklyData.map(d => d.mood_score),
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: '#2d2640'
                    },
                    ticks: {
                        color: '#b8aed4'
                    }
                },
                x: {
                    grid: {
                        color: '#2d2640'
                    },
                    ticks: {
                        color: '#b8aed4'
                    }
                }
            }
        }
    });
}
</script>
@endsection
