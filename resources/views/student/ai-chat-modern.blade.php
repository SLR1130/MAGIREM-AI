@extends('layouts.app')

@section('title', 'MAGI · MAGIREM AI')

@section('content')
<div class="ai-chat-container">
    <!-- Premium Header with Glassmorphism -->
    <div class="ai-chat-header">
        <div class="header-background"></div>
        <div class="header-content">
            <div class="ai-avatar-section">
                <div class="ai-avatar-container">
                    <div class="avatar-glow"></div>
                    <div class="avatar-ring"></div>
                    <img src="{{ asset('images/ai-avatar.png') }}" alt="AI Assistant" class="ai-avatar-img">
                    <span class="status-indicator online"></span>
                </div>
            </div>
            
            <div class="header-info">
                <div class="header-title-group">
                    <h1 class="header-title">MAGI</h1>
                    <span class="ai-badge">v2.0</span>
                </div>
                <p class="header-subtitle">Your personal mental health companion</p>
                <div class="header-stats">
                    <span class="stat-item">
                        <i class="fas fa-heart"></i>
                        <span>Always listening</span>
                    </span>
                    <span class="stat-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>100% Private</span>
                    </span>
                </div>
            </div>
            
            <div class="header-actions">
                <button class="header-btn" id="voiceBtn" title="Voice input">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="header-btn" id="historyBtn" title="Chat history">
                    <i class="fas fa-history"></i>
                </button>
                <button class="header-btn" id="settingsBtn" title="Settings">
                    <i class="fas fa-cog"></i>
                </button>
                <button class="header-btn danger" id="clearChatBtn" title="Clear conversation">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Messages Container with Premium Design -->
    <div class="ai-chat-messages" id="messagesContainer">
        <!-- Welcome Message -->
        <div class="message-group welcome-group">
            <div class="ai-message-wrapper">
                <div class="ai-message-avatar">
                    <div class="avatar-badge">AI</div>
                </div>
                <div class="message-content ai-message">
                    <div class="message-bubble premium">
                        <div class="bubble-header">
                            <span class="bubble-title">Hey Lester Coronel!</span>
                            <span class="bubble-time">Just now</span>
                        </div>
                        <div class="bubble-body">
                            <p>I'm MAGI — a safe, judgment-free space to share your thoughts and feelings.</p>
                            <p>How are you feeling today?</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Input Area -->
    <div class="ai-chat-input-area">
        <div class="input-container">
            <div class="input-wrapper">
                <div class="input-actions-left">
                    <button class="input-action-btn" id="attachBtn" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                    </button>
                </div>

                <div class="input-field-wrapper">
                    <textarea 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="Share your thoughts... (Shift+Enter for new line)"
                        rows="1"
                    ></textarea>
                    <div class="input-counter">
                        <span id="charCount">0</span>/1000
                    </div>
                </div>

                <div class="input-actions-right">
                    <button class="input-action-btn" id="emojiBtn" title="Add emoji">
                        <i class="fas fa-smile"></i>
                    </button>
                    <button class="send-btn" id="sendBtn" title="Send message">
                        <i class="fas fa-paper-plane"></i>
                        <span class="send-text">Send</span>
                    </button>
                </div>
            </div>

            <!-- Quick Suggestions -->
            <div class="quick-suggestions" id="suggestionsContainer" style="display: none;">
                <div class="suggestions-header">
                    <i class="fas fa-lightbulb"></i>
                    <span>Quick suggestions:</span>
                </div>
                <div class="suggestions-list" id="suggestionsList"></div>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div class="typing-indicator" id="typingIndicator" style="display: none;">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
    </div>

    <!-- Emotion Indicator -->
    <div class="emotion-indicator" id="emotionIndicator" style="display: none;">
        <div class="emotion-content">
            <span class="emotion-label">Detected emotion:</span>
            <span class="emotion-badge" id="emotionBadge"></span>
        </div>
    </div>
</div>

<!-- Premium Styles -->
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #8b5cf6;
    --primary-dark: #7c3aed;
    --primary-light: #a78bfa;
    --secondary: #ec4899;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1f2937;
    --light: #f9fafb;
    --border: #e5e7eb;
    --text-muted: #6b7280;
}

.ai-chat-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    overflow: hidden;
    position: relative;
}

.ai-chat-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="1200" height="600" fill="url(%23grid)"/></svg>');
    pointer-events: none;
}

