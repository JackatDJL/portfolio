import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const script = readFileSync(fileURLToPath(new URL('../../public/cp-handwritten-signature.js', import.meta.url)), 'utf8');

test('initializes inserted CP content and replaces retained-control listeners safely', async ({ page }) => {
    await page.goto('/cp/globals/application_defaults#signature');
    await page.setContent('<main id="cp-page"></main>');
    let savedPayload;
    let saveRequests = 0;
    await page.route('**/applications/handwritten-signature', route => {
        if (route.request().method() === 'POST') {
            saveRequests++;
            savedPayload = route.request().postDataJSON();
            return route.fulfill({ json: { saved: true } });
        }
        return route.fulfill({ json: { available: false } });
    });
    await page.addScriptTag({ content: script });

    const editorMarkup = [
            '<div data-handwritten-signature>',
            '<canvas data-handwritten-signature-canvas width="900" height="260" style="width:900px;height:260px"></canvas>',
            '<button type="button" data-handwritten-clear>Clear</button>',
            '<input type="file" data-handwritten-upload>',
            '<input type="checkbox" data-handwritten-remove-background>',
            '<button type="button" data-handwritten-crop>Preview</button>',
            '<div data-handwritten-preview><h4>Preview</h4></div>',
            '<p data-handwritten-preview-status></p>',
            '<button type="button" data-handwritten-save disabled>Save</button>',
            '<button type="button" data-handwritten-delete>Delete</button>',
            '<p data-handwritten-status></p>',
            '</div>',
    ].join('');
    await page.locator('#cp-page').evaluate((element, markup) => { element.innerHTML = markup; }, editorMarkup);
    await expect(page.locator('[data-handwritten-status]')).toContainText('Noch keine private Signaturvorlage');
    // Statamic can replace a control while retaining the editor root and its other listeners.
    await page.locator('[data-handwritten-preview-status]').evaluate(element => {
        const replacement = document.createElement('p');
        replacement.dataset.handwrittenPreviewStatus = '';
        replacement.setAttribute('aria-live', 'polite');
        element.replaceWith(replacement);
    });
    await expect(page.locator('[data-handwritten-status]')).toContainText('Noch keine private Signaturvorlage');

    const canvas = page.locator('[data-handwritten-signature-canvas]');
    await canvas.hover({ position: { x: 100, y: 100 } });
    await page.mouse.down();
    await page.mouse.move(180, 130, { steps: 4 });
    await page.mouse.up();
    await page.getByRole('button', { name: 'Preview' }).click();
    await expect(page.locator('[data-handwritten-save]')).toBeEnabled();
    await expect(page.locator('[data-handwritten-preview] img')).toBeVisible();

    const uploadedPng = await page.evaluate(() => {
        const image = document.createElement('canvas');
        image.width = 180;
        image.height = 60;
        const context = image.getContext('2d');
        context.fillStyle = 'white';
        context.fillRect(0, 0, image.width, image.height);
        context.strokeStyle = '#17231f';
        context.lineWidth = 6;
        context.lineCap = 'round';
        context.beginPath();
        context.moveTo(15, 42);
        context.bezierCurveTo(45, 4, 60, 54, 92, 24);
        context.bezierCurveTo(118, 4, 130, 46, 165, 18);
        context.stroke();
        return image.toDataURL().split(',')[1];
    });
    await page.locator('[data-handwritten-upload]').setInputFiles({
        name: 'signature.png',
        mimeType: 'image/png',
        buffer: Buffer.from(uploadedPng, 'base64'),
    });
    await expect(page.locator('[data-handwritten-status]')).toContainText('Bild geladen');
    await page.getByRole('button', { name: 'Preview' }).click();
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.locator('[data-handwritten-status]')).toContainText('verschlüsselt gespeichert');
    expect(saveRequests).toBe(1);
    expect(savedPayload.image).toMatch(/^data:image\/png;base64,/);
    expect(savedPayload.strokes).toEqual([]);
});
