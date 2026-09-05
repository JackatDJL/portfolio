const root = document.documentElement;
const choices = document.querySelectorAll('input[name="theme"]');
const selected = root.dataset.theme || 'system';
for (const choice of choices) {
    choice.checked = choice.value === selected;
    choice.addEventListener('change', () => {
        if (choice.value === 'system') delete root.dataset.theme;
        else root.dataset.theme = choice.value;
        try {
            if (choice.value === 'system') localStorage.removeItem('jack-design-system-theme');
            else localStorage.setItem('jack-design-system-theme', choice.value);
        } catch { /* The switch still works without persistence. */ }
    });
}
document.querySelector('#replay-motion').addEventListener('click', () => {
    const sample = document.querySelector('#motion-sample');
    sample.classList.remove('animate-ui-in');
    void sample.offsetWidth;
    sample.classList.add('animate-ui-in');
});
