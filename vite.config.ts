import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import { parse as parseSfc } from '@vue/compiler-sfc';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, type Plugin } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

/**
 * Stale HMR can request `*.vue?vue&type=style` after the <style> block was
 * removed. Vite then feeds the whole SFC to @tailwindcss/vite / vite:vue.
 * Map those orphan requests to an empty virtual CSS module.
 */
function emptyOrphanVueStyles(): Plugin {
    let root = process.cwd();

    function resolveVueFile(id: string): string | null {
        if (!id.includes('type=style') || !id.includes('.vue')) {
            return null;
        }

        const rawPath = id.split('?', 1)[0];
        if (!rawPath.endsWith('.vue')) {
            return null;
        }

        // URL paths look absolute (`/resources/...`) but are project-relative.
        const candidates = [rawPath, resolve(root, rawPath.replace(/^\//, ''))];

        for (const candidate of candidates) {
            if (existsSync(candidate)) {
                return candidate;
            }
        }

        return null;
    }

    function isOrphanStyle(id: string, filename: string): boolean {
        try {
            const source = readFileSync(filename, 'utf8');
            const { descriptor } = parseSfc(source, { filename });
            const index = Number(id.match(/[?&]index=(\d+)/)?.[1] ?? 0);

            return !descriptor.styles[index];
        } catch {
            return false;
        }
    }

    const prefix = '\0orphan-vue-style:';

    return {
        name: 'empty-orphan-vue-styles',
        enforce: 'pre',
        configResolved(config) {
            root = config.root;
        },
        resolveId(id) {
            const filename = resolveVueFile(id);
            if (filename && isOrphanStyle(id, filename)) {
                return `${prefix}${filename}.css`;
            }
        },
        configureServer(server) {
            server.middlewares.use((req, res, next) => {
                const url = req.url;
                if (!url || !url.includes('type=style') || !url.includes('.vue')) {
                    return next();
                }

                const filename = resolveVueFile(decodeURIComponent(url.split('#')[0]));
                if (!filename || !isOrphanStyle(url, filename)) {
                    return next();
                }

                res.setHeader('Content-Type', 'text/css');
                res.statusCode = 200;
                res.end('/* orphan vue style */\n');
            });
        },
        load(id) {
            if (id.startsWith(prefix)) {
                return '/* orphan vue style */\n';
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('DM Sans', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('Oswald', {
                    weights: [500, 600, 700],
                }),
                bunny('Noto Sans Arabic', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        emptyOrphanVueStyles(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            registerType: 'autoUpdate',
            injectRegister: false,
            includeAssets: ['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'],
            manifest: {
                name: 'Oscar CMS',
                short_name: 'Oscar',
                description: 'Oscar takeaway order management — kitchen, delivery, and staff',
                theme_color: '#ea7516',
                background_color: '#f7f3ee',
                display: 'standalone',
                start_url: '/dashboard',
                scope: '/',
                icons: [
                    {
                        src: '/favicon.svg',
                        sizes: 'any',
                        type: 'image/svg+xml',
                        purpose: 'any maskable',
                    },
                    {
                        src: '/apple-touch-icon.png',
                        sizes: '180x180',
                        type: 'image/png',
                    },
                ],
            },
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
        },
    },
});
