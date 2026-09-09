import { test, expect } from '@playwright/test';

test('production homepage preserves identity, finite project states, and group focus', async ({ page }) => {
    const errors = [];
    const requests = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => requests.push(request.url()));
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.pin-spacer')).toHaveCount(2);
    const stage = page.locator('[data-home-project-stage]');
    const start = await stage.evaluate(element => element.getBoundingClientRect().top + scrollY);
    const projects = page.locator('[data-home-project]');
    const count = await projects.count();
    expect(count).toBeLessThanOrEqual(3);
    const titles = await projects.locator('h3').allTextContents();
    for (const index of [...Array(count).keys(), 0, count - 1, 0]) {
        await page.evaluate(y => scrollTo(0, y), start + index * 800);
        await expect(page.locator('[data-home-project]:not([inert]) h3')).toHaveText(titles[index]);
    }
    const link = page.locator('[data-home-project]:not([inert]) > a');
    await link.focus();
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    const bounds = await link.boundingBox();
    await link.evaluate(element => element.blur());
    await page.mouse.move(bounds.x + bounds.width - 8, bounds.y + bounds.height - 8);
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    const blog = page.locator('.home-post > a').first();
    await blog.focus();
    await expect(blog.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    await expect(page.locator('a a')).toHaveCount(0);
    await page.evaluate(() => scrollTo(0, 0));
    await expect.poll(async () => (await page.locator('h1').boundingBox()).width).toBeGreaterThan(400);
    expect(requests.some(url => /pdf-viewer-|pdf\.worker/.test(url))).toBe(false);
    expect(errors).toEqual([]);
});

test('mobile and reduced motion retain readable projects and light paper', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.addInitScript(() => localStorage.setItem('jack-theme', 'dark'));
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('.pin-spacer')).toHaveCount(0);
    await expect(page.locator('[data-home-project][inert]')).toHaveCount(0);
    for (const selector of ['.home-person', '.home-projects', '.home-writing', '.home-publications', '.home-now']) {
        await page.locator(selector).scrollIntoViewIfNeeded();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    for (const paper of await page.locator('.home-publication__sheet').all()) {
        await expect(paper).toHaveCSS('background-color', 'rgb(255, 254, 250)');
        await expect(paper.locator('img')).toHaveCSS('object-fit', 'contain');
        const dimensions = await paper.evaluate(element => {
            const box = element.getBoundingClientRect();
            const image = element.querySelector('img');
            return { width: box.width, height: box.height, parentWidth: element.parentElement.getBoundingClientRect().width, portrait: image.naturalHeight > image.naturalWidth };
        });
        expect(dimensions.width).toBeLessThanOrEqual(dimensions.parentWidth + 1);
        if (dimensions.portrait) expect(dimensions.height).toBeGreaterThan(dimensions.width);
        const textFits = await paper.evaluate(element => {
            const box = element.getBoundingClientRect();
            return [...element.querySelectorAll('.home-publication__type, strong, .home-publication__abstract, .home-publication__imprint')].every(child => {
                const bounds = child.getBoundingClientRect();
                return bounds.top >= box.top && bounds.bottom <= box.bottom;
            });
        });
        expect(textFits).toBe(true);
    }
    expect(await page.locator('.home-now__links li').count()).toBeLessThanOrEqual(3);
    await expect(page.locator('.home-now__items')).toHaveCount(0);
});
