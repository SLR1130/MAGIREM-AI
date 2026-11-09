/**
 * MinSuansCore shared progressive enhancement.
 * Uses native dialog APIs when available and leaves normal links/forms untouched.
 */
(() => {
    const openDialog = (id) => {
        const dialog = document.getElementById(id);
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    };

    const closeDialog = (dialog) => {
        if (!dialog) return;
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    };

    document.addEventListener('click', (event) => {
        const openTrigger = event.target.closest?.('[data-msc-modal-open]');
        if (openTrigger) {
            event.preventDefault();
            openDialog(openTrigger.getAttribute('data-msc-modal-open'));
            return;
        }

        const closeTrigger = event.target.closest?.('[data-msc-modal-close]');
        if (closeTrigger) {
            event.preventDefault();
            closeDialog(closeTrigger.closest('dialog'));
            return;
        }

        if (typeof HTMLDialogElement !== 'undefined' && event.target instanceof HTMLDialogElement && event.target.classList.contains('msc-dialog')) {
            const bounds = event.target.getBoundingClientRect();
            const inside = event.clientX >= bounds.left && event.clientX <= bounds.right
                && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
            if (!inside) closeDialog(event.target);
        }
    });
})();
/**
 * Botanical Home hero copy rotation and guest navigation enhancement.
 * Copy changes are text-only; the existing actions remain stable and accessible.
 */
