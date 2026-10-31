<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component;
        class?: string;
        contentClass?: string;
    }>(),
    {
        title: undefined,
        description: undefined,
        icon: undefined,
        class: undefined,
        contentClass: undefined,
    },
);
</script>

<template>
    <section :class="cn('space-y-4', $props.class)">
        <div
            v-if="title || description || $slots.title || $slots.actions || icon"
            class="flex flex-wrap items-end justify-between gap-3"
        >
            <div class="min-w-0 space-y-1">
                <h2
                    v-if="title || $slots.title || icon"
                    class="flex items-center gap-2 text-[17px] font-semibold tracking-tight"
                >
                    <component :is="icon" v-if="icon" class="size-4 text-muted-foreground" />
                    <slot name="title">{{ title }}</slot>
                </h2>
                <p
                    v-if="description || $slots.description"
                    class="text-sm text-muted-foreground"
                >
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>
            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
        <div :class="cn(contentClass)">
            <slot />
        </div>
    </section>
</template>
