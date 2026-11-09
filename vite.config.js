import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/minsuanscore.css',
                'resources/js/minsuanscore.js',
                'resources/css/coping-toolkit-experience.css',
                'resources/js/coping-toolkit-runner.js',
                'resources/js/community-react.tsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
