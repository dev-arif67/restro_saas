import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'robots.txt'],
            manifest: {
                name: 'RestaurantSaaS',
                short_name: 'RestSaaS',
                description: 'Multi-Tenant Restaurant Management Platform',
                theme_color: '#3B82F6',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/',
                icons: [
                    {
                        src: '/assets/images/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/assets/images/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                ],
            },
        }),
    ],
    root: 'resources/js',
    base: '/build/',
    build: {
        outDir: '../../public/build',
        emptyOutDir: true,
        manifest: true,
    },
    server: {
        port: 5173,
        proxy: {
            '/api': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
            '/storage': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
            '/broadcasting': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },
});
