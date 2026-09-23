import { expect, test } from '@playwright/test';

const detailPages = [
    { name: 'blog', path: '/blog/warum-ich-projekte-zu-codeberg-verschiebe', hasToc: true },
    { name: 'publication', path: '/publikationen/breaking-free-from-big-tech', hasToc: true },
    { name: 'experience', path: '/cv/exp/atheblues-robotik-und-teamarbeit', hasToc: true },
    { name: 'education', path: '/cv/edu/gymnasium-athenaeum-stade', hasToc: true },
];

const viewports = [
    { name: '1440', width: 1440, height: 1000 },
    { name: '1024', width: 1024, height: 768 },
    { name: '768', width: 768, height: 900 },
    { name: '390', width: 390, height: 844 },
];

test('CV document links keep the shared semantic-link and arrow treatment', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    for (const path of ['/cv', '/cv/jobmesse-26']) {
        await page.goto(path);
        const experience = page.locator('.cv-document__career a[href="/cv/exp/atheblues-robotik-und-teamarbeit"]');
        const education = page.locator('.cv-document__career a[href="/cv/edu/gymnasium-athenaeum-stade"]');
        await expect(experience.locator('.semantic-link')).toHaveText('Robotik und Teamarbeit');
        await expect(education.locator('.semantic-link')).toBeVisible();

        for (const titleLink of [experience, education]) {
            await expect(titleLink).toHaveClass(/related-reference--text/);
            await expect(titleLink).toHaveAttribute('data-related-reference', '');
            await expect(titleLink.locator('.related-reference__arrow-mask')).toBeVisible();
            await expect(titleLink.locator('.link-icon--md')).toBeVisible();
        }

        const linkAndThreadColors = await experience.evaluate((link) => ({
            underline: getComputedStyle(link.querySelector('.semantic-link')).textDecorationColor,
            arrow: getComputedStyle(link.querySelector('.related-reference__arrow')).color,
            thread: getComputedStyle(document.querySelector('.cv-records--thread > svg path')).stroke,
        }));
        expect(linkAndThreadColors.underline).toBe(linkAndThreadColors.thread);
        expect(linkAndThreadColors.arrow).toBe(linkAndThreadColors.thread);

        const longTitle = page.locator('.cv-document__career a[href*="projektwoche-nachhaltige-webentwicklung"]');
        await expect(longTitle.locator('.semantic-link')).toHaveText('Projektwoche Nachhaltige Webentwicklung');
        await expect(longTitle.locator('.related-reference__arrow-mask')).toBeVisible();
        await expect(longTitle).toContainText('Projektwoche Nachhaltige Webentwicklung');

        await longTitle.hover();
        await expect(longTitle.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');
        await longTitle.focus();
        await expect(longTitle.locator('.semantic-link')).toHaveCSS('background-size', '100% 100%');

        const wrappedArrow = await longTitle.evaluate((link) => {
            const text = link.querySelector('.semantic-link');
            const arrow = link.querySelector('.related-reference__arrow-mask');
            const range = document.createRange();
            range.selectNodeContents(text);
            const textLines = [...range.getClientRects()];
            const arrowRect = arrow.getBoundingClientRect();
            const lastLine = textLines.at(-1);
            return {
                lineCount: textLines.length,
                arrowLeft: arrowRect.left,
                arrowCenterY: arrowRect.top + arrowRect.height / 2,
                lastLineTop: lastLine?.top ?? 0,
                lastLineRight: lastLine?.right ?? 0,
                lastLineBottom: lastLine?.bottom ?? 0,
            };
        });
        expect(wrappedArrow.lineCount).toBeGreaterThan(0);
        expect(wrappedArrow.arrowLeft).toBeGreaterThanOrEqual(wrappedArrow.lastLineRight - 1);
        expect(wrappedArrow.arrowLeft).toBeLessThanOrEqual(wrappedArrow.lastLineRight + 12);
        expect(wrappedArrow.arrowCenterY).toBeGreaterThanOrEqual(wrappedArrow.lastLineTop - 2);
        expect(wrappedArrow.arrowCenterY).toBeLessThanOrEqual(wrappedArrow.lastLineBottom + 2);

        if (path === '/cv/jobmesse-26') {
            const website = page.locator('.cv-contact__links a[href="https://jack.djl.foundation"]');
            const publication = page.locator('.cv-section a[href^="/publikationen/"]').first();
            await expect(website.locator('.semantic-link')).toHaveText('Website');
            await expect(website.locator('.related-reference__arrow-mask')).toBeVisible();
            await expect(publication.locator('.semantic-link')).toBeVisible();
            await expect(publication.locator('.related-reference__arrow-mask')).toBeVisible();
        }
    }

    await page.goto('/cv/exp/atheblues-robotik-und-teamarbeit');
    const relatedProject = page.locator('.experience-rail a[href^="/projekte/"]').first();
    const publicSource = page.locator('.experience-rail a[href^="https://athenetz.de/"]').first();
    await expect(relatedProject.locator('.semantic-link')).toHaveText('AtheBlues');
    await expect(relatedProject.locator('.semantic-link')).toBeVisible();
    await expect(relatedProject.locator('.related-reference__arrow-mask')).toBeVisible();
    await expect(publicSource.locator('.semantic-link')).toBeVisible();
    await expect(publicSource.locator('.related-reference__arrow-mask')).toBeVisible();
    await expect(page.locator('.experience-organisation a')).toHaveCount(0);
    const shortArrowPosition = await relatedProject.evaluate((link) => {
        const range = document.createRange();
        range.selectNodeContents(link.querySelector('.semantic-link'));
        const line = range.getClientRects().item(0);
        const arrow = link.querySelector('.related-reference__arrow-mask').getBoundingClientRect();
        return { lineCount: range.getClientRects().length, arrowLeft: arrow.left, arrowCenterY: arrow.top + arrow.height / 2, lineTop: line.top, lineRight: line.right, lineBottom: line.bottom };
    });
    expect(shortArrowPosition.lineCount).toBe(1);
    expect(shortArrowPosition.arrowLeft).toBeGreaterThanOrEqual(shortArrowPosition.lineRight - 1);
    expect(shortArrowPosition.arrowLeft).toBeLessThanOrEqual(shortArrowPosition.lineRight + 12);
    expect(shortArrowPosition.arrowCenterY).toBeGreaterThanOrEqual(shortArrowPosition.lineTop - 2);
    expect(shortArrowPosition.arrowCenterY).toBeLessThanOrEqual(shortArrowPosition.lineBottom + 2);

    await page.goto('/cv/edu/gymnasium-athenaeum-stade');
    await page.setViewportSize({ width: 1024, height: 768 });
    const educationProject = page.locator('.detail-layout__rail a[href^="/projekte/"]').first();
    await expect(educationProject.locator('.semantic-link')).toBeVisible();
    await expect(educationProject.locator('.related-reference__arrow-mask')).toBeVisible();
    const wrappedProject = page.locator('.detail-layout__rail a[href*="projektwoche-nachhaltige-webentwicklung"]');
    await expect(wrappedProject.locator('.semantic-link')).toHaveText('Projektwoche Nachhaltige Webentwicklung');
    const wrappedProjectArrow = await wrappedProject.evaluate((link) => {
        const range = document.createRange();
        range.selectNodeContents(link.querySelector('.semantic-link'));
        const lines = [...range.getClientRects()];
        const arrow = link.querySelector('.related-reference__arrow-mask').getBoundingClientRect();
        const lastLine = lines.at(-1);
        return { lineCount: lines.length, arrowLeft: arrow.left, arrowCenterY: arrow.top + arrow.height / 2, lastLineTop: lastLine.top, lastLineRight: lastLine.right, lastLineBottom: lastLine.bottom };
    });
    expect(wrappedProjectArrow.lineCount).toBeGreaterThan(1);
    expect(wrappedProjectArrow.arrowLeft).toBeGreaterThanOrEqual(wrappedProjectArrow.lastLineRight - 1);
    expect(wrappedProjectArrow.arrowLeft).toBeLessThanOrEqual(wrappedProjectArrow.lastLineRight + 12);
    expect(wrappedProjectArrow.arrowCenterY).toBeGreaterThanOrEqual(wrappedProjectArrow.lastLineTop - 2);
    expect(wrappedProjectArrow.arrowCenterY).toBeLessThanOrEqual(wrappedProjectArrow.lastLineBottom + 2);
});

