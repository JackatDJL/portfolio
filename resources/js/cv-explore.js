import { gsap } from 'gsap';

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    const open = root?.querySelector('[data-cv-explore-open]');
    const close = root?.querySelector('[data-cv-explore-close]');
    const content = root?.querySelector('[data-cv-explore-content]');
    if (!(root instanceof HTMLElement) || !(open instanceof HTMLButtonElement) || !(close instanceof HTMLButtonElement) || !(content instanceof HTMLElement)) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const setOpen = (expanded) => {
        open.setAttribute('aria-expanded', String(expanded));
        if (expanded) {
            content.hidden = false;
            if (reduced) content.focus({ preventScroll: true });
            else gsap.fromTo(content, { opacity: 0, y: 24, filter: 'blur(10px)' }, { opacity: 1, y: 0, filter: 'blur(0px)', duration: .65, ease: 'power3.out', clearProps: 'filter' });
            open.hidden = true;
            content.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
            close.focus({ preventScroll: true });
        } else {
            const finish = () => { content.hidden = true; open.hidden = false; open.focus({ preventScroll: true }); };
            if (reduced) finish();
            else gsap.to(content, { opacity: 0, y: 16, duration: .25, ease: 'power2.in', onComplete: finish });
        }
    };
    open.addEventListener('click', () => setOpen(true));
    close.addEventListener('click', () => setOpen(false));
    content.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });

    if (!reduced) {
        open.addEventListener('pointermove', (event) => {
            if (event.pointerType !== 'mouse') return;
            const box = open.getBoundingClientRect();
            gsap.to(open, { x: (event.clientX - box.left - box.width / 2) * .08, y: (event.clientY - box.top - box.height / 2) * .08, duration: .35, ease: 'power2.out' });
        });
        open.addEventListener('pointerleave', () => gsap.to(open, { x: 0, y: 0, duration: .45, ease: 'elastic.out(1, .55)' }));
    }
};
