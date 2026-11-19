import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const script = readFileSync(fileURLToPath(new URL('../../public/cp-content-sync.js', import.meta.url)), 'utf8');

const markup = () => `
    <section data-content-sync data-status-url="/cp/content-sync/status" data-start-url="/cp/content-sync" data-conflicts-url="/cp/content-sync/conflicts" data-resolve-url="/cp/content-sync/conflicts/resolve">
        <p data-sync-state>Wird geladen</p>
        <span data-sync-countdown>–</span>
        <button data-sync-start>Jetzt synchronisieren</button>
        <pre data-sync-logs style="height: 100px; overflow: auto"></pre>
        <button data-sync-conflicts-open hidden>Konflikte prüfen</button>
        <section data-sync-conflicts hidden>
            <p data-sync-conflict-message hidden></p>
            <div data-sync-conflict-list></div>
            <button data-sync-conflicts-reload>Neu laden</button>
            <button data-sync-resolve disabled>Validieren und fortsetzen</button>
        </section>
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
    await expect(root.locator('[data-sync-start]')).toBeEnabled({ timeout: 6000 });
    await root.locator('[data-sync-start]').click();
    await expect.poll(() => startCalls).toBe(1);
});

test('log polling preserves the scroll position when reading earlier output', async ({ page }) => {
    let statusCalls = 0;
    const olderLogs = Array.from({ length: 60 }, (_, index) => `Log line ${index}`).join('\n');
    let logs = olderLogs;
    await page.route('**/cp/content-sync/status', route => {
        statusCalls++;
        return route.fulfill({ json: {
            running: true,
            started_at: '2026-11-17T12:00:00Z',
            next_run_at: new Date(Date.now() + 120_000).toISOString(),
            logs,
        } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const logPanel = page.locator('[data-sync-logs]');
    await expect.poll(() => statusCalls).toBeGreaterThan(0);
    await logPanel.evaluate(element => { element.scrollTop = 0; });
    logs += '\nNew log line';
    await page.waitForTimeout(2100);

    await expect(logPanel).toContainText('New log line');
    await expect.poll(() => logPanel.evaluate(element => element.scrollTop)).toBe(0);
});

test('status polling serializes slow requests and refreshes after a manual start', async ({ page }) => {
    let statusCalls = 0;
    let startCalls = 0;
    let activeRequests = 0;
    let maxActiveRequests = 0;
    let releaseFirstResponse;
    const firstResponse = new Promise(resolve => { releaseFirstResponse = resolve; });
    await page.route('**/cp/content-sync/status', async route => {
        statusCalls++;
        activeRequests++;
        maxActiveRequests = Math.max(maxActiveRequests, activeRequests);
        try {
            if (statusCalls === 1) {
                await firstResponse;
                return await route.fulfill({ json: {
                    running: false,
                    started_at: null,
                    next_run_at: new Date(Date.now() + 120_000).toISOString(),
                    logs: 'Earlier idle status',
                } });
            }
            return await route.fulfill({ json: {
                running: true,
                started_at: '2026-11-17T12:00:00Z',
                next_run_at: new Date(Date.now() + 120_000).toISOString(),
                logs: 'Current running status',
            } });
        } finally {
            activeRequests--;
        }
    });
    await page.route('**/cp/content-sync', route => {
        startCalls++;
        return route.fulfill({ status: 202, json: { started: true } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    try {
        const root = page.locator('[data-content-sync]');
        await expect.poll(() => statusCalls).toBe(1);
        await root.locator('[data-sync-start]').click();
        await expect.poll(() => startCalls).toBe(1);
        await page.waitForTimeout(2100);
        expect(statusCalls).toBe(1);
        expect(maxActiveRequests).toBe(1);

        releaseFirstResponse();

        await expect(root.locator('[data-sync-state]')).toContainText('Lauf aktiv seit');
        await expect(root.locator('[data-sync-logs]')).toContainText('Current running status');
        await expect(root.locator('[data-sync-start]')).toBeDisabled();
        expect(statusCalls).toBeGreaterThanOrEqual(2);
        expect(maxActiveRequests).toBe(1);
    } finally {
        releaseFirstResponse();
    }
});

test('conflicts are resolved per section and protected CV values stay out of status and requests', async ({ page }) => {
    const privateValue = 'PRIVATE-CV-EMAIL-SENTINEL@example.invalid';
    let submitted;
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: 'Kandidatenkonflikt erkannt.',
        conflicts: { busy: false, merge_in_progress: true, files: [
            { path: 'content/collections/entry.md', sections: 2, binary: false },
            { path: 'content/globals/cv.yaml', sections: 1, binary: false },
            { path: 'public/documents/obsolete.pdf', sections: 0, binary: true },
        ] },
    } }));
    await page.route('**/cp/content-sync/conflicts', route => route.fulfill({ json: {
        available: true,
        fingerprint: 'a'.repeat(64),
        files: [
            { path: 'content/collections/entry.md', binary: false, sections: [
                { id: 0, base: 'title: Original', ours: 'title: Local', theirs: 'title: Incoming', protected: false },
                { id: 1, base: null, ours: 'body: Local body', theirs: 'body: Incoming body', protected: false },
            ] },
            { path: 'content/globals/cv.yaml', binary: false, sections: [
                { id: 0, base: null, ours: '[Geschützte CV-Werte werden nicht angezeigt.]', theirs: '[Geschützte CV-Werte werden nicht angezeigt.]', protected: true },
            ] },
            { path: 'public/documents/obsolete.pdf', binary: true, ours_available: true, theirs_available: false, ours_deletes: false, theirs_deletes: true, sections: [] },
        ],
    } }));
    await page.route('**/cp/content-sync/conflicts/resolve', async route => {
        submitted = route.request().postDataJSON();
        await route.fulfill({ status: 202, json: { resolved: true, started: false } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await expect(root.locator('[data-sync-state]')).toContainText('3 Konfliktdatei');
    await expect(root.locator('[data-sync-start]')).toBeDisabled();
    await expect(root.locator('[data-sync-logs]')).not.toContainText(privateValue);
    await root.locator('[data-sync-conflicts-open]').click();
    await expect(root.locator('[data-sync-conflict-list]')).toContainText('Geschützte CV-Werte bleiben verborgen');
    await expect(root.locator('[data-sync-conflict-list]')).not.toContainText(privateValue);

    const decisions = root.locator('[data-sync-conflict-list] select');
    await decisions.nth(0).selectOption('theirs');
    await decisions.nth(1).selectOption('manual');
    const manual = root.locator('[data-sync-conflict-list] textarea').nth(1);
    await manual.fill('body: merged manually');
    await decisions.nth(2).selectOption('ours');
    await decisions.nth(3).selectOption('theirs');
    await root.locator('[data-sync-resolve]').click();

    await expect.poll(() => submitted).toBeTruthy();
    expect(submitted.resolutions).toEqual([
        { path: 'content/collections/entry.md', sections: [
            { id: 0, choice: 'theirs' },
            { id: 1, choice: 'manual', text: 'body: merged manually' },
        ] },
        { path: 'content/globals/cv.yaml', sections: [{ id: 0, choice: 'ours' }] },
        { path: 'public/documents/obsolete.pdf', choice: 'theirs' },
    ]);
    expect(JSON.stringify(submitted)).not.toContain(privateValue);
});

test('a busy lock keeps the resolver draft until explicit reload', async ({ page }) => {
    let statusCalls = 0;
    let detailCalls = 0;
    await page.route('**/cp/content-sync/status', route => {
        statusCalls++;
        return route.fulfill({ json: {
            running: false,
            started_at: null,
            next_run_at: new Date(Date.now() + 120_000).toISOString(),
            logs: 'Sync-Lock-Test.',
            conflicts: statusCalls === 1
                ? { busy: false, merge_in_progress: true, files: [{ path: 'content/collections/entry.md', sections: 1, binary: false }] }
                : { busy: true, merge_in_progress: false, files: [] },
        } });
    });
    await page.route('**/cp/content-sync/conflicts', route => {
        detailCalls++;
        return route.fulfill({ json: {
            available: true,
            fingerprint: 'b'.repeat(64),
            files: [{ path: 'content/collections/entry.md', binary: false, sections: [
                { id: 0, base: null, ours: 'body: Local', theirs: 'body: Incoming', protected: false },
            ] }],
        } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await root.locator('[data-sync-conflicts-open]').click();
    await expect(root.locator('[data-sync-conflict-list] select')).toHaveCount(1);
    await root.locator('[data-sync-conflict-list] select').selectOption('manual');
    const manual = root.locator('[data-sync-conflict-list] textarea');
    await manual.fill('body: draft to preserve');

    await expect(root.locator('[data-sync-state]')).toContainText('Sync-Lock belegt', { timeout: 6000 });
    await expect(manual).toHaveValue('body: draft to preserve');
    await expect(root.locator('[data-sync-conflict-list] select')).toHaveValue('manual');
    await expect(root.locator('[data-sync-resolve]')).toBeDisabled();

    await root.locator('[data-sync-conflicts-reload]').click();
    await expect.poll(() => detailCalls).toBe(2);
    await expect(root.locator('[data-sync-conflict-list] textarea')).toHaveValue('body: Local');
});

test('file mode conflicts are resolved per text file', async ({ page }) => {
    let submitted;
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: '',
        conflicts: { busy: false, merge_in_progress: true, files: [{ path: 'content/collections/entry.md', sections: 1, binary: false }] },
    } }));
    await page.route('**/cp/content-sync/conflicts', route => route.fulfill({ json: {
        available: true,
        fingerprint: 'c'.repeat(64),
        files: [{ path: 'content/collections/entry.md', binary: false, mode_conflict: true, ours_executable: false, theirs_executable: true, sections: [
            { id: 0, base: null, ours: 'body: Local', theirs: 'body: Incoming', protected: false },
        ] }],
    } }));
    await page.route('**/cp/content-sync/conflicts/resolve', async route => {
        submitted = route.request().postDataJSON();
        await route.fulfill({ status: 202, json: { resolved: true, started: false } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await root.locator('[data-sync-conflicts-open]').click();
    const decisions = root.locator('[data-sync-conflict-list] select');
    await expect(decisions).toHaveCount(2);
    await decisions.nth(0).selectOption('theirs');
    await decisions.nth(1).selectOption('ours');
    await root.locator('[data-sync-resolve]').click();

    await expect.poll(() => submitted).toBeTruthy();
    expect(submitted.resolutions).toEqual([{
        path: 'content/collections/entry.md',
        sections: [{ id: 0, choice: 'ours' }],
        mode_choice: 'theirs',
    }]);
});

test('resolution stays disabled while its request is pending', async ({ page }) => {
    let resolved = false;
    let resolveCalls = 0;
    let releaseRequest;
    const pendingRequest = new Promise(resolve => { releaseRequest = resolve; });
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: '',
        conflicts: resolved
            ? { busy: false, merge_in_progress: false, files: [] }
            : { busy: false, merge_in_progress: true, files: [{ path: 'content/collections/entry.md', sections: 1, binary: false }] },
    } }));
    await page.route('**/cp/content-sync/conflicts', route => route.fulfill({ json: {
        available: true,
        fingerprint: 'e'.repeat(64),
        files: [{ path: 'content/collections/entry.md', binary: false, sections: [
            { id: 0, base: null, ours: 'local', theirs: 'incoming', protected: false },
        ] }],
    } }));
    await page.route('**/cp/content-sync/conflicts/resolve', async route => {
        resolveCalls++;
        await pendingRequest;
        resolved = true;
        await route.fulfill({ status: 202, json: { resolved: true, started: false } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name=\"csrf-token\" content=\"fixture-csrf\"><main id=\"cp-content\"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await root.locator('[data-sync-conflicts-open]').click();
    const select = root.locator('[data-sync-conflict-list] select');
    await select.selectOption('theirs');
    const resolveButton = root.locator('[data-sync-resolve]');
    await resolveButton.click();
    await expect.poll(() => resolveCalls).toBe(1);
    await expect(resolveButton).toBeDisabled();

    await select.selectOption('ours');
    await expect(resolveButton).toBeDisabled();
    await resolveButton.evaluate(button => button.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    expect(resolveCalls).toBe(1);

    releaseRequest();
    await expect(root.locator('[data-sync-start]')).toBeEnabled();
});

test('mode-only conflicts offer a mode choice and preserve a resolved sync-start failure', async ({ page }) => {
    let resolved = false;
    let submitted;
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: '',
        conflicts: resolved
            ? { busy: false, merge_in_progress: false, files: [] }
            : { busy: false, merge_in_progress: true, files: [{ path: 'content/collections/mode-only.md', sections: 0, binary: false }] },
    } }));
    await page.route('**/cp/content-sync/conflicts', route => route.fulfill({ json: {
        available: true,
        fingerprint: 'd'.repeat(64),
        files: [{ path: 'content/collections/mode-only.md', binary: false, mode_conflict: true, mode_only_conflict: true, ours_executable: false, theirs_executable: true, sections: [] }],
    } }));
    await page.route('**/cp/content-sync/conflicts/resolve', async route => {
        submitted = route.request().postDataJSON();
        resolved = true;
        await route.fulfill({ status: 202, json: {
            resolved: true,
            started: false,
            start_failed: true,
            message: 'Konflikt aufgelöst. Die Synchronisierung konnte nicht gestartet werden; bitte manuell erneut starten.',
        } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await root.locator('[data-sync-conflicts-open]').click();
    await expect(root.locator('[data-sync-conflict-list]')).toContainText('Der Dateiinhalt ist auf beiden Seiten gleich');
    await expect(root.locator('[data-sync-conflict-list] select')).toHaveCount(1);
    await expect(root.locator('[data-sync-conflict-list] textarea')).toHaveCount(0);
    await root.locator('[data-sync-conflict-list] select').selectOption('theirs');
    await expect(root.locator('[data-sync-resolve]')).toBeEnabled();
    await root.locator('[data-sync-resolve]').click();

    await expect.poll(() => submitted).toBeTruthy();
    expect(submitted.resolutions).toEqual([{
        path: 'content/collections/mode-only.md',
        sections: [],
        mode_choice: 'theirs',
    }]);
    await expect(root.locator('[data-sync-state]')).toContainText('Konflikt aufgelöst. Die Synchronisierung konnte nicht gestartet werden');
    await expect(root.locator('[data-sync-start]')).toBeEnabled();
});

test('an incomplete candidate rollback requires manual inspection and disables sync', async ({ page }) => {
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: '',
        conflicts: { busy: false, merge_in_progress: true, recovery_required: true, files: [] },
    } }));

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await expect(root.locator('[data-sync-state]')).toContainText('manuell geprüft werden');
    await expect(root.locator('[data-sync-start]')).toBeDisabled();
    await expect(root.locator('[data-sync-conflicts-open]')).toBeHidden();
});

test('a later healthy candidate status clears the recovery-required state', async ({ page }) => {
    let statusCalls = 0;
    await page.route('**/cp/content-sync/status', route => {
        statusCalls++;
        return route.fulfill({ json: {
            running: false,
            started_at: null,
            next_run_at: new Date(Date.now() + 120_000).toISOString(),
            logs: '',
            conflicts: {
                busy: false,
                merge_in_progress: statusCalls === 1,
                recovery_required: statusCalls === 1,
                files: [],
            },
        } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await expect(root.locator('[data-sync-state]')).toContainText('manuell geprüft werden');
    await expect(root.locator('[data-sync-start]')).toBeDisabled();
    await expect(root.locator('[data-sync-start]')).toBeEnabled({ timeout: 5000 });
    await expect(root.locator('[data-sync-state]')).toContainText('Kein Lauf aktiv');
});

test('an unavailable candidate is not shown as a busy lock', async ({ page }) => {
    await page.route('**/cp/content-sync/status', route => route.fulfill({ json: {
        running: false,
        started_at: null,
        next_run_at: new Date(Date.now() + 120_000).toISOString(),
        logs: '',
        conflicts: { busy: false, merge_in_progress: false, unavailable: true, files: [] },
    } }));

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent('<meta name="csrf-token" content="fixture-csrf"><main id="cp-content"></main>');
    await page.addScriptTag({ content: script });
    await page.evaluate(markup => { document.querySelector('#cp-content').innerHTML = markup; }, markup());

    const root = page.locator('[data-content-sync]');
    await expect(root.locator('[data-sync-state]')).toContainText('Kandidatenstatus nicht verfügbar');
    await expect(root.locator('[data-sync-state]')).not.toContainText('Sync-Lock belegt');
    await expect(root.locator('[data-sync-start]')).toBeDisabled();
    await expect(root.locator('[data-sync-conflicts-open]')).toBeHidden();
});
