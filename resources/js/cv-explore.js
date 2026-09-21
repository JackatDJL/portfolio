import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    const open = root?.querySelector('[data-cv-explore-open]');
    const close = root?.querySelector('[data-cv-explore-close]');
    const content = root?.querySelector('[data-cv-explore-content]');
    const track = root?.querySelector('[data-cv-timeline-track]');
    const milestones = [...(root?.querySelectorAll('[data-cv-milestone]') || [])];
    if (!(root instanceof HTMLElement) || !(open instanceof HTMLButtonElement) || !(close instanceof HTMLButtonElement) || !(content instanceof HTMLElement) || !(track instanceof HTMLElement)) return;
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mobile = window.matchMedia('(max-width: 47.99rem), (pointer: coarse)');
    let trigger;

    const setActive = (progress = 0) => {
        const active = Math.round(progress * Math.max(0, milestones.length - 1));
        milestones.forEach((item, index) => item.toggleAttribute('data-active', index === active));
    };
    const layout = () => {
        const dates = milestones.map((item) => new Date(`${item.dataset.date}T00:00:00`).getTime());
        const first = Math.min(...dates); const last = Math.max(...dates); const span = Math.max(1, last - first);
        milestones.forEach((item, index) => {
            const chronological = (dates[index] - first) / span;
            item.style.setProperty('--timeline-position', `${(0.08 + Math.pow(chronological, 0.82) * 0.84) * 100}%`);
        });
    };
    const destroyPin = () => { trigger?.kill(); trigger = undefined; gsap.set(track, { clearProps: 'transform,width' }); };
    const createPin = () => {
        destroyPin(); layout(); setActive(0);
        if (motion.matches || mobile.matches || content.hidden) return;
        track.style.width = `${Math.max(window.innerWidth * 2.25, milestones.length * 520)}px`;
        const startX = () => root.clientWidth * 0.36 - track.scrollWidth * 0.08;
        const endX = () => root.clientWidth * 0.36 - track.scrollWidth * 0.92;
        const travel = () => Math.abs(endX() - startX());
        trigger = ScrollTrigger.create({
            trigger: content, start: 'top 12%', end: () => `+=${travel()}`,
            pin: true, pinSpacing: true, anticipatePin: 1, invalidateOnRefresh: true,
            animation: gsap.fromTo(track, { x: () => startX() }, { x: () => endX(), ease: 'none' }), scrub: 0.35,
            onUpdate: ({ progress }) => setActive(progress),
        });
    };
    const setOpen = (expanded) => {
        open.setAttribute('aria-expanded', String(expanded));
        if (expanded) {
            content.hidden = false; open.hidden = true; gsap.set(content, { opacity: 1, height: 'auto' });
            if (!motion.matches) gsap.from(content, { opacity: 0, height: 0, duration: 0.55, ease: 'power3.out', clearProps: 'height' });
            requestAnimationFrame(() => { createPin(); close.focus({ preventScroll: true }); ScrollTrigger.refresh(); });
        } else {
            destroyPin();
            const finish = () => { content.hidden = true; open.hidden = false; open.focus({ preventScroll: true }); ScrollTrigger.refresh(); };
            if (motion.matches) finish(); else gsap.to(content, { opacity: 0, height: 0, duration: 0.3, ease: 'power2.in', onComplete: finish });
        }
    };
    open.addEventListener('click', () => setOpen(true));
    close.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && open.getAttribute('aria-expanded') === 'true') setOpen(false);
    });
    motion.addEventListener('change', createPin); mobile.addEventListener('change', createPin);
    new ResizeObserver(() => { if (!content.hidden) createPin(); }).observe(root);
    layout(); setActive(0);
};
