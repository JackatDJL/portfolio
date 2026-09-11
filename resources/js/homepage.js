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
    const projectLayer = root.querySelector('[data-home-projects]');
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
        const state = { travel: 0, inline: 0, peel: 0, establish: full ? 0 : 1 };
        const labels = { hero: 0, thread: .4, 'gallery-in': 1.2, gallery: 2.6, 'project-handoff': 6.0 };
        projects.forEach((_, i) => { labels[`project-${i + 1}`] = 7.5 + i * 1.6; });
        labels.release = 7.5 + Math.max(0, projects.length - 1) * 1.6 + .9;
        let timeline, size, alive = true, refreshTimer;
        let active = -2;
        root.classList.toggle('is-home-enhanced', full);
        const thread = homepageThread({ root, svg: root.querySelector('[data-home-thread]'), path: root.querySelector('[data-home-thread-path]'), headings, projects, full });
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
        const ownership = () => {
            const time = timeline?.time() ?? 0;
            let selected = -1;
            if (full && time >= labels['project-handoff'] + .85) {
                selected = projects.length - 1;
                for (let i = 1; i < projects.length; i++) {
                    if (time < labels[`project-${i + 1}`] - .2) { selected = i - 1; break; }
                }
            }
            if (selected !== active) {
                active = selected;
                projects.forEach((project, i) => {
                    const usable = !full || i === active;
                    project.inert = !usable;
                    if (usable) project.removeAttribute('aria-hidden');
                    else project.setAttribute('aria-hidden', 'true');
                    project.style.pointerEvents = usable ? '' : 'none';
                    project.classList.toggle('is-active', i === active);
                });
            }
            projectLayer.style.pointerEvents = !full || active >= 0 ? '' : 'none';
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
                gsap.set(name, { x, y, scale });
                gsap.set(words[1], { x: (size.wordWidths[0] + size.font * .22) * state.inline, y: -size.line * state.inline });
                words.forEach(word => { word.style.backgroundSize = document.documentElement.dataset.theme === 'dark' ? '0% 100%' : `${state.travel * 100}% 100%`; });
            }
            ownership();
            const end = timeline?.scrollTrigger?.end;
            thread.draw({ ...state, x, y, scale, active, release: Number.isFinite(end) ? Math.min(0, end - scrollY) : 0 });
        };
        measureName();
        if (full) {
            gsap.set(projectLayer, { autoAlpha: 0 });
            gsap.set(media, { transformOrigin: '0 0' });
            gsap.set(projects, { autoAlpha: 0, x: -20, y: 26 });
            timeline = gsap.timeline({ defaults: { ease: 'none' }, onUpdate: render });
            Object.entries(labels).forEach(([label, time]) => timeline.addLabel(label, time));
            timeline.fromTo(state, { travel: 0 }, { travel: 1, duration: 1.25, ease: 'power2.inOut' }, 'thread')
                .fromTo(state, { peel: 0 }, { peel: 1, duration: 1.1, ease: 'power2.inOut' }, 'thread+=.65')
                .fromTo(state, { inline: 0 }, { inline: 1, duration: .45, ease: 'power2.inOut' }, 'thread+=1.5')
                .fromTo(state, { establish: 0 }, { establish: 1, duration: .8 }, 'thread+=1.35')
                .fromTo(intro, { autoAlpha: 1, y: 0 }, { autoAlpha: 0, y: -24, duration: .6 }, 'thread')
                .fromTo(media, { scale: 1, x: 0, y: 0 }, { scale: () => size.mediaScale, x: () => innerWidth * .26 - size.mediaX, y: () => innerHeight * .15 - size.mediaY, duration: 1.3, ease: 'power2.inOut' }, 'thread')
                .fromTo(media, { x: () => innerWidth * .26 - size.mediaX }, { immediateRender: false, x: () => -size.mediaX - size.mediaWidth * size.mediaScale - 40, duration: 3.1 }, 'thread+=1.3');
            stream.build(timeline, labels);
            timeline.fromTo(projectLayer, { autoAlpha: 0, y: 36 }, { autoAlpha: 1, y: 0, duration: .7, ease: 'power2.out' }, 'project-handoff');
            projects.forEach((project, i) => {
                const at = labels[`project-${i + 1}`];
                timeline.fromTo(project, { autoAlpha: 0, x: -20, y: 26 }, { immediateRender: false, autoAlpha: 1, x: 0, y: 0, duration: .38, ease: 'power3.out' }, i === 0 ? labels['project-handoff'] + .85 : at - .38);
                if (i < projects.length - 1) timeline.fromTo(project, { autoAlpha: 1, x: 0, y: 0 }, { immediateRender: false, autoAlpha: 0, x: -16, y: -22, duration: .28, ease: 'power2.in' }, labels[`project-${i + 2}`] - .48);
            });
            timeline.to({}, { duration: .01 }, labels.release);
            const points = projects.map((_, i) => labels[`project-${i + 1}`] / timeline.duration());
            ScrollTrigger.create({ id: 'home-master', animation: timeline, trigger: cinema,
                start: 'top top', end: () => `+=${timeline.duration() * Math.max(720, innerHeight * .85)}`,
                pin: true, scrub: .25, invalidateOnRefresh: true,
                snap: projects.length > 1 ? {
                    snapTo: value => {
                        const time = value * timeline.duration();
                        if (time < labels['project-1'] || time > labels[`project-${projects.length}`] + .25) return value;
                        const nearest = gsap.utils.snap(points, value);
                        // Resolve only while approaching a nearby state. A slow
                        // wheel notch away from a settled project must not get
                        // repeatedly pulled back to the same project.
                        const approaching = (nearest - value) * timeline.scrollTrigger.direction >= 0;
                        return approaching && Math.abs(nearest - value) * timeline.duration() < .6 ? nearest : value;
                    }, duration: { min: .12, max: .28 }, delay: .18, inertia: false, directional: false,
                } : false,
                onUpdate: render,
            });
        }
        const measure = () => { measureName(); };
        const refreshed = () => {
            size.rail = headings[0].getBoundingClientRect().left - (innerWidth < 768 ? 23 : 38);
            thread.measure(size, timeline?.scrollTrigger);
            render();
        };
        ScrollTrigger.addEventListener('refreshInit', measure);
        ScrollTrigger.addEventListener('refresh', refreshed);
        const refresh = () => {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => {
                if (!alive) return;
                if (full) ScrollTrigger.refresh();
                else { measureName(); refreshed(); }
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
            root.classList.remove('is-home-enhanced');
            projectLayer.style.pointerEvents = '';
            projects.forEach(project => { project.inert = false; project.removeAttribute('aria-hidden'); project.style.pointerEvents = ''; });
        };
    });
    window.addEventListener('pagehide', () => {
        try { sessionStorage.setItem('home-scroll', String(scrollY)); } catch {}
    });
    window.addEventListener('pageshow', event => { if (event.persisted) ScrollTrigger.refresh(); });
}
