export type ThemeRadius = 'soft' | 'medium' | 'sharp';
export type ThemeDensity = 'comfortable' | 'compact';
export type ThemeSidebarActive = 'pill' | 'accent' | 'soft';
export type ThemeShell =
    | 'sidebar-floating'
    | 'sidebar-inset'
    | 'sidebar-flush'
    | 'header';

export type ThemeColorSettings = {
    brand: string;
    secondary: string;
    accent: string;
    backgroundLight: string;
    backgroundDark: string;
    accentDark: string;
    radius: ThemeRadius;
    density: ThemeDensity;
    sidebarActive: ThemeSidebarActive;
    shell: ThemeShell;
};

export type ThemePreset = {
    id: string;
    labelKey: string;
    descriptionKey: string;
    settings: ThemeColorSettings;
};

export const THEME_COLORS_STORAGE_KEY = 'theme-colors';

/** Oscar takeaway defaults — charcoal shell, brand orange, warm gold. */
export const DEFAULT_THEME_COLORS: ThemeColorSettings = {
    brand: '#1a120b',
    secondary: '#e4c590',
    accent: '#ea7516',
    backgroundLight: '#f7f3ee',
    backgroundDark: '#120d09',
    accentDark: '#fb923c',
    radius: 'soft',
    density: 'comfortable',
    sidebarActive: 'accent',
    shell: 'sidebar-floating',
};

export const THEME_PRESETS: ThemePreset[] = [
    {
        id: 'oscar',
        labelKey: 'Oscar',
        descriptionKey: 'Pizza & kebab orange with warm charcoal',
        settings: { ...DEFAULT_THEME_COLORS },
    },
    {
        id: 'ember',
        labelKey: 'Ember kitchen',
        descriptionKey: 'Darker kitchen chrome with hot amber',
        settings: {
            brand: '#140e0a',
            secondary: '#f6a05a',
            accent: '#ea7516',
            accentDark: '#ff9f4a',
            backgroundLight: '#faf6f1',
            backgroundDark: '#0c0907',
            radius: 'medium',
            density: 'comfortable',
            sidebarActive: 'accent',
            shell: 'sidebar-floating',
        },
    },
    {
        id: 'slate',
        labelKey: 'Night service',
        descriptionKey: 'Cool charcoal for late service shifts',
        settings: {
            brand: '#111827',
            secondary: '#e4c590',
            accent: '#ea7516',
            accentDark: '#fb923c',
            backgroundLight: '#f8fafc',
            backgroundDark: '#0b0e13',
            radius: 'sharp',
            density: 'compact',
            sidebarActive: 'accent',
            shell: 'sidebar-floating',
        },
    },
];

export const THEME_COLOR_FIELDS: Array<{
    key: keyof Pick<
        ThemeColorSettings,
        | 'brand'
        | 'secondary'
        | 'accent'
        | 'accentDark'
        | 'backgroundLight'
        | 'backgroundDark'
    >;
    labelKey: string;
    descriptionKey: string;
}> = [
    {
        key: 'brand',
        labelKey: 'Sidebar / brand color',
        descriptionKey: 'Floating sidebar background and structural chrome',
    },
    {
        key: 'secondary',
        labelKey: 'Secondary / gold',
        descriptionKey: 'Highlights, badges, and secondary accents',
    },
    {
        key: 'accent',
        labelKey: 'Accent color',
        descriptionKey: 'Buttons, links, and active highlights (light mode)',
    },
    {
        key: 'accentDark',
        labelKey: 'Accent color (dark mode)',
        descriptionKey: 'Buttons and highlights in dark mode',
    },
    {
        key: 'backgroundLight',
        labelKey: 'Background (light mode)',
        descriptionKey: 'Main page background in light mode',
    },
    {
        key: 'backgroundDark',
        labelKey: 'Background (dark mode)',
        descriptionKey: 'Main page background in dark mode',
    },
];

export const THEME_RADIUS_OPTIONS: Array<{
    value: ThemeRadius;
    labelKey: string;
    descriptionKey: string;
}> = [
    {
        value: 'soft',
        labelKey: 'Soft',
        descriptionKey: 'Rounded cards and controls',
    },
    {
        value: 'medium',
        labelKey: 'Medium',
        descriptionKey: 'Balanced default radius',
    },
    {
        value: 'sharp',
        labelKey: 'Sharp',
        descriptionKey: 'Tighter, more formal edges',
    },
];

