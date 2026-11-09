@extends('layouts.app')
@section('title', 'Chat with Students - MAGIREM AI')
@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">

<div class="starry-background">
    <div class="stars-layer stars-layer-1"></div>
    <div class="stars-layer stars-layer-2"></div>
    <div class="stars-layer stars-layer-3"></div>
    <div class="nebula-glow"></div>
</div>

<div class="chat-container">
    <!-- Left Sidebar: Student List -->
    <div class="sidebar-left">
        <div class="sidebar-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-2a6 6 0 0112 0v2zm0 0h6v-2a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <div>
                <h2>Students</h2>
                <p>{{ $students->count() }} total</p>
            </div>
        </div>
        <div class="search-box">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()">
        </div>
        <div class="student-list" id="studentList">
            @foreach($students as $s)
            <div onclick="selectStudent({{$s->id}},'{{$s->name}}','{{$s->student_id??'N/A'}}','{{$s->email}}')" class="student-item" data-student-id="{{$s->id}}" data-student-name="{{strtolower($s->name)}}">
                <div class="student-avatar">{{substr($s->name,0,1)}}</div>
                <div class="student-info">
                    <p class="student-name">{{$s->name}}</p>
                    <p class="student-id">{{$s->student_id??'N/A'}}</p>
                </div>
                <div class="status-indicator" title="Active"></div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Center Panel: Chat Area -->
    <div class="chat-panel">
        <div class="chat-header" id="chatHeader">
            <div class="chat-header-left">
                <div class="chat-avatar" id="chatAvatar">-</div>
                <div class="chat-header-info">
                    <h3 id="chatName">Select a student</h3>
                    <p id="chatStatus">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                        <span>Active now</span>
                    </p>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="header-action-btn" onclick="viewStudentProfile()" title="View Profile">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </button>
                <button class="header-action-btn" onclick="refreshMessages()" title="Refresh">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>

        <div class="empty-state" id="emptyState">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <h3>Start a Conversation</h3>
            <p>Select a student from the list to begin messaging</p>
        </div>

        <div class="messages-area" id="messagesArea"></div>

        <div class="input-area" id="inputArea">
            <div class="input-toolbar">
                <button type="button" class="toolbar-btn" onclick="document.getElementById('fileInput').click()" title="Attach file">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="document.getElementById('imageInput').click()" title="Add image">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="toggleEmojiPicker()" title="Add emoji">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <div class="toolbar-divider"></div>
                <button type="button" class="toolbar-btn" onclick="insertQuickResponse('How are you feeling today?')" title="Quick response">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </button>
            </div>

            <div id="filePreview" class="file-preview">
                <div class="file-preview-content">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span id="fileName"></span>
                </div>
                <button type="button" onclick="clearFile()" class="file-preview-close">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div id="emojiPicker" class="emoji-picker">
                <div class="emoji-grid">
                    <button type="button" onclick="insertEmoji('😊')" title="Happy">😊</button>
                    <button type="button" onclick="insertEmoji('😢')" title="Sad">😢</button>
                    <button type="button" onclick="insertEmoji('😂')" title="Laughing">😂</button>
                    <button type="button" onclick="insertEmoji('❤️')" title="Love">❤️</button>
                    <button type="button" onclick="insertEmoji('👍')" title="Thumbs up">👍</button>
                    <button type="button" onclick="insertEmoji('🙏')" title="Pray">🙏</button>
                    <button type="button" onclick="insertEmoji('😍')" title="Heart eyes">😍</button>
                    <button type="button" onclick="insertEmoji('🎉')" title="Party">🎉</button>
                    <button type="button" onclick="insertEmoji('😔')" title="Pensive">😔</button>
                    <button type="button" onclick="insertEmoji('😭')" title="Crying">😭</button>
                    <button type="button" onclick="insertEmoji('😡')" title="Angry">😡</button>
                    <button type="button" onclick="insertEmoji('😱')" title="Shocked">😱</button>
                    <button type="button" onclick="insertEmoji('🤔')" title="Thinking">🤔</button>
                    <button type="button" onclick="insertEmoji('😴')" title="Sleepy">😴</button>
                    <button type="button" onclick="insertEmoji('💪')" title="Strong">💪</button>
                    <button type="button" onclick="insertEmoji('🌟')" title="Star">🌟</button>
                    <button type="button" onclick="insertEmoji('😎')" title="Cool">😎</button>
                    <button type="button" onclick="insertEmoji('🤗')" title="Hug">🤗</button>
                    <button type="button" onclick="insertEmoji('😇')" title="Angel">😇</button>
                    <button type="button" onclick="insertEmoji('🥰')" title="Love">🥰</button>
                    <button type="button" onclick="insertEmoji('��')" title="Relieved">😌</button>
                    <button type="button" onclick="insertEmoji('🙌')" title="Celebrate">🙌</button>
                    <button type="button" onclick="insertEmoji('💯')" title="100">💯</button>
                    <button type="button" onclick="insertEmoji('✨')" title="Sparkles">✨</button>
                </div>
            </div>

            <form onsubmit="sendMessage(event)" class="message-form">
                @csrf
                <input type="hidden" name="student_id" id="studentId">
                <input type="file" id="fileInput" name="attachment" style="display:none" onchange="handleFileSelect(this)" accept=".pdf,.doc,.docx,.txt">
                <input type="file" id="imageInput" name="attachment" style="display:none" onchange="handleFileSelect(this)" accept="image/*">
                <textarea id="messageInput" name="message" placeholder="Type your message..." rows="1" onkeydown="handleKeyPress(event)"></textarea>
                <button type="submit" class="send-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span>Send</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Right Sidebar: Student Info -->
    <div class="sidebar-right" id="sidebarRight">
        <div class="info-section">
            <div class="info-avatar" id="infoAvatar">-</div>
            <h3 id="infoName">Student Name</h3>
            <p id="infoStudentId" class="info-subtitle">Student ID</p>
        </div>

        <div class="info-card">
            <h4>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Contact Information
            </h4>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value" id="sidebarEmail">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Joined:</span>
                <span class="info-value" id="sidebarJoined">-</span>
            </div>
        </div>

        <div class="quick-actions-card">
            <h4>Quick Actions</h4>
            <button onclick="viewStudentProfile()" class="action-btn action-btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                View Profile
            </button>
            <button onclick="viewJournalEntries()" class="action-btn action-btn-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                View Journal
            </button>
        </div>

        <div class="tips-card">
            <h4>
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                Counseling Tips
            </h4>
            <ul>
                <li>Practice active listening</li>
                <li>Show empathy and understanding</li>
                <li>Ask open-ended questions</li>
                <li>Maintain confidentiality</li>
            </ul>
        </div>
    </div>
</div>

<div class="image-modal" id="imageModal" onclick="closeImageModal()">
    <button class="image-modal-close" onclick="closeImageModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="image-modal-content" onclick="event.stopPropagation()">
        <img id="modalImage" src="" alt="Image">
    </div>
</div>
