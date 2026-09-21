import { chromium } from 'playwright';

const option = (name) => process.argv.find((value) => value.startsWith(`--${name}=`))?.slice(name.length + 3);
const url = option('url');
const output = option('output');
if (!url || !output) throw new Error('Usage: render-cv-pdf.mjs --url=<cv-url> --output=<file.pdf>');

const token = process.env.CV_PDF_TOKEN || '';
const target = token ? `${url}#cv=${token}` : url;
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
    const response = await page.goto(target, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`CV returned HTTP ${response?.status() ?? 'unknown'}`);

    await page.evaluate(async ({ authorized }) => {
        await document.fonts.ready;
        await Promise.all([...document.images].map((image) => {
            if (image.complete) return image.naturalWidth > 0 ? image.decode() : Promise.reject(new Error(`Image failed: ${image.currentSrc || image.src}`));
            return new Promise((resolve, reject) => {
                image.addEventListener('load', () => image.decode().then(resolve, reject), { once: true });
                image.addEventListener('error', () => reject(new Error(`Image failed: ${image.currentSrc || image.src}`)), { once: true });
            });
        }));
        if (authorized && document.documentElement.dataset.cvPrivateState !== 'authorized') {
            await new Promise((resolve, reject) => {
                const timer = setTimeout(() => reject(new Error('Private CV data did not resolve.')), 10_000);
                document.addEventListener('cv:private-settled', () => {
                    clearTimeout(timer);
                    document.documentElement.dataset.cvPrivateState === 'authorized' ? resolve() : reject(new Error('Private CV authorization failed.'));
                }, { once: true });
            });
        }
    }, { authorized: Boolean(token) });

    await page.emulateMedia({ media: 'print', colorScheme: 'light', reducedMotion: 'reduce' });
    await page.pdf({
        path: output,
        format: 'A4',
        preferCSSPageSize: true,
        printBackground: true,
        displayHeaderFooter: false,
        margin: { top: '0', right: '0', bottom: '0', left: '0' },
        tagged: true,
    });
} finally {
    await browser.close();
}
