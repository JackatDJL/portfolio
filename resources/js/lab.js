import { initThemeSwitcher } from './theme.js';

initThemeSwitcher();
document.querySelector('#replay-motion').addEventListener('click', () => {
    const sample = document.querySelector('#motion-sample');
    sample.classList.remove('animate-ui-in');
    void sample.offsetWidth;
    sample.classList.add('animate-ui-in');
});
