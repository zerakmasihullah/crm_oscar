<script setup lang="ts">
import { Check, RotateCcw, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useThemeColors } from '@/composables/useThemeColors';
import { useTranslations } from '@/composables/useTranslations';
import {
    THEME_COLOR_FIELDS,
    THEME_DENSITY_OPTIONS,
    THEME_PRESETS,
    THEME_RADIUS_OPTIONS,
    THEME_SHELL_OPTIONS,
    THEME_SIDEBAR_ACTIVE_OPTIONS,
    settingsMatchPreset,
    type ThemeDensity,
    type ThemeRadius,
    type ThemeShell,
    type ThemeSidebarActive,
} from '@/lib/theme-colors';

const { t } = useTranslations();
const {
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
} = useThemeColors();

const activePresetId = computed(
    () =>
        THEME_PRESETS.find((preset) =>
            settingsMatchPreset(colors.value, preset),
        )?.id ?? null,
);

const previewActiveClass = computed(() => {
    if (colors.value.sidebarActive === 'accent') {
        return {
            background: colors.value.accent,
            color: '#ffffff',
        };
    }

    if (colors.value.sidebarActive === 'soft') {
        return {
            background: 'rgba(255,255,255,0.16)',
            color: '#ffffff',
        };
    }

    return {
        background: '#ffffff',
        color: colors.value.brand,
    };
});

const previewRadius = computed(() => {
    if (colors.value.radius === 'soft') return '1.125rem';
    if (colors.value.radius === 'sharp') return '0.375rem';
    return '0.875rem';
});
</script>

