import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
gsap.registerPlugin(ScrollTrigger);

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    if (!root) return;
    const open = root.querySelector('[data-cv-explore-open]');
    const close = root.querySelector('[data-cv-explore-close]');
    const content = root.querySelector('[data-cv-explore-content]');
    const teaser = root.querySelector('.cv-explore__teaser');
    const stage = root.querySelector('.cv-timeline__stage');
    const track = root.querySelector('[data-cv-timeline-track]');
    const milestones = [...root.querySelectorAll('[data-cv-milestone]')];
    milestones.sort((a, b) => (a.dataset.date || '').localeCompare(b.dataset.date || ''));
    milestones.forEach(item => track.append(item));
    const media = gsap.matchMedia();
    const events = new AbortController();
    let context, trigger, resizeFrame, width = 0, disposed = false, closing = false;
    const active = () => {
        const focal = stage.getBoundingClientRect().left + stage.clientWidth / 2;
        const distances = milestones.map(item => Math.abs(item.getBoundingClientRect().left + item.offsetWidth / 2 - focal));
        const nearest = distances.indexOf(Math.min(...distances));
        milestones.forEach((item, i) => item.toggleAttribute('data-active', i === nearest));
    };
    const destroy = () => { context?.revert(); context = undefined; trigger = undefined; };
    const build = () => {
        destroy();
        if (content.hidden || disposed || closing) return;
        stage.scrollLeft = 0;
        if (matchMedia('(prefers-reduced-motion: reduce), (max-width: 47.99rem), (pointer: coarse)').matches) { active(); return; }
        context = gsap.context(() => {
            gsap.set(milestones, { width: Math.min(336, stage.clientWidth * .42), flexBasis: Math.min(336, stage.clientWidth * .42) });
            const dates = milestones.map(item => Date.parse(item.dataset.date + '-01'));
            const validGaps = dates.slice(1).map((date, i) => Math.max(0, date - dates[i])).filter(Number.isFinite);
            const total = validGaps.reduce((sum, gap) => sum + gap, 0) || 1;
            milestones.forEach((item, i) => {
                const fraction = i ? Math.max(0, dates[i] - dates[i - 1]) / total : 0;
                gsap.set(item, { marginLeft: i && Number.isFinite(fraction) ? Math.min(stage.clientWidth * .06, fraction * stage.clientWidth * .2) : 0 });
            });
            const first = milestones[0], last = milestones.at(-1);
            if (!first || !last) return;
            const start = () => stage.clientWidth / 2 - (first.offsetLeft + first.offsetWidth / 2);
            const end = () => stage.clientWidth / 2 - (last.offsetLeft + last.offsetWidth / 2);
            const animation = gsap.fromTo(track, { x: start }, { x: end, ease: 'none', onUpdate: active });
            trigger = ScrollTrigger.create({ trigger: content, pin: true, start: 'top 100px', end: () => `+=${Math.max(stage.clientWidth, start() - end())}`, animation, scrub: true, invalidateOnRefresh: true });
            active();
        }, root);
        ScrollTrigger.refresh();
    };
    const setOpen = expanded => {
        if (closing || disposed) return;
        gsap.killTweensOf(content);
        open.setAttribute('aria-expanded', String(expanded));
        if (expanded) {
            teaser.hidden = true;
            content.hidden = false;
            requestAnimationFrame(() => { build(); close.focus({ preventScroll: true }); });
        } else {
            const top = root.getBoundingClientRect().top + window.scrollY;
            const wasPinned = trigger && window.scrollY >= trigger.start;
            destroy();
            closing = true;
            if (wasPinned) window.scrollTo({ top: Math.max(0, top - 120), behavior: 'instant' });
            const finish = () => {
                content.hidden = true; teaser.hidden = false; closing = false;
                gsap.set(content, { clearProps: 'height,opacity,overflow' });
                open.focus({ preventScroll: true }); ScrollTrigger.refresh();
            };
            if (matchMedia('(prefers-reduced-motion: reduce)').matches) finish();
            else gsap.to(content, { height: 0, opacity: 0, overflow: 'hidden', duration: .22, onComplete: finish });
        }
    };
    open.addEventListener('click', () => setOpen(true), { signal: events.signal });
    close.addEventListener('click', () => setOpen(false), { signal: events.signal });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !content.hidden) setOpen(false); }, { signal: events.signal });
    stage.addEventListener('scroll', active, { signal: events.signal, passive: true });
    track.addEventListener('focusin', e => {
        const item = e.target.closest('[data-cv-milestone]');
        if (item && trigger) {
            const first = milestones[0], last = milestones.at(-1);
            const progress = (item.offsetLeft - first.offsetLeft) / Math.max(1, last.offsetLeft - first.offsetLeft);
            window.scrollTo({ top: trigger.start + progress * (trigger.end - trigger.start), behavior: 'instant' });
            requestAnimationFrame(() => { stage.scrollLeft = 0; });
        }
    }, { signal: events.signal });
    const observer = new ResizeObserver(() => {
        if (width === root.clientWidth) return;
        width = root.clientWidth; cancelAnimationFrame(resizeFrame); resizeFrame = requestAnimationFrame(build);
    });
    observer.observe(root);
    window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); }, { once: true });
    media.add('(prefers-reduced-motion: reduce), (max-width: 47.99rem), (pointer: coarse)', () => { build(); return () => requestAnimationFrame(build); });
    window.addEventListener('pagehide', () => { disposed = true; observer.disconnect(); cancelAnimationFrame(resizeFrame); events.abort(); media.revert(); destroy(); }, { once: true });
};
