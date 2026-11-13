import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const script = readFileSync(fileURLToPath(new URL('../../resources/js/cv-private.js', import.meta.url)), 'utf8');

const pageMarkup = `
    <aside data-cv-private-panel data-cv-private-endpoint="/cv/private-data"></aside>
    <section data-cv-private-signature data-enabled="true" data-endpoint="/cv/private-signature">
        <div data-cv-security-pattern><svg aria-label="Synthetisches Linienmotiv"></svg></div>
        <img data-cv-private-signature-image hidden>
        <div data-cv-private-signature-meta hidden><p>Musterstadt, 6. November 2026</p><p>Erika Muster</p></div>
    </section>`;

const initializePage = async (page) => {
    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent(pageMarkup);
    await page.evaluate(() => { window.__cvExchange = Promise.resolve(true); });
    await page.addScriptTag({ type: 'module', content: `${script}\ninitCvPrivateData();` });
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
    await page.route('**/cv/private-signature', route => route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('synthetic-png-fixture') }));
    await initializePage(page);

    await expect(page.locator('[data-cv-private-signature-image]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-image]')).toHaveAttribute('src', /^blob:/);
    await expect(page.locator('[data-cv-security-pattern]')).toBeHidden();
    await expect(page.locator('[data-cv-private-signature-meta]')).toBeVisible();
    await expect(page.locator('[data-cv-private-signature-meta]')).toContainText('Musterstadt');
    await expect(page.locator('[data-cv-private-signature-meta]')).toContainText('Erika Muster');
});
