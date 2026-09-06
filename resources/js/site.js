import { initThemeSwitcher } from './theme.js';

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
    if (h2Count < 3) return;

    const usedIds = new Set([...document.querySelectorAll('[id]')].map(({ id }) => id));
    const headings = allHeadings.filter((heading) => heading.tagName === 'H2' || h2Count >= 4);
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
        const target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
        if (target) window.requestAnimationFrame(() => target.scrollIntoView());
    }
};

initArticleToc();

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
