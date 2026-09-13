import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/cv',
    outputDir: './storage/app/cv-qa',
    timeout: 60_000,
    workers: 1,
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8091 --no-reload',
        url: 'http://127.0.0.1:8091/cv',
        reuseExistingServer: true,
    },
    use: { baseURL: process.env.CV_BASE_URL || 'http://127.0.0.1:8091', viewport: { width: 1440, height: 1000 } },
});