(() => {
    const reducedQuery = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const states = [
        {
            eyebrow: 'A WELLNESS SPACE FOR MINSU STUDENTS',
            lineOne: 'Student life',
            lineTwo: 'gets heavy.',
            italic: 'Start here.',
            description: 'Talk with MAGI, write in your journal, or connect with Guidance support—one step at a time.',
        },
        {
            eyebrow: 'A TOOL FOR THE MOMENT YOU ARE IN',
            lineOne: 'A lot can land',
            lineTwo: 'in one day.',
            italic: 'Pause first.',
            description: 'Try a grounding activity or find a wellness resource that fits this moment.',
        },
        {
            eyebrow: 'A PLACE TO PUT YOUR THOUGHTS',
            lineOne: 'Write down',
            lineTwo: 'what is going on.',
            italic: 'At your pace.',
            description: 'Use your journal to reflect on the day and return to your thoughts when you are ready.',
        },
        {
            eyebrow: 'HUMAN SUPPORT IS PART OF THE PATH',
            lineOne: 'You do not have',
            lineTwo: 'to carry it all.',
            italic: 'Reach out.',
            description: 'Find a counselor, explore the community, or book an appointment when talking feels right.',
        },
    ];

    document.querySelectorAll('[data-msc-hero-copy]').forEach((copy) => {
        const eyebrow = copy.querySelector('[data-msc-hero-eyebrow]');
        const lineOne = copy.querySelector('[data-msc-hero-line-one]');
        const lineTwo = copy.querySelector('[data-msc-hero-line-two]');
        const italic = copy.querySelector('[data-msc-hero-italic]');
        const description = copy.querySelector('[data-msc-hero-description]');
        const hero = copy.closest('.msc-home-copy');
        const controls = hero?.querySelector('[data-msc-hero-controls]');
        const previousButton = controls?.querySelector('[data-msc-hero-previous]');
        const nextButton = controls?.querySelector('[data-msc-hero-next]');
        const pauseButton = controls?.querySelector('[data-msc-hero-pause]');
        const pauseIcon = controls?.querySelector('[data-msc-hero-pause-icon]');
        const status = controls?.querySelector('[data-msc-hero-status]');
        const progress = controls?.querySelector('[data-msc-hero-progress]');
        const announcement = controls?.querySelector('[data-msc-hero-announcement]');
        if (!eyebrow || !lineOne || !lineTwo || !italic || !description) return;

        let index = 0;
        let timer = null;
        let swapTimer = null;
        let typingTimer = null;
        let typingRun = 0;
        let paused = false;

        const announce = (state) => {
            if (announcement) {
                announcement.textContent = `${state.eyebrow}. ${state.lineOne} ${state.lineTwo} ${state.italic} ${state.description}`;
            }
        };

        const clearTyping = () => {
            window.clearTimeout(typingTimer);
            typingRun += 1;
            [lineOne, lineTwo, italic, description].forEach((element) => element.classList.remove('is-typing'));
            copy.classList.remove('is-typewriting');
        };

        const paint = (state, shouldAnnounce = false) => {
            clearTyping();
            eyebrow.textContent = state.eyebrow;
            lineOne.textContent = state.lineOne;
            lineTwo.textContent = state.lineTwo;
            italic.textContent = state.italic;
            description.textContent = state.description;
            if (shouldAnnounce) announce(state);
        };

        const typeState = (state) => {
            clearTyping();
            const run = typingRun;
            eyebrow.textContent = state.eyebrow;
            const sequence = [
                { element: lineOne, text: state.lineOne, speed: 48, gap: 80 },
                { element: lineTwo, text: state.lineTwo, speed: 48, gap: 100 },
                { element: italic, text: state.italic, speed: 44, gap: 150 },
                { element: description, text: state.description, speed: 20, gap: 0 },
            ];
            sequence.forEach(({ element }) => {
                element.textContent = '';
                element.classList.remove('is-typing');
            });
            copy.classList.add('is-typewriting');

            let sequenceIndex = 0;
            let characterIndex = 0;
            const typeNextCharacter = () => {
                if (run !== typingRun) return;
                const current = sequence[sequenceIndex];
                if (!current) {
                    copy.classList.remove('is-typewriting');
                    announce(state);
                    return;
                }

                current.element.classList.add('is-typing');
                characterIndex += 1;
                current.element.textContent = current.text.slice(0, characterIndex);

                if (characterIndex < current.text.length) {
                    typingTimer = window.setTimeout(typeNextCharacter, current.speed);
                    return;
                }

                current.element.classList.remove('is-typing');
                sequenceIndex += 1;
                characterIndex = 0;
                typingTimer = window.setTimeout(typeNextCharacter, current.gap);
            };

            typingTimer = window.setTimeout(typeNextCharacter, 90);
        };

        const updateControls = () => {
            if (status) {
                status.textContent = `${String(index + 1).padStart(2, '0')} / ${String(states.length).padStart(2, '0')}`;
            }
            if (pauseButton) {
                pauseButton.setAttribute('aria-pressed', String(paused));
                pauseButton.setAttribute('aria-label', paused ? 'Resume rotating messages' : 'Pause rotating messages');
            }
            if (pauseIcon) {
                pauseIcon.textContent = paused ? '\u25b6' : '\u275a\u275a';
            }
        };

        const restartProgress = () => {
            if (!progress) return;
            progress.classList.remove('is-running', 'is-paused');
            void progress.offsetWidth;
            if (!reducedQuery?.matches) {
                progress.classList.add('is-running');
            }
        };

        const stopClock = () => {
            window.clearTimeout(timer);
            progress?.classList.add('is-paused');
        };

        const go = (nextIndex) => {
            index = (nextIndex + states.length) % states.length;
            updateControls();
            window.clearTimeout(swapTimer);
            if (reducedQuery?.matches) {
                paint(states[index], true);
                copy.classList.remove('is-transitioning');
                return;
            }
            copy.classList.add('is-transitioning');
            swapTimer = window.setTimeout(() => {
                typeState(states[index]);
                copy.classList.remove('is-transitioning');
            }, 220);
        };

        const schedule = () => {
            window.clearTimeout(timer);
            restartProgress();
            const heroFocused = hero?.contains(document.activeElement) ?? false;
            const heroHovered = hero?.matches(':hover') ?? false;
            if (reducedQuery?.matches || document.hidden || paused || heroFocused || heroHovered) {
                progress?.classList.add('is-paused');
                return;
            }
            timer = window.setTimeout(() => {
                go(index + 1);
                schedule();
            }, 10000);
        };

        previousButton?.addEventListener('click', () => {
            go(index - 1);
            schedule();
        });
        nextButton?.addEventListener('click', () => {
            go(index + 1);
            schedule();
        });
        pauseButton?.addEventListener('click', () => {
            paused = !paused;
            updateControls();
            schedule();
        });

        // Pause the rotating hero whenever a visitor is interacting anywhere in
        // its copy, controls, or calls to action (not just over the headline).
        hero?.addEventListener('mouseenter', stopClock);
        hero?.addEventListener('mouseleave', schedule);
        hero?.addEventListener('focusin', stopClock);
        hero?.addEventListener('focusout', (event) => {
            if (!hero.contains(event.relatedTarget)) schedule();
        });
        document.addEventListener('visibilitychange', schedule);
        reducedQuery?.addEventListener?.('change', () => {
            if (reducedQuery.matches) {
                window.clearTimeout(timer);
                window.clearTimeout(swapTimer);
                copy.classList.remove('is-transitioning');
                paint(states[index]);
            } else {
                typeState(states[index]);
            }
            schedule();
        });
        if (reducedQuery?.matches) {
            paint(states[index]);
        } else {
            typeState(states[index]);
        }
        updateControls();
        schedule();
    });

    document.querySelectorAll('[data-msc-hero-art]').forEach((art) => {
        const stage = art.closest('.msc-home-stage');
        let pointerFrame = null;
        let scrollFrame = null;
        const scrollRoot = art.closest('.msc-home-page--student')
            ? (document.querySelector('.app-main') || window)
            : window;

        const updateScrollDepth = () => {
            if (scrollFrame) window.cancelAnimationFrame(scrollFrame);
            scrollFrame = window.requestAnimationFrame(() => {
                if (reducedQuery?.matches) {
                    art.style.setProperty('--msc-art-scroll-y', '0px');
                    stage?.style.setProperty('--msc-stage-pan-y', '0px');
                } else {
                    const bounds = art.getBoundingClientRect();
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    const normalized = Math.max(-1, Math.min(1, ((bounds.top + bounds.height / 2) - viewportHeight / 2) / (viewportHeight / 2)));
                    art.style.setProperty('--msc-art-scroll-y', `${(-normalized * 30).toFixed(1)}px`);
                    stage?.style.setProperty('--msc-stage-pan-y', `${(-normalized * 9).toFixed(1)}px`);
                }
                scrollFrame = null;
            });
        };

        const resetPointerTilt = () => {
            if (pointerFrame) window.cancelAnimationFrame(pointerFrame);
            pointerFrame = null;
            art.style.setProperty('--msc-art-tilt-x', '0deg');
            art.style.setProperty('--msc-art-tilt-y', '0deg');
            art.style.setProperty('--msc-art-shift-x', '0px');
            art.style.setProperty('--msc-art-shift-y', '0px');
            stage?.style.setProperty('--msc-stage-pan-x', '0px');
            stage?.style.setProperty('--msc-stage-pan-y', '0px');
        };

        art.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'touch' || reducedQuery?.matches) return;
            const bounds = art.getBoundingClientRect();
            const horizontal = ((event.clientX - bounds.left) / bounds.width) - 0.5;
            const vertical = ((event.clientY - bounds.top) / bounds.height) - 0.5;
            if (pointerFrame) window.cancelAnimationFrame(pointerFrame);
            pointerFrame = window.requestAnimationFrame(() => {
                // The local foreground, air and distant landscape move at different depths.
                art.style.setProperty('--msc-art-shift-x', `${(-horizontal * 20).toFixed(1)}px`);
                art.style.setProperty('--msc-art-shift-y', `${(-vertical * 12).toFixed(1)}px`);
                art.style.setProperty('--msc-art-tilt-x', `${(vertical * -3.2).toFixed(2)}deg`);
                art.style.setProperty('--msc-art-tilt-y', `${(horizontal * 4.4).toFixed(2)}deg`);
                stage?.style.setProperty('--msc-stage-pan-x', `${(horizontal * 7).toFixed(1)}px`);
                stage?.style.setProperty('--msc-stage-pan-y', `${(vertical * 4).toFixed(1)}px`);
                pointerFrame = null;
            });
        });

        art.addEventListener('pointerleave', resetPointerTilt);
        scrollRoot.addEventListener('scroll', updateScrollDepth, { passive: true });
        window.addEventListener('resize', updateScrollDepth, { passive: true });
        updateScrollDepth();
        reducedQuery?.addEventListener?.('change', () => {
            resetPointerTilt();
            updateScrollDepth();
        });
    });

    const menu = document.getElementById('mscPublicMenu');
    const nav = document.getElementById('mscPublicNav');
    if (menu && nav) {
        menu.addEventListener('click', () => {
            const expanded = menu.getAttribute('aria-expanded') === 'true';
            menu.setAttribute('aria-expanded', String(!expanded));
            nav.classList.toggle('is-open', !expanded);
        });
        nav.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                nav.classList.remove('is-open');
                menu.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                nav.classList.remove('is-open');
                menu.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();

/**
 * Home and landing page interaction layer.
 * Content stays visible without JavaScript and navigation remains ordinary links.
 */
(() => {
    const home = document.querySelector('.msc-home-page');
    if (!home) return;

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const revealItems = [...document.querySelectorAll('[data-msc-reveal]')];
    const studentScrollRoot = home.classList.contains('msc-home-page--student')
        ? document.querySelector('.app-main')
        : null;
    const scrollBehaviorRoot = studentScrollRoot || document.documentElement;
    const syncScrollBehavior = () => {
        scrollBehaviorRoot.classList.toggle('msc-home-smooth-scroll', !reducedMotion?.matches);
    };

    syncScrollBehavior();

    const revealEverything = () => {
        document.documentElement.classList.remove('msc-motion-ready');
        revealItems.forEach((item) => item.classList.add('is-visible'));
    };

    if (reducedMotion?.matches || !('IntersectionObserver' in window)) {
        revealEverything();
    } else {
        document.documentElement.classList.add('msc-motion-ready');
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                entry.target.classList.toggle('is-visible', entry.isIntersecting);
            });
        }, { root: studentScrollRoot, threshold: 0.18, rootMargin: '0px 0px -10% 0px' });
        revealItems.forEach((item) => revealObserver.observe(item));
    }

    reducedMotion?.addEventListener?.('change', () => {
        syncScrollBehavior();
        if (reducedMotion.matches) revealEverything();
    });

    document.querySelectorAll('[data-msc-pathways]').forEach((pathways) => {
        const tabs = [...pathways.querySelectorAll('[data-msc-pathway-tab]')];
        const panels = [...pathways.querySelectorAll('[data-msc-pathway-panel]')];
        if (!tabs.length || !panels.length) return;
        let activeIndex = Math.max(0, tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true'));

        const activate = (tab, moveFocus = false) => {
            const nextIndex = tabs.indexOf(tab);
            if (nextIndex < 0) return;
            if (nextIndex === activeIndex) {
                if (moveFocus) tab.focus();
                return;
            }

            const direction = nextIndex > activeIndex ? 1 : -1;
            activeIndex = nextIndex;
            const key = tab.dataset.mscPathwayTab;
            tabs.forEach((candidate) => {
                const active = candidate === tab;
                candidate.classList.toggle('is-active', active);
                candidate.setAttribute('aria-selected', String(active));
                candidate.tabIndex = active ? 0 : -1;
            });
            panels.forEach((panel) => {
                const active = panel.dataset.mscPathwayPanel === key;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
                if (active) panel.style.setProperty('--msc-panel-enter-x', `${direction * 22}px`);
            });
            pathways.querySelector('.msc-pathways__tabs')?.style.setProperty('--msc-pathway-progress', `${((nextIndex + 0.5) / tabs.length) * 100}%`);
            if (moveFocus) tab.focus();
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab));
            tab.addEventListener('keydown', (event) => {
                let nextIndex = null;
                if (event.key === 'ArrowDown' || event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
                if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') nextIndex = 0;
                if (event.key === 'End') nextIndex = tabs.length - 1;
                if (nextIndex === null) return;
                event.preventDefault();
                activate(tabs[nextIndex], true);
            });
        });
    });

    if (window.matchMedia?.('(hover: hover) and (pointer: fine)').matches && !reducedMotion?.matches) {
        const surfaces = new Set(document.querySelectorAll('[data-msc-spotlight], [data-msc-tilt]'));
        surfaces.forEach((surface) => {
            let frame = null;
            surface.addEventListener('pointermove', (event) => {
                const bounds = surface.getBoundingClientRect();
                const localX = event.clientX - bounds.left;
                const localY = event.clientY - bounds.top;
                const x = `${localX}px`;
                const y = `${localY}px`;
                const horizontal = (localX / bounds.width) - .5;
                const vertical = (localY / bounds.height) - .5;
                if (frame) window.cancelAnimationFrame(frame);
                frame = window.requestAnimationFrame(() => {
                    if (surface.hasAttribute('data-msc-spotlight')) {
                        surface.style.setProperty('--msc-pointer-x', x);
                        surface.style.setProperty('--msc-pointer-y', y);
                    }
                    if (surface.hasAttribute('data-msc-tilt')) {
                        const intensity = surface.classList.contains('msc-home-closing__card') ? 2 : 5;
                        surface.style.setProperty('--msc-tilt-x', `${(vertical * -intensity).toFixed(2)}deg`);
                        surface.style.setProperty('--msc-tilt-y', `${(horizontal * intensity * 1.25).toFixed(2)}deg`);
                        surface.classList.add('is-tilting');
                    }
                    frame = null;
                });
            });
            surface.addEventListener('pointerleave', () => {
                if (frame) window.cancelAnimationFrame(frame);
                frame = null;
                surface.style.setProperty('--msc-tilt-x', '0deg');
                surface.style.setProperty('--msc-tilt-y', '0deg');
                surface.classList.remove('is-tilting');
            });
        });
    }
})();
