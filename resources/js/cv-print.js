// Open during the key gesture so popup blockers permit the canonical PDF tab.
// Browser PDF viewers differ: print() is best effort; the foreground PDF remains available.
document.addEventListener('keydown', event => {
    if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'p' || event.altKey) return;
    if (!document.querySelector('.cv-document')) return;
    event.preventDefault();
    const url = location.pathname.replace(/\/$/, '') + '/pdf';
    const context = window.open(url, '_blank');
    if (!context) { location.assign(url); return; }
    context.addEventListener('load', () => {
        try { if (context.document.contentType === 'application/pdf') { context.focus(); context.print(); } } catch { /* Native PDF viewer retains its own print controls. */ }
    }, { once: true });
});
