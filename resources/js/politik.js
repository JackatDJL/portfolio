import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

for (const root of document.querySelectorAll('[data-politics-page]')) {
    const story = root.querySelector('[data-politics-story]');
    const svg = root.querySelector('[data-politics-thread]');
    const path = root.querySelector('[data-politics-path]');
    const chapters = [...root.querySelectorAll('[data-politics-chapter]')];
    const media = gsap.matchMedia();
    const measure = () => {
        const width = story.clientWidth;
        const height = story.clientHeight;
        const rail = width < 768 ? 5 : width < 1024 ? 12 : 24;
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        // Curves mark actual chapter boundaries. Text and images never move or disappear.
        let d = `M ${rail} 0`;
        for (const chapter of chapters) {
            const top = chapter.offsetTop + 14;
            const bend = width < 768 ? 8 : 14;
            d += ` V ${Math.max(0, top - 12)} Q ${rail} ${top} ${rail + bend} ${top} Q ${rail} ${top} ${rail} ${top + 12}`;
        }
        path.setAttribute('d', `${d} V ${height}`);
        story.classList.add('has-politics-thread');
    };
    measure();
    media.add('(prefers-reduced-motion: no-preference)', () => {
        let tween;
        const build = () => {
            measure();
            tween?.scrollTrigger?.kill();
            tween?.kill();
            const length = path.getTotalLength();
            tween = gsap.fromTo(path, { strokeDasharray: length, strokeDashoffset: length }, {
                strokeDashoffset: 0, ease: 'none', scrollTrigger: {
                    trigger: story, start: 'top 65%', end: 'bottom 65%', scrub: .2,
                },
            });
        };
        build();
        const resize = new ResizeObserver(build);
        resize.observe(story);
        return () => { resize.disconnect(); tween?.scrollTrigger?.kill(); tween?.kill(); path.removeAttribute('style'); measure(); };
    });
    const observer = new ResizeObserver(measure);
    observer.observe(story);
    // Native restoration can change the scroll position after assets have loaded.
    window.addEventListener('pageshow', () => { measure(); ScrollTrigger.refresh(); });
}