test('shared sidebars stick as a whole only when they fit the viewport', async ({ page }) => {
    await page.route('https://zenodo.org/**', (route) => route.abort());
    for (const detail of detailPages) {
        await page.goto(detail.path, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').first()).toBeVisible();
        await page.evaluate(async () => {
            await document.fonts.ready;
            const visibleImages = [...document.images].filter((image) => image.loading !== 'lazy' || image.getBoundingClientRect().top < window.innerHeight);
            await Promise.all(visibleImages.map((image) => image.decode().catch(() => {})));
        });
        for (const viewport of viewports) {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));

            const rail = page.locator('.article-context-rail').first();
            const railContainer = page.locator('.detail-layout__rail, .experience-rail').first();
            const railToc = page.locator('.article-toc--rail');
            const mobileToc = page.locator('.article-toc--mobile');
            const shouldUseRail = viewport.width >= 1024 && detail.hasToc;

            if (shouldUseRail) {
                await expect(railToc).toBeVisible();
                await expect(mobileToc).toBeHidden();
            } else if (detail.hasToc) {
                await expect(railToc).toBeHidden();
                await expect(mobileToc).toBeVisible();
                await expect(mobileToc).toHaveCSS('position', 'static');
            } else {
                await expect(railToc).toBeHidden();
            }

            if (detail.hasToc) {
                const toc = shouldUseRail ? railToc : mobileToc;
                await expect(rail).toHaveAttribute('data-context-rail', '');
                await expect(rail.locator('.article-context-rail__line [data-context-line]')).toHaveCount(1);
                const railMetrics = await rail.evaluate((element) => {
                    const rootFontSize = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
                    const stickyTopRem = Number.parseFloat(getComputedStyle(element).getPropertyValue('--context-rail-sticky-top')) || 0;
                    return {
                        height: element.getBoundingClientRect().height,
                        availableHeight: window.innerHeight - stickyTopRem * rootFontSize,
                    };
                });
                const shouldStick = shouldUseRail && railMetrics.height <= railMetrics.availableHeight;
                await expect.poll(() => rail.evaluate((element) => element.classList.contains('article-context-rail--sticky'))).toBe(shouldStick);
                expect(await rail.evaluate((element) => getComputedStyle(element).position === 'sticky')).toBe(shouldStick);
                await expect(rail).not.toHaveCSS('overflow-y', 'auto');
                await expect(rail.locator('.article-context-rail__content')).not.toHaveCSS('overflow-y', 'auto');
                await expect(toc.locator('.article-toc__list')).not.toHaveCSS('overflow-y', 'auto');

                const firstSemanticLink = rail.locator('.semantic-link').first();
                if (await firstSemanticLink.count()) {
                    const colors = await firstSemanticLink.evaluate((link) => {
                        const arrow = link.closest('[data-related-reference]')?.querySelector('.related-reference__arrow');
                        return {
                            underline: getComputedStyle(link).textDecorationColor,
                            arrow: arrow ? getComputedStyle(arrow).color : null,
                            line: getComputedStyle(link.closest('.article-context-rail').querySelector('[data-context-line]')).stroke,
                        };
                    });
                    expect(colors.underline).toBe(colors.line);
                    if (colors.arrow) expect(colors.arrow).toBe(colors.line);
                }

                if (detail.name === 'publication' && viewport.name === '1440') {
                    await expect(rail.locator('.article-context-rail__line [data-context-line]')).toHaveAttribute('d', /C/);
                }

                if (detail.name === 'blog' && shouldUseRail && viewport.name === '1440') {
                    const firstTocLink = toc.locator('a').first();
                    const stickyPosition = await rail.evaluate((element) => {
                        const documentTop = element.getBoundingClientRect().top + window.scrollY;
                        const target = documentTop + 120;
                        window.scrollTo({ top: target, behavior: 'instant' });
                        return element.getBoundingClientRect().top;
                    });
                    expect(stickyPosition).toBeCloseTo(96, 0);
                    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));

                    await firstTocLink.focus();
                    await expect(firstTocLink).toHaveCSS('outline-width', '3px');
                    const targetHeading = page.locator(await firstTocLink.getAttribute('href'));
                    expect(await targetHeading.evaluate((heading) => getComputedStyle(heading).scrollMarginTop)).toBe('112px');

                    await page.emulateMedia({ reducedMotion: 'reduce' });
                    await firstTocLink.evaluate((link) => link.click());
                    await expect(page).toHaveURL(/#.+/);
                    expect(await targetHeading.evaluate((heading) => heading.getBoundingClientRect().top)).toBeGreaterThanOrEqual(110);

                    await toc.locator('a').last().focus();
                    await page.keyboard.press('Tab');
                    expect(await page.locator('.article-toc a:focus').count()).toBe(0);
                    await page.emulateMedia({ reducedMotion: 'no-preference' });
                    await page.evaluate(() => document.activeElement instanceof HTMLElement && document.activeElement.blur());
                }
            }

            await expect(railContainer).not.toHaveCSS('position', 'sticky');
            await expect(railContainer).not.toHaveCSS('overflow-y', 'auto');
            expect(await page.evaluate(() => document.scrollingElement === document.documentElement)).toBe(true);
            await page.screenshot({ path: `/tmp/detail-rail-${detail.name}-${viewport.name}.png` });
        }
    }

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/blog/warum-ich-projekte-zu-codeberg-verschiebe', { waitUntil: 'domcontentloaded' });
    const longRailToc = page.locator('.article-toc--rail');
    await expect(longRailToc).toBeVisible();
    const longRail = page.locator('.article-context-rail').first();
    await longRailToc.locator('.article-toc__list').evaluate((list) => {
        for (let index = 0; index < 60; index += 1) {
            const item = document.createElement('li');
            item.textContent = `Long section ${index + 1}`;
            list.append(item);
        }
    });
    const longToc = await longRailToc.evaluate((toc) => {
        const list = toc.querySelector('.article-toc__list');
        return {
            tocOverflow: getComputedStyle(toc).overflowY,
            listOverflow: getComputedStyle(list).overflowY,
            listScrollHeight: list.scrollHeight,
            listClientHeight: list.clientHeight,
            listMaxHeight: getComputedStyle(list).maxHeight,
        };
    });
    await expect.poll(() => longRail.evaluate((rail) => rail.classList.contains('article-context-rail--sticky'))).toBe(false);
    await expect(longRail).not.toHaveCSS('position', 'sticky');
    expect(longToc.tocOverflow).not.toBe('auto');
    expect(longToc.listOverflow).not.toBe('auto');
    expect(longToc.listScrollHeight).toBeLessThanOrEqual(longToc.listClientHeight + 1);
    expect(longToc.listMaxHeight).toBe('none');
    expect(await page.evaluate(() => document.scrollingElement === document.documentElement)).toBe(true);
});
