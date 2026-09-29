import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { promisify } from 'node:util';
const execFileAsync = promisify(execFile);
const cvBaseURL = process.env.CV_BASE_URL || 'http://127.0.0.1:8091';
const routes = ['/cv', '/cv/jobmesse-26', '/cv/exp/volt-stade-kommunikation', '/cv/exp/erstwaehlerforum-stade-organisation', '/cv/exp/hackclub-stade-organisation', '/cv/exp/atheblues-robotik-und-teamarbeit', '/cv/exp/volt-europa-technische-mitarbeit', '/cv/exp/volt-deutschland-technische-mitarbeit', '/cv/edu/gymnasium-athenaeum-stade', '/projekte/erstwaehlerforum-stade'];
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
            if (route === '/cv' || route === '/cv/jobmesse-26') {
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
            if (route === '/cv/jobmesse-26') {
                await expect(page.locator('.cv-document__recipient')).toHaveText('Jobmesse 2026');
                await expect(page.locator('.cv-document')).toHaveCSS('--cv-accent', '#5adbbd');
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
test('timeline teaser is compact, centered, closeable, in document flow and excluded from print', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/cv');
    const root = page.locator('[data-cv-explore]');
    const open = page.locator('[data-cv-explore-open]');
    const content = page.locator('[data-cv-explore-content]');
    const closed = await page.evaluate(() => {
        const header = document.querySelector('.cv-document__header').getBoundingClientRect();
        const root = document.querySelector('[data-cv-explore]');
        const teaser = root.querySelector('.cv-explore__teaser').getBoundingClientRect();
        const button = root.querySelector('[data-cv-explore-open]').getBoundingClientRect();
        return {
            height: root.getBoundingClientRect().height,
            headerGap: root.getBoundingClientRect().top - header.bottom,
            centered: Math.abs((button.left + button.right) / 2 - (teaser.left + teaser.right) / 2) < 1,
            bodyFollows: root.nextElementSibling === document.querySelector('.cv-document__body'),
            previewCount: root.querySelectorAll('.cv-explore__teaser svg, .cv-explore__teaser i').length,
        };
    });
    expect(closed.height).toBeLessThan(70);
    expect(closed.headerGap).toBeLessThan(24);
    expect(closed.centered).toBe(true);
    expect(closed.bodyFollows).toBe(true);
    expect(closed.previewCount).toBe(0);
    await expect(open).toHaveAttribute('aria-expanded', 'false');
    await expect(content).toBeHidden();
    await expect(root.locator('template[data-cv-milestone]')).toHaveCount(8);
    await page.screenshot({ path: testInfo.outputPath('timeline-closed.png'), fullPage: false });

    await open.click();
    await expect(content).toBeVisible();
    await expect(open).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-cv-timeline-event]')).toHaveCount(8);
    await expect(page.locator('[data-cv-active-detail] .cv-milestone')).toHaveCount(1);
    await expect(page.locator('[data-cv-active-detail] h3')).toBeVisible();
    await page.waitForTimeout(280);
    await page.screenshot({ path: testInfo.outputPath('timeline-first.png'), fullPage: false });
    await page.locator('[data-cv-timeline-event][aria-pressed="true"]').focus();
    await page.keyboard.press('Escape');
    await expect(content).toBeHidden();
    await expect(open).toHaveAttribute('aria-expanded', 'false');
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.cv-explore')).toBeHidden();
});

