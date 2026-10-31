<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        class?: string;
    }>(),
    {
        description: undefined,
        icon: undefined,
        class: undefined,
    },
);
</script>

<template>
    <header
        :class="
            cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                $props.class,
            )
        "
    >
        <div class="min-w-0 space-y-1.5">
            <div class="flex items-center gap-2.5">
                <div
                    v-if="icon || $slots.icon"
                    class="flex size-10 shrink-0 items-center justify-center rounded-2xl border border-border/70 bg-muted/40 text-foreground"
                >
                    <slot name="icon">
                        <component :is="icon" v-if="icon" class="size-5" />
                    </slot>
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-[1.7rem]">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description || $slots.description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        <slot name="description">{{ description }}</slot>
                    </p>
                </div>
            </div>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-2 sm:justify-end"
        >
            <slot name="actions" />
        </div>
        <div v-if="$slots.meta" class="w-full sm:w-auto">
            <slot name="meta" />
        </div>
    </header>
</template>