export const THEME_DENSITY_OPTIONS: Array<{
    value: ThemeDensity;
    labelKey: string;
    descriptionKey: string;
}> = [
    {
        value: 'comfortable',
        labelKey: 'Comfortable',
        descriptionKey: 'Spacious navigation and content',
    },
    {
        value: 'compact',
        labelKey: 'Compact',
        descriptionKey: 'Denser sidebar and lists',
    },
];

export const THEME_SIDEBAR_ACTIVE_OPTIONS: Array<{
    value: ThemeSidebarActive;
    labelKey: string;
    descriptionKey: string;
}> = [
    {
        value: 'pill',
        labelKey: 'White pill',
        descriptionKey: 'Active item as a white chip on the sidebar',
    },
    {
        value: 'accent',
        labelKey: 'Accent fill',
        descriptionKey: 'Active item uses the accent color',
    },
    {
        value: 'soft',
        labelKey: 'Soft glow',
        descriptionKey: 'Subtle translucent active state',
    },
];

export const THEME_SHELL_OPTIONS: Array<{
    value: ThemeShell;
    labelKey: string;
    descriptionKey: string;
}> = [
    {
        value: 'sidebar-floating',
        labelKey: 'Floating sidebar',
        descriptionKey: 'Rounded island sidebar over the page (default)',
    },
    {
        value: 'sidebar-inset',
        labelKey: 'Workspace canvas',
        descriptionKey: 'Solid side rail with a soft rounded content card',
    },
    {
        value: 'sidebar-flush',
        labelKey: 'Edge rail',
        descriptionKey: 'Full-height sidebar flush to the screen edge',
    },
    {
        value: 'header',
        labelKey: 'Header only',
        descriptionKey: 'No sidebar — top navigation across the ERP',
    },
];

export function shellToSidebarVariant(
    shell: ThemeShell,
): 'floating' | 'inset' | 'sidebar' {
    if (shell === 'sidebar-inset') {
        return 'inset';
    }

    if (shell === 'sidebar-flush') {
        return 'sidebar';
    }

    return 'floating';
}

const RADIUS_VALUES: Record<ThemeRadius, string> = {
    soft: '1.5rem',
    medium: '0.875rem',
    sharp: '0.375rem',
};

function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
    const normalized = hex.replace('#', '');

    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
        return null;
    }

    return {
        r: parseInt(normalized.slice(0, 2), 16),
        g: parseInt(normalized.slice(2, 4), 16),
        b: parseInt(normalized.slice(4, 6), 16),
    };
}

function rgbToHex(r: number, g: number, b: number): string {
    const clamp = (n: number) => Math.max(0, Math.min(255, Math.round(n)));

    return `#${[clamp(r), clamp(g), clamp(b)]
        .map((n) => n.toString(16).padStart(2, '0'))
        .join('')}`;
}

/** Lift a dark background toward a readable elevated surface. */
function elevateHex(hex: string, amount: number): string {
    const rgb = hexToRgb(hex);

    if (!rgb) {
        return hex;
    }

    return rgbToHex(
        rgb.r + (255 - rgb.r) * amount,
        rgb.g + (255 - rgb.g) * amount,
        rgb.b + (255 - rgb.b) * amount,
    );
}

export function withAlpha(hex: string, alpha: number): string {
    const rgb = hexToRgb(hex);

    if (!rgb) {
        return hex;
    }

    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
}

function isDarkMode(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.documentElement.classList.contains('dark');
}

function fromServerShape(
    input: Partial<ThemeColorSettings> & {
        sidebar_active?: ThemeSidebarActive;
    },
): Partial<ThemeColorSettings> {
    const { sidebar_active, ...rest } = input;

    return {
        ...rest,
        ...(sidebar_active ? { sidebarActive: sidebar_active } : {}),
    };
}