/* Header Styles */
.ai-chat-header {
    position: relative;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding: 12px 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 100;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%);
    pointer-events: none;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.header-content {
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.ai-avatar-section {
    position: relative;
    flex-shrink: 0;
}

.ai-avatar-container {
    position: relative;
    width: 40px;
    height: 40px;
}

.avatar-glow {
    position: absolute;
    inset: -8px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: glow 3s ease-in-out infinite;
}

@keyframes glow {
    0%, 100% {
        opacity: 0.5;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.1);
    }
}

.avatar-ring {
    position: absolute;
    inset: 0;
    border: 3px solid var(--primary);
    border-radius: 50%;
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.ai-avatar-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.status-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid white;
    background: var(--success);
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.7;
        transform: scale(1.2);
    }
}

.header-info {
    flex: 1;
}

.header-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
}

.header-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--dark);
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ai-badge {
    display: inline-block;
    padding: 2px 6px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.header-subtitle {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.header-stats {
    display: flex;
    gap: 12px;
    font-size: 10px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
}

.stat-item i {
    color: var(--primary);
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.header-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.header-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.header-btn:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(139, 92, 246, 0.3);
}

.header-btn.danger:hover {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
}

/* Messages Container */
.ai-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scroll-behavior: smooth;
    background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    position: relative;
    z-index: 1;
}

.ai-chat-messages::-webkit-scrollbar {
    width: 10px;
}

.ai-chat-messages::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.ai-chat-messages::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    transition: background 0.3s ease;
}

.ai-chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Message Groups */
.message-group {
    display: flex;
    animation: fadeInUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.welcome-group {
    justify-content: flex-start;
}

.user-message-group {
    justify-content: flex-end;
}

/* Message Wrappers */
.ai-message-wrapper,
.user-message-wrapper {
    display: flex;
    gap: 12px;
    max-width: 70%;
    animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.user-message-wrapper {
    flex-direction: row-reverse;
    align-self: flex-end;
}

.ai-message-wrapper {
    align-self: flex-start;
}

/* Message Avatars */
.ai-message-avatar,
.user-message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    position: relative;
}

.ai-message-avatar {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.avatar-badge {
    color: white;
    font-size: 12px;
    font-weight: 700;
}

.user-message-avatar {
    background: linear-gradient(135deg, var(--secondary) 0%, #be185d 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
}

/* Message Content */
.message-content {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.message-bubble {
    padding: 14px 18px;
    border-radius: 16px;
    word-wrap: break-word;
    line-height: 1.6;
    font-size: 14px;
    animation: bubbleIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.message-bubble::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 100%);
    pointer-events: none;
}

@keyframes bubbleIn {
    from {
        opacity: 0;
        transform: scale(0.8) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.ai-message .message-bubble {
    background: rgba(255, 255, 255, 0.95);
    color: var(--dark);
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.ai-message .message-bubble.premium {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 250, 251, 0.95) 100%);
    border: 1px solid rgba(139, 92, 246, 0.1);
}

.user-message .message-bubble {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
}

.bubble-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
}

.bubble-title {
    font-weight: 600;
    color: var(--primary);
    font-size: 13px;
}

.bubble-time {
    font-size: 11px;
    color: var(--text-muted);
}

.bubble-body {
    color: var(--dark);
}

.bubble-body p {
    margin-bottom: 8px;
}

.feature-list {
    list-style: none;
    margin: 10px 0;
    padding-left: 0;
}

.feature-list li {
    padding: 6px 0;
    padding-left: 24px;
    position: relative;
    color: var(--dark);
    font-size: 13px;
}

.feature-list li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--success);
    font-weight: bold;
}

/* Input Area */
.ai-chat-input-area {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding: 16px 24px;
    box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 50;
}

.input-container {
    max-width: 1400px;
    margin: 0 auto;
}

.input-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 12px;
}

.input-actions-left,
.input-actions-right {
    display: flex;
    gap: 8px;
}

.input-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.input-action-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.input-action-btn:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(139, 92, 246, 0.3);
}

.input-field-wrapper {
    flex: 1;
    position: relative;
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.message-input {
    flex: 1;
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 12px 16px;
    font-size: 14px;
    font-family: inherit;
    resize: none;
    max-height: 120px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    background: white;
    color: var(--dark);
}

.message-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
    background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(249,250,251,0.5) 100%);
}

.message-input::placeholder {
    color: var(--text-muted);
}

.input-counter {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
    transition: color 0.3s ease;
}

.send-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    position: relative;
    overflow: hidden;
}

.send-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.send-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 92, 246, 0.5);
}

.send-btn:active:not(:disabled) {
    transform: scale(0.95);
}

.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.send-text {
    display: none;
}

