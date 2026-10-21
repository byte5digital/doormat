import statamic from '@statamic/cms/vite-plugin';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// The control panel bundle (the dashboard widget), built against Statamic's own UI kit. The visitor
// script is built separately by esbuild (npm run build:script).
export default defineConfig({
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/cp.js'],
            publicDirectory: 'resources/dist',
            hotFile: 'resources/dist/hot',
        }),
    ],
});
