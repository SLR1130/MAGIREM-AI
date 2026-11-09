@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Student Mental Health Monitoring</h2>
        </div>
    </div>

    <!-- Alert Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Critical Alerts</h5>
                    <p class="card-text display-4">
                        {{ $alerts->where('severity', 'critical')->count() }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">High Alerts</h5>
                    <p class="card-text display-4">
                        {{ $alerts->where('severity', 'high')->count() }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Moderate Alerts</h5>
                    <p class="card-text display-4">
                        {{ $alerts->where('severity', 'moderate')->count() }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h5 class="card-title">Total Pending</h5>
                    <p class="card-text display-4">
                        {{ $alerts->count() }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pending Alerts</h5>
                </div>
                <div class="card-body">
                    @forelse($alerts as $alert)
                        <div class="alert-item mb-3 p-3 border rounded" style="border-left: 5px solid 
                            @if($alert->severity === 'critical') #dc3545
                            @elseif($alert->severity === 'high') #fd7e14
                            @elseif($alert->severity === 'moderate') #0dcaf0
                            @else #6c757d
                            @endif
                        ;">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <strong>{{ $alert->student ? 'Student #' . ($alert->student->student_id ?? 'identifier unavailable') : 'Student record unavailable' }}</strong>
                                        <span class="badge badge-{{ 
                                            $alert->severity === 'critical' ? 'danger' : 
                                            ($alert->severity === 'high' ? 'warning' : 
                                            ($alert->severity === 'moderate' ? 'info' : 'secondary'))
                                        }}">
                                            {{ strtoupper($alert->severity) }}
                                        </span>
                                    </h6>
                                    <p class="mb-2">{{ $alert->message }}</p>
                                    <p class="text-muted small mb-0">
                                        <strong>Alert Type:</strong> {{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }} | 
                                        <strong>Created:</strong> {{ $alert->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <button class="btn btn-sm btn-primary acknowledge-alert" 
                                            data-alert-id="{{ $alert->id }}"
                                            @if($alert->status !== 'pending') disabled @endif>
                                        Acknowledge
                                    </button>
                                    <button class="btn btn-sm btn-success resolve-alert" 
                                            data-alert-id="{{ $alert->id }}"
                                            @if($alert->status !== 'pending') disabled @endif>
                                        Resolve
                                    </button>
@if($alert->student)
                                        <a href="{{ route('counselor.student-profile', $alert->student->id) }}" class="btn btn-sm btn-info">
                                            View Profile
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading">Great news!</h4>
                            <p>No pending alerts. All students are doing well! 🎉</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Pagination -->
            @if($alerts->hasPages())
                <div class="mt-3">
                    {{ $alerts->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Acknowledge alert
    document.querySelectorAll('.acknowledge-alert').forEach(btn => {
        btn.addEventListener('click', function() {
            const alertId = this.dataset.alertId;
            fetch(`/counselor/alerts/${alertId}/acknowledge`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Alert acknowledged');
                    location.reload();
                }
            });
        });
    });

    // Resolve alert
    document.querySelectorAll('.resolve-alert').forEach(btn => {
        btn.addEventListener('click', function() {
            const alertId = this.dataset.alertId;
            fetch(`/counselor/alerts/${alertId}/resolve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Alert resolved');
                    location.reload();
                }
            });
        });
    });
});
</script>
@endsection
