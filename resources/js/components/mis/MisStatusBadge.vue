<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type StatusTone = 'success' | 'warning' | 'danger' | 'neutral' | 'info';

const props = withDefaults(
    defineProps<{
        status: string;
        label?: string;
        tone?: StatusTone;
        map?: Record<string, StatusTone>;
        class?: string;
    }>(),
    {
        label: undefined,
        tone: undefined,
        map: undefined,
        class: undefined,
    },
);

const defaults: Record<string, StatusTone> = {
    active: 'success',
    success: 'success',
    completed: 'success',
    submitted: 'success',
    sent: 'success',
    ok: 'success',
    enabled: 'success',
    login_success: 'success',
    pending: 'warning',
    processing: 'warning',
    queued: 'warning',
    warning: 'warning',
    paused: 'warning',
    draft: 'neutral',
    inactive: 'neutral',
    disabled: 'danger',
    failed: 'danger',
    error: 'danger',
    danger: 'danger',
    rejected: 'danger',
    login_failed: 'danger',
    info: 'info',
};

const resolvedTone = computed<StatusTone>(() => {
    if (props.tone) {
        return props.tone;
    }

    const key = props.status.toLowerCase().replace(/\s+/g, '_');
    return props.map?.[key] ?? props.map?.[props.status] ?? defaults[key] ?? 'neutral';
});

const display = computed(
    () => props.label ?? props.status.replace(/_/g, ' '),
);

const toneClass = computed(() => {
    switch (resolvedTone.value) {
        case 'success':
            return 'bg-[var(--brand-accent)]/12 text-[color-mix(in_srgb,var(--brand-accent)_55%,#0a0a0a)] ring-[var(--brand-accent)]/20 dark:text-[var(--brand-accent)]';
        case 'warning':
            return 'bg-amber-500/12 text-amber-800 ring-amber-500/20 dark:text-amber-300';
        case 'danger':
            return 'bg-destructive/10 text-destructive ring-destructive/20';
        case 'info':
            return 'bg-sky-500/12 text-sky-800 ring-sky-500/20 dark:text-sky-300';
        default:
            return 'bg-muted text-muted-foreground ring-border/60';
    }
});
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium capitalize ring-1 ring-inset',
                toneClass,
                props.class,
            )
        "
    >
        <slot>{{ display }}</slot>
    </span>
</template>