test('fixed timeline maps the public start year to today at responsive widths without overflow', async ({ page }, testInfo) => {
    const tickCounts = [];
    for (const [width, height] of [[1440, 900], [1024, 768], [768, 900], [390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.emulateMedia({ media: 'screen', reducedMotion: 'reduce' });
        await page.goto('/cv');
        await page.locator('[data-cv-explore-open]').click();
        const layout = await page.evaluate(() => {
            const header = document.querySelector('.cv-document__header').getBoundingClientRect();
            const root = document.querySelector('[data-cv-explore]').getBoundingClientRect();
            const body = document.querySelector('.cv-document__body').getBoundingClientRect();
            const stage = document.querySelector('[data-cv-timeline-stage]');
            const track = document.querySelector('[data-cv-timeline-track]');
            const end = Number(track.dataset.endTimestamp);
            const start = Number(track.dataset.startTimestamp);
            const events = [...stage.querySelectorAll('[data-cv-timeline-event]')];
            const present = stage.querySelector('[data-cv-timeline-present]');
            const presentBox = present.getBoundingClientRect();
            const trackBox = track.getBoundingClientRect();
            const labels = [...stage.querySelectorAll('[data-cv-year-label]'), stage.querySelector('[data-cv-timeline-present] span')];
            const labelBoxes = labels.map(label => label.getBoundingClientRect());
            return {
                headerGap: root.top - header.bottom,
                bodyGap: body.top - root.bottom,
                openHeight: root.height,
                pageOverflow: document.documentElement.scrollWidth > innerWidth,
                trackWidth: track.getBoundingClientRect().width,
                stageWidth: stage.clientWidth,
                stageScrollWidth: stage.scrollWidth,
                markerCount: events.length,
                positions: events.map(event => Number(event.dataset.position)),
                timestamps: events.map(event => Number(event.dataset.timestamp)),
                startYear: Number(track.dataset.startYear),
                endYear: Number(track.dataset.endYear),
                start,
                end,
                present: Number(document.querySelector('[data-cv-timeline-present]').dataset.timestamp),
                presentAtTrackEnd: Math.abs(presentBox.right - trackBox.right) <= 1,
                years: [...stage.querySelectorAll('[data-year]')].map(node => Number(node.dataset.year)),
                labels: labels.map(node => node.textContent.trim()),
                labelsOverlap: labelBoxes.some((box, index) => labelBoxes.slice(index + 1).some(other => box.right > other.left + 1 && other.right > box.left + 1)),
                hasPinSpacer: Boolean(document.querySelector('.pin-spacer')),
                detailOverflow: getComputedStyle(document.querySelector('.cv-explore__detail')).overflowY,
            };
        });
        expect(layout.headerGap).toBeGreaterThanOrEqual(0);
        expect(layout.headerGap).toBeLessThan(24);
        expect(layout.bodyGap).toBeLessThan(2);
        expect(layout.openHeight).toBeLessThan(360);
        expect(layout.pageOverflow).toBe(false);
        expect(layout.trackWidth).toBeLessThanOrEqual(layout.stageWidth + 1);
        expect(layout.stageScrollWidth).toBeLessThanOrEqual(layout.stageWidth + 1);
        expect(layout.markerCount).toBe(8);
        expect(layout.startYear).toBe(2014);
        expect(layout.endYear).toBe(new Date().getFullYear());
        expect(layout.end).toBe(layout.present);
        expect(layout.presentAtTrackEnd).toBe(true);
        expect(layout.present).toBeGreaterThan(Math.max(...layout.timestamps));
        expect(layout.positions).toEqual([...layout.positions].sort((a, b) => a - b));
        expect(layout.positions.every(position => position >= 0 && position <= 1)).toBe(true);
        expect(layout.positions.every((position, index) => Math.abs(position - (layout.timestamps[index] - layout.start) / (layout.end - layout.start)) < 0.000001)).toBe(true);
        expect(layout.years[0]).toBe(2014);
        expect(new Set(layout.years).size).toBe(layout.years.length);
        expect(layout.labelsOverlap).toBe(false);
        expect(layout.hasPinSpacer).toBe(false);
        expect(layout.detailOverflow).toBe('visible');
        tickCounts.push(await page.locator('.cv-timeline__tick').count());
        await page.screenshot({ path: testInfo.outputPath(`timeline-open-${width}.png`), fullPage: width === 390 });
        if (width === 390) await page.screenshot({ path: testInfo.outputPath('timeline-mobile-open.png'), fullPage: false });
        if (width === 1440) {
            const initialTitle = await page.locator('[data-cv-active-detail] h3').textContent();
            await page.locator('[data-cv-timeline-event="4"]').click();
            await expect(page.locator('[data-cv-timeline-event="4"]')).toHaveAttribute('aria-pressed', 'true');
            await expect(page.locator('[data-cv-active-detail] h3')).not.toHaveText(initialTitle);
            await page.waitForTimeout(240);
            await page.screenshot({ path: testInfo.outputPath('timeline-middle.png'), fullPage: false });
            await page.locator('[data-cv-timeline-event="7"]').click();
            await expect(page.locator('[data-cv-timeline-event="7"]')).toHaveAttribute('aria-pressed', 'true');
            await page.waitForTimeout(240);
            await page.screenshot({ path: testInfo.outputPath('timeline-final.png'), fullPage: false });
            await page.locator('[data-cv-timeline-event="0"]').click();
            await expect(page.locator('[data-cv-timeline-event="0"]')).toHaveAttribute('aria-pressed', 'true');
            await page.keyboard.press('ArrowRight');
            await expect(page.locator('[data-cv-timeline-event="1"]')).toHaveAttribute('aria-pressed', 'true');
            await page.keyboard.press('ArrowLeft');
            await expect(page.locator('[data-cv-timeline-event="0"]')).toHaveAttribute('aria-pressed', 'true');
            await page.keyboard.press('End');
            await expect(page.locator('[data-cv-timeline-event="7"]')).toHaveAttribute('aria-pressed', 'true');
            await page.keyboard.press('Home');
            await expect(page.locator('[data-cv-timeline-event="0"]')).toHaveAttribute('aria-pressed', 'true');
            await page.locator('[data-cv-explore-close]').click();
            await expect(page.locator('[data-cv-explore-content]')).toBeHidden();
        }
    }
    expect(Math.abs(tickCounts[0] - tickCounts[1])).toBeLessThanOrEqual(5);
    expect(tickCounts[1]).toBeGreaterThan(tickCounts[2]);
    expect(tickCounts[2]).toBeGreaterThan(tickCounts[3]);
});

test('milestone click, keyboard and wheel thresholds change one stable detail; wheel hands off at both ends', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/cv');
    await page.locator('[data-cv-explore-open]').click();
    const stage = page.locator('[data-cv-timeline-stage]');
    const wheel = async delta => stage.evaluate((element, amount) => {
        const event = new WheelEvent('wheel', { bubbles: true, cancelable: true, deltaY: amount });
        element.dispatchEvent(event);
        return event.defaultPrevented;
    }, delta);
    const active = () => page.locator('[data-cv-timeline-event][aria-pressed="true"]').getAttribute('data-cv-timeline-event');

    await page.locator('[data-cv-timeline-event="0"]').click();
    expect(await active()).toBe('0');
    const firstTitle = await page.locator('[data-cv-active-detail] h3').textContent();
    expect(firstTitle).toBeTruthy();
    await page.locator('[data-cv-timeline-event="0"]').hover();
    await page.screenshot({ path: testInfo.outputPath('portrait-timeline-interaction.png'), fullPage: false });

    expect(await wheel(-120)).toBe(false);
    expect(await active()).toBe('0');
    expect(await wheel(36)).toBe(true);
    expect(await active()).toBe('0');
    expect(await wheel(36)).toBe(true);
    await expect.poll(active).toBe('1');
    await expect(page.locator('[data-cv-active-detail] h3')).not.toHaveText(firstTitle);
    await page.waitForTimeout(400);
    expect(await wheel(160)).toBe(true);
    await expect.poll(active).toBe('2');
    await page.locator('[data-cv-timeline-event="7"]').click();
    await expect.poll(active).toBe('7');
    expect(await wheel(120)).toBe(false);
    expect(await active()).toBe('7');

    await page.locator('[data-cv-timeline-event="0"]').click();
    await page.evaluate(() => window.scrollTo(0, 300));
    await stage.hover();
    const yBeforeStartHandoff = await page.evaluate(() => window.scrollY);
    await page.mouse.wheel(0, -120);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeLessThan(yBeforeStartHandoff);
    await page.locator('[data-cv-timeline-event="7"]').click();
    await page.evaluate(() => window.scrollTo(0, 300));
    await stage.hover();
    const yBeforeEndHandoff = await page.evaluate(() => window.scrollY);
    await page.mouse.wheel(0, 120);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(yBeforeEndHandoff);
    await page.screenshot({ path: testInfo.outputPath('timeline-after-interaction.png'), fullPage: false });
});

test('touch horizontal swipe selects milestones while vertical gestures remain page scrolling', async ({ browser }, testInfo) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    await page.goto(cvBaseURL + '/cv');
    await page.locator('[data-cv-explore-open]').click();
    const stage = page.locator('[data-cv-timeline-stage]');
    await stage.scrollIntoViewIfNeeded();
    const box = await stage.boundingBox();
    const client = await context.newCDPSession(page);
    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x + 75, y }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x - 10, y }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await expect(page.locator('[data-cv-timeline-event="1"]')).toHaveAttribute('aria-pressed', 'true');

    const beforeScroll = await page.evaluate(() => window.scrollY);
    await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y: y + 12 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + 6, y: y - 80 }] });
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await page.waitForTimeout(100);
    await expect(page.locator('[data-cv-timeline-event="1"]')).toHaveAttribute('aria-pressed', 'true');
    expect(await page.evaluate(() => window.scrollY)).toBeGreaterThanOrEqual(beforeScroll);
    await page.screenshot({ path: testInfo.outputPath('timeline-mobile-open.png'), fullPage: false });
    await context.close();
});

