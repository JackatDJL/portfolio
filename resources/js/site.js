import { initThemeSwitcher } from './theme.js';
import { gsap } from 'gsap';
import { ScrollToPlugin } from 'gsap/ScrollToPlugin';

gsap.registerPlugin(ScrollToPlugin);

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
    let currentId;
    const setCurrent = (id) => {
        if (currentId === id) return;
        currentId = id;
        for (const link of links) {
            if (link.hash === `#${id}`) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
        document.dispatchEvent(new CustomEvent('article:toc-change', { detail: { id } }));
    };
    setCurrent(headings[0].id);
    links.forEach((link) => link.addEventListener('click', (event) => {
        const id = link.hash.slice(1);
        const heading = document.getElementById(id);
        if (!(heading instanceof HTMLElement)) return;

        event.preventDefault();
        setCurrent(id);
        history.pushState(null, '', `#${id}`);

        const target = Math.max(0, window.scrollY + heading.getBoundingClientRect().top - 104);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            window.scrollTo({ top: target });
            return;
        }
        gsap.to(window, { duration: 0.7, ease: 'power2.inOut', scrollTo: { y: target, autoKill: true } });
    }));

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

        const state = { activeY: 0, activeDepth: 0, hoverY: 0, hoverDepth: 0, height: 1 };
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const render = () => {
            const bumps = [
                { y: state.activeY, depth: state.activeDepth },
                { y: state.hoverY, depth: state.hoverDepth },
            ].filter(({ depth }) => depth > 0.05).sort((a, b) => a.y - b.y);
            let cursor = 0;
            const commands = ['M 2 0'];
            for (const { y, depth } of bumps) {
                const shoulder = Math.min(9, Math.max(3, depth * 0.72));
                const start = Math.max(cursor, y - 15);
                const end = Math.min(state.height, y + 15);
                const tip = 2 + depth;
                commands.push(`V ${start}`);
                commands.push(`C 2 ${y - shoulder}, ${tip} ${y - shoulder}, ${tip} ${y}`);
                commands.push(`C ${tip} ${y + shoulder}, 2 ${y + shoulder}, 2 ${end}`);
                cursor = end;
            }
            commands.push(`V ${state.height}`);
            line.setAttribute('d', commands.join(' '));
        };

        const linkY = (link) => {
            const railTop = rail.getBoundingClientRect().top;
            const y = link.getBoundingClientRect().top - railTop + link.offsetHeight / 2;
            return Math.max(15, Math.min(state.height - 15, y));
        };

        const settleAt = (targetY) => {
            gsap.killTweensOf(state);
            state.hoverDepth = 0;
            render();
            if (reduceMotion) {
                state.activeY = targetY;
                state.activeDepth = 9;
                render();
                return;
            }

            const grow = () => gsap.to(state, {
                activeDepth: 9,
                duration: 0.32,
                ease: 'power2.out',
                onUpdate: render,
            });

            if (state.activeDepth > 0.05 && Math.abs(state.activeY - targetY) > 1) {
                gsap.to(state, {
                    activeDepth: 0,
                    duration: 0.18,
                    ease: 'power2.in',
                    onUpdate: render,
                    onComplete: () => {
                        state.activeY = targetY;
                        render();
                        grow();
                    },
                });
            } else {
                state.activeY = targetY;
                grow();
            }
        };

        const showHoverIntent = (link) => {
            const targetY = linkY(link);
            if (Math.abs(state.activeY - targetY) < 1) {
                return;
            }
            state.hoverY = targetY;
            gsap.to(state, { activeDepth: 6.5, hoverDepth: 3.5, duration: 0.18, ease: 'power2.out', onUpdate: render });
        };

        const clearHoverIntent = () => gsap.to(state, {
            activeDepth: 9,
            hoverDepth: 0,
            duration: 0.2,
            ease: 'power2.out',
            onUpdate: render,
        });

        const draw = () => {
            state.height = Math.max(1, rail.clientHeight);
            svg.setAttribute('viewBox', `0 0 20 ${state.height}`);
            svg.setAttribute('preserveAspectRatio', 'none');

            const activeLink = rail.querySelector('.article-toc a[aria-current="true"]');
            if (!(activeLink instanceof HTMLElement)) {
                gsap.killTweensOf(state);
                state.activeDepth = 0;
                state.hoverDepth = 0;
                render();
                return;
            }
            settleAt(linkY(activeLink));
        };

        draw();
        if ('ResizeObserver' in window) new ResizeObserver(draw).observe(rail);
        else window.addEventListener('resize', draw);
        document.addEventListener('article:toc-change', () => {
            const activeLink = rail.querySelector('.article-toc a[aria-current="true"]');
            if (activeLink instanceof HTMLElement) settleAt(linkY(activeLink));
        });
        rail.addEventListener('pointerover', (event) => {
            if (!(event.target instanceof Element)) return;
            const link = event.target.closest('.article-toc a');
            if (link instanceof HTMLElement && rail.contains(link)) showHoverIntent(link);
        });
        rail.addEventListener('pointerout', (event) => {
            if (!(event.target instanceof Element)) return;
            const link = event.target.closest('.article-toc a');
            if (link instanceof HTMLElement && rail.contains(link) && !link.contains(event.relatedTarget)) clearHoverIntent();
        });
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
