<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

const tabs = [
    { value: 'light', Icon: Sun, labelKey: 'Light' },
    { value: 'dark', Icon: Moon, labelKey: 'Dark' },
    { value: 'system', Icon: Monitor, labelKey: 'System' },
] as const;
</script>

<template>
    <div
        class="inline-flex w-full gap-1 rounded-2xl border border-border/70 bg-muted/40 p-1.5 sm:w-auto"
        role="tablist"
        :aria-label="t('Color mode')"
    >
        <button
            v-for="{ value, Icon, labelKey } in tabs"
            :key="value"
            type="button"
            role="tab"
            :aria-selected="appearance === value"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium transition sm:flex-none"
            :class="
                appearance === value
                    ? 'bg-card text-foreground shadow-soft'
                    : 'text-muted-foreground hover:bg-card/70 hover:text-foreground'
            "
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="size-4 shrink-0" />
            <span>{{ t(labelKey) }}</span>
        </button>
    </div>
</template>
