@extends('layouts.app')
@section('title', 'Advanced Chat - Anonymous Student Support')
@section('content')
<style>
/* Main Container */
.chat-advanced-container {
    display: grid;
    grid-template-columns: 300px 1fr 320px;
    gap: 1.5rem;
    height: calc(100vh - 140px);
    padding: 1.5rem;
}

@media (max-width: 1400px) {
    .chat-advanced-container {
        grid-template-columns: 280px 1fr 280px;
    }
}

@media (max-width: 1200px) {
    .chat-advanced-container {
        grid-template-columns: 1fr;
    }
    .student-sidebar, .info-sidebar {
        display: none !important;
    }
}

/* Student Sidebar */
.student-sidebar {
    background: white;
    border-radius: 1rem;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    overflow: hidden;
}

.sidebar-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.student-list {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.student-card {
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.3s;
    background: #f9f7ff;
    border: 2px solid transparent;
    position: relative;
}

.student-card:hover {
    background: white;
    border-color: #7c3aed;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.15);
}

.student-card.active {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    border-color: #7c3aed;
}

.student-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.student-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.student-card.active .student-avatar {
    background: white;
    color: #7c3aed;
}

.student-name {
    flex: 1;
    font-weight: 600;
    font-size: 0.9rem;
}

.crisis-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.crisis-badge.critical {
    background: #dc2626;
    color: white;
}

.crisis-badge.high {
    background: #f59e0b;
    color: white;
}

.crisis-badge.moderate {
    background: #3b82f6;
    color: white;
}

.crisis-badge.stable {
    background: #10b981;
    color: white;
}

.student-meta {
    font-size: 0.8rem;
    opacity: 0.8;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.unread-badge {
    background: #ef4444;
    color: white;
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Chat Panel */
.chat-panel {
    background: white;
    border-radius: 1rem;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    overflow: hidden;
}

.chat-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
    border-bottom: 2px solid #ddd6fe;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chat-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.25rem;
}

.chat-header-info h3 {
    margin: 0 0 0.25rem 0;
    color: #2d0a47;
    font-size: 1.1rem;
}

.chat-header-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #7c3aed;
}

.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #7c3aed;
    padding: 2rem;
}

.empty-state svg {
    width: 5rem;
    height: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
}

.message-bubble {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    animation: slideIn 0.3s;
}

.message-bubble.own {
    justify-content: flex-end;
}

.bubble-content {
    display: flex;
    gap: 0.75rem;
    max-width: 70%;
}

.message-bubble.own .bubble-content {
    flex-direction: row-reverse;
}

.bubble-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.bubble-avatar.own {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
}

