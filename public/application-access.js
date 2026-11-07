(async () => {
    const fragment = new URLSearchParams(location.hash.slice(1));
    const token = fragment.get('application');
    // Remove the bearer secret before any request, including a failed exchange.
    if (location.hash) history.replaceState(null, '', location.pathname);
    if (!token) return;
    const status = document.querySelector('[data-application-access-status]');
    const id = document.body.dataset.applicationId;
    const exchange = (csrf) => fetch('/applications/token-exchange', {
        method: 'POST', credentials: 'same-origin', referrerPolicy: 'no-referrer',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ id, token }),
    });
    try {
        let response = await exchange(document.querySelector('meta[name="csrf-token"]').content);
        if (response.status === 419) {
            const refreshed = await fetch('/applications/' + encodeURIComponent(id), { credentials: 'same-origin', cache: 'no-store', referrerPolicy: 'no-referrer' });
            if (!refreshed.ok) throw new Error('session refresh failed');
            const documentCopy = new DOMParser().parseFromString(await refreshed.text(), 'text/html');
            const freshToken = documentCopy.querySelector('meta[name="csrf-token"]')?.content;
            if (!freshToken) throw new Error('session refresh failed');
            response = await exchange(freshToken);
        }
        if (!response.ok) throw new Error('exchange failed');
        const link = document.querySelector('[data-application-pdf]');
        link.hidden = false;
        status.textContent = 'Zugriff freigegeben.';
        location.replace(link.href);
    } catch { status.textContent = 'Dieser Link ist ungültig oder wurde widerrufen.'; }
})();
