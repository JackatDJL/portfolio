import { expect, test } from '@playwright/test';

test('protects the fixed name over dark surfaces and buffers short crossings before the glass state', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => {
        const surface = document.createElement('div');
        surface.className = 'test-dark-surface';
        surface.style.cssText = 'position:fixed;top:0;left:0;width:400px;height:100px;background:#07100c;z-index:1';
        document.body.append(surface);
        window.dispatchEvent(new Event('scroll'));
    });
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
    await name.hover();
    await expect(name).toHaveCSS('color', 'rgb(6, 16, 12)');
    await page.mouse.move(500, 400);
    // A brief light-surface interval must not turn the marker off.
    await page.locator('.test-dark-surface').evaluate(element => {
        element.remove();
        window.dispatchEvent(new Event('scroll'));
    });
    await page.waitForTimeout(80);
    await page.evaluate(() => {
        const surface = document.createElement('div');
        surface.className = 'test-dark-surface';
        surface.style.cssText = 'position:fixed;top:0;left:0;width:400px;height:100px;background:#07100c;z-index:1';
        document.body.append(surface);
        window.dispatchEvent(new Event('scroll'));
    });
    await page.waitForTimeout(350);
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
    await page.locator('.test-dark-surface').evaluate(element => {
        element.remove();
        window.dispatchEvent(new Event('scroll'));
    });
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => scrollTo(0, 760));
    await expect(name).toHaveClass(/name-island/);
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
});

test('rechecks after theme changes and retains a readable hover marker', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => {
        document.body.style.color = '#f5faf6';
        document.documentElement.dataset.theme = 'dark';
    });
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
    await name.hover();
    await expect(name).toHaveCSS('color', 'rgb(6, 16, 12)');
});

test('restores contrast state when returning from glass to a dark surface before the old marker timeout', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await page.evaluate(() => {
        const surface = document.createElement('div');
        surface.className = 'test-dark-surface';
        surface.style.cssText = 'position:fixed;top:0;left:0;width:400px;height:100px;background:#07100c;z-index:1';
        document.body.append(surface);
        window.dispatchEvent(new Event('scroll'));
    });
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');

    await page.evaluate(() => scrollTo(0, 760));
    await expect(name).toHaveClass(/name-island/);
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.waitForTimeout(80);

    await page.evaluate(() => scrollTo(0, 0));
    await expect(name).not.toHaveClass(/name-island/);
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
});

test('protects decorative images excluded from pointer hit testing', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => {
        const image = document.createElement('img');
        image.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="black"/></svg>';
        image.style.cssText = 'position:fixed;top:0;left:0;width:400px;height:200px;pointer-events:none;z-index:1';
        document.querySelector('main').append(image);
        window.dispatchEvent(new Event('scroll'));
    });
    // Make the underlying page light: only the non-hit-testable image creates the risk.
    await page.locator('.test-dark').evaluate(element => element.style.background = '#fbfefa');
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
});

test('glass loads only after the entire header leaves and restores the plain name', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.getByRole('link', { name: 'Jack Ruder', exact: true });
    await expect(name).not.toHaveClass(/name-island/);
    await expect(name.locator('.name-glass-effect')).toBeHidden();
    await expect(name.locator('.glass')).toHaveCount(0);
    const headerBottom = await page.locator('.site-header').evaluate(e => e.getBoundingClientRect().bottom);
    // Keep even a small piece of the header in view.
    await page.evaluate(y => scrollTo(0, y), Math.max(0, headerBottom - 2));
    await expect(name).not.toHaveClass(/name-island/);
    await page.evaluate(() => scrollTo(0, 760));
    await expect(name).toHaveClass(/name-island/);
    await expect(name.locator('.glass')).toHaveCount(1);
    await expect(name.locator('.name-glass-effect')).toHaveCSS('opacity', '1');
    const before = await name.boundingBox();
    await name.hover();
    expect(await name.boundingBox()).toEqual(before);
    await page.evaluate(() => scrollTo(0, 0));
    await expect(name).not.toHaveClass(/name-island/);
    await expect(name.locator('.name-glass-effect')).toHaveCSS('opacity', '0');
    await expect(name.locator('.glass')).toHaveCount(0);
    await expect(name).toHaveAttribute('href', '#');
});

test('glass fades both ways and a reversed scroll cancels delayed unmount', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    await page.evaluate(() => scrollTo(0, 760));
    const surface = page.locator('.name-glass-effect');
    await page.waitForFunction(() => {
        const opacity = Number(getComputedStyle(document.querySelector('.name-glass-effect')).opacity);
        return opacity > 0 && opacity < 1;
    });
    await expect(surface).toHaveCSS('opacity', '1');
    await expect(surface.locator('.glass')).toHaveCount(1);
    await page.evaluate(() => scrollTo(0, 0));
    await page.waitForFunction(() => {
        const opacity = Number(getComputedStyle(document.querySelector('.name-glass-effect')).opacity);
        return opacity > 0 && opacity < 1;
    });
    await expect(surface.locator('.glass')).toHaveCount(1);
    await page.evaluate(() => scrollTo(0, 760));
    await expect(surface).toHaveCSS('opacity', '1');
    await page.waitForTimeout(450);
    await expect(surface.locator('.glass')).toHaveCount(1);
});
