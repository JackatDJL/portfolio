(() => {
    const entryId = () => location.pathname.match(/\/collections\/applications\/entries\/([^/]+)(?:\/|$)/)?.[1];
    const cpRoot = () => location.pathname.split('/collections/')[0];
    const appBase = () => {
        const id = entryId();
        return id && id !== 'create' ? cpRoot() + '/applications/' + encodeURIComponent(id) : null;
    };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const showDate = value => value ? new Date(value).toLocaleString('de-DE') : '–';

    async function loadOverview(base) {
        const summary = document.querySelector('[data-application-summary]');
        const grants = document.querySelector('[data-application-grants]');
        if (!summary || !base) return;
        try {
            const response = await fetch(base + '/summary', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Übersicht nicht verfügbar.');
            const data = await response.json();
            const statusLabels = { draft: 'Entwurf', ready: 'Bereit', sent: 'Versendet', interview: 'Gespräch', accepted: 'Zusage', rejected: 'Absage', withdrawn: 'Zurückgezogen' };
            const heading = document.createElement('h2');
            heading.textContent = [data.organisation, data.position].filter(Boolean).join(' · ') || 'Bewerbung';
            const status = document.createElement('p');
            status.textContent = 'Status: ' + (statusLabels[data.status] || 'Entwurf');
            const latest = document.createElement('p');
            latest.textContent = data.latest_correspondence
                ? 'Letzte Korrespondenz: ' + data.latest_correspondence.date + ' · ' + data.latest_correspondence.direction + (data.latest_correspondence.summary ? ' · ' + data.latest_correspondence.summary : '')
                : 'Letzte Korrespondenz: keine erfasst';
            const followUp = document.createElement('p');
            followUp.textContent = 'Nächste Wiedervorlage: ' + (data.follow_up_date || 'keine geplant');
            const notes = document.createElement('p');
            notes.textContent = 'Private Notizen: ' + (data.notes || 'keine');
            summary.replaceChildren(heading, status, latest, followUp, notes);
        } catch (error) {
            summary.textContent = error.message;
        }

        if (!grants) return;
        try {
            const response = await fetch(base + '/recipient-grants', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Empfängerzugriffe konnten nicht geladen werden.');
            const data = await response.json();
            grants.replaceChildren();
            for (const grant of data.grants) {
                const row = document.createElement('div');
                const details = document.createElement('p');
                const state = grant.revoked_at ? 'widerrufen' : (grant.expires_at && new Date(grant.expires_at) < new Date() ? 'abgelaufen' : 'aktiv');
                details.textContent = grant.label + ' · ' + state + ' · erstellt ' + showDate(grant.created_at) + ' · zuletzt geöffnet ' + showDate(grant.last_accessed_at) + (grant.expires_at ? ' · Ablauf ' + showDate(grant.expires_at) : '');
                row.append(details);
                if (!grant.revoked_at) {
                    const revoke = document.createElement('button');
                    revoke.type = 'button';
                    revoke.textContent = 'Diesen Zugriff widerrufen';
                    revoke.dataset.applicationGrantRevoke = grant.id;
                    row.append(revoke);
                }
                grants.append(row);
            }
            if (data.grants.some(grant => !grant.revoked_at)) {
                const revokeAll = document.createElement('button');
                revokeAll.type = 'button';
                revokeAll.textContent = 'Alle Empfängerzugriffe widerrufen';
                revokeAll.dataset.applicationGrantRevokeAll = '1';
                grants.append(revokeAll);
            }
        } catch (error) {
            grants.textContent = error.message;
        }
    }

    let profileRequest = 0;
    async function loadProfileSummary(base, profileId = undefined) {
        const target = document.querySelector('[data-application-profile-summary]');
        if (!target || !base) return;
        const request = ++profileRequest;
        try {
            const url = new URL(base + '/cv-profile', location.origin);
            if (profileId !== undefined) url.searchParams.set('profile', profileId || '');
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Profilzusammenfassung nicht verfügbar.');
            const data = await response.json();
            if (request !== profileRequest) return;
            target.replaceChildren();
            if (!data.selected) {
                target.textContent = 'Standard-CV ausgewählt. Einleitung und Akzentfarbe können unten angepasst werden.';
                return;
            }
            const title = document.createElement('strong');
            title.textContent = data.title || 'CV-Profil';
            const slug = document.createElement('span');
            slug.textContent = ' · ' + data.slug;
            target.append(title, slug, document.createElement('br'));
            const addLink = (href, label) => {
                if (!href) return;
                const link = document.createElement('a');
                link.href = href;
                link.textContent = label;
                link.rel = 'noopener noreferrer';
                target.append(link, document.createTextNode(' · '));
            };
            addLink(data.edit_url, 'Profil bearbeiten');
            if (data.public) addLink(data.public_url, 'Öffentliche Profilseite öffnen');
            addLink(data.cv_pdf_url, 'Bewerbungs-CV als PDF prüfen');
            target.lastChild?.textContent === ' · ' && target.lastChild.remove();
        } catch (error) {
            if (request !== profileRequest) return;
            target.textContent = error.message;
        }
    }

    const profileReference = () => {
        const field = [...document.querySelectorAll('input[type="hidden"]')].find(input => input.name.endsWith('[profile]') || input.name.endsWith('.profile'));
        if (!field) return { present: false, id: undefined, key: 'missing' };
        let value = field.value;
        try { value = JSON.parse(value); } catch { /* Statamic may provide a scalar reference. */ }
        const id = Array.isArray(value) ? (value[0] || null) : (typeof value === 'string' && value ? value : null);
        return { present: true, id, key: 'present:' + (id || 'empty') };
    };
    let lastProfileReference = null;
    const refreshProfileSummary = () => {
        const base = appBase();
        if (!base || !document.querySelector('[data-application-profile-summary]')) return;
        const reference = profileReference();
        if (reference.key === lastProfileReference) return;
        lastProfileReference = reference.key;
        loadProfileSummary(base, reference.present ? reference.id : undefined);
    };
    document.addEventListener('input', event => {
        if (event.target.name?.endsWith('[profile]') || event.target.name?.endsWith('.profile')) setTimeout(refreshProfileSummary, 0);
    });
    document.addEventListener('change', event => {
        if (event.target.name?.endsWith('[profile]') || event.target.name?.endsWith('.profile')) setTimeout(refreshProfileSummary, 0);
    });
    document.addEventListener('click', () => setTimeout(refreshProfileSummary, 30));
    new MutationObserver(refreshProfileSummary).observe(document.documentElement, { attributes: true, attributeFilter: ['value'], childList: true, subtree: true });

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-reset-application-date], [data-application-cv], [data-application-preview], [data-application-export], [data-application-grant-create], [data-application-grant-revoke], [data-application-grant-revoke-all], [data-application-copy-link]');
        if (!button) return;
        const base = appBase();
        const panel = button.closest('[data-application-actions]') || document.querySelector('[data-application-actions]');
        const status = panel?.querySelector('[data-application-status]') || document.querySelector('[data-application-status]');
        if (!base) { if (status) status.textContent = 'Bitte zuerst speichern.'; return; }

        if (button.hasAttribute('data-application-preview') || button.hasAttribute('data-application-export')) {
            window.open(base + (button.hasAttribute('data-application-preview') ? '/preview.pdf' : '/export.pdf'), '_blank', 'noopener,noreferrer');
            if (status) status.textContent = 'Es werden gespeicherte Daten verwendet. Vorherige Änderungen zuerst speichern.';
            return;
        }
        if (button.hasAttribute('data-application-cv')) {
            window.open(base + '/cv.pdf', '_blank', 'noopener,noreferrer');
            return;
        }
        if (button.hasAttribute('data-application-copy-link')) {
            const field = document.querySelector('[data-application-recipient-link]');
            try {
                await navigator.clipboard.writeText(field.value);
                if (status) status.textContent = 'Link kopiert. Er wird nach dem Verlassen dieser Seite nicht erneut angezeigt.';
            } catch {
                field.focus(); field.select();
                if (status) status.textContent = 'Kopieren war nicht möglich. Link markieren und manuell kopieren.';
            }
            return;
        }
        if (button.hasAttribute('data-application-grant-create')) {
            const scope = button.closest('[data-application-actions]');
            const label = scope.querySelector('[data-application-grant-label]').value.trim();
            const expiryValue = scope.querySelector('[data-application-grant-expiry]').value;
            const body = { label };
            if (expiryValue) body.expires_at = new Date(expiryValue).toISOString();
            if (!label) { status.textContent = 'Bitte eine Bezeichnung eingeben.'; return; }
            if (!window.confirm('Diesen Empfängerzugriff erstellen? Der Link gibt Zugriff auf die vollständige private Bewerbung. Es wird keine E-Mail versendet.')) return;
            button.disabled = true;
            try {
                const response = await fetch(base + '/recipient-grants', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) });
                if (!response.ok) throw new Error((await response.json()).message || 'Empfängerzugriff konnte nicht erstellt werden.');
                const data = await response.json();
                const field = document.querySelector('[data-application-recipient-link]');
                const labelField = document.querySelector('[data-application-new-link-label]');
                const copy = document.querySelector('[data-application-copy-link]');
                field.value = data.url;
                labelField.hidden = false;
                copy.hidden = false;
                status.textContent = 'Der Link wird nur jetzt angezeigt. Kopiere und verwahre ihn sicher. Weitere Zugriffe widerrufen diesen Link nicht.';
                await loadOverview(base);
            } catch (error) { status.textContent = error.message; }
            finally { button.disabled = false; }
            return;
        }
        if (button.hasAttribute('data-application-grant-revoke') || button.hasAttribute('data-application-grant-revoke-all')) {
            const all = button.hasAttribute('data-application-grant-revoke-all');
            const grantId = button.dataset.applicationGrantRevoke;
            if (!window.confirm(all ? 'Wirklich alle Empfängerzugriffe widerrufen? Bereits geöffnete Sitzungen verlieren ebenfalls den Zugriff.' : 'Diesen Empfängerzugriff widerrufen? Bereits geöffnete Sitzungen verlieren ebenfalls den Zugriff.')) return;
            button.disabled = true;
            try {
                const response = await fetch(base + '/recipient-grants' + (all ? '' : '/' + encodeURIComponent(grantId)), { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
                if (!response.ok) throw new Error('Empfängerzugriff konnte nicht widerrufen werden.');
                if (status) status.textContent = all ? 'Alle aktiven Empfängerzugriffe widerrufen.' : 'Empfängerzugriff widerrufen.';
                await loadOverview(base);
            } catch (error) { if (status) status.textContent = error.message; }
            finally { button.disabled = false; }
            return;
        }
        if (!window.confirm('Gespeichertes Datum auf heute setzen? Ungespeicherte Änderungen zuerst speichern.')) return;
        button.disabled = true;
        try {
            const response = await fetch(base + '/reset-date', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            if (!response.ok) throw new Error('Datum konnte nicht zurückgesetzt werden.');
            location.reload();
        } catch (error) { if (status) status.textContent = error.message; button.disabled = false; }
    });

    const base = appBase();
    if (base) {
        loadOverview(base);
        refreshProfileSummary();
    }
})();

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
