<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: string;
    }>(),
    {
        class: undefined,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                $props.class,
            )
        "
    >
        <div class="min-w-0 flex-1">
            <slot name="start" />
            <slot />
        </div>
        <div
            v-if="$slots.end"
            class="flex flex-wrap items-center gap-2 sm:justify-end"
        >
            <slot name="end" />
        </div>
    </div>
</template>
