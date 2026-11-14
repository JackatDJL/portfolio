import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

for (const section of document.querySelectorAll('[data-support-section]')) {
    const svg = section.querySelector('[data-support-connection]');
    const path = section.querySelector('[data-support-path]');
    const from = section.querySelector('[data-support-from]');
    const to = section.querySelector('[data-support-to]');
    const owner = svg.parentElement;
    const mm = gsap.matchMedia();
    mm.add({ wide: '(min-width: 48rem)', reduce: '(prefers-reduced-motion: reduce)', all: 'all' }, context => {
        let tween;
        const measure = () => {
            if (!context.conditions.wide) return;
            tween?.scrollTrigger?.kill();
            tween?.kill();
            const box = owner.getBoundingClientRect();
            const start = from.getBoundingClientRect();
            const end = to.getBoundingClientRect();
            const y1 = start.top - box.top + 18;
            const y2 = end.top - box.top + 18;
            const x2 = Math.max(0, end.left - box.left - 20);
            svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
            path.setAttribute('d', `M -18 ${y1} V ${y2 - 24} Q -18 ${y2} 6 ${y2} H ${x2}`);
            if (context.conditions.reduce) {
                path.removeAttribute('style');
                return;
            }
            const length = path.getTotalLength();
            tween = gsap.fromTo(path, { strokeDasharray: length, strokeDashoffset: length }, {
                strokeDashoffset: 0, ease: 'none', scrollTrigger: {
                    trigger: section, start: 'top 65%', end: 'bottom 65%', scrub: .2,
                },
            });
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(owner);
        return () => { observer.disconnect(); tween?.scrollTrigger?.kill(); tween?.kill(); path.removeAttribute('style'); };
    });
}
