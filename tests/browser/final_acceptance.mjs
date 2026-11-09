import process from 'node:process';

const debugPort = Number(process.env.MINDCARE_CDP_PORT || 9223);
const baseUrl = process.env.MINDCARE_BASE_URL || 'http://127.0.0.1:8000';
const primaryEmail = process.env.MINDCARE_E2E_EMAIL;
const secondaryEmail = process.env.MINDCARE_ISOLATION_EMAIL;
const password = process.env.MINDCARE_E2E_PASSWORD;

if (!primaryEmail || !secondaryEmail || !password) {
    throw new Error('Set MINDCARE_E2E_EMAIL, MINDCARE_ISOLATION_EMAIL, and MINDCARE_E2E_PASSWORD.');
}

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const deadline = async (fn, description, timeoutMs = 30000, intervalMs = 200) => {
    const end = Date.now() + timeoutMs;
    let last;
    while (Date.now() < end) {
        try {
            const result = await fn();
            if (result) return result;
        } catch (error) {
            last = error;
        }
        await delay(intervalMs);
    }
    throw new Error(`Timed out waiting for ${description}${last ? `: ${last.message}` : ''}`);
};

const targets = await fetch(`http://127.0.0.1:${debugPort}/json/list`).then(response => response.json());
const target = targets.find(item => item.type === 'page');
if (!target) throw new Error('No Chromium page target is available.');

const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
});

let sequence = 0;
const pending = new Map();
const listeners = new Map();
const http500 = [];
const consoleErrors = [];

socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
        const { resolve, reject } = pending.get(message.id);
        pending.delete(message.id);
        message.error ? reject(new Error(message.error.message)) : resolve(message.result || {});
        return;
    }
    if (message.method === 'Network.responseReceived' && message.params.response.status >= 500) {
        http500.push({ status: message.params.response.status, url: message.params.response.url });
    }
    if (message.method === 'Runtime.exceptionThrown') {
        consoleErrors.push(message.params.exceptionDetails.text || 'Uncaught browser exception');
    }
    for (const handler of listeners.get(message.method) || []) handler(message.params);
});

const cdp = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
});

const evaluate = async expression => {
    const result = await cdp('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
        userGesture: true,
    });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text || 'Browser evaluation failed');
    return result.result?.value;
};

const navigate = async path => {
    await cdp('Page.navigate', { url: path.startsWith('http') ? path : `${baseUrl}${path}` });
    await deadline(() => evaluate('document.readyState === "complete"'), `page load: ${path}`, 30000);
};

const waitSelector = (selector, timeoutMs = 30000) => deadline(
    () => evaluate(`Boolean(document.querySelector(${JSON.stringify(selector)}))`),
    `selector ${selector}`,
    timeoutMs,
);

const login = async email => {
    await navigate('/login');
    await waitSelector('#email');
    await evaluate(`(() => {
        const emailInput = document.querySelector('#email');
        const passwordInput = document.querySelector('#password');
        emailInput.value = ${JSON.stringify(email)};
        passwordInput.value = ${JSON.stringify(password)};
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        emailInput.form.requestSubmit();
        return true;
    })()`);
    await deadline(() => evaluate('location.pathname !== "/login"'), `login for ${email}`, 30000);
    return evaluate('location.pathname');
};

const privacyState = () => evaluate(`(() => Object.fromEntries(
    ['share_with_counselor','share_analytics','allow_anonymous_community','share_emotion_data','allow_ai_recommendations']
        .map(name => [name, Boolean(document.querySelector('[name="' + name + '"]')?.checked)])))()`);

const savePrivacy = async aiEnabled => {
    await navigate('/student/privacy');
    await waitSelector('form[action*="privacy/update"]');
    await evaluate(`(() => {
        for (const input of document.querySelectorAll('input[type="checkbox"]')) input.checked = false;
        document.querySelector('[name="allow_ai_recommendations"]').checked = ${aiEnabled};
        document.querySelector('form[action*="privacy/update"]').requestSubmit();
        return true;
    })()`);
    await deadline(() => evaluate('document.readyState === "complete" && location.pathname === "/student/privacy"'), 'privacy save', 30000);
    return privacyState();
};

