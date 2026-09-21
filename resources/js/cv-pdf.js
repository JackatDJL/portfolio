const waitForPrivateState = () => new Promise((resolve) => {
    if (document.documentElement.dataset.cvPrivateState && document.documentElement.dataset.cvPrivateState !== 'loading') {
        resolve();
        return;
    }
    document.addEventListener('cv:private-settled', resolve, { once: true });
});

export const initCvPdf = () => {
    const button = document.querySelector('[data-cv-pdf]');
    const status = document.querySelector('[data-cv-pdf-status]');
    if (!(button instanceof HTMLButtonElement)) return;

    button.addEventListener('click', async () => {
        button.disabled = true;
        if (status) status.textContent = 'PDF wird erstellt …';

        try {
            await waitForPrivateState();
            const response = await fetch('/cv/pdf', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/pdf',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ path: button.dataset.cvPdfPath || location.pathname, token: window.__cvCapability || '' }),
            });
            if (!response.ok) throw new Error(`PDF export failed (${response.status})`);

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'Jack-Ruder-Lebenslauf.pdf';
            link.click();
            URL.revokeObjectURL(url);
            if (status) status.textContent = 'PDF erstellt';
        } catch {
            if (status) status.textContent = 'PDF konnte nicht erstellt werden';
        } finally {
            button.disabled = false;
        }
    });
};
