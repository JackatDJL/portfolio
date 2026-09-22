(function () {
    const request = async (method, endpoint, body) => {
        const response = await fetch(endpoint, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' }, ...(method === 'GET' ? {} : { body: JSON.stringify(body) }) });
        if (!response.ok) throw new Error('Zugriffslink konnte nicht verwaltet werden.');
        return response.json();
    };
    const bind = async root => {
        if (root.dataset.bound === location.pathname) return;
        root.cvEvents?.abort();
        root.cvEvents = new AbortController();
        root.dataset.bound = location.pathname;
        // Statamic 6 Kitt button utility classes; the public site's .btn is not loaded in CP.
        const nativeButton = 'relative inline-flex items-center justify-center whitespace-nowrap font-medium antialiased cursor-pointer no-underline disabled:opacity-60 disabled:cursor-not-allowed bg-linear-to-b from-white to-gray-50 hover:to-gray-100 text-gray-900 border border-gray-300 shadow-ui-sm dark:from-gray-850 dark:to-gray-900 dark:border-gray-700/80 dark:text-gray-300 px-4 h-10 text-sm gap-2 rounded-lg';
        root.querySelectorAll('button').forEach(button => {
            button.className = nativeButton + (button.dataset.action === 'revoke' ? ' text-red-600 dark:text-red-400' : '');
            button.style.marginBlockEnd = '.5rem';
        });
        const match = location.pathname.match(/\/collections\/cv_profiles\/entries\/([^/]+)/);
        let path = match ? '/cv/' + decodeURIComponent(match[1]) : '/cv';
        let links = {};
        const status = root.querySelector('[data-cv-access-status]');
        const refresh = async () => {
            const result = await request('GET', '/cp/cv/private-link/status?path=' + encodeURIComponent(path));
            path = result.path; links = result.links;
            for (const kind of ['temporary', 'permanent']) {
                root.querySelector(`[data-copy="${kind}"]`).disabled = !links[kind]?.url;
                root.querySelector(`[data-action="${kind}"]`).textContent = links[kind] ? (kind === 'temporary' ? 'Neu erstellen' : 'Aktiv') : 'Erstellen';
                root.querySelector(`[data-action="${kind}"]`).disabled = kind === 'permanent' && !!links[kind];
                root.querySelector(`[data-state="${kind}"]`).textContent = links[kind] ? (!links[kind].url ? 'Aktiv, nach Schlüsselwechsel nicht kopierbar. Bei Bedarf widerrufen und neu erstellen.' : links[kind].expires_at ? 'Gültig bis ' + new Date(links[kind].expires_at + 'Z').toLocaleString('de-DE') : 'Aktiv, bis zum Widerruf') : 'Kein aktiver Link';
            }
            root.querySelector('[data-action="revoke"]').disabled = !links.permanent;
        };
        root.addEventListener('click', async event => {
            const button = event.target.closest('button'); if (!button) return;
            button.disabled = true;
            try {
                if (button.dataset.copy) { await navigator.clipboard.writeText(links[button.dataset.copy].url); status.textContent = 'Link kopiert.'; }
                if (button.dataset.action) {
                    const action = button.dataset.action;
                    if (action === 'revoke' && !confirm('Dauerhaften Link und zugehörige Freigaben widerrufen?')) return;
                    if (action === 'temporary' && links.temporary && !confirm('Weiteren temporären Prüf-Link erstellen?')) return;
                    await request(action === 'revoke' ? 'DELETE' : 'POST', '/cp/cv/private-link/' + (action === 'revoke' ? 'permanent' : action), { path, replace: action === 'temporary' && !!links.temporary });
                    status.textContent = action === 'revoke' ? 'Link widerrufen.' : 'Link erstellt.';
                }
                if (button.dataset.pdf === 'copy') { await navigator.clipboard.writeText(location.origin + path + '/pdf'); status.textContent = 'PDF-URL kopiert.'; }
                if (button.dataset.pdf === 'public') window.open(path + '/pdf?public=1', '_blank', 'noopener');
                if (button.dataset.pdf === 'private') {
                    const tab = window.open('about:blank', '_blank');
                    const result = await request('POST', '/cp/cv/private-link/temporary', { path });
                    const url = new URL(result.url); url.pathname += '/pdf'; if (tab) { tab.opener = null; tab.location = url.href; }
                }
            } catch (error) { status.textContent = error.message; }
            finally { button.disabled = false; try { await refresh(); } catch (error) { status.textContent = error.message; } }
        }, { signal: root.cvEvents.signal });
        try { await refresh(); } catch(error) { status.textContent = error.message; }
    };
    const scan = () => document.querySelectorAll('[data-cv-profile-access]').forEach(bind);
    new MutationObserver(scan).observe(document.documentElement, {childList:true,subtree:true}); scan();
})();
