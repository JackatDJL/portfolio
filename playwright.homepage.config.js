import { defineConfig } from '@playwright/test';

// Run after the production build and Statamic SSG. This server has no PHP.
export default defineConfig({
    testDir: './tests/homepage',
    use: { baseURL: 'http://127.0.0.1:8010', viewport: { width: 1440, height: 1000 } },
    webServer: {
        command: 'python3 -m http.server 8010 --bind 127.0.0.1 --directory storage/app/static',
        url: 'http://127.0.0.1:8010',
        reuseExistingServer: false,
    },
});
