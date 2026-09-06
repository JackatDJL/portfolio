import { expect, test } from '@playwright/test';

test('selects a lower visible heading after the active upper heading leaves', async ({ page }) => {
    await page.goto('/tests/browser/fixtures/article-toc.html');

    await page.evaluate(() => {
        window.articleTocObserver.trigger([
            { target: document.querySelector('#upper'), isIntersecting: true, boundingClientRect: { top: 10 } },
            { target: document.querySelector('#lower'), isIntersecting: true, boundingClientRect: { top: 100 } },
        ]);
    });
    await expect(page.locator('a[href="#upper"]')).toHaveAttribute('aria-current', 'true');

    await page.evaluate(() => {
        window.articleTocObserver.trigger([
            { target: document.querySelector('#upper'), isIntersecting: false, boundingClientRect: { top: -100 } },
        ]);
    });
    await expect(page.locator('a[href="#lower"]')).toHaveAttribute('aria-current', 'true');
});
