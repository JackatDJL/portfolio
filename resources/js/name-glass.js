// Keep the home link plain while its header is visible; load glass only after it exits.
export function initNameGlass(wordmark) {
    const header = wordmark.closest('.site-header');
    if (!header) return false;
    wordmark.dataset.contrastMarker = 'false';
    wordmark.classList.add('name-glass-enabled');
    const label = document.createElement('span');
    label.className = 'name-glass-label';
    label.textContent = wordmark.textContent.trim();
    const surface = document.createElement('span');
    surface.className = 'name-glass-effect';
    surface.setAttribute('aria-hidden', 'true');
    wordmark.replaceChildren(surface, label);
    let active = true;
    let visible = false;
    let mounted = false;
    let hideTimer;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let glass;
    let loading;
    const draw = () => {
        if (!active || !glass) return;
        glass.render(mounted, label.offsetWidth, label.offsetHeight);
    };
    const update = (show) => {
        visible = show;
        clearTimeout(hideTimer);
        if (show) {
            mounted = true;
            wordmark.dataset.contrastMarker = 'false';
        }
        else hideTimer = setTimeout(() => {
            mounted = false;
            draw();
        }, motion.matches ? 0 : 420);
        wordmark.classList.toggle('name-island', show);
        wordmark.dispatchEvent(new CustomEvent('wordmark:glass-change', { bubbles: true }));
        if (show && !loading) {
            loading = import('./name-liquid-glass.js').then(({ mountNameGlass }) => {
                if (!active) return;
                glass = mountNameGlass(surface);
                draw();
            }).catch(() => { /* CSS keeps the name readable if the effect cannot load. */ });
        }
        draw();
    };
    const observer = new IntersectionObserver(([entry]) => {
        update(!entry.isIntersecting && entry.boundingClientRect.bottom <= 0);
    }, { threshold: 0 });
    observer.observe(header);
    const resize = new ResizeObserver(draw);
    resize.observe(label);
    const updateMotion = () => update(visible);
    motion.addEventListener('change', updateMotion);
    window.addEventListener('pagehide', event => {
        if (event.persisted) return;
        active = false;
        observer.disconnect();
        resize.disconnect();
        clearTimeout(hideTimer);
        motion.removeEventListener('change', updateMotion);
        glass?.destroy();
    });
    return true;
}