test('reduced motion keeps click and keyboard available and does not intercept wheel', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/cv');
    await page.locator('[data-cv-explore-open]').click();
    const stage = page.locator('[data-cv-timeline-stage]');
    const wheelPrevented = await stage.evaluate(element => {
        const event = new WheelEvent('wheel', { bubbles: true, cancelable: true, deltaY: 120 });
        element.dispatchEvent(event);
        return event.defaultPrevented;
    });
    expect(wheelPrevented).toBe(false);
    await page.locator('[data-cv-timeline-event="3"]').click();
    await expect(page.locator('[data-cv-timeline-event="3"]')).toHaveAttribute('aria-pressed', 'true');
    await page.locator('[data-cv-timeline-event="3"]').focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('[data-cv-timeline-event="4"]')).toHaveAttribute('aria-pressed', 'true');
});

test('portrait is not counter-rotated or transformed and selects responsive source sizes cleanly', async ({ page, browser }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/cv');
    const image = page.locator('.cv-portrait img');
    await image.evaluate(img => img.decode());
    const portrait = await page.evaluate(() => {
        const outer = document.querySelector('.cv-portrait');
        const img = outer.querySelector('img');
        const rect = img.getBoundingClientRect();
        return {
            outerTransform: getComputedStyle(outer).transform,
            imageTransform: getComputedStyle(img).transform,
            overflow: getComputedStyle(outer).overflow,
            width: rect.width,
            dpr: devicePixelRatio,
            currentSrc: img.currentSrc,
            sizes: img.sizes,
            srcset: img.srcset,
            sourceWidth: img.naturalWidth,
        };
    });
    expect(portrait.outerTransform).toBe('none');
    expect(portrait.imageTransform).toBe('none');
    expect(portrait.overflow).toBe('clip');
    expect(portrait.sizes).toContain('10.5rem');
    expect(portrait.width).toBeGreaterThan(150);
    expect(portrait.currentSrc).toMatch(/(?:w|width)=480/);
    expect(portrait.sourceWidth).toBeGreaterThanOrEqual(portrait.width);
    await page.screenshot({ path: testInfo.outputPath('portrait-before.png'), fullPage: false });
    await page.locator('[data-cv-explore-open]').click();
    await page.locator('[data-cv-timeline-event="0"]').click();
    await page.keyboard.press('ArrowRight');
    await page.locator('[data-cv-timeline-event="1"]').hover();
    await page.screenshot({ path: testInfo.outputPath('portrait-after-repeated-interaction.png'), fullPage: false });

    const retinaContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 3 });
    const retinaPage = await retinaContext.newPage();
    await retinaPage.goto(cvBaseURL + '/cv');
    const retinaImage = retinaPage.locator('.cv-portrait img');
    await retinaImage.evaluate(img => img.decode());
    const retinaSource = await retinaImage.evaluate(img => ({ currentSrc: img.currentSrc, dpr: devicePixelRatio, cssWidth: img.getBoundingClientRect().width }));
    expect(retinaSource.dpr).toBe(3);
    expect(retinaSource.currentSrc).toMatch(/(?:w|width)=800/);
    expect(retinaSource.cssWidth * retinaSource.dpr).toBeLessThan(800);
    await retinaContext.close();

    const source = await readFile(new URL('../../resources/js/cv-explore.js', import.meta.url), 'utf8');
    expect(source).not.toMatch(/ScrollTrigger|scrollLeft|scrollTo\s*\(/);
    expect(source).not.toContain('date_of_birth');
    expect(await page.locator('.pin-spacer').count()).toBe(0);
});
test('CV detail navigation and sidebar composition use the parent route correctly', async ({ page }) => {
    await page.goto('/cv/exp/atheblues-robotik-und-teamarbeit');
    await expect(page.locator('.cv-chapter-nav a')).toHaveText('← Lebenslauf');
    const [main, rail] = await Promise.all([page.locator('.experience-content > .detail-layout__main').boundingBox(), page.locator('.experience-rail').boundingBox()]);
    expect(rail.x).toBeGreaterThan(main.x + main.width);
    await expect(page.locator('.experience-work, .cv-project-work')).toHaveCount(0);
    await page.goto('/cv/edu/gymnasium-athenaeum-stade');
    await expect(page.locator('.education-hero__back')).toHaveText('← Lebenslauf');
    await expect(page.locator('.education-hero__image')).toHaveCSS('border-radius', '0px');
});
test('profile personalization does not move the fixed header geometry', async ({ page }) => {
    const geometry = async (route) => {
        await page.goto(route);
        await page.evaluate(() => document.fonts.ready);
        return page.evaluate(() => {
            const box = selector => { const rect = document.querySelector(selector).getBoundingClientRect(); return [rect.x, rect.y, rect.width, rect.height]; };
            return { name: box('.cv-document__header h1'), location: box('.cv-document__subtitle'), portrait: box('.cv-portrait'), header: box('.cv-document__header') };
        });
    };
    expect(await geometry('/cv/jobmesse-26')).toEqual(await geometry('/cv'));
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
    await page.goto('/cv/jobmesse-26');
    await expect(page.locator('[data-cv-private="email"]')).toHaveText('cv-test@example.invalid');
    await expect(page.locator('[data-cv-private="phone"]')).toHaveText('+49 000 000000');
    await expect(page.locator('[data-cv-private="address"]')).toContainText('Musterstraße 1');
    await expect(page.locator('.cv-contact__mask')).toHaveCount(0);
    await expect(page).toHaveURL(/\/cv\/jobmesse-26$/);
});
test('canonical LuaLaTeX PDF preserves identity and public masks', async ({ request }, testInfo) => {
    const response = await request.get('/cv/pdf');
    expect(response.ok()).toBe(true);
    expect(response.headers()['content-type']).toContain('application/pdf');
    const pdfPath = testInfo.outputPath('cv-canonical.pdf');
    await (await import('node:fs/promises')).writeFile(pdfPath, await response.body());
    const [{ stdout: text }, { stdout: info }] = await Promise.all([
        execFileAsync('pdftotext', [pdfPath, '-']), execFileAsync('pdfinfo', [pdfPath]),
    ]);
    expect(text).toContain('Jack Ruder');
    expect(text).toContain('Geschützte Angabe');
    expect(text).not.toContain('Interaktiven Zeitstrahl öffnen');
    expect(info).toContain('Pages:           2');
});

