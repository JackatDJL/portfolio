import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
const hero = document.querySelector('[data-project-hero]');
const frame = hero.querySelector('.project-hero-transition__frame');
const copy = hero.querySelector('.project-hero-transition__copy');
const media = gsap.matchMedia();
let active = true;

media.add({ desktop: '(min-width: 48rem)', mobile: '(max-width: 47.99rem)', motion: '(prefers-reduced-motion: no-preference)' }, ({ conditions }) => {
    if (!conditions.motion) return;
    const compact = conditions.desktop ? .64 : .62;
    const timeline = gsap.timeline({ scrollTrigger: {
        trigger: hero, start: 'top top', end: () => `+=${frame.offsetHeight * (conditions.desktop ? .55 : .32)}`,
        scrub: .65, invalidateOnRefresh: true,
    } });
    timeline.to(hero, { paddingInline: conditions.desktop ? '6vw' : '4vw', ease: 'sine.inOut' }, 0)
        .fromTo(frame, { minHeight: () => Math.max(copy.scrollHeight, window.innerHeight * (conditions.desktop ? 1 : .92) - 80) }, { minHeight: () => Math.max(copy.scrollHeight, window.innerHeight * compact), ease: 'sine.inOut' }, 0)
        .to(copy, { scale: conditions.desktop ? .94 : .97, ease: 'sine.inOut' }, 0)
        .to(frame, { borderBottomLeftRadius: conditions.desktop ? '2.5rem' : '1.5rem', borderBottomRightRadius: conditions.desktop ? '2.5rem' : '1.5rem', ease: 'sine.inOut' }, 0);
});
const refresh = () => { if (active) ScrollTrigger.refresh(); };
const image = frame.querySelector('img');
image?.addEventListener('load', refresh);
document.fonts?.ready.then(refresh);
const pageShow = (event) => { if (event.persisted) refresh(); };
const cleanup = (event) => {
    if (event.persisted) return;
    active = false;
    media.revert();
    image?.removeEventListener('load', refresh);
    window.removeEventListener('pageshow', pageShow);
    window.removeEventListener('pagehide', cleanup);
};
window.addEventListener('pageshow', pageShow);
window.addEventListener('pagehide', cleanup);
