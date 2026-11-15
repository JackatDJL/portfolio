import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

for (const root of document.querySelectorAll('[data-politics-page]')) {
    const story = root.querySelector('[data-politics-story]');
    const svg = root.querySelector('[data-politics-thread]');
    const path = root.querySelector('[data-politics-path]');
    const chapters = [...root.querySelectorAll('[data-politics-chapter]')];
    const branches = new Map();
    const media = gsap.matchMedia();
    const measure = () => {
        const width = story.clientWidth;
        const height = story.clientHeight;
        const rail = width < 768 ? 5 : width < 1024 ? 12 : 24;
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        const bounds = story.getBoundingClientRect();
        // The rail marks chronology; branches sit in chapter gutters and frame the evidence.
        // Their geometry follows real media bounds, including the static reduced-motion state.
        let d = `M ${rail} 0`;
        for (const chapter of chapters) {
            const top = chapter.offsetTop + 14;
            const bend = width < 768 ? 8 : 14;
            d += ` V ${Math.max(0, top - 12)} Q ${rail} ${top} ${rail + bend} ${top} Q ${rail} ${top} ${rail} ${top + 12}`;
            const image = chapter.querySelector('figure img');
            if (!image) continue;
            let branch = branches.get(chapter);
            if (!branch) {
                branch = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                branch.setAttribute('data-politics-branch', '');
                svg.append(branch);
                branches.set(chapter, branch);
            }
            const imageBounds = image.getBoundingClientRect();
            const figureBounds = image.closest('figure').getBoundingClientRect();
            const bodyBounds = chapter.querySelector('.politics-chapter__body').getBoundingClientRect();
            // Pass below both columns and the attribution, never through readable content.
            const bottom = Math.max(figureBounds.bottom, bodyBounds.bottom) - bounds.top + 24;
            const edge = imageBounds.left - bounds.left - 12;
            const end = imageBounds.top - bounds.top + imageBounds.height * .72;
            const corner = Math.min(24, Math.max(4, (edge - rail) / 2));
            branch.setAttribute('d', `M ${rail} ${bottom} H ${edge - corner} Q ${edge} ${bottom} ${edge} ${bottom - corner} V ${end}`);
        }
        path.setAttribute('d', `${d} V ${height}`);
        story.classList.add('has-politics-thread');
    };
    measure();
    media.add('(prefers-reduced-motion: no-preference)', () => {
        let tween;
        const build = () => {
            tween?.scrollTrigger?.kill();
            tween?.kill();
            measure();
            const paths = [path, ...branches.values()];
            tween = gsap.fromTo(paths, { strokeDasharray: (_, line) => line.getTotalLength(), strokeDashoffset: (_, line) => line.getTotalLength() }, {
                strokeDashoffset: 0, ease: 'none', scrollTrigger: {
                    trigger: story, start: 'top 65%', end: 'bottom 65%', scrub: .2,
                },
            });
        };
        build();
        const resize = new ResizeObserver(build);
        resize.observe(story);
        return () => { resize.disconnect(); tween?.scrollTrigger?.kill(); tween?.kill(); [path, ...branches.values()].forEach(line => line.removeAttribute('style')); measure(); };
    });
    const observer = new ResizeObserver(measure);
    observer.observe(story);
    // Native restoration can change the scroll position after assets have loaded.
    window.addEventListener('pageshow', () => { measure(); ScrollTrigger.refresh(); });
}
