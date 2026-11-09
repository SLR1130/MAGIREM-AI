export const BREATHING_PHASES = Object.freeze([
    { label: 'Gentle inhale', seconds: 4, kind: 'inhale' },
    { label: 'Optional hold', seconds: 4, kind: 'hold' },
    { label: 'Gentle exhale', seconds: 4, kind: 'exhale' },
    { label: 'Optional rest', seconds: 4, kind: 'rest' },
]);

export function resolvePracticeMode(name = '', category = '') {
    const normalizedName = String(name).toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ');
    const normalizedCategory = String(category).toLocaleLowerCase().replace(/[_-]+/g, ' ');

    if (/journal|expressive writ/.test(normalizedName)) return 'journal';
    if (/cognitive reframe|reframing/.test(normalizedName)) return 'reframe';
    if (/ground|5 4 3 2 1/.test(normalizedName) || normalizedCategory.includes('ground')) return 'grounding';
    if (/progressive muscle|muscle relaxation/.test(normalizedName)) return 'muscle';
    if (/physical activity|take a walk|walking/.test(normalizedName) || normalizedCategory.includes('physical activity')) return 'movement';
    if (/breath/.test(normalizedName) || normalizedCategory.includes('breath')) return 'breathing';

    return 'guided';
}

export function breathingPhaseAt(elapsedMs, phases = BREATHING_PHASES) {
    const totalMs = phases.reduce((sum, item) => sum + item.seconds * 1000, 0);
    const positionMs = ((Math.max(0, elapsedMs) % totalMs) + totalMs) % totalMs;
    let phaseStartMs = 0;

    for (let index = 0; index < phases.length; index += 1) {
        const phase = phases[index];
        const phaseMs = phase.seconds * 1000;
        if (positionMs < phaseStartMs + phaseMs) {
            const withinPhaseMs = positionMs - phaseStartMs;
            return {
                index,
                phase,
                remainingSeconds: Math.max(1, Math.ceil((phaseMs - withinPhaseMs) / 1000)),
                fraction: withinPhaseMs / phaseMs,
                cycleFraction: positionMs / totalMs,
            };
        }
        phaseStartMs += phaseMs;
    }

    return { index: 0, phase: phases[0], remainingSeconds: phases[0].seconds, fraction: 0, cycleFraction: 0 };
}

