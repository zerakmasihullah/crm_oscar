import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, InterfaceStyle, ResolvedAppearance } from '@/types';

export type { Appearance, InterfaceStyle, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    interfaceStyle: Ref<InterfaceStyle>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    updateInterfaceStyle: (value: InterfaceStyle) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const getStoredInterfaceStyle = (): InterfaceStyle | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('interface-style') as InterfaceStyle | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const legacyAppearance = localStorage.getItem('appearance');

    if (legacyAppearance === 'glass') {
        localStorage.setItem('appearance', 'light');
        localStorage.setItem('interface-style', 'glass');
        setCookie('appearance', 'light');
        setCookie('interface_style', 'glass');
    }

    const savedInterfaceStyle = getStoredInterfaceStyle();
    let savedAppearance = getStoredAppearance();

    if (savedInterfaceStyle === 'glass' && savedAppearance !== 'light') {
        savedAppearance = 'light';
        localStorage.setItem('appearance', 'light');
        setCookie('appearance', 'light');
    }

    updateTheme(savedAppearance || 'system');
    updateInterfaceStyleClass(savedInterfaceStyle || 'standard');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');
const interfaceStyle = ref<InterfaceStyle>('standard');

function updateInterfaceStyleClass(value: InterfaceStyle): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('glass', value === 'glass');
}

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        const savedInterfaceStyle = getStoredInterfaceStyle();

        if (savedInterfaceStyle) {
            interfaceStyle.value = savedInterfaceStyle;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    function updateInterfaceStyle(value: InterfaceStyle) {
        if (value === 'glass' && appearance.value !== 'light') {
            updateAppearance('light');
        }

        interfaceStyle.value = value;
        localStorage.setItem('interface-style', value);
        setCookie('interface_style', value);
        updateInterfaceStyleClass(value);
    }

    return {
        appearance,
        interfaceStyle,
        resolvedAppearance,
        updateAppearance,
        updateInterfaceStyle,
    };
}
