export function initApplicationRecipientAnalytics() {
    try {
        if (window.localStorage.getItem('application-recipient-analytics') !== '1') return;
    } catch {
        return;
    }

    fetch('/applications/analytics/page-view', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        referrerPolicy: 'no-referrer',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ path: window.location.pathname }),
    }).then(response => response.ok ? response.json() : null).then(result => {
        if (result?.tracked === false) window.localStorage.removeItem('application-recipient-analytics');
    }).catch(() => {});
}
