import { test, expect } from '@playwright/test';

async function master(page) {
    await expect(page.locator('.pin-spacer')).toHaveCount(1);
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(async () => {
        const url = performance.getEntriesByType('resource').find(entry => /\/ScrollTrigger-/.test(entry.name)).name;
        const module = await import(url);
        window.homeTestTriggers = Object.values(module).find(value => value.getAll);
    });
    await page.waitForTimeout(250);
}

async function seek(page, label, offset = 0) {
    await page.evaluate(({ label, offset }) => {
        const trigger = homeTestTriggers.getById('home-master');
        trigger.getTween(true)?.kill?.();
        const time = typeof label === 'number' ? label : trigger.animation.labels[label];
        scrollTo(0, trigger.start + (time + offset) / trigger.animation.duration() * (trigger.end - trigger.start));
    }, { label, offset });
    await page.waitForTimeout(600);
}

const visibleStates = () => {
    const visible = element => {
        const box = element.getBoundingClientRect();
        let opacity = 1;
        for (let parent = element; parent; parent = parent.parentElement) {
            const css = getComputedStyle(parent);
            if (css.visibility === 'hidden') return false;
            opacity *= Number(css.opacity);
        }
        return opacity > .05 && box.left < innerWidth && box.right > 0 && box.top < innerHeight && box.bottom > 0;
    };
    return {
        photos: [...document.querySelectorAll('[data-stream-clone] img')].filter(visible).length,
        projects: [...document.querySelectorAll('[data-home-project]')].filter(visible).length,
        heading: visible(document.querySelector('#projects-title')),
    };
};

test('one master owns the opening, with a moving tail and controlled handoff', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (['warning', 'error'].includes(message.type())) errors.push(message.text()); });
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    await master(page);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('h1')).toHaveCount(1);
    expect(await page.evaluate(() => homeTestTriggers.getAll().map(t => t.vars.id))).toEqual(['home-master']);
    await seek(page, .8);
    const attached = await page.locator('[data-home-thread-path]').evaluate(path => {
        const point = path.getPointAtLength(0).matrixTransform(path.getScreenCTM());
        const word = document.querySelector('[data-home-name] > span').getBoundingClientRect();
        return Math.abs(point.x - word.right);
    });
    expect(attached).toBeLessThan(2);
    await seek(page, 1.3);
    const tail = await page.locator('[data-home-thread-path]').evaluate(path => parseFloat(path.style.strokeDashoffset));
    expect(tail).toBeLessThan(0);
    await seek(page, 2.6);
    await expect(page.locator('[data-home-thread-path]')).toHaveCSS('visibility', 'hidden');
    const name = await page.locator('h1').boundingBox();
    expect(name.y).toBeLessThan(45);
    expect(name.height).toBeLessThan(55);
    for (const time of [5.7, 6, 6.3, 6.6, 6.85, 7, 7.2, 7.5, 8.7, 8.85, 8.95, 9.1, 10.3, 10.45, 10.55, 10.7]) {
        await seek(page, time);
        const state = await page.evaluate(visibleStates);
        expect(state.projects).toBeLessThanOrEqual(2);
        if (state.photos > 0) expect(state.projects).toBeLessThanOrEqual(1);
        if (time >= 6.3) expect(state.heading || state.photos > 0 || state.projects > 0).toBe(true);
        if (time >= 7.5) expect(state.photos).toBe(0);
    }
    for (const index of [1, 2, 3, 2, 1]) {
        await seek(page, `project-${index}`);
        expect((await page.evaluate(visibleStates)).projects).toBe(1);
        await expect(page.locator('[data-home-project]:not([inert]) h3')).toHaveText(['prtop↗', 'AtheBlues↗', 'ai-ctx↗'][index - 1]);
    }
    expect(errors).toEqual([]);
});

