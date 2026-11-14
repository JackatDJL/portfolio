import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const script = readFileSync(fileURLToPath(new URL('../../resources/js/cv-private.js', import.meta.url)), 'utf8');
const validPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pWQAAAAASUVORK5CYII=', 'base64');

const pageMarkup = `
    <aside data-cv-private-panel data-cv-private-endpoint="/cv/private-data"></aside>
    <section data-cv-private-signature data-enabled="true" data-endpoint="/cv/private-signature">
        <div data-cv-security-pattern><svg aria-label="Synthetisches Linienmotiv"></svg></div>
        <span data-cv-private-signature-text hidden>Erika Muster</span>
        <img data-cv-private-signature-image style="width:46mm;height:16mm" hidden>
        <div data-cv-private-signature-meta hidden><p>Musterstadt, 6. November 2026</p><p>Erika Muster</p></div>
    </section>`;

const initializePage = async (page, { waitForSignatureError = false } = {}) => {
    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent(pageMarkup);
    await page.evaluate(() => { window.__cvExchange = Promise.resolve(true); });
    if (waitForSignatureError) {
        await page.evaluate(() => {
            const image = document.querySelector('[data-cv-private-signature-image]');
            window.__cvPrivateSignatureError = new Promise(resolve => image.addEventListener('error', resolve, { once: true }));
            window.__cvRevokedPrivateUrls = [];
            const revokeObjectURL = URL.revokeObjectURL.bind(URL);
            URL.revokeObjectURL = url => {
                window.__cvRevokedPrivateUrls.push(url);
                revokeObjectURL(url);
            };
        });
    }
    await page.addScriptTag({ type: 'module', content: `${script}\ninitCvPrivateData();` });
    if (waitForSignatureError) await page.evaluate(() => window.__cvPrivateSignatureError);
};

test('keeps the real signature hidden when the private endpoint denies access', async ({ page }) => {
    await page.route('**/cv/private-data', route => route.fulfill({ json: {} }));
    await page.route('**/cv/private-signature', route => route.fulfill({ status: 404 }));
    await initializePage(page);

    await expect(page.locator('[data-cv-security-pattern]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-image]')).toBeHidden();
    await expect(page.locator('[data-cv-private-signature-image]')).not.toHaveAttribute('src', /.+/);
    await expect(page.locator('[data-cv-private-signature-meta]')).toBeHidden();
});

test('replaces the synthetic pattern only after the private endpoint returns an image', async ({ page }) => {
    await page.route('**/cv/private-data', route => route.fulfill({ json: {} }));
    await page.route('**/cv/private-signature', route => route.fulfill({ status: 200, contentType: 'image/png', body: validPng }));
    await initializePage(page);

    const image = page.locator('[data-cv-private-signature-image]');
    await expect(image).toHaveAttribute('src', /^blob:/);
    await expect.poll(() => image.evaluate(element => element.naturalWidth)).toBe(1);
    await expect(image).toBeVisible();
    await expect(page.locator('[data-cv-security-pattern]')).toBeHidden();
    await expect(page.locator('[data-cv-private-signature-meta]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-meta]')).toContainText('Musterstadt');
    await expect(page.locator('[data-cv-private-signature-meta]')).toContainText('Erika Muster');
});

test('keeps the synthetic pattern when the private signature image cannot be decoded', async ({ page }) => {
    await page.route('**/cv/private-data', route => route.fulfill({ json: {} }));
    await page.route('**/cv/private-signature', route => route.fulfill({
        status: 200,
        contentType: 'image/png',
        body: Buffer.from('not a PNG image'),
    }));
    await initializePage(page, { waitForSignatureError: true });

    const image = page.locator('[data-cv-private-signature-image]');
    await expect(image).not.toHaveAttribute('src', /.+/);
    await expect(image).not.toHaveAttribute('data-object-url', /.+/);
    await expect.poll(() => page.evaluate(() => window.__cvRevokedPrivateUrls.length)).toBe(1);
    await expect(image).toBeHidden();
    await expect(page.locator('[data-cv-security-pattern]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-meta]')).toBeHidden();
});

test('uses the handwriting font name when authorized typed mode is selected', async ({ page }) => {
    await page.route('**/cv/private-data', route => route.fulfill({ json: { signature_mode: 'typed', signature_name: 'Erika Signatur' } }));
    await page.route('**/cv/private-signature', route => route.fulfill({ status: 404 }));
    await initializePage(page);
    await expect(page.locator('[data-cv-private-signature-text]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-text]')).toContainText('Erika Signatur');
    await expect(page.locator('[data-cv-private-signature-image]')).toBeHidden();
    await expect(page.locator('[data-cv-security-pattern]')).toBeHidden();
});
