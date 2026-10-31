<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        padded?: boolean;
    }>(),
    {
        class: undefined,
        padded: true,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'rounded-2xl border border-border/70 bg-card shadow-soft dark:border-white/10 dark:shadow-[0_1px_0_rgba(255,255,255,0.04)_inset,0_10px_36px_-14px_rgba(0,0,0,0.45)]',
                padded ? 'space-y-6 p-5 sm:p-6 lg:p-7' : '',
                $props.class,
            )
        "
    >
        <slot />
    </div>
</template>
