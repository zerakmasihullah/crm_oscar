<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component;
        class?: string;
    }>(),
    {
        title: undefined,
        description: undefined,
        icon: undefined,
        class: undefined,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col items-center justify-center gap-2 rounded-[var(--radius-control,0.75rem)] border border-dashed border-border bg-muted/20 px-6 py-12 text-center',
                $props.class,
            )
        "
    >
        <div
            v-if="icon || $slots.icon"
            class="mb-1 flex size-11 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
        >
            <slot name="icon">
                <component :is="icon" v-if="icon" class="size-5" />
            </slot>
        </div>
        <p v-if="title || $slots.title" class="text-sm font-medium text-foreground">
            <slot name="title">{{ title }}</slot>
        </p>
        <p
            v-if="description || $slots.description"
            class="max-w-sm text-sm text-muted-foreground"
        >
            <slot name="description">{{ description }}</slot>
        </p>
        <div v-if="$slots.default" class="text-sm text-muted-foreground">
            <slot />
        </div>
        <div
            v-if="$slots.actions"
            class="mt-3 flex flex-wrap items-center justify-center gap-2"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
