(async () => {
    const fragment = new URLSearchParams(location.hash.slice(1));
    const token = fragment.get('application');
    // Remove the bearer secret before any request, including a failed exchange.
    if (location.hash) history.replaceState(null, '', location.pathname);
    if (!token) return;
    const status = document.querySelector('[data-application-access-status]');
    try {
        const response = await fetch('/applications/token-exchange', {
            method: 'POST', credentials: 'same-origin', referrerPolicy: 'no-referrer',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ id: document.body.dataset.applicationId, token }),
        });
        if (!response.ok) throw new Error('exchange failed');
        const link = document.querySelector('[data-application-pdf]');
        link.hidden = false;
        status.textContent = 'Zugriff freigegeben.';
        location.replace(link.href);
    } catch { status.textContent = 'Dieser Link ist ungültig oder wurde widerrufen.'; }
})();
