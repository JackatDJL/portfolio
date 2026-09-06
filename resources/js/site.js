import { initThemeSwitcher } from './theme.js';
import { gsap } from 'gsap';

initThemeSwitcher();

const slugifyHeading = (value) => {
    const base = value
        .toLocaleLowerCase('de-DE')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^\p{Letter}\p{Number}]+/gu, '-')
        .replace(/^-+|-+$/g, '');
    return base || 'abschnitt';
};

const initArticleToc = () => {
    const article = document.querySelector('.prose-site');
    const tocTargets = [...document.querySelectorAll('[data-article-toc]')];
    if (!article || tocTargets.length === 0) return;

    const allHeadings = [...article.querySelectorAll('h2, h3')]
        .filter((heading) => !heading.closest('[data-toc-exclude]'));
    const h2Count = allHeadings.filter((heading) => heading.tagName === 'H2').length;
    const isLongEnough = article.textContent.trim().length >= 1200;
    if (h2Count < 2 || (allHeadings.length < 3 && !isLongEnough)) return;

    const usedIds = new Set([...document.querySelectorAll('[id]')].map(({ id }) => id));
    const headings = allHeadings.filter((heading) => heading.tagName === 'H2' || h2Count >= 2);
    for (const heading of headings) {
        if (!heading.id) {
            const base = slugifyHeading(heading.textContent.trim());
            let id = base;
            let index = 2;
            while (usedIds.has(id)) id = `${base}-${index++}`;
            heading.id = id;
            usedIds.add(id);
        }
    }

    const makeLinks = (list) => {
        for (const heading of headings) {
            const item = document.createElement('li');
            if (heading.tagName === 'H3') item.className = 'article-toc__subitem';
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent.trim();
            item.append(link);
            list.append(item);
        }
    };

    for (const toc of tocTargets) {
        makeLinks(toc.querySelector('.article-toc__list'));
        toc.hidden = false;
    }

    const links = [...document.querySelectorAll('.article-toc a')];
    const setCurrent = (id) => {
        for (const link of links) {
            if (link.hash === `#${id}`) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
    };
    setCurrent(headings[0].id);

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (visible) setCurrent(visible.target.id);
    }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });
    headings.forEach((heading) => observer.observe(heading));

    if (window.location.hash) {
        let hashId;
        try { hashId = decodeURIComponent(window.location.hash.slice(1)); } catch { hashId = window.location.hash.slice(1); }
        const target = document.getElementById(hashId);
        if (target) window.requestAnimationFrame(() => target.scrollIntoView());
    }
};

initArticleToc();

const initContextRailLine = () => {
    for (const rail of document.querySelectorAll('[data-context-rail]')) {
        const svg = rail.querySelector('.article-context-rail__line');
        const line = rail.querySelector('[data-context-line]');
        if (!(svg instanceof SVGElement) || !(line instanceof SVGPathElement)) continue;

        const state = { y: 0, depth: 0, height: 1 };
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const render = () => {
            const start = Math.max(0, state.y - 15);
            const end = Math.min(state.height, state.y + 15);
            const shoulder = Math.min(8, Math.max(3, state.depth * 0.68));
            const tip = 2 + state.depth;
            line.setAttribute('d', [
                `M 2 0 V ${start}`,
                `C 2 ${state.y - shoulder}, ${tip} ${state.y - shoulder}, ${tip} ${state.y}`,
                `C ${tip} ${state.y + shoulder}, 2 ${state.y + shoulder}, 2 ${end}`,
                `V ${state.height}`,
            ].join(' '));
        };

        const settleAt = (targetY) => {
            gsap.killTweensOf(state);
            if (reduceMotion) {
                state.y = targetY;
                state.depth = 9;
                render();
                return;
            }

            const grow = () => gsap.to(state, {
                depth: 9,
                duration: 0.32,
                ease: 'power2.out',
                onUpdate: render,
            });

            if (state.depth > 0.05 && Math.abs(state.y - targetY) > 1) {
                gsap.to(state, {
                    depth: 0,
                    duration: 0.18,
                    ease: 'power2.in',
                    onUpdate: render,
                    onComplete: () => {
                        state.y = targetY;
                        render();
                        grow();
                    },
                });
            } else {
                state.y = targetY;
                grow();
            }
        };

        const draw = () => {
            state.height = Math.max(1, rail.clientHeight);
            svg.setAttribute('viewBox', `0 0 20 ${state.height}`);
            svg.setAttribute('preserveAspectRatio', 'none');

            const activeLink = rail.querySelector('.article-toc a[aria-current="true"]');
            if (!(activeLink instanceof HTMLElement)) {
                gsap.killTweensOf(state);
                state.depth = 0;
                render();
                return;
            }

            const railTop = rail.getBoundingClientRect().top;
            const y = activeLink.getBoundingClientRect().top - railTop + activeLink.offsetHeight / 2;
            settleAt(Math.max(15, Math.min(state.height - 15, y)));
        };

        draw();
        if ('ResizeObserver' in window) new ResizeObserver(draw).observe(rail);
        else window.addEventListener('resize', draw);
        new MutationObserver(draw).observe(rail, { subtree: true, attributes: true, attributeFilter: ['aria-current', 'hidden'] });
        window.addEventListener('load', draw, { once: true });
    }
};

initContextRailLine();

for (const gallery of document.querySelectorAll('[data-media-gallery]')) {
    const track = gallery.querySelector('.media-gallery__track');
    if (!(track instanceof HTMLElement)) continue;
    const move = (direction) => track.scrollBy({ left: direction * track.clientWidth * 0.82, behavior: 'smooth' });
    gallery.querySelector('[data-gallery-previous]')?.addEventListener('click', () => move(-1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => move(1));
}

const menu = document.querySelector('#site-menu');
const menuButton = document.querySelector('[data-menu-trigger]');
const closeButton = document.querySelector('[data-menu-close]');
const menuPanel = document.querySelector('.menu-panel');

if (menu instanceof HTMLDialogElement && menuButton instanceof HTMLButtonElement && menuPanel instanceof HTMLElement) {
    const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let closeTimer;

    const finishClose = () => {
        window.clearTimeout(closeTimer);
        menu.close();
        delete menu.dataset.state;
    };

    const closeMenu = () => {
        if (!menu.open || menu.dataset.state === 'closing') return;
        menu.dataset.state = 'closing';
        menuButton.setAttribute('aria-expanded', 'false');

        if (prefersReducedMotion()) finishClose();
        else closeTimer = window.setTimeout(finishClose, 380);
    };

    menuButton.addEventListener('click', () => {
        if (menu.open) return;
        menu.showModal();
        menu.dataset.state = 'open';
        menuButton.setAttribute('aria-expanded', 'true');
    });
    closeButton?.addEventListener('click', closeMenu);
    menu.addEventListener('click', (event) => { if (event.target === menu) closeMenu(); });
    menu.addEventListener('close', () => { menuButton.setAttribute('aria-expanded', 'false'); menuButton.focus(); });
    menu.addEventListener('cancel', (event) => { event.preventDefault(); closeMenu(); });
}
