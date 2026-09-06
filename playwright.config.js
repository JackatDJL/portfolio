import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    use: {
        baseURL: 'http://127.0.0.1:4173',
    },
    webServer: {
        command: 'bun run dev --host 127.0.0.1 --port 4173 --strictPort',
        url: 'http://127.0.0.1:4173/tests/browser/fixtures/article-toc.html',
        reuseExistingServer: !process.env.CI,
    },
});
