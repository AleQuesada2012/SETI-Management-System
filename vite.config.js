import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Inside the `vite` Docker service the dev server must listen on every interface,
// but the browser (on your machine) reaches it through localhost:5173.
const inDocker = process.env.VITE_IN_DOCKER === 'true';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        ...(inDocker && {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5173',
            hmr: { host: 'localhost' },
        }),
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
