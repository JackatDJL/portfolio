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

test('projects use independent reversible motion and native document scrolling', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/'); await master(page);
    const triggers = await page.evaluate(() => homeTestTriggers.getAll().map(t => ({ id: t.vars.id, pin: !!t.pin, snap: !!t.vars.snap })));
    expect(triggers).toEqual([
        { id: 'home-master', pin: true, snap: false },
        ...[1, 2, 3].map(i => ({ id: `home-project-${i}`, pin: false, snap: false })),
    ]);
    await expect(page.locator('.pin-spacer [data-home-project]')).toHaveCount(0);
    await expect(page.locator('[data-home-project][inert], [data-home-project][aria-hidden], [data-home-project].is-active')).toHaveCount(0);
    for (const index of [0, 1, 2, 1, 0]) {
        for (const progress of [.04, .5, .96, .5]) {
            await page.evaluate(({ index, progress }) => {
                const t = homeTestTriggers.getById(`home-project-${index + 1}`);
                scrollTo(0, t.start + (t.end - t.start) * progress);
            }, { index, progress });
            await page.waitForTimeout(80);
            const copy = page.locator('.home-project__copy').nth(index);
            const opacity = Number(await copy.evaluate(e => getComputedStyle(e).opacity));
            if (progress === .5) {
                expect(opacity).toBe(1);
                await expect(copy).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 0, 0)');
            } else expect(opacity).toBeLessThan(1);
        }
        const before = await page.evaluate(() => scrollY);
        await page.mouse.wheel(0, 73); await page.waitForTimeout(350);
        expect(await page.evaluate(() => scrollY)).toBeCloseTo(before + 73, 0);
        const stopped = await page.evaluate(() => scrollY);
        await page.waitForTimeout(600);
        expect(await page.evaluate(() => scrollY)).toBe(stopped);
    }
    await page.mouse.wheel(0, 2800); await page.waitForTimeout(150);
    await page.mouse.wheel(0, -2800); await page.waitForTimeout(150);
    expect(errors).toEqual([]);
});

test('entry arrows stay visible and only section headings reveal on focus', async ({ page }) => {
    await page.goto('/'); await master(page); await page.locator('.home-project__copy').first().scrollIntoViewIfNeeded();
    const link = page.locator('.home-project__copy').first();
    await expect(link.locator('.related-reference__arrow-mask')).toHaveCSS('opacity', '1');
    const bounds = await link.boundingBox();
    await page.mouse.move(bounds.x + bounds.width - 8, bounds.y + bounds.height - 8);
    await expect(link.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
    await page.locator('.home-project__artifact').first().hover();
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

test('thread bumps follow real headings through refresh and breakpoints', async ({ page }) => {
    await page.goto('/'); await master(page);
    for (const width of [1440, 1280, 820, 390, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(400);
        await page.evaluate(() => homeTestTriggers.refresh());
        const headings = page.locator('[data-home-heading], [data-home-project] h3');
        for (const heading of await headings.all()) {
            await heading.evaluate(element => {
                let top = 0;
                for (let node = element; node; node = node.offsetParent) top += node.offsetTop;
                scrollTo(0, top - innerHeight / 2);
            });
            await page.waitForTimeout(100);
            const offset = await heading.evaluate(h => {
                const box = h.getBoundingClientRect();
                const path = document.querySelector('[data-home-thread] > g:last-child path');
                const y = box.top + box.height / 2;
                let nearest = { distance: Infinity, x: 0 };
                const matrix = path.getScreenCTM();
                for (const vertex of path.getAttribute('d').matchAll(/[ML]([\d.-]+),([\d.-]+)/g)) {
                    const point = new DOMPoint(Number(vertex[1]), Number(vertex[2])).matrixTransform(matrix);
                    if (Math.abs(point.y - y) < nearest.distance) nearest = { distance: Math.abs(point.y - y), x: point.x };
                }
                const rail = document.querySelector('#projects-title').getBoundingClientRect().left - (innerWidth < 768 ? 23 : 38);
                return nearest.x - rail;
            });
            expect(offset).toBeGreaterThan(7);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        }
        const styles = await page.locator('.home-thread path').evaluateAll(paths => paths.map(p => ({ stroke: getComputedStyle(p).stroke, opacity: getComputedStyle(p).opacity })));
        expect(new Set(styles.map(s => s.stroke)).size).toBe(1);
        expect(styles.every(s => s.opacity === '1')).toBe(true);
        expect(await page.evaluate(() => homeTestTriggers.getAll().filter(t => /^home-project-/.test(t.vars.id)).length)).toBe(3);
    }
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.waitForTimeout(300);
    await expect(page.locator('.pin-spacer')).toHaveCount(0);
    expect(await page.evaluate(() => homeTestTriggers.getAll().length)).toBe(0);
    for (const copy of await page.locator('.home-project__copy').all()) {
        await expect(copy).toHaveCSS('opacity', '1');
        await expect(copy).toHaveCSS('transform', 'none');
    }
});


test('photo exit overlaps the natural Projects entry and Blog needs no release', async ({ page }) => {
    await page.goto('/'); await master(page);
    await seek(page, 7);
    const handoff = await page.evaluate(visibleStates);
    expect(handoff.photos).toBeGreaterThan(0);
    expect(handoff.heading).toBe(true);
    await seek(page, 7.5);
    expect((await page.evaluate(visibleStates)).photos).toBe(0);
    await page.locator('#writing-title').scrollIntoViewIfNeeded();
    const before = await page.evaluate(() => scrollY);
    await page.mouse.wheel(0, 87); await page.waitForTimeout(500);
    expect(await page.evaluate(() => scrollY)).toBeCloseTo(before + 87, 0);
    const position = await page.evaluate(() => scrollY);
    await page.waitForTimeout(400);
    expect(await page.evaluate(() => scrollY)).toBe(position);
});
