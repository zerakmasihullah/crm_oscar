<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    Info,
    TriangleAlert,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        tone?: 'info' | 'success' | 'warning' | 'danger';
        title?: string;
        class?: string;
        icon?: Component | null;
    }>(),
    {
        tone: 'info',
        title: undefined,
        class: undefined,
        icon: undefined,
    },
);

const toneClass: Record<string, string> = {
    info: 'border-border/80 bg-muted/40 text-foreground',
    success:
        'border-[var(--brand-accent)]/30 bg-[var(--brand-accent)]/5 text-[color-mix(in_srgb,var(--brand-accent)_35%,#0a0a0a)] dark:text-[color-mix(in_srgb,var(--brand-accent)_70%,white)]',
    warning:
        'border-amber-500/30 bg-amber-500/5 text-amber-950 dark:text-amber-100',
    danger:
        'border-destructive/30 bg-destructive/5 text-destructive',
};

const defaultIcon = computed(() => {
    if (props.icon === null) {
        return null;
    }
    if (props.icon) {
        return props.icon;
    }
    const map = {
        info: Info,
        success: CheckCircle2,
        warning: TriangleAlert,
        danger: AlertCircle,
    } as const;
    return map[props.tone];
});
</script>

<template>
    <div
        role="status"
        :class="
            cn(
                'flex gap-3 rounded-xl border px-3.5 py-3 text-sm',
                toneClass[tone],
                $props.class,
            )
        "
    >
        <component
            :is="defaultIcon"
            v-if="defaultIcon"
            class="mt-0.5 size-4 shrink-0 opacity-80"
        />
        <div class="min-w-0 flex-1 space-y-0.5">
            <p v-if="title" class="font-medium">{{ title }}</p>
            <div class="text-sm opacity-90">
                <slot />
            </div>
        </div>
        <div v-if="$slots.actions" class="shrink-0 self-start">
            <slot name="actions" />
        </div>
    </div>
</template>
