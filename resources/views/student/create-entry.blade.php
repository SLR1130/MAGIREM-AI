@extends('layouts.app')

@section('title', 'Create Emotional Entry')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <h1 style="color: var(--purple-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
    <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    Express Your Feelings
</h1>
    <p style="color: var(--purple-600); margin-bottom: 2rem;">
        This is a safe space to share what you're feeling. Your entry is confidential and will help you track your emotional well-being.
    </p>

    <div class="card">
        <form method="POST" action="{{ route('student.store-entry') }}">
            @csrf

            <div>
                <label for="entry_text">How are you feeling today?</label>
                <textarea 
                    id="entry_text" 
                    name="entry_text" 
                    rows="8" 
                    placeholder="Share your thoughts, feelings, and experiences... (minimum 10 characters)"
                    required
                >{{ old('entry_text') }}</textarea>
            </div>

            <div style="display: flex; align-items: center; margin-bottom: 1.5rem;">
                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" checked style="width: auto; margin-right: 0.5rem;">
                <label for="is_anonymous" style="margin: 0; color: var(--purple-700);">
                    Keep this entry anonymous (recommended)
                </label>
            </div>

            <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;">
                    <strong>Note:</strong> Our AI will analyze your entry to detect emotions and provide supportive feedback. 
                    This helps you gain insights into your emotional patterns.
                </p>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-purple">Submit Entry</button>
                <a href="{{ route('student.insights') }}" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: 2px solid var(--purple-600); color: var(--purple-600); text-decoration: none; display: inline-block;">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); margin-top: 2rem;">
        <h3 style="color: var(--purple-900); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
            Helpful Prompts
        </h3>
        <p style="color: var(--purple-700); margin-bottom: 0.5rem;">Not sure what to write? Try these:</p>
        <ul style="color: var(--purple-700); line-height: 1.8;">
            <li>What made you smile today?</li>
            <li>What challenges are you facing right now?</li>
            <li>How did you handle stress today?</li>
            <li>What are you grateful for?</li>
            <li>What's on your mind that you need to express?</li>
        </ul>
    </div>
</div>
@endsection
