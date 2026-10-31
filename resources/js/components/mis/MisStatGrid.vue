<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        columns?: 2 | 3 | 4;
        class?: string;
    }>(),
    {
        columns: 3,
        class: undefined,
    },
);
</script>

<template>
    <section
        :class="
            cn(
                'overflow-hidden rounded-2xl border border-border/80 bg-card shadow-soft dark:border-white/10',
                $props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'grid divide-border/70',
                    columns === 2 && 'grid-cols-2 divide-x',
                    columns === 3 &&
                        'grid-cols-1 divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0',
                    columns === 4 &&
                        'grid-cols-2 divide-x divide-y sm:grid-cols-4 sm:divide-y-0',
                )
            "
        >
            <slot />
        </div>
    </section>
</template>
