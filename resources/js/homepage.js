import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// The two underline subpaths retain the same point count as the two halves of
// the thread. Interpolating these coordinates avoids a topology change at handoff.
const lerp = (a, b, t) => a + (b - a) * t;
const clamp = gsap.utils.clamp(0, 1);
const bump = (y, center, radius, depth) => {
    const distance = Math.abs(y - center) / radius;
    return distance < 1 ? (1 + Math.cos(distance * Math.PI)) * depth / 2 : 0;
};

for (const root of document.querySelectorAll('[data-homepage]')) {
    const opening = root.querySelector('[data-home-opening]');
    const slot = root.querySelector('[data-home-name-slot]');
    const name = root.querySelector('[data-home-name]');
    const words = [...name.children];
    const dock = document.querySelector('[data-home-dock]');
    const svg = root.querySelector('[data-home-thread]');
    const path = root.querySelector('[data-home-thread-path]');
    const gallery = [...root.querySelectorAll('[data-home-gallery] .home-photo')];
    const stage = root.querySelector('[data-home-project-stage]');
    const projects = [...root.querySelectorAll('[data-home-project]')];
    const headings = [...root.querySelectorAll('[data-home-heading]')];
    const media = root.querySelector('[data-home-person-media]');
    const intro = root.querySelector('[data-home-intro]');
    const mm = gsap.matchMedia();

    mm.add({ all: 'all', desktop: '(min-width: 64rem) and (min-height: 42rem) and (hover: hover) and (pointer: fine)', reduce: '(prefers-reduced-motion: reduce)' }, (context) => {
        const { desktop, reduce } = context.conditions;
        const full = desktop && !reduce;
        const motion = { hero: 0, project: 0, majorY: innerHeight, smallY: innerHeight, smallDepth: 0 };
        let size = {}, openingTrigger, projectTrigger, frame = 0, lastSection = -1, active = 0;
        let dirty = true;
        root.classList.toggle('is-home-enhanced', full);

        // Keep exactly one H1. Its slot preserves document geometry while the
        // original element lives outside both pin containers.
        const measure = () => {
            name.classList.remove('is-travelling');
            name.style.cssText = '';
            words.forEach(word => { word.style.cssText = ''; });
            slot.append(name);
            const rect = name.getBoundingClientRect();
            const wordRects = words.map(word => word.getBoundingClientRect());
            const destination = dock.getBoundingClientRect();
            size = {
                width: rect.width, height: rect.height,
                font: parseFloat(getComputedStyle(name).fontSize),
                firstWidth: wordRects[0].width,
                line: wordRects[1].top - wordRects[0].top,
                wordWidths: wordRects.map(word => word.width),
                dockX: destination.left, dockY: destination.top,
                rail: parseFloat(getComputedStyle(root).getPropertyValue('--home-rail')) || 24,
            };
            // Resolve clamp() through a real box rather than parsing its CSS text.
            size.rail = headings[0].getBoundingClientRect().left - (innerWidth < 768 ? 23 : 38);
            slot.style.height = `${rect.height}px`;
            root.prepend(name);
            name.classList.add('is-travelling');
            name.style.width = `${rect.width}px`;
            svg.setAttribute('viewBox', `0 0 ${innerWidth} ${innerHeight}`);
            dirty = true;
        };
        const setActive = (index) => {
            active = index;
            projects.forEach((project, i) => {
                project.inert = full && i !== index;
                if (full && i !== index) project.setAttribute('aria-hidden', 'true');
                else project.removeAttribute('aria-hidden');
                project.classList.toggle('is-active', i === index);
            });
        };
        measure();

        if (full) {
            const openingTimeline = gsap.timeline({ onUpdate: () => { dirty = true; }, defaults: { ease: 'none' }, scrollTrigger: {
                trigger: opening, start: 'top top', end: () => `+=${innerHeight * 1.65 + Math.max(0, gallery.length - 2) * innerWidth * .22}`,
                pin: true, scrub: .35, invalidateOnRefresh: true,
                onUpdate: () => { dirty = true; },
            } });
            openingTrigger = openingTimeline.scrollTrigger;
            openingTimeline.to(motion, { hero: 1, duration: .55 }, 0)
                .to(intro, { y: -35, opacity: 0, duration: .3 }, 0)
                .to(media, { x: () => -innerWidth * .48, y: () => -innerHeight * .16, scale: .42, duration: .55 }, 0)
                .to(media, { x: () => -innerWidth * .95, duration: .45 }, .55);
            const photoStride = () => Math.min(560, Math.max(288, innerWidth * .34)) + 32;
            const streamDistance = () => innerWidth * 1.15 + Math.floor(Math.max(0, gallery.length - 1) / 2) * photoStride();
            gallery.forEach((photo, i) => {
                const lane = i % 2;
                const order = Math.floor(i / 2);
                const startX = () => lane ? -innerWidth * .7 - order * photoStride() : innerWidth * 1.05 + order * photoStride();
                // Every image in a lane shares the same travel distance, so a
                // larger editorial gallery never converges into overlapping images.
                openingTimeline.fromTo(photo, { x: startX }, {
                    x: () => startX() + (lane ? 1 : -1) * streamDistance(),
                    duration: 1, ease: 'none',
                }, 0);
            });
            if (projects.length > 1) {
                projectTrigger = ScrollTrigger.create({
                    trigger: stage, start: 'top top', end: () => `+=${(projects.length - 1) * innerHeight * .8}`,
                    pin: true, scrub: true, invalidateOnRefresh: true,
                    snap: { snapTo: 1 / (projects.length - 1), duration: { min: .12, max: .35 }, delay: .16, inertia: false, directional: false },
                    onUpdate: self => { motion.project = self.progress * (projects.length - 1); dirty = true; },
                });
            }
        }
        setActive(0);
        const update = () => { dirty = true; };
        const draw = () => {
            frame = requestAnimationFrame(draw);
            if (!dirty) return;
            dirty = false;
            const slotRect = slot.getBoundingClientRect();
            const progress = full ? motion.hero : clamp(-slotRect.top / Math.max(180, size.height));
            // Reduced motion retains the original full name until it leaves the
            // viewport, then presents the same element in its stable dock state.
            const travel = reduce ? (slotRect.bottom < 40 ? 1 : 0) : progress;
            const scale = lerp(1, 24 / size.font, travel);
            gsap.set(name, { x: lerp(slotRect.left, size.dockX, travel), y: lerp(slotRect.top, size.dockY, travel), scale });
            gsap.set(words[1], { x: (size.firstWidth + size.font * .22) * clamp(travel / .5), y: -size.line * clamp((travel - .45) / .55) });
            for (const word of words) word.style.backgroundSize = document.documentElement.dataset.theme !== 'dark' ? `${travel * 100}% 100%` : '0% 100%';

            const selected = Math.round(motion.project);
            if (!full) {
                let nearest = Infinity;
                projects.forEach((project, index) => {
                    const y = project.querySelector('h3').getBoundingClientRect().top;
                    const distance = Math.abs(y - innerHeight * .45);
                    if (distance < nearest) { nearest = distance; active = index; }
                });
            }
            if (full) {
                if (selected !== active) setActive(selected);
                projects.forEach((project, i) => {
                    const distance = i - motion.project;
                    gsap.set(project, { yPercent: distance * 115, visibility: Math.abs(distance) > 1.05 ? 'hidden' : 'visible' });
                    const artifact = project.querySelector('.home-project__artifact');
                    if (artifact) gsap.set(artifact, { scale: 1 - Math.min(1, Math.abs(distance)) * .08 });
                });
            }
            const positions = headings.map(heading => heading.getBoundingClientRect());
            let section = positions.findIndex((rect, i) => rect.top < innerHeight * .65 && (i === positions.length - 1 || positions[i + 1].top >= innerHeight * .65));
            if (section < 0) section = 0;
            const targetY = positions[section].top + positions[section].height / 2;
            if (section !== lastSection && !reduce) {
                gsap.to(motion, { majorY: targetY, duration: .3, overwrite: 'auto', onUpdate: update });
                lastSection = section;
            } else if (!gsap.isTweening(motion)) motion.majorY = targetY;
            const projectTitle = projects[active]?.querySelector('h3').getBoundingClientRect();
            const showSmall = section === 0 && projectTitle && projectTitle.top > 60 && projectTitle.top < innerHeight;
            const smallTarget = showSmall ? projectTitle.top + projectTitle.height / 2 : motion.smallY;
            // A damped measured target lets the little bump travel with the
            // incoming title, including when reversing between project states.
            motion.smallY = reduce ? smallTarget : lerp(motion.smallY, smallTarget, .22);
            motion.smallDepth = reduce ? (showSmall ? 12 : 0) : lerp(motion.smallDepth, showSmall ? 12 : 0, .22);
            if (Math.abs(motion.smallY - smallTarget) > .2 || Math.abs(motion.smallDepth - (showSmall ? 12 : 0)) > .1) dirty = true;
            const threadProgress = reduce ? travel : clamp(travel * 1.25);
            const footerTop = document.querySelector('.site-footer')?.getBoundingClientRect().top ?? innerHeight;
            const bottom = Math.min(innerHeight + 5, footerTop);
            const commands = [];
            for (let half = 0; half < 2; half++) {
                for (let i = 0; i <= 64; i++) {
                    const t = i / 64;
                    const y = 65 + ((bottom - 65) / 2) * (half + t);
                    const majorDepth = innerWidth < 768 ? 9 : 23;
                    const threadX = size.rail + Math.sin(y / 66) * (innerWidth < 768 ? 2 : 4)
                        + bump(y, motion.majorY, 44, majorDepth) + bump(y, motion.smallY, 30, motion.smallDepth);
                    const underX = slotRect.left + size.wordWidths[half] * t;
                    const underY = slotRect.top + size.line * half + size.font * .94 + Math.sin(t * Math.PI * 5) * 3;
                    const x = lerp(underX, threadX, threadProgress);
                    const finalY = lerp(underY, y, threadProgress);
                    commands.push(`${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${finalY.toFixed(2)}`);
                }
            }
            path.setAttribute('d', commands.join(' '));
            svg.style.visibility = bottom < 65 ? 'hidden' : 'visible';
        };
        draw();
        window.addEventListener('scroll', update, { passive: true });
        ScrollTrigger.addEventListener('refreshInit', measure);
        const resize = () => { measure(); ScrollTrigger.refresh(); };
        window.addEventListener('resize', resize);
        const themeObserver = new MutationObserver(update);
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        const loaded = () => { measure(); ScrollTrigger.refresh(); };
        window.addEventListener('load', loaded, { once: true });
        document.fonts.ready.then(() => { if (frame) loaded(); });
        return () => {
            cancelAnimationFrame(frame); frame = 0;
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', resize);
            window.removeEventListener('load', loaded);
            ScrollTrigger.removeEventListener('refreshInit', measure);
            themeObserver.disconnect();
            gsap.killTweensOf(motion);
            openingTrigger?.kill(); projectTrigger?.kill();
            name.classList.remove('is-travelling');
            name.style.cssText = ''; words.forEach(word => { word.style.cssText = ''; });
            slot.style.height = ''; slot.append(name);
            root.classList.remove('is-home-enhanced');
            projects.forEach(project => { project.inert = false; project.removeAttribute('aria-hidden'); project.style.cssText = ''; });
        };
    });
    window.addEventListener('pagehide', event => {
        if (!event.persisted) mm.revert();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) ScrollTrigger.refresh();
    });
}
