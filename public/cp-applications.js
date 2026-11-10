document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-reset-application-date], [data-application-cv]');
    if (!button) return;
    const match = location.pathname.match(/\/collections\/applications\/entries\/([^/]+)(?:\/|$)/);
    const status = button.closest('[data-application-actions]').querySelector('[data-application-status]');
    if (!match) { status.textContent = 'Bitte zuerst speichern.'; return; }
    if (match[1] === 'create') { status.textContent = 'Bitte zuerst speichern.'; return; }
    if (button.hasAttribute('data-application-cv')) {
        const cpRoot = location.pathname.split('/collections/')[0];
        window.open(cpRoot + '/applications/' + encodeURIComponent(match[1]) + '/cv.pdf', '_blank', 'noopener,noreferrer');
        return;
    }
    if (!window.confirm('Gespeichertes Datum auf heute setzen? Ungespeicherte Änderungen zuerst speichern.')) return;
    button.disabled = true;
    try {
        const cpRoot = location.pathname.split('/collections/')[0];
        const response = await fetch(cpRoot + '/applications/' + encodeURIComponent(match[1]) + '/reset-date', {
            method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        });
        if (!response.ok) throw new Error('reset failed');
        location.reload();
    } catch { status.textContent = 'Datum konnte nicht zurückgesetzt werden.'; button.disabled = false; }
});

// Use the same Statamic 6 button utilities as the existing CV profile actions.
(() => {
    const decorate = () => document.querySelectorAll('[data-application-actions] button').forEach(button => {
        button.className = 'relative inline-flex items-center justify-center whitespace-nowrap font-medium antialiased cursor-pointer no-underline disabled:opacity-60 disabled:cursor-not-allowed bg-linear-to-b from-white to-gray-50 hover:to-gray-100 text-gray-900 border border-gray-300 shadow-ui-sm dark:from-gray-850 dark:to-gray-900 dark:border-gray-700/80 dark:text-gray-300 px-4 h-10 text-sm gap-2 rounded-lg';
    });
    decorate();
    new MutationObserver(decorate).observe(document.documentElement, { childList: true, subtree: true });
})();
