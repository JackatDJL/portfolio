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
    const link = page.locator('[data-home-project]:not([inert]) .home-project__copy');
    await link.focus();
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    const bounds = await link.boundingBox();
    await link.evaluate(element => element.blur());
    await page.mouse.move(bounds.x + bounds.width - 8, bounds.y + bounds.height - 8);
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    await page.locator('[data-home-project]:not([inert]) .home-project__artifact').hover();
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', /^0(?:px|%) 100%$/);
    await link.focus();
    await expect(link.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
    expect(await link.locator('.related-reference__arrow').evaluate(el => parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThan(30);
    const blog = page.locator('.home-post > a').first();
    await blog.focus();
    await expect(blog.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    for (const [id, href] of [['projects', '/projekte'], ['writing', '/blog'], ['publications', '/publikationen']]) {
        const heading = page.locator(`#${id}-title a`);
        await expect(heading).toHaveAttribute('href', href);
        await heading.focus();
        await expect(heading.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
    }
    await expect(page.locator('.home-publications > a')).toHaveClass(/related-reference/);
    await expect(page.locator('.home-now__content .home-now__cta')).toHaveCount(1);
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
        await expect(paper.locator('img')).toHaveCSS('object-fit', 'cover');
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

test('gallery count does not extend the opening and every repeated photo leaves', async ({ browser }) => {
    const heights = [];
    for (const count of [3, 5, 10]) {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        await page.route('http://127.0.0.1:8010/', async route => {
            const response = await route.fetch();
            const html = (await response.text()).replace(/(<div class="home-gallery"[^>]*>)([\s\S]*?)(<\/div>)/, (_, start, body, end) => {
                const photos = body.match(/<figure[\s\S]*?<\/figure>/g);
                return start + Array.from({ length: count - 1 }, (_, i) => photos[i % photos.length]).join('') + end;
            });
            await route.fulfill({ response, body: html });
        });
        await page.goto('http://127.0.0.1:8010/');
        await expect(page.locator('.pin-spacer')).toHaveCount(2);
        await page.evaluate(() => document.fonts.ready);
        heights.push(await page.locator('.pin-spacer').first().evaluate(el => el.offsetHeight));
        expect(await page.locator('[data-stream-clone]').count()).toBeLessThanOrEqual(12);
        await page.evaluate(() => scrollTo(0, 1740));
        await expect.poll(() => page.locator('[data-stream-clone]').evaluateAll(elements => elements.filter(el => {
            const r = el.getBoundingClientRect();
            return r.left < innerWidth && r.right > 0 && r.top < innerHeight && r.bottom > 0;
        }).length)).toBe(0);
        await page.close();
    }
    expect(new Set(heights).size).toBe(1);
});

test('major section bumps stay attached through scrolling and resize', async ({ page }) => {
    await page.goto('/');
    await page.locator('#writing-title').scrollIntoViewIfNeeded();
    for (const width of [1440, 1280, 820, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(400);
        for (const id of ['writing', 'publications', 'now']) {
            await page.locator(`#${id}-title`).scrollIntoViewIfNeeded();
            await page.waitForTimeout(100);
            const offset = await page.evaluate(id => {
                const svg = document.querySelector('[data-home-thread]');
                const heading = document.querySelector(`#${id}-title`).getBoundingClientRect();
                const y = heading.top + heading.height / 2 - svg.getBoundingClientRect().top;
                const path = svg.querySelector('path:last-child');
                let nearest = { distance: Infinity, x: 0 };
                for (let d = 0; d < path.getTotalLength(); d += 4) {
                    const point = path.getPointAtLength(d);
                    if (Math.abs(point.y - y) < nearest.distance) nearest = { distance: Math.abs(point.y - y), x: point.x };
                }
                const rail = heading.left - (innerWidth < 768 ? 23 : 38);
                return nearest.x - rail;
            }, id);
            expect(offset).toBeGreaterThan(width < 768 ? 5 : 16);
        }
    }
});
