(function (window, document) {
    'use strict';

    const DEFAULT_SOURCE = '/assets/branding/magi-sprite-normalized.png';
    const instances = new Set();
    const imageCache = new Map();
    let rafId = 0;
    let intersectionObserver = null;

    const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

    function spriteValue(root, key, fallback) {
        const canvas = root.querySelector('.magi-avatar__canvas');
        return canvas?.dataset?.[key] ?? root.dataset?.[key] ?? fallback;
    }

    function loadImage(src) {
        if (imageCache.has(src)) return imageCache.get(src);
        const promise = new Promise((resolve, reject) => {
            const image = new Image();
            image.decoding = 'async';
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('MAGI sprite sheet could not be loaded'));
            image.src = src;
        });
        imageCache.set(src, promise);
        return promise;
    }

    function frameSequence(root) {
        const state = root.dataset.state || 'idle';
        if (state === 'error') return [];
        const totalFrames = Math.max(1, Number(spriteValue(root, 'spriteFrames', 44)));
        const idleStart = Math.max(0, Math.min(totalFrames - 1, Number(spriteValue(root, 'spriteIdleStart', 4))));
        if (state === 'thinking') {
            const thinkingStart = Math.max(idleStart, totalFrames - 12);
            return Array.from({ length: totalFrames - thinkingStart }, (_, index) => thinkingStart + index);
        }
        return Array.from({ length: totalFrames - idleStart }, (_, index) => idleStart + index);
    }

    function introSequence(root) {
        const totalFrames = Math.max(1, Number(spriteValue(root, 'spriteFrames', 44)));
        const introCount = Math.max(0, Math.min(totalFrames, Number(spriteValue(root, 'spriteIntroCount', 0))));
        return Array.from({ length: introCount }, (_, index) => index);
    }

    function frameDuration(root) {
        if (root.dataset.state === 'responding') return 70;
        if (root.dataset.state === 'thinking') return 80;
        return 90;
    }

    function ensureCanvasSize(instance) {
        const { root, canvas } = instance;
        const rect = root.getBoundingClientRect();
        const cssWidth = Math.max(1, Math.round(rect.width || root.clientWidth || 42));
        const cssHeight = Math.max(1, Math.round(rect.height || root.clientHeight || cssWidth));
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const width = Math.max(1, Math.round(cssWidth * dpr));
        const height = Math.max(1, Math.round(cssHeight * dpr));
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
            instance.dpr = dpr;
            instance.cssWidth = cssWidth;
            instance.cssHeight = cssHeight;
            if (instance.image) drawFrame(instance);
        }
    }

    function drawFrame(instance) {
        if (!instance.image || !instance.ctx || instance.failed || reducedMotion()) return;
        const root = instance.root;
        const columns = Math.max(1, Number(spriteValue(root, 'spriteColumns', 10)));
        const rows = Math.max(1, Number(spriteValue(root, 'spriteRows', 10)));
        if (instance.image.naturalWidth % columns !== 0 || instance.image.naturalHeight % rows !== 0) {
            instance.failed = true;
            root.dataset.error = 'true';
            root.dataset.rendered = 'false';
            instances.delete(instance);
            return;
        }
        // The normalized atlas is intentionally divisible by its grid. Integer
        // source rectangles prevent neighboring cells from bleeding into one another.
        const sourceWidth = Math.floor(instance.image.naturalWidth / columns);
        const sourceHeight = Math.floor(instance.image.naturalHeight / rows);
        const frame = Math.max(0, Math.min(Number(spriteValue(root, 'spriteFrames', 44)) - 1, instance.frames[instance.frameIndex] ?? Number(spriteValue(root, 'spriteIdleStart', 4))));
        const column = frame % columns;
        const row = Math.floor(frame / columns);
        const targetWidth = instance.canvas.width;
        const targetHeight = instance.canvas.height;
        const sourceRatio = sourceWidth / sourceHeight;
        const targetRatio = targetWidth / targetHeight;
        let drawWidth = targetWidth;
        let drawHeight = targetHeight;
        let offsetX = 0;
        let offsetY = 0;
        if (sourceRatio > targetRatio) {
            drawHeight = targetWidth / sourceRatio;
            offsetY = (targetHeight - drawHeight) / 2;
        } else {
            drawWidth = targetHeight * sourceRatio;
            offsetX = (targetWidth - drawWidth) / 2;
        }
        instance.ctx.clearRect(0, 0, targetWidth, targetHeight);
        instance.ctx.drawImage(
            instance.image,
            column * sourceWidth,
            row * sourceHeight,
            sourceWidth,
            sourceHeight,
            offsetX,
            offsetY,
            drawWidth,
            drawHeight
        );
        root.dataset.rendered = 'true';
    }

    function setVisible(instance, visible) {
        instance.visible = visible;
        instance.root.classList.toggle('is-visible', visible);
        if (visible && !reducedMotion() && !instance.failed) {
            instance.lastTime = performance.now();
            instances.add(instance);
            schedule();
        } else {
            instances.delete(instance);
        }
    }

    function schedule() {
        if (!rafId && instances.size && document.visibilityState === 'visible') {
            rafId = window.requestAnimationFrame(tick);
        }
    }

    function tick(now) {
        rafId = 0;
        if (document.visibilityState !== 'visible' || reducedMotion()) {
            instances.clear();
            return;
        }
        instances.forEach(instance => {
            if (!instance.visible || instance.failed || !instance.image || !instance.frames.length) return;
            const elapsed = now - instance.lastTime;
            if (elapsed >= frameDuration(instance.root)) {
                const stepCount = Math.max(1, Math.floor(elapsed / frameDuration(instance.root)));
                instance.frameIndex += stepCount;
                instance.lastTime = now;
                if (instance.frameIndex >= instance.frames.length) {
                    if (!instance.introComplete) {
                        instance.introComplete = true;
                        instance.root.dataset.introPlayed = 'true';
                        instance.frames = frameSequence(instance.root);
                        instance.frameIndex = 0;
                        instance.root.dispatchEvent(new CustomEvent('magi:sprite-intro-complete', { bubbles: true }));
                    } else {
                        instance.frameIndex %= instance.frames.length;
                        instance.root.dispatchEvent(new CustomEvent('magi:sprite-cycle', { bubbles: true }));
                    }
                }
                drawFrame(instance);
            }
        });
        schedule();
    }

    function setupIntersectionObserver() {
        if (intersectionObserver || !('IntersectionObserver' in window)) return;
        intersectionObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const instance = entry.target.__magiSpriteInstance;
                if (instance) setVisible(instance, entry.isIntersecting);
            });
        }, { rootMargin: '80px', threshold: 0.01 });
    }

    function mount(root) {
        if (!root || root.dataset.animated !== 'true' || root.__magiSpriteInstance) return root?.__magiSpriteInstance;
        const canvas = root.querySelector('.magi-avatar__canvas');
        if (!canvas || !canvas.getContext) {
            root.dataset.error = 'true';
            return null;
        }
        const instance = {
            root,
            canvas,
            ctx: canvas.getContext('2d'),
            image: null,
            frames: [],
            frameIndex: 0,
            introComplete: root.dataset.intro !== 'true',
            visible: true,
            failed: false,
            lastTime: performance.now(),
            resizeObserver: null,
            alwaysVisible: root.dataset.avatarContext === 'home',
        };
        root.__magiSpriteInstance = instance;
        ensureCanvasSize(instance);
        if (reducedMotion()) return instance;
        setupIntersectionObserver();
        if (intersectionObserver && !instance.alwaysVisible) intersectionObserver.observe(root);
        if (typeof ResizeObserver !== 'undefined') {
            instance.resizeObserver = new ResizeObserver(() => ensureCanvasSize(instance));
            instance.resizeObserver.observe(root);
        }
        const source = canvas.dataset.spriteSrc || DEFAULT_SOURCE;
        loadImage(source).then(image => {
            instance.image = image;
            instance.frames = instance.introComplete ? frameSequence(root) : introSequence(root);
            instance.frameIndex = 0;
            drawFrame(instance);
            setVisible(instance, instance.visible);
        }).catch(() => {
            instance.failed = true;
            root.dataset.error = 'true';
            root.dataset.rendered = 'false';
            instances.delete(instance);
        });
        return instance;
    }

    function scan(scope) {
        const root = scope?.matches?.('[data-magi-avatar][data-animated="true"]') ? scope : null;
        if (root) mount(root);
        (scope || document).querySelectorAll?.('[data-magi-avatar][data-animated="true"]').forEach(mount);
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'visible') {
            instances.forEach(instance => { instance.lastTime = performance.now(); });
            schedule();
        } else {
            instances.clear();
            if (rafId) window.cancelAnimationFrame(rafId);
            rafId = 0;
        }
    }

    window.MagiSpriteAvatar = { mount, scan };

    document.addEventListener('visibilitychange', onVisibilityChange);
    document.addEventListener('DOMContentLoaded', () => {
        scan(document);
        if ('MutationObserver' in window) {
            const observer = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => scan(node))));
            observer.observe(document.body, { childList: true, subtree: true });
        }
        window.addEventListener('resize', () => instances.forEach(ensureCanvasSize), { passive: true });
    });
})(window, document);
