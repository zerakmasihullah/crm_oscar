import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/__tests__/**/*.{test,spec}.ts'],
        setupFiles: ['resources/js/offline/__tests__/setup.ts'],
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
