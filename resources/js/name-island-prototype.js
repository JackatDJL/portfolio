// URL-only experiment. React/glass are loaded only after the header leaves view.
export function initNameIsland(wordmark) {
    if (new URLSearchParams(window.location.search).get('name') !== 'island') return false;
    const header = wordmark.closest('.site-header');
    if (!header) return false;
    wordmark.dataset.contrastMarker = 'false';
    const label = document.createElement('span');
    label.className = 'name-glass-label';
    label.textContent = wordmark.textContent.trim();
    const surface = document.createElement('span');
    surface.className = 'name-glass-effect';
    surface.setAttribute('aria-hidden', 'true');
    wordmark.replaceChildren(surface, label);
    let active = true;
    let visible = false;
    let glass;
    let loading;
    const draw = () => {
        if (!active || !glass) return;
        glass.render(visible, label.offsetWidth, label.offsetHeight);
    };
    const update = (show) => {
        visible = show;
        wordmark.classList.toggle('name-island', show);
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
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    motion.addEventListener('change', draw);
    window.addEventListener('pagehide', event => {
        if (event.persisted) return;
        active = false;
        observer.disconnect();
        resize.disconnect();
        motion.removeEventListener('change', draw);
        glass?.destroy();
    });
    return true;
}
