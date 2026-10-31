import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    DEFAULT_THEME_COLORS,
    applyThemeColors,
    cacheThemeColors,
    normalizeThemeColors,
    resetThemeColors,
    saveThemeColors,
    toServerThemePayload,
    type ThemeColorSettings,
    type ThemeDensity,
    type ThemePreset,
    type ThemeRadius,
    type ThemeShell,
    type ThemeSidebarActive,
} from '@/lib/theme-colors';
import {
    reset as resetAppearanceColors,
    update as updateAppearanceColors,
} from '@/routes/appearance/colors';

function cloneDefaults(): ThemeColorSettings {
    return { ...DEFAULT_THEME_COLORS };
}

function isValidHex(hex: string): boolean {
    return /^#[0-9a-fA-F]{6}$/.test(hex);
}

type AppearancePageProps = {
    themeColors?: ThemeColorSettings & { sidebar_active?: ThemeSidebarActive };
    themeColorsCustomized?: boolean;
    canManageTheme?: boolean;
};

export function useThemeColors() {
    const page = usePage();
    const appearance = computed(
        () => page.props as unknown as AppearancePageProps,
    );

    const colors = ref<ThemeColorSettings>(cloneDefaults());
    const isCustomized = ref(false);
    const canManage = computed(() => Boolean(appearance.value.canManageTheme));
    const saving = ref(false);
    let persistTimer: ReturnType<typeof setTimeout> | null = null;

    function hydrateFromProps(): void {
        const fromPage = appearance.value.themeColors;
        if (fromPage) {
            colors.value = normalizeThemeColors(fromPage);
            isCustomized.value = Boolean(
                appearance.value.themeColorsCustomized,
            );
            cacheThemeColors(colors.value);
            applyThemeColors(colors.value);
        }
    }

    onMounted(() => {
        hydrateFromProps();
    });

    onUnmounted(() => {
        if (persistTimer) {
            clearTimeout(persistTimer);
        }
    });

    watch(
        () => appearance.value.themeColors,
        () => hydrateFromProps(),
        { deep: true },
    );

    function commit(next: ThemeColorSettings): void {
        if (!canManage.value) {
            return;
        }

        colors.value = next;
        isCustomized.value = true;
        saveThemeColors(next);
        schedulePersist();
    }

    function setColor(key: keyof ThemeColorSettings, value: string) {
        if (!canManage.value) {
            return;
        }

        if (
            key === 'radius' ||
            key === 'density' ||
            key === 'sidebarActive'
        ) {
            return;
        }

        const normalized = value.startsWith('#') ? value : `#${value}`;

        if (!isValidHex(normalized)) {
            colors.value = { ...colors.value, [key]: normalized };
            return;
        }

        commit({ ...colors.value, [key]: normalized });
    }

    function setRadius(radius: ThemeRadius): void {
        commit({ ...colors.value, radius });
    }

    function setDensity(density: ThemeDensity): void {
        commit({ ...colors.value, density });
    }

    function setSidebarActive(sidebarActive: ThemeSidebarActive): void {
        commit({ ...colors.value, sidebarActive });
    }

    function setShell(shell: ThemeShell): void {
        commit({ ...colors.value, shell });
    }

    function applyPreset(preset: ThemePreset): void {
        commit({ ...preset.settings });
    }

    function schedulePersist(): void {
        if (!canManage.value) {
            return;
        }

        if (persistTimer) {
            clearTimeout(persistTimer);
        }

        persistTimer = setTimeout(() => {
            persist();
        }, 400);
    }

    function persist(): void {
        if (!canManage.value) {
            return;
        }

        saving.value = true;
        router.put(
            updateAppearanceColors.url(),
            toServerThemePayload(colors.value),
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    saving.value = false;
                },
            },
        );
    }

    function resetColors() {
        if (!canManage.value) {
            return;
        }

        if (persistTimer) {
            clearTimeout(persistTimer);
        }

        colors.value = cloneDefaults();
        isCustomized.value = false;
        resetThemeColors();
        applyThemeColors(DEFAULT_THEME_COLORS);
        cacheThemeColors(DEFAULT_THEME_COLORS);

        router.delete(resetAppearanceColors.url(), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return {
        colors,
        isCustomized,
        canManage,
        saving,
        setColor,
        setRadius,
        setDensity,
        setSidebarActive,
        setShell,
        applyPreset,
        resetColors,
        defaults: DEFAULT_THEME_COLORS,
    };
}