test('entry arrows stay visible and only section headings reveal on focus', async ({ page }) => {
    await page.goto('/'); await master(page); await seek(page, 'project-1');
    const link = page.locator('[data-home-project]:not([inert]) .home-project__copy');
    await expect(link.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
    const bounds = await link.boundingBox();
    await page.mouse.move(bounds.x + bounds.width - 8, bounds.y + bounds.height - 8);
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    await page.locator('[data-home-project]:not([inert]) .home-project__artifact').hover();
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', /^0(?:px|%) 100%$/);
    for (const [id, href] of [['projects', '/projekte'], ['writing', '/blog'], ['publications', '/publikationen'], ['now', '/aktuell']]) {
        const heading = page.locator(`#${id}-title a`);
        await expect(heading).toHaveAttribute('href', href);
        await expect(heading.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '0');
        await heading.focus();
        await expect(heading.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
        await expect(heading.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
        await heading.evaluate(element => element.blur());
    }
    const post = page.locator('.home-post > a').first();
    await expect(post.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
    await post.focus();
    await expect(post.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    await expect(page.locator('.home-publications > a')).toHaveClass(/related-reference/);
    await expect(page.locator('.home-now__content .home-now__cta')).toHaveCount(1);
    await expect(page.locator('a a')).toHaveCount(0);
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


test('gallery source count never changes pacing, and every image exits by project 1', async ({ browser }) => {
    const measurements = [];
    for (const count of [3, 5, 8, 12]) {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        await page.route('http://127.0.0.1:8010/', async route => {
            const response = await route.fetch();
            const html = (await response.text()).replace(/(<div class="home-gallery"[^>]*>)([\s\S]*?)(<\/div>)/, (_, start, body, end) => {
                const photos = body.match(/<figure[\s\S]*?<\/figure>/g);
                return start + Array.from({ length: count - 1 }, (_, i) => photos[i % photos.length]).join('') + end;
            });
            await route.fulfill({ response, body: html });
        });
        await page.goto('http://127.0.0.1:8010/'); await master(page);
        await expect(page.locator('[data-stream-clone]')).toHaveCount(8);
        await seek(page, 'gallery');
        const before = await page.locator('[data-stream-clone]').first().boundingBox();
        await page.mouse.wheel(0, 120); await page.waitForTimeout(500);
        const after = await page.locator('[data-stream-clone]').first().boundingBox();
        const distance = Math.abs(after.x - before.x);
        expect(distance).toBeLessThan(120);
        measurements.push(await page.evaluate(() => {
            const t = homeTestTriggers.getById('home-master');
            return { distance: t.end - t.start, duration: t.animation.duration() };
        }));
        await seek(page, 'project-1');
        expect((await page.evaluate(visibleStates)).photos).toBe(0);
        await page.close();
    }
    expect(new Set(measurements.map(value => JSON.stringify(value))).size).toBe(1);
});

test('refresh, resize, and restored scroll keep one pin and permanent bumps', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/'); await master(page);
    for (const time of [.8, 6.9, 9.1]) {
        await seek(page, time);
        await page.evaluate(() => homeTestTriggers.refresh());
        await page.waitForTimeout(400);
        await expect(page.locator('.pin-spacer')).toHaveCount(1);
        const paths = await page.locator('[data-project-bump]').evaluateAll(nodes => nodes.map(node => node.getAttribute('d')));
        expect(new Set(paths).size).toBe(3);
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.waitForTimeout(400);
        await expect(page.locator('.pin-spacer')).toHaveCount(1);
        await page.setViewportSize({ width: 1440, height: 1000 });
    }
    await seek(page, 'project-2');
    await page.reload(); await master(page);
    await expect(page.locator('[data-home-project]:not([inert])')).toHaveCount(1);
    for (const width of [1440, 1280, 1586, 820, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(500);
        for (const id of ['writing', 'publications', 'now']) {
            await page.locator(`#${id}-title`).scrollIntoViewIfNeeded();
            await page.waitForTimeout(200);
            const offset = await page.evaluate(id => {
                const heading = document.querySelector(`#${id}-title`).getBoundingClientRect();
                const path = document.querySelector('[data-home-thread] > path:last-child');
                const y = heading.top + heading.height / 2;
                let nearest = { distance: Infinity, x: 0 };
                for (let d = 0; d < path.getTotalLength(); d += 3) {
                    const point = path.getPointAtLength(d).matrixTransform(path.getScreenCTM());
                    if (Math.abs(point.y - y) < nearest.distance) nearest = { distance: Math.abs(point.y - y), x: point.x };
                }
                return nearest.x - (heading.left - (innerWidth < 768 ? 23 : 38));
            }, id);
            expect(offset).toBeGreaterThan(width < 768 ? 5 : 16);
            expect(errors).toEqual([]);
        }
    }
});

test('native snap approaches projects in both directions without catching other phases', async ({ page }) => {
    await page.goto('/'); await master(page);
    for (const time of [.8, 3.4]) {
        await seek(page, time);
        const actual = await page.evaluate(() => homeTestTriggers.getById('home-master').animation.time());
        expect(actual).toBeCloseTo(time, 1);
    }
    await seek(page, 'project-1');
    await seek(page, 'project-2', -.25);
    await expect.poll(() => page.evaluate(() => homeTestTriggers.getById('home-master').animation.time())).toBeCloseTo(9.8, 1);
    await seek(page, 'project-3');
    await seek(page, 'project-3', -.75);
    await expect.poll(() => page.evaluate(() => homeTestTriggers.getById('home-master').animation.time())).toBeCloseTo(9.8, 1);
    const before = await page.evaluate(() => scrollY);
    await page.mouse.wheel(0, 120); await page.waitForTimeout(700);
    expect(await page.evaluate(() => scrollY)).toBeGreaterThan(before + 100);
    await page.locator('#writing-title').scrollIntoViewIfNeeded();
    const blogScroll = await page.evaluate(() => scrollY);
    await page.waitForTimeout(700);
    expect(await page.evaluate(() => scrollY)).toBe(blogScroll);
});


test('words converge directly and project titles align with their own fixed bumps', async ({ page }) => {
    await page.goto('/'); await master(page);
    let previousGap = Infinity;
    for (const time of [.5, .8, 1.1, 1.4, 1.65, 1.85]) {
        await seek(page, time);
        const [jack, ruder] = await page.locator('[data-home-name] > span').evaluateAll(words => words.map(word => { const r = word.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width }; }));
        expect(ruder.x).toBeGreaterThan(jack.x);
        expect(ruder.y - jack.y).toBeLessThanOrEqual(previousGap + .1);
        if (time >= 1.65) expect(Math.abs(ruder.y - jack.y)).toBeLessThan(5);
        previousGap = ruder.y - jack.y;
    }
    for (const index of [0, 1, 2, 1, 0]) {
        await seek(page, `project-${index + 1}`);
        const project = page.locator('[data-home-project]').nth(index);
        await expect(project).toHaveCSS('opacity', '1');
        const title = await project.locator('h3').boundingBox();
        const bumpY = await page.locator('[data-project-bump]').nth(index).evaluate(path => { const box = path.getBBox(); return box.y + box.height / 2; });
        expect(Math.abs(title.y + title.height / 2 - bumpY)).toBeLessThan(2);
    }
});
