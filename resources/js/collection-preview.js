// The stream is complete HTML. The stage is a decorative copy, never a second link.
for (const root of document.querySelectorAll('[data-project-stream]')) {
    const records = [...root.querySelectorAll('[data-project-record]')];
    const stage = root.querySelector('[data-project-preview]');
    if (!stage || !records.length) continue;
    const desktop = matchMedia('(min-width: 64rem)');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let active;
    let animation;
    const activate = (record) => {
        if (!desktop.matches || record === active) return;
        const artifact = record.querySelector('[data-project-artifact]');
        if (!artifact) return;
        active?.classList.remove('is-active');
        active = record;
        active.classList.add('is-active');
        animation?.cancel();
        stage.replaceChildren(artifact.cloneNode(true));
        if (!reduced.matches) animation = stage.animate([{ opacity: 0.65, transform: 'translateY(4px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 180, easing: 'ease-out' });
    };
    root.classList.add('is-enhanced');
    activate(records[0]);
    records.forEach((record) => {
        record.addEventListener('pointerenter', () => activate(record));
        record.addEventListener('focusin', () => activate(record));
    });
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(() => {
            if (root.contains(document.activeElement) || root.querySelector('[data-project-record]:hover')) return;
            const center = innerHeight * 0.325;
            const nearest = records.map((record) => {
                const rect = record.getBoundingClientRect();
                return { record, distance: Math.abs((rect.top + rect.bottom) / 2 - center) };
            }).sort((a, b) => a.distance - b.distance)[0];
            if (nearest) activate(nearest.record);
        }, { rootMargin: '-20% 0px -55% 0px' });
        records.forEach((record) => observer.observe(record));
    }
    desktop.addEventListener('change', () => { active?.classList.remove('is-active'); active = null; activate(records[0]); });
    reduced.addEventListener('change', () => animation?.cancel());
}
