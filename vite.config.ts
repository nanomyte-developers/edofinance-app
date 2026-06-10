import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts',
                    'resources/css/app.css',
                    'resources/css/sakai-theme.css',
                ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            // Use index.esm.js instead of vue.es.js
            'ziggy-js': path.resolve(__dirname, 'vendor/tightenco/ziggy/dist/'),
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});