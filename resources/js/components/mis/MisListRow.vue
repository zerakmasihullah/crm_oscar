<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: string;
        as?: string;
    }>(),
    {
        class: undefined,
        as: 'div',
    },
);
</script>

<template>
    <component
        :is="as"
        data-dust-row
        :class="cn('ui-list-row', $props.class)"
    >
        <slot />
    </component>
</template>
