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
            // Keep the return beneath a neighbouring figure, including its credit.
            const media = owner.querySelector('.support-screen');
            const returnY = Math.max(y2, media ? media.getBoundingClientRect().bottom - box.top + 16 : y2);
            const radius = Math.min(24, Math.max(0, (returnY - y2) / 2));
            path.setAttribute('d', returnY > y2
                ? `M -18 ${y1} V ${returnY - 24} Q -18 ${returnY} 6 ${returnY} H ${x2 - radius} Q ${x2} ${returnY} ${x2} ${returnY - radius} V ${y2}`
                : `M -18 ${y1} V ${y2 - 24} Q -18 ${y2} 6 ${y2} H ${x2}`);
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

// Inspect real source material. Overview and explanations remain complete without JS.
let documentIndex = 0;
for (const record of window.document.querySelectorAll('[data-support-document]')) {
    const image = record.querySelector('figure img');
    const controls = record.querySelector('[data-document-controls]');
    const choices = [...record.querySelectorAll('[data-document-choice]')];
    const status = record.querySelector('[data-document-status]');
    if (!image || !controls || !choices.length) continue;
    const viewport = window.document.createElement('div');
    viewport.className = 'support-document-viewport';
    viewport.style.aspectRatio = `${image.getAttribute('width')} / ${image.getAttribute('height')}`;
    image.before(viewport);
    viewport.append(image);
    const notes = [...record.querySelectorAll('[data-document-note]')];
    const id = ++documentIndex;
    notes.forEach((note, index) => { note.id = `support-document-${id}-note-${index}`; });
    const originalAlt = image.alt;
    let selected = choices[0];
    controls.hidden = false;
    const motion = gsap.matchMedia();
    motion.add({ reduce: '(prefers-reduced-motion: reduce)', all: 'all' }, context => {
        const draw = (animate = false) => {
            const number = (key, fallback) => {
                const value = Number(selected.dataset[key]);
                return Number.isFinite(value) ? value : fallback;
            };
            const left = gsap.utils.clamp(0, 99, number('left', 0)) / 100;
            const top = gsap.utils.clamp(0, 99, number('top', 0)) / 100;
            const width = gsap.utils.clamp(.01, 1 - left, number('width', 100) / 100);
            const height = gsap.utils.clamp(.01, 1 - top, number('height', 100) / 100);
            const resolutionLimit = Math.min(8, Number(image.getAttribute('width')) / image.offsetWidth * 1.5);
            const scale = Math.min(resolutionLimit, viewport.clientWidth / (image.offsetWidth * width), viewport.clientHeight / (image.offsetHeight * height));
            if (!Number.isFinite(scale) || scale <= 0) return;
            const renderedWidth = image.offsetWidth * scale;
            const renderedHeight = image.offsetHeight * scale;
            const x = renderedWidth <= viewport.clientWidth ? (viewport.clientWidth - renderedWidth) / 2 : gsap.utils.clamp(viewport.clientWidth - renderedWidth, 0, viewport.clientWidth / 2 - (left + width / 2) * renderedWidth);
            const y = renderedHeight <= viewport.clientHeight ? (viewport.clientHeight - renderedHeight) / 2 : gsap.utils.clamp(viewport.clientHeight - renderedHeight, 0, viewport.clientHeight / 2 - (top + height / 2) * renderedHeight);
            gsap.killTweensOf(image);
            gsap.to(image, { x, y, scale, duration: animate && !context.conditions.reduce ? .55 : 0, ease: 'power2.inOut', overwrite: true });
        };
        const present = () => {
            choices.forEach(choice => choice.setAttribute('aria-pressed', String(choice === selected)));
            const note = [...record.querySelectorAll('[data-document-note]')].find(note => note.dataset.documentNote === selected.dataset.note);
            notes.forEach(item => { item.hidden = item !== note; item.classList.toggle('is-selected', item === note); });
            choices.forEach(choice => { const target = notes.find(item => item.dataset.documentNote === choice.dataset.note); if (target) choice.setAttribute('aria-controls', target.id); });
            status.textContent = selected === choices[0] ? '' : selected.dataset.description;
            image.alt = selected === choices[0] ? originalAlt : `${selected.dataset.description} ${originalAlt}`;
        };
        const select = event => {
            selected = event.currentTarget;
            present();
            // Fetch one full-resolution derivative only after deliberate inspection.
            if (selected !== choices[0]) image.sizes = '1920px';
            draw(true);
        };
        present();
        choices.forEach(choice => choice.addEventListener('click', select));
        const resize = new ResizeObserver(() => draw());
        resize.observe(viewport);
        const loaded = () => draw();
        image.addEventListener('load', loaded);
        draw();
        return () => {
            resize.disconnect();
            choices.forEach(choice => choice.removeEventListener('click', select));
            image.removeEventListener('load', loaded);
            gsap.killTweensOf(image);
        };
    });
}
