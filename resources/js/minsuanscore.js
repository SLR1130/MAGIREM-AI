// Use the actual scrolling ancestor, not .app-main when the student shell makes
// it overflow:visible. Both the photo depth and reveal observer share this rule.
const getHomeScrollRoot = (element) => {
    let ancestor = element.parentElement;
    while (ancestor && ancestor !== document.body && ancestor !== document.documentElement) {
        const overflow = window.getComputedStyle(ancestor).overflowY;
        if (/(auto|scroll)/.test(overflow) && ancestor.scrollHeight > ancestor.clientHeight) return ancestor;
        ancestor = ancestor.parentElement;
    }
    return null;
};

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
        const homePage = copy.closest('.msc-home-page');
        if (!eyebrow || !lineOne || !lineTwo || !italic || !description) return;

        let index = 0;
        let timer = null;
        let swapTimer = null;
        let paused = false;

        const announce = (state) => {
            if (announcement) {
                announcement.textContent = `${state.eyebrow}. ${state.lineOne} ${state.lineTwo} ${state.italic} ${state.description}`;
            }
        };

        const paint = (state, shouldAnnounce = false) => {
            eyebrow.textContent = state.eyebrow;
            lineOne.textContent = state.lineOne;
            lineTwo.textContent = state.lineTwo;
            italic.textContent = state.italic;
            description.textContent = state.description;
            if (shouldAnnounce) announce(state);
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
            if (reducedQuery?.matches || homePage?.dataset.mscMotionPaused === 'true') {
                paint(states[index], true);
                copy.classList.remove('is-transitioning');
                return;
            }
            copy.classList.add('is-transitioning');
            swapTimer = window.setTimeout(() => {
                paint(states[index], true);
                copy.classList.remove('is-transitioning');
            }, 220);
        };

        const schedule = () => {
            window.clearTimeout(timer);
            restartProgress();
            const heroFocused = hero?.contains(document.activeElement) ?? false;
            const heroHovered = hero?.matches(':hover') ?? false;
            if (reducedQuery?.matches || homePage?.dataset.mscMotionPaused === 'true' || document.hidden || paused || heroFocused || heroHovered) {
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
        homePage?.addEventListener('msc:motionchange', schedule);
        reducedQuery?.addEventListener?.('change', () => {
            if (reducedQuery.matches) {
                window.clearTimeout(timer);
                window.clearTimeout(swapTimer);
                copy.classList.remove('is-transitioning');
                paint(states[index]);
            }
            schedule();
        });
        paint(states[index]);
        updateControls();
        schedule();
    });

    document.querySelectorAll('[data-msc-hero-art]').forEach((art) => {
        const home = art.closest('.msc-home-page');
        const pauseButton = art.querySelector('[data-msc-scene-pause]');
        const pauseLabel = art.querySelector('[data-msc-scene-pause-label]');
        const pauseIcon = art.querySelector('[data-msc-scene-pause-icon]');
        const scrollRoot = getHomeScrollRoot(art) || window;
        let scrollFrame = null;
        let userPaused = false;
        const motionAllowed = () => !reducedQuery?.matches
            && home?.dataset.mscMotionPaused !== 'true' && !document.hidden;

        // The photograph stays still; only ambient layers animate while visible.
        const updateSceneVisibility = () => {
            if (scrollFrame) return;
            scrollFrame = window.requestAnimationFrame(() => {
                const bounds = art.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const visible = bounds.bottom > 0 && bounds.top < viewportHeight;
                art.dataset.mscSceneVisible = String(visible && motionAllowed());
                scrollFrame = null;
            });
        };
        const syncMotion = () => {
            const paused = Boolean(reducedQuery?.matches || userPaused);
            if (home) home.dataset.mscMotionPaused = String(paused);
            if (pauseButton) {
                pauseButton.hidden = false;
                pauseButton.disabled = Boolean(reducedQuery?.matches);
                pauseButton.setAttribute('aria-pressed', String(paused));
                pauseButton.setAttribute('aria-label', reducedQuery?.matches ? 'Motion disabled by your reduced-motion preference' : paused ? 'Resume homepage motion' : 'Pause homepage motion');
            }
            if (pauseLabel) pauseLabel.textContent = reducedQuery?.matches ? 'Reduced motion' : paused ? 'Resume motion' : 'Pause motion';
            pauseIcon?.setAttribute('d', paused ? 'm9 5 10 7-10 7V5Z' : 'M9 5v14M15 5v14');
            updateSceneVisibility();
            home?.dispatchEvent(new CustomEvent('msc:motionchange'));
        };

        pauseButton?.addEventListener('click', () => { userPaused = !userPaused; syncMotion(); });
        scrollRoot.addEventListener('scroll', updateSceneVisibility, { passive: true });
        window.addEventListener('resize', updateSceneVisibility, { passive: true });
        document.addEventListener('visibilitychange', syncMotion);
        reducedQuery?.addEventListener?.('change', syncMotion);
        syncMotion();
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
    const studentScrollRoot = getHomeScrollRoot(home);
    const scrollBehaviorRoot = studentScrollRoot || document.documentElement;
    const syncScrollBehavior = () => {
        scrollBehaviorRoot.classList.toggle('msc-home-smooth-scroll', !reducedMotion?.matches && home.dataset.mscMotionPaused !== 'true');
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
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        }, { root: studentScrollRoot, threshold: 0.18, rootMargin: '0px 0px -10% 0px' });
        revealItems.forEach((item) => revealObserver.observe(item));
    }

    reducedMotion?.addEventListener?.('change', () => {
        syncScrollBehavior();
        if (reducedMotion.matches) revealEverything();
    });
    home.addEventListener('msc:motionchange', () => {
        syncScrollBehavior();
        if (home.dataset.mscMotionPaused === 'true') revealEverything();
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

    if (window.matchMedia?.('(hover: hover) and (pointer: fine)').matches) {
        const surfaces = new Set(document.querySelectorAll('[data-msc-spotlight], [data-msc-tilt]'));
        surfaces.forEach((surface) => {
            let frame = null;
            surface.addEventListener('pointermove', (event) => {
                if (event.pointerType === 'touch' || reducedMotion?.matches || home.dataset.mscMotionPaused === 'true') return;
                const bounds = surface.getBoundingClientRect();
                const localX = event.clientX - bounds.left;
                const localY = event.clientY - bounds.top;
                const x = `${localX}px`;
                const y = `${localY}px`;
                const horizontal = (localX / bounds.width) - .5;
                const vertical = (localY / bounds.height) - .5;
                if (frame) window.cancelAnimationFrame(frame);
                frame = window.requestAnimationFrame(() => {
                    if (reducedMotion?.matches || home.dataset.mscMotionPaused === 'true') { frame = null; return; }
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
            const resetTilt = () => {
                if (frame) window.cancelAnimationFrame(frame);
                frame = null;
                surface.style.setProperty('--msc-tilt-x', '0deg');
                surface.style.setProperty('--msc-tilt-y', '0deg');
                surface.classList.remove('is-tilting');
            };
            surface.addEventListener('pointerleave', resetTilt);
            reducedMotion?.addEventListener?.('change', resetTilt);
            home.addEventListener('msc:motionchange', resetTilt);
        });
    }
})();
