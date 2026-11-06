document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-reset-application-date], [data-application-cv], [data-application-preview], [data-application-export], [data-application-link], [data-application-revoke]');
    if (!button) return;
    const match = location.pathname.match(/\/collections\/applications\/entries\/([^/]+)(?:\/|$)/);
    const status = button.closest('[data-application-actions]').querySelector('[data-application-status]');
    if (!match) { status.textContent = 'Bitte zuerst speichern.'; return; }
    if (match[1] === 'create') { status.textContent = 'Bitte zuerst speichern.'; return; }
    const base = location.pathname.split('/collections/')[0] + '/applications/' + encodeURIComponent(match[1]);
    if (button.hasAttribute('data-application-preview') || button.hasAttribute('data-application-export')) {
        window.open(base + (button.hasAttribute('data-application-preview') ? '/preview.pdf' : '/export.pdf'), '_blank', 'noopener,noreferrer');
        status.textContent = 'Es werden die gespeicherten Daten exportiert. Ungespeicherte Änderungen vorher speichern.';
        return;
    }
    if (button.hasAttribute('data-application-link') || button.hasAttribute('data-application-revoke')) {
        const revoke = button.hasAttribute('data-application-revoke');
        if (!window.confirm(revoke ? 'Empfängerlink und bestehende Zugriffssitzungen widerrufen?' : 'Empfängerlink erstellen? Ein bestehender Link wird ersetzt. Der neue Link gibt die vollständige private Bewerbung frei und wird nur einmal angezeigt.')) return;
        button.disabled = true;
        const field = button.closest('[data-application-actions]').querySelector('[data-application-recipient-link]');
        field.value = ''; field.hidden = true;
        try {
            const response = await fetch(base + '/recipient-link', { method: revoke ? 'DELETE' : 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
            if (!response.ok) throw new Error('link failed');
            const data = await response.json();
            if (!revoke) { field.value = data.url; field.hidden = false; field.style.width = '100%'; field.focus(); field.select(); }
            status.textContent = revoke ? 'Empfängerzugriff widerrufen.' : 'Link jetzt kopieren und sicher verwahren. Er wird nach dem Verlassen dieser Seite nicht mehr angezeigt. Änderungen an den Bewerbungsdaten aktualisieren die freigegebene PDF.';
        } catch { status.textContent = 'Empfängerlink konnte nicht verwaltet werden. Datenbankmigration und Berechtigung prüfen.'; }
        finally { button.disabled = false; }
        return;
    }
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
