import { initThemeSwitcher } from './theme.js';

initThemeSwitcher();

const menu = document.querySelector('#site-menu');
const menuButton = document.querySelector('[data-menu-trigger]');
const closeButton = document.querySelector('[data-menu-close]');

if (menu instanceof HTMLDialogElement && menuButton instanceof HTMLButtonElement) {
    const closeMenu = () => menu.close();

    menuButton.addEventListener('click', () => menu.showModal());
    closeButton?.addEventListener('click', closeMenu);
    menu.addEventListener('click', (event) => { if (event.target === menu) closeMenu(); });
    menu.addEventListener('close', () => { menuButton.setAttribute('aria-expanded', 'false'); menuButton.focus(); });
    menu.addEventListener('cancel', (event) => { event.preventDefault(); closeMenu(); });
    new MutationObserver(() => menuButton.setAttribute('aria-expanded', String(menu.open)))
        .observe(menu, { attributes: true, attributeFilter: ['open'] });
}
