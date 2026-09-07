import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// The stream remains complete, linked HTML. This is only a decorative desktop copy.
for (const root of document.querySelectorAll('[data-project-stream]')) {
    const records = [...root.querySelectorAll('[data-project-record]')];
    const stage = root.querySelector('[data-project-preview]');
    if (!stage || !records.length) continue;
    const desktop = matchMedia('(min-width: 64rem) and (hover: hover) and (pointer: fine)');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let active = null;
    let activeMedia = null;
    let previewWidth = 0;
    let previewHeight = 0;
    let pointerX = 0;
    let pointerY = 0;
    const setX = gsap.quickTo(stage, 'x', { duration: 0.28, ease: 'power3.out' });
    const setY = gsap.quickTo(stage, 'y', { duration: 0.28, ease: 'power3.out' });

    const measure = () => {
        const rect = stage.getBoundingClientRect();
        previewWidth = rect.width;
        previewHeight = rect.height;
    };
    const position = (x, y) => {
        measure();
        const gap = 28;
        let targetX = x + gap;
        let targetY = y + gap;
        if (targetX + previewWidth > innerWidth - gap) targetX = x - previewWidth - gap;
        if (targetY + previewHeight > innerHeight - gap) targetY = y - previewHeight - gap;
        targetX = Math.max(gap, Math.min(targetX, innerWidth - previewWidth - gap));
        targetY = Math.max(gap, Math.min(targetY, innerHeight - previewHeight - gap));
        if (reduced.matches) gsap.set(stage, { x: targetX, y: targetY });
        else { setX(targetX); setY(targetY); }
    };
    const show = () => stage.classList.add('is-visible');
    const hide = () => {
        stage.classList.remove('is-visible');
        active?.classList.remove('is-active');
        active = null;
    };
    const activate = (record, stable = false) => {
        if (!desktop.matches) return;
        const artifact = record.querySelector('[data-project-artifact]');
        if (!artifact) return;
        active?.classList.remove('is-active');
        active = record;
        active.classList.add('is-active');
        if (stable) position(innerWidth - previewWidth - 48, Math.min(112, innerHeight - previewHeight - 28));
        else position(pointerX, pointerY);
        show();
        const source = record.querySelector('h2')?.textContent || '';
        if (activeMedia?.dataset.source === source) return;
        const next = document.createElement('div');
        next.className = 'project-float__media';
        next.dataset.source = source;
        next.append(artifact.cloneNode(true));
        stage.append(next);
        const previous = activeMedia;
        activeMedia = next;
        if (reduced.matches) { previous?.remove(); return; }
        gsap.fromTo(next, { autoAlpha: 0, scale: 0.97, clipPath: 'inset(6% 0 0 0)' }, { autoAlpha: 1, scale: 1, clipPath: 'inset(0 0 0 0)', duration: 0.24, ease: 'power2.out' });
        if (previous) gsap.to(previous, { autoAlpha: 0, scale: 1.025, duration: 0.16, ease: 'power1.in', onComplete: () => previous.remove() });
    };
    const prime = (record) => {
        const artifact = record.querySelector('[data-project-artifact]');
        if (!artifact || activeMedia) return;
        const first = document.createElement('div');
        first.className = 'project-float__media';
        first.dataset.source = record.querySelector('h2')?.textContent || '';
        first.append(artifact.cloneNode(true));
        const image = first.querySelector('img');
        if (image) image.loading = 'eager';
        stage.append(first);
        activeMedia = first;
    };
    const enable = () => {
        if (!desktop.matches) return;
        root.classList.add('is-enhanced');
        records.forEach((record) => ScrollTrigger.create({
            trigger: record,
            start: 'top 72%',
            end: 'bottom 28%',
            toggleClass: { targets: record, className: 'is-viewport' },
        }));
    };
    const disable = () => {
        root.classList.remove('is-enhanced');
        hide();
        stage.replaceChildren();
        activeMedia = null;
        ScrollTrigger.getAll().filter((trigger) => records.includes(trigger.trigger)).forEach((trigger) => trigger.kill());
    };
    enable();
    prime(records[0]);
    const initiallyHovered = root.querySelector('[data-project-record]:hover');
    if (initiallyHovered instanceof HTMLElement) {
        const rect = initiallyHovered.getBoundingClientRect();
        pointerX = rect.left + Math.min(rect.width * 0.5, 180);
        pointerY = rect.top + Math.min(rect.height * 0.35, 160);
        activate(initiallyHovered);
    }
    records.forEach((record) => {
        record.addEventListener('pointerenter', (event) => { pointerX = event.clientX; pointerY = event.clientY; activate(record); });
        record.addEventListener('focusin', () => activate(record, true));
    });
    root.addEventListener('pointermove', (event) => { pointerX = event.clientX; pointerY = event.clientY; if (active) position(pointerX, pointerY); });
    root.addEventListener('pointerleave', () => { if (!root.contains(document.activeElement)) hide(); });
    root.addEventListener('focusout', () => requestAnimationFrame(() => { if (!root.contains(document.activeElement) && !root.matches(':hover')) hide(); }));
    addEventListener('resize', () => { if (active) position(pointerX, pointerY); }, { passive: true });
    desktop.addEventListener('change', () => desktop.matches ? enable() : disable());
    reduced.addEventListener('change', () => { if (active) position(pointerX, pointerY); });
}
