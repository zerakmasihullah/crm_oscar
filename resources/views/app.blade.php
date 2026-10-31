<!DOCTYPE html>
<html
        lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        dir="{{ app(\App\Services\LocaleRegistry::class)->direction(app()->getLocale()) }}"
        @class([
            'dark' => ($appearance ?? 'system') == 'dark',
            'glass' => ($interfaceStyle ?? 'standard') == 'glass',
        ])
    >
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                var appearance = '{{ $appearance ?? "system" }}';

                try {
                    var path = window.location.pathname || '/';
                    var storefrontPaths = [
                        '/',
                        '/checkout',
                        '/my-orders',
                        '/order-success',
                        '/support',
                        '/terms-of-use',
                        '/privacy-statement',
                    ];
                    var onStorefront = storefrontPaths.indexOf(path) !== -1;
                    var storefrontTheme = localStorage.getItem('storefront-theme');
                    if (
                        onStorefront &&
                        (storefrontTheme === 'light' || storefrontTheme === 'dark' || storefrontTheme === 'system')
                    ) {
                        appearance = storefrontTheme;
                    } else {
                        var stored = localStorage.getItem('appearance');
                        if (stored === 'light' || stored === 'dark' || stored === 'system') {
                            appearance = stored;
                        }
                    }
                } catch (e) {}

                if (appearance === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (appearance === 'light') {
                    document.documentElement.classList.remove('dark');
                } else {
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', prefersDark);
                }

                try {
                    const match = document.cookie.match(/(?:^|; )locale=([^;]+)/);
                    const locale = match ? decodeURIComponent(match[1]) : null;
                    const dirs = @json(app(\App\Services\LocaleRegistry::class)->directions());
                    if (locale && dirs[locale]) {
                        document.documentElement.lang = locale;
                        document.documentElement.dir = dirs[locale];
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Apply org theme colors before paint (from org_settings) --}}
        <script>
            (function() {
                try {
                    var c = @json($themeColors ?? \App\Support\ThemeColors::defaults());
                    var root = document.documentElement;
                    var dark = root.classList.contains('dark');
                    var accent = dark ? (c.accentDark || c.accent) : c.accent;
                    var bg = dark ? (c.backgroundDark || '#0b0e13') : (c.backgroundLight || '#f3f3f4');
                    // Upgrade legacy pure-black shells before first paint.
                    if (dark && (bg === '#0a0a0a' || bg === '#111318')) {
                        bg = '#0b0e13';
                    }
                    var chrome = dark ? '#f4f4f5' : (c.brand || '#171717');
                    var radiusMap = { soft: '1.5rem', medium: '0.875rem', sharp: '0.375rem' };

                    function hexToRgb(hex) {
                        var n = String(hex || '').replace('#', '');
                        if (!/^[0-9a-fA-F]{6}$/.test(n)) return null;
                        return {
                            r: parseInt(n.slice(0, 2), 16),
                            g: parseInt(n.slice(2, 4), 16),
                            b: parseInt(n.slice(4, 6), 16),
                        };
                    }

                    function elevateHex(hex, amount) {
                        var rgb = hexToRgb(hex);
                        if (!rgb) return hex;
                        var to = function (v) {
                            return Math.max(0, Math.min(255, Math.round(v + (255 - v) * amount)));
                        };
                        var h = function (v) {
                            return v.toString(16).padStart(2, '0');
                        };
                        return '#' + h(to(rgb.r)) + h(to(rgb.g)) + h(to(rgb.b));
                    }

                    function withAlpha(hex, alpha) {
                        var rgb = hexToRgb(hex);
                        if (!rgb) return hex;
                        return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + alpha + ')';
                    }

                    root.style.setProperty('--kit-brand', c.brand || '#171717');
                    root.style.setProperty('--school-navy', chrome);
                    root.style.setProperty('--sidebar-primary', chrome);
                    root.style.setProperty('--primary', chrome);
                    root.style.setProperty(
                        '--accent-foreground',
                        dark ? (accent || chrome) : (c.brand || '#171717')
                    );
                    root.style.setProperty(
                        '--sidebar-ring',
                        withAlpha(dark ? (accent || chrome) : (c.brand || '#171717'), dark ? 0.35 : 0.2)
                    );
                    root.style.setProperty(
                        '--ring',
                        withAlpha(dark ? (accent || chrome) : (c.brand || '#171717'), dark ? 0.42 : 0.28)
                    );
                    root.style.setProperty('--chart-2', dark ? chrome : (c.brand || '#171717'));

                    if (c.secondary) {
                        root.style.setProperty('--school-gold', c.secondary);
                        root.style.setProperty('--chart-3', c.secondary);
                    }
                    if (accent) {
                        root.style.setProperty('--brand-accent', accent);
                        root.style.setProperty('--school-accent', accent);
                        root.style.setProperty('--kit-accent', accent);
                        root.style.setProperty('--dash-lime', accent);
                        root.style.setProperty('--chart-1', accent);
                        root.style.setProperty('--accent', withAlpha(accent, dark ? 0.22 : 0.28));
                    }
                    if (bg) {
                        root.style.setProperty('--background', bg);
                        root.style.setProperty('--school-panel', bg);
                        root.style.setProperty('--app-shell', bg);
                        root.style.setProperty('--kit-panel', bg);
                        root.style.setProperty('--sidebar-background', 'transparent');
                        root.style.setProperty('--sidebar', 'transparent');
                    }
                    if (dark && bg) {
                        var card = elevateHex(bg, 0.1);
                        var muted = elevateHex(bg, 0.14);
                        var popover = elevateHex(bg, 0.12);
                        root.style.setProperty('--card', card);
                        root.style.setProperty('--popover', popover);
                        root.style.setProperty('--muted', muted);
                        root.style.setProperty('--card-border', 'rgba(255, 255, 255, 0.11)');
                        root.style.setProperty('--border', 'rgba(255, 255, 255, 0.11)');
                        root.style.setProperty('--input', 'rgba(255, 255, 255, 0.14)');
                        root.style.setProperty('--muted-foreground', '#9aa3b2');
                        root.style.setProperty('--foreground', '#f4f4f5');
                        root.style.setProperty('--card-foreground', '#f4f4f5');
                        root.style.setProperty('--popover-foreground', '#f4f4f5');
                        root.style.setProperty('--primary-foreground', bg);
                        root.style.setProperty('--sidebar-primary-foreground', bg);
                    }
                    if (c.radius && radiusMap[c.radius]) {
                        root.style.setProperty('--radius', radiusMap[c.radius]);
                        root.setAttribute('data-theme-radius', c.radius);
                    }
                    if (c.density) {
                        root.setAttribute('data-theme-density', c.density);
                    }
                    if (c.sidebar_active) {
                        root.setAttribute('data-theme-sidebar-active', c.sidebar_active);
                    }
                    if (c.shell) {
                        root.setAttribute('data-theme-shell', c.shell);
                    }
                } catch (e) {}
            })();
        </script>

        <style>
            html {
                background-color: var(--background, #f3f3f4);
            }

            html.dark {
                background-color: var(--background, #0b0e13);
            }
        </style>

        <link rel="icon" href="{{ \App\Models\OrgSetting::faviconUrl() ?? '/favicon.ico' }}" sizes="any">
        @if (! \App\Models\OrgSetting::faviconUrl())
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif
        @if (\App\Support\OfflineSettings::pwaEnabled())
            <link rel="manifest" href="/manifest.webmanifest">
            <meta name="theme-color" content="#171717">
        @else
            <link rel="manifest" href="/storefront/site.webmanifest">
            <meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
            <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0a0a0a">
        @endif
        <meta
            name="kit-offline"
            content="{{ \App\Support\OfflineSettings::enabled() ? '1' : '0' }}"
            data-pwa="{{ \App\Support\OfflineSettings::pwaEnabled() ? '1' : '0' }}"
            data-auto-sync="{{ \App\Support\OfflineSettings::autoSync() ? '1' : '0' }}"
            data-warm="{{ \App\Support\OfflineSettings::warmCache() ? '1' : '0' }}"
        >

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Oscar') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
