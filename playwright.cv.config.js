import { defineConfig } from '@playwright/test';

const port = process.env.CV_PORT || '8091';
const baseURL = process.env.CV_BASE_URL || `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: './tests/cv',
    outputDir: './storage/app/cv-qa',
    timeout: 60_000,
    workers: 1,
    webServer: {
        command: `/usr/bin/php -S 127.0.0.1:${port} -t public public/index.php`,
        url: `${baseURL}/cv`,
        reuseExistingServer: true,
    },
    use: { baseURL, viewport: { width: 1440, height: 1000 } },
});
