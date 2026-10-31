<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
    }>(),
    {
        class: undefined,
    },
);
</script>

<template>
    <div :class="cn('space-y-4', $props.class)">
        <div
            v-if="$slots.tabs || $slots.actions"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <div class="min-w-0 flex-1">
                <slot name="tabs" />
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>

        <slot />
    </div>
</template>
