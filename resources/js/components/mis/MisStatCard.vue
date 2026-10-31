<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        hint?: string | null;
        icon?: Component;
        /** Visual tone for KPI emphasis. */
        tone?: 'default' | 'muted' | 'success' | 'warning' | 'danger' | 'info';
        class?: string;
    }>(),
    {
        hint: null,
        icon: undefined,
        tone: 'default',
        class: undefined,
    },
);

const toneStyles = computed(() => {
    switch (props.tone) {
        case 'success':
            return {
                icon: 'bg-[var(--brand-accent)]/10 text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]',
                value: 'text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] dark:text-[var(--brand-accent)]',
            };
        case 'warning':
            return {
                icon: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                value: 'text-amber-700 dark:text-amber-300',
            };
        case 'danger':
            return {
                icon: 'bg-destructive/10 text-destructive',
                value: 'text-destructive',
            };
        case 'info':
            return {
                icon: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
                value: 'text-sky-700 dark:text-sky-300',
            };
        case 'muted':
            return {
                icon: 'bg-muted text-muted-foreground',
                value: 'text-foreground',
            };
        default:
            return {
                icon: 'bg-zinc-900/5 text-zinc-700 dark:bg-white/10 dark:text-zinc-200',
                value: 'text-zinc-700 dark:text-zinc-200',
            };
    }
});
</script>

<template>
    <div
        :class="
            cn(
                'flex items-center gap-3 px-4 py-3.5 sm:px-5',
                $props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    toneStyles.icon,
                )
            "
        >
            <component :is="icon" v-if="icon" class="size-4" />
            <span
                v-else
                class="size-1.5 rounded-full bg-current opacity-70"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 text-muted-foreground">
                <span
                    class="text-[11px] font-semibold tracking-[0.12em] uppercase"
                >
                    {{ label }}
                </span>
                <div v-if="$slots.trailing" class="ms-auto">
                    <slot name="trailing" />
                </div>
            </div>

            <p
                :class="
                    cn(
                        'mt-0.5 text-xl font-semibold tracking-tight tabular-nums sm:text-2xl',
                        toneStyles.value,
                    )
                "
            >
                {{ value }}
            </p>

            <p v-if="hint" class="mt-0.5 text-xs text-muted-foreground">
                {{ hint }}
            </p>

            <slot />
        </div>
    </div>
</template>