test('CV document canvas is constrained on desktop and fluid on smaller screens', async ({ page }, testInfo) => {
    for (const width of [1920, 1440, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/cv');
        await page.evaluate(() => document.fonts.ready);
        await page.locator('.cv-portrait img').waitFor({ state: 'visible' });
        await expect.poll(() => page.locator('.cv-portrait img').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
        await page.locator('.cv-portrait img').evaluate(image => image.decode());
        const documentBox = await page.locator('.cv-document').boundingBox();
        expect(documentBox.width).toBeLessThanOrEqual(width >= 1024 ? 882 : width + 1);
        if (width >= 1024) expect(Math.abs(documentBox.x - (width - documentBox.width) / 2)).toBeLessThan(2);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`cv-${width}.png`), fullPage: true });
    }
});
test('Jobmesse release renders at all target widths with its timeline off', async ({ page }, testInfo) => {
    for (const width of [1920, 1440, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('/cv/jobmesse-26');
        await page.evaluate(() => document.fonts.ready);
        await page.locator('.cv-portrait img').evaluate(image => image.decode());
        await expect(page.locator('[data-cv-explore]')).toHaveCount(0);
        await expect(page.locator('main')).toContainText('Eigenständiges Arbeiten');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`jobmesse-${width}.png`), fullPage: true });
        for (const [kind, route] of [
            ['experience', '/cv/exp/volt-stade-kommunikation'],
            ['education', '/cv/edu/gymnasium-athenaeum-stade'],
        ]) {
            await page.goto(route);
            await page.evaluate(() => document.fonts.ready);
            await expect(page.locator('a.btn-ghost[href="/cv"]')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.screenshot({ path: testInfo.outputPath(`${kind}-${width}.png`), fullPage: true });
        }
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
