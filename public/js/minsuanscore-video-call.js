(function () {
    'use strict';

    const cfg = window.MinSuansVideoCallConfig;
    if (!cfg || !window.RTCPeerConnection) return;

    const state = {
        call: null,
        pc: null,
        localStream: null,
        remoteStream: null,
        signalCursor: 0,
        signalTimer: null,
        statusTimer: null,
        incomingTimer: null,
        pendingIncoming: null,
        handledIce: new Set(),
        ending: false
    };

    const modal = document.getElementById('mcVideoModal');
    const incoming = document.getElementById('mcIncomingCall');
    const localVideo = document.getElementById('mcLocalVideo');
    const remoteVideo = document.getElementById('mcRemoteVideo');
    const statusText = document.getElementById('mcVideoStatus');
    const waiting = document.getElementById('mcVideoWaiting');
    const waitingTitle = document.getElementById('mcVideoWaitingTitle');
    const waitingCopy = document.getElementById('mcVideoWaitingCopy');
    const micButton = document.getElementById('mcToggleMic');
    const cameraButton = document.getElementById('mcToggleCamera');
    const endButton = document.getElementById('mcEndCall');
    const acceptButton = document.getElementById('mcAcceptCall');
    const declineButton = document.getElementById('mcDeclineCall');

    function headers(json = true) {
        const h = {
            'X-CSRF-TOKEN': cfg.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        };
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }

    async function api(path, options = {}) {
        const response = await fetch(cfg.baseUrl + path, {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...headers(options.body !== undefined),
                ...(options.headers || {})
            }
        });

        let data = {};
        try { data = await response.json(); } catch (_) {}

        if (!response.ok) {
            throw new Error(data.message || data.error || ('Video call request failed (' + response.status + ')'));
        }

        return data;
    }

    function openModal() {
        modal?.classList.add('open');
        modal?.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal?.classList.remove('open');
        modal?.setAttribute('aria-hidden', 'true');
    }

    function setStatus(text) {
        if (statusText) statusText.textContent = text;
    }

    function showWaiting(title, copy) {
        if (waitingTitle) waitingTitle.textContent = title;
        if (waitingCopy) waitingCopy.textContent = copy;
        if (waiting) waiting.style.display = 'flex';
    }

    function hideWaiting() {
        if (waiting) waiting.style.display = 'none';
    }

    async function ensureMedia() {
        if (state.localStream) return state.localStream;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera and microphone are not available in this browser.');
        }

        state.localStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: { echoCancellation: true, noiseSuppression: true }
        });

        if (localVideo) localVideo.srcObject = state.localStream;
        return state.localStream;
    }

    async function createPeer() {
        if (state.pc) return state.pc;

        const pc = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' }
            ]
        });

        state.remoteStream = new MediaStream();
        if (remoteVideo) remoteVideo.srcObject = state.remoteStream;

        const local = await ensureMedia();
        local.getTracks().forEach(track => pc.addTrack(track, local));

        pc.ontrack = function (event) {
            event.streams[0].getTracks().forEach(track => {
                if (!state.remoteStream.getTracks().some(existing => existing.id === track.id)) {
                    state.remoteStream.addTrack(track);
                }
            });
            hideWaiting();
            setStatus('Connected');
        };

        pc.onicecandidate = async function (event) {
            if (!event.candidate || !state.call) return;
            try {
                await sendSignal('ice', event.candidate.toJSON ? event.candidate.toJSON() : event.candidate);
            } catch (error) {
                console.warn('[VIDEO_CALL] ICE send failed', error);
            }
        };

        pc.onconnectionstatechange = function () {
            if (!state.pc) return;

            if (pc.connectionState === 'connected') {
                hideWaiting();
                setStatus('Connected');
            } else if (pc.connectionState === 'failed') {
                setStatus('Connection failed');
                showWaiting('Connection failed', 'Try ending the call and starting again.');
            } else if (pc.connectionState === 'disconnected') {
                setStatus('Connection interrupted');
            }
        };

        state.pc = pc;
        return pc;
    }

    async function sendSignal(type, payload) {
        if (!state.call) return;
        await api('/' + state.call.id + '/signals', {
            method: 'POST',
            body: JSON.stringify({ type, payload })
        });
    }

    async function pollSignals() {
        if (!state.call || !state.pc) return;

        try {
            const data = await api('/' + state.call.id + '/signals?after=' + state.signalCursor, {
                method: 'GET'
            });

            for (const signal of (data.signals || [])) {
                state.signalCursor = Math.max(state.signalCursor, Number(signal.id) || 0);

                if (signal.type === 'offer' && !state.pc.remoteDescription) {
                    await state.pc.setRemoteDescription(new RTCSessionDescription(signal.payload));
                    const answer = await state.pc.createAnswer();
                    await state.pc.setLocalDescription(answer);
                    await sendSignal('answer', answer);
                    setStatus('Connecting…');
                }

                if (signal.type === 'answer' && !state.pc.remoteDescription) {
                    await state.pc.setRemoteDescription(new RTCSessionDescription(signal.payload));
                    setStatus('Connecting…');
                }

                if (signal.type === 'ice') {
                    const iceKey = JSON.stringify(signal.payload);
                    if (!state.handledIce.has(iceKey)) {
                        state.handledIce.add(iceKey);
                        try {
                            await state.pc.addIceCandidate(new RTCIceCandidate(signal.payload));
                        } catch (error) {
                            console.warn('[VIDEO_CALL] ICE candidate rejected', error);
                        }
                    }
                }
            }
        } catch (error) {
            console.warn('[VIDEO_CALL] Signal poll failed', error);
        }
    }

    async function pollStatus() {
        if (!state.call) return;

        try {
            const data = await api('/' + state.call.id, { method: 'GET' });
            const status = data.call?.status;

            if (status === 'accepted') {
                if (state.pc?.connectionState !== 'connected') setStatus('Connecting…');
            }

            if (['declined', 'ended', 'cancelled'].includes(status)) {
                setStatus(status === 'declined' ? 'Call declined' : 'Call ended');
                await cleanup(false);
            }
        } catch (error) {
            console.warn('[VIDEO_CALL] Status poll failed', error);
        }
    }

    function startPolling() {
        stopPolling();
        state.signalTimer = setInterval(pollSignals, 850);
        state.statusTimer = setInterval(pollStatus, 1400);
        pollSignals();
        pollStatus();
    }

    function stopPolling() {
        if (state.signalTimer) clearInterval(state.signalTimer);
        if (state.statusTimer) clearInterval(state.statusTimer);
        state.signalTimer = null;
        state.statusTimer = null;
    }

    async function startCall(peerId) {
        peerId = Number(peerId);
        if (!peerId) {
            alert('Select a student first.');
            return;
        }

        try {
            resetSessionState();
            openModal();
            showWaiting('Calling student…', 'Waiting for the student to accept the private video session.');
            setStatus('Starting camera and microphone…');

            await ensureMedia();

            const created = await api('', {
                method: 'POST',
                body: JSON.stringify({ callee_id: peerId })
            });

            state.call = created.call;

            const pc = await createPeer();
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            await sendSignal('offer', offer);

            setStatus('Ringing…');
            startPolling();
        } catch (error) {
            console.error('[VIDEO_CALL] Start failed', error);
            setStatus(error.message || 'Unable to start call');
            showWaiting('Unable to start video call', error.message || 'Please try again.');
        }
    }

    async function checkIncoming() {
        if (state.call || state.pendingIncoming || cfg.role === 'counselor') return;

        try {
            const data = await api('/incoming', { method: 'GET' });
            if (data.call) {
                state.pendingIncoming = data.call;
                incoming?.classList.add('open');
            }
        } catch (error) {
            console.warn('[VIDEO_CALL] Incoming check failed', error);
        }
    }

    async function acceptIncoming() {
        if (!state.pendingIncoming) return;

        try {
            incoming?.classList.remove('open');
            resetSessionState(false);
            state.call = state.pendingIncoming;
            state.pendingIncoming = null;

            openModal();
            showWaiting('Connecting…', 'Preparing your camera and microphone.');
            setStatus('Starting camera and microphone…');

            await api('/' + state.call.id + '/accept', {
                method: 'POST',
                body: JSON.stringify({})
            });

            await createPeer();
            setStatus('Connecting…');
            startPolling();
        } catch (error) {
            console.error('[VIDEO_CALL] Accept failed', error);
            setStatus(error.message || 'Unable to accept call');
            showWaiting('Unable to join call', error.message || 'Please try again.');
        }
    }

    async function declineIncoming() {
        if (!state.pendingIncoming) return;

        try {
            await api('/' + state.pendingIncoming.id + '/decline', {
                method: 'POST',
                body: JSON.stringify({})
            });
        } catch (error) {
            console.warn('[VIDEO_CALL] Decline failed', error);
        }

        state.pendingIncoming = null;
        incoming?.classList.remove('open');
    }

    async function endCall() {
        if (state.ending) return;
        state.ending = true;

        try {
            if (state.call) {
                await api('/' + state.call.id + '/end', {
                    method: 'POST',
                    body: JSON.stringify({})
                });
            }
        } catch (error) {
            console.warn('[VIDEO_CALL] End request failed', error);
        }

        await cleanup(true);
        state.ending = false;
    }

    async function cleanup(closeNow = true) {
        stopPolling();

        if (state.pc) {
            try { state.pc.close(); } catch (_) {}
        }

        if (state.localStream) {
            state.localStream.getTracks().forEach(track => track.stop());
        }

        if (localVideo) localVideo.srcObject = null;
        if (remoteVideo) remoteVideo.srcObject = null;

        state.pc = null;
        state.call = null;
        state.localStream = null;
        state.remoteStream = null;
        state.signalCursor = 0;
        state.handledIce.clear();

        if (closeNow) closeModal();
        else setTimeout(closeModal, 900);
        
        // Notify chat timeline to refresh
        window.dispatchEvent(new CustomEvent('minsuans:video-call-updated'));
    }

    function resetSessionState(clearPending = true) {
        stopPolling();
        if (state.pc) {
            try { state.pc.close(); } catch (_) {}
        }
        if (state.localStream) state.localStream.getTracks().forEach(track => track.stop());

        state.pc = null;
        state.call = null;
        state.localStream = null;
        state.remoteStream = null;
        state.signalCursor = 0;
        state.handledIce.clear();
        if (clearPending) state.pendingIncoming = null;
    }

    function toggleMic() {
        const track = state.localStream?.getAudioTracks?.()[0];
        if (!track) return;
        track.enabled = !track.enabled;
        micButton?.classList.toggle('off', !track.enabled);
        if (micButton) micButton.title = track.enabled ? 'Mute microphone' : 'Unmute microphone';
    }

    function toggleCamera() {
        const track = state.localStream?.getVideoTracks?.()[0];
        if (!track) return;
        track.enabled = !track.enabled;
        cameraButton?.classList.toggle('off', !track.enabled);
        if (cameraButton) cameraButton.title = track.enabled ? 'Turn camera off' : 'Turn camera on';
    }

    window.MinSuansVideoCall = {
        startCall,
        endCall
    };

    micButton?.addEventListener('click', toggleMic);
    cameraButton?.addEventListener('click', toggleCamera);
    endButton?.addEventListener('click', endCall);
    acceptButton?.addEventListener('click', acceptIncoming);
    declineButton?.addEventListener('click', declineIncoming);

    window.addEventListener('beforeunload', function () {
        if (state.localStream) state.localStream.getTracks().forEach(track => track.stop());
    });

    if (cfg.role !== 'counselor') {
        state.incomingTimer = setInterval(checkIncoming, 1800);
        checkIncoming();
    }
})();
