let selectedStudentId = null;
let isLoadingMessages = false;
let messageCache = {};
let lastMessageCount = {};

function selectStudent(id, name) {
    console.log('[SELECT_START]', id, name);
    selectedStudentId = id;
    
    // Fast DOM updates
    const items = document.querySelectorAll('.student-item');
    for (let i = 0; i < items.length; i++) {
        items[i].classList.remove('active');
    }
    
    const studentItem = document.querySelector('[data-student-id="' + id + '"]');
    if (studentItem) studentItem.classList.add('active');
    console.log('[SELECT_ROW_ACTIVE]');
    
    // Batch style updates - use setProperty to ensure !important override
    const emptyState = document.getElementById('emptyState');
    const chatHeader = document.getElementById('chatHeader');
    const messagesArea = document.getElementById('messagesArea');
    const inputArea = document.getElementById('inputArea');
    
    if (emptyState) emptyState.style.setProperty('display', 'none', 'important');
    if (chatHeader) chatHeader.style.setProperty('display', 'flex', 'important');
    if (messagesArea) messagesArea.style.setProperty('display', 'block', 'important');
    if (inputArea) inputArea.style.setProperty('display', 'block', 'important');
    console.log('[SELECT_UI_UPDATED]');
    
    const avatarEl = document.getElementById('chatAvatar');
    if (avatarEl) avatarEl.textContent = name.charAt(0);
    
    const nameEl = document.getElementById('chatName');
    if (nameEl) nameEl.textContent = name;
    
    const idEl = document.getElementById('studentId');
    if (idEl) idEl.value = id;
    
    console.log('[SELECT_BEFORE_LOAD]');
    loadMessages(id);
    
    // Restart polling
    lastTimelineSignature = null;
    // The counselor workspace uses the interval defined below. The student
    // counselor-chat page also exposes startContinuousPolling(), but it is not
    // loaded here, so do not raise a ReferenceError when selecting a student.
    if (typeof startContinuousPolling === 'function') {
        startContinuousPolling();
    }
    console.log('[SELECT_COMPLETE]');
}

function loadMessages(id, skipCache = false) {
    if (isLoadingMessages) return;
    
    // Use cache if available and not skipping
    if (!skipCache && messageCache[id]) {
        renderMessages(messageCache[id], id);
        return;
    }
    
    isLoadingMessages = true;
    
    fetch('/counselor/api/student/' + id + '/messages')
        .then(r => r.json())
        .then(data => {
            const messages = data.messages || [];
            
            // Only update if message count changed (optimization)
            if (lastMessageCount[id] !== messages.length) {
                messageCache[id] = messages;
                lastMessageCount[id] = messages.length;
                renderMessages(messages, id);
            }
        })
        .catch(err => console.error('Error loading messages:', err))
        .finally(() => {
            isLoadingMessages = false;
        });
}

function renderMessages(messages, studentId) {
    const c = document.getElementById('messagesArea');
    if (!c) return;
    
    if (!messages || messages.length === 0) {
        c.innerHTML = '<p style="text-align:center;color:#7c3aed;padding:2rem">No messages yet. Start the conversation!</p>';
        return;
    }
    
    // Check if we should scroll to bottom
    const shouldScroll = c.scrollHeight - c.scrollTop <= c.clientHeight + 100;
    
    // Clear previous content
    c.innerHTML = '';
    
    // Use DocumentFragment for efficient DOM building
    const fragment = document.createDocumentFragment();
    
    for (let i = 0; i < messages.length; i++) {
        const m = messages[i];
        const own = m.sender_id === window.authUserId;
        
        // Create outer message container
        const messageContainer = document.createElement('div');
        messageContainer.style.marginBottom = '1rem';
        messageContainer.style.display = 'flex';
        messageContainer.style.alignItems = 'flex-end';
        messageContainer.style.gap = '0.75rem';
        if (own) {
            messageContainer.style.justifyContent = 'flex-end';
        }
        
        // Create message bubble
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble' + (own ? ' own' : '');
        bubble.style.display = 'flex';
        bubble.style.gap = '0.75rem';
        if (own) {
            bubble.style.flexDirection = 'row-reverse';
        }
        
        const content = document.createElement('div');
        content.className = 'bubble-content';
        content.style.display = 'flex';
        content.style.gap = '0.75rem';
        if (own) {
            content.style.flexDirection = 'row-reverse';
        }
        
        const avatar = document.createElement('div');
        avatar.className = 'bubble-avatar ' + (own ? 'own' : 'other');
        avatar.textContent = own ? window.authUserInitial : 'S';
        avatar.style.flexShrink = '0';
        
        const text = document.createElement('div');
        text.className = 'bubble-text ' + (own ? 'own' : 'other');
        
        const p = document.createElement('p');
        p.style.margin = '0';
        p.style.wordWrap = 'break-word';
        p.style.whiteSpace = 'pre-wrap';
        p.textContent = m.message || '';
        text.appendChild(p);
        
        // Handle attachments
        if (m.attachment_path || m.attachment_url) {
            const isImage = m.attachment_type && m.attachment_type.startsWith('image/');
            const attachDiv = document.createElement('div');
            attachDiv.style.marginTop = '0.5rem';
            const attachmentTarget = m.attachment_url || ('/storage/' + m.attachment_path);
            
            if (isImage) {
                const img = document.createElement('img');
                img.src = attachmentTarget;
                img.style.maxWidth = '200px';
                img.style.borderRadius = '0.5rem';
                img.style.cursor = 'pointer';
                img.onclick = function() { openImageModal(attachmentTarget); };
                attachDiv.appendChild(img);
            } else {
                attachDiv.style.padding = '0.5rem';
                attachDiv.style.background = 'rgba(255,255,255,0.1)';
                attachDiv.style.borderRadius = '0.5rem';
                attachDiv.style.display = 'flex';
                attachDiv.style.alignItems = 'center';
                attachDiv.style.gap = '0.5rem';
                
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('style', 'width:1rem;height:1rem');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('viewBox', '0 0 24 24');
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('stroke-width', '2');
                path.setAttribute('d', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z');
                svg.appendChild(path);
                
                const a = document.createElement('a');
                a.href = attachmentTarget;
                a.download = m.attachment_name;
                a.style.color = 'inherit';
                a.style.textDecoration = 'underline';
                a.textContent = m.attachment_name;
                
                attachDiv.appendChild(svg);
                attachDiv.appendChild(a);
            }
            text.appendChild(attachDiv);
        }
        
        // Add timestamp
        const meta = document.createElement('div');
        meta.className = 'bubble-meta';
        const timeSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        timeSvg.setAttribute('fill', 'none');
        timeSvg.setAttribute('stroke', 'currentColor');
        timeSvg.setAttribute('viewBox', '0 0 24 24');
        const timePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        timePath.setAttribute('stroke-linecap', 'round');
        timePath.setAttribute('stroke-linejoin', 'round');
        timePath.setAttribute('stroke-width', '2');
        timePath.setAttribute('d', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z');
        timeSvg.appendChild(timePath);
        
        const timeSpan = document.createElement('span');
        timeSpan.textContent = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        meta.appendChild(timeSvg);
        meta.appendChild(timeSpan);
        text.appendChild(meta);
        
        content.appendChild(avatar);
        content.appendChild(text);
        bubble.appendChild(content);
        messageContainer.appendChild(bubble);
        fragment.appendChild(messageContainer);
    }
    
    // Append all messages at once
    c.appendChild(fragment);
    
    // Scroll to bottom if needed
    if (shouldScroll) {
        requestAnimationFrame(() => {
            c.scrollTop = c.scrollHeight;
        });
    }
}

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        console.log('File selected:', file.name, file.type, file.size);
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('filePreview').style.display = 'flex';
    }
}

function clearFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('imageInput').value = '';
    document.getElementById('filePreview').style.display = 'none';
}

function toggleEmojiPicker() {
    const picker = document.getElementById('emojiPicker');
    const isHidden = picker.style.display === 'none' || picker.style.display === '';
    picker.style.display = isHidden ? 'block' : 'none';
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    input.value += emoji;
    input.focus();
    document.getElementById('emojiPicker').style.display = 'none';
}

function sendMessage(e) {
    e.preventDefault();
    
    const msg = document.getElementById('messageInput').value.trim();
    const fileInput = document.getElementById('fileInput');
    const imageInput = document.getElementById('imageInput');
    
    if (!msg && !fileInput.files[0] && !imageInput.files[0]) return;
    if (!selectedStudentId) return;
    
    const sendBtn = document.querySelector('.send-btn');
    const originalBtnText = sendBtn.innerHTML;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<svg style="width:1.25rem;height:1.25rem;animation:spin 1s linear infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
    
    const fd = new FormData();
    fd.append('_token', document.querySelector('input[name="_token"]').value);
    fd.append('student_id', selectedStudentId);
    
    if (msg) fd.append('message', msg);
    if (fileInput.files[0]) fd.append('attachment', fileInput.files[0]);
    else if (imageInput.files[0]) fd.append('attachment', imageInput.files[0]);
    
    fetch('/counselor/send-message/' + selectedStudentId, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('messageInput').value = '';
            clearFile();
            
            // Optimistic update - add message immediately
            if (!messageCache[selectedStudentId]) {
                messageCache[selectedStudentId] = [];
            }
            messageCache[selectedStudentId].push(data.message);
            lastMessageCount[selectedStudentId] = messageCache[selectedStudentId].length;
            renderMessages(messageCache[selectedStudentId], selectedStudentId);
        } else {
            alert('Failed to send message');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error sending message');
    })
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalBtnText;
    });

}

function viewStudentProfile() {
    if (selectedStudentId) window.location.href = '/counselor/student/' + selectedStudentId;
}

function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.add('active');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('active');
}

// Close emoji picker when clicking outside
document.addEventListener('click', function (e) {
    const picker = document.getElementById('emojiPicker');
    const emojiBtn = e.target.closest('.toolbar-btn[onclick*="toggleEmojiPicker"]');
    if (picker && !picker.contains(e.target) && !emojiBtn) {
        picker.style.display = 'none';
    }
});

// Close image modal when clicking outside
document.getElementById('imageModal').addEventListener('click', function (e) {
    if (e.target === this) closeImageModal();
});

// Auto-select first student on load
window.addEventListener('load', function () {
    const first = document.querySelector('.student-item');
    if (first) {
        const id = first.getAttribute('data-student-id');
        const name = first.querySelector('.student-name').textContent;
        selectStudent(parseInt(id), name);
    }
});

// Optimized auto-refresh with smart polling
let lastActivity = Date.now();

document.addEventListener('keydown', function() {
    lastActivity = Date.now();
});

// Smart polling - only check when idle, no cache deletion
setInterval(function () {
    if (selectedStudentId && Date.now() - lastActivity > 3000) {
        loadMessages(selectedStudentId, true);
    }
}, 10000);

// Add CSS animation for spinner
const style = document.createElement('style');
style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(style);