await cdp('Page.enable');
await cdp('Runtime.enable');
await cdp('Network.enable');
await cdp('Page.setDownloadBehavior', { behavior: 'deny' });

const results = {
    browser: await cdp('Browser.getVersion'),
    checks: {},
    evidence: {},
};

results.evidence.loginLanding = await login(primaryEmail);
results.checks.studentLogin = !results.evidence.loginLanding.includes('/login');

await navigate('/student/privacy');
results.evidence.initialPrivacy = await privacyState();
results.evidence.optOutPrivacy = await savePrivacy(false);
results.checks.privacyOptOut = Object.values(results.evidence.optOutPrivacy).every(value => value === false);
results.evidence.optInPrivacy = await savePrivacy(true);
results.checks.privacyOptIn = results.evidence.optInPrivacy.allow_ai_recommendations === true
    && Object.entries(results.evidence.optInPrivacy).filter(([key]) => key !== 'allow_ai_recommendations').every(([, value]) => value === false);

await navigate('/student/ai-chat');
await waitSelector('#chatForm');
await deadline(() => evaluate('typeof window.startNewConversation === "function"'), 'chat JavaScript initialization');
await evaluate('window.confirm = () => true; window.startNewConversation(); true');
await deadline(() => evaluate('location.pathname.startsWith("/student/ai-chat/")'), 'new chat navigation', 30000);
await waitSelector('#chatForm');
await deadline(() => evaluate('document.querySelector("#messagesContainer .message.ai .message-bubble")?.textContent.trim().length > 0'), 'AI greeting', 120000);

const firstSessionId = await evaluate('Number(location.pathname.split("/").filter(Boolean).at(-1))');
results.evidence.firstSessionId = firstSessionId;
results.checks.newChatUsesSessionId = Number.isInteger(firstSessionId) && firstSessionId > 0;
results.checks.aiGreeting = await evaluate('document.querySelector("#messagesContainer .message.ai .message-bubble")?.textContent.trim().length > 0');

await evaluate(`(() => {
    window.__acceptanceChatResponses = [];
    const originalFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        const url = String(args[0]);
        if (url.includes('/api/ai/chat')) {
            response.clone().json().then(data => window.__acceptanceChatResponses.push(data)).catch(() => {});
        }
        return response;
    };
    return true;
})()`);

const beforeMessages = await evaluate('document.querySelectorAll("#messagesContainer .message").length');
await evaluate(`(() => {
    const input = document.querySelector('#chatInput');
    input.value = 'I feel okay and steady right now.';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelector('#chatForm').requestSubmit();
    return true;
})()`);
results.checks.typingIndicator = await deadline(
    () => evaluate('Boolean(document.querySelector("#messagesContainer .typing-indicator"))'),
    'typing indicator',
    5000,
);
results.checks.loadingButton = await evaluate('document.querySelector("#sendButton").disabled === true');
await deadline(() => evaluate('window.__acceptanceChatResponses?.length === 1'), 'AI chat response', 240000, 500);
await deadline(() => evaluate(`document.querySelectorAll('#messagesContainer .message').length >= ${beforeMessages + 2}`), 'rendered AI response', 30000);

results.evidence.chatResponse = await evaluate('window.__acceptanceChatResponses[0]');
results.evidence.renderedEmotion = await evaluate('document.querySelector("#messagesContainer .message.ai:last-of-type .emotion-badge")?.textContent.trim() || null');
results.checks.aiResponseRendered = await evaluate('document.querySelector("#messagesContainer .message.ai:last-of-type .message-bubble")?.textContent.trim().length > 0');
results.checks.typingCleared = await evaluate('!document.querySelector("#messagesContainer .typing-indicator")');
results.checks.buttonReset = await evaluate('document.querySelector("#sendButton").disabled === false');
results.checks.noErrorBanner = await evaluate('getComputedStyle(document.querySelector("#chatErrorBanner")).display === "none"');
results.checks.wellbeingLoaded = await deadline(
    () => evaluate('document.querySelector("#chatWellnessChip")?.textContent.trim().length > 0'),
    'wellbeing indicator',
    30000,
);

