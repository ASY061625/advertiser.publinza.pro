import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * Three independent entry points, one per surface.
 *
 * Each entry resolves its Inertia pages with an `import.meta.glob` rooted in its
 * own directory (see `resources/js/<surface>/main.tsx`), so the admin page graph
 * is never reachable from the advertiser entry and never lands in its bundle.
 * Only genuinely shared code (React, Inertia, `resources/js/shared`) is hoisted
 * into common chunks.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/marketing/main.tsx',
                'resources/js/advertiser/main.tsx',
                'resources/js/admin/main.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '@shared': path.resolve(__dirname, 'resources/js/shared'),
        },
    },
    build: {
        // Surface bundles are compared in CI; keep chunk names stable.
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/react') || id.includes('node_modules/@inertiajs')) {
                        return 'vendor';
                    }
                    return undefined;
                },
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
        watch: { usePolling: true },
    },
});