function initCopingRunner() {
    const dialog = document.getElementById('copingPracticeRunner');
    if (!dialog || dialog.dataset.runnerReady === 'true') return;
    dialog.dataset.runnerReady = 'true';
    const pageScroller = dialog.closest('.app-main');
    document.body.append(dialog);

    const shell = dialog.querySelector('.coping-runner-shell');
    const panel = dialog.querySelector('.coping-runner-panel');
    const stage = dialog.querySelector('[data-runner-stage]');
    const visual = dialog.querySelector('[data-runner-visual]');
    const title = dialog.querySelector('[data-runner-title]');
    const stageKicker = dialog.querySelector('[data-runner-stage-kicker]');
    const stageTitle = dialog.querySelector('[data-runner-stage-title]');
    const stageCaption = dialog.querySelector('[data-runner-stage-caption]');
    const status = dialog.querySelector('[data-runner-status]');
    const content = dialog.querySelector('[data-runner-content]');
    const sourceInstructions = dialog.querySelector('[data-runner-source-instructions]');
    const modeControls = dialog.querySelector('[data-runner-mode-controls]');
    const progressTrack = dialog.querySelector('[data-runner-progress-track]');
    const progressBar = dialog.querySelector('[data-runner-progress]');
    const startButton = dialog.querySelector('[data-runner-start]');
    const pauseButton = dialog.querySelector('[data-runner-pause]');
    const resumeButton = dialog.querySelector('[data-runner-resume]');
    const restartButton = dialog.querySelector('[data-runner-restart]');
    const finishButton = dialog.querySelector('[data-runner-finish]');

    const groundingSteps = [
        { count: 5, sense: 'things you see', label: 'Look around and notice five things you can see.', symbol: 'See' },
        { count: 4, sense: 'things you feel', label: 'Notice four things you can feel, such as a surface or the air.', symbol: 'Feel' },
        { count: 3, sense: 'things you hear', label: 'Notice three sounds, near or far.', symbol: 'Hear' },
        { count: 2, sense: 'things you smell', label: 'If comfortable, notice two scents. You can skip this step.', symbol: 'Smell' },
        { count: 1, sense: 'thing you taste', label: 'If comfortable, notice one taste. You can skip this step.', symbol: 'Taste' },
    ];
    const muscleGroups = ['Hands and forearms', 'Shoulders', 'Face and jaw', 'Abdomen', 'Legs', 'Feet'];
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');

    let active = null;
    let runnerState = 'idle';
    let rafId = null;
    let startedAt = 0;
    let elapsedBeforeRun = 0;
    let lastAnnouncedPhase = '';
    let restoreFocus = null;
    let savedScrollY = 0;
    let phaseLiveRegion = null;
    let breathScaleTarget = 1;
    let breathCountdown = null;
    let journalClock = null;
    let journalTimerEndAt = null;

    const make = (tag, className, text = '') => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== '') element.textContent = text;
        return element;
    };

    const button = (label, action, className = 'runner-button') => {
        const element = make('button', className, label);
        element.type = 'button';
        element.dataset.runnerAction = action;
        return element;
    };

    function setStatus(message) {
        status.textContent = message;
    }

    function setProgress(value, enabled = true) {
        progressTrack.hidden = !enabled;
        progressBar.style.width = `${Math.max(0, Math.min(100, value))}%`;
    }

    function setState(nextState, message) {
        runnerState = nextState;
        stage.dataset.runnerState = nextState;
        startButton.hidden = nextState !== 'idle';
        pauseButton.hidden = nextState !== 'running';
        resumeButton.hidden = nextState !== 'paused';
        restartButton.hidden = nextState === 'idle';
        finishButton.hidden = !['running', 'paused'].includes(nextState);
        if (message) setStatus(message);
        modeControls.querySelectorAll('[data-requires-running]').forEach((control) => {
            control.disabled = !['running', 'paused'].includes(nextState);
        });
    }

    function cancelFrame() {
        if (rafId !== null) {
            window.cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    function currentElapsed(now = performance.now()) {
        return elapsedBeforeRun + (runnerState === 'running' ? Math.max(0, now - startedAt) : 0);
    }

    function buildVisual(mode) {
        visual.replaceChildren();
        delete stage.dataset.mode;
        stage.dataset.mode = mode;
        stageKicker.textContent = 'Guided practice';
        stageTitle.textContent = 'Move at your own pace';
        stageCaption.textContent = 'The activity instructions stay available in the panel.';

        if (mode === 'breathing') {
            stageKicker.textContent = 'Gentle breathing';
            stageTitle.textContent = 'Let the circle guide the pace';
            stageCaption.textContent = 'Optional holds can be skipped. Breathe normally or stop if this feels uncomfortable.';
            const wrap = make('div', 'breath-orb-wrap');
            wrap.setAttribute('aria-hidden', 'true');
            wrap.append(make('span', 'breath-orb-ring'), make('span', 'breath-orb-ring'));
            const orb = make('div', 'breath-orb');
            const phaseText = make('span', 'breath-phase-text');
            phaseText.append(document.createTextNode('Ready'));
            const count = make('span', 'breath-phase-count');
            count.setAttribute('aria-hidden', 'true');
            phaseText.append(count);
            orb.append(phaseText);
            wrap.append(orb);
            visual.append(wrap);
            active.refs.orb = orb;
            active.refs.breathCount = count;
            active.phases = /4\s*7\s*8|4-7-8/i.test(active.name)
                ? [
                    { label: 'Gentle inhale', seconds: 4, kind: 'inhale' },
                    { label: 'Optional hold', seconds: 7, kind: 'hold' },
                    { label: 'Gentle exhale', seconds: 8, kind: 'exhale' },
                ]
                : /deep belly|diaphragmatic/i.test(active.name)
                    ? [
                        { label: 'Gentle inhale', seconds: 4, kind: 'inhale' },
                        { label: 'Gentle exhale', seconds: 6, kind: 'exhale' },
                    ]
                    : BREATHING_PHASES;
            active.refs.breathText = phaseText.firstChild;
            return;
        }

        if (mode === 'grounding') {
            stageKicker.textContent = '5-4-3-2-1 grounding';
            stageTitle.textContent = 'Notice what is around you';
            stageCaption.textContent = 'No need to type or record what you notice. You can skip any sense.';
            const station = make('div', 'grounding-station');
            station.setAttribute('aria-hidden', 'true');
            const symbol = make('span', 'grounding-station-symbol', 'See');
            const count = make('span', 'grounding-station-count', '5');
            const label = make('span', 'grounding-station-label', 'things you see');
            const dots = make('span', 'grounding-dots');
            groundingSteps.forEach(() => dots.append(make('span')));
            station.append(symbol, count, label, dots);
            visual.append(station);
            active.refs.groundSymbol = symbol;
            active.refs.groundCount = count;
            active.refs.groundLabel = label;
            active.refs.groundDots = dots;
            return;
        }

        if (mode === 'muscle') {
            stageKicker.textContent = 'Progressive muscle relaxation';
            stageTitle.textContent = 'Choose gentle release';
            stageCaption.textContent = 'Tensing is optional. Never squeeze painfully; release-only is available.';
            const map = make('div', 'muscle-map');
            map.setAttribute('aria-hidden', 'true');
            muscleGroups.forEach((group) => map.append(make('span', '', group)));
            visual.append(map);
            active.refs.muscleMap = map;
            return;
        }

        if (mode === 'journal') {
            stageKicker.textContent = 'Private reflection';
            stageTitle.textContent = 'Write only what you choose';
            stageCaption.textContent = 'Your words remain in this open page. They are not auto-saved or sent.';
            const page = make('div', 'journal-page-visual');
            page.setAttribute('aria-hidden', 'true');
            for (let index = 0; index < 5; index += 1) page.append(make('span'));
            visual.append(page);
            return;
        }

        if (mode === 'reframe') {
            stageKicker.textContent = 'Cognitive reframing';
            stageTitle.textContent = 'Make room for another possibility';
            stageCaption.textContent = 'A balanced alternative does not need to be positive or certain.';
            const cards = make('div', 'reframe-visual');
            cards.setAttribute('aria-hidden', 'true');
            const first = make('div', 'reframe-visual-card');
            first.append(make('strong', '', 'A thought'));
            first.append(make('span', '', 'What is on your mind?'));
            const arrow = make('span', 'reframe-arrow', '→');
            const second = make('div', 'reframe-visual-card');
            second.append(make('strong', '', 'Another possibility'));
            second.append(make('span', '', 'What is a fairer view?'));
            cards.append(first, arrow, second);
            visual.append(cards);
            return;
        }

        if (mode === 'movement') {
            stageKicker.textContent = 'Comfortable movement';
            stageTitle.textContent = 'Choose a pace that suits you';
            stageCaption.textContent = 'The path is only a visual cue; use the activity instructions on the right.';
            const path = make('div', 'movement-path');
            path.setAttribute('aria-hidden', 'true');
            const dot = make('span', 'movement-dot');
            path.append(dot);
            visual.append(path);
            active.refs.movementPath = path;
            return;
        }

        stageTitle.textContent = 'Take the activity one step at a time';
        stageCaption.textContent = 'Use the source instructions below; pause or stop whenever you choose.';
        const rings = make('div', 'generic-visual-rings');
        rings.setAttribute('aria-hidden', 'true');
        visual.append(rings);
    }

    function addInstruction(text) {
        content.append(make('p', 'runner-instruction', text));
    }

    function buildGroundingControls() {
        const stepLabel = make('span', 'runner-step-label');
        const previous = button('Previous', 'grounding-previous');
        const next = button('Next', 'grounding-next', 'runner-button runner-button-primary');
        previous.dataset.requiresRunning = 'true';
        next.dataset.requiresRunning = 'true';
        const nav = make('div', 'runner-step-nav');
        nav.append(previous, stepLabel, next);
        modeControls.append(nav);
        active.refs.groundStepLabel = stepLabel;
        active.refs.groundPrevious = previous;
        active.refs.groundNext = next;
        updateGroundingStep();
    }

    function updateGroundingStep() {
        const step = groundingSteps[active.stepIndex];
        const count = active.refs.groundDots.querySelectorAll('span');
        active.refs.groundSymbol.textContent = step.symbol;
        active.refs.groundCount.textContent = String(step.count);
        active.refs.groundLabel.textContent = step.sense;
        active.refs.groundStepLabel.textContent = `Step ${active.stepIndex + 1} of ${groundingSteps.length}`;
        active.refs.groundPrevious.disabled = active.stepIndex === 0;
        active.refs.groundNext.textContent = active.stepIndex === groundingSteps.length - 1 ? 'Finish grounding' : 'Next';
        active.refs.groundNext.dataset.runnerAction = active.stepIndex === groundingSteps.length - 1 ? 'grounding-finish' : 'grounding-next';
        count.forEach((dot, index) => dot.classList.toggle('is-current', index === active.stepIndex));
        count.forEach((dot, index) => dot.classList.toggle('is-done', index < active.stepIndex));
        const live = content.querySelector('[data-ground-live]');
        if (live) live.textContent = `Step ${active.stepIndex + 1} of ${groundingSteps.length}: ${step.label}`;
        setProgress((active.stepIndex / groundingSteps.length) * 100);
    }

    function buildMuscleControls() {
        addInstruction('Move through one area at a time. You may gently tense and release, or use the release-only option. Stop if anything hurts.');
        const releaseLabel = make('label', 'runner-option');
        const releaseOnly = make('input');
        releaseOnly.type = 'checkbox';
        releaseOnly.dataset.releaseOnly = 'true';
        const releaseText = make('span', '', 'Use release-only guidance (no tensing).');
        releaseLabel.append(releaseOnly, releaseText);
        modeControls.append(releaseLabel);
        active.refs.releaseOnly = releaseOnly;
        const phaseText = make('p', 'runner-step-instruction', 'Choose Start when you are ready.');
        phaseText.dataset.musclePhase = 'true';
        phaseText.setAttribute('role', 'status');
        phaseText.setAttribute('aria-live', 'polite');
        content.append(phaseText);
        active.refs.musclePhase = phaseText;
        const stepLabel = make('span', 'runner-step-label');
        stepLabel.dataset.muscleStepLabel = 'true';
        const previous = button('Previous group', 'muscle-previous');
        const next = button('Next group', 'muscle-next', 'runner-button runner-button-primary');
        const skip = button('Skip group', 'muscle-skip');
        [previous, next, skip].forEach((control) => { control.dataset.requiresRunning = 'true'; });
        const nav = make('div', 'runner-step-nav');
        nav.append(previous, stepLabel, next, skip);
        modeControls.append(nav);
        const phaseControls = make('div', 'runner-phase-controls');
        const tense = button('Gently tense', 'muscle-tense');
        const release = button('Release', 'muscle-release');
        tense.dataset.requiresRunning = 'true';
        release.dataset.requiresRunning = 'true';
        phaseControls.append(tense, release);
        modeControls.append(phaseControls);
        active.refs.muscleStepLabel = stepLabel;
        active.refs.musclePrevious = previous;
        active.refs.muscleNext = next;
        active.refs.muscleSkip = skip;
        active.refs.muscleTense = tense;
        active.refs.muscleRelease = release;
        updateMuscleStep();
        releaseOnly.addEventListener('change', updateMuscleStep);
    }

    function updateMuscleStep() {
        const group = muscleGroups[active.stepIndex];
        const nodes = active.refs.muscleMap.querySelectorAll('span');
        nodes.forEach((node, index) => node.classList.toggle('is-current', index === active.stepIndex));
        active.refs.muscleStepLabel.textContent = `Group ${active.stepIndex + 1} of ${muscleGroups.length}`;
        active.refs.musclePrevious.disabled = active.stepIndex === 0;
        active.refs.muscleNext.textContent = active.stepIndex === muscleGroups.length - 1 ? 'Finish muscle practice' : 'Next group';
        active.refs.muscleNext.dataset.runnerAction = active.stepIndex === muscleGroups.length - 1 ? 'muscle-finish' : 'muscle-next';
        const releaseOnly = active.refs.releaseOnly.checked;
        active.refs.muscleTense.hidden = releaseOnly;
        active.refs.muscleTense.disabled = false;
        active.refs.musclePhase.textContent = releaseOnly
            ? `${group}: Let this area soften and release, if that feels comfortable.`
            : `${group}: If comfortable, gently tense briefly, then release. No force or breath-holding is needed.`;
        stageTitle.textContent = group;
        setProgress((active.stepIndex / muscleGroups.length) * 100);
    }

    function buildJournalContent() {
        const label = make('label', 'runner-field');
        label.htmlFor = 'copingJournalDraft';
        label.append(make('span', 'runner-field-label', 'Write a few words, or leave this blank.'));
        const textarea = make('textarea');
        textarea.id = 'copingJournalDraft';
        textarea.autocomplete = 'off';
        textarea.spellcheck = true;
        textarea.maxLength = 5000;
        textarea.placeholder = 'Your reflection stays in this open page only.';
        label.append(textarea);
        const note = make('p', 'runner-privacy-note', 'This text is held only in this page session. It is not saved to your Journal, sent to MAGI, or shared with a third party. Closing the practice discards it.');
        const timerLabel = make('label', 'runner-field');
        timerLabel.htmlFor = 'copingJournalTimer';
        timerLabel.append(make('span', 'runner-field-label', 'Optional session timer'));
        const select = make('select');
        select.id = 'copingJournalTimer';
        select.className = 'runner-timer-select';
        [['0', 'Off'], ['5', '5 minutes'], ['10', '10 minutes'], ['15', '15 minutes']].forEach(([value, text]) => {
            const option = make('option', '', text);
            option.value = value;
            select.append(option);
        });
        timerLabel.append(select);
        const clock = make('p', 'runner-clock', 'Timer off');
        clock.dataset.journalClock = 'true';
        content.append(label, note, timerLabel, clock);
        active.refs.journalText = textarea;
        active.refs.journalTimerSelect = select;
        active.refs.journalClock = clock;
        select.addEventListener('change', () => {
            const minutes = Number(select.value);
            journalTimerEndAt = minutes > 0 ? minutes * 60 * 1000 : null;
            active.journalTimerMs = journalTimerEndAt;
            clock.textContent = minutes > 0 ? `${minutes}:00 optional timer` : 'Timer off';
            journalClock = journalTimerEndAt;
            if (runnerState === 'running') {
                elapsedBeforeRun = currentElapsed();
                startedAt = performance.now();
                active.journalTimerStartedElapsed = elapsedBeforeRun;
            } else if (runnerState === 'paused') {
                active.journalTimerStartedElapsed = elapsedBeforeRun;
            }
        });
    }

    function buildReframeContent() {
        const first = make('label', 'runner-field');
        first.htmlFor = 'copingThoughtDraft';
        first.append(make('span', 'runner-field-label', 'A thought you would like to look at (optional)'));
        const thought = make('textarea');
        thought.id = 'copingThoughtDraft';
        thought.maxLength = 2000;
        thought.placeholder = 'You can keep this brief or skip it.';
        first.append(thought);
        const second = make('label', 'runner-field');
        second.htmlFor = 'copingAlternativeDraft';
        second.append(make('span', 'runner-field-label', 'A fairer or more balanced alternative (optional)'));
        const alternative = make('textarea');
        alternative.id = 'copingAlternativeDraft';
        alternative.maxLength = 2000;
        alternative.placeholder = 'It does not have to be positive or certain.';
        second.append(alternative);
        const note = make('p', 'runner-privacy-note', 'These notes remain only in the open page. They are not analyzed, saved, or sent.');
        content.append(first, second, note);
        active.refs.thought = thought;
        active.refs.alternative = alternative;
    }

    function buildModeContent() {
        content.replaceChildren();
        modeControls.replaceChildren();
        active.refs = active.refs || {};
        active.stepIndex = 0;
        active.released = false;
        active.journalTimerMs = null;
        journalTimerEndAt = null;
        journalClock = null;

        if (active.mode === 'breathing') {
            addInstruction('Follow the gentle circle only if it is comfortable. You may breathe normally, skip an optional hold, pause, or stop at any time.');
            phaseLiveRegion = make('p', 'runner-live-phase');
            phaseLiveRegion.setAttribute('role', 'status');
            phaseLiveRegion.setAttribute('aria-live', 'polite');
            phaseLiveRegion.dataset.breathLive = 'true';
            phaseLiveRegion.textContent = 'Ready. Start when you choose.';
            const skipHold = button('Skip this hold', 'breath-skip-hold');
            skipHold.hidden = true;
            skipHold.dataset.requiresRunning = 'true';
            modeControls.append(skipHold);
            content.append(phaseLiveRegion);
            active.refs.skipHold = skipHold;
        } else if (active.mode === 'grounding') {
            addInstruction('Move through each sense quietly. You do not need to type, say, or record anything. Smell and taste steps are optional; you can skip them.');
            const live = make('p', 'runner-live-phase');
            live.setAttribute('role', 'status');
            live.setAttribute('aria-live', 'polite');
            live.dataset.groundLive = 'true';
            content.append(live);
            buildGroundingControls();
        } else if (active.mode === 'muscle') {
            buildMuscleControls();
        } else if (active.mode === 'journal') {
            buildJournalContent();
        } else if (active.mode === 'reframe') {
            addInstruction('Consider the first thought, then make room for another possible view. You do not have to force a positive conclusion.');
            buildReframeContent();
        } else if (active.mode === 'movement') {
            addInstruction('Use the original activity instructions below to choose comfortable movement. There is no required speed or distance; stop whenever you choose.');
            const pathStatus = make('p', 'runner-live-phase', 'Ready when you are.');
            pathStatus.setAttribute('role', 'status');
            pathStatus.setAttribute('aria-live', 'polite');
            content.append(pathStatus);
            active.refs.movementStatus = pathStatus;
        } else {
            addInstruction('Follow the original activity instructions below, at a pace that feels manageable. You can pause, finish, or stop at any point.');
        }
    }

    function openPractice(trigger) {
        if (active) closePractice(false);
        const data = trigger.dataset;
        const name = data.activityName || 'Guided practice';
        const category = data.activityKind || '';
        active = {
            name,
            category,
            instructions: data.activityInstructions || '',
            duration: Number(data.activityDuration || 0) || null,
            mode: resolvePracticeMode(name, category),
            refs: {},
            stepIndex: 0,
            userNotes: Object.create(null),
        };
        runnerState = 'idle';
        elapsedBeforeRun = 0;
        startedAt = 0;
        lastAnnouncedPhase = '';
        restoreFocus = trigger;
        savedScrollY = pageScroller ? pageScroller.scrollTop : window.scrollY;
        title.textContent = name;
        sourceInstructions.textContent = active.instructions;
        buildVisual(active.mode);
        buildModeContent();
        setProgress(0, ['grounding', 'muscle'].includes(active.mode));
        setState('idle', 'Ready when you are. Nothing is saved or sent from this practice.');
        dialog.hidden = false;
        document.documentElement.classList.add('coping-practice-open');
        document.body.classList.add('coping-practice-open');
        pageScroller?.classList.add('coping-practice-open');
        startButton.focus();
    }

    function closePractice(restore = true) {
        cancelFrame();
        active = null;
        runnerState = 'stopped';
        dialog.hidden = true;
        document.documentElement.classList.remove('coping-practice-open');
        document.body.classList.remove('coping-practice-open');
        pageScroller?.classList.remove('coping-practice-open');
        if (restore) {
            if (pageScroller) pageScroller.scrollTop = savedScrollY;
            else window.scrollTo(0, savedScrollY);
            if (restoreFocus?.isConnected) restoreFocus.focus();
        }
        restoreFocus = null;
        content.replaceChildren();
        modeControls.replaceChildren();
        visual.replaceChildren();
        active = null;
    }

    function startPractice() {
        if (!active || runnerState !== 'idle') return;
        elapsedBeforeRun = 0;
        startedAt = performance.now();
        setState('running', 'Practice started. Pause or stop whenever you choose.');
        if (active.mode === 'movement' && active.refs.movementStatus) active.refs.movementStatus.textContent = 'Move at your own comfortable pace; this visual cue is not a distance or speed target.';
        if (active.mode === 'journal' && active.journalTimerMs) {
            journalClock = active.journalTimerMs;
            active.journalTimerStartedElapsed = 0;
        }
        pauseButton.focus();
        updateTimeline(performance.now());
        scheduleFrame();
    }

    function pausePractice(automatic = false) {
        if (!active || runnerState !== 'running') return;
        elapsedBeforeRun = currentElapsed();
        startedAt = 0;
        cancelFrame();
        const message = automatic
            ? 'Paused because this page is not visible. Resume when you are ready.'
            : 'Paused. Resume when you feel ready.';
        setState('paused', message);
        resumeButton.focus();
        updateTimeline(performance.now());
    }

    function resumePractice() {
        if (!active || runnerState !== 'paused') return;
        startedAt = performance.now();
        setState('running', 'Practice resumed. Pause or stop whenever you choose.');
        pauseButton.focus();
        scheduleFrame();
    }

    function restartPractice() {
        if (!active) return;
        const wasRunning = runnerState === 'running';
        cancelFrame();
        elapsedBeforeRun = 0;
        startedAt = runnerState === 'running' ? performance.now() : 0;
        lastAnnouncedPhase = '';
        if (active.mode === 'grounding') {
            active.stepIndex = 0;
            updateGroundingStep();
        } else if (active.mode === 'muscle') {
            active.stepIndex = 0;
            active.released = false;
            updateMuscleStep();
        }
        setProgress(0, ['breathing', 'grounding', 'muscle'].includes(active.mode));
        if (active.mode === 'breathing') {
            active.refs.breathText.textContent = 'Ready';
            active.refs.breathCount.textContent = '';
            active.refs.orb.style.setProperty('--breath-scale', '0.84');
            phaseLiveRegion.textContent = 'Ready. The first phase will begin when you choose Start.';
        }
        if (active.mode === 'journal' && journalClock !== null) {
            journalClock = active.journalTimerMs;
            active.refs.journalClock.textContent = `${Math.round(journalClock / 60000)}:00 optional timer`;
            active.journalTimerStartedElapsed = 0;
        }
        setState('idle', 'Restarted. Your on-page writing is still here; nothing has been saved or sent.');
        if (wasRunning) startPractice();
        else startButton.focus();
    }

    function finishPractice() {
        if (!active || !['running', 'paused'].includes(runnerState)) return;
        if (runnerState === 'running') elapsedBeforeRun = currentElapsed();
        cancelFrame();
        setState('completed', 'Finished for now. Any writing remains only in this page and will be discarded when it closes.');
        restartButton.focus();
        if (active.mode === 'breathing') {
            phaseLiveRegion.textContent = 'Practice finished when you choose.';
            active.refs.orb.style.setProperty('--breath-scale', '1');
        }
        if (active.mode === 'grounding') setProgress(100);
        if (active.mode === 'muscle') setProgress(100);
    }

    function applyBreathing(elapsedMs) {
        const currentPhase = breathingPhaseAt(elapsedMs, active.phases);
        const { phase, fraction, index, remainingSeconds } = currentPhase;
        if (active.refs.breathText.textContent !== phase.label) active.refs.breathText.textContent = phase.label;
        if (active.refs.breathCount.textContent !== `${remainingSeconds} seconds`) active.refs.breathCount.textContent = `${remainingSeconds} seconds`;
        const phaseKey = `${index}:${phase.label}`;
        if (phaseKey !== lastAnnouncedPhase) {
            lastAnnouncedPhase = phaseKey;
            phaseLiveRegion.textContent = `Current phase: ${phase.label.toLocaleLowerCase()}. ${phase.kind === 'hold' || phase.kind === 'rest' ? 'You can skip this phase and breathe normally.' : 'Keep the breath gentle; stop if uncomfortable.'}`;
        }
        const scale = phase.kind === 'inhale'
            ? 0.84 + (0.30 * fraction)
            : phase.kind === 'exhale'
                ? 1.14 - (0.30 * fraction)
                : phase.kind === 'hold' ? 1.14 : 0.84;
        breathScaleTarget = reduceMotion?.matches ? 1 : scale;
        active.refs.orb.style.setProperty('--breath-scale', breathScaleTarget.toFixed(3));
        setProgress(currentPhase.cycleFraction * 100);
        if (active.refs.skipHold) active.refs.skipHold.hidden = phase.kind !== 'hold';
    }

    function updateTimeline(now) {
        if (!active || runnerState !== 'running') return;
        const elapsed = currentElapsed(now);
        if (active.mode === 'breathing') applyBreathing(elapsed);
        if (active.mode === 'journal' && journalClock !== null) {
            const timerElapsed = Math.max(0, elapsed - (active.journalTimerStartedElapsed || 0));
            const remaining = Math.max(0, journalClock - timerElapsed);
            const totalSeconds = Math.ceil(remaining / 1000);
            const display = `${Math.floor(totalSeconds / 60)}:${String(totalSeconds % 60).padStart(2, '0')} optional timer`;
            if (active.refs.journalClock.textContent !== display) active.refs.journalClock.textContent = display;
            if (remaining <= 0) {
                elapsedBeforeRun = elapsed;
                cancelFrame();
                setState('paused', 'Optional timer ended. Finish now, restart it, or continue when you choose.');
                return;
            }
        }
        if (active.mode === 'movement' && active.refs.movementPath) {
            const fraction = (elapsed % 18000) / 18000;
            active.refs.movementPath.style.setProperty('--movement-progress', `${(fraction * 100).toFixed(1)}%`);
        }
    }

    function scheduleFrame() {
        if (runnerState === 'running' && rafId === null) {
            rafId = window.requestAnimationFrame((now) => {
                rafId = null;
                updateTimeline(now);
                scheduleFrame();
            });
        }
    }

    function activateAction(action) {
        if (!active) return;
        if (action === 'grounding-previous' && active.stepIndex > 0) {
            active.stepIndex -= 1;
            updateGroundingStep();
        } else if (action === 'grounding-next' && active.stepIndex < groundingSteps.length - 1) {
            active.stepIndex += 1;
            updateGroundingStep();
        } else if (action === 'grounding-finish') {
            finishPractice();
        } else if (action === 'muscle-previous' && active.stepIndex > 0) {
            active.stepIndex -= 1;
            updateMuscleStep();
        } else if (['muscle-next', 'muscle-skip'].includes(action) && active.stepIndex < muscleGroups.length - 1) {
            active.stepIndex += 1;
            updateMuscleStep();
        } else if (action === 'muscle-finish') {
            finishPractice();
        } else if (action === 'muscle-tense') {
            active.refs.musclePhase.textContent = `${muscleGroups[active.stepIndex]}: Release whenever you are ready. Keep the tension gentle and skip it if uncomfortable.`;
            active.refs.muscleTense.disabled = true;
        } else if (action === 'muscle-release') {
            active.released = true;
            active.refs.musclePhase.textContent = `${muscleGroups[active.stepIndex]}: Released. Move on whenever you are ready.`;
            active.refs.muscleTense.disabled = false;
        } else if (action === 'breath-skip-hold') {
            if (runnerState !== 'running') return;
            const now = performance.now();
            const elapsed = currentElapsed(now);
            const currentPhase = breathingPhaseAt(elapsed, active.phases);
            if (currentPhase.phase.kind !== 'hold' && currentPhase.phase.kind !== 'rest') return;
            const phaseOffset = active.phases.slice(0, currentPhase.index).reduce((sum, item) => sum + item.seconds * 1000, 0);
            const currentCyclePosition = elapsed % active.phases.reduce((sum, item) => sum + item.seconds * 1000, 0);
            const target = phaseOffset + currentPhase.phase.seconds * 1000;
            const adjustment = Math.max(0, target - currentCyclePosition);
            elapsedBeforeRun += adjustment;
            startedAt = now;
            updateTimeline(now);
        }
    }

    document.addEventListener('click', (event) => {
        const launch = event.target.closest?.('[data-practice-launch]');
        if (launch) {
            event.preventDefault();
            openPractice(launch);
        }
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closePractice();
            return;
        }
        if (event.target.closest?.('[data-runner-stop]')) {
            closePractice();
            return;
        }
        const action = event.target.closest?.('[data-runner-action]')?.dataset.runnerAction;
        if (action) activateAction(action);
    });

    dialog.addEventListener('click', (event) => {
        if (!active) return;
        if (event.target.closest?.('[data-runner-start]')) startPractice();
        else if (event.target.closest?.('[data-runner-pause]')) pausePractice();
        else if (event.target.closest?.('[data-runner-resume]')) resumePractice();
        else if (event.target.closest?.('[data-runner-restart]')) restartPractice();
        else if (event.target.closest?.('[data-runner-finish]')) finishPractice();
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePractice();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...dialog.querySelectorAll('button:not([disabled]):not([hidden]),textarea:not([disabled]),select:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])')]
            .filter((element) => !element.hidden && element.getClientRects().length > 0);
        if (!focusable.length) {
            event.preventDefault();
            panel.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) pausePractice(true);
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCopingRunner, { once: true });
    else initCopingRunner();
}
