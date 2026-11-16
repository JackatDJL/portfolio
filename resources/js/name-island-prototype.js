// URL-only CSS experiment. Keep the original home link and its accessible name.
export function initNameIsland(wordmark) {
    if (new URLSearchParams(window.location.search).get('name') !== 'island') return false;
    wordmark.classList.add('name-island');
    return true;
}
