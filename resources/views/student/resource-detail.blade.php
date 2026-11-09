@extends('layouts.app')

@section('title', $resource->title . ' - MAGIREM AI')

@section('content')
<style>
.resource-detail{max-width:860px;margin:2rem auto 4rem;color:#263a33}.resource-detail-card{background:#fff;border:1px solid rgba(16,63,50,.12);border-radius:22px;padding:clamp(1.25rem,4vw,2.5rem);box-shadow:0 16px 36px rgba(16,63,50,.08)}.resource-detail-meta{color:#236b55;font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.resource-detail h1{color:#103f32;margin:.5rem 0 1rem;font-size:clamp(1.8rem,4vw,2.6rem)}.resource-detail p{color:#50685d;line-height:1.75;white-space:pre-line}.resource-detail-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}.resource-detail-actions a{display:inline-flex;border-radius:10px;padding:.7rem 1rem;background:#236b55;color:#fff;text-decoration:none;font-weight:800}.resource-detail-actions a.secondary{background:#e6f1eb;color:#236b55}.resource-detail-warning{margin-top:1rem;padding:.8rem 1rem;background:#fff8df;border:1px solid #f4c84e;border-radius:12px;color:#5b4a12;line-height:1.5}
</style>
<div class="resource-detail">
    <article class="resource-detail-card">
        <div class="resource-detail-meta">{{ ucwords(str_replace(['-','_'],' ',$resource->category ?: 'general')) }}</div>
        <h1>{{ $resource->title }}</h1>
        @if($resource->description)<p>{{ $resource->description }}</p>@endif
        @if($resource->content)<p>{{ $resource->content }}</p>@endif
        @if($resource->url && str_starts_with(strtolower((string)$resource->url), 'https://'))<div class="resource-detail-actions"><a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer">Open source link</a></div>@endif
        <p class="resource-detail-warning">This is general wellness information. It is not a diagnosis or a substitute for professional assessment. For urgent danger, use Emergency Resources.</p>
        <div class="resource-detail-actions"><a class="secondary" href="{{ route('student.resources') }}">Back to resources</a><a class="secondary" href="{{ route('student.coping-toolkit') }}">Try coping tools</a><a class="secondary" href="{{ route('student.emergency-resources') }}">Emergency resources</a></div>
    </article>
</div>
@endsection