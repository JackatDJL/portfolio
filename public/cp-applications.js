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
        try {
            const response = await fetch(base + '/recipient-link', { method: revoke ? 'DELETE' : 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
            if (!response.ok) throw new Error('link failed');
            const data = await response.json();
            if (!revoke) { field.value = data.url; field.hidden = false; field.style.width = '100%'; field.focus(); field.select(); }
            else { field.value = ''; field.hidden = true; }
            status.textContent = revoke ? 'Empfängerzugriff widerrufen.' : 'Link jetzt kopieren und sicher verwahren. Er wird nach dem Verlassen dieser Seite nicht mehr angezeigt. Änderungen an den Bewerbungsdaten aktualisieren die freigegebene PDF.';
        } catch { status.textContent = 'Linkverwaltung fehlgeschlagen. Der angezeigte Link bleibt erhalten, seine Gültigkeit ist aber unklar, weil der Server die Anfrage möglicherweise bereits verarbeitet hat.'; }
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

// Private, explicit signature operations. No action runs on publish, preview or autosave.
document.addEventListener('click', async event => {
    const button = event.target.closest('[data-signature-action]');
    if (!button) return;
    const panel = button.closest('[data-application-signatures]');
    const status = panel.querySelector('[data-signature-status]');
    const match = location.pathname.match(/\/collections\/applications\/entries\/([^/]+)(?:\/|$)/);
    if (!match || match[1] === 'create') { status.textContent = 'Bitte zuerst speichern.'; return; }
    const base = location.pathname.split('/collections/')[0] + '/applications/' + encodeURIComponent(match[1]) + '/signatures/';
    const action = button.dataset.signatureAction;
    const version = button.dataset.version;
    const digest = button.dataset.sha256;
    if (action === 'download') { window.open(base + 'download?version=' + encodeURIComponent(version) + (button.dataset.signed ? '&signed=1' : ''), '_blank', 'noopener,noreferrer'); return; }
    if (action !== 'status' && !window.confirm(action === 'sandbox'
        ? 'Exakt diese PDF lokal mit einem wegwerfbaren Testzertifikat signieren? KEINE QES. SHA-256: ' + digest
        : action === 'activate' ? 'Diese Version ausdrücklich zur Auslieferung auswählen? Integrität ist geprüft; QES und Widerruf sind NICHT bestätigt. Sandbox bleibt für Empfänger gesperrt.'
        : action === 'prepare' ? 'Gespeicherte Daten zur endgültigen privaten PDF einfrieren? Änderungen erfordern eine neue Version.'
        : action === 'unsigned' ? 'Signaturauswahl aufheben und künftig die aktuelle unsignierte PDF ausliefern?'
        : 'Extern signierte PDF für genau diese unveränderte Version importieren? SHA-256: ' + digest)) return;
    let file;
    if (action === 'import') {
        file = button.parentElement.querySelector('input[type="file"]').files[0];
        if (!file) { status.textContent = 'Bitte eine signierte PDF auswählen.'; return; }
    }
    button.disabled = true;
    try {
        if (action !== 'status') {
            const body = new FormData();
            body.append('authorized', '1');
            if (version) body.append('version', version);
            if (digest) body.append('sha256', digest);
            if (file) body.append('pdf', file);
            const response = await fetch(base + action, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
            if (!response.ok) { const data = await response.json(); throw new Error(data.message || 'Vorgang fehlgeschlagen.'); }
        }
        const response = await fetch(base + 'status', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Status nicht verfügbar.');
        const data = await response.json();
        status.textContent = 'Auslieferung: ' + data.mode + '. QES-Qualifikation nicht geprüft. Vorschau bleibt unsigniert. Bei Änderungen wird eine ausgewählte signierte Auslieferung gesperrt.';
        const list = panel.querySelector('[data-signature-versions]');
        list.replaceChildren();
        for (const item of data.versions) {
            const row = document.createElement('div');
            const label = document.createElement('p');
            label.textContent = item.id + ' · ' + item.state + (item.sandbox ? ' · SANDBOX, KEINE QES' : '') + (item.id === data.active_version_id ? ' · ausgewählt' : '') + ' · SHA-256: ' + item.sha256;
            row.append(label);
            if (item.verification) {
                const report = document.createElement('p');
                report.textContent = 'Integrität: ' + item.verification.integrity + '; Zertifikatspfad: ' + item.verification.certificate_path + '; Widerruf: ' + item.verification.revocation + '; Qualifikation: ' + item.verification.qualification;
                row.append(report);
            }
            const add = (action, text, signed = false) => {
                const control = document.createElement('button'); control.type = 'button'; control.textContent = text;
                Object.assign(control.dataset, { signatureAction: action, version: item.id, sha256: item.sha256 });
                if (signed) control.dataset.signed = '1';
                row.append(control);
            };
            add('download', 'Unsigniertes Original');
            if (item.state === 'prepared') {
                const input = document.createElement('input'); input.type = 'file'; input.accept = 'application/pdf'; input.setAttribute('aria-label', 'Extern signierte PDF'); row.append(input);
                add('import', 'Extern signierte PDF importieren');
                if (data.capabilities.available && data.capabilities.sandbox) add('sandbox', 'Lokale Testsignatur (keine QES)');
            } else if (item.state === 'validated') { add('download', 'Signierte PDF', true); add('activate', 'Zur Auslieferung auswählen'); }
            list.append(row);
        }
    } catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
});
