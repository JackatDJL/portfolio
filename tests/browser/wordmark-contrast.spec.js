import { expect, test } from '@playwright/test';

test('protects the fixed name over dark surfaces and buffers short crossings', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => scrollTo(0, 760));
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
    await name.hover();
    await expect(name).toHaveCSS('color', 'rgb(6, 16, 12)');
    await page.mouse.move(500, 400);
    // A brief excursion into the light section must not turn the marker off.
    await page.evaluate(() => scrollTo(0, 1500));
    await page.waitForTimeout(80);
    await page.evaluate(() => scrollTo(0, 760));
    await page.waitForTimeout(350);
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
    await page.evaluate(() => scrollTo(0, 1500));
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

test('protects decorative images excluded from pointer hit testing', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html');
    const name = page.locator('.site-header__wordmark');
    await expect(name).toHaveAttribute('data-contrast-marker', 'false');
    await page.evaluate(() => {
        const image = document.createElement('img');
        image.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="black"/></svg>';
        image.style.cssText = 'position:absolute;top:720px;left:0;width:400px;height:200px;pointer-events:none';
        document.querySelector('main').append(image);
    });
    // Make the underlying section light before scrolling: only the image creates the risk.
    await page.locator('.test-dark').evaluate(element => element.style.background = '#fbfefa');
    await page.evaluate(() => scrollTo(0, 760));
    await expect(name).toHaveAttribute('data-contrast-marker', 'true');
});

test('glass loads only after the entire header leaves and restores the plain name', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/wordmark-contrast.html?name=island');
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
    const before = await name.boundingBox();
    await name.hover();
    expect(await name.boundingBox()).toEqual(before);
    await page.evaluate(() => scrollTo(0, 0));
    await expect(name).not.toHaveClass(/name-island/);
    await expect(name.locator('.glass')).toHaveCount(0);
    await expect(name).toHaveAttribute('href', '#');
});
