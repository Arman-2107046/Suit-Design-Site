import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
            // Out of the web root: see AppServiceProvider::boot().
            hotFile: 'storage/vite.hot',
        }),
        react(),
    ],
});
