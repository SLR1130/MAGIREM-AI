@php
    $activityName = (string) data_get($activity, 'name', 'Guided practice');
    $activityInstructions = trim((string) data_get($activity, 'instructions', ''));
    $activityCategory = (string) data_get($activity, 'category', 'wellness');
    $activityDuration = $activity instanceof \App\Models\CopingStrategy ? data_get($activity, 'duration_minutes') : null;
@endphp
<article class="activity-card">
    <div class="activity-card-top">
        <span class="activity-icon activity-icon-{{ $activityIcon($activity) }}" aria-hidden="true"></span>
        <div class="activity-title-wrap"><h3>{{ $activityName }}</h3></div>
    </div>
    <div class="activity-meta">
        <span>{{ $categoryLabel($activityCategory) }}</span>
        @if(data_get($activity, 'duration_minutes') && $activity instanceof \App\Models\CopingStrategy)
            <span class="activity-duration">{{ data_get($activity, 'duration_minutes') }} min</span>
        @endif
    </div>
    <p>{{ data_get($activity, 'description') ?: 'A guided wellness practice from the MAGIREM AI toolkit.' }}</p>
    @if($activityInstructions !== '')
        <details><summary>View steps</summary><p>{{ $activityInstructions }}</p></details>
        <div class="activity-card-actions"><button type="button" class="exercise-button" data-practice-launch data-activity-name="{{ $activityName }}" data-activity-kind="{{ $activityCategory }}" data-activity-instructions="{{ $activityInstructions }}" @if($activityDuration) data-activity-duration="{{ (int) $activityDuration }}" @endif>Start activity</button></div>
    @else
        <div class="activity-card-actions"><span class="activity-unavailable">Practice instructions are not available yet.</span></div>
    @endif
</article>
