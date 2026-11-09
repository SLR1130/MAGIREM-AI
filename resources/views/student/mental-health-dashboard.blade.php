@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Your Mental Health Status</h2>
        </div>
    </div>

    <!-- Mental Status Score Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Mental Status Score</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h1 class="display-4">{{ $mentalStatus['score'] }}/100</h1>
                        <p class="text-muted">{{ $mentalStatus['status'] }}</p>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress" style="height: 30px;">
                        @php
                            $score = $mentalStatus['score'];
                            $color = 'success';
                            if ($score < 30) $color = 'danger';
                            elseif ($score < 40) $color = 'danger';
                            elseif ($score < 60) $color = 'warning';
                            elseif ($score < 80) $color = 'info';
                        @endphp
                        <div class="progress-bar bg-{{ $color }}" role="progressbar" 
                             style="width: {{ $score }}%"
                             aria-valuenow="{{ $score }}" 
                             aria-valuemin="0" aria-valuemax="100">
                            {{ $score }}%
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <p><strong>Current Emotion:</strong> <span class="badge badge-info">{{ ucfirst($mentalStatus['emotion']) }}</span></p>
                        <p><strong>Crisis Level:</strong> <span class="badge badge-warning">{{ ucfirst($mentalStatus['crisis_level']) }}</span></p>
                        <p><strong>Late Night Usage:</strong> 
                            @if($mentalStatus['late_night_usage'])
                                <span class="badge badge-danger">Yes</span>
                            @else
                                <span class="badge badge-success">No</span>
                            @endif
                        </p>
                        <p class="text-muted small">Last updated: {{ $mentalStatus['last_updated']->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Explanation -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">What This Means</h5>
                </div>
                <div class="card-body">
                    @php
                        $score = $mentalStatus['score'];
                        if ($score >= 80) {
                            $explanation = "You're doing great! Your mental health is in excellent condition. Keep up the positive momentum!";
                            $icon = "😊";
                        } elseif ($score >= 60) {
                            $explanation = "You're doing well overall. There might be some minor concerns, but you're managing well.";
                            $icon = "🙂";
                        } elseif ($score >= 40) {
                            $explanation = "You're experiencing some challenges. Consider reaching out to a counselor for support.";
                            $icon = "😐";
                        } elseif ($score >= 20) {
                            $explanation = "You're going through a difficult time. Please talk to a counselor or trusted person soon.";
                            $icon = "😟";
                        } else {
                            $explanation = "You're in crisis. Please reach out to a counselor or emergency services immediately.";
                            $icon = "🚨";
                        }
                    @endphp
                    
                    <p class="text-center" style="font-size: 3rem;">{{ $icon }}</p>
                    <p>{{ $explanation }}</p>
                    
                    <hr>
                    
                    <h6>💡 Recommendations:</h6>
                    <ul>
                        <li>Get enough sleep (avoid late night usage)</li>
                        <li>Talk to a counselor about your feelings</li>
                        <li>Practice self-care activities</li>
                        <li>Reach out to friends and family</li>
                        <li>Use our coping toolkit for support</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Patterns -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">This Week's Usage Patterns</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Messages</th>
                                    <th>Time of Day</th>
                                    <th>Emotions</th>
                                    <th>Late Night</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($usagePatterns as $date => $patterns)
                                    <tr>
                                        <td><strong>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</strong></td>
                                        <td>{{ $patterns->count() }}</td>
                                        <td>
                                            @php
                                                $times = $patterns->pluck('time_of_day')->unique()->implode(', ');
                                            @endphp
                                            {{ ucfirst($times) }}
                                        </td>
                                        <td>
                                            @php
                                                $emotions = $patterns->pluck('emotion_detected')->unique();
                                            @endphp
                                            @foreach($emotions as $emotion)
                                                <span class="badge badge-secondary">{{ ucfirst($emotion) }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($patterns->where('is_late_night', true)->count() > 0)
                                                <span class="badge badge-danger">{{ $patterns->where('is_late_night', true)->count() }}x</span>
                                            @else
                                                <span class="badge badge-success">None</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No usage data yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trends -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Monthly Trends</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Avg Score</th>
                                    <th>Messages</th>
                                    <th>Late Night</th>
                                    <th>Crisis Events</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trends as $trend)
                                    <tr>
                                        <td><strong>{{ \Carbon\Carbon::parse($trend->week_start)->format('M d') }}</strong></td>
                                        <td>
                                            <span class="badge badge-info">{{ $trend->avg_score }}/100</span>
                                        </td>
                                        <td>{{ $trend->message_count }}</td>
                                        <td>
                                            @if($trend->late_night_count > 0)
                                                <span class="badge badge-danger">{{ $trend->late_night_count }}</span>
                                            @else
                                                <span class="badge badge-success">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($trend->crisis_count > 0)
                                                <span class="badge badge-danger">{{ $trend->crisis_count }}</span>
                                            @else
                                                <span class="badge badge-success">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($trend->trend_direction === 'improving')
                                                <span class="badge badge-success">📈 Improving</span>
                                            @elseif($trend->trend_direction === 'declining')
                                                <span class="badge badge-danger">📉 Declining</span>
                                            @else
                                                <span class="badge badge-info">➡️ Stable</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No trend data yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mt-4 mb-4">
        <div class="col-md-12">
            <a href="{{ route('student.chat') }}" class="btn btn-primary">Chat with AI</a>
            <a href="{{ route('student.counselor-directory') }}" class="btn btn-success">Find a Counselor</a>
            <a href="{{ route('student.emergency-resources') }}" class="btn btn-danger">Emergency Resources</a>
        </div>
    </div>
</div>
@endsection
