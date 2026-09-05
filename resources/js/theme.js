const preferenceKey = 'jack-theme';

export function initThemeSwitcher() {
    const root = document.documentElement;
    const choices = document.querySelectorAll('input[name="theme"], [data-theme-choice]');
    const selected = root.dataset.theme || 'system';

    for (const choice of choices) {
        choice.checked = choice.value === selected;
        choice.addEventListener('change', () => {
            for (const otherChoice of choices) {
                otherChoice.checked = otherChoice.value === choice.value;
            }
            if (choice.value === 'system') delete root.dataset.theme;
            else root.dataset.theme = choice.value;
            try {
                if (choice.value === 'system') localStorage.removeItem(preferenceKey);
                else localStorage.setItem(preferenceKey, choice.value);
            } catch {
                // Theme controls work for this page even when storage is blocked.
            }
        });
    }
}
