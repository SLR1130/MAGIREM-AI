@extends('layouts.app')

@section('title', 'Wellbeing Tracker - MAGIREM AI')

@section('content')
<div style="margin-bottom: 2rem;">
    <a href="{{ route('student.insights') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--purple-600); text-decoration: none; margin-bottom: 1rem;">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Back to Dashboard
    </a>
    <h1 style="color: var(--purple-900); font-size: 2rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        Wellbeing Progress Tracker
    </h1>
    <p style="color: var(--purple-600);">Monitor your emotional stability and stress levels over time</p>
</div>

<!-- Progress Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="background: linear-gradient(135deg, #10b981, #059669);">
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Emotional Stability</h3>
        <div style="font-size: 2.5rem; color: white; font-weight: bold;">{{ $progress->emotional_stability }}%</div>
        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0 0;">Non-clinical indicator</p>
    </div>

    <div class="card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Stress Level</h3>
        <div style="font-size: 2.5rem; color: white; font-weight: bold;">{{ $progress->stress_level }}%</div>
        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0 0;">Non-clinical indicator</p>
    </div>

    <div class="card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Mood Entries</h3>
        <div style="font-size: 2.5rem; color: white; font-weight: bold;">{{ $moodHistory->count() }}</div>
        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0 0;">This month</p>
    </div>

    <div class="card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
        <h3 style="color: white; margin: 0 0 0.5rem 0;">Wellbeing Score</h3>
        <div style="font-size: 2.5rem; color: white; font-weight: bold;">{{ $progress->wellbeing_score }}%</div>
        <p style="color: rgba(255,255,255,0.8); margin: 0.5rem 0 0 0;">Daily non-clinical indicator</p>
    </div>
</div>

<!-- Mood History -->
<div class="card">
    <h2 style="color: var(--purple-900); margin-bottom: 1rem;">📊 Recent Mood History</h2>
    
    @if($moodHistory->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--purple-200);">
                        <th style="text-align: left; padding: 1rem; color: var(--purple-900);">Date</th>
                        <th style="text-align: left; padding: 1rem; color: var(--purple-900);">Mood</th>
                        <th style="text-align: left; padding: 1rem; color: var(--purple-900);">Intensity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($moodHistory as $mood)
                        <tr style="border-bottom: 1px solid var(--purple-100);">
                            <td style="padding: 1rem; color: var(--purple-700);">{{ $mood->created_at->format('M d, Y H:i') }}</td>
                            <td style="padding: 1rem;">
                                <span style="display: inline-block; padding: 0.5rem 1rem; background: var(--purple-100); color: var(--purple-900); border-radius: 0.5rem; font-weight: bold;">
                                    {{ ucfirst($mood->mood) }}
                                </span>
                            </td>
                            <td style="padding: 1rem; color: var(--purple-700);">{{ $mood->intensity ?? 5 }}/10</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p style="color: var(--purple-600); text-align: center; padding: 2rem;">
            No mood entries yet. Start tracking your mood to see your progress!
        </p>
    @endif
</div>

<!-- Insights -->
<div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100));">
    <h3 style="color: var(--purple-900); margin-bottom: 1rem;">💡 Insights</h3>
    <ul style="color: var(--purple-700); line-height: 1.8;">
        <li>Your current non-clinical wellbeing indicator is {{ $progress->wellbeing_score }}/100.</li>
        <li>Your recorded stress indicator is {{ $progress->stress_level }}/100.</li>
        @if ($moodHistory->isEmpty())
            <li>Complete a mood check-in to begin building a personal trend.</li>
        @else
            <li>{{ $moodHistory->count() }} mood {{ Str::plural('check-in', $moodHistory->count()) }} recorded this month.</li>
        @endif
        <li>These indicators support reflection and are not a clinical diagnosis.</li>
    </ul>
</div>
@endsection
