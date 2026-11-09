@extends('layouts.app')

@section('title', 'MAGI Companion · MAGIREM AI')

@section('content')
<div style="margin-bottom: 1.5rem;">
    <h1 style="color: var(--purple-900); font-size: 1.5rem; display: flex; align-items: center; gap: 0.4rem;">
        <svg class="icon-lg" fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"></path></svg>
        MAGI
    </h1>
    <p style="color: var(--purple-600); font-size: 0.95rem;">Personal AI support for your emotional wellness</p>
</div>

<!-- AI Companion Features -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Feature 1: Emotion Detection -->
    <div class="card" style="border-left: 4px solid #8b5cf6; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #8b5cf6, #6b46c1); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">Emotion Detection</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            AI analyzes your messages to understand your emotional state and provides personalized support based on what you're feeling.
        </p>
    </div>

    <!-- Feature 2: Conversation History -->
    <div class="card" style="border-left: 4px solid #3b82f6; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #1e40af); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">Conversation History</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            All your conversations are saved and tracked, allowing the AI to understand your journey and provide contextual support.
        </p>
    </div>

    <!-- Feature 3: Crisis Detection -->
    <div class="card" style="border-left: 4px solid #ef4444; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">Crisis Detection</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            The AI identifies signs of crisis and alerts counselors when you need immediate professional support.
        </p>
    </div>

    <!-- Feature 4: Coping Strategies -->
    <div class="card" style="border-left: 4px solid #10b981; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">Coping Strategies</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            Receive personalized coping strategies and techniques tailored to your emotional needs and challenges.
        </p>
    </div>

    <!-- Feature 5: 24/7 Availability -->
    <div class="card" style="border-left: 4px solid #f59e0b; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">24/7 Availability</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            Chat with MAGI anytime, anywhere. Get support whenever you need it, day or night.
        </p>
    </div>

    <!-- Feature 6: Privacy & Security -->
    <div class="card" style="border-left: 4px solid #06b6d4; transition: transform 0.2s, box-shadow 0.2s;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #06b6d4, #0891b2); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h3 style="color: var(--purple-900); margin: 0;">Privacy & Security</h3>
        </div>
        <p style="color: var(--purple-700); line-height: 1.6; margin: 0;">
            Your conversations are encrypted and secure. You control your privacy settings and data.
            <a href="{{ route('student.privacy') }}#privacy" style="display:inline-block;margin-top:.65rem;color:#0891b2;font-weight:600;text-decoration:underline;">Manage Privacy Settings</a>
        </p>
    </div>
</div>

<!-- Stats Section -->
<div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); margin-bottom: 2rem;">
    <h2 style="color: var(--purple-900); margin-bottom: 1.5rem;">Your AI Companion Stats</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--purple-600); margin-bottom: 0.5rem;">{{ $totalConversations }}</div>
            <div style="color: var(--purple-700); font-weight: 600;">Total Conversations</div>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin: 0.5rem 0 0 0;">Messages shared with AI</p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; border-left: 4px solid #3b82f6;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--purple-600); margin-bottom: 0.5rem;">{{ ucfirst($primaryEmotion) }}</div>
            <div style="color: var(--purple-700); font-weight: 600;">Primary Emotion</div>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin: 0.5rem 0 0 0;">Most common feeling</p>
        </div>
        
        <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; text-align: center; border-left: 4px solid #10b981;">
            <div style="font-size: 2.5rem; font-weight: bold; color: var(--purple-600); margin-bottom: 0.5rem;">{{ $streak }}</div>
            <div style="color: var(--purple-700); font-weight: 600;">Day Streak</div>
            <p style="color: var(--purple-600); font-size: 0.9rem; margin: 0.5rem 0 0 0;">Consecutive days chatting</p>
        </div>
    </div>
</div>

<!-- Call to Action -->
<div class="card" style="background: linear-gradient(135deg, #8b5cf6, #6b46c1); color: white; text-align: center; padding: 2rem;">
    <h2 style="margin-bottom: 1rem; color: white;">Ready to Chat?</h2>
    <p style="color: rgba(255, 255, 255, 0.9); margin-bottom: 1.5rem; font-size: 1.1rem;">
        Start a conversation with MAGI and get personalized emotional support
    </p>
    <a href="{{ route('student.ai-chat') }}" style="display: inline-block; background: white; color: #8b5cf6; padding: 0.75rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s;">
        Start Chatting Now
    </a>
</div>

<style>
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(139, 92, 246, 0.15);
    }
</style>
@endsection
