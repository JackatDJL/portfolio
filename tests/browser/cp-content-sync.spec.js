import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const script = readFileSync(fileURLToPath(new URL('../../public/cp-content-sync.js', import.meta.url)), 'utf8');

const markup = () => `
    <section data-content-sync data-status-url="/cp/content-sync/status" data-start-url="/cp/content-sync">
        <p data-sync-state>Wird geladen</p>
        <span data-sync-countdown>–</span>
        <button data-sync-start>Jetzt synchronisieren</button>
        <pre data-sync-logs></pre>
    </section>`;

test('content sync controls bind after CP mounting and poll recent logs', async ({ page }) => {
    let statusCalls = 0;
    let startCalls = 0;
    let running = true;
    await page.route('**/cp/content-sync/status', route => {
        statusCalls++;
        return route.fulfill({ json: {
            running,
            started_at: running ? '2026-11-17T12:00:00Z' : null,
            next_run_at: new Date(Date.now() + 120_000).toISOString(),
            logs: 'Lauf gestartet.\nGit fetch läuft.',
        } });
    });
    await page.route('**/cp/content-sync', route => {
        startCalls++;
        return route.fulfill({ status: 202, json: { started: true } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await expect(root.locator('[data-sync-state]')).toContainText('Lauf aktiv seit');
    await expect(root.locator('[data-sync-logs]')).toContainText('Git fetch läuft.');
    await expect(root.locator('[data-sync-countdown]')).not.toHaveText('–');
    await expect.poll(() => statusCalls).toBeGreaterThan(0);
    await expect(root.locator('[data-sync-start]')).toBeDisabled();
    running = false;
    await expect(root.locator('[data-sync-start]')).toBeEnabled({ timeout: 3000 });
    await root.locator('[data-sync-start]').click();
    await expect.poll(() => startCalls).toBe(1);
});
