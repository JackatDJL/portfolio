const composeAddress = (data) => [
    [data.street, data.house_number].filter(Boolean).join(' '),
    [data.postal_code, data.city].filter(Boolean).join(' '),
    data.country,
].filter(Boolean).join('\n');

export const initCvPrivateData = ({ watch = true } = {}) => {
    const panel = document.querySelector('[data-cv-private-panel]');
    if (!(panel instanceof HTMLElement)) return;

    const retryOnHashChange = () => {
        if (watch) window.addEventListener('hashchange', () => initCvPrivateData(), { once: true });
    };
    const exchange = window.__cvExchange ?? window.__cvExchangeFragment?.() ?? Promise.resolve(true);
    window.__cvExchange = undefined;
    document.documentElement.dataset.cvPrivateState = 'loading';

    const setValue = (name, value, href) => {
        const field = panel.querySelector(`[data-cv-private="${name}"]`);
        if (!(field instanceof HTMLElement) || !value) return;
        if (!href) {
            field.textContent = value;
            return;
        }
        const link = document.createElement('a');
        link.className = 'semantic-link';
        link.href = href;
        link.textContent = value;
        field.replaceChildren(link);
    };

    exchange.then(ok => ok ? fetch(panel.dataset.cvPrivateEndpoint || '/cv/private-data', {
        method: 'POST',
        credentials: 'same-origin',
        referrerPolicy: 'no-referrer',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ path: location.pathname }),
    }) : null)
        .then(response => response?.ok ? response.json() : null)
        .then(data => {
            if (!data) {
                document.documentElement.dataset.cvPrivateState = 'public';
                document.dispatchEvent(new CustomEvent('cv:private-settled'));
                retryOnHashChange();
                return;
            }
            setValue('email', data.private_email, data.private_email ? `mailto:${data.private_email}` : null);
            setValue('phone', data.phone, data.phone ? `tel:${data.phone.replace(/[^+0-9]/g, '')}` : null);
            setValue('address', composeAddress(data));
            document.documentElement.dataset.cvPrivateReady = 'true';
            document.documentElement.dataset.cvPrivateState = 'authorized';
            document.dispatchEvent(new CustomEvent('cv:private-ready'));
            document.dispatchEvent(new CustomEvent('cv:private-settled'));
        })
        .catch(() => {
            document.documentElement.dataset.cvPrivateState = 'failed';
            document.dispatchEvent(new CustomEvent('cv:private-settled'));
            retryOnHashChange();
        });

    const signaturePanel = document.querySelector('[data-cv-private-signature]');
    if (!(signaturePanel instanceof HTMLElement) || signaturePanel.dataset.enabled !== 'true') return;

    exchange.then(ok => ok ? fetch(signaturePanel.dataset.endpoint || '/cv/private-signature', {
        method: 'POST',
        credentials: 'same-origin',
        referrerPolicy: 'no-referrer',
        headers: { 'Content-Type': 'application/json', Accept: 'image/png' },
        body: JSON.stringify({ path: location.pathname }),
    }) : null)
        .then(response => response?.ok ? response.blob() : null)
        .then(blob => {
            if (!(blob instanceof Blob) || blob.type !== 'image/png') return;
            const image = signaturePanel.querySelector('[data-cv-private-signature-image]');
            const pattern = signaturePanel.querySelector('[data-cv-security-pattern]');
            const meta = signaturePanel.querySelector('[data-cv-private-signature-meta]');
            if (!(image instanceof HTMLImageElement)) return;
            if (image.dataset.objectUrl) URL.revokeObjectURL(image.dataset.objectUrl);
            const objectUrl = URL.createObjectURL(blob);
            image.dataset.objectUrl = objectUrl;
            image.hidden = true;
            if (pattern instanceof HTMLElement) pattern.hidden = false;
            if (meta instanceof HTMLElement) meta.hidden = true;
            image.addEventListener('load', () => {
                if (image.dataset.objectUrl !== objectUrl || image.naturalWidth === 0) return;
                image.hidden = false;
                if (pattern instanceof HTMLElement) pattern.hidden = true;
                if (meta instanceof HTMLElement) meta.hidden = false;
                signaturePanel.hidden = false;
            }, { once: true });
            image.addEventListener('error', () => {
                if (image.dataset.objectUrl !== objectUrl) return;
                URL.revokeObjectURL(objectUrl);
                delete image.dataset.objectUrl;
                image.removeAttribute('src');
                image.hidden = true;
                if (meta instanceof HTMLElement) meta.hidden = true;
                if (pattern instanceof HTMLElement) {
                    pattern.hidden = false;
                    signaturePanel.hidden = false;
                } else {
                    signaturePanel.hidden = true;
                }
            }, { once: true });
            image.src = objectUrl;
        })
        .catch(() => {});
};