/* Quick Suggestions */
.quick-suggestions {
    margin-top: 12px;
    padding: 12px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(236, 72, 153, 0.08) 100%);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 12px;
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.suggestions-header {
    font-size: 12px;
    color: var(--primary);
    margin-bottom: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.suggestions-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.suggestion-btn {
    padding: 8px 14px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    font-size: 12px;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    white-space: nowrap;
    font-weight: 500;
}

.suggestion-btn:hover {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

/* Typing Indicator */
.typing-indicator {
    display: flex;
    gap: 6px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 16px;
    width: fit-content;
    margin-left: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.typing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        opacity: 0.5;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-10px);
    }
}

/* Emotion Indicator */
.emotion-indicator {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: white;
    padding: 14px 18px;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    animation: slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 100;
    border: 1px solid rgba(139, 92, 246, 0.1);
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.emotion-content {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}

.emotion-label {
    color: var(--text-muted);
    font-weight: 500;
}

.emotion-badge {
    padding: 6px 14px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    font-size: 12px;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .ai-message-wrapper,
    .user-message-wrapper {
        max-width: 85%;
    }

    .header-content {
        gap: 12px;
    }

    .header-title {
        font-size: 18px;
    }

    .header-stats {
        display: none;
    }

    .ai-chat-messages {
        padding: 16px;
    }

    .ai-chat-input-area {
        padding: 12px 16px;
    }

    .input-wrapper {
        gap: 8px;
    }

    .message-bubble {
        font-size: 13px;
    }

    .send-text {
        display: inline;
    }
}

@media (max-width: 480px) {
    .ai-message-wrapper,
    .user-message-wrapper {
        max-width: 95%;
    }

    .header-actions {
        gap: 4px;
    }

    .header-btn {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }

    .input-action-btn,
    .send-btn {
        width: 36px;
        height: 36px;
        font-size: 14px;
    }

    .emotion-indicator {
        bottom: 10px;
        right: 10px;
        font-size: 12px;
    }

    .header-title-group {
        flex-direction: column;
        gap: 4px;
    }

    .ai-badge {
        align-self: flex-start;
    }
}

    /* Dusk AI Chat Overrides */
    body.dusk-mode .ai-chat-container {
        background: radial-gradient(circle at 25% 20%, rgba(123, 70, 255, 0.15), transparent 18%),
                    linear-gradient(135deg, rgba(16, 10, 30, 0.92), rgba(38, 20, 70, 0.92));
    }

    body.dusk-mode .ai-chat-container::before {
        background: radial-gradient(circle at 18% 22%, rgba(255, 255, 255, 0.03), transparent 22%),
                    radial-gradient(circle at 80% 30%, rgba(248, 113, 113, 0.06), transparent 16%),
                    linear-gradient(135deg, rgba(255, 255, 255, 0.01), transparent 60%);
    }

    body.dusk-mode .ai-chat-header {
        background: rgba(18, 12, 40, 0.60);
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.24);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    body.dusk-mode .header-background {
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.16) 0%, rgba(236, 72, 153, 0.16) 100%);
    }

    body.dusk-mode .header-title {
        color: #f4efff;
        background: none;
        text-shadow: 0 0 20px rgba(139, 92, 246, 0.25);
    }

    body.dusk-mode .ai-badge {
        background: rgba(139, 92, 246, 0.95);
        color: #fff;
    }

    body.dusk-mode .header-info {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 18px;
        border-radius: 28px;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
    }

    body.dusk-mode .header-actions .header-btn {
        background: rgba(255, 255, 255, 0.06);
    }

    body.dusk-mode .header-subtitle,
    body.dusk-mode .stat-item {
        color: rgba(255, 255, 255, 0.92);
    }

    body.dusk-mode .stat-item i {
        color: #fbbf24;
    }

    body.dusk-mode .header-btn {
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(12px);
    }

    body.dusk-mode .header-btn:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.95), rgba(91, 33, 182, 0.95));
        color: white;
        box-shadow: 0 10px 24px rgba(139, 92, 246, 0.35);
    }

    body.dusk-mode .ai-chat-messages {
        background: rgba(15, 11, 35, 0.56);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(18px);
    }

    body.dusk-mode .ai-chat-messages::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.03);
    }

    body.dusk-mode .ai-chat-messages::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.18);
    }

    body.dusk-mode .message-bubble {
        color: #eef2ff;
    }

    body.dusk-mode .ai-message .message-bubble {
        background: rgba(12, 10, 32, 0.90);
        color: #ffffff;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22);
        border: 1px solid rgba(255, 255, 255, 0.10);
        backdrop-filter: blur(12px);
    }

    body.dusk-mode .ai-message .message-bubble.premium {
        background: rgba(12, 10, 32, 0.94);
        border: 1px solid rgba(255, 255, 255, 0.10);
    }

    body.dusk-mode .user-message .message-bubble {
        background: linear-gradient(135deg, rgba(123, 92, 246, 0.92) 0%, rgba(79, 70, 229, 0.92) 100%);
        color: white;
        box-shadow: 0 10px 28px rgba(79, 70, 229, 0.24);
    }

    body.dusk-mode .bubble-header {
        border-bottom-color: rgba(255, 255, 255, 0.10);
    }

    body.dusk-mode .bubble-title {
        color: #ffffff !important;
    }

    body.dusk-mode .bubble-time {
        color: rgba(255, 255, 255, 0.88) !important;
    }

    body.dusk-mode .bubble-body,
    body.dusk-mode .bubble-body p,
    body.dusk-mode .bubble-body li,
    body.dusk-mode .bubble-body span,
    body.dusk-mode .message-bubble.premium .bubble-body,
    body.dusk-mode .message-bubble.premium .bubble-body p,
    body.dusk-mode .message-bubble.premium .bubble-body li,
    body.dusk-mode .message-bubble.premium .bubble-body span {
        color: #ffffff !important;
    }

    body.dusk-mode .message-bubble.premium {
        background: rgba(12, 10, 32, 0.96) !important;
        border: 1px solid rgba(255, 255, 255, 0.10) !important;
    }

    body.dusk-mode .feature-list li {
        color: rgba(255, 255, 255, 0.95);
    }

    body.dusk-mode .ai-chat-input-area {
        background: rgba(12, 8, 30, 0.28);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 -10px 34px rgba(0, 0, 0, 0.20);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    body.dusk-mode .input-action-btn,
    body.dusk-mode .send-btn {
        background: rgba(255, 255, 255, 0.03);
        color: rgba(255, 255, 255, 0.88);
        border: 1px solid rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(12px);
    }

    body.dusk-mode .input-action-btn:hover,
    body.dusk-mode .send-btn:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.85), rgba(79, 70, 229, 0.85));
        color: white;
        box-shadow: 0 8px 24px rgba(139, 92, 246, 0.18);
    }

    body.dusk-mode .message-input {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
    }

    body.dusk-mode .message-input:focus {
        background: rgba(255, 255, 255, 0.10);
        border-color: rgba(139, 92, 246, 0.55);
        box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.16);
    }

    body.dusk-mode .message-input::placeholder {
        color: rgba(255, 255, 255, 0.60);
    }

    body.dusk-mode .quick-suggestions {
        background: rgba(255, 255, 255, 0.04);
        border-color: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(14px);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.01);
    }

    body.dusk-mode .suggestion-btn {
        background: rgba(255, 255, 255, 0.06);
        color: #eef2ff;
        border-color: rgba(255, 255, 255, 0.10);
    }

    body.dusk-mode .suggestion-btn:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.85), rgba(79, 70, 229, 0.85));
        color: white;
        border-color: rgba(139, 92, 246, 0.30);
    }

    body.dusk-mode .typing-indicator {
        background: rgba(255, 255, 255, 0.06);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.20);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    body.dusk-mode .typing-dot {
        background: linear-gradient(135deg, #8b5cf6 0%, #4f46e5 100%);
    }

    body.dusk-mode .emotion-indicator {
        background: rgba(18, 12, 43, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    }

    body.dusk-mode .emotion-label {
        color: rgba(255, 255, 255, 0.72);
    }

    body.dusk-mode .emotion-badge {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.95), rgba(91, 33, 182, 0.95));
        color: white;
    }
