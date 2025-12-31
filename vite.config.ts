import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: 'localhost',
        // Optimization: Speed up HMR and file watching
        watch: {
            usePolling: true,
        },
    },
    build: {
        // Optimization: Efficient chunk splitting
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom', '@inertiajs/react'],
                    ui: ['framer-motion', 'lucide-react'],
                },
            },
        },
        chunkSizeWarningLimit: 1000,
    },
    // Optimization: Faster development builds
    optimizeDeps: {
        include: ['react', 'react-dom', 'axios', '@inertiajs/react', 'framer-motion', 'lucide-react'],
    },
});
