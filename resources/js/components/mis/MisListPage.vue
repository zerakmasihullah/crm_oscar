<script setup lang="ts">
import type { Component } from 'vue';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        /** Hide the outer Card and render a flat section instead. */
        flat?: boolean;
        class?: string;
        contentClass?: string;
        headerClass?: string;
    }>(),
    {
        description: undefined,
        icon: undefined,
        flat: false,
        class: undefined,
        contentClass: undefined,
        headerClass: undefined,
    },
);
</script>

<template>
    <div :class="cn('space-y-6', $props.class)">
        <component :is="flat ? 'section' : Card" :class="flat ? 'space-y-4' : undefined">
            <component
                :is="flat ? 'div' : CardHeader"
                :class="cn(flat && 'space-y-1', headerClass)"
            >
                <div
                    :class="
                        flat
                            ? 'flex flex-wrap items-start justify-between gap-3'
                            : undefined
                    "
                >
                    <div class="min-w-0 space-y-1">
                        <component
                            :is="flat ? 'h2' : CardTitle"
                            :class="
                                cn(
                                    'flex items-center gap-2',
                                    flat && 'text-lg font-semibold tracking-tight',
                                )
                            "
                        >
                            <component :is="icon" v-if="icon" class="size-5" />
                            {{ title }}
                        </component>
                        <component
                            :is="flat ? 'p' : CardDescription"
                            v-if="description || $slots.description"
                            :class="flat ? 'text-sm text-muted-foreground' : undefined"
                        >
                            <slot name="description">{{ description }}</slot>
                        </component>
                    </div>
                    <component
                        :is="flat ? 'div' : CardAction"
                        v-if="$slots.actions"
                        :class="
                            cn(
                                'flex flex-wrap items-center gap-2',
                                flat && 'shrink-0',
                            )
                        "
                    >
                        <slot name="actions" />
                    </component>
                </div>
                <div v-if="$slots.toolbar" class="mt-3 w-full">
                    <slot name="toolbar" />
                </div>
            </component>
            <component
                :is="flat ? 'div' : CardContent"
                :class="cn(flat ? 'space-y-4' : 'space-y-4', contentClass)"
            >
                <slot />
                <slot name="pagination" />
            </component>
        </component>
        <slot name="below" />
    </div>
</template>
