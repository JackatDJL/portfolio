import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
const hero = document.querySelector('[data-project-hero]');
const frame = hero.querySelector('.project-hero-prototype__frame');
const title = hero.querySelector('h1');
const media = gsap.matchMedia();
let active = true;

media.add({ desktop: '(min-width: 48rem)', mobile: '(max-width: 47.99rem)', motion: '(prefers-reduced-motion: no-preference)' }, ({ conditions }) => {
    if (!conditions.motion) return;
    const compact = conditions.desktop ? .64 : .62;
    const copy = hero.querySelector('.project-hero-prototype__copy');
    const timeline = gsap.timeline({ scrollTrigger: {
        trigger: hero, start: 'top top', end: () => `+=${frame.offsetHeight * (conditions.desktop ? .55 : .32)}`,
        scrub: true, invalidateOnRefresh: true,
    } });
    timeline.to(hero, { paddingInline: conditions.desktop ? '6vw' : '4vw', ease: 'none' }, 0)
        .fromTo(frame, { minHeight: () => Math.max(copy.scrollHeight, window.innerHeight * (conditions.desktop ? 1 : .92) - 80) }, { minHeight: () => Math.max(copy.scrollHeight, window.innerHeight * compact), ease: 'none' }, 0)
        .to(title, { scale: conditions.desktop ? .76 : .9, x: conditions.desktop ? '2vw' : 0, ease: 'none' }, 0);
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