results.evidence.firstHistory = await evaluate(`fetch('/api/ai/sessions/${firstSessionId}/messages', { headers: { Accept: 'application/json' } }).then(async r => ({ status:r.status, body:await r.json() }))`);
results.checks.historyHasExactlyOneInteraction = results.evidence.firstHistory.status === 200
    && results.evidence.firstHistory.body.messages?.length === 1;
results.checks.emotionConsistent = results.evidence.chatResponse.emotion === results.evidence.chatResponse.analysis?.emotion
    && results.evidence.chatResponse.emotion === results.evidence.firstHistory.body.messages?.[0]?.emotion
    && results.evidence.renderedEmotion?.toLowerCase().includes(results.evidence.chatResponse.emotion);

const countBeforeEmptySubmit = await evaluate('document.querySelectorAll("#messagesContainer .message").length');
await evaluate(`(() => { document.querySelector('#chatInput').value = '   '; document.querySelector('#chatForm').requestSubmit(); return true; })()`);
await delay(400);
results.checks.emptyMessageHandled = await evaluate(`document.querySelectorAll('#messagesContainer .message').length === ${countBeforeEmptySubmit}`);

await evaluate('window.confirm = () => true; window.startNewConversation(); true');
await deadline(() => evaluate(`location.pathname.startsWith('/student/ai-chat/') && !location.pathname.endsWith('/${firstSessionId}')`), 'second chat navigation', 30000);
await waitSelector('#chatForm');
await deadline(() => evaluate('document.querySelector("#messagesContainer .message.ai .message-bubble")?.textContent.trim().length > 0'), 'second AI greeting', 120000);
const secondSessionId = await evaluate('Number(location.pathname.split("/").filter(Boolean).at(-1))');
results.evidence.secondSessionId = secondSessionId;

await deadline(() => evaluate(`Boolean(document.querySelector('[data-session-id="${firstSessionId}"]'))`), 'first session in history sidebar', 30000);
await evaluate(`document.querySelector('[data-session-id="${firstSessionId}"]').click(); true`);
await deadline(() => evaluate(`location.pathname.endsWith('/${firstSessionId}')`), 'session switch', 30000);
await waitSelector('#chatForm');
await deadline(() => evaluate('document.querySelectorAll("#messagesContainer .message.user").length === 1'), 'switched session history', 30000);
results.checks.sessionSwitching = true;

await cdp('Page.reload', { ignoreCache: true });
await deadline(() => evaluate('document.readyState === "complete"'), 'chat refresh', 30000);
await deadline(() => evaluate('document.querySelectorAll("#messagesContainer .message.user").length === 1'), 'history after refresh', 30000);
results.checks.refreshRestoresHistory = true;

await navigate('/student/wellbeing');
results.checks.wellbeingPage = await evaluate('document.body.innerText.toLowerCase().includes("wellbeing")');

await cdp('Network.clearBrowserCookies');
results.evidence.isolationLoginLanding = await login(secondaryEmail);
results.evidence.ownershipProbe = await evaluate(`fetch('/api/ai/sessions/${firstSessionId}/messages', { headers: { Accept:'application/json' } }).then(async r => ({ status:r.status, text:await r.text() }))`);
results.checks.sessionOwnership = [403, 404].includes(results.evidence.ownershipProbe.status);

results.evidence.http500 = http500;
results.evidence.browserExceptions = consoleErrors;
results.checks.noHttp500 = http500.length === 0;
results.checks.noBrowserExceptions = consoleErrors.length === 0;
results.passed = Object.values(results.checks).every(Boolean);

console.log(JSON.stringify(results, null, 2));
socket.close();
if (!results.passed) process.exitCode = 1;
