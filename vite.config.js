import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/site.css', 'resources/js/site.js', 'resources/css/lab.css', 'resources/js/lab.js'],
            hotFile: process.env.HOMEPAGE_PREVIEW_HOT_FILE || undefined,
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: process.env.HOMEPAGE_PREVIEW_HOST || undefined,
        port: process.env.HOMEPAGE_PREVIEW_VITE_PORT ? Number(process.env.HOMEPAGE_PREVIEW_VITE_PORT) : undefined,
        strictPort: Boolean(process.env.HOMEPAGE_PREVIEW_VITE_PORT),
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    ...(process.env.HOMEPAGE_PREVIEW_ENV_DIR ? { envDir: process.env.HOMEPAGE_PREVIEW_ENV_DIR } : {}),
});
