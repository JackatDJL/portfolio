import { test, expect } from '@playwright/test';
const cvBaseURL = process.env.CV_BASE_URL || 'http://127.0.0.1:8091';
const routes = ['/cv', '/cv/airbus-26', '/cv/exp/volt-stade-kommunikation', '/cv/exp/erstwaehlerforum-stade-organisation', '/cv/exp/hackclub-stade-organisation', '/cv/exp/atheblues-robotik-und-teamarbeit', '/cv/exp/volt-europa-technische-mitarbeit', '/cv/exp/volt-deutschland-technische-mitarbeit', '/cv/edu/gymnasium-athenaeum-stade', '/projekte/erstwaehlerforum-stade'];
for (const width of [1440, 390]) for (const theme of ['light', 'dark']) {
    test(`${width}px ${theme}: CV pages render without overflow`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
        await page.addInitScript(theme => localStorage.setItem('jack-theme', theme), theme);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const route of routes) {
            const response = await page.goto(route);
            expect(response.status()).toBe(200);
            await page.evaluate(() => document.fonts.ready);
            await expect(page.locator('h1')).toHaveCount(1);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await page.screenshot({ path: testInfo.outputPath(`${route.replaceAll('/', '_')}.png`), fullPage: true });
            if (route === '/cv' || route === '/cv/airbus-26') {
                await expect(page.locator('h1')).toHaveText('Jack Ruder');
                await expect(page.locator('main')).not.toContainText(/Allgemeines Profil|Ausgangsprofil|Preset|für Bewerbungen|Personalisiertes Profil|Mein Lebenslauf für/);
                await expect(page.locator('.cv-record time')).not.toHaveCount(0);
                await expect(page.locator('main')).toContainText('02/2026 – heute');
                await expect(page.locator('main')).toContainText('Seit etwa dem Schuljahr 2021/22');
                await expect(page.locator('main time[datetime="2026-02"]')).toHaveCount(1);
                await expect(page.locator('.cv-record__thread-mark, .cv-record circle')).toHaveCount(0);
                expect(await page.locator('.cv-records--thread').evaluateAll(roots => roots.every(root => getComputedStyle(root, '::before').content === 'none'))).toBe(true);
                const threads = page.locator('[data-cv-thread]');
                await expect(threads).toHaveCount(2);
                await expect(threads.locator(':scope > svg')).toHaveCount(2);
                await expect(threads.locator(':scope > svg > path')).toHaveCount(2);
                await expect(threads.locator(':scope > svg path + *')).toHaveCount(0);
                await expect(page.locator('.cv-contact__mask')).toHaveCount(3);
                expect(await page.locator('[data-cv-private]').allTextContents()).not.toEqual(expect.arrayContaining([expect.stringMatching(/@|PRIVATE-CV/)]));
                expect(await page.locator('.cv-section--timeline .cv-period').evaluateAll(periods => periods.every(period => getComputedStyle(period).whiteSpace === 'nowrap'))).toBe(true);
            }
            if (route === '/cv') {
                await expect(page.locator('.cv-document__recipient')).toHaveCount(0);
            }
            if (route === '/cv/airbus-26') {
                await expect(page.locator('.cv-document__recipient')).toHaveText('Airbus · 2026');
                await expect(page.locator('.cv-document')).toHaveCSS('--cv-accent', '#315c78');
                await expect(page.locator('main')).toContainText('Robotik und Teamarbeit');
                await expect(page.locator('main')).toContainText('Gymnasium Athenaeum Stade');
            }
            if (route.includes('/edu/')) {
                const hero = await page.locator('.education-hero').boundingBox();
                expect(hero.width).toBeGreaterThan(width - 20);
                expect(hero.height).toBeGreaterThan((width === 390 ? 844 : 1000) * .85);
                await expect(page.locator('.asset-attribution')).toContainText('Marvin Ruder');
                await expect(page.locator('.asset-attribution a')).toHaveAttribute('href', 'https://commons.wikimedia.org/w/index.php?curid=30528076');
            }
            if (route.startsWith('/projekte/')) await expect(page.locator('main a[href*="/cv/exp/"]')).toHaveCount(0);
        }
        expect(errors).toEqual([]);
    });
}
test('gallery buttons, keyboard, horizontal wheel, boundaries and reduced motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/projekte/erstwaehlerforum-stade');
    const gallery = page.locator('[data-media-gallery]');
    const track = gallery.locator('.media-gallery__track');
    await gallery.scrollIntoViewIfNeeded();
    await expect(gallery.locator('img')).toHaveCount(33);
    expect(await gallery.locator('img').evaluateAll(images => images.every(img => img.alt && img.loading === 'lazy' && img.width > 0 && img.height > 0))).toBe(true);
    expect((await gallery.boundingBox()).height).toBeLessThan(650);
    await expect(gallery.locator('[data-gallery-previous]')).toBeDisabled();
    await gallery.locator('[data-gallery-next]').click();
    await expect.poll(() => track.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);
    await track.focus();
    await page.keyboard.press('End');
    await expect(gallery.locator('[data-gallery-next]')).toBeDisabled();
    const end = await track.evaluate(el => el.scrollLeft);
    await page.keyboard.press('ArrowLeft');
    await expect.poll(() => track.evaluate(el => el.scrollLeft)).toBeLessThan(end - 10);
    await page.keyboard.press('Home');
    await expect(gallery.locator('[data-gallery-previous]')).toBeDisabled();
    await track.hover();
    await page.mouse.wheel(500, 0);
    await expect.poll(() => track.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);
});
test('authorized private response replaces masks in the same contact fields', async ({ page }) => {
    await page.route('**/cv/private-data', route => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            private_email: 'cv-test@example.invalid',
            phone: '+49 000 000000',
            street: 'Musterstraße',
            house_number: '1',
            postal_code: '21600',
            city: 'Teststadt',
        }),
    }));
    await page.goto('/cv/airbus-26#cv=validtestcapabilitytoken1234567890');
    await expect(page.locator('[data-cv-private="email"]')).toHaveText('cv-test@example.invalid');
    await expect(page.locator('[data-cv-private="phone"]')).toHaveText('+49 000 000000');
    await expect(page.locator('[data-cv-private="address"]')).toContainText('Musterstraße 1');
    await expect(page.locator('.cv-contact__mask')).toHaveCount(0);
    await expect(page).toHaveURL(/\/cv\/airbus-26$/);
});
test('A4 print keeps document text and removes navigation', async ({ page }, testInfo) => {
    await page.goto('/cv');
    await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.cv-document__header')).toHaveCSS('border-bottom-color', 'rgb(0, 0, 0)');
    await expect(page.locator('html')).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await expect(page.locator('html')).toHaveCSS('color-scheme', 'light');
    await expect(page.locator('.site-header')).toBeHidden();
    await expect(page.locator('.site-footer')).toBeHidden();
    await expect(page.locator('h1')).toHaveCSS('font-size', '38.6667px');
    await page.pdf({ path: testInfo.outputPath('cv-a4.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
    await page.screenshot({ path: testInfo.outputPath('cv-print.png'), fullPage: true });
});
test('CV document canvas is constrained on desktop and fluid on smaller screens', async ({ page }, testInfo) => {
    for (const width of [1920, 1440, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/cv');
        await page.evaluate(() => document.fonts.ready);
        await page.locator('.cv-portrait img').waitFor({ state: 'visible' });
        const documentBox = await page.locator('.cv-document').boundingBox();
        expect(documentBox.width).toBeLessThanOrEqual(width >= 1024 ? 882 : width + 1);
        if (width >= 1024) expect(Math.abs(documentBox.x - (width - documentBox.width) / 2)).toBeLessThan(2);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`cv-${width}.png`), fullPage: true });
    }
});
test('mobile gallery responds to native touch swipe', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    await page.goto(`${cvBaseURL}/projekte/erstwaehlerforum-stade`);
    const track = page.locator('.media-gallery__track');
    await track.scrollIntoViewIfNeeded();
    const box = await track.boundingBox();
    const y = box.y + Math.min(150, box.height / 2);
    const client = await context.newCDPSession(page);
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: 330, y }] });
    for (const x of [290, 240, 180, 120, 60]) {
        await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y }] });
        await page.waitForTimeout(30);
    }
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await expect.poll(() => track.evaluate(el => el.scrollLeft)).toBeGreaterThan(100);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await context.close();
});
test('gallery width variants occupy distinct desktop widths', async ({ page }) => {
    await page.goto('/projekte/erstwaehlerforum-stade');
    const widths = await page.evaluate(() => {
        const original = document.querySelector('[data-media-gallery]');
        const prose = document.querySelector('.detail-layout__main > .prose-site');
        return ['reading', 'wide', 'full'].map(mode => {
            const fixture = original.cloneNode(true);
            fixture.className = `media-gallery article-breakout article-breakout--${mode}`;
            prose.append(fixture);
            const width = fixture.getBoundingClientRect().width;
            fixture.remove();
            return width;
        });
    });
    expect(widths[1]).toBeGreaterThan(widths[0] + 100);
    expect(widths[2]).toBeGreaterThan(widths[1] + 100);
});
