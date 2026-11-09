@extends('layouts.app')

@section('title', 'Wellness Reminders - MAGIREM AI')

@section('content')
<div style="margin-bottom: 2rem;">
    <a href="{{ route('student.insights') }}" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--purple-600); text-decoration: none; margin-bottom: 1rem;">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Back to Dashboard
    </a>
    <h1 style="color: var(--purple-900); font-size: 2rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg class="icon-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
        Daily Wellness Reminders
    </h1>
    <p style="color: var(--purple-600);">Stay on track with personalized wellness reminders</p>
</div>

<!-- Active Reminders -->
<div style="margin-bottom: 2rem;">
    <h2 style="color: var(--purple-900); margin-bottom: 1rem;">🔔 Your Active Reminders</h2>
    
    @if($reminders->count() > 0)
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            @foreach($reminders as $reminder)
                <div class="card" style="border-left: 4px solid var(--purple-500);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <h3 style="color: var(--purple-900); margin: 0;">{{ $reminder->title }}</h3>
                        <span style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.85rem;">
                            Active
                        </span>
                    </div>
                    
                    <p style="color: var(--purple-600); margin-bottom: 1rem;">{{ $reminder->description }}</p>
                    
                    <div style="background: var(--purple-50); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <p style="color: var(--purple-700); margin: 0; font-size: 0.9rem;">
                            <strong>Time:</strong> {{ $reminder->reminder_time ?? 'Daily' }}<br>
                            <strong>Frequency:</strong> {{ $reminder->frequency ?? 'Daily' }}
                        </p>
                    </div>
                    
                    <button style="background: var(--purple-600); color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer; margin-right: 0.5rem;">
                        Edit
                    </button>
                    <button style="background: #ef4444; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; cursor: pointer;">
                        Disable
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <div class="card" style="text-align: center; padding: 2rem;">
            <svg class="icon-xl" style="margin: 0 auto 1rem; color: var(--purple-300);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            <p style="color: var(--purple-600); font-size: 1.1rem;">No reminders set yet.</p>
        </div>
    @endif
</div>

<!-- Available Reminder Templates -->
<div class="card">
    <h2 style="color: var(--purple-900); margin-bottom: 1rem;">➕ Add New Reminder</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">😊 Mood Check-In</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Daily reminder to check your mood</p>
        </div>

        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">📝 Journal Entry</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Reminder to write your daily journal</p>
        </div>

        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">🧘 Self-Care</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Reminder to practice self-care</p>
        </div>

        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">💧 Hydration</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Reminder to drink water</p>
        </div>

        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">🚶 Exercise</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Reminder to move and exercise</p>
        </div>

        <div style="border: 2px solid var(--purple-200); padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-500)'; this.style.background='var(--purple-50)'" onmouseout="this.style.borderColor='var(--purple-200)'; this.style.background='white'">
            <h4 style="color: var(--purple-900); margin-bottom: 0.5rem;">😴 Sleep</h4>
            <p style="color: var(--purple-600); margin: 0; font-size: 0.9rem;">Reminder to get adequate sleep</p>
        </div>
    </div>
</div>

<!-- Tips -->
<div class="card" style="background: linear-gradient(135deg, var(--purple-50), var(--purple-100)); margin-top: 2rem;">
    <h3 style="color: var(--purple-900); margin-bottom: 1rem;">💡 Reminder Tips</h3>
    <ul style="color: var(--purple-700); line-height: 1.8;">
        <li>✅ Set reminders at times that work best for you</li>
        <li>✅ Start with 1-2 reminders and add more gradually</li>
        <li>✅ Adjust frequency based on your needs</li>
        <li>✅ Reminders help build healthy habits</li>
        <li>✅ You can disable reminders anytime</li>
    </ul>
</div>
@endsection
