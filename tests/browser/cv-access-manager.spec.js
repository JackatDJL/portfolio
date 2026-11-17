import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const read = path => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');

test('application CV grant responses and revoke actions stay bound to the selected profile', async ({ page }) => {
    const applicationScript = read('../../public/cp-applications.js');
    const label = 'Bewerbungs Lebenslauf für 2027-001';
    let releaseProfileA;
    const pendingProfileA = new Promise(resolve => { releaseProfileA = resolve; });
    let profileARequestStarted = false;
    let revokedRequest;

    await page.route('**/cp/applications/application-1/cv-profile**', route => {
        const profile = new URL(route.request().url()).searchParams.get('profile');
        return route.fulfill({ json: profile === 'profile-b'
            ? { selected: true, slug: 'profile-b', title: 'Profil B', cv_link_path: '/cv/profile-b', cv_link_label: label }
            : { selected: true, slug: 'profile-a', title: 'Profil A', cv_link_path: '/cv/profile-a', cv_link_label: label } });
    });
    await page.route('**/cp/cv/private-link/grants**', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() === 'DELETE') {
            revokedRequest = { path: url.searchParams.get('path'), id: url.pathname.split('/').at(-1) };
            return route.fulfill({ json: { revoked: true } });
        }
        const path = url.searchParams.get('path');
        if (path === '/cv/profile-a') {
            profileARequestStarted = true;
            const grants = await pendingProfileA;
            return route.fulfill({ json: { path, grants } });
        }
        return route.fulfill({ json: { path, grants: [{ id: 22, label, kind: 'permanent', created_at: '2026-11-14T12:00:00Z', expires_at: null, revoked_at: null }] } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent(`
        <meta name="csrf-token" content="fixture-csrf">
        <div data-application-actions>
            <input type="hidden" name="cv[profile]" value="profile-a">
            <div data-application-summary></div>
            <div data-application-profile-summary></div>
            <section data-application-cv-grant data-path="/cv/profile-a" data-label="${label}">
                <p data-application-cv-grant-state></p>
                <input data-application-cv-grant-url value="https://example.invalid/cv/profile-a#cv=old-secret">
                <div data-application-cv-grant-url-wrap></div>
                <p data-application-cv-grant-status></p>
                <div data-application-cv-grant-list></div>
            </section>
        </div>`);
    await page.evaluate(() => history.replaceState({}, '', '/cp/collections/applications/entries/application-1/edit'));
    await page.addScriptTag({ content: applicationScript });
    await expect.poll(() => profileARequestStarted).toBe(true);

    const urlField = page.locator('[data-application-cv-grant-url]');
    const wrapper = page.locator('[data-application-cv-grant-url-wrap]');
    await urlField.evaluate(node => { node.value = 'https://example.invalid/cv/profile-a#cv=old-secret'; });
    await wrapper.evaluate(node => { node.hidden = false; });
    const profile = page.locator('input[name="cv[profile]"]');
    await profile.evaluate(node => {
        node.value = 'profile-b';
        node.dispatchEvent(new Event('input', { bubbles: true }));
        node.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await expect(urlField).toHaveValue('');
    await expect(wrapper).toBeHidden();
    await expect(page.locator('[data-application-cv-grant-revoke="22"]')).toBeVisible();

    releaseProfileA([{ id: 11, label, kind: 'permanent', created_at: '2026-11-14T12:00:00Z', expires_at: null, revoked_at: null }]);
    await expect(page.locator('[data-application-cv-grant-list] [data-application-cv-grant-revoke]')).toHaveCount(1);
    await expect(page.locator('[data-application-cv-grant-revoke="11"]')).toHaveCount(0);
    page.once('dialog', dialog => dialog.accept());
    await page.locator('[data-application-cv-grant-revoke="22"]').click();
    await expect.poll(() => revokedRequest).toEqual({ path: '/cv/profile-b', id: '22' });
});

test('CV profile manager prevents duplicate create requests while a grant is pending', async ({ page }) => {
    const profileAccessScript = read('../../public/cp-cv-profile-access.js');
    let releasePost;
    let postCount = 0;
    let postStarted;
    const pendingPost = new Promise(resolve => { releasePost = resolve; });
    const postStartedPromise = new Promise(resolve => { postStarted = resolve; });
    await page.route('**/cp/cv/private-link/grants**', async route => {
        if (route.request().method() === 'GET') return route.fulfill({ json: { path: '/cv/profile-a', grants: [] } });
        postCount++;
        postStarted();
        await pendingPost;
        return route.fulfill({ json: { created: true, url: 'https://example.invalid/cv/profile-a#cv=one-time-secret', grant: { id: 42 } } });
    });

    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent(`
        <meta name="csrf-token" content="fixture-csrf">
        <section data-cv-named-grants>
            <input data-cv-grant-label value="Bewerbungs Lebenslauf für 2027-001">
            <select data-cv-grant-kind><option value="permanent" selected>Permanent</option></select>
            <button type="button" data-cv-grant-create>Erstellen</button>
            <div data-cv-grant-url-wrap hidden><input data-cv-grant-url><button type="button" data-cv-grant-copy>Kopieren</button></div>
            <ul data-cv-grant-list></ul>
            <p data-cv-grant-status></p>
        </section>`);
    await page.evaluate(() => history.replaceState({}, '', '/cp/collections/cv_profiles/entries/profile-a/edit'));
    await page.addScriptTag({ content: profileAccessScript });

    const button = page.locator('[data-cv-grant-create]');
    await button.click();
    await postStartedPromise;
    await expect(button).toBeDisabled();
    await button.evaluate(node => node.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    await expect.poll(() => postCount).toBe(1);
    releasePost();
    await expect(button).toBeEnabled();
    await expect(page.locator('[data-cv-grant-url-wrap]')).toBeVisible();
    expect(postCount).toBe(1);
});

test('named CV grant fields stack their labels and align controls at the bottom', async ({ page }) => {
    const accessStyles = read('../../public/cp-cv-profile-access.css');
    await page.setViewportSize({ width: 975, height: 780 });
    await page.goto('/tests/browser/fixtures/article-toc.html');
    await page.setContent(`
        <section data-cv-named-grants>
            <h3>Bewerbungslinks</h3>
            <p>Ein neuer Link wird nur einmal angezeigt.</p>
            <div class="flex flex-wrap items-end gap-2">
                <label>Bezeichnung<input data-cv-grant-label></label>
                <label>Art<select data-cv-grant-kind><option>Dauerhaft</option></select></label>
                <button data-cv-grant-create>Link erstellen</button>
            </div>
        </section>`);
    await page.addStyleTag({ content: accessStyles + '.flex{display:flex}.flex-wrap{flex-wrap:wrap}.items-end{align-items:flex-end}.gap-2{gap:.5rem}' });

    const geometry = await page.evaluate(() => {
        const label = document.querySelector('[data-cv-grant-label]');
        const select = document.querySelector('[data-cv-grant-kind]');
        const button = document.querySelector('[data-cv-grant-create]');
        const labelRect = document.querySelector('[data-cv-named-grants] label').getBoundingClientRect();
        return {
            labelTop: labelRect.top,
            inputTop: label.getBoundingClientRect().top,
            selectTop: select.getBoundingClientRect().top,
            buttonBottom: button.getBoundingClientRect().bottom,
            fieldBottom: label.getBoundingClientRect().bottom,
        };
    });

    expect(geometry.inputTop).toBeGreaterThan(geometry.labelTop);
    expect(geometry.selectTop).toBeGreaterThan(geometry.labelTop);
    expect(Math.abs(geometry.buttonBottom - geometry.fieldBottom)).toBeLessThan(2);
});
