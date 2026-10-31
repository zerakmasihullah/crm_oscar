<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

withDefaults(
    defineProps<{
        /** `row` = full-width preference row for account/settings */
        variant?: 'row' | 'icon';
    }>(),
    { variant: 'row' },
);

const STORAGE_KEY = 'storefront-theme';
const mounted = ref(false);
const isDark = ref(false);

type ThemePref = 'light' | 'dark' | 'system';

function resolveDark(pref: ThemePref): boolean {
    if (pref === 'dark') return true;
    if (pref === 'light') return false;
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function readPref(): ThemePref {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark' || stored === 'system') return stored;
    // Legacy / Next default: dark when unset
    return stored ? 'dark' : 'system';
}

function applyTheme(pref: ThemePref) {
    const dark = resolveDark(pref);
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem(STORAGE_KEY, pref);
}

function toggle() {
    // Cycle: system → light → dark → system would be complex for icon;
    // icon toggles between light and dark explicitly.
    applyTheme(isDark.value ? 'light' : 'dark');
}

let media: MediaQueryList | null = null;
function onSystemChange() {
    if (readPref() === 'system') applyTheme('system');
}

onMounted(() => {
    applyTheme(readPref());
    mounted.value = true;
    media = window.matchMedia('(prefers-color-scheme: dark)');
    media.addEventListener('change', onSystemChange);
});

onUnmounted(() => {
    media?.removeEventListener('change', onSystemChange);
});
</script>

<template>
    <button
        v-if="variant === 'icon'"
        type="button"
        class="inline-flex items-center justify-center size-9 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition"
        :aria-label="mounted ? `Switch to ${isDark ? 'light' : 'dark'} mode` : 'Toggle theme'"
        @click="toggle"
    >
        <Sun v-if="!mounted || !isDark" class="w-4 h-4" />
        <Moon v-else class="w-4 h-4" />
    </button>

    <button
        v-else
        type="button"
        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-border bg-muted/40 hover:bg-muted transition text-left"
        :aria-label="mounted ? `Switch to ${isDark ? 'light' : 'dark'} mode` : 'Toggle theme'"
        @click="toggle"
    >
        <span class="inline-flex items-center justify-center size-9 rounded-lg bg-background border border-border shrink-0">
            <Sun v-if="!mounted || !isDark" class="w-4 h-4 text-orange-500" />
            <Moon v-else class="w-4 h-4 text-foreground" />
        </span>
        <span class="flex-1 min-w-0">
            <span class="block text-sm font-semibold text-foreground">Appearance</span>
            <span class="block text-xs text-muted-foreground mt-0.5">
                {{ !mounted ? 'Theme' : isDark ? 'Dark mode' : 'Light mode' }}
            </span>
        </span>
        <span
            class="relative w-10 h-5 rounded-full transition shrink-0"
            :class="isDark ? 'bg-orange-500' : 'bg-muted-foreground/30'"
        >
            <span
                class="absolute top-0.5 left-0.5 size-4 rounded-full bg-white shadow transition"
                :class="isDark ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
