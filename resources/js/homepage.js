import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { homepageThread } from './homepage-thread';
import { homepageStream } from './homepage-stream';

gsap.registerPlugin(ScrollTrigger);
const lerp = (a, b, t) => a + (b - a) * t;

for (const root of document.querySelectorAll('[data-homepage]')) {
    const cinema = root.querySelector('[data-home-cinema]');
    const slot = root.querySelector('[data-home-name-slot]');
    const name = root.querySelector('[data-home-name]');
    const words = [...name.children];
    const dock = document.querySelector('[data-home-dock]');
    const projects = [...root.querySelectorAll('[data-home-project]')];
    const headings = [...root.querySelectorAll('[data-home-heading]')];
    const media = root.querySelector('[data-home-person-media]');
    const gallery = root.querySelector('[data-home-gallery]');
    const intro = root.querySelector('[data-home-intro]');
    const mm = gsap.matchMedia();
    // Dynamic import can finish after native restoration has been clamped to
    // the shorter, unenhanced document. Restore once after the pin exists.
    let restoredScroll = null;
    try {
        if (performance.getEntriesByType('navigation')[0]?.type === 'reload') {
            const saved = sessionStorage.getItem('home-scroll');
            if (saved !== null) restoredScroll = Number(saved);
        }
    } catch {}


    mm.add({ desktop: '(min-width: 64rem) and (min-height: 42rem) and (hover: hover) and (pointer: fine)', reduce: '(prefers-reduced-motion: reduce)', all: 'all' }, context => {
        const full = context.conditions.desktop && !context.conditions.reduce;
        const state = { travel: 0, peel: 0, establish: full ? 0 : 1 };
        const labels = { hero: 0, thread: .4, 'gallery-in': 1.2, gallery: 2.6 };
        labels['project-1'] = 7.5; // Existing photo-stream endpoint.
        let timeline, size, stickyHeading, headingShift = 0, alive = true, refreshTimer;
        root.classList.toggle('is-home-enhanced', full);
        const thread = homepageThread({ root, svg: root.querySelector('[data-home-thread]'), path: root.querySelector('[data-home-thread-path]'), headings, projects, full, reduce: context.conditions.reduce });
        const stream = full ? homepageStream(gallery, [...media.querySelectorAll('.home-photo'), ...gallery.querySelectorAll('.home-photo')], gsap) : null;

        const measureName = () => {
            // A pin still has its old inline width during refreshInit. Measure
            // against the new viewport before resolving centred containers.
            const previousWidth = cinema.style.width;
            if (full) cinema.style.width = `${root.clientWidth}px`;
            name.classList.remove('is-travelling');
            name.style.cssText = '';
            words.forEach(word => { word.style.cssText = ''; });
            slot.style.height = '';
            slot.append(name);
            const rect = name.getBoundingClientRect();
            const wordRects = words.map(word => word.getBoundingClientRect());
            const destination = dock.getBoundingClientRect();
            const font = parseFloat(getComputedStyle(name).fontSize);
            const savedMedia = media.style.cssText;
            media.style.transform = 'none';
            const mediaRect = media.getBoundingClientRect();
            const cinemaRect = cinema.getBoundingClientRect();
            media.style.cssText = savedMedia;
            size = { originX: rect.left, originY: rect.top - cinemaRect.top + root.getBoundingClientRect().top + scrollY, mediaX: mediaRect.left, mediaY: mediaRect.top - cinemaRect.top, mediaWidth: mediaRect.width, mediaScale: innerHeight * .32 / mediaRect.height, width: rect.width, height: rect.height, font, scale: 24 / font,
                wordWidths: wordRects.map(r => r.width), line: wordRects[1].top - wordRects[0].top,
                dockX: destination.left, dockY: destination.top,
                rail: headings[0].getBoundingClientRect().left - (innerWidth < 768 ? 23 : 38) };
            if (full) {
                slot.style.height = `${rect.height}px`;
                root.prepend(name);
                name.classList.add('is-travelling');
                name.style.width = `${rect.width}px`;
            }
            cinema.style.width = previousWidth;
        };
        const render = () => {
            if (!size || !alive) return;
            let x = size.originX, y = size.originY - scrollY, scale = 1;
            if (full) {
                const trigger = timeline?.scrollTrigger;
                const pinOffset = trigger ? gsap.utils.clamp(0, trigger.end - trigger.start, scrollY - trigger.start) : 0;
                x = lerp(size.originX, size.dockX, state.travel);
                y = lerp(size.originY - scrollY + pinOffset, size.dockY, state.travel);
                scale = lerp(1, size.scale, state.travel);
                gsap.set(name, { x: 0, y: 0, scale: 1, height: size.height * scale });
                gsap.set(words[0], { x, y, scale });
                // Each word interpolates directly between its measured start and inline destination.
                const wordX = (size.wordWidths[0] + size.font * .22) * size.scale * state.travel / scale;
                const wordY = size.line * (1 - state.travel) / scale - size.line;
                gsap.set(words[1], { x: x + wordX * scale, y: y + size.line * (1 - state.travel) - size.line, scale });
                state.wordX = wordX; state.wordY = wordY;
                words.forEach(word => { word.style.backgroundSize = document.documentElement.dataset.theme === 'dark' ? '0% 100%' : `${state.travel * 100}% 100%`; });
            }
            if (stickyHeading) {
                headingShift = gsap.utils.clamp(0, stickyHeading.distance, scrollY - stickyHeading.start);
                headings[0].style.translate = `0 ${headingShift}px`;
                headings[0].classList.toggle('is-sticky', headingShift > 0);
            }
            thread.draw({ ...state, x, y, scale, headingShift });
        };
        measureName();
        if (full) {
            gsap.set(media, { transformOrigin: '0 0' });
            timeline = gsap.timeline({ defaults: { ease: 'none' }, onUpdate: render });
            Object.entries(labels).forEach(([label, time]) => timeline.addLabel(label, time));
            timeline.fromTo(state, { travel: 0 }, { travel: 1, duration: 1.25, ease: 'power2.inOut' }, 'thread')
                .fromTo(state, { peel: 0 }, { peel: 1, duration: 1.1, ease: 'power2.inOut' }, 'thread+=.65')
                .fromTo(state, { establish: 0 }, { establish: 1, duration: .8 }, 'thread+=1.35')
                .fromTo(intro, { autoAlpha: 1, y: 0 }, { autoAlpha: 0, y: -24, duration: .6 }, 'thread')
                .fromTo(media, { scale: 1, x: 0, y: 0 }, { scale: () => size.mediaScale, x: () => innerWidth * .26 - size.mediaX, y: () => innerHeight * .15 - size.mediaY, duration: 1.3, ease: 'power2.inOut' }, 'thread')
                .fromTo(media, { x: () => innerWidth * .26 - size.mediaX }, { immediateRender: false, x: () => -size.mediaX - size.mediaWidth * size.mediaScale - 40, duration: 3.1 }, 'thread+=1.3');
            stream.build(timeline, labels);
            ScrollTrigger.create({ id: 'home-master', animation: timeline, trigger: cinema,
                start: 'top top', end: () => `+=${timeline.duration() * Math.max(720, innerHeight * .85)}`,
                pin: true, scrub: .25, invalidateOnRefresh: true, onUpdate: render,
            });
        }
        if (!context.conditions.reduce) {
            projects.forEach((project, index) => {
                const copy = project.querySelector('.home-project__copy');
                const artifact = project.querySelector('.home-project__artifact');
                const parts = artifact ? [copy, artifact] : [copy];
                // Distance-based entry and exit leave the entire central reading
                // range stable, including projects taller than a mobile viewport.
                const motion = gsap.timeline({ scrollTrigger: {
                    id: `home-project-${index + 1}`, trigger: project,
                    start: 'top bottom', end: 'bottom top', scrub: true,
                    invalidateOnRefresh: true,
                } });
                const phase = () => Math.min(.2, innerHeight * .2 / (innerHeight + project.offsetHeight));
                motion.fromTo(parts, { x: i => i ? 20 : -20, y: 24, opacity: .65 },
                    { x: 0, y: 0, opacity: 1, duration: phase(), ease: 'power2.out' }, 0)
                    .to(parts, { x: i => i ? 16 : -16, y: -20, opacity: .7,
                        duration: phase(), ease: 'power2.in' }, 1 - phase());
            });
        }

        const measure = () => { measureName(); };
        const refreshed = () => {
            size.rail = headings[0].getBoundingClientRect().left - (innerWidth < 768 ? 23 : 38);
            const heading = headings[0];
            heading.style.setProperty('--project-heading-left', `${heading.getBoundingClientRect().left}px`);
            const penultimate = projects.at(-2);
            if (penultimate) {
                const top = heading.getBoundingClientRect().top + scrollY - headingShift;
                const bottom = penultimate.getBoundingClientRect().bottom + scrollY;
                // Hold only the heading; release before the final Project arrives.
                stickyHeading = { start: top - 80, distance: Math.max(0, bottom - top - heading.offsetHeight) };
            }
            thread.measure(size);
            render();
        };
        ScrollTrigger.addEventListener('refreshInit', measure);
        ScrollTrigger.addEventListener('refresh', refreshed);
        const refresh = () => {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => {
                if (!alive) return;
                ScrollTrigger.refresh();
                if (restoredScroll !== null && Number.isFinite(restoredScroll)) {
                    window.scrollTo(0, restoredScroll);
                    restoredScroll = null;
                    ScrollTrigger.update();
                }
            }, 120);
        };
        const scroll = () => render();
        window.addEventListener('scroll', scroll, { passive: true });
        window.addEventListener('resize', refresh);
        window.addEventListener('load', refresh);
        const images = [...root.querySelectorAll('img')];
        images.forEach(img => img.addEventListener('load', refresh));
        const observer = new MutationObserver(render);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        document.fonts.ready.then(() => { if (alive) refresh(); });
        if (!full) refreshed();
        refresh();
        return () => {
            alive = false;
            clearTimeout(refreshTimer);
            window.removeEventListener('scroll', scroll);
            window.removeEventListener('resize', refresh);
            window.removeEventListener('load', refresh);
            images.forEach(img => img.removeEventListener('load', refresh));
            observer.disconnect();
            ScrollTrigger.removeEventListener('refreshInit', measure);
            ScrollTrigger.removeEventListener('refresh', refreshed);
            timeline?.scrollTrigger?.kill();
            timeline?.kill();
            stream?.destroy(); thread.destroy();
            name.classList.remove('is-travelling');
            name.style.cssText = ''; words.forEach(word => { word.style.cssText = ''; });
            slot.style.height = ''; slot.append(name);
            headings[0].style.translate = '';
            headings[0].classList.remove('is-sticky');
            headings[0].style.removeProperty('--project-heading-left');
            root.classList.remove('is-home-enhanced');
        };
    });
    window.addEventListener('pagehide', () => {
        try { sessionStorage.setItem('home-scroll', String(scrollY)); } catch {}
    });
    window.addEventListener('pageshow', event => { if (event.persisted) ScrollTrigger.refresh(); });
}
