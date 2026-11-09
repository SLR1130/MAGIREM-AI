(() => {
    'use strict';

    function initialize() {
        const root = document.querySelector('[data-home-magi]');
        if (!root) return;

        const panel = root.querySelector('#home-magi-panel');
        const toggle = root.querySelector('[data-magi-toggle]');
        const close = root.querySelector('[data-magi-close]');
        const nudge = root.querySelector('[data-magi-nudge]');
        const nudgeText = root.querySelector('[data-magi-nudge-text]');
        const nudgeAnnouncement = root.querySelector('[data-magi-nudge-announcement]');
        const messages = root.querySelector('[data-magi-messages]');
        const body = root.querySelector('.home-magi__body');
        const form = root.querySelector('[data-magi-form]');
        const input = root.querySelector('[data-magi-input]');
        const sendButton = root.querySelector('[data-magi-send]');
        const thinking = root.querySelector('[data-magi-thinking]');
        const errorBox = root.querySelector('[data-magi-error]');
        const errorText = root.querySelector('[data-magi-error-text]');
        const retryButton = root.querySelector('[data-magi-retry]');
        const privacyLink = root.querySelector('[data-magi-privacy-link]');
        const fullChat = root.querySelector('[data-magi-full-chat]');
        const promptGroup = root.querySelector('[data-magi-prompts]');
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const timeoutMs = Math.max(30000, Number(root.dataset.requestTimeout) || 210000);
        let aiEnabled = root.dataset.aiEnabled === 'true';
        let currentSessionId = null;
        let pending = null;
        let busy = false;
        let typingTimer = null;
        let initialTimer = null;
        let nudgeSuppressed = false;
        let lastNudgeAt = 0;
        let sectionNudges = 0;
        const seenSections = new Set();

        try { nudgeSuppressed = sessionStorage.getItem('magirem-home-magi-dismissed') === '1'; } catch (_) { /* Storage is optional. */ }

        function suppressNudges() {
            nudgeSuppressed = true;
            window.clearTimeout(initialTimer);
            window.clearTimeout(typingTimer);
            nudgeText.classList.remove('is-typing');
            nudge.hidden = true;
            try { sessionStorage.setItem('magirem-home-magi-dismissed', '1'); } catch (_) { /* Never store messages. */ }
        }

        function showNudge(text) {
            if (nudgeSuppressed || !panel.hidden || document.hidden) return;
            window.clearTimeout(typingTimer);
            nudge.hidden = false;
            nudgeText.textContent = '';
            nudgeAnnouncement.textContent = '';
            lastNudgeAt = Date.now();
            if (reducedMotion?.matches) {
                nudgeText.textContent = text;
                nudgeAnnouncement.textContent = text;
                return;
            }
            const characters = Array.from(text);
            let index = 0;
            nudgeText.classList.add('is-typing');
            const typeNext = () => {
                if (nudge.hidden || nudgeSuppressed) return;
                nudgeText.textContent += characters[index++];
                if (index < characters.length) typingTimer = window.setTimeout(typeNext, 24);
                else {
                    nudgeText.classList.remove('is-typing');
                    nudgeAnnouncement.textContent = text;
                }
            };
            typeNext();
        }

        function setOpen(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) {
                suppressNudges();
                if (aiEnabled && !input.disabled) input.focus(); else close.focus();
            } else toggle.focus();
        }

        toggle.addEventListener('click', () => setOpen(panel.hidden));
        close.addEventListener('click', () => setOpen(false));
        root.querySelector('[data-magi-open]')?.addEventListener('click', () => setOpen(true));
        root.querySelector('[data-magi-dismiss]')?.addEventListener('click', suppressNudges);
        panel.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });

        initialTimer = window.setTimeout(() => showNudge(aiEnabled
            ? 'Hi! Kumusta ka? Nandito lang ako kung gusto mong mag-usap.'
            : 'Hi! Check your Privacy Settings if you would like to chat with MAGI.'), 1800);

        // These are prewritten comments about visible page sections, not inferences
        // from the student's history, mood, or private information.
        if (aiEnabled && 'IntersectionObserver' in window) {
            const sectionMessages = new Map([
                ['mscStudentPathways', 'Maraming puwedeng piliin dito. Start wherever feels right for you.'],
                ['mscStudentSupport', 'May Guidance options din dito kung mas gusto mong may kausap na tao.'],
            ]);
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    const id = entry.target.id;
                    if (!entry.isIntersecting || !lastNudgeAt || seenSections.has(id) || sectionNudges >= 2 || nudgeSuppressed || Date.now() - lastNudgeAt < 12000) return;
                    seenSections.add(id);
                    sectionNudges += 1;
                    showNudge(sectionMessages.get(id));
                });
            }, { root: document.querySelector('.app-main') || null, threshold: .45 });
            sectionMessages.forEach((_, id) => { const section = document.getElementById(id); if (section) observer.observe(section); });
        }

        function scrollToLatest() { body.scrollTop = body.scrollHeight; }
        function appendMessage(text, author, { animate = false } = {}) {
            const message = document.createElement('div');
            message.className = `home-magi__message home-magi__message--${author}`;
            const bubble = document.createElement('span');
            bubble.className = 'home-magi__bubble';
            if (animate && !reducedMotion?.matches && !document.hidden) {
                const visible = document.createElement('span');
                visible.setAttribute('aria-hidden', 'true');
                const accessible = document.createElement('span');
                accessible.className = 'home-magi__sr-only';
                accessible.textContent = text;
                bubble.append(visible, accessible);
                const characters = Array.from(text);
                const duration = Math.min(1400, Math.max(350, characters.length * 7));
                const start = performance.now();
                const typeFrame = (now) => {
                    const complete = reducedMotion?.matches || document.hidden;
                    const progress = complete ? 1 : Math.min(1, (now - start) / duration);
                    visible.textContent = characters.slice(0, Math.ceil(characters.length * progress)).join('');
                    if (progress < 1) window.requestAnimationFrame(typeFrame);
                };
                window.requestAnimationFrame(typeFrame);
            } else bubble.textContent = text;
            message.appendChild(bubble);
            messages.appendChild(message);
            scrollToLatest();
        }

        function appendReferral(referral) {
            if (!referral?.offered || !Array.isArray(referral.actions)) return;
            const links = [];
            referral.actions.forEach((action) => {
                if (typeof action?.url !== 'string' || typeof action?.label !== 'string') return;
                try {
                    const url = new URL(action.url, window.location.origin);
                    if (url.origin !== window.location.origin) return;
                    const link = document.createElement('a');
                    link.href = url.pathname + url.search + url.hash;
                    link.textContent = action.label;
                    links.push(link);
                } catch (_) { /* Ignore malformed or external destinations. */ }
            });
            if (!links.length) return;
            const card = document.createElement('div');
            card.className = 'home-magi__referral';
            card.appendChild(document.createTextNode(referral.lookup_outcome === 'no_eligible_counselors'
                ? 'Counseling support is not currently listed as available. You can still request follow-up.'
                : 'If you would like professional support, you can choose an option below.'));
            const actions = document.createElement('div');
            links.forEach((link) => actions.appendChild(link));
            card.appendChild(actions);
            messages.appendChild(card);
            scrollToLatest();
        }

        function updateControls() {
            input.disabled = !aiEnabled || busy || !!pending;
            sendButton.disabled = !aiEnabled || busy || !!pending;
            thinking.hidden = !busy;
        }

        function showError(text, { retry = false, privacy = false } = {}) {
            errorText.textContent = text;
            retryButton.hidden = !retry;
            privacyLink.hidden = !privacy;
            errorBox.hidden = false;
            scrollToLatest();
        }

        function hideError() { errorBox.hidden = true; retryButton.hidden = true; privacyLink.hidden = true; }

        function requestId() {
            if (window.crypto?.randomUUID) return window.crypto.randomUUID();
            return `home-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        }

        function updateFullChatLink() {
            if (!currentSessionId) return;
            const url = new URL(root.dataset.fullChatUrl, window.location.origin);
            if (url.origin !== window.location.origin) return;
            url.pathname = `${url.pathname.replace(/\/+$/, '')}/${currentSessionId}`;
            fullChat.href = url.pathname + url.search;
        }

        async function parseResponse(response) {
            try { return await response.json(); } catch (_) { throw new Error('INVALID_RESPONSE'); }
        }

        async function deliverPending() {
            if (!pending || busy || !aiEnabled) return;
            busy = true;
            hideError();
            updateControls();
            scrollToLatest();
            const controller = new AbortController();
            const timer = window.setTimeout(() => controller.abort(), timeoutMs);
            try {
                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
                if (!currentSessionId) {
                    const sessionResponse = await fetch(root.dataset.sessionUrl, {
                        method: 'POST', credentials: 'same-origin', headers, body: '{}', signal: controller.signal,
                    });
                    const sessionData = await parseResponse(sessionResponse);
                    const sessionId = Number(sessionData.session_id);
                    if (!sessionResponse.ok || !sessionData.success || !Number.isSafeInteger(sessionId) || sessionId <= 0) throw new Error('SESSION_UNAVAILABLE');
                    currentSessionId = sessionId;
                    updateFullChatLink();
                }

                const response = await fetch(root.dataset.chatUrl, {
                    method: 'POST', credentials: 'same-origin', headers, signal: controller.signal,
                    body: JSON.stringify({ message: pending.message, session_id: currentSessionId, request_id: pending.requestId }),
                });
                const data = await parseResponse(response);
                if (data?.code === 'AI_CONSENT_REQUIRED') {
                    aiEnabled = false;
                    pending = null;
                    showError('AI Chat is disabled in your privacy settings. Review your choice before continuing.', { privacy: true });
                    return;
                }
                if (response.status === 401 || response.status === 419) {
                    showError('Your login session may have expired. Open the full chat after signing in again.');
                    return;
                }
                if (!response.ok || !data?.success || data.available === false || typeof data.response !== 'string' || !data.response.trim()) {
                    throw new Error('CHAT_UNAVAILABLE');
                }
                if (Number(data.session_id) !== currentSessionId) throw new Error('INVALID_SESSION');

                // Safety or unknown-risk replies are always shown immediately.
                const risk = String(data.crisis_analysis?.level || '').toLowerCase();
                const animate = ['none', 'low'].includes(risk) && !String(data.response_source || '').startsWith('crisis');
                appendMessage(data.response, 'assistant', { animate });
                appendReferral(data.counselor_referral);
                pending = null;
            } catch (_) {
                showError('Hindi pa namin nakuha ang reply. Maaaring nagpo-process pa ang message; retry safely after a moment.', { retry: true });
            } finally {
                window.clearTimeout(timer);
                busy = false;
                updateControls();
                scrollToLatest();
            }
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const message = input.value.trim();
            if (!aiEnabled || busy || pending || !message) return;
            pending = { message, requestId: requestId() };
            appendMessage(message, 'user');
            input.value = '';
            if (promptGroup) promptGroup.hidden = true;
            deliverPending();
        });
        retryButton.addEventListener('click', deliverPending);
        root.querySelectorAll('[data-magi-prompt]').forEach((button) => button.addEventListener('click', () => {
            if (!aiEnabled || busy || pending) return;
            input.value = button.dataset.magiPrompt || '';
            input.focus();
        }));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