<template>
    <div class="space-y-6" data-tour="theme-color-settings">
        <!-- Live shell preview -->
        <Card class="overflow-hidden border-border/70 shadow-soft">
            <CardHeader
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="size-4 text-primary" />
                        {{ t('Theme studio') }}
                    </CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'Preview and tune the admin shell — colors, corners, density, and active sidebar style.',
                            )
                        }}
                    </CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="saving"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('Saving…') }}
                    </span>
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="shrink-0"
                        :disabled="!isCustomized"
                        @click="resetColors"
                    >
                        <RotateCcw class="size-4" />
                        {{ t('Reset to default') }}
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-5">
                <div
                    class="overflow-hidden rounded-[1.25rem] border border-border/60 shadow-soft"
                    :style="{ background: colors.backgroundLight }"
                >
                    <div class="flex min-h-[168px]">
                        <aside
                            class="flex w-[112px] shrink-0 flex-col gap-2 p-3 sm:w-[132px]"
                            :style="{
                                background: colors.brand,
                                borderRadius: `0 ${previewRadius} ${previewRadius} 0`,
                            }"
                        >
                            <div class="mb-1 flex items-center gap-2 px-1">
                                <span
                                    class="size-6 rounded-md"
                                    :style="{ background: colors.secondary }"
                                />
                                <span
                                    class="hidden h-2 w-12 rounded-full bg-white/40 sm:block"
                                />
                            </div>
                            <div
                                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[10px] font-semibold"
                                :style="previewActiveClass"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :style="{
                                        background:
                                            colors.sidebarActive === 'pill'
                                                ? colors.brand
                                                : '#ffffff',
                                    }"
                                />
                                {{ t('Dashboard') }}
                            </div>
                            <div
                                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-[10px] font-medium text-white/70"
                            >
                                <span class="size-1.5 rounded-full bg-white/35" />
                                {{ t('Settings') }}
                            </div>
                            <div
                                class="mt-auto h-7 rounded-lg"
                                :style="{
                                    background: 'rgba(255,255,255,0.08)',
                                }"
                            />
                        </aside>
                        <div class="flex min-w-0 flex-1 flex-col gap-3 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div class="space-y-1.5">
                                    <div
                                        class="h-2.5 w-24 rounded-full"
                                        :style="{
                                            background: colors.brand,
                                            opacity: 0.85,
                                        }"
                                    />
                                    <div
                                        class="h-2 w-36 max-w-full rounded-full bg-black/10"
                                    />
                                </div>
                                <div
                                    class="rounded-full px-3 py-1.5 text-[10px] font-semibold text-white"
                                    :style="{
                                        background: colors.accent,
                                        borderRadius: previewRadius,
                                    }"
                                >
                                    {{ t('Primary action') }}
                                </div>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <div
                                    v-for="tone in [
                                        colors.brand,
                                        colors.secondary,
                                        colors.accent,
                                    ]"
                                    :key="tone"
                                    class="h-14 border border-black/5 bg-white/80 shadow-sm dark:border-white/10 dark:bg-white/5"
                                    :style="{
                                        borderRadius: previewRadius,
                                        borderTop: `3px solid ${tone}`,
                                    }"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    canManage
                                        ? t(
                                              'Changes apply instantly and save for the whole organization.',
                                          )
                                        : t(
                                              'Only administrators can change organization theme colors.',
                                          )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Presets -->
                <div class="space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold tracking-tight">
                            {{ t('Presets') }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    'Start from a curated palette, then fine-tune below.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="preset in THEME_PRESETS"
                            :key="preset.id"
                            type="button"
                            class="group relative rounded-2xl border p-3 text-start transition"
                            :class="
                                activePresetId === preset.id
                                    ? 'border-primary bg-primary/5 shadow-soft'
                                    : 'border-border/70 bg-card hover:border-primary/40 hover:bg-muted/40'
                            "
                            :disabled="!canManage"
                            @click="applyPreset(preset)"
                        >
                            <div class="mb-3 flex items-center gap-1.5">
                                <span
                                    class="size-5 rounded-full ring-2 ring-white dark:ring-card"
                                    :style="{
                                        background: preset.settings.brand,
                                    }"
                                />
                                <span
                                    class="size-5 rounded-full ring-2 ring-white dark:ring-card"
                                    :style="{
                                        background: preset.settings.secondary,
                                    }"
                                />
                                <span
                                    class="size-5 rounded-full ring-2 ring-white dark:ring-card"
                                    :style="{
                                        background: preset.settings.accent,
                                    }"
                                />
                                <Check
                                    v-if="activePresetId === preset.id"
                                    class="ms-auto size-4 text-primary"
                                />
                            </div>
                            <p class="text-sm font-semibold tracking-tight">
                                {{ t(preset.labelKey) }}
                            </p>
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- App layout (shell) -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('App layout') }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            'Pick how navigation frames the ERP. Floating is the default look.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="option in THEME_SHELL_OPTIONS"
                        :key="option.value"
                        type="button"
                        class="group rounded-2xl border p-3 text-start transition"
                        :class="
                            colors.shell === option.value
                                ? 'border-primary bg-primary/[0.06] shadow-soft ring-1 ring-primary/20'
                                : 'border-border/70 hover:border-primary/30 hover:bg-muted/30'
                        "
                        :disabled="!canManage"
                        @click="setShell(option.value as ThemeShell)"
                    >
                        <div
                            class="mb-3 flex h-20 overflow-hidden rounded-xl border border-border/50"
                            :style="{
                                background:
                                    option.value === 'sidebar-inset'
                                        ? colors.backgroundLight
                                        : 'color-mix(in srgb, var(--muted) 55%, transparent)',
                            }"
                        >
                            <div
                                v-if="option.value === 'header'"
                                class="flex w-full flex-col"
                            >
                                <div
                                    class="flex h-5 items-center gap-1 px-2"
                                    :style="{ background: colors.brand }"
                                >
                                    <span class="size-1.5 rounded-full bg-white/70" />
                                    <span class="h-1 w-8 rounded-full bg-white/35" />
                                    <span class="ms-auto h-1 w-6 rounded-full bg-white/25" />
                                </div>
                                <div class="flex flex-1 flex-col gap-1.5 p-2.5">
                                    <div class="h-2 w-1/3 rounded-full bg-black/10" />
                                    <div class="h-8 flex-1 rounded-md bg-white/80 dark:bg-white/5" />
                                </div>
                            </div>

                            <template v-else-if="option.value === 'sidebar-floating'">
                                <div
                                    class="m-2 w-7 shrink-0 rounded-lg shadow-sm"
                                    :style="{ background: colors.brand }"
                                />
                                <div class="my-2 me-2 flex-1 rounded-lg bg-white/85 dark:bg-white/5" />
                            </template>

                            <template v-else-if="option.value === 'sidebar-inset'">
                                <div
                                    class="h-full w-6 shrink-0"
                                    :style="{ background: colors.brand }"
                                />
                                <div
                                    class="m-2 flex-1 rounded-lg border border-black/5 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
                                />
                            </template>

                            <template v-else>
                                <div
                                    class="h-full w-6 shrink-0 border-e border-white/10"
                                    :style="{ background: colors.brand }"
                                />
                                <div class="flex flex-1 flex-col">
                                    <div
                                        class="h-4 border-b border-black/5 bg-white/70 dark:border-white/10 dark:bg-white/5"
                                    />
                                    <div class="flex-1 bg-white/40 dark:bg-transparent" />
                                </div>
                            </template>
                        </div>

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold tracking-tight">
                                    {{ t(option.labelKey) }}
                                </p>
                            </div>
                            <span
                                v-if="colors.shell === option.value"
                                class="mt-0.5 size-2 shrink-0 rounded-full bg-primary"
                            />
                        </div>
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Layout options -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('Layout & chrome') }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            'Control corner radius, spacing density, and how the active sidebar item looks.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label>{{ t('Corner radius') }}</Label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="option in THEME_RADIUS_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-start transition"
                            :class="
                                colors.radius === option.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border/70 hover:bg-muted/50'
                            "
                            :disabled="!canManage"
                            @click="setRadius(option.value as ThemeRadius)"
                        >
                            <div
                                class="mb-2 h-8 border border-border bg-muted/60"
                                :style="{
                                    borderRadius:
                                        option.value === 'soft'
                                            ? '1rem'
                                            : option.value === 'sharp'
                                              ? '0.25rem'
                                              : '0.7rem',
                                }"
                            />
                            <p class="text-sm font-semibold">
                                {{ t(option.labelKey) }}
                            </p>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('Density') }}</Label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in THEME_DENSITY_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-start transition"
                            :class="
                                colors.density === option.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border/70 hover:bg-muted/50'
                            "
                            :disabled="!canManage"
                            @click="setDensity(option.value as ThemeDensity)"
                        >
                            <div class="mb-2 flex flex-col gap-1">
                                <div
                                    v-for="n in option.value === 'compact'
                                        ? 4
                                        : 3"
                                    :key="n"
                                    class="rounded-md bg-muted"
                                    :class="
                                        option.value === 'compact'
                                            ? 'h-2'
                                            : 'h-2.5'
                                    "
                                />
                            </div>
                            <p class="text-sm font-semibold">
                                {{ t(option.labelKey) }}
                            </p>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('Sidebar active style') }}</Label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="option in THEME_SIDEBAR_ACTIVE_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-start transition"
                            :class="
                                colors.sidebarActive === option.value
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border/70 hover:bg-muted/50'
                            "
                            :disabled="!canManage"
                            @click="
                                setSidebarActive(
                                    option.value as ThemeSidebarActive,
                                )
                            "
                        >
                            <div
                                class="mb-2 flex h-10 items-center rounded-lg px-2"
                                :style="{ background: colors.brand }"
                            >
                                <span
                                    class="rounded-md px-2 py-1 text-[10px] font-semibold"
                                    :style="
                                        option.value === 'pill'
                                            ? {
                                                  background: '#fff',
                                                  color: colors.brand,
                                              }
                                            : option.value === 'accent'
                                              ? {
                                                    background: colors.accent,
                                                    color: '#fff',
                                                }
                                              : {
                                                    background:
                                                        'rgba(255,255,255,0.16)',
                                                    color: '#fff',
                                                }
                                    "
                                >
                                    Nav
                                </span>
                            </div>
                            <p class="text-sm font-semibold">
                                {{ t(option.labelKey) }}
                            </p>
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Color fields -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('Theme colors') }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            'Customize sidebar, brand, and accent colors across the entire application.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div
                        v-for="field in THEME_COLOR_FIELDS"
                        :key="field.key"
                        class="space-y-2"
                    >
                        <Label :for="`theme-${field.key}`">{{
                            t(field.labelKey)
                        }}</Label>
                        <div class="flex items-center gap-2">
                            <div
                                class="relative size-10 shrink-0 overflow-hidden rounded-xl border border-border shadow-sm"
                            >
                                <input
                                    :id="`theme-${field.key}`"
                                    type="color"
                                    class="absolute inset-0 size-[150%] -translate-x-1/4 -translate-y-1/4 cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
                                    :value="colors[field.key]"
                                    :disabled="!canManage"
                                    @input="
                                        setColor(
                                            field.key,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </div>
                            <Input
                                class="font-mono text-sm uppercase"
                                :model-value="colors[field.key]"
                                maxlength="7"
                                :disabled="!canManage"
                                @update:model-value="
                                    (value) =>
                                        setColor(field.key, String(value ?? ''))
                                "
                            />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