.bubble-avatar.other {
    background: linear-gradient(135deg, #5b21b6, #4c1d95);
}

.bubble-text {
    padding: 1rem;
    border-radius: 1rem;
    line-height: 1.6;
}

.bubble-text.own {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    border-radius: 1rem 1rem 0.25rem 1rem;
}

.bubble-text.other {
    background: white;
    border: 2px solid #ddd6fe;
    color: #2d0a47;
    border-radius: 1rem 1rem 1rem 0.25rem;
}

.bubble-meta {
    font-size: 0.75rem;
    margin-top: 0.5rem;
}

.bubble-text.own .bubble-meta {
    color: rgba(255, 255, 255, 0.7);
}

.bubble-text.other .bubble-meta {
    color: #7c3aed;
}

.input-area {
    padding: 1.5rem;
    background: white;
    border-top: 2px solid #ddd6fe;
}

.message-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.message-form textarea {
    flex: 1;
    border: 2px solid #ddd6fe;
    background: #faf5ff;
    color: #2d0a47;
    border-radius: 0.75rem;
    padding: 1rem;
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
    min-height: 50px;
    max-height: 150px;
}

.message-form textarea:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.send-btn {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

/* Info Sidebar */
.info-sidebar {
    background: white;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    overflow-y: auto;
}

.info-section {
    margin-bottom: 1.5rem;
}

.info-section h4 {
    margin: 0 0 1rem 0;
    color: #2d0a47;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-section h4 svg {
    width: 1.25rem;
    height: 1.25rem;
    color: #7c3aed;
}

.info-card {
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
    padding: 1rem;
    border-radius: 0.75rem;
    border-left: 4px solid #7c3aed;
    margin-bottom: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(124, 58, 237, 0.1);
}

.info-item:last-child {
    border: none;
}

.info-label {
    font-weight: 600;
    color: #7c3aed;
}

.info-value {
    color: #2d0a47;
}

.appointment-card {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 0.75rem;
}

.appointment-card h5 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
}

.appointment-card p {
    margin: 0.25rem 0;
    font-size: 0.85rem;
    opacity: 0.9;
}

.btn-schedule {
    width: 100%;
    padding: 0.75rem;
    background: white;
    color: #7c3aed;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 0.75rem;
}

.btn-schedule:hover {
    background: #f5f3ff;
    transform: translateY(-2px);
}

.session-note-form {
    background: #f5f3ff;
    padding: 1rem;
    border-radius: 0.75rem;
    margin-top: 0.75rem;
}

.session-note-form textarea {
    width: 100%;
    border: 2px solid #ddd6fe;
    background: white;
    color: #2d0a47;
    border-radius: 0.5rem;
    padding: 0.75rem;
    font-family: inherit;
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    margin-bottom: 0.75rem;
}

.session-note-form select {
    width: 100%;
    border: 2px solid #ddd6fe;
    background: white;
    color: #2d0a47;
    border-radius: 0.5rem;
    padding: 0.75rem;
    font-family: inherit;
    font-size: 0.85rem;
    margin-bottom: 0.75rem;
}

.btn-save-note {
    width: 100%;
    padding: 0.75rem;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-save-note:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.note-history {
    max-height: 300px;
    overflow-y: auto;
}

.note-item {
    background: white;
    padding: 0.75rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    border-left: 3px solid #7c3aed;
}

.note-item-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.75rem;
    color: #7c3aed;
}

.note-item-text {
    font-size: 0.85rem;
    color: #2d0a47;
    line-height: 1.5;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="chat-advanced-container">
    <!-- Student Sidebar -->
    <div class="student-sidebar">
        <div class="sidebar-header">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:1.5rem;height:1.5rem">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Students with Appointments
            </h2>
        </div>
        <div class="student-list">
            @if($students->count() > 0)
                @foreach($students as $s)
                <div class="student-card {{ $studentId == $s['id'] ? 'active' : '' }}" 
                     onclick="window.location.href='{{ route('counselor.chat.advanced', $s['id']) }}'">
                    <div class="student-card-header">
                        <div class="student-avatar">{{ $s['avatar'] }}</div>
                        <div class="student-name">{{ $s['display_name'] }}</div>
                        @if($s['crisis_level'] === 'critical')
                            <span class="crisis-badge critical">🚨</span>
                        @elseif($s['crisis_level'] === 'high')
                            <span class="crisis-badge high">⚠️</span>
                        @elseif($s['crisis_level'] === 'moderate')
                            <span class="crisis-badge moderate">📊</span>
                        @else
                            <span class="crisis-badge stable">😊</span>
                        @endif
                    </div>
                    <div class="student-meta">
                        <span>ID: {{ $s['student_id'] }}</span>
                        @if($s['unread_count'] > 0)
                            <span class="unread-badge">{{ $s['unread_count'] }}</span>
                        @endif
                    </div>
                    @if($s['latest_appointment'])
                    <div style="font-size:0.75rem;color:#7c3aed;margin-top:0.5rem">
                        📅 {{ $s['latest_appointment'] }}
                    </div>
                    @endif
                </div>
                @endforeach
            @else
                <div style="padding:2rem;text-align:center;color:#7c3aed">
                    <p style="margin:0;font-size:0.9rem">No students with confirmed appointments</p>
                    <p style="margin:0.5rem 0 0 0;font-size:0.8rem;opacity:0.8">Approve appointments to enable chat</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Chat Panel -->
    <div class="chat-panel">
        @if($selectedStudent && $hasAccess)
            <div class="chat-header">
                <div class="chat-header-left">
                    <div class="chat-avatar">S{{ substr($selectedStudent->student_id, -1) }}</div>
                    <div class="chat-header-info">
                        <h3>Student #{{ $selectedStudent->student_id }}</h3>
                        <p>Anonymous Counseling Session</p>
                    </div>
                </div>
            </div>

            <div class="messages-area" id="messagesArea">
                @if($messages->count() > 0)
                    @foreach($messages as $msg)
                    <div class="message-bubble {{ $msg->sender_id == auth()->id() ? 'own' : 'other' }}">
                        <div class="bubble-content">
                            <div class="bubble-avatar {{ $msg->sender_id == auth()->id() ? 'own' : 'other' }}">
                                {{ $msg->sender_id == auth()->id() ? substr(auth()->user()->name, 0, 1) : 'S' }}
                            </div>
                            <div class="bubble-text {{ $msg->sender_id == auth()->id() ? 'own' : 'other' }}">
                                {{ $msg->message }}
                                <div class="bubble-meta">
                                    {{ $msg->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div style="text-align:center;padding:2rem;color:#7c3aed;opacity:0.7">
                        <p style="margin:0">No messages yet. Start the conversation!</p>
                    </div>
                @endif
            </div>

            <div class="input-area">
                <form id="messageForm" onsubmit="sendMessage(event)" class="message-form">
                    @csrf
                    <textarea id="messageInput" name="message" placeholder="Type your message..." rows="2" required></textarea>
                    <button type="submit" class="send-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:1.25rem;height:1.25rem">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Send
                    </button>
                </form>
            </div>
        @elseif($selectedStudent && !$hasAccess)
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:5rem;height:5rem;margin-bottom:1rem;opacity:0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <p style="font-size:1.1rem;margin:0;font-weight:600">No Appointment Access</p>
                <p style="font-size:0.9rem;margin:0.5rem 0 0 0;opacity:0.8">This student needs a confirmed appointment to chat</p>
                <button onclick="window.location.href='{{ route('counselor.appointments') }}'" style="margin-top:1rem;padding:0.75rem 1.5rem;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:white;border:none;border-radius:0.5rem;cursor:pointer;font-weight:600">
                    Go to Appointments
                </button>
            </div>
        @else
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p style="font-size:1.1rem;margin:0">Select a student to start messaging</p>
                <p style="font-size:0.9rem;margin:0.5rem 0 0 0;opacity:0.8">Only students with confirmed appointments can chat</p>
            </div>
        @endif
    </div>

    <!-- Info Sidebar -->
    @if($selectedStudent && $hasAccess)
    <div class="info-sidebar">
        <!-- Student Stats -->
        <div class="info-section">
            <h4>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Student Stats
            </h4>
            <div class="info-card">
                <div class="info-item">
                    <span class="info-label">Total Messages:</span>
                    <span class="info-value">{{ $studentStats['total_messages'] }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Sessions:</span>
                    <span class="info-value">{{ $studentStats['total_sessions'] }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Crisis Events:</span>
                    <span class="info-value">{{ $studentStats['crisis_count'] }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Contact:</span>
                    <span class="info-value">{{ $studentStats['last_contact'] }}</span>
                </div>
            </div>
        </div>

        <!-- Appointments -->
        <div class="info-section">
            <h4>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Appointments
            </h4>
            @if($upcomingAppointment)
            <div class="appointment-card">
                <h5>📅 Next Appointment</h5>
                <p>{{ $upcomingAppointment->appointment_date->format('M d, Y') }}</p>
                <p>{{ $upcomingAppointment->appointment_date->format('g:i A') }}</p>
            </div>
            @else
            <div class="info-card">
                <p style="margin:0;font-size:0.85rem;color:#7c3aed">No upcoming appointments</p>
            </div>
            @endif
            <button class="btn-schedule" onclick="window.location.href='{{ route('counselor.appointments') }}'">
                Schedule New Appointment
            </button>
        </div>

        <!-- Session Notes -->
        <div class="info-section">
            <h4>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Session Notes (Private)
            </h4>
            <div class="session-note-form">
                <textarea id="noteInput" placeholder="Add private session note..."></textarea>
                <select id="sessionType">
                    <option value="chat">Chat Session</option>
                    <option value="appointment">Appointment</option>
                    <option value="crisis">Crisis Intervention</option>
                </select>
                <select id="moodAssessment">
                    <option value="">Mood Assessment (Optional)</option>
                    <option value="improving">Improving</option>
                    <option value="stable">Stable</option>
                    <option value="declining">Declining</option>
                </select>
                <button class="btn-save-note" onclick="saveSessionNote()">Save Note</button>
            </div>

            @if($sessionNotes->count() > 0)
            <div class="note-history">
                <h5 style="margin:1rem 0 0.5rem 0;font-size:0.85rem;color:#7c3aed">Recent Notes</h5>
                @foreach($sessionNotes as $note)
                <div class="note-item">
                    <div class="note-item-header">
                        <span>{{ ucfirst($note->session_type) }}</span>
                        <span>{{ $note->session_date->format('M d') }}</span>
                    </div>
                    <div class="note-item-text">{{ $note->note }}</div>
                    @if($note->mood_assessment)
                    <div style="margin-top:0.5rem;font-size:0.75rem;color:#7c3aed">
                        Mood: {{ ucfirst($note->mood_assessment) }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

<script>
const studentId = {{ $studentId ?? 'null' }};
const authUserId = {{ auth()->id() }};

// Send Message
function sendMessage(event) {
    event.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    fetch(`/counselor/send-message/${studentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            location.reload(); // Reload to show new message
        }
    })
    .catch(error => console.error('Error:', error));
}

// Save Session Note
function saveSessionNote() {
    const noteInput = document.getElementById('noteInput');
    const sessionType = document.getElementById('sessionType').value;
    const moodAssessment = document.getElementById('moodAssessment').value;
    const note = noteInput.value.trim();
    
    if (!note) {
        alert('Please enter a note');
        return;
    }
    
    fetch(`/counselor/session-note/${studentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            note,
            session_type: sessionType,
            mood_assessment: moodAssessment || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            noteInput.value = '';
            document.getElementById('moodAssessment').value = '';
            alert('Session note saved successfully');
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

// Auto-scroll to bottom of messages
if (document.getElementById('messagesArea')) {
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.scrollTop = messagesArea.scrollHeight;
}
</script>
@endsection