export function normalizeThemeColors(
    input:
        | (Partial<ThemeColorSettings> & {
              sidebar_active?: ThemeSidebarActive;
          })
        | null
        | undefined,
): ThemeColorSettings {
    const mapped = fromServerShape(input ?? {});

    // Upgrade legacy pure-black dark backgrounds to the current charcoal shell.
    if (
        mapped.backgroundDark === '#0a0a0a' ||
        mapped.backgroundDark === '#111318'
    ) {
        mapped.backgroundDark = DEFAULT_THEME_COLORS.backgroundDark;
    }

    return {
        ...DEFAULT_THEME_COLORS,
        ...mapped,
        radius: (['soft', 'medium', 'sharp'] as const).includes(
            mapped.radius as ThemeRadius,
        )
            ? (mapped.radius as ThemeRadius)
            : DEFAULT_THEME_COLORS.radius,
        density: (['comfortable', 'compact'] as const).includes(
            mapped.density as ThemeDensity,
        )
            ? (mapped.density as ThemeDensity)
            : DEFAULT_THEME_COLORS.density,
        sidebarActive: (['pill', 'accent', 'soft'] as const).includes(
            mapped.sidebarActive as ThemeSidebarActive,
        )
            ? (mapped.sidebarActive as ThemeSidebarActive)
            : DEFAULT_THEME_COLORS.sidebarActive,
        shell: (
            [
                'sidebar-floating',
                'sidebar-inset',
                'sidebar-flush',
                'header',
            ] as const
        ).includes(mapped.shell as ThemeShell)
            ? (mapped.shell as ThemeShell)
            : DEFAULT_THEME_COLORS.shell,
    };
}

/** Payload shape expected by the Laravel API (snake_case for sidebar_active). */
export function toServerThemePayload(settings: ThemeColorSettings): Record<
    string,
    string
> {
    return {
        brand: settings.brand,
        secondary: settings.secondary,
        accent: settings.accent,
        accentDark: settings.accentDark,
        backgroundLight: settings.backgroundLight,
        backgroundDark: settings.backgroundDark,
        radius: settings.radius,
        density: settings.density,
        sidebar_active: settings.sidebarActive,
        shell: settings.shell,
    };
}

export function applyThemeColors(settings: ThemeColorSettings | null): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const cssVars = [
        '--school-navy',
        '--school-gold',
        '--school-accent',
        '--school-panel',
        '--kit-brand',
        '--kit-accent',
        '--kit-panel',
        '--primary',
        '--accent-foreground',
        '--sidebar-background',
        '--sidebar',
        '--sidebar-primary',
        '--sidebar-ring',
        '--ring',
        '--accent',
        '--chart-1',
        '--chart-2',
        '--chart-3',
        '--background',
        '--brand-accent',
        '--dash-lime',
        '--app-shell',
        '--radius',
        '--card',
        '--popover',
        '--muted',
        '--card-border',
        '--border',
        '--input',
        '--muted-foreground',
        '--foreground',
        '--card-foreground',
        '--popover-foreground',
        '--primary-foreground',
        '--sidebar-primary-foreground',
    ];

    if (!settings) {
        cssVars.forEach((name) => root.style.removeProperty(name));
        root.removeAttribute('data-theme-radius');
        root.removeAttribute('data-theme-density');
        root.removeAttribute('data-theme-sidebar-active');
        root.removeAttribute('data-theme-shell');

        return;
    }

    const dark = isDarkMode();
    const accent = dark ? settings.accentDark : settings.accent;
    const background = dark
        ? settings.backgroundDark
        : settings.backgroundLight;
    const chrome = dark ? '#f4f4f5' : settings.brand;

    root.style.setProperty('--school-navy', chrome);
    root.style.setProperty('--school-gold', settings.secondary);
    root.style.setProperty('--school-accent', accent);
    root.style.setProperty('--school-panel', background);
    root.style.setProperty('--kit-brand', settings.brand);
    root.style.setProperty('--kit-accent', accent);
    root.style.setProperty('--kit-panel', background);
    root.style.setProperty('--primary', chrome);
    root.style.setProperty('--brand-accent', accent);
    root.style.setProperty('--dash-lime', accent);
    root.style.setProperty(
        '--accent-foreground',
        dark ? accent : settings.brand,
    );
    root.style.setProperty('--sidebar-background', 'transparent');
    root.style.setProperty('--sidebar', 'transparent');
    root.style.setProperty('--sidebar-primary', chrome);
    root.style.setProperty(
        '--sidebar-ring',
        withAlpha(dark ? accent : settings.brand, dark ? 0.35 : 0.2),
    );
    root.style.setProperty(
        '--ring',
        withAlpha(dark ? accent : settings.brand, dark ? 0.42 : 0.28),
    );
    root.style.setProperty('--accent', withAlpha(accent, dark ? 0.22 : 0.28));
    root.style.setProperty('--chart-1', accent);
    root.style.setProperty('--chart-2', dark ? chrome : settings.brand);
    root.style.setProperty('--chart-3', settings.secondary);
    root.style.setProperty('--background', background);
    root.style.setProperty('--app-shell', background);
    root.style.setProperty('--radius', RADIUS_VALUES[settings.radius]);

    if (dark) {
        const card = elevateHex(background, 0.1);
        const muted = elevateHex(background, 0.14);
        const popover = elevateHex(background, 0.12);

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
        root.style.setProperty('--primary-foreground', background);
        root.style.setProperty('--sidebar-primary-foreground', background);
    } else {
        [
            '--card',
            '--popover',
            '--muted',
            '--card-border',
            '--border',
            '--input',
            '--muted-foreground',
            '--foreground',
            '--card-foreground',
            '--popover-foreground',
            '--primary-foreground',
            '--sidebar-primary-foreground',
        ].forEach((name) => root.style.removeProperty(name));
    }

    root.dataset.themeRadius = settings.radius;
    root.dataset.themeDensity = settings.density;
    root.dataset.themeSidebarActive = settings.sidebarActive;
    root.dataset.themeShell = settings.shell;

    let meta = document.querySelector<HTMLMetaElement>(
        'meta[name="theme-color"]',
    );
    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'theme-color';
        document.head.appendChild(meta);
    }
    meta.content = background;
}

