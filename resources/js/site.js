import { initThemeSwitcher } from './theme.js';

initThemeSwitcher();

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