</style>


<!-- Premium Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messagesContainer');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const charCount = document.getElementById('charCount');
    const typingIndicator = document.getElementById('typingIndicator');
    const emotionIndicator = document.getElementById('emotionIndicator');
    const emotionBadge = document.getElementById('emotionBadge');
    const clearChatBtn = document.getElementById('clearChatBtn');

    // Session management
    let currentSessionId = null;

    // One logical browser send must retain its identifier if a response is
    // temporarily lost. Laravel uses this to replay a completed response
    // instead of creating a second message or starting another generation.
    function createChatRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return `chat-${window.crypto.randomUUID()}`;
        }

        return `chat-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
    }

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        charCount.textContent = this.value.length;
    });

    // Send message on Enter (Shift+Enter for new line)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Send button click
    sendBtn.addEventListener('click', sendMessage);

    // Clear chat
    clearChatBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear the conversation?')) {
            messagesContainer.innerHTML = `
                <div class="message-group welcome-group">
                    <div class="ai-message-wrapper">
                        <div class="ai-message-avatar">
                            <div class="avatar-badge">AI</div>
                        </div>
                        <div class="message-content ai-message">
                            <div class="message-bubble premium">
                                <div class="bubble-header">
                                    <span class="bubble-title">Welcome to MAGI</span>
                                    <span class="bubble-time">Just now</span>
                                </div>
                                <div class="bubble-body">
                                    <p>👋 Hello! I'm your MAGI Companion, powered by advanced emotional intelligence.</p>
                                    <p>I'm here to:</p>
                                    <ul class="feature-list">
                                        <li>Listen without judgment</li>
                                        <li>Provide emotional support</li>
                                        <li>Offer coping strategies</li>
                                        <li>Help you understand your feelings</li>
                                    </ul>
                                    <p style="margin-top: 12px;">How are you feeling today? Share anything on your mind.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            messageInput.value = '';
            messageInput.style.height = 'auto';
            charCount.textContent = '0';
            // Reset session for new conversation
            currentSessionId = null;
        }
    });

    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;

        // Add the student message once, before any recoverable retry.
        addMessage(message, 'user');

        messageInput.value = '';
        messageInput.style.height = 'auto';
        charCount.textContent = '0';
        sendBtn.disabled = true;
        showTypingIndicator();

        submitChatRequest({
            message: message,
            requestId: createChatRequestId(),
            sessionId: currentSessionId,
        });
    }

    async function submitChatRequest(request, retryCount = 0) {
        try {
            const response = await fetch('/api/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    message: request.message,
                    user_id: '{{ auth()->id() }}',
                    session_id: request.sessionId,
                    request_id: request.requestId,
                })
            });

            const data = await response.json();
            if (!response.ok || data.success !== true) {
                const error = new Error(data.error || 'The chat request could not be completed.');
                error.code = data.code || `HTTP_${response.status}`;
                throw error;
            }

            hideTypingIndicator();
            if (data.session_id) {
                currentSessionId = data.session_id;
            }

            addMessage(data.response, 'ai');
            if (data.emotion) {
                showEmotion(data.emotion);
            }

            scrollToBottom();
            sendBtn.disabled = false;
        } catch (error) {
            // A response can be lost after Laravel has completed inference.
            // Reuse this exact ID once, so Laravel can replay the completed
            // result rather than create another message or Ollama job.
            const canRecover = retryCount < 1
                && (error.code === 'CHAT_IN_PROGRESS' || error.code?.startsWith('HTTP_') || error instanceof TypeError);

            if (canRecover) {
                setTimeout(() => submitChatRequest(request, retryCount + 1), 1200);
                return;
            }

            console.error('Chat request failed:', error.code || error.message);
            hideTypingIndicator();
            addMessage('Sorry, I could not send that message. Please try again.', 'ai');
            sendBtn.disabled = false;
        }
    }

    function addMessage(text, sender) {
        const messageGroup = document.createElement('div');
        messageGroup.className = `message-group ${sender === 'user' ? 'user-message-group' : 'welcome-group'}`;

        const wrapper = document.createElement('div');
        wrapper.className = `${sender === 'user' ? 'user-message-wrapper' : 'ai-message-wrapper'}`;

        const avatar = document.createElement('div');
        avatar.className = `${sender === 'user' ? 'user-message-avatar' : 'ai-message-avatar'}`;
        if (sender === 'user') {
            avatar.textContent = '{{ auth()->user()->name[0] ?? 'U' }}';
        } else {
            const badge = document.createElement('div');
            badge.className = 'avatar-badge';
            badge.textContent = 'AI';
            avatar.appendChild(badge);
        }

        const content = document.createElement('div');
        content.className = `message-content ${sender === 'user' ? 'user-message' : 'ai-message'}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = text;

        const time = document.createElement('span');
        time.className = 'message-time';
        time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        content.appendChild(bubble);
        content.appendChild(time);
        wrapper.appendChild(avatar);
        wrapper.appendChild(content);
        messageGroup.appendChild(wrapper);

        messagesContainer.appendChild(messageGroup);
        scrollToBottom();
    }

    function showTypingIndicator() {
        typingIndicator.style.display = 'flex';
        scrollToBottom();
    }

    function hideTypingIndicator() {
        typingIndicator.style.display = 'none';
    }

    function showEmotion(emotion) {
        emotionBadge.textContent = emotion.charAt(0).toUpperCase() + emotion.slice(1);
        emotionIndicator.style.display = 'block';

        setTimeout(() => {
            emotionIndicator.style.display = 'none';
        }, 3000);
    }

    function scrollToBottom() {
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 0);
    }

    // Initial scroll
    scrollToBottom();
});
</script>
@endsection