export function loadStoredThemeColors(): ThemeColorSettings | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(THEME_COLORS_STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as Partial<ThemeColorSettings> & {
            sidebar_active?: ThemeSidebarActive;
        };
        const normalized = normalizeThemeColors(parsed);
        const legacyDark =
            parsed.backgroundDark === '#0a0a0a' ||
            parsed.backgroundDark === '#111318';

        if (legacyDark) {
            cacheThemeColors(normalized);
        }

        return normalized;
    } catch {
        return null;
    }
}

export function cacheThemeColors(settings: ThemeColorSettings): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(THEME_COLORS_STORAGE_KEY, JSON.stringify(settings));
}

export function saveThemeColors(settings: ThemeColorSettings): void {
    cacheThemeColors(settings);
    applyThemeColors(settings);
}

export function resetThemeColors(): void {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.removeItem(THEME_COLORS_STORAGE_KEY);
    applyThemeColors(null);
}

export function initializeThemeColors(
    serverColors?: ThemeColorSettings | null,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (serverColors) {
        const normalized = normalizeThemeColors(serverColors);
        cacheThemeColors(normalized);
        applyThemeColors(normalized);
    } else {
        // Re-apply cached theme on refresh so dark chrome/surfaces survive reload.
        const stored = loadStoredThemeColors();

        if (stored) {
            applyThemeColors(stored);
        }
    }

    const observer = new MutationObserver(() => {
        const current = loadStoredThemeColors();

        if (current) {
            applyThemeColors(current);
        }
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

export function settingsMatchPreset(
    settings: ThemeColorSettings,
    preset: ThemePreset,
): boolean {
    return (
        settings.brand === preset.settings.brand &&
        settings.secondary === preset.settings.secondary &&
        settings.accent === preset.settings.accent &&
        settings.accentDark === preset.settings.accentDark &&
        settings.backgroundLight === preset.settings.backgroundLight &&
        settings.backgroundDark === preset.settings.backgroundDark &&
        settings.radius === preset.settings.radius &&
        settings.density === preset.settings.density &&
        settings.sidebarActive === preset.settings.sidebarActive &&
        settings.shell === preset.settings.shell
    );
}
