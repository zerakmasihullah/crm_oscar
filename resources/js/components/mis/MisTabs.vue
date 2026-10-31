<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

export type MisTabItem = {
    id: string;
    label: string;
    href?: string;
    icon?: Component;
};

defineProps<{
    tabs: MisTabItem[];
    class?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        :class="
            cn(
                'inline-flex max-w-full flex-wrap gap-0.5 rounded-lg border border-border/60 bg-muted/30 p-1 dark:border-white/10 dark:bg-white/[0.04]',
                $props.class,
            )
        "
        role="tablist"
    >
        <Link
            v-for="tab in tabs.filter((t) => t.href)"
            :key="tab.id"
            :href="tab.href!"
            preserve-scroll
            role="tab"
            :aria-selected="model === tab.id"
            :class="
                cn(
                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition',
                    model === tab.id
                        ? 'bg-card text-foreground shadow-sm dark:bg-white dark:text-zinc-900'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
            @click="model = tab.id"
        >
            <component
                :is="tab.icon"
                v-if="tab.icon"
                class="size-3.5 shrink-0 opacity-70"
            />
            <span>{{ tab.label }}</span>
        </Link>
        <button
            v-for="tab in tabs.filter((t) => !t.href)"
            :key="`btn-${tab.id}`"
            type="button"
            role="tab"
            :aria-selected="model === tab.id"
            :class="
                cn(
                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition',
                    model === tab.id
                        ? 'bg-card text-foreground shadow-sm dark:bg-white dark:text-zinc-900'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
            @click="model = tab.id"
        >
            <component
                :is="tab.icon"
                v-if="tab.icon"
                class="size-3.5 shrink-0 opacity-70"
            />
            <span>{{ tab.label }}</span>
        </button>
    </div>
</template>
