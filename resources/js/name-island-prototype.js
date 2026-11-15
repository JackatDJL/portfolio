// URL-only visual experiment. No persistence, extra menu or navigation changes.
export function initNameIsland(wordmark) {
    if (new URLSearchParams(window.location.search).get('name') !== 'island') return;
    const label = document.createElement('span');
    label.className = 'name-island__label';
    label.dataset.nameContrastLabel = '';
    label.textContent = wordmark.textContent.trim();
    const hint = document.createElement('span');
    hint.className = 'name-island__hint';
    hint.setAttribute('aria-hidden', 'true');
    const hintText = document.createElement('span');
    hintText.textContent = 'Startseite ↗';
    hint.append(hintText);
    wordmark.replaceChildren(label, hint);
    wordmark.classList.add('name-island');
}
