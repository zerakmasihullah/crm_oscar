<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

export type MisSegmentItem = {
    id: string;
    label: string;
    href?: string;
    icon?: Component;
};

defineProps<{
    items: MisSegmentItem[];
    class?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div
        :class="
            cn(
                'inline-flex w-full gap-1 rounded-2xl border border-border/70 bg-muted/40 p-1.5 sm:w-auto dark:border-white/10 dark:bg-white/[0.04]',
                $props.class,
            )
        "
        role="tablist"
    >
        <Link
            v-for="item in items.filter((i) => i.href)"
            :key="item.id"
            :href="item.href!"
            preserve-scroll
            role="tab"
            :aria-selected="model === item.id"
            :class="
                cn(
                    'flex flex-1 items-center justify-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium transition sm:flex-none',
                    model === item.id
                        ? 'bg-card text-foreground shadow-soft dark:bg-white dark:text-zinc-900'
                        : 'text-muted-foreground hover:bg-card/70 hover:text-foreground',
                )
            "
            @click="model = item.id"
        >
            <component
                :is="item.icon"
                v-if="item.icon"
                class="size-4 shrink-0"
            />
            <span>{{ item.label }}</span>
        </Link>
        <button
            v-for="item in items.filter((i) => !i.href)"
            :key="`btn-${item.id}`"
            type="button"
            role="tab"
            :aria-selected="model === item.id"
            :class="
                cn(
                    'flex flex-1 items-center justify-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium transition sm:flex-none',
                    model === item.id
                        ? 'bg-card text-foreground shadow-soft dark:bg-white dark:text-zinc-900'
                        : 'text-muted-foreground hover:bg-card/70 hover:text-foreground',
                )
            "
            @click="model = item.id"
        >
            <component
                :is="item.icon"
                v-if="item.icon"
                class="size-4 shrink-0"
            />
            <span>{{ item.label }}</span>
        </button>
    </div>
</template>
