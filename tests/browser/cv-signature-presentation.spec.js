import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const read = path => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');
const patternScript = read('../../resources/js/cv-signature-pattern.js');
const exchangeScript = read('../../resources/views/layout.antlers.html').match(/<script>([\s\S]*?)<\/script>/)[1];

test('animates twenty independent decorative layers and respects reduced motion', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.clock.install();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setContent('<svg data-signature-pattern viewBox="0 0 720 180"><path stroke="#315CA8" d="M0 0L100 100"/></svg>');
    await page.addScriptTag({ type: 'module', content: patternScript });
    const layers = page.locator('[data-signature-pattern] > g');
    await expect(layers).toHaveCount(20);
    const first = await layers.first().locator('path').first().getAttribute('d');
    await page.clock.fastForward(4000);
    expect(await layers.first().locator('path').first().getAttribute('d')).toBe(first);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await expect.poll(() => layers.first().locator('path').first().getAttribute('d')).not.toBe(first);
    const offsets = await layers.evaluateAll(nodes => nodes.map(node => {
        const values = node.getAttribute('transform').match(/-?\d+(?:\.\d+)?/g).map(Number);
        const matrix = node.ownerSVGElement.getScreenCTM();
        return [values[0] * Math.hypot(matrix.a, matrix.b), values[1] * Math.hypot(matrix.c, matrix.d)];
    }));
    for (const offset of offsets.flat()) expect(Math.abs(offset)).toBeLessThanOrEqual(10);

});

for (const detail of ['/cv/edu/example', '/cv/exp/example', '/projekte/example']) {
    test(`private PDF detail link exchanges only the exact profile scope at ${detail}`, async ({ page }) => {
        const token = 'X'.repeat(64);
        let exchanged;
        await page.route('**/cv/token-exchange', route => {
            exchanged = route.request().postDataJSON();
            return route.fulfill({ json: { authorized: true } });
        });
        await page.route(`**${detail}?*`, route => route.fulfill({ contentType: 'text/html', body: `<script>${exchangeScript}</script><a href="/cv/jobmesse-26">Lebenslauf</a>` }));
        await page.goto(`${detail}?cv_return=%2Fcv%2Fjobmesse-26#cv=${token}`);
        await expect.poll(() => exchanged).toEqual({ path: '/cv/jobmesse-26', token });
        expect(new URL(page.url()).hash).toBe('');
        expect(new URL(page.url()).search).not.toContain(token);
        await expect(page.locator('a')).toHaveAttribute('href', '/cv/jobmesse-26');
    });
}
