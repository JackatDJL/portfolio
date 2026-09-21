(function () {
    const token = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const profilePath = () => {
        const match = location.pathname.match(/\/collections\/cv_profiles\/entries\/([^/]+)/);
        return match ? `/cv/${decodeURIComponent(match[1])}` : null;
    };
    const copy = async (value) => { await navigator.clipboard.writeText(value); };
    const request = async (method, endpoint, body) => {
        const response = await fetch(endpoint, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), Accept: 'application/json' }, body: JSON.stringify(body) });
        if (!response.ok) throw new Error(response.status === 404 ? 'Profil nicht gefunden.' : 'Link konnte nicht verwaltet werden.');
        return response.json();
    };
    const bind = (root) => {
        if (root.dataset.bound || !profilePath()) return;
        root.dataset.bound = 'true';
        const status = root.querySelector('[data-cv-access-status]');
        const run = async (button, action) => {
            button.disabled = true; status.textContent = '';
            try { status.textContent = await action(); status.className = 'mt-3 text-sm text-green-600'; }
            catch (error) { status.textContent = error.message; status.className = 'mt-3 text-sm text-red-600'; }
            finally { button.disabled = false; }
        };
        root.querySelector('[data-cv-access-temporary]').addEventListener('click', (event) => run(event.currentTarget, async () => { const data = await request('POST', '/cp/cv/private-link/temporary', { path: profilePath() }); await copy(data.url); return 'Temporärer Prüf-Link erstellt und kopiert. Er läuft automatisch ab.'; }));
        root.querySelector('[data-cv-access-permanent]').addEventListener('click', (event) => run(event.currentTarget, async () => { const data = await request('POST', '/cp/cv/private-link/permanent', { path: profilePath() }); await copy(data.url); return `Dauerhafter Link ${data.identifier} kopiert.`; }));
        root.querySelector('[data-cv-access-replace]').addEventListener('click', (event) => run(event.currentTarget, async () => { if (!window.confirm('Aktiven dauerhaften Link widerrufen und ersetzen?')) return 'Link blieb unverändert.'; const data = await request('POST', '/cp/cv/private-link/permanent', { path: profilePath(), replace: true }); await copy(data.url); return `Neuer dauerhafter Link ${data.identifier} kopiert.`; }));
        root.querySelector('[data-cv-access-revoke]').addEventListener('click', (event) => run(event.currentTarget, async () => { const data = await request('DELETE', '/cp/cv/private-link/permanent', { path: profilePath() }); return data.revoked ? 'Dauerhafter Link widerrufen.' : 'Kein aktiver dauerhafter Link vorhanden.'; }));
    };
    const scan = () => document.querySelectorAll('[data-cv-profile-access]').forEach(bind);
    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    scan();
})();
