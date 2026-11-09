@extends('layouts.app')

@section('title', 'Reflection Prompts - MAGIREM AI')

@section('content')
<div style="margin-bottom: 2rem;">
    <a href="{{ route('student.insights') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--purple-600); text-decoration: none; margin-bottom: 1rem;">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Back to Dashboard
    </a>
    <h1 style="color: var(--purple-900); font-size: 2rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Smart Reflection Prompts
    </h1>
    <p style="color: var(--purple-600);">Deepen your emotional awareness with guided reflection questions</p>
</div>

<!-- Reflection Prompts -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
    @forelse($prompts as $prompt)
        <div class="card" style="border-top: 4px solid var(--purple-500); background: linear-gradient(135deg, var(--purple-50), white);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <h3 style="color: var(--purple-900); margin: 0; flex: 1;">{{ $prompt->prompt_text }}</h3>
                <span style="background: var(--purple-200); color: var(--purple-900); padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.85rem; white-space: nowrap;">
                    {{ $prompt->category }}
                </span>
            </div>
            
            <p style="color: var(--purple-600); margin-bottom: 1rem; font-style: italic;">{{ $prompt->description }}</p>
            
            <textarea placeholder="Write your reflection here..." style="width: 100%; min-height: 120px; padding: 1rem; border: 2px solid var(--purple-200); border-radius: 0.5rem; font-family: inherit; resize: vertical;"></textarea>
            
            <button style="margin-top: 1rem; background: var(--purple-600); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: bold;">
                Save Reflection
            </button>
        </div>
    @empty
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
            <svg class="icon-xl" style="margin: 0 auto 1rem; color: var(--purple-300);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p style="color: var(--purple-600); font-size: 1.1rem;">No reflection prompts available yet.</p>
            <p style="color: var(--purple-500);">Check back soon for personalized prompts based on your emotional patterns.</p>
        </div>
    @endforelse
</div>

<!-- Reflection Tips -->
<div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); margin-top: 2rem;">
    <h3 style="color: var(--purple-900); margin-bottom: 1rem;">💡 Reflection Tips</h3>
    <ul style="color: var(--purple-700); line-height: 1.8;">
        <li>✅ Be honest and authentic in your reflections</li>
        <li>✅ Take your time - there's no rush</li>
        <li>✅ Write freely without worrying about grammar</li>
        <li>✅ Reflect regularly to track your growth</li>
        <li>✅ Use reflections to understand your patterns</li>
        <li>✅ Share insights with your counselor if comfortable</li>
    </ul>
</div>
@endsection
